<?php

declare(strict_types=1);

use App\Enums\OperationalActivityTypeEnum;
use App\Models\OperationalActivity;
use App\Models\Store;
use App\Services\OperationalActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

\test('store activity is journaled without Slack configuration', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', null);
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Praha', 'slack_channel' => null]);

    OperationalActivityService::dispatch(
        OperationalActivityTypeEnum::STATEMENT_SAVED,
        $admin,
        '2026-08-02T10:15:00+00:00',
        '/statements',
        [['store' => $store, 'perspective' => null]],
        ['Slack month' => '2026-08'],
    );

    $activity = Typer::assertInstance(OperationalActivity::query()->sole(), OperationalActivity::class);

    \expect($activity->getCompanyUserId())->toBe($admin->getKey())
        ->and($activity->getType())->toBe(OperationalActivityTypeEnum::STATEMENT_SAVED)
        ->and($activity->getActorEmail())->toBe($admin->getEmail())
        ->and($activity->getOccurredAt()->toIso8601String())->toBe('2026-08-02T10:15:00+00:00')
        ->and($activity->getUrl())->toBe('/statements')
        ->and($activity->getFacts())->toBe(['Slack month' => '2026-08'])
        ->and($activity->getStoreContexts())->toBe([[
            'store_id' => $store->getKey(),
            'store_name' => 'Praha',
            'perspective' => null,
        ]]);
    Notification::assertNothingSent();
});

\test('one transfer activity snapshots both store perspectives', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', null);
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Brno']);

    OperationalActivityService::dispatch(
        OperationalActivityTypeEnum::STOCK_TRANSFER_CREATED,
        $admin,
        '2026-08-02T11:00:00+00:00',
        '/stock-movements/42',
        [
            ['store' => $warehouse, 'perspective' => 'outgoing'],
            ['store' => $retail, 'perspective' => 'incoming'],
        ],
        ['Slack movement number' => 'P-2026-0042'],
    );

    $activity = Typer::assertInstance(OperationalActivity::query()->sole(), OperationalActivity::class);

    \expect($activity->getStoreContexts())->toBe([
        ['store_id' => $warehouse->getKey(), 'store_name' => $warehouse->getName(), 'perspective' => 'outgoing'],
        ['store_id' => $retail->getKey(), 'store_name' => 'Brno', 'perspective' => 'incoming'],
    ]);
});

\test('rolled back business transaction removes the journal entry', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'slack_channel' => '#praha']);

    try {
        DB::transaction(static function () use ($admin, $store): void {
            OperationalActivityService::dispatch(
                OperationalActivityTypeEnum::STATEMENT_SAVED,
                $admin,
                '2026-08-02T10:15:00+00:00',
                '/statements',
                [['store' => $store, 'perspective' => null]],
                [],
            );

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
        // Expected rollback.
    }

    \expect(OperationalActivity::query()->count())->toBe(0);
    Notification::assertNothingSent();
});
