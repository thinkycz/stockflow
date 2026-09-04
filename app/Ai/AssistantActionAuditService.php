<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\StockflowAssistant;
use App\Ai\Tools\AuditableAssistantTool;
use App\Enums\AssistantActionClassificationEnum;
use App\Enums\AssistantActionStatusEnum;
use App\Models\AssistantActionAudit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolApprovalRequested;
use Laravel\Ai\Events\ToolApprovalResolved;
use Laravel\Ai\Events\ToolFailed;
use Laravel\Ai\Events\ToolInvoked;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

final class AssistantActionAuditService
{
    /**
     * Maximum stored summary characters for any scalar model/tool output.
     */
    private const int SUMMARY_CHARACTERS = 2000;

    /**
     * Record a read-only invocation from Laravel AI's native run context.
     */
    public function toolInvoking(InvokingTool $event): void
    {
        if (!$event->agent instanceof StockflowAssistant ||
            !$event->tool instanceof AuditableAssistantTool ||
            $event->tool->auditClassification($event->arguments) !== AssistantActionClassificationEnum::READ) {
            return;
        }

        AssistantActionAudit::query()->updateOrCreate([
            'tool_invocation_id' => $event->toolInvocationId,
        ], [
            'actor_user_id' => $event->agent->actor()->getKey(),
            'actor_email' => $event->agent->actor()->getEmail(),
            'conversation_id' => $event->agent->assistantConversationId(),
            'turn_id' => $this->currentTurnId(),
            'invocation_id' => $event->invocationId,
            'tool_name' => $event->tool->name(),
            'domain' => $event->tool->auditDomain(),
            'operation' => $event->tool->auditOperation($event->arguments),
            'classification' => AssistantActionClassificationEnum::READ->value,
            'status' => AssistantActionStatusEnum::RUNNING->value,
            ...$event->tool->auditContext($event->arguments),
            'arguments' => $this->sanitize($event->arguments),
            'proposed_at' => Carbon::now(),
            'started_at' => Carbon::now(),
        ]);
    }

    /**
     * Complete a read-only audit from Laravel AI's native invocation event.
     */
    public function toolInvoked(ToolInvoked $event): void
    {
        AssistantActionAudit::query()->where('tool_invocation_id', $event->toolInvocationId)
            ->whereNotIn('status', [AssistantActionStatusEnum::SUCCEEDED->value, AssistantActionStatusEnum::FAILED->value, AssistantActionStatusEnum::UNCERTAIN->value, AssistantActionStatusEnum::REJECTED->value])
            ->update([
                'status' => AssistantActionStatusEnum::SUCCEEDED->value,
                'result_summary' => $this->readSummary($event->result),
                'completed_at' => Carbon::now(),
                'duration_ms' => $event->time,
            ]);
    }

    /**
     * Fail a read-only audit from Laravel AI's native invocation event.
     */
    public function toolFailed(ToolFailed $event): void
    {
        AssistantActionAudit::query()->where('tool_invocation_id', $event->toolInvocationId)
            ->whereNotIn('status', [AssistantActionStatusEnum::SUCCEEDED->value, AssistantActionStatusEnum::FAILED->value, AssistantActionStatusEnum::UNCERTAIN->value, AssistantActionStatusEnum::REJECTED->value])
            ->update([
                'status' => AssistantActionStatusEnum::FAILED->value,
                'error_summary' => $this->truncate($event->exception->getMessage()),
                'completed_at' => Carbon::now(),
                'duration_ms' => $event->time,
            ]);
    }

