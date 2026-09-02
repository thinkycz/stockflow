<?php

declare(strict_types=1);

use App\Ai\AssistantActionPresenter;
use App\Ai\AssistantToolCatalog;
use App\Ai\Tools\AskUserChoiceTool;
use App\Ai\Tools\ConfiguredWriteResourceTool;
use App\Ai\Tools\WriteWorkersTool;
use App\Enums\AssistantActionStatusEnum;
use App\Models\AssistantActionAudit;
use App\Models\ChecklistDay;
use App\Models\Store;
use App\Models\Worker;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;

\test('native tool catalog exposes concrete resource tools with unique names', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $tools = (new AssistantToolCatalog())->tools($admin, 'native-resource-catalog');
    $names = \array_map(ToolNameResolver::resolve(...), $tools);

    \expect($names)
        ->toHaveCount(41)
        ->toContain(
            'read_stores',
            'write_stores',
            'read_users',
            'write_users',
            'read_workers',
            'write_workers',
            'read_settings',
            'write_settings',
            'read_attendance',
            'write_attendance',
            'read_shifts',
            'write_shifts',
            'read_shift_requests',
            'write_shift_requests',
            'read_shift_share_links',
            'write_shift_share_links',
            'read_checklists',
            'write_checklists',
            'read_noticeboard',
            'write_noticeboard',
            'read_items',
            'write_items',
            'read_inventory_counts',
            'write_inventory_counts',
            'read_stock_movements',
            'write_stock_movements',
            'read_statements',
            'write_statements',
            'read_recipes',
            'write_recipes',
            'read_recipe_tests',
            'write_recipe_tests',
            'read_payroll',
            'write_payroll',
            'read_financial_reports',
            'write_financial_reports',
            'read_recurring_expenses',
            'write_recurring_expenses',
            'read_gift_vouchers',
            'write_gift_vouchers',
            'ask_user_choice',
        )
        ->not->toContain('stockflow_data', 'inventory_mutation', 'workforce_tasks', 'administration_tasks')
        ->and(\array_unique($names))->toHaveCount(\count($names));
});

\test('every writer is represented by its own final native tool class', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $tools = (new AssistantToolCatalog())->tools($admin, 'native-writer-classes');
    $classes = [];

    foreach ($tools as $tool) {
        if (!\str_starts_with(ToolNameResolver::resolve($tool), 'write_')) {
            continue;
        }

        $reflection = new ReflectionClass($tool);
        $classes[] = $tool::class;

        \expect($tool::class)->not->toBe(ConfiguredWriteResourceTool::class)
            ->and($reflection->isFinal())->toBeTrue();
    }

    \expect(\array_unique($classes))->toHaveCount(20);
});

\test('every concrete read tool executes a bounded tenant scoped query', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $tools = (new AssistantToolCatalog())->tools($admin, 'native-read-catalog');

    foreach ($tools as $tool) {
        if (!\str_starts_with(ToolNameResolver::resolve($tool), 'read_')) {
            continue;
        }

        $result = \json_decode($tool->handle(new Request([
            'request' => ['operation' => 'list', 'limit' => 1],
        ], 'read-call')), true, 32, \JSON_THROW_ON_ERROR);

        \expect($result['version'])->toBe(2)
            ->and($result['returned_count'])->toBeLessThanOrEqual(1)
            ->and($result['complete'])->toBeBool()
            ->and($result['has_more'])->toBeBool()
            ->and($result['records'])->toBeArray();
    }
});

\test('every concrete reader publishes closed typed branches and bounded list limits', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $catalog = new AssistantToolCatalog();
    $tools = \collect($catalog->tools($admin, 'native-read-schemas'))->keyBy(ToolNameResolver::resolve(...));

    foreach ($catalog->readCapabilities() as $name => $declaredDatasets) {
        $tool = $tools->get($name);
        $schema = (new ObjectSchema($tool->schema(new JsonSchemaTypeFactory())))->toSchema();
        $branches = $schema['properties']['request']['anyOf'];
        $operations = [];
        $schemaDatasets = [];

        foreach ($branches as $branch) {
            \expect($branch['additionalProperties'])->toBeFalse();
            $operations[] = $branch['properties']['operation']['enum'][0];

            if (isset($branch['properties']['limit'])) {
                \expect($branch['properties']['limit']['maximum'])->toBeLessThanOrEqual(50);
            }
            foreach ($branch['properties']['dataset']['enum'] ?? [] as $dataset) {
                $schemaDatasets[] = $dataset;
            }
        }

        \expect($operations)->toContain('list', 'summary');
        if ($schemaDatasets !== []) {
            \expect(\array_values(\array_unique($schemaDatasets)))->toEqualCanonicalizing($declaredDatasets);
        }
    }
});

