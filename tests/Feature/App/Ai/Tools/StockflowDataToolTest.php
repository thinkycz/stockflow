<?php

declare(strict_types=1);

use App\Ai\AssistantToolCatalog;
use App\Domain\Finance\FinancialReportService;
use App\Enums\FinancialDirectionEnum;
use App\Models\ChecklistDay;
use App\Models\ChecklistItem;
use App\Models\FinancialRecurringExpense;
use App\Models\FinancialRecurringExpenseVersion;
use App\Models\GiftVoucher;
use App\Models\GiftVoucherBatch;
use App\Models\Item;
use App\Models\OperationalDailyDigest;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeInstruction;
use App\Models\RecipeVariant;
use App\Models\Shift;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use App\Models\Worker;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

\test('every bounded read explicitly reports and continues partial results', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    [$otherAdmin] = \createIsolatedUserWithWarehouse();
    Item::factory()->count(55)->create(['user_id' => $admin->getKey()]);
    Item::factory()->create(['user_id' => $otherAdmin->getKey(), 'title' => 'Other company secret']);
    $tool = (new AssistantToolCatalog())->find($admin, 'query-conversation', 'read_items');

    \expect($tool)->toBeInstanceOf(Tool::class);
    $first = \readToolResult($tool, [
        'request' => ['operation' => 'list', 'limit' => 50],
    ]);
    $second = \readToolResult($tool, [
        'request' => [
            'operation' => 'list',
            'limit' => 50,
            'cursor' => $first['next_cursor'],
        ],
    ]);
    $titles = [
        ...\array_column($first['records'], 'title'),
        ...\array_column($second['records'], 'title'),
    ];

    \expect($first)->toMatchArray([
        'version' => 2,
        'resource' => 'items',
        'operation' => 'list',
        'returned_count' => 50,
        'complete' => false,
        'has_more' => true,
        'warnings' => ['PARTIAL_RESULT'],
    ])->and($first['as_of'])->toBeString()
        ->and($first['next_cursor'])->toBeString()
        ->and($second['returned_count'])->toBe(5)
        ->and($second['complete'])->toBeTrue()
        ->and($second['has_more'])->toBeFalse()
        ->and($second['next_cursor'])->toBeNull()
        ->and($titles)->toHaveCount(55)
        ->and(\array_unique($titles))->toHaveCount(55)
        ->and($titles)->not->toContain('Other company secret');
});

\test('read cursors are encrypted and bound to actor resource and normalized filters', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    [$otherAdmin] = \createIsolatedUserWithWarehouse();
    foreach (['Alpha', 'Beta', 'Gamma'] as $title) {
        Item::factory()->create(['user_id' => $admin->getKey(), 'title' => $title]);
    }
    $tool = (new AssistantToolCatalog())->find($admin, 'cursor-security', 'read_items');
    $otherTool = (new AssistantToolCatalog())->find($otherAdmin, 'cursor-security', 'read_items');

    \expect($tool)->toBeInstanceOf(Tool::class)
        ->and($otherTool)->toBeInstanceOf(Tool::class);
    $first = \readToolResult($tool, [
        'request' => ['operation' => 'list', 'search' => 'a', 'limit' => 1],
    ]);
    $cursor = $first['next_cursor'];

    \expect($cursor)->toBeString();
    $wrongFilter = \readToolResult($tool, [
        'request' => ['operation' => 'list', 'search' => 'different', 'limit' => 1, 'cursor' => $cursor],
    ]);
    $wrongActor = \readToolResult($otherTool, [
        'request' => ['operation' => 'list', 'search' => 'a', 'limit' => 1, 'cursor' => $cursor],
    ]);
    $tampered = \readToolResult($tool, [
        'request' => ['operation' => 'list', 'search' => 'a', 'limit' => 1, 'cursor' => $cursor . 'tampered'],
    ]);

    \expect($wrongFilter['ok'])->toBeFalse()
        ->and($wrongFilter['error']['code'])->toBe('INVALID_REQUEST')
        ->and($wrongFilter['error']['repairable'])->toBeTrue()
        ->and($wrongActor['ok'])->toBeFalse()
        ->and($wrongActor['error']['code'])->toBe('INVALID_REQUEST')
        ->and($wrongActor['error']['repairable'])->toBeTrue()
        ->and($tampered['ok'])->toBeFalse()
        ->and($tampered['error']['code'])->toBe('INVALID_REQUEST')
        ->and($tampered['error']['repairable'])->toBeTrue();
});