    /**
     * Record every native approval proposal after the paused turn is persisted.
     */
    public function approvalRequested(ToolApprovalRequested $event): void
    {
        if (!$event->agent instanceof StockflowAssistant || $event->conversationId === null) {
            return;
        }

        foreach ($event->pendingApprovals as $pending) {
            $tool = $event->agent->findAuditableTool($pending->tool);

            if (!$tool instanceof AuditableAssistantTool) {
                continue;
            }

            $context = $tool->auditContext($pending->arguments);
            $existing = $this->find($event->conversationId, $pending->id);

            if ($existing instanceof AssistantActionAudit && \in_array($existing->getStatus(), [
                AssistantActionStatusEnum::RUNNING,
                AssistantActionStatusEnum::SUCCEEDED,
                AssistantActionStatusEnum::FAILED,
                AssistantActionStatusEnum::UNCERTAIN,
                AssistantActionStatusEnum::REJECTED,
            ], true)) {
                continue;
            }

            AssistantActionAudit::query()->updateOrCreate([
                'conversation_id' => $event->conversationId,
                'tool_call_id' => $pending->id,
            ], [
                'actor_user_id' => $event->agent->actor()->getKey(),
                'actor_email' => $event->agent->actor()->getEmail(),
                'turn_id' => $this->currentTurnId(),
                'invocation_id' => $event->invocationId,
                'tool_name' => $pending->tool,
                'domain' => $tool->auditDomain(),
                'operation' => $tool->auditOperation($pending->arguments),
                'classification' => $tool->auditClassification($pending->arguments)->value,
                'status' => AssistantActionStatusEnum::PENDING_APPROVAL->value,
                ...$context,
                'arguments' => $this->sanitize($pending->arguments),
                'proposed_at' => Carbon::now(),
            ]);
        }
    }

    /**
     * Record rejected decisions and the final decision arguments emitted by Laravel AI.
     */
    public function approvalResolved(ToolApprovalResolved $event): void
    {
        if (!$event->agent instanceof StockflowAssistant || $event->conversationId === null) {
            return;
        }

        foreach ($event->toolResults as $result) {
            $audit = $this->find($event->conversationId, $result->id);

            if (!$audit instanceof AssistantActionAudit) {
                continue;
            }

            if (\in_array($audit->getStatus(), [
                AssistantActionStatusEnum::RUNNING,
                AssistantActionStatusEnum::SUCCEEDED,
                AssistantActionStatusEnum::FAILED,
                AssistantActionStatusEnum::UNCERTAIN,
                AssistantActionStatusEnum::REJECTED,
            ], true)) {
                continue;
            }

            $arguments = $this->sanitize($result->arguments);
            $attributes = [
                'decided_at' => $audit->getAttribute('decided_at') ?? Carbon::now(),
                'arguments' => $arguments,
                'status' => $arguments === $audit->getArguments()
                    ? AssistantActionStatusEnum::APPROVED->value
                    : AssistantActionStatusEnum::EDITED->value,
            ];

            if ($result->denied) {
                $attributes = [
                    ...$attributes,
                    'status' => AssistantActionStatusEnum::REJECTED->value,
                    'result_summary' => $this->summary($result->result),
                    'completed_at' => Carbon::now(),
                ];
            }

            $audit->update($attributes);
        }
    }

    /**
     * Persist a bounded invalid proposal without creating an approval card.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $result
     */
    public function invalidProposal(
        StockflowAssistant $agent,
        AuditableAssistantTool $tool,
        array $arguments,
        string $toolCallId,
        array $result,
    ): void {
        AssistantActionAudit::query()->updateOrCreate([
            'conversation_id' => $agent->assistantConversationId(),
            'tool_call_id' => $toolCallId,
        ], [
            'actor_user_id' => $agent->actor()->getKey(),
            'actor_email' => $agent->actor()->getEmail(),
            'turn_id' => $this->currentTurnId(),
            'invocation_id' => $toolCallId,
            'tool_name' => $tool->name(),
            'domain' => $tool->auditDomain(),
            'operation' => $this->safeOperation($tool, $arguments),
            'classification' => $this->safeClassification($tool, $arguments)->value,
            'store_id' => null,
            'store_name' => null,
            'target_type' => null,
            'target_id' => null,
            'arguments' => $this->sanitize($arguments),
            'result_summary' => $this->summary($result),
            'error_summary' => $this->truncate(Typer::parseNullableString($result['error'] ?? null) ?? 'Invalid proposal.'),
            'proposed_at' => Carbon::now(),
            'completed_at' => Carbon::now(),
            'status' => AssistantActionStatusEnum::FAILED->value,
        ]);
    }