\test('recipe reader exposes a closed natural-language lookup branch', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $tool = (new AssistantToolCatalog())->find($admin, 'recipe-lookup-schema', 'read_recipes');

    \expect($tool)->toBeInstanceOf(Tool::class);
    $schema = (new ObjectSchema($tool->schema(new JsonSchemaTypeFactory())))->toSchema();
    $branches = $schema['properties']['request']['anyOf'];
    $lookup = \collect($branches)->first(
        static fn(array $branch): bool => ($branch['properties']['operation']['enum'][0] ?? null) === 'lookup',
    );

    \expect($lookup)->toBeArray()
        ->and($lookup['additionalProperties'])->toBeFalse()
        ->and($lookup['required'])->toContain('operation', 'dataset', 'query')
        ->and($lookup['properties']['dataset']['enum'])->toBe(['recipes'])
        ->and($lookup['properties']['limit']['maximum'])->toBeLessThanOrEqual(50);
});

\test('every native writer publishes one action branch per declared capability', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $catalog = new AssistantToolCatalog();
    $tools = \collect($catalog->tools($admin, 'native-schema-catalog'))->keyBy(ToolNameResolver::resolve(...));

    foreach ($catalog->capabilities() as $name => $actions) {
        $tool = $tools->get($name);
        $schema = (new ObjectSchema($tool->schema(new JsonSchemaTypeFactory())))->toSchema();
        $branches = $schema['properties']['request']['anyOf'];
        $schemaActions = \array_values(\array_unique(\array_map(
            static fn(array $branch): string => $branch['properties']['action']['enum'][0],
            $branches,
        )));

        \expect($schemaActions)->toEqualCanonicalizing($actions);

        foreach ($branches as $branch) {
            \expect($branch['additionalProperties'])->toBeFalse();
        }
    }
});

\test('write workers exposes action specific typed branches with genuinely optional values', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $tool = new WriteWorkersTool($admin, 'native-worker-contract');
    $schema = (new ObjectSchema($tool->schema(new JsonSchemaTypeFactory())))->toSchema();
    $branches = $schema['properties']['request']['anyOf'];
    $create = $branches[0]['properties'];

    \expect($branches)->toHaveCount(4)
        ->and($create['action']['enum'])->toBe(['create_worker'])
        ->and($create['values']['required'])->toBe(['first_name', 'last_name', 'hourly_rate'])
        ->and($create['values']['properties'])->toHaveKeys([
            'first_name',
            'last_name',
            'hourly_rate',
            'attendance_rating_enabled',
            'calendar_color',
        ])
        ->and($create['values']['additionalProperties'])->toBeFalse();
});

\test('worker assistant exposes restoration as a separate approved lifecycle action', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $worker = Worker::factory()->create(['user_id' => $admin->getKey(), 'archived_at' => \now()]);
    $tool = new WriteWorkersTool($admin, 'native-worker-restore');
    $arguments = ['request' => ['action' => 'restore_worker', 'target_id' => $worker->getKey()]];

    \expect($tool->shouldRequestApproval(new Request($arguments, 'worker-restore-call')))
        ->toBeInstanceOf(Laravel\Ai\Approvals\Approval::class);

    $result = \json_decode(
        $tool->handle(new Request($arguments, 'worker-restore-call', 'worker-restore-invocation')),
        true,
        32,
        \JSON_THROW_ON_ERROR,
    );

    \expect($result['operation'])->toBe('restore_worker')
        ->and($worker->refresh()->isArchived())->toBeFalse();
});

