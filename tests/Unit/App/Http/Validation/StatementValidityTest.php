<?php

declare(strict_types=1);

use App\Http\Validation\StatementValidity;
use Thinkycz\LaravelCore\Validation\Validity;

\test('constructor creates instance with explicit user id', function (): void {
    $validity = new StatementValidity(42);

    \expect($validity)->toBeInstanceOf(StatementValidity::class);
});

\test('storeId returns a Validity instance with exists rule', function (): void {
    $rules = (new StatementValidity(1))->storeId()->required()->toArray();

    \expect($rules)->toBeArray();
    \expect(\implode('|', \array_map('strval', $rules)))->toContain('exists:');
});

\test('year returns a Validity instance with integer constraints', function (): void {
    $rules = (new StatementValidity(1))->year()->required()->toArray();

    \expect($rules)->toBeArray();
    \expect(\implode('|', \array_map('strval', $rules)))->toContain('integer');
});

\test('month returns a Validity instance with integer constraints', function (): void {
    $rules = (new StatementValidity(1))->month()->required()->toArray();

    \expect($rules)->toBeArray();
    \expect(\implode('|', \array_map('strval', $rules)))->toContain('integer');
});

\test('id returns a Validity instance with exists rule', function (): void {
    $rules = (new StatementValidity(1))->id()->required()->toArray();

    \expect($rules)->toBeArray();
    \expect(\implode('|', \array_map('strval', $rules)))->toContain('exists:');
});

\test('days returns a Validity instance with array rule', function (): void {
    $rules = (new StatementValidity(1))->days()->required()->toArray();

    \expect($rules)->toBeArray();
    \expect($rules)->toContain('array');
});

\test('dayId returns a Validity instance with exists rule', function (): void {
    $rules = (new StatementValidity(1))->dayId()->required()->toArray();

    \expect($rules)->toBeArray();
    \expect(\implode('|', \array_map('strval', $rules)))->toContain('exists:');
});

\test('dayDate returns a Validity instance with string rule', function (): void {
    $rules = (new StatementValidity(1))->dayDate()->required()->toArray();

    \expect($rules)->toBeArray();
    \expect($rules)->toContain('string');
});

\test('amount returns a Validity instance with numeric rule', function (): void {
    $rules = (new StatementValidity(1))->amount()->required()->toArray();

    \expect($rules)->toBeArray();
    \expect($rules)->toContain('numeric');
});

\test('all methods return Validity instances', function (): void {
    $validity = new StatementValidity(1);

    \expect($validity->storeId())->toBeInstanceOf(Validity::class);
    \expect($validity->year())->toBeInstanceOf(Validity::class);
    \expect($validity->month())->toBeInstanceOf(Validity::class);
    \expect($validity->id())->toBeInstanceOf(Validity::class);
    \expect($validity->days())->toBeInstanceOf(Validity::class);
    \expect($validity->dayId())->toBeInstanceOf(Validity::class);
    \expect($validity->dayDate())->toBeInstanceOf(Validity::class);
    \expect($validity->amount())->toBeInstanceOf(Validity::class);
});
