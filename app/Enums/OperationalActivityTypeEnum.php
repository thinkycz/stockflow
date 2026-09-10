<?php

declare(strict_types=1);

namespace App\Enums;

enum OperationalActivityTypeEnum: string
{
    case FINANCIAL_ROW_CREATED = 'financial_row_created';

    case FINANCIAL_ROW_UPDATED = 'financial_row_updated';

    case FINANCIAL_ROW_DELETED = 'financial_row_deleted';

    case FINANCIAL_OVERRIDE_SET = 'financial_override_set';

    case FINANCIAL_OVERRIDE_RESET = 'financial_override_reset';

    case FINANCIAL_ROWS_COPIED = 'financial_rows_copied';

    case RECURRING_EXPENSE_CREATED = 'recurring_expense_created';

    case RECURRING_EXPENSE_UPDATED = 'recurring_expense_updated';

    case RECURRING_EXPENSE_TERMINATED = 'recurring_expense_terminated';

    case BANK_STATEMENT_CONFIRMED = 'bank_statement_confirmed';

    case BANK_STATEMENT_REOPENED = 'bank_statement_reopened';

    case BANK_STATEMENT_DELETED = 'bank_statement_deleted';

    case PAYROLL_WORKER_ADDED = 'payroll_worker_added';

    case PAYROLL_WORKER_REMOVED = 'payroll_worker_removed';

    case PAYROLL_WAGE_OVERRIDE_SET = 'payroll_wage_override_set';

    case PAYROLL_WAGE_OVERRIDE_RESET = 'payroll_wage_override_reset';

    case PAYROLL_ADJUSTMENT_CREATED = 'payroll_adjustment_created';

    case PAYROLL_ADJUSTMENT_UPDATED = 'payroll_adjustment_updated';

    case PAYROLL_ADJUSTMENT_DELETED = 'payroll_adjustment_deleted';

    case PAYROLL_TIPS_DISTRIBUTED = 'payroll_tips_distributed';

    case SHIFT_CREATED = 'shift_created';

    case SHIFT_UPDATED = 'shift_updated';

    case SHIFT_DELETED = 'shift_deleted';

    case SHIFT_REQUEST_APPROVED = 'shift_request_approved';

    case SHIFT_REQUESTS_LOCKED = 'shift_requests_locked';

    case SHIFT_REQUESTS_UNLOCKED = 'shift_requests_unlocked';

    case ATTENDANCE_CORRECTION_RESTORED = 'attendance_correction_restored';

    case ATTENDANCE_SHIFT_MATCHED = 'attendance_shift_matched';

    case ATTENDANCE_REPORT_MATCHED = 'attendance_report_matched';

    case NOTICEBOARD_CREATED = 'noticeboard_created';

    case NOTICEBOARD_UPDATED = 'noticeboard_updated';

    case NOTICEBOARD_TRASHED = 'noticeboard_trashed';

    case NOTICEBOARD_RESTORED = 'noticeboard_restored';

    case ATTENDANCE_ARRIVAL = 'attendance_arrival';

    case ATTENDANCE_BREAK_STARTED = 'attendance_break_started';

    case ATTENDANCE_BREAK_ENDED = 'attendance_break_ended';

    case ATTENDANCE_DEPARTURE = 'attendance_departure';

    case ATTENDANCE_CORRECTION_CREATED = 'attendance_correction_created';

    case ATTENDANCE_CORRECTION_UPDATED = 'attendance_correction_updated';

    case ATTENDANCE_CORRECTION_VOIDED = 'attendance_correction_voided';

    case ATTENDANCE_DEVIATION_APPROVED = 'attendance_deviation_approved';

    case ATTENDANCE_DEVIATION_REJECTED = 'attendance_deviation_rejected';

    case CHECKLIST_SHIFT_COMPLETED = 'checklist_shift_completed';

    case CHECKLIST_SHIFT_REOPENED = 'checklist_shift_reopened';

    case CHECKLIST_DAY_EXCUSED = 'checklist_day_excused';

    case CHECKLIST_DAY_EXCUSE_REVOKED = 'checklist_day_excuse_revoked';

    case PAYROLL_REPORT_CLOSED = 'payroll_report_closed';

