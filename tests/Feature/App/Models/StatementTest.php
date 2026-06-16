<?php

declare(strict_types=1);

use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use Database\Factories\StatementFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('statement getters round-trip persisted attributes', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);

    $statement = Typer::assertInstance(StatementFactory::new()->forStore($store)->forMonth(2026, 6)->createOne(), Statement::class);

    \expect($statement->getStoreId())->toBe($store->getKey());
    \expect($statement->getYear())->toBe(2026);
    \expect($statement->getMonth())->toBe(6);
    \expect($statement->getStore()->getKey())->toBe($store->getKey());
});

\test('statement scopeForMonth filters by year and month', function (): void {
    $statement = Statement::factory()->forMonth(2026, 5)->create();
    Statement::factory()->forMonth(2026, 6)->create();

    $query = Statement::query();
    Statement::scopeForMonth($query, 2026, 5);
    \expect($query->get()->pluck('id')->all())->toBe([$statement->getKey()]);
});

\test('statement scopeForStore filters by store', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $a = Store::factory()->create(['user_id' => $user->getKey()]);
    $b = Store::factory()->create(['user_id' => $user->getKey()]);

    $aStatement = Statement::factory()->forStore($a)->create();
    Statement::factory()->forStore($b)->create();

    $query = Statement::query();
    Statement::scopeForStore($query, $a->getKey());
    \expect($query->get()->pluck('id')->all())->toBe([$aStatement->getKey()]);
});

\test('statement days relationship returns daily rows', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $statement = Statement::factory()->forStore($store)->create();
    StatementDay::factory()->count(3)->for($statement, 'statement')->create();

    \expect($statement->getDays()->count())->toBe(3);
});
