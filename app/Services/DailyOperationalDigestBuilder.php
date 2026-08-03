<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationalActivityTypeEnum;
use App\Models\OperationalActivity;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class DailyOperationalDigestBuilder
{
    public const BUSINESS_TIMEZONE = 'Europe/Prague';

    /**
     * Safe fact keys already approved for immediate Slack notifications.
     *
     * @var list<string>
     */
    private const SAFE_FACT_KEYS = [
        'Slack actual time',
        'Slack attendance date',
        'Slack checklist date',
        'Slack checklist shift',
        'Slack counted rows',
        'Slack difference rows',
        'Slack financial expenses',
        'Slack financial income',
        'Slack financial profit',
        'Slack inventory number',
        'Slack item count',
        'Slack movement number',
        'Slack payroll base',
        'Slack payroll deductions',
        'Slack payroll final',
        'Slack payroll tips',
        'Slack payslip count',
        'Slack planned time',
        'Slack recipe count',
        'Slack recipe test result',
        'Slack recipe test score',
        'Slack recipe',
        'Slack report month',
        'Slack reviewed time',
        'Slack statement bolt cash',
        'Slack statement bolt',
        'Slack statement card',
        'Slack statement cash',
        'Slack statement date',
        'Slack statement foodora',
        'Slack statement period',
        'Slack statement today total',
        'Slack statement wolt',
        'Slack total quantity',
        'Slack total value',
        'Slack voucher amount',
        'Slack voucher batch',
        'Slack voucher expiration',
        'Slack voucher quantity',
        'Slack voucher total value',
        'Slack voucher',
        'Slack worker',
    ];

    /**
     * Build one immutable digest snapshot for a Prague calendar date.
     *
     * @return array{
     *     date: string,
     *     title: string,
     *     intro: string,
     *     period_start: string,
     *     period_end: string,
     *     activity_count: int,
     *     sections: list<array{
     *         key: string,
     *         name: string,
     *         is_warehouse: bool,
     *         activity_count: int,
     *         paragraphs: list<string>,
     *         details: list<array{title: string, body: string, actor: string|null, url: string}>
     *     }>
     * }
     */
    public function build(User $company, CarbonImmutable $date): array
    {
        $localStart = $date->setTimezone(self::BUSINESS_TIMEZONE)->startOfDay();
        $localEnd = $localStart->addDay();
        $periodStart = $localStart->utc();
        $periodEnd = $localEnd->utc();

        $activityQuery = OperationalActivity::querySelect(OperationalActivity::query())
            ->where('company_user_id', $company->getKey())
            ->where('occurred_at', '>=', $periodStart)
            ->where('occurred_at', '<', $periodEnd)
            ->orderBy('occurred_at')
            ->orderBy('id');
        $activities = $activityQuery->get();

        $storeQuery = Store::querySelect(Store::query()->where('user_id', $company->getKey()));
        Store::scopeActive($storeQuery);
        $activeStores = $storeQuery->orderByDesc('is_warehouse')->orderBy('name')->get();

        /** @var array<int, array{key: string, name: string, is_warehouse: bool, activities: list<OperationalActivity>}> $locations */
        $locations = [];
        foreach ($activeStores as $value) {
            $store = Typer::assertInstance($value, Store::class);
            $locations[$store->getKey()] = [
                'key' => 'store:' . $store->getKey(),
                'name' => $store->getName(),
                'is_warehouse' => $store->isWarehouse(),
                'activities' => [],
            ];
        }

        $companyActivities = [];
        foreach ($activities as $value) {
            $activity = Typer::assertInstance($value, OperationalActivity::class);
            $contexts = $activity->getStoreContexts();
            if ($contexts === []) {
                $companyActivities[] = $activity;

                continue;
            }

            foreach ($contexts as $context) {
                if (!isset($locations[$context['store_id']])) {
                    $locations[$context['store_id']] = [
                        'key' => 'store:' . $context['store_id'],
                        'name' => $context['store_name'],
                        'is_warehouse' => false,
                        'activities' => [],
                    ];
                }
                $locations[$context['store_id']]['activities'][] = $activity;
            }
        }

        $sections = [];
        foreach ($locations as $location) {
            $sections[] = $this->buildSection(
                $location['key'],
                $location['name'],
                $location['is_warehouse'],
                $location['activities'],
            );
        }
        $sections[] = $this->buildSection('company', 'Celofiremní', false, $companyActivities);

        $activityCount = $activities->count();
        $formattedDate = Typer::assertInstance($localStart->locale('cs'), CarbonImmutable::class)
            ->translatedFormat('j. F Y');

        return [
            'date' => $localStart->toDateString(),
            'title' => 'Denní provozní souhrn — ' . $formattedDate,
            'intro' => $activityCount === 0
                ? 'Za tento den nebyly zaznamenány žádné provozní milníky.'
                : 'Za tento den bylo zaznamenáno ' . $activityCount . ' provozních milníků.',
            'period_start' => $periodStart->toIso8601String(),
            'period_end' => $periodEnd->toIso8601String(),
            'activity_count' => $activityCount,
            'sections' => $sections,
        ];
    }

    /**
     * Build one human-facing location or company section.
     *
     * @param list<OperationalActivity> $activities
     *
     * @return array{
     *     key: string,
     *     name: string,
     *     is_warehouse: bool,
     *     activity_count: int,
     *     paragraphs: list<string>,
     *     details: list<array{title: string, body: string, actor: string|null, url: string}>
     * }
     */
    private function buildSection(string $key, string $name, bool $isWarehouse, array $activities): array
    {
        /** @var array<string, array<string, int>> $counts */
        $counts = [];
        $details = [];

        foreach ($activities as $activity) {
            $type = $activity->getType();
            $counts[$type->digestCategory()][$type->value] = ($counts[$type->digestCategory()][$type->value] ?? 0) + 1;

            if (!$type->hasDigestDetail()) {
                continue;
            }

            $facts = [];
            foreach ($activity->getFacts() as $label => $value) {
                if (!\in_array($label, self::SAFE_FACT_KEYS, true)) {
                    continue;
                }
                $translated = Typer::assertString(Resolver::resolveTranslator()->get($label, [], 'cs'));
                $facts[] = $translated . ': ' . $value;
            }

            $details[] = [
                'title' => \ucfirst($type->digestLabel()),
                'body' => \implode(', ', $facts),
                'actor' => $type->hasDigestActor() ? $activity->getActorEmail() : null,
                'url' => $activity->getUrl(),
            ];
        }

        $paragraphs = [];
        foreach ($counts as $category => $typeCounts) {
            $parts = [];
            foreach ($typeCounts as $typeValue => $count) {
                $type = OperationalActivityTypeEnum::from($typeValue);
                $directionCounts = $this->directionCounts($activities, $type, $key);
                if ($directionCounts !== []) {
                    foreach ($directionCounts as $direction => $directionCount) {
                        $parts[] = $directionCount . '× ' . $type->digestLabel() . ' – ' . $direction;
                    }

                    continue;
                }
                $parts[] = $count . '× ' . $type->digestLabel();
            }
            $paragraph = $category . ': ' . \implode('; ', $parts);
            if ($category === 'Receptové testy') {
                $scores = $this->numericFacts($activities, 'Slack recipe test score');
                if ($scores !== []) {
                    $paragraph .= '; průměrné skóre ' . $this->formatNumber(\array_sum($scores) / \count($scores)) . ' %';
                }
            }
            if ($category === 'Dárkové poukazy') {
                $batches = \array_values(\array_filter(
                    $activities,
                    static fn(OperationalActivity $activity): bool => $activity->getType() === OperationalActivityTypeEnum::GIFT_VOUCHER_BATCH_ISSUED,
                ));
                if ($batches !== []) {
                    $quantity = (int) \array_sum($this->numericFacts($batches, 'Slack voucher quantity'));
                    $value = \array_sum($this->numericFacts($batches, 'Slack voucher total value'));
                    $paragraph .= '; vydáno ' . \count($batches) . ' batchů, ' . $quantity . ' poukazů, celková hodnota '
                        . \number_format($value, 2, ',', ' ') . ' Kč';
                }
            }
            $paragraphs[] = $paragraph . '.';
        }

        if ($paragraphs === []) {
            $paragraphs[] = 'Bez provozních milníků.';
        }

        return [
            'key' => $key,
            'name' => $name,
            'is_warehouse' => $isWarehouse,
            'activity_count' => \count($activities),
            'paragraphs' => $paragraphs,
            'details' => $details,
        ];
    }

    /**
     * Read numeric values from safe display facts such as `90 %` or `1 500,00 Kč`.
     *
     * @param list<OperationalActivity> $activities
     *
     * @return list<float>
     */
    private function numericFacts(array $activities, string $key): array
    {
        $values = [];
        foreach ($activities as $activity) {
            $value = $activity->getFacts()[$key] ?? null;
            if ($value === null) {
                continue;
            }

            $normalized = \str_replace(["\u{00A0}", ' '], '', $value);
            $normalized = \str_replace(',', '.', $normalized);
            $normalized = \preg_replace('/[^0-9.\\-]/', '', $normalized);
            if ($normalized === null || !\is_numeric($normalized)) {
                continue;
            }
            $values[] = (float) $normalized;
        }

        return $values;
    }

    /**
     * Format an aggregate decimal without redundant trailing zeroes.
     */
    private function formatNumber(float $value): string
    {
        return \mb_rtrim(\mb_rtrim(\number_format($value, 2, ',', ''), '0'), ',');
    }

    /**
     * Count transfer activities from the perspective of the current location.
     *
     * @param list<OperationalActivity> $activities
     *
     * @return array<string, int>
     */
    private function directionCounts(array $activities, OperationalActivityTypeEnum $type, string $sectionKey): array
    {
        if (!\in_array($type, [
            OperationalActivityTypeEnum::STOCK_TRANSFER_CREATED,
            OperationalActivityTypeEnum::STOCK_TRANSFER_REVERSED,
        ], true) || !\str_starts_with($sectionKey, 'store:')) {
            return [];
        }

        $storeId = (int) \mb_substr($sectionKey, 6);
        $counts = [];
        foreach ($activities as $activity) {
            if ($type !== $activity->getType()) {
                continue;
            }
            foreach ($activity->getStoreContexts() as $context) {
                if ($context['store_id'] !== $storeId || $context['perspective'] === null) {
                    continue;
                }
                $direction = match ($context['perspective']) {
                    'incoming' => 'příchozí',
                    'outgoing' => 'odchozí',
                    default => $context['perspective'],
                };
                $counts[$direction] = ($counts[$direction] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
