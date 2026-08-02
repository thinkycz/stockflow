<?php

declare(strict_types=1);

use Database\Seeders\RecipeCatalogSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Replace every production recipe even when the previous optional seed was skipped or partial.
     */
    public function up(): void
    {
        (new RecipeCatalogSeeder())->replaceAll();
    }
};