    /**
     * Atomically start a mutation or return its already completed safe result.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>|null
     */
    public function start(
        StockflowAssistant $agent,
        AuditableAssistantTool $tool,
        array $arguments,
        string $toolCallId,
        string|null $toolInvocationId,
    ): array|null {
        $audit = $this->find($agent->assistantConversationId(), $toolCallId);

        if ($audit instanceof AssistantActionAudit && $audit->getStatus() === AssistantActionStatusEnum::SUCCEEDED) {
            $result = $audit->getAttribute('result_summary');

            return \is_array($result) ? Typer::assertStringKeyArray($result) : [];
        }

        if ($audit instanceof AssistantActionAudit && \in_array($audit->getStatus(), [
            AssistantActionStatusEnum::RUNNING,
            AssistantActionStatusEnum::FAILED,
            AssistantActionStatusEnum::UNCERTAIN,
            AssistantActionStatusEnum::REJECTED,
        ], true)) {
            throw new RuntimeException('This assistant tool call was already resolved and will not be executed again.');
        }

        if (!$audit instanceof AssistantActionAudit) {
            $context = $tool->auditContext($arguments);
            $audit = AssistantActionAudit::query()->create([
                'actor_user_id' => $agent->actor()->getKey(),
                'actor_email' => $agent->actor()->getEmail(),
                'conversation_id' => $agent->assistantConversationId(),
                'turn_id' => $this->currentTurnId(),
                'invocation_id' => $toolInvocationId ?? $toolCallId,
                'tool_call_id' => $toolCallId,
                'tool_name' => $tool->name(),
                'domain' => $tool->auditDomain(),
                'operation' => $tool->auditOperation($arguments),
                'classification' => $tool->auditClassification($arguments)->value,
                ...$context,
                'arguments' => $this->sanitize($arguments),
                'proposed_at' => Carbon::now(),
                'status' => AssistantActionStatusEnum::APPROVED->value,
            ]);
        }

        $audit->update([
            'turn_id' => $audit->getAttribute('turn_id') ?? $this->currentTurnId(),
            'tool_invocation_id' => $toolInvocationId,
            'arguments' => $this->sanitize($arguments),
            'status' => AssistantActionStatusEnum::RUNNING->value,
            'decided_at' => $audit->getAttribute('decided_at') ?? Carbon::now(),
            'started_at' => Carbon::now(),
        ]);

        return null;
    }

