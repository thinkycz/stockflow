<?php

declare(strict_types=1);

use App\Http\Validation\StoreValidity;
use Thinkycz\LaravelCore\Validation\Validity;

\test('constructor creates instance with explicit user id', function (): void {
    $validity = new StoreValidity(42);

    \expect($validity)->toBeInstanceOf(StoreValidity::class);
});

\test('name returns a Validity with string rule', function (): void {
    $rules = (new StoreValidity(1))->name()->required()->toArray();

    \expect($rules)->toContain('string');
});

\test('address returns a Validity with string rule', function (): void {
    $rules = (new StoreValidity(1))->address()->nullable()->toArray();

    \expect($rules)->toContain('string');
});

\test('status returns a Validity with in rule', function (): void {
    $rules = (new StoreValidity(1))->status()->required()->toArray();

    \expect(\implode('|', \array_map('strval', $rules)))->toContain('in:');
});

\test('notes returns a Validity with string rule', function (): void {
    $rules = (new StoreValidity(1))->notes()->nullable()->toArray();

    \expect($rules)->toContain('string');
});

\test('isWarehouse returns a Validity with boolean rule', function (): void {
    $rules = (new StoreValidity(1))->isWarehouse()->required()->toArray();

    \expect($rules)->toContain('boolean');
});

\test('id returns a Validity with exists rule', function (): void {
    $rules = (new StoreValidity(1))->id()->required()->toArray();

    \expect(\implode('|', \array_map('strval', $rules)))->toContain('exists:');
});

\test('retailId returns a Validity with exists rule constraining is_warehouse', function (): void {
    $rules = (new StoreValidity(1))->retailId()->required()->toArray();
    $joined = \implode('|', \array_map('strval', $rules));

    \expect($joined)->toContain('exists:');
    \expect($joined)->toContain('is_warehouse');
});

\test('search returns a Validity with string rule', function (): void {
    $rules = (new StoreValidity(1))->search()->nullable()->toArray();

    \expect($rules)->toContain('string');
});

\test('all methods return Validity instances', function (): void {
    $validity = new StoreValidity(1);

    \expect($validity->name())->toBeInstanceOf(Validity::class);
    \expect($validity->address())->toBeInstanceOf(Validity::class);
    \expect($validity->status())->toBeInstanceOf(Validity::class);
    \expect($validity->notes())->toBeInstanceOf(Validity::class);
    \expect($validity->isWarehouse())->toBeInstanceOf(Validity::class);
    \expect($validity->id())->toBeInstanceOf(Validity::class);
    \expect($validity->retailId())->toBeInstanceOf(Validity::class);
    \expect($validity->search())->toBeInstanceOf(Validity::class);
});
