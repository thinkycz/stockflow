<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\OnlyRunsInDemoEnvironment;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use OnlyRunsInDemoEnvironment;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->ensureDemoEnvironment();

        $this->callOnce(UserSeeder::class);
        $this->callOnce(StoreSeeder::class);
        $this->callOnce(ItemSeeder::class);
        $this->callOnce(RecipeCatalogSeeder::class);
    }
}
