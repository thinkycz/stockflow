<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OperationalDailyDigestStatusEnum;
use Database\Factories\OperationalDailyDigestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class OperationalDailyDigest extends BaseModel
{
    /** @use HasFactory<OperationalDailyDigestFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'operational_daily_digests';

    /**
     * Search digest status and date.
     *
     * @param Builder<OperationalDailyDigest> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where(static function (Builder $query) use ($search): void {
            $query->where('status', 'like', '%' . $search . '%')
                ->orWhere('digest_date', 'like', '%' . $search . '%');
        });
    }

    /**
     * Restrict digest queries to explicit columns.
     *
     * @param Builder<OperationalDailyDigest> $query
     *
     * @return Builder<OperationalDailyDigest>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'company_user_id', 'digest_date', 'period_start', 'period_end', 'status', 'snapshot', 'activity_count', 'attempt_count', 'last_error', 'queued_at', 'sent_at', 'created_at', 'updated_at',
        ]);
    }

    /**
     * Owning company id.
     */
    public function getCompanyUserId(): int
    {
        return $this->assertInt('company_user_id');
    }

    /**
     * Covered Prague calendar date.
     */
    public function getDigestDate(): Carbon
    {
        return $this->assertCarbon('digest_date');
    }

    /**
     * UTC period start.
     */
    public function getPeriodStart(): Carbon
    {
        return $this->assertCarbon('period_start');
    }

    /**
     * UTC period end.
     */
    public function getPeriodEnd(): Carbon
    {
        return $this->assertCarbon('period_end');
    }

    /**
     * Delivery status.
     */
    public function getStatus(): OperationalDailyDigestStatusEnum
    {
        return OperationalDailyDigestStatusEnum::from($this->assertString('status'));
    }

    /**
     * Immutable structured summary snapshot.
     *
     * @return array<string, mixed>
     */
    public function getSnapshot(): array
    {
        return Typer::assertStringKeyArray(Typer::assertArray($this->getAttribute('snapshot')));
    }

    /**
     * Unique journal activity count.
     */
    public function getActivityCount(): int
    {
        return $this->assertInt('activity_count');
    }

    /**
     * Slack transport attempt count.
     */
    public function getAttemptCount(): int
    {
        return $this->assertInt('attempt_count');
    }

    /**
     * Safe final delivery error.
     */
    public function getLastError(): string|null
    {
        return $this->assertNullableString('last_error');
    }

    /**
     * Time the notification was queued.
     */
    public function getQueuedAt(): Carbon|null
    {
        return $this->assertNullableCarbon('queued_at');
    }

    /**
     * Successful Slack delivery time.
     */
    public function getSentAt(): Carbon|null
    {
        return $this->assertNullableCarbon('sent_at');
    }

    /**
     * Mark the digest as queued for delivery.
     */
    public function markQueued(): void
    {
        $this->setAttribute('status', OperationalDailyDigestStatusEnum::QUEUED->value);
        $this->setAttribute('last_error', null);
        $this->setAttribute('queued_at', Carbon::now('UTC'));
        $this->save();
    }

    /**
     * Mark the digest as failed with a safe user-facing error.
     */
    public function markFailed(string $error): void
    {
        $this->setAttribute('status', OperationalDailyDigestStatusEnum::FAILED->value);
        $this->setAttribute('last_error', \mb_substr($error, 0, 500));
        $this->save();
    }

    /**
     * Count one concrete Slack transport attempt.
     */
    public function incrementAttemptCount(): void
    {
        $this->setAttribute('attempt_count', $this->getAttemptCount() + 1);
        $this->save();
    }

    /**
     * Mark the digest as delivered successfully.
     */
    public function markSent(): void
    {
        $this->setAttribute('status', OperationalDailyDigestStatusEnum::SENT->value);
        $this->setAttribute('last_error', null);
        $this->setAttribute('sent_at', Carbon::now('UTC'));
        $this->save();
    }

    /**
     * Model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'digest_date' => 'date',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'snapshot' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
