<?php

declare(strict_types=1);

namespace App\Domain\Recipes;

/**
 * Compatibility for historical data migrations before the catalog was retired.
 */
final class RecipeCatalogMigrationService
{
    /**
     * Keep historical forced seed calls runnable before the retirement migration.
     */
    public function replace(bool $force): void
    {
        // Historical migrations remain runnable; the new file catalog needs no seeding.
    }
}