    case PAYROLL_REPORT_REOPENED = 'payroll_report_reopened';

    case FINANCIAL_REPORT_CLOSED = 'financial_report_closed';

    case FINANCIAL_REPORT_REOPENED = 'financial_report_reopened';

    case RECIPE_TEST_PASSED = 'recipe_test_passed';

    case RECIPE_TEST_FAILED = 'recipe_test_failed';

    case GIFT_VOUCHER_BATCH_ISSUED = 'gift_voucher_batch_issued';

    case GIFT_VOUCHER_REDEEMED = 'gift_voucher_redeemed';

    case GIFT_VOUCHER_VOIDED = 'gift_voucher_voided';

    case GIFT_VOUCHER_REDEMPTION_REVERSED = 'gift_voucher_redemption_reversed';

    case INVENTORY_SAVED = 'inventory_saved';

    case STATEMENT_SAVED = 'statement_saved';

    case STATEMENT_CLEARED = 'statement_cleared';

    case STATEMENT_RESTORED = 'statement_restored';

    case STOCK_MOVEMENT_CREATED = 'stock_movement_created';

    case STOCK_TRANSFER_CREATED = 'stock_transfer_created';

    case STOCK_MOVEMENT_REVERSED = 'stock_movement_reversed';

    case STOCK_TRANSFER_REVERSED = 'stock_transfer_reversed';

