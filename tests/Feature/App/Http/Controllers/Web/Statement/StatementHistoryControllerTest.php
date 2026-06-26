<?php

declare(strict_types=1);

use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\StatementVersion;
use App\Models\StatementVersionDay;
use App\Models\Store;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;

\test('admin sees the version history for the selected statement', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 6)->create();
    StatementDay::factory()->for($statement, 'statement')->count(3)->create();

    $version = StatementVersion::factory()->forStatement($statement)->byCreator($user)->create([
        'snapshot_at' => Carbon::now()->subMinute(),
    ]);
    StatementVersionDay::factory()->forVersion($version)->create();

    $response = $this->actingAs($user)->get(\route('statements.history', [
        'statement' => $statement->getKey(),
    ]));

    $response->assertOk();
    $response->assertInertia(static fn($page) => $page
        ->component('statements/History')
        ->where('statement.id', $statement->getKey())
        ->where('statement.store_name', $store->getName())
        ->where('statement.year', 2026)
        ->where('statement.month', 6)
        ->has('rows', 1)
        ->where('rows.0.id', $version->getKey())
        ->where('rows.0.day_count', 1)
        ->where('rows.0.created_by_email', $user->getEmail())
        ->where('is_admin', true));
});

\test('history is scoped to the user that owns the statement', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $other->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 6)->create();

    $this->actingAs($user)
        ->get(\route('statements.history', ['statement' => $statement->getKey()]))
        ->assertNotFound();
});

\test('limited user is pinned to their assigned store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $storeA = Store::factory()->create(['user_id' => $admin->getKey()]);
    $storeB = Store::factory()->create(['user_id' => $admin->getKey()]);

    $statementA = Statement::factory()->forStore($storeA)->forMonth(2026, 6)->byUser($admin)->create();
    $statementB = Statement::factory()->forStore($storeB)->forMonth(2026, 6)->byUser($admin)->create();

    $limited = UserFactory::new()->limited($storeA)->createOne();

    $this->actingAs($limited)
        ->get(\route('statements.history', ['statement' => $statementA->getKey()]))
        ->assertOk();

    $this->actingAs($limited)
        ->get(\route('statements.history', ['statement' => $statementB->getKey()]))
        ->assertForbidden();
});

\test('limited user without an assigned store is refused', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 6)->byUser($admin)->create();

    $limited = UserFactory::new()->limited($store)->createOne();
    $limited->update(['assigned_store_id' => null]);

    $this->actingAs($limited)
        ->get(\route('statements.history', ['statement' => $statement->getKey()]))
        ->assertForbidden();
});

\test('history is ordered by snapshot_at descending', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 6)->create();
    StatementDay::factory()->for($statement, 'statement')->create();

    $first = StatementVersion::factory()->forStatement($statement)->byCreator($user)->create([
        'snapshot_at' => Carbon::now()->subHours(2),
    ]);
    $second = StatementVersion::factory()->forStatement($statement)->byCreator($user)->create([
        'snapshot_at' => Carbon::now()->subHour(),
    ]);
    $third = StatementVersion::factory()->forStatement($statement)->byCreator($user)->create([
        'snapshot_at' => Carbon::now(),
    ]);

    $response = $this->actingAs($user)->get(\route('statements.history', [
        'statement' => $statement->getKey(),
    ]));

    $response->assertOk();
    $response->assertInertia(static fn($page) => $page
        ->has('rows', 3)
        ->where('rows.0.id', $third->getKey())
        ->where('rows.1.id', $second->getKey())
        ->where('rows.2.id', $first->getKey()));
});
