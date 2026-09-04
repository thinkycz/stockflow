<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final readonly class InventoryDraftRowInput
{
    /**
     * Exact user input and the server revision against which it was edited.
     */
    public function __construct(
        public int $itemId,
        public string $quantity,
        public string|null $classification,
        public string|null $note,
        public int $expectedRevision,
    ) {}

    /**
     * Validate a transport payload before crossing the domain command boundary.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        $validated = Resolver::resolveValidatorFactory()->make($payload, [
            'item_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'classification' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'expected_revision' => ['required', 'integer', 'min:0'],
        ])->validate();

        return new self(
            itemId: Typer::parseInt($validated['item_id'] ?? null),
            quantity: (string) Typer::assertScalar($validated['quantity'] ?? null),
            classification: Typer::parseNullableString($validated['classification'] ?? null),
            note: Typer::parseNullableString($validated['note'] ?? null),
            expectedRevision: Typer::parseInt($validated['expected_revision'] ?? null),
        );
    }
}
