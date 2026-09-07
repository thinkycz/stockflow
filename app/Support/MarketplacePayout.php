<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\StatementDay;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Cash settlement estimates, including VAT on the separately rounded commission.
 *
 * @phpstan-type Breakdown array{base: string, commission_rate: string, vat_rate: string, commission: string, vat: string, deduction: string, net_revenue: string, expected_transfer: string}
 */
final class MarketplacePayout
{
    /**
     * VAT charged on marketplace commissions; unrelated to VAT on sold products.
     */
    public const string VAT_RATE = '0.21';

    /**
     * Calculate a channel over one complete period; cash is already received by the store.
     *
     * @return Breakdown
     */
    public static function calculate(string $channel, string $sales, string $cash = '0'): array
    {
        $rate = match ($channel) {
            'wolt' => CommissionRates::WOLT,
            'bolt' => CommissionRates::BOLT,
            'foodora' => CommissionRates::FOODORA,
            default => throw new InvalidArgumentException('Unsupported marketplace channel.'),
        };
        $base = BigDecimal::of($sales)->plus($channel === 'bolt' ? $cash : '0')->toScale(2, RoundingMode::HalfUp);
        $commission = $base->multipliedBy($rate)->toScale(2, RoundingMode::HalfUp);
        $vat = $commission->multipliedBy(self::VAT_RATE)->toScale(2, RoundingMode::HalfUp);
        $deduction = $commission->plus($vat);

        return [
            'base' => (string) $base,
            'commission_rate' => $rate,
            'vat_rate' => self::VAT_RATE,
            'commission' => (string) $commission,
            'vat' => (string) $vat,
            'deduction' => (string) $deduction,
            'net_revenue' => (string) $base->minus($deduction),
            'expected_transfer' => (string) BigDecimal::of($sales)->minus($deduction)->toScale(2, RoundingMode::HalfUp),
        ];
    }

    /**
     * Sum persisted decimal inputs before calculating and rounding period fees.
     *
     * @param iterable<StatementDay> $days
     *
     * @return array{wolt: Breakdown, bolt: Breakdown, foodora: Breakdown}
     */
    public static function forDays(iterable $days): array
    {
        $wolt = $bolt = $cash = $foodora = BigDecimal::zero();
        foreach ($days as $day) {
            $wolt = $wolt->plus($day->getWoltDecimal());
            $bolt = $bolt->plus($day->getBoltDecimal());
            $cash = $cash->plus($day->getBoltCashDecimal());
            $foodora = $foodora->plus($day->getFoodoraDecimal());
        }

        return [
            'wolt' => self::calculate('wolt', (string) $wolt),
            'bolt' => self::calculate('bolt', (string) $bolt, (string) $cash),
            'foodora' => self::calculate('foodora', (string) $foodora),
        ];
    }

    /**
     * Preserve legacy numeric totals at the transport boundary only.
     *
     * @param array<string, Breakdown> $breakdowns
     */
    public static function totalDeduction(array $breakdowns): float
    {
        $total = BigDecimal::zero();
        foreach ($breakdowns as $breakdown) {
            $total = $total->plus($breakdown['deduction']);
        }

        return $total->toFloat();
    }
}
