<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Ai\Tools\ReadWorkersTool;
use App\Enums\AssistantActionStatusEnum;
use App\Models\AssistantActionAudit;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StoreItem;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolApprovalRequested;
use Laravel\Ai\Events\ToolApprovalResolved;
use Laravel\Ai\Events\ToolFailed;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Responses\Data\ToolResult;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('native approval lifecycle events create a sanitized durable audit and record rejection', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $admin->getKey()]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 0,
    ]);
    $conversationId = Str::uuid()->toString();
    $agent = new StockflowAssistant($admin, $conversationId);
    $arguments = [
        'password' => 'must-not-be-stored',
        'request' => [
            'action' => 'create_stock_movement',
            'mode' => 'incoming',
            'store_id' => $warehouse->getKey(),
            'values' => [
                'items' => [[
                    'item_id' => $item->getKey(),
                    'quantity' => 3,
                ]],
            ],
        ],
    ];

    Resolver::resolveEventDispatcher()->dispatch(new ToolApprovalRequested(
        'proposal-invocation',
        $agent,
        \collect([new PendingApproval('call-rejected', 'write_stock_movements', $arguments, 'Approve movement')]),
        $conversationId,
        $admin,
    ));

    $audit = AssistantActionAudit::query()->sole();

    \expect($audit->getStatus())->toBe(AssistantActionStatusEnum::PENDING_APPROVAL)
        ->and($audit->getArguments()['password'])->toBe('[REDACTED]')
        ->and($audit->getArguments())->not->toHaveKey('prompt');

    Resolver::resolveEventDispatcher()->dispatch(new ToolApprovalResolved(
        'decision-invocation',
        $agent,
        \collect([new ToolResult(
            'call-rejected',
            'write_stock_movements',
            $arguments,
            'Rejected by administrator',
            denied: true,
        )]),
        $conversationId,
        $admin,
    ));

    \expect($audit->fresh()?->getStatus())->toBe(AssistantActionStatusEnum::REJECTED)
        ->and(StockMovement::query()->count())->toBe(0);
});

\test('audit redacts Slack and credential values nested inside native structured arguments', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $conversationId = Str::uuid()->toString();
    $agent = new StockflowAssistant($admin, $conversationId);

    Resolver::resolveEventDispatcher()->dispatch(new ToolApprovalRequested(
        'proposal-administration',
        $agent,
        \collect([new PendingApproval('call-slack', 'write_settings', [
            'request' => [
                'action' => 'update_slack_channel',
                'values' => [
                    'company_slack_channel' => 'https://hooks.slack.test/secret',
                    'password' => 'never-store-me',
                ],
            ],
        ], 'Update Slack')]),
        $conversationId,
        $admin,
    ));

    $stored = AssistantActionAudit::query()->sole()->getArguments();
    $request = Typer::assertStringKeyArray(Typer::assertArray($stored['request'] ?? null));
    $values = Typer::assertStringKeyArray(Typer::assertArray($request['values'] ?? null));

    \expect($values['company_slack_channel'])->toBe('[REDACTED]')
        ->and($values['password'])->toBe('[REDACTED]');
});

\test('native approval resolution distinguishes unchanged approval from a safe edit', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $conversationId = Str::uuid()->toString();
    $agent = new StockflowAssistant($admin, $conversationId);
    $arguments = [
        'request' => [
            'action' => 'create_worker',
            'values' => ['first_name' => 'Leo', 'last_name' => 'Original', 'hourly_rate' => 130],
        ],
    ];
    $events = Resolver::resolveEventDispatcher();

    $events->dispatch(new ToolApprovalRequested(
        'proposal-edit',
        $agent,
        \collect([new PendingApproval('call-edit', 'write_workers', $arguments, 'Create worker')]),
        $conversationId,
        $admin,
    ));
    $events->dispatch(new ToolApprovalResolved(
        'decision-edit',
        $agent,
        \collect([new ToolResult(
            'call-edit',
            'write_workers',
            [...$arguments, 'request' => [...$arguments['request'], 'values' => [...$arguments['request']['values'], 'last_name' => 'Edited']]],
            'Approved',
        )]),
        $conversationId,
        $admin,
    ));

    \expect(AssistantActionAudit::query()->sole()->getStatus())->toBe(AssistantActionStatusEnum::EDITED);
});

