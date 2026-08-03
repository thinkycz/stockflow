<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OperationalActivityTypeEnum;
use Database\Factories\OperationalActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class OperationalActivity extends BaseModel
{
    /** @use HasFactory<OperationalActivityFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'operational_activities';

    /**
     * Search immutable activity snapshots.
     *
     * @param Builder<OperationalActivity> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where(static function (Builder $query) use ($search): void {
            $query->where('type', 'like', '%' . $search . '%')
                ->orWhere('actor_email', 'like', '%' . $search . '%');
        });
    }

    /**
     * Restrict activity queries to explicit columns.
     *
     * @param Builder<OperationalActivity> $query
     *
     * @return Builder<OperationalActivity>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'company_user_id', 'type', 'actor_email', 'occurred_at', 'url', 'store_contexts', 'facts', 'created_at', 'updated_at',
        ]);
    }

    /**
     * Owning company account id.
     */
    public function getCompanyUserId(): int
    {
        return $this->assertInt('company_user_id');
    }

    /**
     * Operational activity type.
     */
    public function getType(): OperationalActivityTypeEnum
    {
        return OperationalActivityTypeEnum::from($this->assertString('type'));
    }

    /**
     * Snapshotted actor email.
     */
    public function getActorEmail(): string
    {
        return $this->assertString('actor_email');
    }

    /**
     * Business occurrence timestamp.
     */
    public function getOccurredAt(): Carbon
    {
        return $this->assertCarbon('occurred_at');
    }

    /**
     * Stable authenticated URL.
     */
    public function getUrl(): string
    {
        return $this->assertString('url');
    }

    /**
     * Snapshotted location contexts.
     *
     * @return list<array{store_id: int, store_name: string, perspective: string|null}>
     */
    public function getStoreContexts(): array
    {
        $contexts = [];
        foreach (Typer::assertArray($this->getAttribute('store_contexts')) as $value) {
            $context = Typer::assertStringKeyArray(Typer::assertArray($value));
            $contexts[] = [
                'store_id' => Typer::assertInt($context['store_id'] ?? null),
                'store_name' => Typer::assertString($context['store_name'] ?? null),
                'perspective' => Typer::assertNullableString($context['perspective'] ?? null),
            ];
        }

        return $contexts;
    }

    /**
     * Safe scalar fact snapshot.
     *
     * @return array<string, string>
     */
    public function getFacts(): array
    {
        $facts = [];
        foreach (Typer::assertArray($this->getAttribute('facts')) as $key => $value) {
            $facts[Typer::assertString($key)] = Typer::assertString($value);
        }

        return $facts;
    }

    /**
     * Model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'store_contexts' => 'array', 'facts' => 'array'];
    }
}