    /**
     * Return all operational activity values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_map(static fn(self $type): string => $type->value, self::cases());
    }

    /**
     * Backend translation key for the activity heading.
     */
    public function translationKey(): string
    {
        return match ($this) {
            self::FINANCIAL_ROW_CREATED => 'Slack activity financial row created',
            self::FINANCIAL_ROW_UPDATED => 'Slack activity financial row updated',
            self::FINANCIAL_ROW_DELETED => 'Slack activity financial row deleted',
            self::FINANCIAL_OVERRIDE_SET => 'Slack activity financial override set',
            self::FINANCIAL_OVERRIDE_RESET => 'Slack activity financial override reset',
            self::FINANCIAL_ROWS_COPIED => 'Slack activity financial rows copied',
            self::RECURRING_EXPENSE_CREATED => 'Slack activity recurring expense created',
            self::RECURRING_EXPENSE_UPDATED => 'Slack activity recurring expense updated',
            self::RECURRING_EXPENSE_TERMINATED => 'Slack activity recurring expense terminated',
            self::BANK_STATEMENT_CONFIRMED => 'Slack activity bank statement confirmed',
            self::BANK_STATEMENT_REOPENED => 'Slack activity bank statement reopened',
            self::BANK_STATEMENT_DELETED => 'Slack activity bank statement deleted',
            self::PAYROLL_WORKER_ADDED => 'Slack activity payroll worker added',
            self::PAYROLL_WORKER_REMOVED => 'Slack activity payroll worker removed',
            self::PAYROLL_WAGE_OVERRIDE_SET => 'Slack activity payroll wage override set',
            self::PAYROLL_WAGE_OVERRIDE_RESET => 'Slack activity payroll wage override reset',
            self::PAYROLL_ADJUSTMENT_CREATED => 'Slack activity payroll adjustment created',
            self::PAYROLL_ADJUSTMENT_UPDATED => 'Slack activity payroll adjustment updated',
            self::PAYROLL_ADJUSTMENT_DELETED => 'Slack activity payroll adjustment deleted',
            self::PAYROLL_TIPS_DISTRIBUTED => 'Slack activity payroll tips distributed',
            self::SHIFT_CREATED => 'Slack activity shift created',
            self::SHIFT_UPDATED => 'Slack activity shift updated',
            self::SHIFT_DELETED => 'Slack activity shift deleted',
            self::SHIFT_REQUEST_APPROVED => 'Slack activity shift request approved',
            self::SHIFT_REQUESTS_LOCKED => 'Slack activity shift requests locked',
            self::SHIFT_REQUESTS_UNLOCKED => 'Slack activity shift requests unlocked',
            self::ATTENDANCE_CORRECTION_RESTORED => 'Slack activity attendance correction restored',
            self::ATTENDANCE_SHIFT_MATCHED => 'Slack activity attendance shift matched',
            self::ATTENDANCE_REPORT_MATCHED => 'Slack activity attendance report matched',
            self::NOTICEBOARD_CREATED => 'Slack activity noticeboard created',
            self::NOTICEBOARD_UPDATED => 'Slack activity noticeboard updated',
            self::NOTICEBOARD_TRASHED => 'Slack activity noticeboard trashed',
            self::NOTICEBOARD_RESTORED => 'Slack activity noticeboard restored',

            self::ATTENDANCE_ARRIVAL => 'Slack activity attendance arrival',
            self::ATTENDANCE_BREAK_STARTED => 'Slack activity attendance break started',
            self::ATTENDANCE_BREAK_ENDED => 'Slack activity attendance break ended',
            self::ATTENDANCE_DEPARTURE => 'Slack activity attendance departure',
            self::ATTENDANCE_CORRECTION_CREATED => 'Slack activity attendance correction created',
            self::ATTENDANCE_CORRECTION_UPDATED => 'Slack activity attendance correction updated',
            self::ATTENDANCE_CORRECTION_VOIDED => 'Slack activity attendance correction voided',
            self::ATTENDANCE_DEVIATION_APPROVED => 'Slack activity attendance deviation approved',
            self::ATTENDANCE_DEVIATION_REJECTED => 'Slack activity attendance deviation rejected',
            self::CHECKLIST_SHIFT_COMPLETED => 'Slack activity checklist shift completed',
            self::CHECKLIST_SHIFT_REOPENED => 'Slack activity checklist shift reopened',
            self::CHECKLIST_DAY_EXCUSED => 'Slack activity checklist day excused',
            self::CHECKLIST_DAY_EXCUSE_REVOKED => 'Slack activity checklist day excuse revoked',
            self::PAYROLL_REPORT_CLOSED => 'Slack activity payroll report closed',
            self::PAYROLL_REPORT_REOPENED => 'Slack activity payroll report reopened',
            self::FINANCIAL_REPORT_CLOSED => 'Slack activity financial report closed',
            self::FINANCIAL_REPORT_REOPENED => 'Slack activity financial report reopened',
            self::RECIPE_TEST_PASSED => 'Slack activity recipe test passed',
            self::RECIPE_TEST_FAILED => 'Slack activity recipe test failed',
            self::GIFT_VOUCHER_BATCH_ISSUED => 'Slack activity gift voucher batch issued',
            self::GIFT_VOUCHER_REDEEMED => 'Slack activity gift voucher redeemed',
            self::GIFT_VOUCHER_VOIDED => 'Slack activity gift voucher voided',
            self::GIFT_VOUCHER_REDEMPTION_REVERSED => 'Slack activity gift voucher redemption reversed',
            self::INVENTORY_SAVED => 'Slack activity inventory saved',
            self::STATEMENT_SAVED => 'Slack activity statement saved',
            self::STATEMENT_CLEARED => 'Slack activity statement cleared',
            self::STATEMENT_RESTORED => 'Slack activity statement restored',
            self::STOCK_MOVEMENT_CREATED => 'Slack activity stock movement created',
            self::STOCK_TRANSFER_CREATED => 'Slack activity stock transfer created',
            self::STOCK_MOVEMENT_REVERSED => 'Slack activity stock movement reversed',
            self::STOCK_TRANSFER_REVERSED => 'Slack activity stock transfer reversed',
        };
    }

