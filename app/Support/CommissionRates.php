<?php

declare(strict_types=1);

namespace App\Support;

final class CommissionRates
{
    /**
     * Card processing commission.
     */
    public const string CARD = '0.01';

    /**
     * Bolt commission excluding VAT, including its cash sales base.
     */
    public const string BOLT = '0.35';

    /**
     * Wolt commission excluding VAT.
     */
    public const string WOLT = '0.30';

    /**
     * Foodora commission excluding VAT.
     */
    public const string FOODORA = '0.30';
}
