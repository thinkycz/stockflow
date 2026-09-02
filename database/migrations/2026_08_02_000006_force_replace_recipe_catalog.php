<?php

declare(strict_types=1);

use App\Services\RecipeCatalogMigrationService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Replace every production recipe even when the previous optional seed was skipped or partial.
     */
    public function up(): void
    {
        (new RecipeCatalogMigrationService())->replace(true);
    }
};