    /**
     * Human-facing digest category in Czech.
     */
    public function digestCategory(): string
    {
        return match ($this) {
            self::FINANCIAL_ROW_CREATED,
            self::FINANCIAL_ROW_UPDATED,
            self::FINANCIAL_ROW_DELETED,
            self::FINANCIAL_OVERRIDE_SET,
            self::FINANCIAL_OVERRIDE_RESET,
            self::FINANCIAL_ROWS_COPIED,
            self::RECURRING_EXPENSE_CREATED,
            self::RECURRING_EXPENSE_UPDATED,
            self::RECURRING_EXPENSE_TERMINATED,
            self::BANK_STATEMENT_CONFIRMED,
            self::BANK_STATEMENT_REOPENED,
            self::BANK_STATEMENT_DELETED => 'Finance',
            self::PAYROLL_WORKER_ADDED,
            self::PAYROLL_WORKER_REMOVED,
            self::PAYROLL_WAGE_OVERRIDE_SET,
            self::PAYROLL_WAGE_OVERRIDE_RESET,
            self::PAYROLL_ADJUSTMENT_CREATED,
            self::PAYROLL_ADJUSTMENT_UPDATED,
            self::PAYROLL_ADJUSTMENT_DELETED,
            self::PAYROLL_TIPS_DISTRIBUTED => 'Výplaty',
            self::SHIFT_CREATED,
            self::SHIFT_UPDATED,
            self::SHIFT_DELETED,
            self::SHIFT_REQUEST_APPROVED,
            self::SHIFT_REQUESTS_LOCKED,
            self::SHIFT_REQUESTS_UNLOCKED => 'Směny',
            self::ATTENDANCE_CORRECTION_RESTORED,
            self::ATTENDANCE_SHIFT_MATCHED,
            self::ATTENDANCE_REPORT_MATCHED => 'Docházka',
            self::NOTICEBOARD_CREATED,
            self::NOTICEBOARD_UPDATED,
            self::NOTICEBOARD_TRASHED,
            self::NOTICEBOARD_RESTORED => 'Nástěnka',

            self::ATTENDANCE_ARRIVAL,
            self::ATTENDANCE_BREAK_STARTED,
            self::ATTENDANCE_BREAK_ENDED,
            self::ATTENDANCE_DEPARTURE,
            self::ATTENDANCE_CORRECTION_CREATED,
            self::ATTENDANCE_CORRECTION_UPDATED,
            self::ATTENDANCE_CORRECTION_VOIDED,
            self::ATTENDANCE_DEVIATION_APPROVED,
            self::ATTENDANCE_DEVIATION_REJECTED => 'Docházka',
            self::CHECKLIST_SHIFT_COMPLETED,
            self::CHECKLIST_SHIFT_REOPENED,
            self::CHECKLIST_DAY_EXCUSED,
            self::CHECKLIST_DAY_EXCUSE_REVOKED => 'Checklisty',
            self::PAYROLL_REPORT_CLOSED,
            self::PAYROLL_REPORT_REOPENED => 'Výplaty',
            self::FINANCIAL_REPORT_CLOSED,
            self::FINANCIAL_REPORT_REOPENED => 'Finance',
            self::RECIPE_TEST_PASSED,
            self::RECIPE_TEST_FAILED => 'Receptové testy',
            self::GIFT_VOUCHER_BATCH_ISSUED,
            self::GIFT_VOUCHER_REDEEMED,
            self::GIFT_VOUCHER_VOIDED,
            self::GIFT_VOUCHER_REDEMPTION_REVERSED => 'Dárkové poukazy',
            self::INVENTORY_SAVED => 'Inventury',
            self::STATEMENT_SAVED,
            self::STATEMENT_CLEARED,
            self::STATEMENT_RESTORED => 'Tržby',
            self::STOCK_MOVEMENT_CREATED,
            self::STOCK_TRANSFER_CREATED,
            self::STOCK_MOVEMENT_REVERSED,
            self::STOCK_TRANSFER_REVERSED => 'Skladové pohyby',
        };
    }