\test('read cursors expire and report concurrent dataset changes safely', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    Item::factory()->count(3)->create(['user_id' => $admin->getKey()]);
    $tool = (new AssistantToolCatalog())->find($admin, 'cursor-lifecycle', 'read_items');

    \expect($tool)->toBeInstanceOf(Tool::class);
    $first = \readToolResult($tool, [
        'request' => ['operation' => 'list', 'limit' => 1],
    ]);
    Item::factory()->create(['user_id' => $admin->getKey()]);
    $changed = \readToolResult($tool, [
        'request' => ['operation' => 'list', 'limit' => 1, 'cursor' => $first['next_cursor']],
    ]);

    \expect($changed['ok'])->toBeFalse()
        ->and($changed['error']['code'])->toBe('DATA_CHANGED')
        ->and($changed['warnings'])->toContain('DATA_CHANGED');

    $fresh = \readToolResult($tool, [
        'request' => ['operation' => 'list', 'limit' => 1],
    ]);
    $this->travel(31)->minutes();
    $expired = \readToolResult($tool, [
        'request' => ['operation' => 'list', 'limit' => 1, 'cursor' => $fresh['next_cursor']],
    ]);

    \expect($expired['ok'])->toBeFalse()
        ->and($expired['error']['code'])->toBe('INVALID_REQUEST');
});

\test('read details stay tenant scoped and encoded results remain within the byte budget', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    [$otherAdmin] = \createIsolatedUserWithWarehouse();
    $foreign = Item::factory()->create(['user_id' => $otherAdmin->getKey()]);
    Item::factory()->count(55)->create([
        'user_id' => $admin->getKey(),
        'description' => \str_repeat('Příliš dlouhý popis ', 500),
    ]);
    $tool = (new AssistantToolCatalog())->find($admin, 'bounded-detail', 'read_items');

    \expect($tool)->toBeInstanceOf(Tool::class);
    $forbidden = \readToolResult($tool, [
        'request' => ['operation' => 'detail', 'id' => $foreign->getKey()],
    ]);
    $encoded = $tool->handle(new Request([
        'request' => ['operation' => 'list', 'limit' => 50],
    ], 'byte-limit'));
    $page = \json_decode($encoded, true, flags: \JSON_THROW_ON_ERROR);

    \expect($forbidden['ok'])->toBeFalse()
        ->and($forbidden['error']['code'])->toBe('NOT_FOUND_OR_NOT_AUTHORIZED')
        ->and(\mb_strlen($encoded, '8bit'))->toBeLessThanOrEqual(65536)
        ->and($page['has_more'])->toBeTrue()
        ->and($page['next_cursor'])->toBeString()
        ->and($page['truncated_fields'])->not->toBeEmpty();
});

\test('shift month summary includes early dates beyond the first raw page', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->createOne(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->createOne(['user_id' => $admin->getKey()]);

    foreach (\range(0, 59) as $offset) {
        Shift::factory()->createOne([
            'user_id' => $admin->getKey(),
            'store_id' => $store->getKey(),
            'worker_id' => $worker->getKey(),
            'date' => Carbon::parse('2026-09-01')->addDays(\intdiv($offset, 2))->toDateString(),
            'start_time' => $offset % 2 === 0 ? '08:00' : '12:00',
            'end_time' => $offset % 2 === 0 ? '12:00' : '16:00',
            'hourly_rate' => $worker->getHourlyRate(),
        ]);
    }

    $tool = (new AssistantToolCatalog())->find($admin, 'shift-summary', 'read_shifts');

    \expect($tool)->toBeInstanceOf(Tool::class);
    $result = \readToolResult($tool, [
        'request' => [
            'operation' => 'summary',
            'store_id' => $store->getKey(),
            'year' => 2026,
            'month' => 9,
        ],
    ]);

    \expect($result)->toMatchArray([
        'version' => 2,
        'resource' => 'shifts',
        'operation' => 'summary',
        'complete' => true,
        'has_more' => false,
    ])->and($result['summary']['shift_count'])->toBe(60)
        ->and($result['summary']['scheduled_days'])->toBe(30)
        ->and($result['summary']['first_shift_date'])->toBe('2026-09-01')
        ->and($result['summary']['last_shift_date'])->toBe('2026-09-30')
        ->and($result['summary']['total_scheduled_minutes'])->toBe(14400)
        ->and($result['summary']['can_determine_full_coverage'])->toBeFalse()
        ->and($result['summary']['required_start_time'])->toBeNull()
        ->and($result['summary']['required_end_time'])->toBeNull()
        ->and($result['summary']['days_without_shifts'])->toBe([])
        ->and($result['summary']['fully_covered'])->toBeNull()
        ->and($result['summary']['daily_coverage'])->toHaveCount(30);

    $coverage = \readToolResult($tool, [
        'request' => [
            'operation' => 'summary',
            'store_id' => $store->getKey(),
            'year' => 2026,
            'month' => 9,
            'required_start_time' => '08:00',
            'required_end_time' => '16:00',
        ],
    ]);

    \expect($coverage['summary']['can_determine_full_coverage'])->toBeTrue()
        ->and($coverage['summary']['fully_covered'])->toBeTrue()
        ->and($coverage['summary']['days_without_full_coverage'])->toBe([])
        ->and($coverage['summary']['daily_coverage'][0]['scheduled_intervals'])->toBe([
            ['start_time' => '08:00', 'end_time' => '16:00'],
        ]);
});

