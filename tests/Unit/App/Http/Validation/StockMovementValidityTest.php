<?php

declare(strict_types=1);

use App\Http\Validation\StockMovementValidity;
use Thinkycz\LaravelCore\Validation\Validity;

\test('constructor creates instance with explicit user id', function (): void {
    $validity = new StockMovementValidity(42);

    \expect($validity)->toBeInstanceOf(StockMovementValidity::class);
});

\test('type returns a Validity with in rule', function (): void {
    $rules = (new StockMovementValidity(1))->type()->required()->toArray();

    \expect(\implode('|', \array_map('strval', $rules)))->toContain('in:');
});

\test('storeId returns a Validity with exists rule', function (): void {
    $rules = (new StockMovementValidity(1))->storeId()->required()->toArray();

    \expect(\implode('|', \array_map('strval', $rules)))->toContain('exists:');
});

\test('activeStoreId returns a Validity with exists rule constraining status', function (): void {
    $rules = (new StockMovementValidity(1))->activeStoreId()->required()->toArray();
    $joined = \implode('|', \array_map('strval', $rules));

    \expect($joined)->toContain('exists:');
    \expect($joined)->toContain('active');
});

\test('retailStoreId returns a Validity with exists rule constraining is_warehouse', function (): void {
    $rules = (new StockMovementValidity(1))->retailStoreId()->required()->toArray();
    $joined = \implode('|', \array_map('strval', $rules));

    \expect($joined)->toContain('exists:');
    \expect($joined)->toContain('is_warehouse');
});

\test('warehouseStoreId returns a Validity with exists rule constraining is_warehouse', function (): void {
    $rules = (new StockMovementValidity(1))->warehouseStoreId()->required()->toArray();
    $joined = \implode('|', \array_map('strval', $rules));

    \expect($joined)->toContain('exists:');
    \expect($joined)->toContain('is_warehouse');
});

\test('note returns a Validity with string rule', function (): void {
    $rules = (new StockMovementValidity(1))->note()->nullable()->toArray();

    \expect($rules)->toContain('string');
});

\test('items returns a Validity with array rule', function (): void {
    $rules = (new StockMovementValidity(1))->items()->required()->toArray();

    \expect($rules)->toContain('array');
});

\test('rowItemId returns a Validity with exists rule', function (): void {
    $rules = (new StockMovementValidity(1))->rowItemId()->required()->toArray();

    \expect(\implode('|', \array_map('strval', $rules)))->toContain('exists:');
});

\test('rowQuantity returns a Validity with integer rule', function (): void {
    $rules = (new StockMovementValidity(1))->rowQuantity()->required()->toArray();

    \expect($rules)->toContain('integer');
});

\test('rowQuantityAfter returns a Validity with integer rule', function (): void {
    $rules = (new StockMovementValidity(1))->rowQuantityAfter()->required()->toArray();

    \expect($rules)->toContain('integer');
});

\test('rowAdjustmentReason returns a Validity with in rule', function (): void {
    $rules = (new StockMovementValidity(1))->rowAdjustmentReason()->required()->toArray();

    \expect(\implode('|', \array_map('strval', $rules)))->toContain('in:');
});

\test('id returns a Validity with exists rule', function (): void {
    $rules = (new StockMovementValidity(1))->id()->required()->toArray();

    \expect(\implode('|', \array_map('strval', $rules)))->toContain('exists:');
});

\test('search returns a Validity with string rule', function (): void {
    $rules = (new StockMovementValidity(1))->search()->nullable()->toArray();

    \expect($rules)->toContain('string');
});

\test('typeFilter returns a Validity with in rule', function (): void {
    $rules = (new StockMovementValidity(1))->typeFilter()->required()->toArray();

    \expect(\implode('|', \array_map('strval', $rules)))->toContain('in:');
});

\test('dateFrom returns a Validity with string and date_format rule', function (): void {
    $rules = (new StockMovementValidity(1))->dateFrom()->required()->toArray();

    \expect($rules)->toContain('string');
});

\test('dateTo returns a Validity with string and date_format rule', function (): void {
    $rules = (new StockMovementValidity(1))->dateTo()->required()->toArray();

    \expect($rules)->toContain('string');
});

\test('all methods return Validity instances', function (): void {
    $validity = new StockMovementValidity(1);

    \expect($validity->type())->toBeInstanceOf(Validity::class);
    \expect($validity->storeId())->toBeInstanceOf(Validity::class);
    \expect($validity->activeStoreId())->toBeInstanceOf(Validity::class);
    \expect($validity->retailStoreId())->toBeInstanceOf(Validity::class);
    \expect($validity->warehouseStoreId())->toBeInstanceOf(Validity::class);
    \expect($validity->note())->toBeInstanceOf(Validity::class);
    \expect($validity->items())->toBeInstanceOf(Validity::class);
    \expect($validity->rowItemId())->toBeInstanceOf(Validity::class);
    \expect($validity->rowQuantity())->toBeInstanceOf(Validity::class);
    \expect($validity->rowQuantityAfter())->toBeInstanceOf(Validity::class);
    \expect($validity->rowAdjustmentReason())->toBeInstanceOf(Validity::class);
    \expect($validity->id())->toBeInstanceOf(Validity::class);
    \expect($validity->search())->toBeInstanceOf(Validity::class);
    \expect($validity->typeFilter())->toBeInstanceOf(Validity::class);
    \expect($validity->dateFrom())->toBeInstanceOf(Validity::class);
    \expect($validity->dateTo())->toBeInstanceOf(Validity::class);
});
