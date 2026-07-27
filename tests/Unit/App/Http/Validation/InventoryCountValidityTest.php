<?php

declare(strict_types=1);

use App\Http\Validation\InventoryCountValidity;
use Thinkycz\LaravelCore\Validation\Validity;

\test('constructor creates instance with explicit user id', function (): void {
    $validity = new InventoryCountValidity(42);

    \expect($validity)->toBeInstanceOf(InventoryCountValidity::class);
});

\test('storeId returns a Validity with exists rule', function (): void {
    $rules = (new InventoryCountValidity(1))->storeId()->required()->toArray();

    \expect(\implode('|', \array_map('strval', $rules)))->toContain('exists:');
});

\test('itemId returns a Validity with exists rule', function (): void {
    $rules = (new InventoryCountValidity(1))->itemId()->required()->toArray();

    \expect(\implode('|', \array_map('strval', $rules)))->toContain('exists:');
});

\test('id returns a Validity with exists rule', function (): void {
    $rules = (new InventoryCountValidity(1))->id()->required()->toArray();

    \expect(\implode('|', \array_map('strval', $rules)))->toContain('exists:');
});

\test('rows returns a Validity with array rule', function (): void {
    $rules = (new InventoryCountValidity(1))->rows()->required()->toArray();

    \expect($rules)->toContain('array');
});

\test('rowQuantity returns a Validity with integer rule', function (): void {
    $rules = (new InventoryCountValidity(1))->rowQuantity()->required()->toArray();

    \expect($rules)->toContain('integer');
});

\test('rowNote returns a Validity with string rule', function (): void {
    $rules = (new InventoryCountValidity(1))->rowNote()->nullable()->toArray();

    \expect($rules)->toContain('string');
});

\test('all methods return Validity instances', function (): void {
    $validity = new InventoryCountValidity(1);

    \expect($validity->storeId())->toBeInstanceOf(Validity::class);
    \expect($validity->itemId())->toBeInstanceOf(Validity::class);
    \expect($validity->id())->toBeInstanceOf(Validity::class);
    \expect($validity->rows())->toBeInstanceOf(Validity::class);
    \expect($validity->rowQuantity())->toBeInstanceOf(Validity::class);
    \expect($validity->rowNote())->toBeInstanceOf(Validity::class);
});
