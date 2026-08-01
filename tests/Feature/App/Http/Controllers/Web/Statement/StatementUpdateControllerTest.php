<?php

declare(strict_types=1);

use App\Models\AttendanceSession;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Typer;

\afterEach(function (): void {
    Carbon::setTestNow();
});

\test('user can save daily amounts on a statement', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 6)->create();
    StatementDay::factory()->for($statement, 'statement')->create();

    $dayOne = $statement->days()->orderBy('date')->first();
    \assert($dayOne instanceof StatementDay);

    $response = $this->be($user, 'users')
        ->put('/statements/' . $statement->getKey(), [
            'days' => [
                [
                    'date' => $dayOne->getDate(),
                    'cash' => 100.5,
                    'card' => 50.25,
                    'wolt' => 30,
                    'bolt' => 20,
                    'bolt_cash' => 15,
                    'foodora' => 10,
                ],
            ],
        ]);

    $response->assertRedirect();
    \assertInertiaFlash($response, 'success', \__('Statement saved.'));

    $dayOne->refresh();
    \expect($dayOne->getCash())->toBe(100.5);
    \expect($dayOne->getCard())->toBe(50.25);
    \expect($dayOne->getWolt())->toBe(30.0);
    \expect($dayOne->getBolt())->toBe(20.0);
    \expect($dayOne->getBoltCash())->toBe(15.0);
    \expect($dayOne->getFoodora())->toBe(10.0);
    \expect($dayOne->getTotal())->toBe(225.75);
});

\test('update controller recalculates totals as the sum of channels', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 1)->create();
    $day = StatementDay::factory()->for($statement, 'statement')->create();

    $this->be($user, 'users')
        ->put('/statements/' . $statement->getKey(), [
            'days' => [
                [
                    'date' => $day->getDate(),
                    'cash' => 0,
                    'card' => 100,
                    'wolt' => 0,
                    'bolt' => 0,
                    'bolt_cash' => 0,
                    'foodora' => 0,
                ],
            ],
        ])
        ->assertRedirect();

    $day->refresh();
    \expect($day->getTotal())->toBe(100.0);
});

\test('update controller rejects negative amounts', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 1)->create();
    $day = StatementDay::factory()->for($statement, 'statement')->create();

    $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->put('/statements/' . $statement->getKey(), [
            'days' => [
                [
                    'date' => $day->getDate(),
                    'cash' => -1,
                    'card' => 0,
                    'wolt' => 0,
                    'bolt' => 0,
                    'bolt_cash' => 0,
                    'foodora' => 0,
                ],
            ],
        ])
        ->assertRedirect()->assertSessionHasErrors();
});

\test('update controller rejects another user\'s statement', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $other->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 1)->create();

    $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->put('/statements/' . $statement->getKey(), [
            'days' => [
                [
                    'date' => '2026-01-01',
                    'cash' => 1,
                    'card' => 0,
                    'wolt' => 0,
                    'bolt' => 0,
                    'foodora' => 0,
                ],
            ],
        ])
        ->assertNotFound();
});

\test('limited user can update a statement for their assigned store', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 6)->create();
    StatementDay::factory()->for($statement, 'statement')->create();

    $dayOne = $statement->days()->orderBy('date')->first();
    \assert($dayOne instanceof StatementDay);

    $response = $this->actingAs($limited, 'users')
        ->put('/statements/' . $statement->getKey(), [
            'days' => [
                [
                    'date' => $dayOne->getDate(),
                    'cash' => 100,
                    'card' => 50,
                    'wolt' => 30,
                    'bolt' => 20,
                    'bolt_cash' => 15,
                    'foodora' => 10,
                ],
            ],
        ]);

    $response->assertRedirect();
    \assertInertiaFlash($response, 'success', \__('Statement saved.'));

    $dayOne->refresh();
    \expect($dayOne->getCash())->toBe(100.0);
    \expect($dayOne->getTotal())->toBe(225.0);
});

\test('limited user cannot update a statement for a different store', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $storeA = Store::factory()->create(['user_id' => $admin->getKey()]);
    $storeB = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($storeA)->createOne(), User::class);
    $statement = Statement::factory()->forStore($storeB)->forMonth(2026, 1)->create();
    StatementDay::factory()->for($statement, 'statement')->create();

    $day = $statement->days()->orderBy('date')->first();
    \assert($day instanceof StatementDay);

    $this->actingAs($limited, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->put('/statements/' . $statement->getKey(), [
            'days' => [
                [
                    'date' => $day->getDate(),
                    'cash' => 1,
                    'card' => 0,
                    'wolt' => 0,
                    'bolt' => 0,
                    'bolt_cash' => 0,
                    'foodora' => 0,
                ],
            ],
        ])
        ->assertForbidden();
});

\test('limited user can save the current month and close all active current-day attendances', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Europe/Prague'));
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 7)->create();
    $day = StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-07-23']);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $session = AttendanceSession::query()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'created_by_user_id' => $limited->getKey(),
        'active_worker_id' => $worker->getKey(),
        'hourly_rate' => $worker->getHourlyRate(),
        'started_at' => Carbon::parse('2026-07-23 08:00:00', 'Europe/Prague')->utc(),
    ]);

    $this->actingAs($limited, 'users')
        ->put('/statements/' . $statement->getKey(), [
            'days' => [[
                'date' => $day->getDate(),
                'cash' => 125,
                'card' => 0,
                'wolt' => 0,
                'bolt' => 0,
                'bolt_cash' => 0,
                'foodora' => 0,
            ]],
            'close_attendances' => true,
        ])
        ->assertRedirect();

    \expect($day->refresh()->getTotal())->toBe(125.0)
        ->and($session->refresh()->getActiveWorkerId())->toBeNull();
});

\test('admin can save the current month and close all active current-day attendances', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Europe/Prague'));
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 7)->create();
    $day = StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-07-23']);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $session = AttendanceSession::query()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'active_worker_id' => $worker->getKey(),
        'hourly_rate' => $worker->getHourlyRate(),
        'started_at' => Carbon::parse('2026-07-23 08:00:00', 'Europe/Prague')->utc(),
    ]);

    $this->actingAs($admin, 'users')
        ->put('/statements/' . $statement->getKey(), [
            'days' => [[
                'date' => $day->getDate(),
                'cash' => 125,
                'card' => 0,
                'wolt' => 0,
                'bolt' => 0,
                'bolt_cash' => 0,
                'foodora' => 0,
            ]],
            'close_attendances' => true,
        ])
        ->assertRedirect();

    \expect($day->refresh()->getTotal())->toBe(125.0)
        ->and($session->refresh()->getActiveWorkerId())->toBeNull();
});

\test('historical statement save cannot close current attendances', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Europe/Prague'));
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 6)->create();
    $day = StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-06-23']);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $session = AttendanceSession::query()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'created_by_user_id' => $limited->getKey(),
        'active_worker_id' => $worker->getKey(),
        'hourly_rate' => $worker->getHourlyRate(),
        'started_at' => Carbon::parse('2026-07-23 08:00:00', 'Europe/Prague')->utc(),
    ]);

    $this->actingAs($limited, 'users')
        ->put('/statements/' . $statement->getKey(), [
            'days' => [[
                'date' => $day->getDate(),
                'cash' => 125,
                'card' => 0,
                'wolt' => 0,
                'bolt' => 0,
                'bolt_cash' => 0,
                'foodora' => 0,
            ]],
            'close_attendances' => true,
        ])
        ->assertForbidden();

    \expect($day->refresh()->getTotal())->toBe(0.0)
        ->and($session->refresh()->getActiveWorkerId())->toBe($worker->getKey());
});
