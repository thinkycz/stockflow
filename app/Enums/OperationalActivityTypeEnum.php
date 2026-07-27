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
