<?php

declare(strict_types=1);

use App\Support\MarketplacePayout;

\test('marketplace estimates separate commission VAT and already received cash', function (string $channel, string $sales, string $cash, string $commission, string $vat, string $transfer, string $net): void {
    \expect(MarketplacePayout::calculate($channel, $sales, $cash))->toMatchArray([
        'commission' => $commission, 'vat' => $vat, 'expected_transfer' => $transfer, 'net_revenue' => $net,
    ]);
})->with([
    ['wolt', '1000', '0', '350.00', '73.50', '564.40', '564.40'],
    ['foodora', '1000', '0', '300.00', '63.00', '637.00', '637.00'],
    ['bolt', '1000', '0', '350.00', '73.50', '576.50', '576.50'],
    ['bolt', '1000', '200', '420.00', '88.20', '491.80', '691.80'],
    ['wolt', '0.05', '0', '0.02', '0.00', '0.03', '0.03'],
    ['wolt', '0.10', '0', '0.04', '0.01', '0.05', '0.05'],
    ['foodora', '0', '0', '0.00', '0.00', '0.00', '0.00'],
    ['bolt', '0', '100', '35.00', '7.35', '-42.35', '57.65'],
]);

\test('Wolt range includes the separately rounded transaction fee and VAT', function (): void {
    \expect(MarketplacePayout::calculate('wolt', '1000'))->toMatchArray([
        'estimate_type' => 'range', 'transfer_min' => '564.40', 'transfer_max' => '624.90',
        'transaction_fee' => '10.00', 'transaction_vat' => '2.10',
        'deduction' => '435.60', 'net_min' => '564.40', 'net_max' => '624.90',
    ]);
    \expect(MarketplacePayout::calculate('wolt', '-1000'))->toMatchArray([
        'transfer_min' => '-624.90', 'transfer_max' => '-564.40', 'expected_transfer' => '-624.90',
    ]);
});

\test('Bolt charged VAT changes by sales date while cash stays outside the transfer', function (): void {
    \expect(MarketplacePayout::calculate('bolt', '1000', '200', '2026-08-02'))->toMatchArray([
        'vat' => '0.00', 'expected_transfer' => '580.00', 'net_revenue' => '780.00',
    ])->and(MarketplacePayout::calculate('bolt', '1000', '200', '2026-08-03'))->toMatchArray([
        'vat' => '88.20', 'expected_transfer' => '491.80', 'net_revenue' => '691.80',
    ]);
});
