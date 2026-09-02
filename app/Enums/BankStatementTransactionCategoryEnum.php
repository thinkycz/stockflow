<?php

declare(strict_types=1);

namespace App\Enums;

enum BankStatementTransactionCategoryEnum: string
{
    case CARD = 'card';

    case WOLT = 'wolt';

    case BOLT = 'bolt';

    case FOODORA = 'foodora';

    case OTHER_INCOMING = 'other_incoming';

    case OUTGOING = 'outgoing';

    /**
     * Get possible values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_map(static fn(self $category): string => $category->value, self::cases());
    }

    /**
     * Determine whether the category can be reconciled with daily statements.
     */
    public function reconciliable(): bool
    {
        return \in_array($this, [self::CARD, self::WOLT, self::BOLT, self::FOODORA], true);
    }
}