/**
 * @param array<string, mixed> $arguments
 *
 * @return array<string, mixed>
 */
function readToolResult(Tool $tool, array $arguments): array
{
    return \json_decode(
        $tool->handle(new Request($arguments, 'read-call')),
        true,
        512,
        \JSON_THROW_ON_ERROR,
    );
}

\test('recipe lookup resolves a natural question to complete company recipe instructions', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    [$otherAdmin] = \createIsolatedUserWithWarehouse();
    $category = RecipeCategory::factory()->createOne([
        'user_id' => $admin->getKey(),
        'name' => 'Čaje',
    ]);
    $recipe = Recipe::factory()->createOne([
        'user_id' => $admin->getKey(),
        'recipe_category_id' => $category->getKey(),
        'name' => 'OOLONG MILK TEA (3.5l) (steep)',
        'note' => '70g oolong + 30g ceylon',
    ]);
    $variant = RecipeVariant::factory()->createOne([
        'recipe_id' => $recipe->getKey(),
        'name' => '3.5 l',
    ]);
    foreach ([
        '2.5l water (90 degrees) + 100g tea (let steep for 10min)',
        '+ 900g powdered milk',
        '+ ice up to 3.5l',
    ] as $position => $text) {
        RecipeInstruction::query()->create([
            'recipe_variant_id' => $variant->getKey(),
            'position' => $position + 1,
            'type' => $position === 0 ? 'ingredient' : 'action',
            'text' => $text,
            'action_key' => $position === 0 ? 'add' : 'other',
            'quantity_value' => $position === 0 ? 3 : null,
            'quantity_text' => null,
            'unit' => $position === 0 ? 'ml' : null,
            'ingredient_name' => $position === 0 ? 'liquid sugar' : null,
            'target' => $position === 0 ? 'shaker' : null,
            'icon_group' => $position === 0 ? 'syrup_sweetener' : 'neutral',
            'is_inferred' => false,
        ]);
    }
    $foreignCategory = RecipeCategory::factory()->createOne(['user_id' => $otherAdmin->getKey()]);
    Recipe::factory()->createOne([
        'user_id' => $otherAdmin->getKey(),
        'recipe_category_id' => $foreignCategory->getKey(),
        'name' => 'OOLONG MILK TEA PRIVATE',
    ]);

    $tool = (new AssistantToolCatalog())->find($admin, 'recipe-lookup', 'read_recipes');

    \expect($tool)->toBeInstanceOf(Tool::class);
    $result = \readToolResult($tool, [
        'request' => [
            'operation' => 'lookup',
            'dataset' => 'recipes',
            'query' => 'jak se dela oolong milk tea podle naseho receptu',
            'limit' => 5,
        ],
    ]);

    \expect($result)->toMatchArray([
        'ok' => true,
        'resource' => 'recipes',
        'operation' => 'lookup',
        'dataset' => 'recipes',
        'scope' => ['type' => 'company', 'store_scoped' => false],
        'returned_count' => 1,
        'complete' => true,
    ])->and($result['records'][0]['name'])->toBe('OOLONG MILK TEA (3.5l) (steep)')
        ->and($result['records'][0]['variants'][0]['instructions'])->toHaveCount(3)
        ->and($result['records'][0]['variants'][0]['instructions'][0]['text'])
        ->toBe('2.5l water (90 degrees) + 100g tea (let steep for 10min)')
        ->and($result['records'][0]['variants'][0]['topping_adjustments'])->toMatchArray([
            'base_toppings' => '0–1',
            'components' => [[
                'ingredient_name' => 'liquid sugar',
                'unit' => 'ml',
                'base_quantity' => 3,
                'two_toppings_quantity' => 0,
                'three_toppings_quantity' => 0,
            ]],
        ])
        ->and(\array_column($result['records'], 'name'))->not->toContain('OOLONG MILK TEA PRIVATE');
});

