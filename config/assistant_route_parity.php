<?php

declare(strict_types=1);

$toolForAction = static function (string $action): string {
    foreach ([
        'write_stores' => ['create_store', 'update_store', 'delete_store', 'switch_active_store'],
        'write_users' => ['create_user', 'update_user', 'delete_user'],
        'write_workers' => ['create_worker', 'update_worker', 'delete_worker'],
        'write_settings' => ['update_profile', 'update_slack_channel', 'test_slack_channel', 'retry_slack_digest'],
        'write_attendance' => ['record_attendance_action', 'create_attendance_correction', 'update_attendance_correction', 'void_attendance_session', 'review_attendance_deviation'],
        'write_shifts' => ['create_shift', 'quick_add_shift', 'update_shift', 'delete_shift', 'create_shift_preset', 'update_shift_preset', 'delete_shift_preset'],
        'write_shift_requests' => ['set_shift_request_lock', 'toggle_shift_request', 'approve_shift_request'],
        'write_shift_share_links' => ['create_shift_share_link', 'revoke_shift_share_link'],
        'write_checklists' => ['update_checklist_item', 'excuse_checklist_day', 'restore_checklist_day', 'replace_checklist_template'],
        'write_noticeboard' => ['create_noticeboard_card', 'update_noticeboard_card', 'trash_noticeboard_card', 'restore_noticeboard_card', 'delete_noticeboard_card_permanently'],
        'write_items' => ['create_item', 'update_item', 'delete_item'],
        'write_inventory_counts' => ['start_inventory_draft', 'save_inventory_draft_row', 'close_inventory_draft', 'cancel_inventory_draft', 'create_inventory_count'],
        'write_stock_movements' => ['create_stock_movement', 'reverse_stock_movement'],
        'write_statements' => ['update_statement', 'update_today_statement', 'clear_statement', 'restore_statement_version'],
        'write_recipes' => ['create_recipe_category', 'update_recipe_category', 'delete_recipe_category', 'move_recipe_category', 'create_recipe', 'update_recipe', 'archive_recipe', 'move_recipe'],
        'write_recipe_tests' => ['start_recipe_test_session', 'submit_recipe_test', 'submit_recipe_test_session'],
        'write_payroll' => ['close_payroll_report', 'reopen_payroll_report', 'add_payroll_worker', 'remove_payroll_worker', 'set_payroll_wage_override', 'reset_payroll_wage_override', 'create_payroll_adjustment', 'update_payroll_adjustment', 'delete_payroll_adjustment'],
        'write_financial_reports' => ['copy_previous_financial_rows', 'close_financial_report', 'reopen_financial_report', 'create_financial_row', 'update_financial_row', 'delete_financial_row', 'set_financial_override', 'reset_financial_override'],
        'write_recurring_expenses' => ['create_recurring_expense', 'update_recurring_expense', 'terminate_recurring_expense'],
        'write_gift_vouchers' => ['issue_gift_vouchers', 'update_voucher_branding', 'redeem_gift_voucher', 'void_gift_voucher', 'reverse_gift_voucher_redemption'],
    ] as $tool => $actions) {
        if (\in_array($action, $actions, true)) {
            return $tool;
        }
    }

    throw new InvalidArgumentException('Unmapped assistant route action: ' . $action);
};

