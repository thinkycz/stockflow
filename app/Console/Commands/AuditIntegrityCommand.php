<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InventorySession;
use App\Models\RecipeTestSession;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class AuditIntegrityCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'stockflow:integrity:diagnose';

    /**
     * @var string
     */
    protected $description = 'Report historical lifecycle inconsistencies without modifying data.';

    /**
     * Emit one JSON evidence record for each affected parent.
     */
    public function handle(): int
    {
        $found = false;
        InventorySession::query()->where('status', 'cancelled')
            ->whereIn('id', static function (QueryBuilder $query): void {
                $query->select('inventory_session_id')->from('stock_movements')->whereNotNull('inventory_session_id');
            })->chunkById(100, function ($sessions) use (&$found): void {
                foreach ($sessions as $session) {
                    $found = true;
                    $this->line(\json_encode(['issue' => 'cancelled_inventory_posted', 'session_id' => $session->getKey()], \JSON_THROW_ON_ERROR));
                }
            });
        RecipeTestSession::query()->whereNull('submitted_at')
            ->whereHas('attempts', static fn($query) => $query->whereNotNull('submitted_at'))
            ->chunkById(100, function ($sessions) use (&$found): void {
                foreach ($sessions as $session) {
                    $found = true;
                    $this->line(\json_encode(['issue' => 'partially_submitted_recipe_session', 'session_id' => $session->getKey()], \JSON_THROW_ON_ERROR));
                }
            });

        return $found ? self::FAILURE : self::SUCCESS;
    }
}
