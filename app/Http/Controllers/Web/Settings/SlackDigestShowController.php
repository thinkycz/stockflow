<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Settings;

use App\Models\OperationalDailyDigest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class SlackDigestShowController
{
    /**
     * Render one company-scoped daily digest.
     */
    public function __invoke(int $digest): Response
    {
        $company = User::mustAuth();
        $model = Typer::assertInstance(OperationalDailyDigest::query()
            ->where('company_user_id', $company->getKey())
            ->whereKey($digest)
            ->firstOrFail(), OperationalDailyDigest::class);

        return Inertia::render('settings/slack-digests/Show', [
            'digest' => [
                'id' => $model->getKey(),
                'date' => $model->getDigestDate()->toDateString(),
                'status' => $model->getStatus()->value,
                'activity_count' => $model->getActivityCount(),
                'attempt_count' => $model->getAttemptCount(),
                'last_error' => $model->getLastError(),
                'queued_at' => $model->getQueuedAt()?->toJSON(),
                'sent_at' => $model->getSentAt()?->toJSON(),
                'snapshot' => $model->getSnapshot(),
            ],
        ]);
    }
}
