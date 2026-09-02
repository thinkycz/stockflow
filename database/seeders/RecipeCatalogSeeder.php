<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\RecipeCatalogMigrationService;
use Database\Seeders\Concerns\OnlyRunsInDemoEnvironment;
use Illuminate\Database\Seeder;

class RecipeCatalogSeeder extends Seeder
{
    use OnlyRunsInDemoEnvironment;

    /**
     * Replace the legacy catalog exactly once with canonical recipe instructions.
     */
    public function run(): void
    {
        $this->ensureDemoEnvironment();

        (new RecipeCatalogMigrationService())->replace(false);
    }
}
