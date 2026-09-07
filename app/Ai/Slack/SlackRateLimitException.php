<?php

declare(strict_types=1);

namespace App\Ai\Slack;

use RuntimeException;

final class SlackRateLimitException extends RuntimeException
{
    /**
     * Preserve the server's minimum retry delay.
     */
    public function __construct(/**
     * Server Retry-After in seconds.
     */ public readonly int $retryAfter)
    {
        parent::__construct('Slack rate limited the request.');
    }
}
