<?php

declare(strict_types=1);

use App\Models\StockMovementSequence;

\test('getType returns the type attribute', function (): void {
    $seq = StockMovementSequence::factory()->make(['type' => 'incoming']);

    \expect($seq->getType())->toBe('incoming');
});

\test('getYear returns the year attribute', function (): void {
    $seq = StockMovementSequence::factory()->make(['year' => 2025]);

    \expect($seq->getYear())->toBe(2025);
});

\test('getLastNumber returns the last_number attribute', function (): void {
    $seq = StockMovementSequence::factory()->make(['last_number' => 42]);

    \expect($seq->getLastNumber())->toBe(42);
});

\test('timestamps is disabled', function (): void {
    \expect((new StockMovementSequence())->timestamps)->toBeFalse();
});

\test('incrementing is disabled', function (): void {
    \expect((new StockMovementSequence())->incrementing)->toBeFalse();
});

\test('scopeSearch is a no-op', function (): void {
    $query = StockMovementSequence::query();
    $sqlBefore = $query->toSql();

    StockMovementSequence::scopeSearch($query, 'anything');

    \expect($query->toSql())->toBe($sqlBefore);
});

\test('querySelect limits columns', function (): void {
    $query = StockMovementSequence::querySelect(StockMovementSequence::query());
    $sql = $query->toSql();

    \expect($sql)->toContain('"user_id"');
    \expect($sql)->toContain('"type"');
    \expect($sql)->toContain('"year"');
    \expect($sql)->toContain('"last_number"');
});

\test('casts returns expected cast definitions', function (): void {
    $seq = new StockMovementSequence();
    $casts = (new ReflectionMethod($seq, 'casts'))->invoke($seq);

    \expect($casts)->toHaveKey('user_id', 'integer');
    \expect($casts)->toHaveKey('year', 'integer');
    \expect($casts)->toHaveKey('last_number', 'integer');
});
