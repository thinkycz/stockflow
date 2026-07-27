<?php

declare(strict_types=1);

use App\Models\Statement;

\test('getStoreId returns the store_id attribute', function (): void {
    $statement = Statement::factory()->make();

    \expect($statement->getStoreId())->toBeInt();
});

\test('getUserId returns the user_id attribute', function (): void {
    $statement = Statement::factory()->make();

    \expect($statement->getUserId())->toBeInt();
});

\test('getYear returns the year attribute', function (): void {
    $statement = Statement::factory()->make(['year' => 2025]);

    \expect($statement->getYear())->toBe(2025);
});

\test('getMonth returns the month attribute', function (): void {
    $statement = Statement::factory()->make(['month' => 6]);

    \expect($statement->getMonth())->toBe(6);
});

\test('scopeSearch adds where clauses for year and month', function (): void {
    $query = Statement::query();

    Statement::scopeSearch($query, '2025');
    $sql = $query->toSql();

    \expect($sql)->toContain('year');
    \expect($sql)->toContain('month');
});

\test('scopeForStore filters by store_id', function (): void {
    $query = Statement::query();

    Statement::scopeForStore($query, 5);

    \expect($query->toSql())->toContain('store_id');
});

\test('scopeForMonth filters by year and month', function (): void {
    $query = Statement::query();

    Statement::scopeForMonth($query, 2025, 6);
    $sql = $query->toSql();

    \expect($sql)->toContain('year');
    \expect($sql)->toContain('month');
});

\test('querySelect limits columns', function (): void {
    $query = Statement::querySelect(Statement::query());
    $sql = $query->toSql();

    \expect($sql)->toContain('"id"');
    \expect($sql)->toContain('"user_id"');
    \expect($sql)->toContain('"store_id"');
    \expect($sql)->toContain('"year"');
    \expect($sql)->toContain('"month"');
});

\test('casts returns expected cast definitions', function (): void {
    $statement = new Statement();
    $casts = (new ReflectionMethod($statement, 'casts'))->invoke($statement);

    \expect($casts)->toHaveKey('year', 'integer');
    \expect($casts)->toHaveKey('month', 'integer');
});
