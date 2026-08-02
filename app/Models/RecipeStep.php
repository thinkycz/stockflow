<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RecipeStepFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;

class RecipeStep extends BaseModel
{
    /** @use HasFactory<RecipeStepFactory> */
    use HasFactory;

    protected $table = 'recipe_steps';

    /**
     * @param Builder<RecipeStep> $query
     */
    public static function scopeSearch(Builder $query, string $search): void { $query->where('text', 'like', '%' . $search . '%'); }

    /**
     * @param Builder<RecipeStep> $query
     *
     * @return Builder<RecipeStep>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'recipe_variant_id', 'text', 'action_key', 'source_text', 'position', 'created_at', 'updated_at']);
    }

    /**
     * @return BelongsTo<RecipeVariant, $this>
     */
    public function variant(): BelongsTo { return $this->belongsTo(RecipeVariant::class, 'recipe_variant_id'); }

    /**
     * Get the parent variant id.
     */
    public function getVariantId(): int { return $this->assertInt('recipe_variant_id'); }

    /**
     * Get the instruction text.
     */
    public function getText(): string { return $this->assertString('text'); }

    /**
     * Get the action key used for the procedure icon.
     */
    public function getActionKey(): string { return $this->assertString('action_key'); }

    /**
     * Get the original source wording.
     */
    public function getSourceText(): string|null { return $this->assertNullableString('source_text'); }

    /**
     * Get the instruction position.
     */
    public function getPosition(): int { return $this->assertInt('position'); }

    /**
     * @return array<string, string>
     */
    protected function casts(): array { return []; }
}
