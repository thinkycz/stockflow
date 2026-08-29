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
use App\Ai\Tools\ConfiguredReadResourceTool;
use App\Ai\Tools\ReadShiftsTool;
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
    public function tools(User $actor, string $conversationId): array
    {
        $administration = Resolver::resolve(AdministrationOperationExecutor::class);
        $finance = Resolver::resolve(FinanceOperationExecutor::class);
        $inventory = Resolver::resolve(InventoryLifecycleOperationExecutor::class);
        $operations = Resolver::resolve(OperationsOperationExecutor::class);
        $recipes = Resolver::resolve(RecipeOperationExecutor::class);
        $statements = Resolver::resolve(StatementOperationExecutor::class);
        $workforce = Resolver::resolve(WorkforceOperationExecutor::class);
        $tools = [
            new AskUserChoiceTool(),
            new ConfiguredReadResourceTool($actor, $conversationId, 'read_stores', 'stores', 'Read owned stores and their operational state.', true),
            new ConfiguredReadResourceTool($actor, $conversationId, 'read_users', 'users', 'Read managed limited users without credentials.', true),
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
        ];

        foreach (AssistantResourceToolDefinitions::reads() as $name => $definition) {
            if (\in_array($name, ['read_workers', 'read_shifts', 'read_stores', 'read_users'], true)) {
                continue;
            }

            $tools[] = new ConfiguredReadResourceTool(
                $actor,
                $conversationId,
                $name,
                $definition['resource'],
                $definition['description'],
                $definition['searchable'],
                $definition['store_scoped'],
            );
        }

        return $tools;
    }

    /**
     * Resolve a persisted provider-facing tool name for approval continuation.
     */
    public function find(User $actor, string $conversationId, string $name): Tool|null
    {
        foreach ($this->tools($actor, $conversationId) as $tool) {
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
            'write_workers' => ['create_worker', 'update_worker', 'delete_worker'],
            'write_shifts' => ['create_shift', 'quick_add_shift', 'update_shift', 'delete_shift', 'create_shift_preset', 'update_shift_preset', 'delete_shift_preset'],
            'write_stock_movements' => ['create_stock_movement', 'reverse_stock_movement'],
        ];

        foreach (AssistantResourceToolDefinitions::writers() as $name => $definition) {
            $capabilities[$name] = \array_keys($definition['actions']);
        }

        return $capabilities;
    }
}
