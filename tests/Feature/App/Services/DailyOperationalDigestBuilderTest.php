<?php

declare(strict_types=1);

use App\Enums\OperationalActivityTypeEnum;
use App\Models\FinancialReport;
use App\Models\FinancialReportManualRow;
use App\Models\OperationalActivity;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use App\Services\DailyOperationalDigestBuilder;
use Carbon\CarbonImmutable;

\test('builder uses Prague calendar boundaries and includes active and snapshotted locations', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Praha']);
    $inactive = Store::factory()->inactive()->create(['user_id' => $admin->getKey(), 'name' => 'Zavřená pobočka']);

    foreach ([
        ['2026-08-01T21:59:59+00:00', []],
        ['2026-08-01T22:00:00+00:00', [['store_id' => $retail->getKey(), 'store_name' => 'Praha', 'perspective' => null]]],
        ['2026-08-02T21:59:59+00:00', [['store_id' => $inactive->getKey(), 'store_name' => 'Zavřená pobočka', 'perspective' => null]]],
        ['2026-08-02T22:00:00+00:00', []],
    ] as [$occurredAt, $contexts]) {
        OperationalActivity::factory()->create([
            'company_user_id' => $admin->getKey(),
            'occurred_at' => $occurredAt,
            'store_contexts' => $contexts,
        ]);
    }

    $snapshot = (new DailyOperationalDigestBuilder())->build($admin, CarbonImmutable::parse('2026-08-02'));

    \expect($snapshot['activity_count'])->toBe(2)
        ->and($snapshot['period_start'])->toBe('2026-08-01T22:00:00+00:00')
        ->and($snapshot['period_end'])->toBe('2026-08-02T22:00:00+00:00')
        ->and(\array_column($snapshot['sections'], 'name'))->toBe([
            $warehouse->getName(),
            'Praha',
            'Zavřená pobočka',
            'Celofiremní',
        ])
        ->and($snapshot['sections'][0]['activity_count'])->toBe(0)
        ->and($snapshot['sections'][1]['activity_count'])->toBe(1)
        ->and($snapshot['sections'][2]['activity_count'])->toBe(1)
        ->and($snapshot['sections'][3]['activity_count'])->toBe(0);
});

\test('builder aggregates routine events and preserves audit details without sensitive fields', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Brno']);
    $context = [['store_id' => $store->getKey(), 'store_name' => 'Brno', 'perspective' => null]];

    OperationalActivity::factory()->count(2)->create([
        'company_user_id' => $admin->getKey(),
        'type' => OperationalActivityTypeEnum::ATTENDANCE_ARRIVAL->value,
        'occurred_at' => '2026-08-02T08:00:00+00:00',
        'store_contexts' => $context,
        'facts' => ['Slack worker' => 'Jan Novák'],
    ]);
    OperationalActivity::factory()->create([
        'company_user_id' => $admin->getKey(),
        'type' => OperationalActivityTypeEnum::ATTENDANCE_DEVIATION_REJECTED->value,
        'actor_email' => 'manager@example.com',
        'occurred_at' => '2026-08-02T09:00:00+00:00',
        'store_contexts' => $context,
        'facts' => ['Slack worker' => 'Petr Malý', 'Slack review reason' => 'NESMÍ SE ZOBRAZIT'],
    ]);

    $snapshot = (new DailyOperationalDigestBuilder())->build($admin, CarbonImmutable::parse('2026-08-02'));
    $section = $snapshot['sections'][1];
    $encoded = \json_encode($section, flags: \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

    \expect($section['activity_count'])->toBe(3)
        ->and($encoded)->toContain('2× příchod')
        ->toContain('Odchylka docházky zamítnuta')
        ->toContain('manager@example.com')
        ->not->toContain('NESMÍ SE ZOBRAZIT')
        ->not->toContain('Slack review reason');
});

\test('every operational activity type has an explicit digest category and label', function (): void {
    foreach (OperationalActivityTypeEnum::cases() as $type) {
        \expect($type->digestCategory())->not->toBe('')
            ->and($type->digestLabel())->not->toBe('');
    }
});

