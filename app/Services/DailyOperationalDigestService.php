<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationalDailyDigestStatusEnum;
use App\Models\OperationalDailyDigest;
use App\Models\User;
use App\Notifications\OperationalDailyDigestSlackNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

class DailyOperationalDigestService
{
    /**
     * Create and queue the oldest missing eligible digest.
     */
    public function createNext(User $company, CarbonImmutable $now): OperationalDailyDigest|null
    {
        $activation = $company->getOperationalDigestStartedOn();
        if ($activation === null) {
            return null;
        }

        $candidate = CarbonImmutable::parse($activation->toDateString(), DailyOperationalDigestBuilder::BUSINESS_TIMEZONE);
        $latest = $now->setTimezone(DailyOperationalDigestBuilder::BUSINESS_TIMEZONE)->subDay()->startOfDay();
        while ($candidate->lessThanOrEqualTo($latest)) {
            $exists = OperationalDailyDigest::query()
                ->where('company_user_id', $company->getKey())
                ->whereDate('digest_date', $candidate->toDateString())
                ->exists();
            if (!$exists) {
                return $this->createAndQueue($company, $candidate);
            }
            $candidate = $candidate->addDay();
        }

        return null;
    }

    /**
     * Requeue a failed company digest using the current Slack configuration.
     */
    public function retry(User $company, OperationalDailyDigest $digest): OperationalDailyDigest
    {
        return DB::transaction(function () use ($company, $digest): OperationalDailyDigest {
            $locked = Typer::assertInstance(OperationalDailyDigest::query()
                ->where('company_user_id', $company->getKey())
                ->whereKey($digest->getKey())
                ->lockForUpdate()
                ->firstOrFail(), OperationalDailyDigest::class);

            if ($locked->getStatus() !== OperationalDailyDigestStatusEnum::FAILED) {
                Thrower::default()->message('digest', Typer::assertString(\__('Only failed daily Slack digests can be retried.')))->throw();
            }

            $this->queue($company, $locked);

            return $locked;
        });
    }

    /**
     * Persist one immutable digest and queue its notification when configured.
     */
    private function createAndQueue(User $company, CarbonImmutable $date): OperationalDailyDigest
    {
        $snapshot = (new DailyOperationalDigestBuilder())->build($company, $date);
        $digest = Typer::assertInstance(OperationalDailyDigest::query()->createOrFirst([
            'company_user_id' => $company->getKey(),
            'digest_date' => $snapshot['date'],
        ], [
            'period_start' => $snapshot['period_start'],
            'period_end' => $snapshot['period_end'],
            'status' => OperationalDailyDigestStatusEnum::PENDING->value,
            'snapshot' => $snapshot,
            'activity_count' => $snapshot['activity_count'],
            'attempt_count' => 0,
            'last_error' => null,
            'queued_at' => null,
            'sent_at' => null,
        ]), OperationalDailyDigest::class);

        if (!$digest->wasRecentlyCreated) {
            return $digest;
        }

        $this->queue($company, $digest);

        return $digest;
    }

    /**
     * Queue an existing immutable digest or record a safe configuration failure.
     */
    private function queue(User $company, OperationalDailyDigest $digest): void
    {
        $token = \mb_trim(Config::inject()->assertNullableString('services.slack.notifications.bot_user_oauth_token') ?? '');
        if ($token === '') {
            $digest->markFailed('Chybí Slack bot token.');

            return;
        }

        $channel = \mb_trim($company->getCompanySlackChannel() ?? '');
        if ($channel === '') {
            $digest->markFailed('Chybí firemní Slack kanál.');

            return;
        }

        try {
            $digest->markQueued();
            Resolver::resolveNotificationFactory()->send(
                (new AnonymousNotifiable())->route('slack', $channel),
                new OperationalDailyDigestSlackNotification($digest->getKey()),
            );
        } catch (Throwable $exception) {
            $digest->markFailed('Notifikaci se nepodařilo zařadit do fronty.');
            Resolver::resolveExceptionHandler()->report($exception);
        }
    }
}
