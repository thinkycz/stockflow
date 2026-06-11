<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;
use Thinkycz\LaravelCore\Support\Config;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (Config::inject()->appEnvIs(['staging', 'production'])) {
            return;
        }

        if (
            User::query()
                ->getQuery()
                ->exists()
        ) {
            return;
        }

        UserFactory::new()
            ->password()
            ->createOne(['email' => 'test@test.com']);
    }
}