\test('builder calculates recipe score and voucher batch totals', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $occurredAt = '2026-08-02T10:00:00+00:00';

    foreach ([
        [OperationalActivityTypeEnum::RECIPE_TEST_PASSED, ['Slack recipe test score' => '90 %']],
        [OperationalActivityTypeEnum::RECIPE_TEST_FAILED, ['Slack recipe test score' => '50 %']],
        [OperationalActivityTypeEnum::GIFT_VOUCHER_BATCH_ISSUED, [
            'Slack voucher quantity' => '3',
            'Slack voucher total value' => '1 500,00 Kč',
        ]],
        [OperationalActivityTypeEnum::GIFT_VOUCHER_BATCH_ISSUED, [
            'Slack voucher quantity' => '2',
            'Slack voucher total value' => '800,00 Kč',
        ]],
    ] as [$type, $facts]) {
        OperationalActivity::factory()->create([
            'company_user_id' => $admin->getKey(),
            'type' => $type->value,
            'occurred_at' => $occurredAt,
            'store_contexts' => [],
            'facts' => $facts,
        ]);
    }

    $snapshot = (new DailyOperationalDigestBuilder())->build($admin, CarbonImmutable::parse('2026-08-02'));
    $paragraphs = \implode(' ', $snapshot['sections'][1]['paragraphs']);

    \expect($paragraphs)->toContain('1× úspěšný receptový test')
        ->toContain('1× neúspěšný receptový test')
        ->toContain('průměrné skóre 70 %')
        ->toContain('2 batchů, 5 poukazů, celková hodnota 2 300,00 Kč');
});

\test('builder includes monthly financial stats and the statement total for every active retail store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Praha']);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 8)->create();
    StatementDay::factory()->create([
        'statement_id' => $statement->getKey(),
        'date' => '2026-08-02',
        'cash' => 100,
        'total' => 100,
    ]);
    $report = FinancialReport::factory()->byUser($admin)->forStore($store)->forMonth(2026, 8)->create();
    FinancialReportManualRow::factory()->create([
        'financial_report_id' => $report->getKey(),
        'occurred_on' => '2026-08-02',
        'amount' => 40,
    ]);

    $snapshot = (new DailyOperationalDigestBuilder())->build($admin, CarbonImmutable::parse('2026-08-02'));

    \expect(\implode(' ', $snapshot['sections'][0]['paragraphs']))
        ->not->toContain('Finance za 08/2026')
        ->not->toContain('Výkaz za 02. 08. 2026')
        ->and(\implode(' ', $snapshot['sections'][1]['paragraphs']))
        ->toContain('Finance za 08/2026: příjmy 100,00 Kč; výdaje 40,00 Kč; zisk 60,00 Kč.')
        ->toContain('Výkaz za 02. 08. 2026: celkem 100,00 Kč.');
});

\test('builder respects the twenty-five-hour Prague daylight-saving day', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();

    $snapshot = (new DailyOperationalDigestBuilder())->build($admin, CarbonImmutable::parse('2026-10-25'));

    \expect($snapshot['period_start'])->toBe('2026-10-24T22:00:00+00:00')
        ->and($snapshot['period_end'])->toBe('2026-10-25T23:00:00+00:00');
});

\test('builder shows one transfer in both locations with its local direction', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Olomouc']);

    OperationalActivity::factory()->create([
        'company_user_id' => $admin->getKey(),
        'type' => OperationalActivityTypeEnum::STOCK_TRANSFER_CREATED->value,
        'occurred_at' => '2026-08-02T10:00:00+00:00',
        'store_contexts' => [
            ['store_id' => $warehouse->getKey(), 'store_name' => $warehouse->getName(), 'perspective' => 'outgoing'],
            ['store_id' => $store->getKey(), 'store_name' => 'Olomouc', 'perspective' => 'incoming'],
        ],
    ]);

    $snapshot = (new DailyOperationalDigestBuilder())->build($admin, CarbonImmutable::parse('2026-08-02'));

    \expect(\implode(' ', $snapshot['sections'][0]['paragraphs']))->toContain('1× převod zásob – odchozí')
        ->and(\implode(' ', $snapshot['sections'][1]['paragraphs']))->toContain('1× převod zásob – příchozí')
        ->and($snapshot['activity_count'])->toBe(1);
});
