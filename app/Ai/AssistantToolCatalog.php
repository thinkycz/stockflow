<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Operations\Administration\AdministrationOperationExecutor;
use App\Ai\Operations\Finance\FinanceOperationExecutor;
use App\Ai\Operations\Inventory\InventoryLifecycleOperationExecutor;
use App\Ai\Operations\Operations\OperationsOperationExecutor;
use App\Ai\Operations\Recipes\RecipeOperationExecutor;
use App\Ai\Operations\Statements\StatementOperationExecutor;
use App\Ai\Operations\Workforce\WorkforceOperationExecutor;
use App\Ai\Tools\AskUserChoiceTool;
use App\Ai\Tools\ReadAttendanceTool;
use App\Ai\Tools\ReadChecklistsTool;
use App\Ai\Tools\ReadFinancialReportsTool;
use App\Ai\Tools\ReadGiftVouchersTool;
use App\Ai\Tools\ReadInventoryCountsTool;
use App\Ai\Tools\ReadItemsTool;
use App\Ai\Tools\ReadNoticeboardTool;
use App\Ai\Tools\ReadPayrollTool;
use App\Ai\Tools\ReadRecipesTool;
use App\Ai\Tools\ReadRecipeTestsTool;
use App\Ai\Tools\ReadRecurringExpensesTool;
use App\Ai\Tools\ReadSettingsTool;
use App\Ai\Tools\ReadShiftRequestsTool;
use App\Ai\Tools\ReadShiftShareLinksTool;
use App\Ai\Tools\ReadShiftsTool;
use App\Ai\Tools\ReadStatementsTool;
use App\Ai\Tools\ReadStockMovementsTool;
use App\Ai\Tools\ReadStoresTool;
use App\Ai\Tools\ReadUsersTool;
use App\Ai\Tools\ReadWorkersTool;
use App\Ai\Tools\WriteAttendanceTool;
use App\Ai\Tools\WriteChecklistsTool;
use App\Ai\Tools\WriteFinancialReportsTool;
use App\Ai\Tools\WriteGiftVouchersTool;
use App\Ai\Tools\WriteInventoryCountsTool;
use App\Ai\Tools\WriteItemsTool;
use App\Ai\Tools\WriteNoticeboardTool;
use App\Ai\Tools\WritePayrollTool;
use App\Ai\Tools\WriteRecipesTool;
use App\Ai\Tools\WriteRecipeTestsTool;
use App\Ai\Tools\WriteRecurringExpensesTool;
use App\Ai\Tools\WriteSettingsTool;
use App\Ai\Tools\WriteShiftRequestsTool;
use App\Ai\Tools\WriteShiftShareLinksTool;
use App\Ai\Tools\WriteShiftsTool;
use App\Ai\Tools\WriteStatementsTool;
use App\Ai\Tools\WriteStockMovementsTool;
use App\Ai\Tools\WriteStoresTool;
use App\Ai\Tools\WriteUsersTool;
use App\Ai\Tools\WriteWorkersTool;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\ToolNameResolver;
use Thinkycz\LaravelCore\Support\Resolver;

