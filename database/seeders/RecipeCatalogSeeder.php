<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeTestAttempt;
use App\Models\User;
use App\Services\RecipeCatalogService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeCatalogSeeder extends Seeder
{
    /**
     * Replace the legacy catalog exactly once with canonical recipe instructions.
     */
    public function run(): void
    {
        $this->replace(false);
    }

    /**
     * Force replacement for the corrective production data migration.
     */
    public function replaceAll(): void
    {
        $this->replace(true);
    }

    /**
     * Replace the single company catalog, optionally ignoring an earlier deploy marker.
     */
    private function replace(bool $force): void
    {
        $adminQuery = User::query();
        User::scopeAdmin($adminQuery);
        if ($adminQuery->count() > 1) {
            throw new RuntimeException('StockFlow supports exactly one main administrator.');
        }
        $admin = $adminQuery->first();
        if (!$admin instanceof User) {
            return;
        }

        DB::transaction(static function () use ($admin, $force): void {
            $owner = Typer::assertInstance(User::query()->whereKey($admin->getKey())->lockForUpdate()->firstOrFail(), User::class);
            if (!$force && $owner->getRecipeCatalogV2SeededAt() !== null) {
                return;
            }

            RecipeTestAttempt::query()->where('user_id', $owner->getKey())->update([
                'recipe_id' => null,
                'recipe_variant_id' => null,
            ]);
            Recipe::query()->where('user_id', $owner->getKey())->delete();
            RecipeCategory::query()->where('user_id', $owner->getKey())->delete();

            $owner->setAttribute('recipes_initialized_at', null);
            $owner->setAttribute('recipe_instructions_initialized_at', null);
            $owner->save();

            (new RecipeCatalogService())->initialize($owner);

            $owner->setAttribute('recipe_catalog_v2_seeded_at', Carbon::now());
            $owner->save();
        });
    }
}
