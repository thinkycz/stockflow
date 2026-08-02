<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeInstruction extends BaseModel
{
    /**
     * @var string|null
     */
    protected $table = 'recipe_instructions';

    /**
     * @param Builder<RecipeInstruction> $query
     */
    public static function scopeSearch(Builder $query, string $search): void
    {
        $query->where('text', 'like', '%' . $search . '%');
    }

    /**
     * @param Builder<RecipeInstruction> $query
     *
     * @return Builder<RecipeInstruction>
     */
    public static function querySelect(Builder $query): Builder
    {
        return $query->select([
            'id', 'recipe_variant_id', 'position', 'type', 'text', 'action_key',
            'quantity_value', 'quantity_text', 'unit', 'ingredient_name', 'target',
            'icon_group', 'source_text', 'is_inferred', 'created_at', 'updated_at',
        ]);
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
     * Get the display position.
     */
    public function getPosition(): int { return $this->assertInt('position'); }

    /**
     * Get the canonical instruction type.
     */
    public function getType(): string { return $this->assertString('type'); }

    /**
     * Get the rendered instruction text.
     */
    public function getText(): string { return $this->assertString('text'); }

    /**
     * Get the action key used for the instruction icon.
     */
    public function getActionKey(): string { return $this->assertString('action_key'); }

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
    public function getQuantityText(): string|null { return $this->assertNullableString('quantity_text'); }

    /**
     * Get the source unit abbreviation.
     */
    public function getUnit(): string|null { return $this->assertNullableString('unit'); }

    /**
     * Get the ingredient name for an ingredient instruction.
     */
    public function getIngredientName(): string|null { return $this->assertNullableString('ingredient_name'); }

    /**
     * Get the target vessel or container.
     */
    public function getTarget(): string|null { return $this->assertNullableString('target'); }

    /**
     * Get the curated ingredient icon group.
     */
    public function getIconGroup(): string { return $this->assertString('icon_group'); }

    /**
     * Get the internal original source wording.
     */
    public function getSourceText(): string|null { return $this->assertNullableString('source_text'); }

    /**
     * Determine whether the instruction was inferred automatically.
     */
    public function isInferred(): bool { return $this->assertBool('is_inferred'); }

    /**
     * @return array<string, string>
     */
    protected function casts(): array { return ['quantity_value' => 'decimal:3', 'is_inferred' => 'boolean']; }
}
