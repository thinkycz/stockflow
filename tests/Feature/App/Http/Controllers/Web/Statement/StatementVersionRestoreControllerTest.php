<?php

declare(strict_types=1);

use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\StatementVersion;
use App\Models\StatementVersionDay;
use App\Models\Store;
use Database\Factories\UserFactory;

\test('admin restores a statement from a saved version', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 6)->create();
    $day = StatementDay::factory()->for($statement, 'statement')->create();

    // Initial state: snapshot of zeroed row (initial save).
    $original = StatementVersion::factory()->forStatement($statement)->byCreator($user)->create();
    StatementVersionDay::factory()->forVersion($original)->create([
        'date' => $day->getDate(),
        'cash' => 0,
        'card' => 0,
        'wolt' => 0,
        'bolt' => 0,
        'bolt_cash' => 0,
        'foodora' => 0,
        'total' => 0,
    ]);

    // Second save overwrites with non-zero amounts.
    $this->actingAs($user)
        ->put(\route('statements.update', ['statement' => $statement->getKey()]), [
            'days' => [
                [
                    'date' => $day->getDate(),
                    'cash' => 100,
                    'card' => 0,
                    'wolt' => 0,
                    'bolt' => 0,
                    'bolt_cash' => 0,
                    'foodora' => 0,
                ],
            ],
        ])
        ->assertRedirect();

    \expect(StatementVersion::query()->count())->toBe(2);

    $response = $this->actingAs($user)
        ->post(\route('statements.versions.restore', ['version' => $original->getKey()]));

    $response->assertRedirect(\route('statements.index', [
        'store_id' => $store->getKey(),
        'year' => 2026,
        'month' => 6,
    ]));
    \assertInertiaFlash($response, 'success', \__('Statement restored from version.'));

    $day->refresh();
    \expect($day->getCash())->toBe(0.0);

    // Pre-restore snapshot was added before overwriting the data.
    \expect(StatementVersion::query()->count())->toBe(3);
});

\test('restore rejects another user\'s version', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $otherStore = Store::factory()->create(['user_id' => $other->getKey()]);
    $otherStatement = Statement::factory()->forStore($otherStore)->forMonth(2026, 6)->create();
    $otherVersion = StatementVersion::factory()->forStatement($otherStatement)->create();

    $this->actingAs($user)
        ->post(\route('statements.versions.restore', ['version' => $otherVersion->getKey()]))
        ->assertNotFound();
});

\test('limited user cannot restore a version from a different store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $storeA = Store::factory()->create(['user_id' => $admin->getKey()]);
    $storeB = Store::factory()->create(['user_id' => $admin->getKey()]);
    $statementB = Statement::factory()->forStore($storeB)->forMonth(2026, 6)->byUser($admin)->create();
    $versionB = StatementVersion::factory()->forStatement($statementB)->byUser($admin)->create();

    $limited = UserFactory::new()->limited($storeA)->createOne();

    $this->actingAs($limited)
        ->post(\route('statements.versions.restore', ['version' => $versionB->getKey()]))
        ->assertForbidden();
});
