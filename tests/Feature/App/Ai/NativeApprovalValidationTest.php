<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Ai\Tools\WriteWorkersTool;
use Illuminate\Support\Collection;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Exceptions\ApprovalMismatchException;
use Laravel\Ai\Gateway\FakeTextGateway;
use Laravel\Ai\Gateway\TextGenerationLoop;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;

\test('a worker proposal with only its required fields reaches native approval', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
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

    StockflowAssistant::fake([
        new ToolCall('worker-call', 'write_workers', $arguments),
    ]);
    $events = \collect(StockflowAssistant::make($admin, 'worker-approval-contract')
        ->stream('Create Leo Do as a worker for 130 Kč per hour.'));
    $approval = $events->first(static fn(mixed $event): bool => $event instanceof ToolApprovalRequest);

    \expect($approval)->toBeInstanceOf(ToolApprovalRequest::class)
        ->and($approval->pendingApprovals->sole()->arguments)->toBe($arguments);
});

\test('native approval validation rejects unknown missing and already resolved decisions', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $arguments = [
        'request' => [
            'action' => 'create_worker',
            'values' => ['first_name' => 'Leo', 'last_name' => 'Do', 'hourly_rate' => 130],
        ],
    ];
    $tool = new WriteWorkersTool($admin, 'native-approval-contract');
    $first = new ToolCall('first-call', $tool->name(), $arguments);
    $second = new ToolCall('second-call', $tool->name(), $arguments);
    $loop = new TextGenerationLoop(new FakeTextGateway([]));

    \expect(fn(): array => $loop->validateApproval(
        ['unknown-call' => Decision::approve()],
        [new AssistantMessage('', new Collection([$first]))],
        [$tool],
    ))->toThrow(ApprovalMismatchException::class, 'do not match the pending tool calls');

    \expect(fn(): array => $loop->validateApproval(
        ['first-call' => Decision::approve()],
        [new AssistantMessage('', new Collection([$first, $second]))],
        [$tool],
    ))->toThrow(ApprovalMismatchException::class, 'do not match the pending tool calls');

    \expect(fn(): array => $loop->validateApproval(
        ['first-call' => Decision::approve()],
        [
            new AssistantMessage('', new Collection([$first])),
            new ToolResultMessage(new Collection([
                new ToolResult('first-call', $tool->name(), $arguments, 'Completed'),
            ])),
        ],
        [$tool],
    ))->toThrow(ApprovalMismatchException::class, 'already-resolved tool call ids');
});