    /**
     * Compact Czech label for aggregate digest sentences.
     */
    public function digestLabel(): string
    {
        return match ($this) {
            self::FINANCIAL_ROW_CREATED => 'příjem nebo výdaj přidán',
            self::FINANCIAL_ROW_UPDATED => 'příjem nebo výdaj upraven',
            self::FINANCIAL_ROW_DELETED => 'příjem nebo výdaj odstraněn',
            self::FINANCIAL_OVERRIDE_SET => 'finanční částka přepsána',
            self::FINANCIAL_OVERRIDE_RESET => 'finanční výpočet obnoven',
            self::FINANCIAL_ROWS_COPIED => 'řádky minulého měsíce zkopírovány',
            self::RECURRING_EXPENSE_CREATED => 'pravidelný výdaj vytvořen',
            self::RECURRING_EXPENSE_UPDATED => 'pravidelný výdaj upraven',
            self::RECURRING_EXPENSE_TERMINATED => 'pravidelný výdaj ukončen',
            self::BANK_STATEMENT_CONFIRMED => 'bankovní výpis potvrzen',
            self::BANK_STATEMENT_REOPENED => 'bankovní výpis znovu otevřen',
            self::BANK_STATEMENT_DELETED => 'bankovní výpis odstraněn',
            self::PAYROLL_WORKER_ADDED => 'pracovník přidán do výplat',
            self::PAYROLL_WORKER_REMOVED => 'pracovník odebrán z výplat',
            self::PAYROLL_WAGE_OVERRIDE_SET => 'přepis mzdy upraven',
            self::PAYROLL_WAGE_OVERRIDE_RESET => 'automatický výpočet mzdy obnoven',
            self::PAYROLL_ADJUSTMENT_CREATED => 'úprava výplaty vytvořena',
            self::PAYROLL_ADJUSTMENT_UPDATED => 'úprava výplaty změněna',
            self::PAYROLL_ADJUSTMENT_DELETED => 'úprava výplaty odstraněna',
            self::PAYROLL_TIPS_DISTRIBUTED => 'spropitné rozděleno',
            self::SHIFT_CREATED => 'směna vytvořena',
            self::SHIFT_UPDATED => 'směna upravena',
            self::SHIFT_DELETED => 'směna odstraněna',
            self::SHIFT_REQUEST_APPROVED => 'žádost o směnu schválena',
            self::SHIFT_REQUESTS_LOCKED => 'žádosti o směny uzamčeny',
            self::SHIFT_REQUESTS_UNLOCKED => 'žádosti o směny odemčeny',
            self::ATTENDANCE_CORRECTION_RESTORED => 'docházka obnovena',
            self::ATTENDANCE_SHIFT_MATCHED => 'přiřazení směny k docházce upraveno',
            self::ATTENDANCE_REPORT_MATCHED => 'docházka hromadně přiřazena',
            self::NOTICEBOARD_CREATED => 'oznámení na nástěnce vytvořeno',
            self::NOTICEBOARD_UPDATED => 'oznámení na nástěnce upraveno',
            self::NOTICEBOARD_TRASHED => 'oznámení na nástěnce přesunuto do koše',
            self::NOTICEBOARD_RESTORED => 'oznámení na nástěnce obnoveno',

            self::ATTENDANCE_ARRIVAL => 'příchod',
            self::ATTENDANCE_BREAK_STARTED => 'zahájení pauzy',
            self::ATTENDANCE_BREAK_ENDED => 'ukončení pauzy',
            self::ATTENDANCE_DEPARTURE => 'odchod',
            self::ATTENDANCE_CORRECTION_CREATED => 'korekce docházky vytvořena',
            self::ATTENDANCE_CORRECTION_UPDATED => 'korekce docházky upravena',
            self::ATTENDANCE_CORRECTION_VOIDED => 'korekce docházky zrušena',
            self::ATTENDANCE_DEVIATION_APPROVED => 'odchylka docházky schválena',
            self::ATTENDANCE_DEVIATION_REJECTED => 'odchylka docházky zamítnuta',
            self::CHECKLIST_SHIFT_COMPLETED => 'checklist dokončen',
            self::CHECKLIST_SHIFT_REOPENED => 'checklist znovu otevřen',
            self::CHECKLIST_DAY_EXCUSED => 'den omluven',
            self::CHECKLIST_DAY_EXCUSE_REVOKED => 'omluva dne zrušena',
            self::PAYROLL_REPORT_CLOSED => 'výplatní report uzavřen',
            self::PAYROLL_REPORT_REOPENED => 'výplatní report znovu otevřen',
            self::FINANCIAL_REPORT_CLOSED => 'finanční report uzavřen',
            self::FINANCIAL_REPORT_REOPENED => 'finanční report znovu otevřen',
            self::RECIPE_TEST_PASSED => 'úspěšný receptový test',
            self::RECIPE_TEST_FAILED => 'neúspěšný receptový test',
            self::GIFT_VOUCHER_BATCH_ISSUED => 'vydaný batch poukazů',
            self::GIFT_VOUCHER_REDEEMED => 'uplatněný poukaz',
            self::GIFT_VOUCHER_VOIDED => 'zneplatněný poukaz',
            self::GIFT_VOUCHER_REDEMPTION_REVERSED => 'vrácené uplatnění poukazu',
            self::INVENTORY_SAVED => 'uzavřená inventura',
            self::STATEMENT_SAVED => 'uložená tržba',
            self::STATEMENT_CLEARED => 'vymazaná tržba',
            self::STATEMENT_RESTORED => 'obnovená tržba',
            self::STOCK_MOVEMENT_CREATED => 'skladový pohyb',
            self::STOCK_TRANSFER_CREATED => 'převod zásob',
            self::STOCK_MOVEMENT_REVERSED => 'storno skladového pohybu',
            self::STOCK_TRANSFER_REVERSED => 'storno převodu zásob',
        };
    }

