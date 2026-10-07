<?php

declare(strict_types=1);

use App\Domain\Noticeboard\NoticeboardCardService;
use App\Domain\Workforce\AttendanceService;
use App\Enums\AttendanceActionEnum;
use App\Models\AttendanceSession;
use App\Models\NoticeboardCard;
use App\Models\NoticeboardConfirmation;
use App\Models\NoticeboardConfirmationItem;
use App\Models\Store;
use App\Models\Worker;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Start a real competing operation when the first arrival holds the store lock.
 *
 * @param Closure(): void $arrival
 * @param Closure(): void $competingOperation
 */
function run_mysql_noticeboard_arrival_race(Closure $arrival, Closure $competingOperation): void
{
    $directory = \sys_get_temp_dir() . '/stockflow-noticeboard-race-' . \bin2hex(\random_bytes(8));
    if (!\mkdir($directory)) {
        throw new RuntimeException('Could not create the race directory.');
    }
    $ready = $directory . '/ready';
    $go = $directory . '/go';
    $started = $directory . '/started';
    $result = $directory . '/result';
    DB::disconnect();
    $pid = \pcntl_fork();
    if ($pid === -1) {
        throw new RuntimeException('Could not fork the race process.');
    }
    if ($pid === 0) {
        DB::purge();
        \file_put_contents($ready, 'ready');
        for ($attempt = 0; $attempt < 500 && !\is_file($go); ++$attempt) {
            \usleep(10_000);
        }
        try {
            \file_put_contents($started, 'started');
            $competingOperation();
            \file_put_contents($result, 'success');
        } catch (Throwable $throwable) {
            \file_put_contents($result, $throwable::class . ': ' . $throwable->getMessage());
        }
        exit(0);
    }

    $lockObserved = false;
    $competitorWaited = false;
    try {
        for ($attempt = 0; $attempt < 500 && !\is_file($ready); ++$attempt) {
            \usleep(10_000);
        }
        if (!\is_file($ready)) {
            throw new RuntimeException('The race process did not become ready.');
        }
        DB::listen(static function (QueryExecuted $query) use (&$lockObserved, &$competitorWaited, $go, $started, $result): void {
            $sql = \mb_strtolower($query->sql);
            if ($lockObserved || !\str_contains($sql, '`stores`') || !\str_contains($sql, 'for update')) {
                return;
            }
            $lockObserved = true;
            \file_put_contents($go, 'go');
            for ($attempt = 0; $attempt < 500 && !\is_file($started); ++$attempt) {
                \usleep(10_000);
            }
            \usleep(250_000);
            $competitorWaited = \is_file($started) && !\is_file($result);
        });
        $arrival();
        \pcntl_waitpid($pid, $status);
        \expect($lockObserved)->toBeTrue()
            ->and($competitorWaited)->toBeTrue()
            ->and(\pcntl_wexitstatus($status))->toBe(0)
            ->and((string) \file_get_contents($result))->toBe('success');
    } finally {
        \file_put_contents($go, 'go');
        \pcntl_waitpid($pid, $status);
        if (DB::connection()->transactionLevel() === 0) {
            DB::connection()->beginTransaction();
        }
        foreach ([$ready, $go, $started, $result] as $path) {
            if (\is_file($path)) {
                \unlink($path);
            }
        }
        \rmdir($directory);
    }
}

\test('concurrent arrivals keep one daily list owned by the first arriving worker', function (string $competitor): void {
    if (DB::connection()->getDriverName() !== 'mysql' || !\function_exists('pcntl_fork')) {
        $this->markTestSkipped('This invariant requires MySQL row locks and pcntl.');
    }
    Carbon::setTestNow('2026-10-07 06:00:00 UTC');
    try {
        [$owner] = \createIsolatedUserWithWarehouse();
        $store = Store::factory()->create(['user_id' => $owner->getKey(), 'is_warehouse' => false]);
        $firstWorker = Worker::factory()->create(['user_id' => $owner->getKey()]);
        $secondWorker = Worker::factory()->create(['user_id' => $owner->getKey()]);
        $card = NoticeboardCard::factory()->create([
            'user_id' => $owner->getKey(), 'store_id' => $store->getKey(),
            'display_on' => '2026-10-07', 'body_html' => '<p>Before arrival</p>',
        ]);
        DB::connection()->commit();

        \run_mysql_noticeboard_arrival_race(
            static function () use ($owner, $store, $firstWorker): void {
                (new AttendanceService())->perform($owner, $store, $firstWorker, AttendanceActionEnum::ARRIVAL, true);
            },
            static function () use ($owner, $store, $secondWorker, $card, $competitor): void {
                if ($competitor === 'arrival') {
                    (new AttendanceService())->perform($owner, $store, $secondWorker, AttendanceActionEnum::ARRIVAL, true);
                } else {
                    (new NoticeboardCardService())->update(
                        $card,
                        $owner,
                        '<p>Changed after arrival</p>',
                        $card->getLabel()->value,
                        $card->getColor()->value,
                        $card->getSize()->value,
                        null,
                        null,
                        false,
                        $card->getLockVersion(),
                    );
                }
            },
        );

        $confirmation = NoticeboardConfirmation::query()->where('store_id', $store->getKey())->sole();
        \expect($confirmation->getWorkerId())->toBe($firstWorker->getKey())
            ->and($confirmation->getItems())->toHaveCount(1)
            ->and(NoticeboardConfirmationItem::query()->where('confirmation_id', $confirmation->getKey())->sole()->getBodyHtml())->toBe('<p>Before arrival</p>')
            ->and(AttendanceSession::query()->where('store_id', $store->getKey())->count())->toBe($competitor === 'arrival' ? 2 : 1);
        if ($competitor === 'card edit') {
            \expect($card->refresh()->getBodyHtml())->toBe('<p>Changed after arrival</p>');
        }
    } finally {
        Carbon::setTestNow();
    }
})->with(['arrival', 'card edit']);