\test('recipe category results state that they cannot establish recipe absence', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    RecipeCategory::factory()->createOne(['user_id' => $admin->getKey(), 'name' => 'Čaje']);
    $tool = (new AssistantToolCatalog())->find($admin, 'recipe-category-boundary', 'read_recipes');

    \expect($tool)->toBeInstanceOf(Tool::class);
    $result = \readToolResult($tool, [
        'request' => ['operation' => 'list', 'dataset' => 'categories'],
    ]);

    \expect($result)->toMatchArray([
        'ok' => true,
        'scope' => ['type' => 'company', 'store_scoped' => false],
        'capability' => [
            'can_determine_recipe_existence' => false,
            'recipe_lookup_operation' => 'lookup',
            'recipe_lookup_dataset' => 'recipes',
        ],
    ]);
});

\test('statement and financial readers expose the same decision data as human reports', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->createOne([
        'user_id' => $admin->getKey(),
        'name' => 'Žižkov',
    ]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 8)->createOne();
    StatementDay::factory()->createOne([
        'statement_id' => $statement->getKey(),
        'date' => '2026-08-10',
        'cash' => 1000,
        'card' => 2000,
        'wolt' => 500,
        'bolt' => 0,
        'bolt_cash' => 0,
        'foodora' => 0,
        'total' => 3500,
    ]);
    (new FinancialReportService())->createManualRow(
        $admin,
        $store,
        2026,
        8,
        FinancialDirectionEnum::EXPENSE,
        'Rent',
        '2026-08-01',
        1200,
        'Monthly lease',
    );

    $statements = (new AssistantToolCatalog())->find($admin, 'statement-facts', 'read_statements');
    $financials = (new AssistantToolCatalog())->find($admin, 'financial-facts', 'read_financial_reports');

    \expect($statements)->toBeInstanceOf(Tool::class)
        ->and($financials)->toBeInstanceOf(Tool::class);

    $statementResult = \readToolResult($statements, [
        'request' => [
            'operation' => 'summary',
            'store_id' => $store->getKey(),
            'year' => 2026,
            'month' => 8,
        ],
    ]);
    $financialResult = \readToolResult($financials, [
        'request' => [
            'operation' => 'summary',
            'dataset' => 'reports',
            'store_id' => $store->getKey(),
            'year' => 2026,
            'month' => 8,
        ],
    ]);

    \expect($statementResult['ok'])->toBeTrue()
        ->and($statementResult['dataset'])->toBe('reports')
        ->and($statementResult['summary']['totals']['total_revenue'])->toBe(3500)
        ->and($statementResult['summary']['channels'])->toMatchArray(['cash' => 1000, 'card' => 2000, 'wolt' => 500])
        ->and($statementResult['summary']['totals'])->toHaveKeys([
            'gross_margin',
            'margin_percent',
            'provisions',
        ])->and($financialResult['ok'])->toBeTrue()
        ->and($financialResult['dataset'])->toBe('reports')
        ->and($financialResult['summary']['totals'])->toBe(['income' => 3330, 'expenses' => 1200, 'profit' => 2130])
        ->and($financialResult['summary']['income_rows'])->not->toBeEmpty()
        ->and($financialResult['summary']['expense_rows'])->toContainEqual([
            'id' => 'manual:1',
            'kind' => 'manual',
            'direction' => 'expense',
            'source_type' => null,
            'source_key' => null,
            'label' => 'Rent',
            'occurred_on' => '2026-08-01',
            'calculated_amount' => 1200,
            'override_amount' => null,
            'effective_amount' => 1200,
            'note' => 'Monthly lease',
            'details' => [],
            'manual_row_id' => 1,
        ]);
});