final class AssistantToolCatalog
{
    /**
     * Construct the concrete native tools for one authorized conversation.
     *
     * @return list<Tool>
     */
    public function tools(User $actor, string $conversationId, int|null $activeStoreId = null): array
    {
        $administration = Resolver::resolve(AdministrationOperationExecutor::class);
        $finance = Resolver::resolve(FinanceOperationExecutor::class);
        $inventory = Resolver::resolve(InventoryLifecycleOperationExecutor::class);
        $operations = Resolver::resolve(OperationsOperationExecutor::class);
        $recipes = Resolver::resolve(RecipeOperationExecutor::class);
        $statements = Resolver::resolve(StatementOperationExecutor::class);
        $workforce = Resolver::resolve(WorkforceOperationExecutor::class);

        return [
            new AskUserChoiceTool(),
            new ReadStoresTool($actor, $conversationId),
            new ReadUsersTool($actor, $conversationId),
            new WriteStoresTool($actor, $conversationId, $administration),
            new WriteUsersTool($actor, $conversationId, $administration),
            new ReadShiftsTool($actor, $conversationId),
            new WriteShiftsTool($actor, $conversationId),
            new ReadWorkersTool($actor, $conversationId),
            new WriteWorkersTool($actor, $conversationId),
            new WriteSettingsTool($actor, $conversationId, $administration),
            new WriteAttendanceTool($actor, $conversationId, $workforce),
            new WriteShiftRequestsTool($actor, $conversationId, $workforce),
            new WriteShiftShareLinksTool($actor, $conversationId, $workforce),
            new WriteChecklistsTool($actor, $conversationId, $operations),
            new WriteNoticeboardTool($actor, $conversationId, $operations),
            new WriteItemsTool($actor, $conversationId, $administration),
            new WriteInventoryCountsTool($actor, $conversationId, $inventory),
            new WriteStockMovementsTool($actor, $conversationId),
            new WriteStatementsTool($actor, $conversationId, $statements),
            new WriteRecipesTool($actor, $conversationId, $recipes),
            new WriteRecipeTestsTool($actor, $conversationId, $recipes),
            new WritePayrollTool($actor, $conversationId, $finance),
            new WriteFinancialReportsTool($actor, $conversationId, $finance),
            new WriteRecurringExpensesTool($actor, $conversationId, $finance),
            new WriteGiftVouchersTool($actor, $conversationId, $finance),
            new ReadSettingsTool($actor, $conversationId, $activeStoreId),
            new ReadAttendanceTool($actor, $conversationId),
            new ReadShiftRequestsTool($actor, $conversationId),
            new ReadShiftShareLinksTool($actor, $conversationId),
            new ReadChecklistsTool($actor, $conversationId),
            new ReadNoticeboardTool($actor, $conversationId),
            new ReadItemsTool($actor, $conversationId),
            new ReadInventoryCountsTool($actor, $conversationId),
            new ReadStockMovementsTool($actor, $conversationId),
            new ReadStatementsTool($actor, $conversationId),
            new ReadRecipesTool($actor, $conversationId),
            new ReadRecipeTestsTool($actor, $conversationId),
            new ReadPayrollTool($actor, $conversationId),
            new ReadFinancialReportsTool($actor, $conversationId),
            new ReadRecurringExpensesTool($actor, $conversationId),
            new ReadGiftVouchersTool($actor, $conversationId),
        ];
    }

    /**
     * Resolve a persisted provider-facing tool name for approval continuation.
     */
    public function find(User $actor, string $conversationId, string $name, int|null $activeStoreId = null): Tool|null
    {
        foreach ($this->tools($actor, $conversationId, $activeStoreId) as $tool) {
            if ($name === ToolNameResolver::resolve($tool)) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * Expose route-parity metadata without executing an operation.
     *
     * @return array<string, list<string>>
     */
    public function capabilities(): array
    {
        $capabilities = [
            'write_workers' => ['create_worker', 'update_worker', 'delete_worker', 'restore_worker'],
            'write_shifts' => ['create_shift', 'quick_add_shift', 'update_shift', 'delete_shift', 'create_shift_preset', 'update_shift_preset', 'delete_shift_preset'],
            'write_stock_movements' => ['create_stock_movement', 'reverse_stock_movement'],
        ];

        foreach (AssistantResourceToolDefinitions::writers() as $name => $definition) {
            $capabilities[$name] = \array_keys($definition['actions']);
        }

        return $capabilities;
    }

    /**
     * Expose the non-executing read datasets used by route-parity checks.
     *
     * @return array<string, list<string>>
     */
    public function readCapabilities(): array
    {
        return [
            'read_stores' => ['stores'],
            'read_users' => ['users'],
            'read_workers' => ['workers'],
            'read_settings' => ['profile', 'integrations', 'digests'],
            'read_attendance' => ['sessions', 'monthly_report'],
            'read_shifts' => ['shifts'],
            'read_shift_requests' => ['requests', 'month_locks'],
            'read_shift_share_links' => ['share_links'],
            'read_checklists' => ['days', 'items'],
            'read_noticeboard' => ['cards'],
            'read_items' => ['catalog', 'store_stock'],
            'read_inventory_counts' => ['sessions'],
            'read_stock_movements' => ['movements'],
            'read_statements' => ['reports', 'days'],
            'read_recipes' => ['recipes', 'categories'],
            'read_recipe_tests' => ['sessions', 'attempts'],
            'read_payroll' => ['reports', 'payslips'],
            'read_financial_reports' => ['reports', 'rows'],
            'read_recurring_expenses' => ['expenses', 'versions'],
            'read_gift_vouchers' => ['vouchers', 'batches'],
        ];
    }
}
