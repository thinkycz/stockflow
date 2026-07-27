<?php

declare(strict_types=1);

use App\Models\StatementDay;

\test('getStatementId returns the statement_id attribute', function (): void {
    $day = StatementDay::factory()->make();

    \expect($day->getStatementId())->toBeInt();
});

\test('getDate returns a Y-m-d string', function (): void {
    $day = StatementDay::factory()->make();

    \expect($day->getDate())->toMatch('/^\\d{4}-\\d{2}-\\d{2}$/');
});

\test('getCash returns a float', function (): void {
    $day = StatementDay::factory()->make(['cash' => '12.50']);

    \expect($day->getCash())->toBe(12.5);
});

\test('getCard returns a float', function (): void {
    $day = StatementDay::factory()->make(['card' => '25.00']);

    \expect($day->getCard())->toBe(25.0);
});

\test('getWolt returns a float', function (): void {
    $day = StatementDay::factory()->make(['wolt' => '3.75']);

    \expect($day->getWolt())->toBe(3.75);
});

\test('getBolt returns a float', function (): void {
    $day = StatementDay::factory()->make(['bolt' => '7.20']);

    \expect($day->getBolt())->toBe(7.2);
});

\test('getBoltCash returns a float', function (): void {
    $day = StatementDay::factory()->make(['bolt_cash' => '1.10']);

    \expect($day->getBoltCash())->toBe(1.1);
});

\test('getFoodora returns a float', function (): void {
    $day = StatementDay::factory()->make(['foodora' => '4.00']);

    \expect($day->getFoodora())->toBe(4.0);
});

\test('getTotal returns a float', function (): void {
    $day = StatementDay::factory()->make(['total' => '50.55']);

    \expect($day->getTotal())->toBe(50.55);
});

\test('scopeSearch is a no-op', function (): void {
    $query = StatementDay::query();
    $sqlBefore = $query->toSql();

    StatementDay::scopeSearch($query, 'anything');

    \expect($query->toSql())->toBe($sqlBefore);
});

\test('querySelect limits columns', function (): void {
    $query = StatementDay::querySelect(StatementDay::query());
    $sql = $query->toSql();

    \expect($sql)->toContain('"id"');
    \expect($sql)->toContain('"statement_id"');
    \expect($sql)->toContain('"date"');
    \expect($sql)->toContain('"cash"');
    \expect($sql)->toContain('"total"');
});

\test('casts returns expected cast definitions', function (): void {
    $day = new StatementDay();
    $casts = (new ReflectionMethod($day, 'casts'))->invoke($day);

    \expect($casts)->toHaveKey('date', 'date');
    \expect($casts)->toHaveKey('cash', 'decimal:2');
    \expect($casts)->toHaveKey('card', 'decimal:2');
    \expect($casts)->toHaveKey('wolt', 'decimal:2');
    \expect($casts)->toHaveKey('bolt', 'decimal:2');
    \expect($casts)->toHaveKey('bolt_cash', 'decimal:2');
    \expect($casts)->toHaveKey('foodora', 'decimal:2');
    \expect($casts)->toHaveKey('total', 'decimal:2');
});
