<?php

declare(strict_types=1);

use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use App\Models\User;
use App\Services\StatementService;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Typer;

\afterEach(function (): void {
    Carbon::setTestNow();
});

\test('user can save only todays amounts and return to the selected historical month', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Europe/Prague'));
    [$user, $store] = \createIsolatedUserWithWarehouse();
    $statement = \app(StatementService::class)->findOrCreateForMonth($user, $store, 2026, 7);
    $today = $statement->days()->whereDate('date', '2026-07-23')->firstOrFail();
    $otherDay = $statement->days()->whereDate('date', '2026-07-22')->firstOrFail();
    $otherDay->update(['cash' => 50, 'total' => 50]);

    $response = $this->be($user, 'users')
        ->from('/statements?store_id=' . $store->getKey() . '&year=2026&month=6')
        ->put('/statements/' . $statement->getKey() . '/today', [
            'cash' => 100.5,
            'card' => 50.25,
            'wolt' => 30,
            'bolt' => 20,
            'bolt_cash' => 15,
            'foodora' => 10,
        ]);

    $response->assertRedirect('/statements?store_id=' . $store->getKey() . '&year=2026&month=6');
    \assertInertiaFlash($response, 'success', \__('Statement saved.'));

    $today->refresh();
    $otherDay->refresh();
    \expect($today->getCash())->toBe(100.5)
        ->and($today->getCard())->toBe(50.25)
        ->and($today->getTotal())->toBe(225.75)
        ->and($otherDay->getCash())->toBe(50.0);
});

\test('today update rejects negative and invalid amounts', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Europe/Prague'));
    [$user, $store] = \createIsolatedUserWithWarehouse();
    $statement = \app(StatementService::class)->findOrCreateForMonth($user, $store, 2026, 7);

    $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->put('/statements/' . $statement->getKey() . '/today', [
            'cash' => -1,
            'card' => 'invalid',
            'wolt' => 0,
            'bolt' => 0,
            'bolt_cash' => 0,
            'foodora' => 0,
        ])
        ->assertStatus(422);

    \expect($statement->days()->whereDate('date', '2026-07-23')->firstOrFail()->getTotal())->toBe(0.0);
});

\test('today update rejects another users statement and a historical statement', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Europe/Prague'));
    [$user] = \createIsolatedUserWithWarehouse();
    [$other, $otherStore] = \createIsolatedUserWithWarehouse();
    $foreign = Statement::factory()->forStore($otherStore)->forMonth(2026, 7)->create();
    $historicalStore = Store::factory()->create(['user_id' => $user->getKey()]);
    $historical = Statement::factory()->forStore($historicalStore)->forMonth(2026, 6)->create();
    $payload = [
        'cash' => 1,
        'card' => 0,
        'wolt' => 0,
        'bolt' => 0,
        'bolt_cash' => 0,
        'foodora' => 0,
    ];

    $this->be($user, 'users')
        ->put('/statements/' . $foreign->getKey() . '/today', $payload)
        ->assertNotFound();
    $this->be($user, 'users')
        ->put('/statements/' . $historical->getKey() . '/today', $payload)
        ->assertNotFound();

    \expect($other)->toBeInstanceOf(User::class);
});

\test('limited user can update today only for the assigned store', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Europe/Prague'));
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $assignedStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($assignedStore)->createOne(), User::class);
    $assigned = \app(StatementService::class)->findOrCreateForMonth($admin, $assignedStore, 2026, 7);
    $other = \app(StatementService::class)->findOrCreateForMonth($admin, $otherStore, 2026, 7);
    $payload = [
        'cash' => 1,
        'card' => 0,
        'wolt' => 0,
        'bolt' => 0,
        'bolt_cash' => 0,
        'foodora' => 0,
    ];

    $this->actingAs($limited, 'users')
        ->put('/statements/' . $assigned->getKey() . '/today', $payload)
        ->assertRedirect();
    $this->actingAs($limited, 'users')
        ->put('/statements/' . $other->getKey() . '/today', $payload)
        ->assertForbidden();

    $today = $assigned->days()->whereDate('date', '2026-07-23')->first();
    \assert($today instanceof StatementDay);
    \expect($today->getCash())->toBe(1.0);
});
