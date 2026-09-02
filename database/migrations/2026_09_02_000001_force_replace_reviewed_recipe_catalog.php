<?php

declare(strict_types=1);

use Database\Seeders\RecipeCatalogSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Replace every deployed recipe and category with the reviewed canonical catalog.
     */
    public function up(): void
    {
        (new RecipeCatalogSeeder())->replaceAll();
    }
};
