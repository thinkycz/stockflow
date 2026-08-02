<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Models\User;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class RecipeValidity
{
    /**
     * Core validity builder.
     */
    public BaseValidity $baseValidity;

    /**
     * Create recipe validation rules for one company owner.
     */
    public function __construct(private readonly int $userId)
    {
        $this->baseValidity = new BaseValidity();
    }

    /**
     * Resolve validity for the authenticated company scope.
     */
    public static function inject(int|null $userId = null): self
    {
        return new self($userId ?? User::mustAuth()->resolveScopeUser()->getKey());
    }

    /**
     * Company recipe category id.
     */
    public function categoryId(): Validity { return $this->baseValidity->id()->exists('recipe_categories', 'id', ['user_id', (string) $this->userId]); }

    /**
     * Company recipe id.
     */
    public function recipeId(): Validity { return $this->baseValidity->id()->exists('recipes', 'id', ['user_id', (string) $this->userId]); }

    /**
     * Company worker id.
     */
    public function workerId(): Validity { return $this->baseValidity->id()->exists('workers', 'id', ['user_id', (string) $this->userId]); }

    /**
     * Recipe name.
     */
    public function name(): Validity { return $this->baseValidity->make()->string(180); }

    /**
     * Recipe category name.
     */
    public function categoryName(): Validity { return $this->baseValidity->make()->string(120); }

    /**
     * Shared recipe note.
     */
    public function note(): Validity { return $this->baseValidity->make()->text(5000); }

    /**
     * Positive display position.
     */
    public function position(): Validity { return $this->baseValidity->make()->integer(null, 1); }

    /**
     * Nested recipe variants.
     */
    public function variants(): Validity { return $this->baseValidity->make()->array(null)->min(1)->max(30); }

    /**
     * Optional variant label.
     */
    public function variantName(): Validity { return $this->baseValidity->make()->string(80); }

    /**
     * Ordered recipe steps.
     */
    public function steps(): Validity { return $this->baseValidity->make()->array(null)->min(2)->max(100); }

    /**
     * Individual instruction text.
     */
    public function stepText(): Validity { return $this->baseValidity->make()->string(1000); }

    /**
     * Submitted opaque step tokens.
     */
    public function tokens(): Validity { return $this->baseValidity->make()->array(null)->max(100); }

    /**
     * Individual opaque step token.
     */
    public function token(): Validity { return $this->baseValidity->make()->string(64); }

    /**
     * Requested archive state.
     */
    public function archived(): Validity { return $this->baseValidity->make()->boolean(); }

    /**
     * Requested ordering direction.
     */
    public function direction(): Validity { return $this->baseValidity->make()->string(4)->in(['up', 'down']); }
}
