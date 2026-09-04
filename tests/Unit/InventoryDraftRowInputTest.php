<?php

declare(strict_types=1);

use App\Domain\Inventory\InventoryDraftRowInput;
use Illuminate\Validation\ValidationException;

\test('inventory input preserves exact decimal text until domain rounding', function (): void {
    $input = InventoryDraftRowInput::fromPayload([
        'item_id' => '42',
        'quantity' => '1.234500',
        'classification' => 'consumption',
        'note' => 'Physical count',
        'expected_revision' => '7',
    ]);

    \expect($input->itemId)->toBe(42)
        ->and($input->quantity)->toBe('1.234500')
        ->and($input->expectedRevision)->toBe(7)
        ->and($input->classification)->toBe('consumption')
        ->and($input->note)->toBe('Physical count');
});

\test('inventory input rejects invalid transport values with existing validation keys', function (string $field, mixed $invalid): void {
    $payload = ['item_id' => 42, 'quantity' => '1.250', 'expected_revision' => 0];
    $payload[$field] = $invalid;

    try {
        InventoryDraftRowInput::fromPayload($payload);
        \test()->fail('Expected invalid inventory input to be rejected.');
    } catch (ValidationException $exception) {
        \expect($exception->errors())->toHaveKey($field);
    }
})->with([
    ['item_id', 'invalid'],
    ['quantity', '-0.001'],
    ['quantity', 'not a number'],
    ['expected_revision', -1],
    ['expected_revision', null],
]);
