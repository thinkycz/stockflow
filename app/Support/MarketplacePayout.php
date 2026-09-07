<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\StatementDay;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Cash estimates, never a substitute for a platform settlement.
 *
 * @phpstan-type Segment array{base: string, commission_rate: string, vat_rate: string, commission: string, vat: string, transaction_fee: string, transaction_vat: string, deduction: string, net_revenue: string, expected_transfer: string}
 * @phpstan-type Breakdown array{base: string, commission_rate: string, vat_rate: string, commission: string, vat: string, transaction_fee: string, transaction_vat: string, deduction: string, net_revenue: string, expected_transfer: string, estimate_type: string, transfer_min: string, transfer_max: string, net_min: string, net_max: string, excluded_items: list<string>, segments: list<Segment>}
 */
final class MarketplacePayout
{
    /**
     * Commission VAT, unrelated to VAT on sold products.
     */
    public const string VAT_RATE = '0.21';

    /**
     * First sales day with charged Czech Bolt commission VAT.
     */
    public const string BOLT_VAT_FROM = '2026-08-03';

    /**
     * Wolt+ commission excluding VAT.
     */
    public const string WOLT_PLUS_RATE = '0.35';

    /**
     * Wolt transaction fee excluding VAT.
     */
    public const string WOLT_TRANSACTION_RATE = '0.01';

    /**
     * Calculate a uniform tax regime. Dated daily input uses forDays instead.
     *
     * @return Breakdown
     */
    public static function calculate(string $channel, string $sales, string $cash = '0', string|null $salesDate = null): array
    {
        $rate = match ($channel) {
            'wolt' => self::WOLT_PLUS_RATE,
            'bolt' => CommissionRates::BOLT,
            'foodora' => CommissionRates::FOODORA,
            default => throw new InvalidArgumentException('Unsupported marketplace channel.'),
        };
        $vatRate = $channel === 'bolt' && $salesDate !== null && $salesDate < self::BOLT_VAT_FROM ? '0' : self::VAT_RATE;
        $base = BigDecimal::of($sales)->plus($channel === 'bolt' ? $cash : '0');
        $primary = self::segment($base, BigDecimal::of($sales), $rate, $vatRate, $channel === 'wolt' ? self::WOLT_TRANSACTION_RATE : '0');
        $alternative = $channel === 'wolt' ? self::segment($base, BigDecimal::of($sales), CommissionRates::WOLT, $vatRate, self::WOLT_TRANSACTION_RATE) : $primary;
        $min = BigDecimal::min($primary['expected_transfer'], $alternative['expected_transfer']);
        $max = BigDecimal::max($primary['expected_transfer'], $alternative['expected_transfer']);
        // Preserve conservative scalar fields even for negative input adjustments.
        if (BigDecimal::of($alternative['expected_transfer'])->isLessThan(BigDecimal::of($primary['expected_transfer']))) {
            [$primary, $alternative] = [$alternative, $primary];
        }

        return [...$primary, 'estimate_type' => $channel === 'wolt' ? 'range' : 'point',
            'transfer_min' => (string) $min, 'transfer_max' => (string) $max,
            'net_min' => $primary['net_revenue'], 'net_max' => $alternative['net_revenue'],
            'excluded_items' => match ($channel) {
                'wolt' => ['wolt_plus_share', 'advertising', 'delivery_service_vat', 'other_adjustments'],
                'bolt' => ['other_adjustments'],
                default => [],
            }, 'segments' => [$primary],
        ];
    }

    /**
     * Aggregate first, rounding once per period and applicable tax regime.
     *
     * @param iterable<StatementDay> $days
     *
     * @return array{wolt: Breakdown, bolt: Breakdown, foodora: Breakdown}
     */
    public static function forDays(iterable $days): array
    {
        $wolt = $foodora = BigDecimal::zero();
        $bolt = ['old' => BigDecimal::zero(), 'new' => BigDecimal::zero()];
        $cash = $bolt;
        $regimes = [];
        foreach ($days as $day) {
            $wolt = $wolt->plus($day->getWoltDecimal());
            $foodora = $foodora->plus($day->getFoodoraDecimal());
            $regime = $day->getDate() < self::BOLT_VAT_FROM ? 'old' : 'new';
            $regimes[$regime] = true;
            $bolt[$regime] = $bolt[$regime]->plus($day->getBoltDecimal());
            $cash[$regime] = $cash[$regime]->plus($day->getBoltCashDecimal());
        }
        $old = self::calculate('bolt', (string) $bolt['old'], (string) $cash['old'], '2026-08-02');
        $new = self::calculate('bolt', (string) $bolt['new'], (string) $cash['new'], self::BOLT_VAT_FROM);
        $combined = isset($regimes['old']) && !isset($regimes['new']) ? $old : $new;
        if (isset($regimes['old'], $regimes['new'])) {
            foreach (['base', 'commission', 'vat', 'transaction_fee', 'transaction_vat', 'deduction', 'net_revenue', 'expected_transfer', 'transfer_min', 'transfer_max', 'net_min', 'net_max'] as $field) {
                $combined[$field] = (string) BigDecimal::of($old[$field])->plus($new[$field]);
            }
            $combined['segments'] = [...$old['segments'], ...$new['segments']];
        }

        return ['wolt' => self::calculate('wolt', (string) $wolt), 'bolt' => $combined, 'foodora' => self::calculate('foodora', (string) $foodora)];
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

    /**
     * Calculate each fee and its VAT separately using exact decimal arithmetic.
     *
     * @return Segment
     */
    private static function segment(BigDecimal $base, BigDecimal $sales, string $rate, string $vatRate, string $transactionRate): array
    {
        $commission = $base->multipliedBy($rate)->toScale(2, RoundingMode::HalfUp);
        $vat = $commission->multipliedBy($vatRate)->toScale(2, RoundingMode::HalfUp);
        $transaction = $base->multipliedBy($transactionRate)->toScale(2, RoundingMode::HalfUp);
        $transactionVat = $transaction->multipliedBy($vatRate)->toScale(2, RoundingMode::HalfUp);
        $deduction = $commission->plus($vat)->plus($transaction)->plus($transactionVat);

        return ['base' => (string) $base->toScale(2, RoundingMode::HalfUp), 'commission_rate' => $rate, 'vat_rate' => $vatRate,
            'commission' => (string) $commission, 'vat' => (string) $vat,
            'transaction_fee' => (string) $transaction, 'transaction_vat' => (string) $transactionVat,
            'deduction' => (string) $deduction,
            'net_revenue' => (string) $base->minus($deduction)->toScale(2, RoundingMode::HalfUp),
            'expected_transfer' => (string) $sales->minus($deduction)->toScale(2, RoundingMode::HalfUp)];
    }
}
