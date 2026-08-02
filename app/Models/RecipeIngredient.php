<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RecipeIngredientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeIngredient extends BaseModel
{
    /** @use HasFactory<RecipeIngredientFactory> */
    use HasFactory;

    protected $table = 'recipe_ingredients';

    /**
     * @param Builder<RecipeIngredient> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('name', 'like', '%' . $search . '%');
    }

    /**
     * @param Builder<RecipeIngredient> $query
     *
     * @return Builder<RecipeIngredient>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select(['id', 'recipe_variant_id', 'position', 'quantity_value', 'quantity_text', 'unit', 'name', 'icon_group', 'source_text', 'created_at', 'updated_at']);
    }

    /**
     * @return BelongsTo<RecipeVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(RecipeVariant::class, 'recipe_variant_id');
    }

    /**
     * Get the parent variant id.
     */
    public function getVariantId(): int
    {
        return $this->assertInt('recipe_variant_id');
    }

    /**
     * Get the display position.
     */
    public function getPosition(): int
    {
        return $this->assertInt('position');
    }

    /**
     * Get the normalized numeric quantity, when one exists.
     */
    public function getQuantityValue(): float|int|null
    {
        $value = $this->getAttribute('quantity_value');
        if ($value === null) {
            return null;
        }

        $number = (float) Typer::assertScalar($value);

        return $number === \floor($number) ? (int) $number : $number;
    }

    /**
     * Get the exact fallback quantity expression.
     */
    public function getQuantityText(): string|null
    {
        return $this->assertNullableString('quantity_text');
    }

    /**
     * Get the source unit abbreviation.
     */
    public function getUnit(): string|null
    {
        return $this->assertNullableString('unit');
    }

    /**
     * Get the ingredient name.
     */
    public function getName(): string
    {
        return $this->assertString('name');
    }

    /**
     * Get the curated icon group.
     */
    public function getIconGroup(): string
    {
        return $this->assertString('icon_group');
    }

    /**
     * Get the original source wording.
     */
    public function getSourceText(): string
    {
        return $this->assertString('source_text');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity_value' => 'decimal:3'];
    }
}
