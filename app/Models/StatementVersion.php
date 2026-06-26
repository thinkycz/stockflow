<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\StatementVersionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class StatementVersion extends BaseModel
{
    use BelongsToUser;
    /** @use HasFactory<StatementVersionFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'statement_versions';

    /**
     * Scope a query to a specific statement.
     *
     * @param Builder<StatementVersion> $query
     */
    public static function scopeForStatement(Builder $query, int|Statement $statement): void
    {
        $statementId = $statement instanceof Statement ? $statement->getKey() : $statement;

        $query->where($query->getModel()->getTable() . '.statement_id', $statementId);
    }

    /**
     * Scope a search to nothing. Version browsing is scoped by statement;
     * a free-text search field is not exposed in the UI yet.
     *
     * @param Builder<StatementVersion> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        // No-op: versions are filtered through `scopeForStatement`.
    }

    /**
     * Restrict the query to a curated set of columns for list views.
     *
     * @param Builder<StatementVersion> $query
     *
     * @return Builder<StatementVersion>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'statement_id', 'created_by', 'snapshot_at', 'note', 'created_at', 'updated_at']);
    }

    /**
     * Statement relationship.
     *
     * @return BelongsTo<Statement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(Statement::class, 'statement_id');
    }

    /**
     * Created-by user relationship.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Day rows relationship.
     *
     * @return HasMany<StatementVersionDay, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(StatementVersionDay::class, 'version_id');
    }

    /**
     * Loaded or queried statement.
     */
    public function getStatement(): Statement
    {
        if ($this->relationLoaded('statement')) {
            return $this->assertRelationship('statement', Statement::class);
        }

        return Typer::assertInstance($this->statement()->first(), Statement::class);
    }

    /**
     * Snapshot-at getter.
     */
    public function getSnapshotAt(): Carbon
    {
        return Typer::assertCarbon($this->getAttribute('snapshot_at'));
    }

    /**
     * Note getter.
     */
    public function getNote(): string|null
    {
        return Typer::assertNullableString($this->getAttribute('note'));
    }

    /**
     * Created-by id getter.
     */
    public function getCreatedBy(): int|null
    {
        return Typer::assertNullableInt($this->getAttribute('created_by'));
    }

    /**
     * Statement id getter.
     */
    public function getStatementId(): int
    {
        return $this->assertInt('statement_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_at' => 'datetime',
        ];
    }
}
