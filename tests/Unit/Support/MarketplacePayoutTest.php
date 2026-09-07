<?php

declare(strict_types=1);

use App\Support\MarketplacePayout;

\test('marketplace estimates separate commission VAT and already received cash', function (string $channel, string $sales, string $cash, string $commission, string $vat, string $transfer, string $net): void {
    \expect(MarketplacePayout::calculate($channel, $sales, $cash))->toMatchArray([
        'commission' => $commission, 'vat' => $vat, 'expected_transfer' => $transfer, 'net_revenue' => $net,
    ]);
})->with([
    ['wolt', '1000', '0', '300.00', '63.00', '637.00', '637.00'],
    ['foodora', '1000', '0', '300.00', '63.00', '637.00', '637.00'],
    ['bolt', '1000', '0', '350.00', '73.50', '576.50', '576.50'],
    ['bolt', '1000', '200', '420.00', '88.20', '491.80', '691.80'],
    ['wolt', '0.05', '0', '0.02', '0.00', '0.03', '0.03'],
    ['wolt', '0.10', '0', '0.03', '0.01', '0.06', '0.06'],
    ['foodora', '0', '0', '0.00', '0.00', '0.00', '0.00'],
    ['bolt', '0', '100', '35.00', '7.35', '-42.35', '57.65'],
]);
