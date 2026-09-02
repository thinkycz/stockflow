<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\UserFactory;
use Database\Seeders\Concerns\OnlyRunsInDemoEnvironment;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    use OnlyRunsInDemoEnvironment;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->ensureDemoEnvironment();

        $adminQuery = User::query();
        User::scopeAdmin($adminQuery);
        $adminCount = $adminQuery->count();

        if ($adminCount > 1) {
            throw new RuntimeException('StockFlow supports exactly one main administrator.');
        }

        if ($adminCount === 1) {
            return;
        }

        if (User::query()->getQuery()->exists()) {
            throw new RuntimeException('Cannot provision the main administrator while orphan root accounts exist.');
        }

        $admin = UserFactory::new()
            ->admin()
            ->password()
            ->createOne(['email' => 'test@test.com']);

        $admin->provisionWarehouse();
    }
}
