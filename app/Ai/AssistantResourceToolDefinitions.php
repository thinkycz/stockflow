<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * Static model-facing capability metadata for native resource tools.
 *
 * This class deliberately contains no authorization, validation, target
 * resolution, or execution behavior. Those remain in the concrete tools and
 * the human-facing application commands they invoke.
 */
final class AssistantResourceToolDefinitions
{
    /**
     * @return array<string, array{resource: string, description: string, searchable: bool, store_scoped: bool}>
     */
    public static function reads(): array
    {
        return [
            'read_stores' => self::read('stores', 'Read owned stores and their operational state.', true),
            'read_users' => self::read('users', 'Read managed limited users without credentials.', true),
            'read_settings' => self::read('settings', 'Read non-sensitive profile and integration settings.'),
            'read_attendance' => self::read('attendance', 'Read attendance sessions and state.', false, true),
            'read_shift_requests' => self::read('shift_requests', 'Read shift requests and month locks.', false, true),
            'read_shift_share_links' => self::read('shift_share_links', 'Read active and revoked shift share links.', false, true),
            'read_checklists' => self::read('checklists', 'Read checklist days and completion state.', false, true),
            'read_noticeboard' => self::read('noticeboard', 'Read noticeboard card metadata without binary content.', true, true),
            'read_items' => self::read('items', 'Read catalog items and aggregate stock.', true),
            'read_inventory_counts' => self::read('inventory_counts', 'Read inventory count drafts and completed sessions.', false, true),
            'read_stock_movements' => self::read('stock_movements', 'Read recent stock movements and reversals.', true, true),
            'read_statements' => self::read('statements', 'Read statement months and version metadata.', false, true),
            'read_recipes' => self::read('recipes', 'Read recipe categories and recipes.', true),
            'read_recipe_tests' => self::read('recipe_tests', 'Read recipe test sessions and attempts.'),
            'read_payroll' => self::read('payroll', 'Read payroll report lifecycle and worker totals.', false, true),
            'read_financial_reports' => self::read('income_expenses', 'Read financial report lifecycle and totals.', false, true),
            'read_recurring_expenses' => self::read('recurring_expenses', 'Read recurring expense versions.', true, true),
            'read_gift_vouchers' => self::read('gift_vouchers', 'Read voucher lifecycle metadata without codes.'),
        ];
    }

