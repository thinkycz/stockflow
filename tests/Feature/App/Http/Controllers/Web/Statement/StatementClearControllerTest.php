<?php

declare(strict_types=1);

use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('user can clear a statement', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 1)->create();
    $day = StatementDay::factory()->for($statement, 'statement')->create([
        'cash' => 100,
        'card' => 50,
        'wolt' => 20,
        'bolt' => 10,
        'foodora' => 5,
        'total' => 185,
    ]);

    $response = $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/statements/' . $statement->getKey() . '/clear');

    $response->assertRedirect();
    \assertInertiaFlash($response, 'success', \__('Statement cleared.'));

    $day->refresh();
    \expect($day->getCash())->toBe(0.0);
    \expect($day->getCard())->toBe(0.0);
    \expect($day->getWolt())->toBe(0.0);
    \expect($day->getBolt())->toBe(0.0);
    \expect($day->getFoodora())->toBe(0.0);
    \expect($day->getTotal())->toBe(0.0);
});

\test('clear controller rejects another user\'s statement', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $other->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 1)->create();

    $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/statements/' . $statement->getKey() . '/clear')
        ->assertNotFound();
});

\test('limited user can clear a statement for their assigned store', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 1)->create();
    StatementDay::factory()->for($statement, 'statement')->create([
        'cash' => 100,
        'card' => 50,
        'wolt' => 20,
        'bolt' => 10,
        'foodora' => 5,
        'total' => 185,
    ]);

    $response = $this->actingAs($limited, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/statements/' . $statement->getKey() . '/clear');

    $response->assertRedirect();
    \assertInertiaFlash($response, 'success', \__('Statement cleared.'));
});

\test('limited user cannot clear a statement for a different store', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $storeA = Store::factory()->create(['user_id' => $admin->getKey()]);
    $storeB = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($storeA)->createOne(), User::class);
    $statement = Statement::factory()->forStore($storeB)->forMonth(2026, 1)->create();

    $this->actingAs($limited, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/statements/' . $statement->getKey() . '/clear')
        ->assertForbidden();
});
