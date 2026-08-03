<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\OperationalActivityTypeEnum;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class OperationalActivityEvent implements ShouldDispatchAfterCommit
{
    /**
     * Activity type.
     */
    public readonly OperationalActivityTypeEnum $type;

    /**
     * Acting user's email address.
     */
    public readonly string $actorEmail;

    /**
     * ISO-8601 event timestamp.
     */
    public readonly string $occurredAt;

    /**
     * Stable application URL.
     */
    public readonly string $url;

    /**
     * @var list<array{channel: string, store: string|null, perspective: string|null}>
     */
    public readonly array $destinations;

    /**
     * @var array<string, string>
     */
    public readonly array $facts;

    /**
     * Create an immutable operational activity snapshot.
     *
     * @param list<array{channel: string, store: string|null, perspective: string|null}> $destinations
     * @param array<string, string> $facts
     */
    public function __construct(
        OperationalActivityTypeEnum $type,
        string $actorEmail,
        string $occurredAt,
        string $url,
        array $destinations,
        array $facts,
    ) {
        $this->type = $type;
        $this->actorEmail = $actorEmail;
        $this->occurredAt = $occurredAt;
        $this->url = $url;
        $this->destinations = $destinations;
        $this->facts = $facts;
    }
}