    /**
     * Preserve an external outcome that cannot be safely retried.
     */
    public function uncertain(string $conversationId, string $toolCallId): void
    {
        $this->find($conversationId, $toolCallId)?->update([
            'status' => AssistantActionStatusEnum::UNCERTAIN->value,
            'error_summary' => 'External outcome is uncertain; verify it before any new action.',
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark one invocation successful and retain only its bounded safe result.
     *
     * @param array<string, mixed> $result
     */
    public function succeeded(string $conversationId, string $toolCallId, array $result, float $startedAt): void
    {
        $this->find($conversationId, $toolCallId)?->update([
            'status' => AssistantActionStatusEnum::SUCCEEDED->value,
            'result_summary' => $this->summary($result),
            'completed_at' => Carbon::now(),
            'duration_ms' => (\microtime(true) - $startedAt) * 1000,
        ]);
    }

    /**
     * Mark one invocation failed without recording an unrestricted exception trace.
     */
    public function failed(string $conversationId, string $toolCallId, Throwable $exception, float $startedAt): void
    {
        $audit = $this->find($conversationId, $toolCallId);
        // An after-commit callback can fail after both mutation and outcome persisted.
        if ($audit?->getStatus() === AssistantActionStatusEnum::SUCCEEDED) {
            return;
        }

        $audit?->update([
            'status' => AssistantActionStatusEnum::FAILED->value,
            'error_summary' => $this->truncate($exception->getMessage()),
            'completed_at' => Carbon::now(),
            'duration_ms' => (\microtime(true) - $startedAt) * 1000,
        ]);
    }

    /**
     * Find one audit by its replay-protected key.
     */
    private function find(string $conversationId, string $toolCallId): AssistantActionAudit|null
    {
        $audit = AssistantActionAudit::query()
            ->where('conversation_id', $conversationId)
            ->where('tool_call_id', $toolCallId)
            ->first();

        return $audit instanceof AssistantActionAudit ? $audit : null;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function safeOperation(AuditableAssistantTool $tool, array $arguments): string
    {
        try {
            return $tool->auditOperation($arguments);
        } catch (Throwable) {
            return $tool->name();
        }
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function safeClassification(AuditableAssistantTool $tool, array $arguments): AssistantActionClassificationEnum
    {
        try {
            return $tool->auditClassification($arguments);
        } catch (Throwable) {
            return AssistantActionClassificationEnum::MUTATION;
        }
    }

    /**
     * Redact secrets, binary payloads, provider configuration, and sensitive Slack settings.
     *
     * @param array<array-key, mixed> $payload
     *
     * @return array<array-key, mixed>
     */
    private function sanitize(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $name = (string) $key;

            if (\preg_match('/password|credential|secret|token|api.?key|provider|binary|image|slack/i', $name) === 1) {
                $sanitized[$name] = '[REDACTED]';
            } elseif (\is_array($value)) {
                $sanitized[$name] = $this->sanitize($value);
            } elseif (\is_string($value)) {
                $sanitized[$name] = $this->sanitizeStringValue($name, $value);
            } elseif (\is_scalar($value) || $value === null) {
                $sanitized[$name] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize JSON-envelope strings before they enter the durable ledger.
     */
    private function sanitizeStringValue(string $key, string $value): string
    {
        if (!\str_ends_with($key, '_json')) {
            return $this->truncate($value);
        }

        $decoded = \json_decode($value, true);

        if (!\is_array($decoded)) {
            return $this->truncate($value);
        }

        return $this->truncate(\json_encode(
            $this->sanitize($decoded),
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * Build a bounded result value suitable for the supplemental ledger.
     *
     * @return array<string, mixed>
     */
    private function summary(mixed $result): array
    {
        if (\is_array($result)) {
            $bounded = \array_slice($result, 0, 50, true);

            return \array_is_list($bounded)
                ? ['items' => $this->sanitize($bounded)]
                : Typer::assertStringKeyArray($this->sanitize($bounded));
        }

        if (\is_string($result)) {
            $decoded = \json_decode($result, true);

            if (\is_array($decoded)) {
                $bounded = \array_slice($decoded, 0, 50, true);

                return \array_is_list($bounded)
                    ? ['items' => $this->sanitize($bounded)]
                    : Typer::assertStringKeyArray($this->sanitize($bounded));
            }

            return ['message' => $this->truncate($result)];
        }

        return ['value' => \is_scalar($result) || $result === null ? $result : '[OMITTED]'];
    }

    /**
     * @return array<string, mixed>
     */
    private function readSummary(mixed $result): array
    {
        $summary = $this->summary($result);
        $decoded = \is_string($result) ? \json_decode($result, true) : $result;
        if (!\is_array($decoded) || ($decoded['version'] ?? null) !== 2) {
            return $summary;
        }

        $summary['_read_audit'] = [
            'dataset' => Typer::parseNullableString($decoded['dataset'] ?? null),
            'applied_filters' => $this->sanitize(Typer::assertArray($decoded['applied_filters'] ?? [])),
            'complete' => (bool) ($decoded['complete'] ?? false),
            'returned_count' => Typer::parseNullableInt($decoded['returned_count'] ?? null),
            'has_more' => (bool) ($decoded['has_more'] ?? false),
            'warnings' => $this->sanitize(Typer::assertArray($decoded['warnings'] ?? [])),
            'bytes' => \is_string($result)
                ? \mb_strlen($result, '8bit')
                : \mb_strlen(\json_encode($result, \JSON_THROW_ON_ERROR), '8bit'),
        ];

        return $summary;
    }

    /**
     * Return the durable turn currently invoking the tool, when available.
     */
    private function currentTurnId(): string|null
    {
        $turnId = Context::get('assistant_turn_id');

        return \is_string($turnId) ? $turnId : null;
    }

    /**
     * Truncate one stored scalar summary.
     */
    private function truncate(string $value): string
    {
        return \mb_substr($value, 0, self::SUMMARY_CHARACTERS);
    }
}
