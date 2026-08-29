<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Ai\AssistantToolCatalog;
use App\Ai\Tools\AbstractApprovableResourceTool;
use App\Ai\Tools\WriteWorkersTool;
use App\Enums\AssistantActionStatusEnum;
use App\Models\AssistantActionAudit;
use App\Models\FinancialReportManualRow;
use App\Models\InventorySession;
use App\Models\Item;
use App\Models\NoticeboardCard;
use App\Models\RecipeCategory;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Tools\Request;

\test('administration mutation tool exposes the exact worker creation contract to the model', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $description = (new WriteWorkersTool($admin, 'worker-contract'))->description();

    \expect($description)
        ->toContain('first name, last name, and hourly rate are required')
        ->toContain('attendance rating and calendar color are optional');
});

\test('application mutation tool approves and executes an inventory lifecycle command exactly once', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $arguments = [
        'operation' => 'start_inventory_draft',
        'store_id' => $warehouse->getKey(),
        'target_id' => null,
        'context_json' => null,
        'values_json' => '{}',
    ];
    $arguments = \nativeResourceArguments($arguments);
    $tool = \nativeResourceTool($admin, 'conversation-inventory', 'write_inventory_counts');

    $approval = $tool->shouldRequestApproval(new Request($arguments, 'call-start'));

    \expect($approval)->toBeInstanceOf(Approval::class)
        ->and(InventorySession::query()->count())->toBe(0);

    $result = \json_decode(
        $tool->handle(new Request($arguments, 'call-start', 'invocation-start')),
        true,
        32,
        \JSON_THROW_ON_ERROR,
    );
    $replayed = \json_decode(
        $tool->handle(new Request($arguments, 'call-start', 'invocation-replay')),
        true,
        32,
        \JSON_THROW_ON_ERROR,
    );
    $audit = AssistantActionAudit::query()->sole();

    \expect($result['operation'])->toBe('start_inventory_draft')
        ->and($result['status'])->toBe('succeeded')
        ->and($replayed)->toBe($result)
        ->and(InventorySession::query()->count())->toBe(1)
        ->and($audit->getStatus())->toBe(AssistantActionStatusEnum::SUCCEEDED);
});

\test('a previously failed tool call cannot replay its domain command', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $arguments = [
        'operation' => 'start_inventory_draft',
        'store_id' => $warehouse->getKey(),
        'target_id' => null,
        'context_json' => null,
        'values_json' => '{}',
    ];
    AssistantActionAudit::factory()->create([
        'actor_user_id' => $admin->getKey(),
        'actor_email' => $admin->getEmail(),
        'conversation_id' => 'failed-replay-conversation',
        'tool_call_id' => 'failed-replay-call',
        'tool_name' => 'write_inventory_counts',
        'domain' => 'inventory',
        'operation' => 'start_inventory_draft',
        'status' => AssistantActionStatusEnum::FAILED->value,
        'arguments' => $arguments,
    ]);
    $arguments = \nativeResourceArguments($arguments);
    $tool = \nativeResourceTool($admin, 'failed-replay-conversation', 'write_inventory_counts');

    \expect(fn(): string => $tool->handle(new Request(
        $arguments,
        'failed-replay-call',
        'failed-replay-invocation',
    )))->toThrow(RuntimeException::class, 'already resolved')
        ->and(InventorySession::query()->count())->toBe(0);
});

\test('a completed mutation remains authoritative when follow-up generation fails', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $conversation = $admin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Completed mutation',
    ]);
    $tool = \nativeResourceTool($admin, (string) $conversation->getKey(), 'write_inventory_counts');
    $arguments = [
        'operation' => 'start_inventory_draft',
        'store_id' => $warehouse->getKey(),
        'target_id' => null,
        'context_json' => null,
        'values_json' => '{}',
    ];
    $arguments = \nativeResourceArguments($arguments);

    $tool->handle(new Request($arguments, 'completed-before-failure', 'completed-invocation'));
    StockflowAssistant::fake(static function (): never {
        throw new RuntimeException('Follow-up generation failed.');
    });
    $stream = StockflowAssistant::make(
        actor: $admin,
        assistantConversationId: (string) $conversation->getKey(),
    )->continue((string) $conversation->getKey(), $admin)->stream('Summarize the completed action.');

    \expect(static function () use ($stream): void {
        foreach ($stream as $event) {
            unset($event);
        }
    })->toThrow(RuntimeException::class, 'Follow-up generation failed.')
        ->and(InventorySession::query()->count())->toBe(1)
        ->and(AssistantActionAudit::query()->sole()->getStatus())->toBe(AssistantActionStatusEnum::SUCCEEDED);
});

