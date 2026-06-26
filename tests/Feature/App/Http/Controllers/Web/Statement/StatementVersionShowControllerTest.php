<?php

declare(strict_types=1);

use App\Models\Statement;
use App\Models\StatementVersion;
use App\Models\StatementVersionDay;
use App\Models\Store;
use Database\Factories\UserFactory;

\test('admin opens a single statement version detail', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 6)->create();
    $version = StatementVersion::factory()->forStatement($statement)->byCreator($user)->create();
    StatementVersionDay::factory()->forVersion($version)->create([
        'date' => '2026-06-01',
        'cash' => 12.5,
        'card' => 7.5,
        'wolt' => 0,
        'bolt' => 0,
        'bolt_cash' => 0,
        'foodora' => 0,
        'total' => 20,
    ]);

    $response = $this->actingAs($user)->get(\route('statements.versions.show', [
        'version' => $version->getKey(),
    ]));

    $response->assertOk();
    $response->assertInertia(static fn($page) => $page
        ->component('statements/Version')
        ->where('version.id', $version->getKey())
        ->where('version.created_by_email', $user->getEmail())
        ->where('statement.id', $statement->getKey())
        ->has('rows', 1)
        ->where('rows.0.cash', 12.5)
        ->where('rows.0.total', 20)
        ->where('is_admin', true));
});

\test('show rejects another user\'s version', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $otherStore = Store::factory()->create(['user_id' => $other->getKey()]);
    $otherStatement = Statement::factory()->forStore($otherStore)->forMonth(2026, 6)->create();
    $otherVersion = StatementVersion::factory()->forStatement($otherStatement)->create();

    $this->actingAs($user)
        ->get(\route('statements.versions.show', ['version' => $otherVersion->getKey()]))
        ->assertNotFound();
});

\test('limited user cannot open a version from a different store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $storeA = Store::factory()->create(['user_id' => $admin->getKey()]);
    $storeB = Store::factory()->create(['user_id' => $admin->getKey()]);
    $statementB = Statement::factory()->forStore($storeB)->forMonth(2026, 6)->byUser($admin)->create();
    $versionB = StatementVersion::factory()->forStatement($statementB)->byUser($admin)->create();

    $limited = UserFactory::new()->limited($storeA)->createOne();

    $this->actingAs($limited)
        ->get(\route('statements.versions.show', ['version' => $versionB->getKey()]))
        ->assertForbidden();
});
