<?php

declare(strict_types=1);

use App\Models\StockMovementSequence;

\test('first increment creates a new sequence row', function (): void {
    $number = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026);

    \expect($number)->toBe('IN-2026-0001');
    \expect(StockMovementSequence::query()->count())->toBe(1);
});

\test('subsequent increments reuse the row and bump last_number', function (): void {
    $a = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026);
    $b = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026);
    $c = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026);

    \expect($a)->toBe('IN-2026-0001');
    \expect($b)->toBe('IN-2026-0002');
    \expect($c)->toBe('IN-2026-0003');
    \expect(StockMovementSequence::query()->count())->toBe(1);
});

\test('different types and years are tracked independently', function (): void {
    $in1 = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026);
    $out1 = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::TRANSFER, 2026);
    $adj1 = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::ADJUSTMENT, 2026);
    $in2 = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2027);

    \expect($in1)->toBe('IN-2026-0001');
    \expect($out1)->toBe('TR-2026-0001');
    \expect($adj1)->toBe('ADJ-2026-0001');
    \expect($in2)->toBe('IN-2027-0001');
    \expect(StockMovementSequence::query()->count())->toBe(4);
});

\test('movement counters are company wide', function (): void {
    $a1 = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026);
    $b1 = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026);
    $a2 = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026);

    \expect($a1)->toBe('IN-2026-0001');
    \expect($b1)->toBe('IN-2026-0002');
    \expect($a2)->toBe('IN-2026-0003');
});

\test('next() survives the first-time primary-key race', function (): void {
    // Simulate the race by pre-inserting a row that the new
    // `next()` call would also try to insert, then re-call `next()`
    // and assert the locked read+update path recovers cleanly.
    DB::table('stock_movement_sequences')->insert([
        'type' => App\Enums\StockMovementTypeEnum::INCOMING->value,
        'year' => 2026,
        'last_number' => 1,
    ]);

    $next = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026);

    \expect($next)->toBe('IN-2026-0002');
    \expect(StockMovementSequence::query()->count())->toBe(1);
});
\test('next() seeds the sequence from existing stock_movements when the counter is missing', function (): void {
    $user = Database\Factories\UserFactory::new()->admin()->createOne();

    // Simulate stock_movements that were created without going
    // through the sequence (seeders, migrations, older code).
    DB::table('stock_movements')->insert([
        ['user_id' => $user->getKey(), 'number' => 'IN-2026-0001', 'type' => 'incoming', 'occurred_at' => '2026-01-01 00:00:00', 'total_quantity' => 0, 'total_value' => 0, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ['user_id' => $user->getKey(), 'number' => 'IN-2026-0002', 'type' => 'incoming', 'occurred_at' => '2026-01-01 00:00:00', 'total_quantity' => 0, 'total_value' => 0, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
    ]);

    $next = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026);

    \expect($next)->toBe('IN-2026-0003');
});

\test('next() advances the sequence when it is behind the actual data', function (): void {
    $user = Database\Factories\UserFactory::new()->admin()->createOne();

    // Sequence thinks it is at 1, but the actual data already has
    // numbers up to 0005.
    StockMovementSequence::query()->create([
        'type' => App\Enums\StockMovementTypeEnum::INCOMING->value,
        'year' => 2026,
        'last_number' => 1,
    ]);
    DB::table('stock_movements')->insert([
        ['user_id' => $user->getKey(), 'number' => 'IN-2026-0005', 'type' => 'incoming', 'occurred_at' => '2026-01-01 00:00:00', 'total_quantity' => 0, 'total_value' => 0, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
    ]);

    $next = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026);

    \expect($next)->toBe('IN-2026-0006');
});
