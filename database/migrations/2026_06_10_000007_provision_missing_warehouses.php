<?php

declare(strict_types=1);

use App\Enums\StoreStatusEnum;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        User::query()
            ->whereDoesntHave('stores', static function ($query): void {
                $query->where('is_warehouse', true);
            })
            ->each(static function (User $user): void {
                Store::query()->create([
                    'user_id' => $user->getKey(),
                    'name' => 'Warehouse',
                    'status' => StoreStatusEnum::ACTIVE->value,
                    'is_warehouse' => true,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data backfill only; no rollback.
    }
};
