<?php

declare(strict_types=1);

use App\Models\StockMovementSequence;

\test('first increment creates a new sequence row', function (): void {
    $user = Database\Factories\UserFactory::new()->createOne();

    $number = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026, $user->getKey());

    \expect($number)->toBe('IN-2026-0001');
    \expect(StockMovementSequence::query()->count())->toBe(1);
});

\test('subsequent increments reuse the row and bump last_number', function (): void {
    $user = Database\Factories\UserFactory::new()->createOne();

    $a = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026, $user->getKey());
    $b = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026, $user->getKey());
    $c = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026, $user->getKey());

    \expect($a)->toBe('IN-2026-0001');
    \expect($b)->toBe('IN-2026-0002');
    \expect($c)->toBe('IN-2026-0003');
    \expect(StockMovementSequence::query()->count())->toBe(1);
});

\test('different types and years are tracked independently', function (): void {
    $user = Database\Factories\UserFactory::new()->createOne();

    $in1 = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026, $user->getKey());
    $out1 = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::OUTGOING, 2026, $user->getKey());
    $adj1 = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::ADJUSTMENT, 2026, $user->getKey());
    $in2 = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2027, $user->getKey());

    \expect($in1)->toBe('IN-2026-0001');
    \expect($out1)->toBe('OUT-2026-0001');
    \expect($adj1)->toBe('ADJ-2026-0001');
    \expect($in2)->toBe('IN-2027-0001');
    \expect(StockMovementSequence::query()->count())->toBe(4);
});

\test('different users have independent counters', function (): void {
    $userA = Database\Factories\UserFactory::new()->createOne();
    $userB = Database\Factories\UserFactory::new()->createOne();

    $a1 = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026, $userA->getKey());
    $b1 = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026, $userB->getKey());
    $a2 = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026, $userA->getKey());

    \expect($a1)->toBe('IN-2026-0001');
    \expect($b1)->toBe('IN-2026-0001');
    \expect($a2)->toBe('IN-2026-0002');
});

\test('next() survives the first-time primary-key race', function (): void {
    $user = Database\Factories\UserFactory::new()->createOne();

    // Simulate the race by pre-inserting a row that the new
    // `next()` call would also try to insert, then re-call `next()`
    // and assert the locked read+update path recovers cleanly.
    DB::table('stock_movement_sequences')->insert([
        'user_id' => $user->getKey(),
        'type' => App\Enums\StockMovementTypeEnum::INCOMING->value,
        'year' => 2026,
        'last_number' => 1,
    ]);

    $next = StockMovementSequence::next(App\Enums\StockMovementTypeEnum::INCOMING, 2026, $user->getKey());

    \expect($next)->toBe('IN-2026-0002');
    \expect(StockMovementSequence::query()->count())->toBe(1);
});
