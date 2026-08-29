<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class AssistantTurnEvent extends BaseModel
{
    /**
     * The table associated with the model.
     */
    protected $table = 'assistant_turn_events';

    /**
     * Search journal events by owning turn or protocol event type.
     *
     * @param Builder<AssistantTurnEvent> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where(static function (Builder $query) use ($search): void {
            $query->where('turn_id', 'like', '%' . $search . '%')
                ->orWhere('event_type', 'like', '%' . $search . '%');
        });
    }

    /**
     * Restrict journal event queries to explicit columns.
     *
     * @param Builder<AssistantTurnEvent> $query
     *
     * @return Builder<AssistantTurnEvent>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'turn_id', 'sequence', 'event_type', 'payload', 'created_at', 'updated_at']);
    }

    /**
     * Sequence number within one durable turn.
     */
    public function getSequence(): int
    {
        return $this->assertInt('sequence');
    }

    /**
     * Decrypted Vercel stream event payload.
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return Typer::assertStringKeyArray(Typer::assertArray($this->getAttribute('payload')));
    }

    /**
     * Attribute casts for encrypted stream payloads.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payload' => 'encrypted:array'];
    }
}