$matrix = [
    /*
     * Every authenticated application mutation that the assistant can execute.
     * Constraints document route facets intentionally kept outside model input.
     */
    'supported' => [
        'attendance.actions.store' => ['operation' => 'record_attendance_action'],
        'attendance.corrections.store' => ['operation' => 'create_attendance_correction'],
        'attendance.sessions.update' => ['operation' => 'update_attendance_correction'],
        'attendance.sessions.void' => ['operation' => 'void_attendance_session'],
        'attendance.deviation-reviews.store' => ['operation' => 'review_attendance_deviation'],
        'checklist-days.excuse' => ['operation' => 'excuse_checklist_day'],
        'checklist-days.excuse.destroy' => ['operation' => 'restore_checklist_day'],
        'checklist-items.update' => ['operation' => 'update_checklist_item'],
        'checklists.templates.update' => ['operation' => 'replace_checklist_template'],
        'gift-voucher-batches.store' => ['operation' => 'issue_gift_vouchers'],
        'gift-voucher-settings.update' => [
            'operation' => 'update_voucher_branding',
            'constraints' => 'Text changes and removal of an existing logo are supported; binary logo add/replace is excluded.',
        ],
        'gift-vouchers.redeem' => ['operation' => 'redeem_gift_voucher'],
        'gift-vouchers.reverse-redemption' => ['operation' => 'reverse_gift_voucher_redemption'],
        'gift-vouchers.void' => ['operation' => 'void_gift_voucher'],
        'income-expenses.close' => ['operation' => 'close_financial_report'],
        'income-expenses.copy-previous' => ['operation' => 'copy_previous_financial_rows'],
        'income-expenses.manual-rows.store' => ['operation' => 'create_financial_row'],
        'income-expenses.manual-rows.update' => ['operation' => 'update_financial_row'],
        'income-expenses.manual-rows.destroy' => ['operation' => 'delete_financial_row'],
        'income-expenses.overrides.store' => ['operation' => 'set_financial_override'],
        'income-expenses.overrides.destroy' => ['operation' => 'reset_financial_override'],
        'income-expenses.recurring-expenses.store' => ['operation' => 'create_recurring_expense'],
        'income-expenses.recurring-expenses.update' => ['operation' => 'update_recurring_expense'],
        'income-expenses.recurring-expenses.terminate' => ['operation' => 'terminate_recurring_expense'],
        'income-expenses.reopen' => ['operation' => 'reopen_financial_report'],
        'inventory-counts.drafts.cancel' => ['operation' => 'cancel_inventory_draft'],
        'inventory-counts.drafts.close' => ['operation' => 'close_inventory_draft'],
        'inventory-counts.drafts.rows.update' => ['operation' => 'save_inventory_draft_row'],
        'inventory-counts.drafts.start' => ['operation' => 'start_inventory_draft'],
        'inventory-counts.update' => ['operation' => 'create_inventory_count'],
        'items.store' => ['operation' => 'create_item'],
        'items.update' => ['operation' => 'update_item'],
        'items.destroy' => ['operation' => 'delete_item'],
        'noticeboard-cards.store' => [
            'operation' => 'create_noticeboard_card',
            'constraints' => 'Text-only creation is supported; binary image creation is excluded.',
        ],
        'noticeboard-cards.update' => [
            'operation' => 'update_noticeboard_card',
            'constraints' => 'Text changes and removal of an existing image are supported; binary image add/replace is excluded.',
        ],
        'noticeboard-cards.destroy' => ['operation' => 'trash_noticeboard_card'],
        'noticeboard-cards.restore' => ['operation' => 'restore_noticeboard_card'],
        'noticeboard-cards.force-destroy' => ['operation' => 'delete_noticeboard_card_permanently'],
        'payroll.close' => ['operation' => 'close_payroll_report'],
        'payroll.reopen' => ['operation' => 'reopen_payroll_report'],
        'payroll.workers.store' => ['operation' => 'add_payroll_worker'],
        'payroll.workers.destroy' => ['operation' => 'remove_payroll_worker'],
        'payroll.wage-override.update' => ['operation' => 'set_payroll_wage_override'],
        'payroll.wage-override.destroy' => ['operation' => 'reset_payroll_wage_override'],
        'payroll.adjustments.store' => ['operation' => 'create_payroll_adjustment'],
        'payroll.adjustments.update' => ['operation' => 'update_payroll_adjustment'],
        'payroll.adjustments.destroy' => ['operation' => 'delete_payroll_adjustment'],
        'recipe-categories.store' => ['operation' => 'create_recipe_category'],
        'recipe-categories.update' => ['operation' => 'update_recipe_category'],
        'recipe-categories.destroy' => ['operation' => 'delete_recipe_category'],
        'recipe-categories.position' => ['operation' => 'move_recipe_category'],
        'recipes.store' => ['operation' => 'create_recipe'],
        'recipes.update' => ['operation' => 'update_recipe'],
        'recipes.archive' => ['operation' => 'archive_recipe'],
        'recipes.position' => ['operation' => 'move_recipe'],
        'recipe-test-sessions.store' => ['operation' => 'start_recipe_test_session'],
        'recipe-test-sessions.update' => ['operation' => 'submit_recipe_test_session'],
        'recipe-tests.update' => ['operation' => 'submit_recipe_test'],
        'settings.profile.update' => ['operation' => 'update_profile'],
        'settings.slack.update' => ['operation' => 'update_slack_channel'],
        'settings.slack.test' => ['operation' => 'test_slack_channel'],
        'settings.slack-digests.retry' => ['operation' => 'retry_slack_digest'],
        'shift-presets.store' => ['operation' => 'create_shift_preset'],
        'shift-presets.update' => ['operation' => 'update_shift_preset'],
        'shift-presets.destroy' => ['operation' => 'delete_shift_preset'],
        'shift-request-month-locks.update' => ['operation' => 'set_shift_request_lock'],
        'shift-requests.approve' => ['operation' => 'approve_shift_request'],
        'shift-share-links.store' => ['operation' => 'create_shift_share_link'],
        'shift-share-links.destroy' => ['operation' => 'revoke_shift_share_link'],
        'shifts.store' => ['operation' => 'create_shift'],
        'shifts.quick-add' => ['operation' => 'quick_add_shift'],
        'shifts.update' => ['operation' => 'update_shift'],
        'shifts.destroy' => ['operation' => 'delete_shift'],
        'statements.update' => ['operation' => 'update_statement'],
        'statements.today.update' => ['operation' => 'update_today_statement'],
        'statements.clear' => ['operation' => 'clear_statement'],
        'statements.versions.restore' => ['operation' => 'restore_statement_version'],
        'stock-movements.store' => ['operation' => 'create_stock_movement'],
        'stock-movements.reverse' => ['operation' => 'reverse_stock_movement'],
        'stores.store' => ['operation' => 'create_store'],
        'stores.update' => ['operation' => 'update_store'],
        'stores.destroy' => ['operation' => 'delete_store'],
        'stores.switch' => ['operation' => 'switch_active_store'],
        'users.store' => [
            'operation' => 'create_user',
            'constraints' => 'The server creates a random secret; passwords never enter the model or assistant audit.',
        ],
        'users.update' => [
            'operation' => 'update_user',
            'constraints' => 'Limited-account email and store assignment are supported; password changes are excluded.',
        ],
        'users.destroy' => ['operation' => 'delete_user'],
        'workers.store' => ['operation' => 'create_worker'],
        'workers.update' => ['operation' => 'update_worker'],
        'workers.destroy' => ['operation' => 'delete_worker'],

        // The public UI route is intentionally included in the durable matrix even though the contract test targets authenticated routes.
        'public-shift-requests.toggle' => ['operation' => 'toggle_shift_request'],
    ],

    // POST endpoints that are semantically queries and therefore use bounded read tools.
    'semantically_read_only' => [
        'gift-vouchers.lookup' => 'Exact company-scoped lookup; the assistant read tool never returns voucher codes.',
    ],

    // Authenticated mutation-shaped routes intentionally outside assistant operations.
    'excluded' => [
        'assistant.chat' => 'Assistant control-plane streaming endpoint, not a domain operation.',
        'assistant.turns.cancel' => 'Durable assistant transport lifecycle, not an application-domain operation.',
        'assistant.turns.retry' => 'Durable assistant recovery lifecycle, not an application-domain operation.',
        'assistant.conversations.destroy' => 'Assistant transcript lifecycle, not an application-domain operation.',
        'logout' => 'Authentication lifecycle is excluded from chat.',
        'settings.password.update' => 'Password and credential flows are excluded from chat.',
        'verify-email.store' => 'Email-verification lifecycle is excluded from chat.',
    ],
];

foreach ($matrix['supported'] as $route => $definition) {
    $action = $definition['operation'];
    unset($definition['operation']);
    $matrix['supported'][$route] = [
        'tool' => $toolForAction($action),
        'action' => $action,
        ...$definition,
    ];
}

return $matrix;