    /**
     * @return array<string, array{domain: string, description: string, actions: array<string, array<string, mixed>>, external_actions: list<string>}>
     */
    public static function writers(): array
    {
        return [
            'write_stores' => self::writer('stores', 'Create, update, delete, or activate an owned store.', [
                'create_store' => self::action(false, false, [], self::storeValues()),
                'update_store' => self::action(true, true, [], self::storeValues()),
                'delete_store' => self::action(true, true),
                'switch_active_store' => self::action(true, true),
            ]),
            'write_users' => self::writer('users', 'Create, update, or delete a managed limited user. Passwords are never accepted.', [
                'create_user' => self::action(true, false, [], ['email' => self::text(true, 'Email')]),
                'update_user' => self::action(true, true, [], ['email' => self::text(true, 'Email')]),
                'delete_user' => self::action(false, true),
            ]),
            'write_settings' => self::writer('settings', 'Update profile or Slack settings, send a Slack test, or retry a failed digest.', [
                'update_profile' => self::action(false, false, [], [
                    'email' => self::text(true, 'Email'),
                    'locale' => self::select(true, 'Locale', ['en', 'cs', 'sk']),
                ]),
                'update_slack_channel' => self::action(false, false, [], ['company_slack_channel' => self::text(false, 'Slack channel')]),
                'test_slack_channel' => self::action(),
                'retry_slack_digest' => self::action(false, true),
            ], ['test_slack_channel', 'retry_slack_digest']),
            'write_attendance' => self::writer('attendance', 'Record attendance and manage audited attendance corrections and deviations.', [
                'record_attendance_action' => self::action(true, true, [], [
                    'action' => self::text(true, 'Attendance action'),
                    'confirm_without_shift' => self::bool(false, 'Confirm without shift'),
                ]),
                'create_attendance_correction' => self::action(true, false, ['worker_id' => self::id()], self::attendanceCorrectionValues()),
                'update_attendance_correction' => self::action(true, true, ['worker_id' => self::id()], self::attendanceCorrectionValues()),
                'void_attendance_session' => self::action(true, true, [], ['reason' => self::textarea(true, 'Reason')]),
                'review_attendance_deviation' => self::action(true, true, self::deviationContext(), [
                    'decision' => self::select(true, 'Decision', ['approve', 'reject']),
                    'reason' => self::textarea(true, 'Reason'),
                    'start_time' => self::time(true, 'Start time'),
                    'end_time' => self::time(true, 'End time'),
                    'allow_overlap' => self::bool(false, 'Allow overlap'),
                ]),
            ]),
            'write_shift_requests' => self::writer('shift_requests', 'Manage request locks, worker requests, and approvals.', [
                'set_shift_request_lock' => self::action(true, false, [], [
                    'year' => self::integer(true, 'Year'),
                    'month' => self::integer(true, 'Month', 1, 12),
                    'locked' => self::bool(true, 'Locked'),
                ]),
                'toggle_shift_request' => self::action(true, true, [], [
                    'date' => self::date(true, 'Date'),
                    'start_time' => self::time(true, 'Start time'),
                    'end_time' => self::time(true, 'End time'),
                ]),
                'approve_shift_request' => self::action(true, true, [], [
                    'start_time' => self::time(true, 'Start time'),
                    'end_time' => self::time(true, 'End time'),
                    'allow_overlap' => self::bool(false, 'Allow overlap'),
                ]),
            ]),
            'write_shift_share_links' => self::writer('shift_share_links', 'Create or revoke public shift share links.', [
                'create_shift_share_link' => self::action(true, false, [], ['name' => self::text(true, 'Name')]),
                'revoke_shift_share_link' => self::action(true, true),
            ], ['create_shift_share_link', 'revoke_shift_share_link']),
            'write_checklists' => self::writer('checklists', 'Update checklist items, day excuses, and templates.', [
                'update_checklist_item' => self::action(true, true, [
                    'worker_id' => self::id(false),
                    'lock_version' => self::integer(true, 'Lock version', 1),
                ], ['completed' => self::bool(true, 'Completed')]),
                'excuse_checklist_day' => self::action(true, true, [], ['reason' => self::textarea(true, 'Reason')]),
                'restore_checklist_day' => self::action(true, true, [], ['reason' => self::textarea(true, 'Reason')]),
                'replace_checklist_template' => self::action(true, false, [], [
                    'scope' => self::text(true, 'Scope'),
                    'weekday' => self::integer(false, 'Weekday', 1, 7),
                    'shift' => self::text(true, 'Shift'),
                    'tasks' => self::collection(true, 'Tasks', [
                        'text' => self::textarea(true, 'Task'),
                    ]),
                ]),
            ]),
            'write_noticeboard' => self::writer('noticeboard', 'Create and manage text noticeboard cards. Binary uploads are excluded.', [
                'create_noticeboard_card' => self::action(true, false, [], self::noticeValues()),
                'update_noticeboard_card' => self::action(true, true, ['lock_version' => self::integer(true, 'Lock version', 1)], [
                    ...self::noticeValues(),
                    'remove_image' => self::bool(false, 'Remove existing image'),
                ]),
                'trash_noticeboard_card' => self::action(true, true),
                'restore_noticeboard_card' => self::action(true, true),
                'delete_noticeboard_card_permanently' => self::action(true, true),
            ]),
            'write_items' => self::writer('items', 'Create, update, or delete catalog items without directly changing stock.', [
                'create_item' => self::action(false, false, [], self::itemValues()),
                'update_item' => self::action(false, true, [], self::itemValues()),
                'delete_item' => self::action(false, true),
            ]),
            'write_inventory_counts' => self::writer('inventory_counts', 'Manage inventory count drafts and completed counts.', [
                'start_inventory_draft' => self::action(true),
                'save_inventory_draft_row' => self::action(false, true, [], [
                    'item_id' => self::id(),
                    'quantity' => self::number(true, 'Quantity', 0),
                    'classification' => self::text(false, 'Classification'),
                    'note' => self::textarea(false, 'Note'),
                    'client_version' => self::integer(true, 'Client version', 1),
                ]),
                'close_inventory_draft' => self::action(false, true, [], ['counted_on' => self::date(true, 'Counted on')]),
                'cancel_inventory_draft' => self::action(false, true),
                'create_inventory_count' => self::action(true, false, [], [
                    'rows' => self::collection(true, 'Count rows', [
                        'item_id' => self::id(),
                        'quantity' => self::number(false, 'Quantity', 0),
                        'classification' => self::text(false, 'Classification'),
                        'note' => self::textarea(false, 'Note'),
                    ]),
                ]),
            ]),
            'write_statements' => self::writer('statements', 'Update, clear, or restore monthly statement data.', [
                'update_statement' => self::action(true, true, [], [
                    'days' => self::collection(true, 'Days', self::statementDayValues()),
                    'close_attendances' => self::bool(false, 'Close attendances'),
                ]),
                'update_today_statement' => self::action(true, true, [], [
                    ...self::statementAmounts(),
                    'close_attendances' => self::bool(false, 'Close attendances'),
                ]),
                'clear_statement' => self::action(true, true),
                'restore_statement_version' => self::action(true, true),
            ]),
            'write_recipes' => self::writer('recipes', 'Manage recipe categories, recipes, ordering, and archive state.', [
                'create_recipe_category' => self::action(false, false, [], ['name' => self::text(true, 'Name')]),
                'update_recipe_category' => self::action(false, true, [], ['name' => self::text(true, 'Name')]),
                'delete_recipe_category' => self::action(false, true),
                'move_recipe_category' => self::action(false, true, [], ['direction' => self::select(true, 'Direction', ['up', 'down'])]),
                'create_recipe' => self::action(false, false, ['category_id' => self::id()], self::recipeValues()),
                'update_recipe' => self::action(false, true, ['category_id' => self::id()], self::recipeValues()),
                'archive_recipe' => self::action(false, true, [], ['archived' => self::bool(true, 'Archived')]),
                'move_recipe' => self::action(false, true, [], ['direction' => self::select(true, 'Direction', ['up', 'down'])]),
            ]),
            'write_recipe_tests' => self::writer('recipe_tests', 'Start and submit recipe test attempts or sessions.', [
                'start_recipe_test_session' => self::action(false, false, [
                    'actor_user_id' => self::id(),
                    'worker_id' => self::id(),
                ]),
                'submit_recipe_test' => self::action(false, true, ['actor_user_id' => self::id()], [
                    'tokens' => self::array(true, 'Tokens', self::text(true, 'Token')),
                ]),
                'submit_recipe_test_session' => self::action(false, true, ['actor_user_id' => self::id()], [
                    'answers' => self::collection(true, 'Answers', [
                        'attempt_id' => self::id(),
                        'tokens' => self::array(true, 'Tokens', self::text(true, 'Token')),
                        'amounts' => ['type' => 'object', 'required' => true, 'label' => 'Amounts', 'fields' => []],
                    ]),
                ]),
            ]),
            'write_payroll' => self::writer('payroll', 'Manage payroll report lifecycle, workers, wage overrides, and adjustments.', [
                'close_payroll_report' => self::payrollAction(),
                'reopen_payroll_report' => self::payrollAction(),
                'add_payroll_worker' => self::payrollAction(false, ['worker_id' => self::id()]),
                'remove_payroll_worker' => self::payrollAction(false, ['worker_id' => self::id()]),
                'set_payroll_wage_override' => self::payrollAction(false, ['worker_id' => self::id()], [
                    'hours' => self::number(true, 'Hours', 0),
                    'hourly_rate' => self::number(true, 'Hourly rate', 0, null, 'money'),
                ]),
                'reset_payroll_wage_override' => self::payrollAction(false, ['worker_id' => self::id()]),
                'create_payroll_adjustment' => self::payrollAction(false, ['worker_id' => self::id()], self::payrollAdjustmentValues()),
                'update_payroll_adjustment' => self::payrollAction(true, ['worker_id' => self::id()], self::payrollAdjustmentValues()),
                'delete_payroll_adjustment' => self::payrollAction(true),
            ]),
            'write_financial_reports' => self::writer('financial_reports', 'Manage financial reports, manual rows, and automatic-row overrides.', [
                'copy_previous_financial_rows' => self::financialAction(),
                'close_financial_report' => self::financialAction(),
                'reopen_financial_report' => self::financialAction(),
                'create_financial_row' => self::financialAction(false, self::financialRowValues()),
                'update_financial_row' => self::financialAction(true, self::financialRowValues()),
                'delete_financial_row' => self::financialAction(true),
                'set_financial_override' => self::action(true, false, self::financialOverrideContext(), ['amount' => self::number(true, 'Amount', null, null, 'money')]),
                'reset_financial_override' => self::action(true, false, self::financialOverrideContext()),
            ]),
            'write_recurring_expenses' => self::writer('recurring_expenses', 'Create, version, or terminate recurring expenses.', [
                'create_recurring_expense' => self::action(true, false, [], self::recurringValues()),
                'update_recurring_expense' => self::action(true, true, [], self::recurringValues()),
                'terminate_recurring_expense' => self::action(true, true, [], ['ends_before_period' => self::text(true, 'Ends before period')]),
            ]),
            'write_gift_vouchers' => self::writer('gift_vouchers', 'Issue, configure, redeem, void, or reverse gift vouchers without exposing codes.', [
                'issue_gift_vouchers' => self::action(false, false, [], [
                    'quantity' => self::integer(true, 'Quantity', 1),
                    'amount' => self::number(true, 'Amount', 0, null, 'money'),
                    'expires_on' => self::date(false, 'Expires on'),
                ]),
                'update_voucher_branding' => self::action(false, false, [], [
                    'public_name' => self::text(true, 'Public name'),
                    'message' => self::textarea(false, 'Message'),
                    'remove_logo' => self::bool(false, 'Remove existing logo'),
                ]),
                'redeem_gift_voucher' => self::action(true, true),
                'void_gift_voucher' => self::action(false, true, [], ['reason' => self::textarea(true, 'Reason')]),
                'reverse_gift_voucher_redemption' => self::action(false, true, [], ['reason' => self::textarea(true, 'Reason')]),
            ]),
        ];
    }

