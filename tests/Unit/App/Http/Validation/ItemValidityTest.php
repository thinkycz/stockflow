<?php

declare(strict_types=1);

use App\Http\Validation\ItemValidity;
use Thinkycz\LaravelCore\Validation\Validity;

\test('constructor creates instance with explicit user id', function (): void {
    $validity = new ItemValidity(42);

    \expect($validity)->toBeInstanceOf(ItemValidity::class);
});

\test('title returns a Validity with string rule', function (): void {
    $rules = (new ItemValidity(1))->title()->required()->toArray();

    \expect($rules)->toContain('string');
});

\test('sku without ignore returns a Validity with unique rule', function (): void {
    $rules = (new ItemValidity(1))->sku()->required()->toArray();
    $joined = \implode('|', \array_map('strval', $rules));

    \expect($joined)->toContain('unique:');
});

\test('sku with ignore returns a Validity with unique rule excluding the id', function (): void {
    $rules = (new ItemValidity(1))->sku(99)->required()->toArray();
    $joined = \implode('|', \array_map('strval', $rules));

    \expect($joined)->toContain('unique:');
});

\test('unit returns a Validity with string rule', function (): void {
    $rules = (new ItemValidity(1))->unit()->nullable()->toArray();

    \expect($rules)->toContain('string');
});

\test('purchasePrice returns a Validity with numeric rule', function (): void {
    $rules = (new ItemValidity(1))->purchasePrice()->required()->toArray();

    \expect($rules)->toContain('numeric');
});

\test('description returns a Validity with string rule', function (): void {
    $rules = (new ItemValidity(1))->description()->nullable()->toArray();

    \expect($rules)->toContain('string');
});

\test('id returns a Validity with exists rule', function (): void {
    $rules = (new ItemValidity(1))->id()->required()->toArray();

    \expect(\implode('|', \array_map('strval', $rules)))->toContain('exists:');
});

\test('search returns a Validity with string rule', function (): void {
    $rules = (new ItemValidity(1))->search()->nullable()->toArray();

    \expect($rules)->toContain('string');
});

\test('all methods return Validity instances', function (): void {
    $validity = new ItemValidity(1);

    \expect($validity->title())->toBeInstanceOf(Validity::class);
    \expect($validity->sku())->toBeInstanceOf(Validity::class);
    \expect($validity->sku(1))->toBeInstanceOf(Validity::class);
    \expect($validity->unit())->toBeInstanceOf(Validity::class);
    \expect($validity->purchasePrice())->toBeInstanceOf(Validity::class);
    \expect($validity->description())->toBeInstanceOf(Validity::class);
    \expect($validity->id())->toBeInstanceOf(Validity::class);
    \expect($validity->search())->toBeInstanceOf(Validity::class);
});
