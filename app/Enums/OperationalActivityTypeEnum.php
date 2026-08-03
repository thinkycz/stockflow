<?php

declare(strict_types=1);

namespace App\Enums;

enum OperationalActivityTypeEnum: string
{
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
}
