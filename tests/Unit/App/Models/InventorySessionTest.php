<?php

declare(strict_types=1);

use App\Models\InventorySession;
use Illuminate\Support\Carbon;

\test('getCountedAt returns a Carbon instance', function (): void {
    $session = InventorySession::factory()->make(['counted_at' => '2025-06-15 10:30:00']);

    \expect($session->getCountedAt())->toBeInstanceOf(Carbon::class);
});

\test('getNote returns a string or null', function (): void {
    $session = InventorySession::factory()->make(['note' => 'Monthly count']);

    \expect($session->getNote())->toBe('Monthly count');
});

\test('getNote returns null when attribute is null', function (): void {
    $session = InventorySession::factory()->make(['note' => null]);

    \expect($session->getNote())->toBeNull();
});

\test('getCreatedBy returns an integer or null', function (): void {
    $session = InventorySession::factory()->make(['created_by' => 7]);

    \expect($session->getCreatedBy())->toBe(7);
});

\test('getCreatedBy returns null when attribute is null', function (): void {
    $session = InventorySession::factory()->make(['created_by' => null]);

    \expect($session->getCreatedBy())->toBeNull();
});

\test('scopeSearch adds where clause for note', function (): void {
    $query = InventorySession::query();

    InventorySession::scopeSearch($query, 'count');

    \expect($query->toSql())->toContain('note');
});

\test('scopeForStore filters by store_id', function (): void {
    $query = InventorySession::query();

    InventorySession::scopeForStore($query, 3);

    \expect($query->toSql())->toContain('store_id');
});

\test('scopeBetween filters by date range', function (): void {
    $query = InventorySession::query();

    InventorySession::scopeBetween($query, Carbon::parse('2025-01-01'), Carbon::parse('2025-12-31'));

    \expect($query->toSql())->toContain('counted_at');
});

\test('querySelect limits columns', function (): void {
    $query = InventorySession::querySelect(InventorySession::query());
    $sql = $query->toSql();

    \expect($sql)->toContain('"id"');
    \expect($sql)->toContain('"store_id"');
    \expect($sql)->toContain('"counted_at"');
});

\test('casts returns expected cast definitions', function (): void {
    $session = new InventorySession();
    $casts = (new ReflectionMethod($session, 'casts'))->invoke($session);

    \expect($casts)->toHaveKey('counted_at', 'datetime');
});
