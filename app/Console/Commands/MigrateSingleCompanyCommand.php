<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Typer;

class MigrateSingleCompanyCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'stockflow:migrate-single-company {--dry-run : Report changes without writing them}';

    /**
     * @var string
     */
    protected $description = 'Merge orphan root accounts and their business data into the single StockFlow company.';

    /**
     * Report or execute the single-company ownership migration.
     */
    public function handle(): int
    {
        $adminQuery = User::query();
        User::scopeAdmin($adminQuery);
        $admins = $adminQuery->get();

        if ($admins->count() !== 1) {
            throw new RuntimeException('Exactly one main administrator is required before the single-company migration.');
        }

        $admin = $admins->first();
        if (!$admin instanceof User) {
            throw new RuntimeException('Main administrator could not be resolved.');
        }

        $orphans = User::query()
            ->where('is_admin', false)
            ->whereNull('parent_user_id')
            ->orderBy('id')
            ->get();
        $assignedStore = $this->resolveAssignedStore($admin);
        $rows = [];

        foreach ($orphans as $orphan) {
            $rows[] = [
                $orphan->getKey(),
                $orphan->getEmail(),
                $this->ownedRows($orphan->getKey()),
                $assignedStore->getName(),
            ];
        }

        $this->table(['User ID', 'Email', 'Owned rows', 'Assigned store'], $rows);

        if ($this->option('dry-run') || $orphans->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($orphans as $orphan) {
            DB::transaction(function () use ($admin, $assignedStore, $orphan): void {
                foreach ($this->ownedTables() as $table) {
                    DB::table($table)
                        ->where('user_id', $orphan->getKey())
                        ->update(['user_id' => $admin->getKey()]);
                }

                $orphan->update([
                    'parent_user_id' => $admin->getKey(),
                    'assigned_store_id' => $assignedStore->getKey(),
                    'active_store_id' => $assignedStore->getKey(),
                    'is_admin' => false,
                ]);
            }, 3);
        }

        return self::SUCCESS;
    }

    /**
     * Choose the deterministic store assigned to converted accounts.
     */
    private function resolveAssignedStore(User $admin): Store
    {
        $query = Store::query();
        Store::scopeForUser($query, $admin);
        Store::scopeActive($query);
        $retail = $query->where('is_warehouse', false)->orderBy('id')->first();

        return $retail instanceof Store ? $retail : $admin->warehouse();
    }

    /**
     * Count directly owned business rows for dry-run reporting.
     */
    private function ownedRows(int $userId): int
    {
        $total = 0;
        foreach ($this->ownedTables() as $table) {
            $total += Typer::parseInt(DB::table($table)->where('user_id', $userId)->count());
        }

        return $total;
    }

    /**
     * @return list<string>
     */
    private function ownedTables(): array
    {
        return [
            'stores',
            'items',
            'stock_movements',
            'statements',
            'statement_versions',
            'inventory_sessions',
            'workers',
            'shifts',
            'shift_presets',
        ];
    }
}