    /**
     * @return array{resource: string, description: string, searchable: bool, store_scoped: bool}
     */
    private static function read(string $resource, string $description, bool $searchable = false, bool $storeScoped = false): array
    {
        return [
            'resource' => $resource,
            'description' => $description,
            'searchable' => $searchable,
            'store_scoped' => $storeScoped,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $actions
     * @param list<string> $externalActions
     *
     * @return array{domain: string, description: string, actions: array<string, array<string, mixed>>, external_actions: list<string>}
     */
    private static function writer(string $domain, string $description, array $actions, array $externalActions = []): array
    {
        return ['domain' => $domain, 'description' => $description, 'actions' => $actions, 'external_actions' => $externalActions];
    }

    /**
     * @param array<string, array<string, mixed>> $context
     * @param array<string, array<string, mixed>> $values
     *
     * @return array<string, mixed>
     */
    private static function action(bool $store = false, bool $target = false, array $context = [], array $values = []): array
    {
        return ['store' => $store, 'target' => $target, 'context' => $context, 'values' => $values];
    }

    /**
     * @return array<string, mixed>
     */
    private static function text(bool $required, string $label): array { return ['type' => 'string', 'required' => $required, 'label' => $label, 'control' => 'text']; }

    /**
     * @return array<string, mixed>
     */
    private static function textarea(bool $required, string $label): array { return [...self::text($required, $label), 'control' => 'textarea']; }

    /**
     * @return array<string, mixed>
     */
    private static function date(bool $required, string $label): array { return [...self::text($required, $label), 'control' => 'date']; }

    /**
     * @return array<string, mixed>
     */
    private static function time(bool $required, string $label): array { return [...self::text($required, $label), 'control' => 'time']; }

    /**
     * @return array<string, mixed>
     */
    private static function bool(bool $required, string $label): array { return ['type' => 'boolean', 'required' => $required, 'label' => $label, 'control' => 'checkbox']; }

    /**
     * @return array<string, mixed>
     */
    private static function integer(bool $required, string $label, int|null $min = null, int|null $max = null): array { return \array_filter(['type' => 'integer', 'required' => $required, 'label' => $label, 'control' => 'number', 'min' => $min, 'max' => $max], static fn(mixed $value): bool => $value !== null); }

    /**
     * @return array<string, mixed>
     */
    private static function number(bool $required, string $label, float|int|null $min = null, float|int|null $max = null, string $control = 'number'): array { return \array_filter(['type' => 'number', 'required' => $required, 'label' => $label, 'control' => $control, 'min' => $min, 'max' => $max, 'step' => 0.01], static fn(mixed $value): bool => $value !== null); }

    /**
     * @return array<string, mixed>
     */
    private static function id(bool $required = true): array { return ['type' => 'integer', 'required' => $required, 'editable' => false]; }

    /**
     * @param list<string> $options
     *
     * @return array<string, mixed>
     */
    private static function select(bool $required, string $label, array $options): array { return ['type' => 'string', 'required' => $required, 'label' => $label, 'control' => 'select', 'enum' => $options, 'options' => $options]; }

    /**
     * @param array<string, mixed> $items
     *
     * @return array<string, mixed>
     */
    private static function array(bool $required, string $label, array $items): array { return ['type' => 'array', 'required' => $required, 'label' => $label, 'control' => 'collection', 'items' => $items]; }

    /**
     * @param array<string, array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private static function collection(bool $required, string $label, array $fields): array { return self::array($required, $label, ['type' => 'object', 'fields' => $fields]); }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function storeValues(): array { return ['name' => self::text(true, 'Name'), 'address' => self::textarea(false, 'Address'), 'status' => self::text(true, 'Status'), 'notes' => self::textarea(false, 'Notes'), 'slack_channel' => self::text(false, 'Slack channel'), 'is_warehouse' => self::bool(false, 'Warehouse')]; }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function itemValues(): array { return ['title' => self::text(true, 'Title'), 'sku' => self::text(false, 'SKU'), 'unit' => self::text(false, 'Unit'), 'purchase_price' => self::number(true, 'Purchase price', 0, null, 'money'), 'description' => self::textarea(false, 'Description')]; }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function attendanceCorrectionValues(): array { return ['started_at' => self::text(true, 'Started at'), 'ended_at' => self::text(true, 'Ended at'), 'breaks' => self::collection(false, 'Breaks', ['started_at' => self::text(true, 'Started at'), 'ended_at' => self::text(true, 'Ended at')]), 'reason' => self::textarea(true, 'Reason')]; }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function deviationContext(): array { return ['expected_started_at' => self::text(true, 'Expected started at'), 'expected_ended_at' => self::text(true, 'Expected ended at'), 'expected_start_time' => self::time(true, 'Expected start time'), 'expected_end_time' => self::time(true, 'Expected end time')]; }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function noticeValues(): array { return ['body_html' => self::textarea(true, 'Content'), 'label' => self::text(true, 'Label'), 'color' => self::text(true, 'Color'), 'size' => self::text(false, 'Size'), 'expires_on' => self::date(false, 'Expires on')]; }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function statementAmounts(): array { return ['cash' => self::number(true, 'Cash', 0, null, 'money'), 'card' => self::number(true, 'Card', 0, null, 'money'), 'wolt' => self::number(true, 'Wolt', 0, null, 'money'), 'bolt' => self::number(true, 'Bolt', 0, null, 'money'), 'bolt_cash' => self::number(true, 'Bolt cash', 0, null, 'money'), 'foodora' => self::number(true, 'Foodora', 0, null, 'money')]; }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function statementDayValues(): array { return ['date' => self::date(true, 'Date'), ...self::statementAmounts(), 'cash_checked' => self::bool(false, 'Cash checked')]; }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function recipeValues(): array { return ['name' => self::text(true, 'Name'), 'note' => self::textarea(false, 'Note'), 'variants' => self::collection(true, 'Variants', ['name' => self::text(false, 'Variant name'), 'instructions' => self::collection(true, 'Instructions', ['type' => self::text(true, 'Type'), 'text' => self::textarea(true, 'Text'), 'action_key' => self::text(true, 'Action key'), 'quantity_value' => self::number(false, 'Quantity'), 'quantity_text' => self::text(false, 'Quantity text'), 'unit' => self::text(false, 'Unit'), 'ingredient_name' => self::text(false, 'Ingredient'), 'target' => self::text(false, 'Target'), 'icon_group' => self::text(true, 'Icon group')])])]; }

    /**
     * @param array<string, array<string, mixed>> $extraContext
     * @param array<string, array<string, mixed>> $values
     *
     * @return array<string, mixed>
     */
    private static function payrollAction(bool $target = false, array $extraContext = [], array $values = []): array { return self::action(true, $target, ['year' => self::integer(true, 'Year'), 'month' => self::integer(true, 'Month', 1, 12), ...$extraContext], $values); }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function payrollAdjustmentValues(): array { return ['type' => self::text(true, 'Type'), 'amount' => self::number(true, 'Amount', null, null, 'money'), 'reason' => self::textarea(true, 'Reason')]; }

    /**
     * @param array<string, array<string, mixed>> $values
     *
     * @return array<string, mixed>
     */
    private static function financialAction(bool $target = false, array $values = []): array { return self::action(true, $target, ['year' => self::integer(true, 'Year'), 'month' => self::integer(true, 'Month', 1, 12)], $values); }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function financialRowValues(): array { return ['direction' => self::text(true, 'Direction'), 'label' => self::text(true, 'Label'), 'occurred_on' => self::date(true, 'Occurred on'), 'amount' => self::number(true, 'Amount', null, null, 'money'), 'note' => self::textarea(false, 'Note')]; }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function financialOverrideContext(): array { return ['year' => self::integer(true, 'Year'), 'month' => self::integer(true, 'Month', 1, 12), 'source_type' => self::text(true, 'Source type'), 'source_key' => self::text(true, 'Source key')]; }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function recurringValues(): array { return ['effective_period' => self::text(true, 'Effective period'), 'label' => self::text(true, 'Label'), 'amount' => self::number(true, 'Amount', 0, null, 'money'), 'due_day' => self::integer(true, 'Due day', 1, 31), 'note' => self::textarea(false, 'Note')]; }
}
