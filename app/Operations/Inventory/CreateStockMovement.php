<?php

declare(strict_types=1);

namespace App\Operations\Inventory;

use App\Http\Validation\StockMovementValidity;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockMovementService;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class CreateStockMovement
{
    /**
     * Create the shared stock movement command.
     */
    public function __construct(
        private readonly StockMovementService $service,
    ) {}

    /**
     * Validate and execute one human-equivalent stock movement.
     *
     * @param array<string, mixed> $input
     */
    public function execute(User $actor, array $input): StockMovement
    {
        return $this->service->createMovement($this->validate($actor, $input), $actor);
    }

    /**
     * Validate and normalize stock movement input without persisting it.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function validate(User $actor, array $input): array
    {
        $mode = Typer::parseNullableString($input['mode'] ?? null) ?? 'transfer';
        $parser = new Parser(Resolver::resolveValidator($input, $this->rules($actor, $mode))->validate());

        return [
            'mode' => $mode,
            'store_id' => Typer::parseNullableInt($parser->mixed('store_id')),
            'source_store_id' => Typer::parseNullableInt($parser->mixed('source_store_id')),
            'note' => $parser->assertNullableString('note'),
            'occurred_at' => $parser->assertNullableString('occurred_at'),
            'items' => $parser->assertArray('items'),
        ];
    }

    /**
     * Build the shared human and assistant validation rules.
     *
     * @return array<string, mixed>
     */
    private function rules(User $actor, string $mode): array
    {
        $validity = StockMovementValidity::inject($actor->resolveScopeUser()->getKey());
        $rules = [
            'note' => $validity->note()->nullable()->toArray(),
            'occurred_at' => $actor->isAdmin()
                ? ['nullable', 'date', 'before_or_equal:now']
                : ['prohibited'],
            'items' => $validity->items()->required()->toArray(),
            'items.*.item_id' => $validity->rowItemId()->required()->toArray(),
        ];

        if ($mode === 'adjustment') {
            $rules['mode'] = $validity->baseValidity->mode(['adjustment'])->nullable()->toArray();
            $rules['store_id'] = $validity->activeStoreId()->required()->toArray();
            $rules['items.*.quantity_after'] = $validity->rowQuantityAfter()->required()->toArray();
            $rules['items.*.adjustment_reason'] = $validity->rowAdjustmentReason()->required()->toArray();
        } elseif ($mode === 'consumption') {
            $rules['mode'] = $validity->baseValidity->mode(['consumption'])->required()->toArray();
            $rules['store_id'] = $validity->activeStoreId()->required()->toArray();
            $rules['items.*.quantity'] = $validity->rowQuantity()->required()->toArray();
        } elseif ($mode === 'incoming') {
            $rules['mode'] = $validity->baseValidity->mode(['incoming'])->required()->toArray();
            $rules['store_id'] = $validity->activeStoreId()->required()->toArray();
            $rules['items.*.quantity'] = $validity->rowQuantity()->required()->toArray();
        } else {
            $rules['mode'] = $validity->baseValidity->mode(['transfer'])->nullable()->toArray();
            $rules['source_store_id'] = $validity->activeStoreId()->nullable()->toArray();
            $rules['store_id'] = $validity->activeStoreId()->required()->toArray();
            $rules['items.*.quantity'] = $validity->rowQuantity()->required()->toArray();
        }

        return $rules;
    }
}
