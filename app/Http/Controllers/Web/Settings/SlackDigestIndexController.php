<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Settings;

use App\Models\OperationalDailyDigest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class SlackDigestIndexController
{
    /**
     * Maximum retained daily digests shown in the archive.
     */
    public const int TAKE = 90;

    /**
     * Render the company daily digest archive.
     */
    public function __invoke(): Response
    {
        $company = User::mustAuth();
        $digests = OperationalDailyDigest::querySelect(OperationalDailyDigest::query())
            ->where('company_user_id', $company->getKey())
            ->latest('digest_date')
            ->limit(self::TAKE)
            ->get();

        return Inertia::render('settings/slack-digests/Index', [
            'digests' => $digests->map(static function (mixed $value): array {
                $digest = Typer::assertInstance($value, OperationalDailyDigest::class);

                return [
                    'id' => $digest->getKey(),
                    'date' => $digest->getDigestDate()->toDateString(),
                    'status' => $digest->getStatus()->value,
                    'activity_count' => $digest->getActivityCount(),
                    'attempt_count' => $digest->getAttemptCount(),
                    'last_error' => $digest->getLastError(),
                    'queued_at' => $digest->getQueuedAt()?->toJSON(),
                    'sent_at' => $digest->getSentAt()?->toJSON(),
                ];
            })->values()->all(),
        ]);
    }
}
