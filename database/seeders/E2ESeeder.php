<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class E2ESeeder extends Seeder
{
    /**
     * Seed deterministic English credentials for browser tests.
     */
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        User::query()->where('email', 'test@test.com')->update(['locale' => 'en']);
    }
}
