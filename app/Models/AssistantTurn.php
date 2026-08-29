<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssistantTurnStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class AssistantTurn extends BaseModel
{
    /**
     * Turn identifiers are client-generated UUIDs.
     */
    public $incrementing = false;

    /**
     * Turn identifiers are stored as strings.
     */
    protected $keyType = 'string';

    /**
     * The table associated with the model.
     */
    protected $table = 'assistant_turns';

    /**
     * Durable turns explicitly allow their client-generated identifier.
     */
    protected $guarded = [];

    /**
     * Search durable turns by conversation, kind, or status.
     *
     * @param Builder<AssistantTurn> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where(static function (Builder $query) use ($search): void {
            $query->where('conversation_id', 'like', '%' . $search . '%')
                ->orWhere('kind', 'like', '%' . $search . '%')
                ->orWhere('status', 'like', '%' . $search . '%');
        });
    }

    /**
     * Restrict durable turn queries to explicit columns.
     *
     * @param Builder<AssistantTurn> $query
     *
     * @return Builder<AssistantTurn>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'actor_user_id', 'conversation_id', 'kind', 'status', 'input_hash', 'input_payload',
            'error_summary', 'queued_at', 'started_at', 'completed_at', 'cancel_requested_at',
            'created_at', 'updated_at',
        ]);
    }

    /**
     * Ordered journal events for this turn.
     *
     * @return HasMany<AssistantTurnEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AssistantTurnEvent::class, 'turn_id');
    }

    /**
     * Stable client-generated turn identifier.
     */
    public function getTurnId(): string
    {
        return Typer::assertString($this->getAttribute('id'));
    }

    /**
     * Administrator that owns the turn.
     */
    public function getActorUserId(): int
    {
        return Typer::assertInt($this->getAttribute('actor_user_id'));
    }

    /**
     * Native Laravel AI conversation identifier.
     */
    public function getConversationId(): string
    {
        return Typer::assertString($this->getAttribute('conversation_id'));
    }

    /**
     * Submission kind: message or approval decisions.
     */
    public function getKind(): string
    {
        return Typer::assertString($this->getAttribute('kind'));
    }

    /**
     * Current monotonic durable turn state.
     */
    public function getStatus(): AssistantTurnStatusEnum
    {
        return AssistantTurnStatusEnum::from(Typer::assertString($this->getAttribute('status')));
    }

    /**
     * Hash used to reject conflicting idempotency-key reuse.
     */
    public function getInputHash(): string
    {
        return Typer::assertString($this->getAttribute('input_hash'));
    }

    /**
     * Encrypted turn input retained only while required for execution or retry.
     *
     * @return array<string, mixed>
     */
    public function getInputPayload(): array
    {
        return Typer::assertStringKeyArray(Typer::assertArray($this->getAttribute('input_payload')));
    }

    /**
     * Time at which the turn became visible to the queue.
     */
    public function getQueuedAt(): Carbon
    {
        return Typer::assertInstance($this->getAttribute('queued_at'), Carbon::class);
    }

    /**
     * Attribute casts for encrypted input and lifecycle timestamps.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_payload' => 'encrypted:array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
        ];
    }
}