    /**
     * Whether the archive should retain a per-event detail sentence.
     */
    public function hasDigestDetail(): bool
    {
        return !\in_array($this, [
            self::ATTENDANCE_ARRIVAL,
            self::ATTENDANCE_BREAK_STARTED,
            self::ATTENDANCE_BREAK_ENDED,
            self::ATTENDANCE_DEPARTURE,
            self::STOCK_MOVEMENT_CREATED,
            self::STOCK_TRANSFER_CREATED,
        ], true);
    }

    /**
     * Whether an audit-style digest detail should identify its actor.
     */
    public function hasDigestActor(): bool
    {
        return \in_array($this, [
            self::FINANCIAL_ROW_CREATED,
            self::FINANCIAL_ROW_UPDATED,
            self::FINANCIAL_ROW_DELETED,
            self::FINANCIAL_OVERRIDE_SET,
            self::FINANCIAL_OVERRIDE_RESET,
            self::FINANCIAL_ROWS_COPIED,
            self::RECURRING_EXPENSE_CREATED,
            self::RECURRING_EXPENSE_UPDATED,
            self::RECURRING_EXPENSE_TERMINATED,
            self::BANK_STATEMENT_CONFIRMED,
            self::BANK_STATEMENT_REOPENED,
            self::BANK_STATEMENT_DELETED,
            self::PAYROLL_WORKER_ADDED,
            self::PAYROLL_WORKER_REMOVED,
            self::PAYROLL_WAGE_OVERRIDE_SET,
            self::PAYROLL_WAGE_OVERRIDE_RESET,
            self::PAYROLL_ADJUSTMENT_CREATED,
            self::PAYROLL_ADJUSTMENT_UPDATED,
            self::PAYROLL_ADJUSTMENT_DELETED,
            self::PAYROLL_TIPS_DISTRIBUTED,
            self::SHIFT_CREATED,
            self::SHIFT_UPDATED,
            self::SHIFT_DELETED,
            self::SHIFT_REQUEST_APPROVED,
            self::SHIFT_REQUESTS_LOCKED,
            self::SHIFT_REQUESTS_UNLOCKED,
            self::ATTENDANCE_CORRECTION_RESTORED,
            self::ATTENDANCE_SHIFT_MATCHED,
            self::ATTENDANCE_REPORT_MATCHED,
            self::NOTICEBOARD_CREATED,
            self::NOTICEBOARD_UPDATED,
            self::NOTICEBOARD_TRASHED,
            self::NOTICEBOARD_RESTORED,

            self::ATTENDANCE_CORRECTION_CREATED,
            self::ATTENDANCE_CORRECTION_UPDATED,
            self::ATTENDANCE_CORRECTION_VOIDED,
            self::ATTENDANCE_DEVIATION_APPROVED,
            self::ATTENDANCE_DEVIATION_REJECTED,
            self::PAYROLL_REPORT_CLOSED,
            self::PAYROLL_REPORT_REOPENED,
            self::FINANCIAL_REPORT_CLOSED,
            self::FINANCIAL_REPORT_REOPENED,
            self::GIFT_VOUCHER_VOIDED,
            self::GIFT_VOUCHER_REDEMPTION_REVERSED,
            self::STOCK_MOVEMENT_REVERSED,
            self::STOCK_TRANSFER_REVERSED,
        ], true);
    }
}
