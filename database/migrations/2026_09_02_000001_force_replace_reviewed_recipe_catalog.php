<?php

declare(strict_types=1);

use App\Domain\Recipes\RecipeCatalogMigrationService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Replace every deployed recipe and category with the reviewed canonical catalog.
     */
    public function up(): void
    {
        (new RecipeCatalogMigrationService())->replace(true);
    }
};
