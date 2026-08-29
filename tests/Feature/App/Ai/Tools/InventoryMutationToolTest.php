<?php

declare(strict_types=1);

use App\Ai\Tools\WriteStockMovementsTool;
use App\Enums\AssistantActionStatusEnum;
use App\Models\AssistantActionAudit;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StoreItem;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Tools\Request;

\test('inventory mutation tool requires approval and posts through the stock movement command', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $admin->setActiveStoreId($warehouse->getKey());
    $item = Item::factory()->create([
        'user_id' => $admin->getKey(),
        'title' => 'Assistant coffee',
        'purchase_price' => '8.50',
    ]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 0,
    ]);
    $arguments = [
        'request' => [
            'action' => 'create_stock_movement',
            'mode' => 'incoming',
            'store_id' => $warehouse->getKey(),
            'values' => [
                'note' => 'AI approved delivery',
                'items' => [[
                    'item_id' => $item->getKey(),
                    'quantity' => 4,
                ]],
            ],
        ],
    ];
    $tool = new WriteStockMovementsTool($admin, 'conversation-1');

    $approval = $tool->shouldRequestApproval(new Request($arguments, 'call-1'));

    \expect($approval)->toBeInstanceOf(Approval::class)
        ->and(StockMovement::query()->count())->toBe(0);

    $result = \json_decode((string) $tool->handle(new Request($arguments, 'call-1', 'invocation-1')), true, 512, \JSON_THROW_ON_ERROR);

    \expect($result['operation'])->toBe('create_stock_movement')
        ->and($result['status'])->toBe('succeeded')
        ->and($result['record']['number'])->toStartWith('IN-')
        ->and(StockMovement::query()->count())->toBe(1)
        ->and($item->fresh()?->getWarehouseQuantity())->toBe(4);

    $replayed = \json_decode((string) $tool->handle(new Request($arguments, 'call-1', 'invocation-2')), true, 512, \JSON_THROW_ON_ERROR);
    $audit = AssistantActionAudit::query()->sole();

    \expect($replayed)->toBe($result)
        ->and(StockMovement::query()->count())->toBe(1)
        ->and($audit->getStatus())->toBe(AssistantActionStatusEnum::SUCCEEDED);
});
