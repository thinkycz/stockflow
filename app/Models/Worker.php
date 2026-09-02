<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\WorkerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class Worker extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<WorkerFactory> */
    use HasFactory;

    /**
     * Distinct calendar colors selected for readable text on a light tint.
     *
     * @var list<string>
     */
    private const array CALENDAR_COLORS = [
        '#2563EB',
        '#7C3AED',
        '#DB2777',
        '#DC2626',
        '#EA580C',
        '#A16207',
        '#16A34A',
        '#0D9488',
        '#0891B2',
        '#4F46E5',
        '#9333EA',
        '#C026D3',
    ];

    /**
     * The table associated with the model.
     */
    protected $table = 'workers';

    /**
     * Scope a query to only include workers matching the search term.
     *
     * Matches against first name or last name.
     *
     * @param Builder<Worker> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where(static function (Builder $query) use ($search): void {
            $query->where('first_name', 'like', '%' . $search . '%')
                ->orWhere('last_name', 'like', '%' . $search . '%');
        });
    }

    /**
     * Scope a query to workers available for new work.
     *
     * @param Builder<Worker> $query
     */
    public static function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Scope a query to archived workers retained for historical records.
     *
     * @param Builder<Worker> $query
     */
    public static function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<Worker> $query
     *
     * @return Builder<Worker>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'first_name', 'last_name', 'calendar_color', 'hourly_rate', 'attendance_rating_enabled', 'archived_at', 'created_at', 'updated_at']);
    }

    /**
     * Available curated calendar colors.
     *
     * @return list<string>
     */
    public static function calendarColors(): array
    {
        return self::CALENDAR_COLORS;
    }

    /**
     * Normalize an optional calendar color for persistence.
     */
    public static function normalizeCalendarColor(string|null $calendarColor): string|null
    {
        return $calendarColor === null ? null : \mb_strtoupper($calendarColor);
    }

    /**
     * First name getter.
     */
    public function getFirstName(): string
    {
        return $this->assertString('first_name');
    }

    /**
     * Last name getter.
     */
    public function getLastName(): string
    {
        return $this->assertString('last_name');
    }

    /**
     * Full name getter (first + last).
     */
    public function getFullName(): string
    {
        return $this->getFirstName() . ' ' . $this->getLastName();
    }

    /**
     * Stable color used to identify the worker throughout shift calendars.
     */
    public function getCalendarColor(): string
    {
        $calendarColor = $this->getStoredCalendarColor();

        if ($calendarColor !== null) {
            return \mb_strtoupper($calendarColor);
        }

        return $this->getAutomaticCalendarColor();
    }

    /**
     * Deterministic fallback color used when no explicit color is stored.
     */
    public function getAutomaticCalendarColor(): string
    {
        return self::CALENDAR_COLORS[(Typer::assertInt($this->getKey()) - 1) % \count(self::CALENDAR_COLORS)];
    }

    /**
     * Explicitly stored calendar color, or null when automatic assignment is used.
     */
    public function getStoredCalendarColor(): string|null
    {
        return Typer::assertNullableString($this->getAttribute('calendar_color'));
    }

    /**
     * Hourly rate getter (CZK).
     */
    public function getHourlyRate(): float
    {
        return (float) $this->getHourlyRateDecimal();
    }

    /**
     * Hourly rate without floating-point conversion.
     */
    public function getHourlyRateDecimal(): string
    {
        return Typer::assertString($this->getAttribute('hourly_rate'));
    }

    /**
     * Whether attendance rating is enabled for this worker.
     */
    public function isAttendanceRatingEnabled(): bool
    {
        return $this->assertBool('attendance_rating_enabled');
    }

    /**
     * Archive timestamp, retained separately from deletion semantics.
     */
    public function getArchivedAt(): Carbon|null
    {
        return $this->assertNullableCarbon('archived_at');
    }

    /**
     * Whether this worker is unavailable for new work.
     */
    public function isArchived(): bool
    {
        return $this->getArchivedAt() instanceof Carbon;
    }

    /**
     * User id getter.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'attendance_rating_enabled' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }
}