\test('store assistant returns its typed archival outcome', function (bool $historical): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    if ($historical) {
        ChecklistDay::factory()->create([
            'user_id' => $admin->getKey(),
            'store_id' => $store->getKey(),
            'date' => '2026-01-01',
        ]);
    }
    $tool = (new AssistantToolCatalog())->find($admin, 'native-store-removal', 'write_stores');
    $arguments = ['request' => [
        'action' => 'delete_store',
        'store_id' => $store->getKey(),
        'target_id' => $store->getKey(),
    ]];

    $result = \json_decode(
        $tool->handle(new Request($arguments, 'store-removal-call', 'store-removal-invocation')),
        true,
        32,
        \JSON_THROW_ON_ERROR,
    );

    \expect($result['record']['removal_outcome'])->toBe('archived');
})->with([
    'assistant audit is store history' => [false],
    'existing business history' => [true],
]);

\test('minimal native worker creation reaches Laravel approval without optional fields', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $tool = new WriteWorkersTool($admin, 'native-worker-approval');
    $arguments = [
        'request' => [
            'action' => 'create_worker',
            'values' => [
                'first_name' => 'Leo',
                'last_name' => 'Do',
                'hourly_rate' => 130,
            ],
        ],
    ];

    $approval = $tool->shouldRequestApproval(new Request($arguments, 'worker-call'));

    \expect($approval)->toBeInstanceOf(Laravel\Ai\Approvals\Approval::class)
        ->and(\json_decode((string) $approval?->reason, true, 512, \JSON_THROW_ON_ERROR))->toMatchArray([
            'version' => 2,
            'kind' => 'action_confirmation',
            'summary_key' => 'assistant.action_summaries.create_worker',
            'summary_params' => [
                'first_name' => 'Leo',
                'last_name' => 'Do',
                'hourly_rate' => 130,
            ],
        ]);
});

\test('every writer action has one safe localized presentation in all frontend locales', function (): void {
    $catalog = new AssistantToolCatalog();
    $presenter = new AssistantActionPresenter();
    $keys = [];

    foreach ($catalog->capabilities() as $actions) {
        foreach ($actions as $action) {
            $keys[] = $presenter->summaryKey($action);
        }
    }

    foreach (['en', 'cs', 'sk'] as $locale) {
        $translations = \json_decode((string) \file_get_contents(\resource_path('js/i18n/' . $locale . '.json')), true, 512, \JSON_THROW_ON_ERROR);

        foreach (\array_unique($keys) as $key) {
            $action = \str_replace('assistant.action_summaries.', '', $key);
            \expect($translations['assistant']['action_summaries'] ?? [])->toHaveKey($action);
        }
    }
});

\test('ask user choice requires one locked option selection before returning a result', function (): void {
    $tool = new AskUserChoiceTool();
    $arguments = [
        'question' => 'Which store should be used?',
        'options' => [
            ['id' => 'brno', 'label' => 'Brno'],
            ['id' => 'ostrava', 'label' => 'Ostrava', 'description' => 'The warehouse destination.'],
        ],
    ];
    $approval = $tool->shouldRequestApproval(new Request($arguments, 'choice-call'));
    $preview = \json_decode((string) $approval?->reason, true, 32, \JSON_THROW_ON_ERROR);

    \expect($preview)->toMatchArray([
        'version' => 1,
        'kind' => 'choice',
        'question' => 'Which store should be used?',
        'options' => $arguments['options'],
    ]);

    $result = \json_decode($tool->handle(new Request([
        ...$arguments,
        'selected_option_id' => 'ostrava',
    ], 'choice-call')), true, 32, \JSON_THROW_ON_ERROR);

    \expect($result)->toMatchArray([
        'selected_option_id' => 'ostrava',
        'selected_label' => 'Ostrava',
    ]);
});

\test('invalid worker proposals return a bounded repairable result without approval or mutation', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $tool = new WriteWorkersTool($admin, 'native-invalid-worker');
    $arguments = [
        'request' => [
            'action' => 'create_worker',
            'values' => ['first_name' => 'Leo', 'hourly_rate' => 130],
        ],
    ];
    $request = new Request($arguments, 'invalid-worker-call', 'invalid-worker-invocation');

    \expect($tool->shouldRequestApproval($request))->toBeNull();

    $result = \json_decode($tool->handle($request), true, 32, \JSON_THROW_ON_ERROR);

    \expect($result)->toMatchArray(['status' => 'failed', 'repairable' => true])
        ->and($result['error'])->toBeString()
        ->and(Worker::query()->count())->toBe(0)
        ->and(AssistantActionAudit::query()->sole()->getStatus())->toBe(AssistantActionStatusEnum::FAILED);
});