\test('late approval lifecycle events never downgrade a terminal audit status', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $conversationId = Str::uuid()->toString();
    $agent = new StockflowAssistant($admin, $conversationId);
    $arguments = ['request' => [
        'action' => 'create_worker',
        'values' => ['first_name' => 'Leo', 'last_name' => 'Do', 'hourly_rate' => 130],
    ]];
    $pending = new PendingApproval('late-call', 'write_workers', $arguments, 'Create worker');
    $events = Resolver::resolveEventDispatcher();

    $events->dispatch(new ToolApprovalRequested('proposal', $agent, \collect([$pending]), $conversationId, $admin));
    $audit = AssistantActionAudit::query()->sole();
    $audit->update(['status' => AssistantActionStatusEnum::SUCCEEDED->value]);
    $events->dispatch(new ToolApprovalResolved(
        'decision',
        $agent,
        \collect([new ToolResult('late-call', 'write_workers', $arguments, 'Approved')]),
        $conversationId,
        $admin,
    ));
    $events->dispatch(new ToolApprovalRequested('late-proposal', $agent, \collect([$pending]), $conversationId, $admin));

    \expect($audit->fresh()?->getStatus())->toBe(AssistantActionStatusEnum::SUCCEEDED);
});

\test('native read-tool lifecycle events record bounded success and failure outcomes', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $conversationId = Str::uuid()->toString();
    $turnId = Str::uuid()->toString();
    $agent = new StockflowAssistant($admin, $conversationId);
    $tool = new ReadWorkersTool($admin, $conversationId);
    $arguments = ['request' => ['operation' => 'summary', 'search' => 'Leo']];
    $events = Resolver::resolveEventDispatcher();
    Context::add('assistant_turn_id', $turnId);

    $events->dispatch(new InvokingTool('read-invocation', 'read-tool-success', $agent, $tool, $arguments));
    $events->dispatch(new ToolInvoked(
        'read-invocation',
        'read-tool-success',
        $agent,
        $tool,
        $arguments,
        [
            'version' => 2,
            'ok' => true,
            'resource' => 'workers',
            'operation' => 'summary',
            'dataset' => 'workers',
            'applied_filters' => ['search' => 'Leo'],
            'complete' => true,
            'has_more' => false,
            'returned_count' => 0,
            'warnings' => [],
            'summary' => ['worker_count' => 1],
            'oversized' => \str_repeat('x', 3000),
        ],
        12.5,
    ));
    $succeeded = AssistantActionAudit::query()->where('tool_invocation_id', 'read-tool-success')->sole();
    $result = Typer::assertStringKeyArray(Typer::assertArray($succeeded->getAttribute('result_summary')));
    $readAudit = Typer::assertStringKeyArray(Typer::assertArray($result['_read_audit'] ?? null));

    \expect($succeeded->getStatus())->toBe(AssistantActionStatusEnum::SUCCEEDED)
        ->and($succeeded->getAttribute('turn_id'))->toBe($turnId)
        ->and($succeeded->getAttribute('operation'))->toBe('summary')
        ->and(\mb_strlen(Typer::assertString($result['oversized'] ?? null)))->toBe(2000)
        ->and($readAudit)->toMatchArray([
            'dataset' => 'workers',
            'applied_filters' => ['search' => 'Leo'],
            'complete' => true,
            'returned_count' => 0,
            'has_more' => false,
        ])
        ->and($readAudit['bytes'])->toBeInt();

    $events->dispatch(new InvokingTool('failed-invocation', 'read-tool-failed', $agent, $tool, $arguments));
    $events->dispatch(new ToolFailed(
        'failed-invocation',
        'read-tool-failed',
        $agent,
        $tool,
        $arguments,
        new RuntimeException(\str_repeat('failure', 500)),
        25,
    ));
    $failed = AssistantActionAudit::query()->where('tool_invocation_id', 'read-tool-failed')->sole();

    \expect($failed->getStatus())->toBe(AssistantActionStatusEnum::FAILED)
        ->and(\mb_strlen(Typer::assertString($failed->getAttribute('error_summary'))))->toBe(2000);

    Context::forget('assistant_turn_id');
});