\test('operations assistant creates the same sanitized noticeboard record without binary input', function (): void {
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $tool = \nativeResourceTool($admin, 'conversation-operations', 'write_noticeboard');
    $arguments = [
        'operation' => 'create_noticeboard_card',
        'store_id' => $store->getKey(),
        'target_id' => null,
        'context_json' => '{}',
        'values_json' => \json_encode([
            'body_html' => '<p>Assistant <strong>notice</strong><script>alert(1)</script></p>',
            'label' => 'important',
            'color' => 'yellow',
            'size' => 'medium',
            'expires_on' => null,
        ], \JSON_THROW_ON_ERROR),
    ];
    $arguments = \nativeResourceArguments($arguments);

    \expect($tool->shouldRequestApproval(new Request($arguments, 'call-notice')))
        ->toBeInstanceOf(Approval::class);

    $tool->handle(new Request($arguments, 'call-notice', 'invocation-notice'));
    $card = NoticeboardCard::query()->sole();

    \expect($card->getTitle())->toBe('Assistant notice')
        ->and($card->getBodyHtml())->toContain('<strong>notice</strong>')
        ->not->toContain('<script');
});

\test('application mutation tool rejects cross-company locked store identifiers before approval', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    [, $foreignWarehouse] = \createIsolatedUserWithWarehouse();
    $tool = \nativeResourceTool($admin, 'conversation-inventory', 'write_inventory_counts');

    $arguments = \nativeResourceArguments([
        'operation' => 'start_inventory_draft',
        'store_id' => $foreignWarehouse->getKey(),
        'target_id' => null,
        'context_json' => null,
        'values_json' => '{}',
    ]);

    \expect($tool->shouldRequestApproval(new Request($arguments, 'call-foreign')))->toBeNull()
        ->and(InventorySession::query()->count())->toBe(0);
});

\test('finance assistant operation uses the same financial report service records', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    $tool = \nativeResourceTool($admin, 'conversation-finance', 'write_financial_reports');
    $arguments = [
        'operation' => 'create_financial_row',
        'store_id' => $store->getKey(),
        'target_id' => null,
        'context_json' => \json_encode(['year' => 2026, 'month' => 8], \JSON_THROW_ON_ERROR),
        'values_json' => \json_encode([
            'direction' => 'expense',
            'label' => 'Assistant repair',
            'occurred_on' => '2026-08-28',
            'amount' => 125.50,
            'note' => 'Approved repair',
        ], \JSON_THROW_ON_ERROR),
    ];
    $arguments = \nativeResourceArguments($arguments);

    \expect($tool->shouldRequestApproval(new Request($arguments, 'call-finance')))
        ->toBeInstanceOf(Approval::class)
        ->and(FinancialReportManualRow::query()->count())->toBe(0);

    $result = \json_decode(
        $tool->handle(new Request($arguments, 'call-finance', 'invocation-finance')),
        true,
        32,
        \JSON_THROW_ON_ERROR,
    );
    $row = FinancialReportManualRow::query()->sole();

    \expect($result['operation'])->toBe('create_financial_row')
        ->and($row->getLabel())->toBe('Assistant repair')
        ->and($row->getAmount())->toBe(125.5)
        ->and($row->getNote())->toBe('Approved repair');
});