\test('embedded settings checklist and recurring version datasets expose real scoped records', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    [$otherAdmin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->createOne(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->createOne(['user_id' => $admin->getKey()]);
    OperationalDailyDigest::factory()->createOne([
        'company_user_id' => $admin->getKey(),
        'status' => 'failed',
        'activity_count' => 7,
        'attempt_count' => 2,
        'last_error' => 'Delivery was rejected.',
    ]);
    OperationalDailyDigest::factory()->createOne([
        'company_user_id' => $otherAdmin->getKey(),
        'activity_count' => 999,
    ]);
    $day = ChecklistDay::factory()->createOne([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-08-29',
    ]);
    ChecklistItem::factory()->createOne([
        'checklist_day_id' => $day->getKey(),
        'text' => 'Clean the coffee machine',
        'completed_by_worker_id' => $worker->getKey(),
        'completed_at' => Carbon::parse('2026-08-29 10:00:00'),
    ]);
    $expense = FinancialRecurringExpense::factory()->forStore($store)->createOne([
        'starts_on' => '2026-01-01',
    ]);
    FinancialRecurringExpenseVersion::factory()->createOne([
        'financial_recurring_expense_id' => $expense->getKey(),
        'effective_from' => '2026-08-01',
        'label' => 'Monthly rent',
        'amount' => 15000,
        'due_day' => 5,
    ]);

    $settings = (new AssistantToolCatalog())->find($admin, 'deep-settings', 'read_settings');
    $checklists = (new AssistantToolCatalog())->find($admin, 'deep-checklists', 'read_checklists');
    $recurring = (new AssistantToolCatalog())->find($admin, 'deep-recurring', 'read_recurring_expenses');

    \expect($settings)->toBeInstanceOf(Tool::class)
        ->and($checklists)->toBeInstanceOf(Tool::class)
        ->and($recurring)->toBeInstanceOf(Tool::class);

    $digestSummary = \readToolResult($settings, ['request' => [
        'operation' => 'summary',
        'dataset' => 'digests',
    ]]);
    $checklistItems = \readToolResult($checklists, ['request' => [
        'operation' => 'list',
        'dataset' => 'items',
        'worker_id' => $worker->getKey(),
        'search' => 'coffee',
    ]]);
    $versions = \readToolResult($recurring, ['request' => [
        'operation' => 'list',
        'dataset' => 'versions',
        'expense_id' => $expense->getKey(),
    ]]);

    \expect($digestSummary['summary'])->toMatchArray([
        'digest_count' => 1,
        'activity_count' => 7,
        'attempt_count' => 2,
        'by_status' => ['failed' => 1],
    ])->and($checklistItems['dataset'])->toBe('items')
        ->and($checklistItems['records'])->toHaveCount(1)
        ->and($checklistItems['records'][0])->toMatchArray([
            'text' => 'Clean the coffee machine',
            'completed_by_worker_name' => $worker->getFullName(),
            'completed' => true,
        ])->and($versions['dataset'])->toBe('versions')
        ->and($versions['records'])->toHaveCount(1)
        ->and($versions['records'][0])->toMatchArray([
            'label' => 'Monthly rent',
            'amount' => 15000.0,
            'store_id' => $store->getKey(),
        ]);
});

\test('voucher status reads use effective expiry and never expose voucher codes', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $expiredBatch = GiftVoucherBatch::factory()->createOne([
        'user_id' => $admin->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'quantity' => 1,
        'expires_at' => Carbon::now()->subDay(),
    ]);
    $activeBatch = GiftVoucherBatch::factory()->createOne([
        'user_id' => $admin->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'quantity' => 1,
        'expires_at' => Carbon::now()->addDay(),
    ]);
    GiftVoucher::factory()->createOne([
        'gift_voucher_batch_id' => $expiredBatch->getKey(),
        'user_id' => $admin->getKey(),
        'status' => 'active',
    ]);
    GiftVoucher::factory()->createOne([
        'gift_voucher_batch_id' => $activeBatch->getKey(),
        'user_id' => $admin->getKey(),
        'status' => 'active',
    ]);
    $tool = (new AssistantToolCatalog())->find($admin, 'safe-vouchers', 'read_gift_vouchers');

    \expect($tool)->toBeInstanceOf(Tool::class);
    $expired = \readToolResult($tool, ['request' => [
        'operation' => 'list',
        'dataset' => 'vouchers',
        'status' => 'expired',
    ]]);
    $active = \readToolResult($tool, ['request' => [
        'operation' => 'list',
        'dataset' => 'vouchers',
        'status' => 'active',
    ]]);

    \expect($expired['records'])->toHaveCount(1)
        ->and($expired['records'][0]['status'])->toBe('expired')
        ->and($expired['records'][0])->not->toHaveKeys(['code', 'code_hash'])
        ->and($active['records'])->toHaveCount(1)
        ->and($active['records'][0]['status'])->toBe('active')
        ->and($active['records'][0])->not->toHaveKeys(['code', 'code_hash']);
});
