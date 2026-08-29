<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssistantActionClassificationEnum;
use App\Enums\AssistantActionStatusEnum;
use Database\Factories\AssistantActionAuditFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class AssistantActionAudit extends BaseModel
{
    /** @use HasFactory<AssistantActionAuditFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'assistant_action_audits';

    /**
     * Search assistant action audit snapshots.
     *
     * @param Builder<AssistantActionAudit> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where(static function (Builder $query) use ($search): void {
            $query->where('tool_name', 'like', '%' . $search . '%')
                ->orWhere('operation', 'like', '%' . $search . '%')
                ->orWhere('actor_email', 'like', '%' . $search . '%');
        });
    }

    /**
     * Restrict assistant action audit queries to explicit columns.
     *
     * @param Builder<AssistantActionAudit> $query
     *
     * @return Builder<AssistantActionAudit>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'actor_user_id', 'actor_email', 'conversation_id', 'turn_id', 'invocation_id', 'tool_call_id',
            'tool_invocation_id', 'tool_name', 'domain', 'operation', 'classification', 'status',
            'store_id', 'store_name', 'target_type', 'target_id', 'arguments', 'result_summary',
            'error_summary', 'proposed_at', 'decided_at', 'started_at', 'completed_at', 'duration_ms',
            'created_at', 'updated_at',
        ]);
    }

    /**
     * Actor user id snapshot.
     */
    public function getActorUserId(): int
    {
        return $this->assertInt('actor_user_id');
    }

    /**
     * Actor email snapshot.
     */
    public function getActorEmail(): string
    {
        return $this->assertString('actor_email');
    }

    /**
     * Owning conversation id.
     */
    public function getConversationId(): string
    {
        return $this->assertString('conversation_id');
    }

    /**
     * Stable provider tool-call id when available.
     */
    public function getToolCallId(): string|null
    {
        return $this->assertNullableString('tool_call_id');
    }

    /**
     * Assistant tool name.
     */
    public function getToolName(): string
    {
        return $this->assertString('tool_name');
    }

    /**
     * Assistant operation classification.
     */
    public function getClassification(): AssistantActionClassificationEnum
    {
        return AssistantActionClassificationEnum::from($this->assertString('classification'));
    }

    /**
     * Assistant action lifecycle status.
     */
    public function getStatus(): AssistantActionStatusEnum
    {
        return AssistantActionStatusEnum::from($this->assertString('status'));
    }

    /**
     * Sanitized argument snapshot.
     *
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return Typer::assertStringKeyArray(Typer::assertArray($this->getAttribute('arguments')));
    }

    /**
     * Proposal timestamp.
     */
    public function getProposedAt(): Carbon
    {
        return $this->assertCarbon('proposed_at');
    }

    /**
     * Model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'result_summary' => 'array',
            'proposed_at' => 'datetime',
            'decided_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_ms' => 'float',
        ];
    }
}