\test('recipe assistant creates a category through the shared catalog service', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $tool = \nativeResourceTool($admin, 'conversation-recipes', 'write_recipes');
    $arguments = [
        'operation' => 'create_recipe_category',
        'store_id' => null,
        'target_id' => null,
        'context_json' => '{}',
        'values_json' => \json_encode(['name' => 'Assistant drinks'], \JSON_THROW_ON_ERROR),
    ];
    $arguments = \nativeResourceArguments($arguments);

    \expect($tool->shouldRequestApproval(new Request($arguments, 'call-recipe')))
        ->toBeInstanceOf(Approval::class)
        ->and(RecipeCategory::query()->count())->toBe(0);

    $tool->handle(new Request($arguments, 'call-recipe', 'invocation-recipe'));

    \expect(RecipeCategory::query()->sole()->getName())->toBe('Assistant drinks');
});

\test('workforce assistant creates the same shift with worker wage snapshot', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey(), 'hourly_rate' => 245.5]);
    $tool = \nativeResourceTool($admin, 'conversation-workforce', 'write_shifts');
    $arguments = [
        'operation' => 'create_shift',
        'store_id' => $store->getKey(),
        'target_id' => null,
        'context_json' => \json_encode(['worker_id' => $worker->getKey()], \JSON_THROW_ON_ERROR),
        'values_json' => \json_encode([
            'date' => '2026-09-01',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'allow_overlap' => false,
        ], \JSON_THROW_ON_ERROR),
    ];
    $arguments = \nativeResourceArguments($arguments);

    \expect($tool->shouldRequestApproval(new Request($arguments, 'call-shift')))
        ->toBeInstanceOf(Approval::class)
        ->and(Shift::query()->count())->toBe(0);

    $tool->handle(new Request($arguments, 'call-shift', 'invocation-shift'));
    $shift = Shift::query()->sole();

    \expect($shift->getWorkerId())->toBe($worker->getKey())
        ->and($shift->getHourlyRate())->toBe(245.5);
});

\test('administration assistant creates an item and warehouse stock entry atomically', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $tool = \nativeResourceTool($admin, 'conversation-administration', 'write_items');
    $arguments = [
        'operation' => 'create_item',
        'store_id' => null,
        'target_id' => null,
        'context_json' => '{}',
        'values_json' => \json_encode([
            'title' => 'Assistant coffee',
            'sku' => 'AI-COFFEE',
            'unit' => 'kg',
            'purchase_price' => '320.50',
            'description' => 'Created after individual approval.',
        ], \JSON_THROW_ON_ERROR),
    ];
    $arguments = \nativeResourceArguments($arguments);

    \expect($tool->shouldRequestApproval(new Request($arguments, 'call-item')))
        ->toBeInstanceOf(Approval::class)
        ->and(Item::query()->count())->toBe(0);

    $tool->handle(new Request($arguments, 'call-item', 'invocation-item'));
    $item = Item::query()->sole();

    \expect($item->getTitle())->toBe('Assistant coffee')
        ->and($item->storeItems()->where('store_id', $warehouse->getKey())->sole()->getQuantity())->toBe(0);
});

function nativeResourceTool(User $actor, string $conversationId, string $name): AbstractApprovableResourceTool
{
    $tool = (new AssistantToolCatalog())->find($actor, $conversationId, $name);

    if (!$tool instanceof AbstractApprovableResourceTool) {
        throw new RuntimeException('Native resource tool not found.');
    }

    return $tool;
}

/**
 * @param array<string, mixed> $legacy
 *
 * @return array<string, mixed>
 */
function nativeResourceArguments(array $legacy): array
{
    $context = \json_decode(\is_string($legacy['context_json'] ?? null) ? $legacy['context_json'] : '{}', true, 32, \JSON_THROW_ON_ERROR);
    $values = \json_decode(\is_string($legacy['values_json'] ?? null) ? $legacy['values_json'] : '{}', true, 32, \JSON_THROW_ON_ERROR);

    return ['request' => \array_filter([
        'action' => $legacy['operation'],
        'store_id' => $legacy['store_id'] ?? null,
        'target_id' => $legacy['target_id'] ?? null,
        'context' => $context,
        'values' => $values,
    ], static fn(mixed $value): bool => $value !== null && $value !== [])];
}
