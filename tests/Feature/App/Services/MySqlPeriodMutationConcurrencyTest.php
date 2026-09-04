<?php

declare(strict_types=1);

use App\Domain\BankStatements\BankStatementService;
use App\Domain\Finance\FinancialReportService;
use App\Domain\Identity\PasswordResetService;
use App\Domain\Inventory\InventoryDraftRowInput;
use App\Domain\Inventory\InventorySessionService;
use App\Domain\Inventory\StockMovementService;
use App\Domain\Noticeboard\NoticeboardCardService;
use App\Domain\Payroll\PayrollReportService;
use App\Domain\Statements\StatementService;
use App\Domain\Workforce\ShiftAssignmentService;
use App\Domain\Workforce\WorkforceManagementService;
use App\Enums\BankStatementStatusEnum;
use App\Enums\FinancialDirectionEnum;
use App\Enums\PayrollAdjustmentTypeEnum;
use App\Enums\PayrollReportStatusEnum;
use App\Models\BankStatement;
use App\Models\FinancialReport;
use App\Models\FinancialReportManualRow;
use App\Models\Item;
use App\Models\PayrollAdjustment;
use App\Models\PayrollReport;
use App\Models\Shift;
use App\Models\ShiftPreset;
use App\Models\ShiftShareLink;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * Run a mutation on a second MySQL connection while the parent row is locked,
 * then commit a competing terminal-state transition before releasing it.
 *
 * @param Closure(): void $mutation
 * @param Closure(ConnectionInterface): void $transition
 */
function assert_mysql_delayed_mutation_is_blocked(Closure $mutation, Closure $transition, string|null $transitionLockTable = null): void
{
    $directory = \sys_get_temp_dir() . '/stockflow-mysql-lock-' . \bin2hex(\random_bytes(8));
    if (!\mkdir($directory) && !\is_dir($directory)) {
        throw new RuntimeException('Could not create the concurrency test directory.');
    }

    $ready = $directory . '/ready';
    $go = $directory . '/go';
    $result = $directory . '/result';
    DB::disconnect();
    $pid = \pcntl_fork();

    if ($pid === -1) {
        throw new RuntimeException('Could not fork the concurrency test process.');
    }

    if ($pid === 0) {
        DB::purge();
        \file_put_contents($ready, 'ready');
        for ($attempt = 0; $attempt < 500 && !\is_file($go); ++$attempt) {
            \usleep(10_000);
        }

        try {
            $mutation();
            \file_put_contents($result, 'mutated');
        } catch (HttpExceptionInterface|InvalidArgumentException|ValidationException) {
            \file_put_contents($result, 'blocked');
        } catch (Throwable $throwable) {
            \file_put_contents($result, 'error:' . $throwable::class . ':' . $throwable->getMessage());
        }

        exit(0);
    }

    try {
        for ($attempt = 0; $attempt < 500 && !\is_file($ready); ++$attempt) {
            \usleep(10_000);
        }
        if (!\is_file($ready)) {
            throw new RuntimeException('The concurrency test child did not become ready.');
        }

        $connection = DB::connection();
        $connection->beginTransaction();
        $lockObserved = false;
        if ($transitionLockTable !== null) {
            DB::listen(static function (QueryExecuted $query) use (&$lockObserved, $go, $transitionLockTable): void {
                $sql = \mb_strtolower($query->sql);
                if ($lockObserved || !\str_contains($sql, \mb_strtolower($transitionLockTable)) || !\str_contains($sql, 'for update')) {
                    return;
                }

                $lockObserved = true;
                \file_put_contents($go, 'go');
                \usleep(250_000);
            });
        }
        $transition($connection);
        if ($transitionLockTable === null) {
            \file_put_contents($go, 'go');
            \usleep(250_000);
        } elseif (!$lockObserved) {
            \file_put_contents($go, 'go');
            $connection->rollBack();
            \pcntl_waitpid($pid, $status);

            throw new RuntimeException("The {$transitionLockTable} lifecycle transition did not acquire FOR UPDATE.");
        }
        $connection->commit();
        \pcntl_waitpid($pid, $status);

        \expect(\pcntl_wexitstatus($status))->toBe(0)
            ->and((string) \file_get_contents($result))->toBe('blocked');
    } finally {
        $connection = DB::connection();
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
        if (!$connection->getPdo()->inTransaction()) {
            $connection->beginTransaction();
        }
        foreach ([$ready, $go, $result] as $path) {
            if (\is_file($path)) {
                \unlink($path);
            }
        }
        \rmdir($directory);
    }
}

function require_mysql_fork_support(): void
{
    if (DB::connection()->getDriverName() !== 'mysql') {
        \test()->markTestSkipped('This invariant requires MySQL row locks.');
    }
    if (!\function_exists('pcntl_fork')) {
        \test()->markTestSkipped('The pcntl extension is required for a two-connection invariant.');
    }
}

\test('financial close serializes ahead of a delayed manual-row mutation', function (): void {
    \require_mysql_fork_support();
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $report = FinancialReport::factory()->forStore($store)->forMonth(2026, 8)->create();
    PayrollReport::query()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'year' => 2026,
        'month' => 8,
        'status' => PayrollReportStatusEnum::CLOSED->value,
    ]);
    DB::connection()->commit();

    \assert_mysql_delayed_mutation_is_blocked(
        static function () use ($admin, $store): void {
            (new FinancialReportService())->createManualRow(
                $admin,
                $store,
                2026,
                8,
                FinancialDirectionEnum::EXPENSE,
                'Late expense',
                '2026-08-31',
                1.0,
                null,
            );
        },
        static function (ConnectionInterface $connection) use ($admin, $store): void {
            (new FinancialReportService())->close($admin, $store, 2026, 8);
        },
        'financial_reports',
    );

    \expect(FinancialReportManualRow::query()->where('financial_report_id', $report->getKey())->count())->toBe(0);
});

\test('bank confirmation serializes ahead of delayed edit and retry mutations', function (string $operation): void {
    \require_mysql_fork_support();
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $statement = BankStatement::factory()->forStore($store)->create([
        'status' => BankStatementStatusEnum::REVIEW->value,
    ]);
    DB::connection()->commit();

    \assert_mysql_delayed_mutation_is_blocked(
        static function () use ($statement, $operation, $admin): void {
            $service = new BankStatementService();
            if ($operation === 'edit') {
                $service->updateDraft($statement, [], $admin);

                return;
            }
            $service->retry($statement, $admin);
        },
        static function (ConnectionInterface $connection) use ($statement, $admin): void {
            (new BankStatementService())->confirm($statement, $admin);
        },
        'bank_statements',
    );

    \expect(BankStatement::query()->whereKey($statement->getKey())->value('status'))
        ->toBe(BankStatementStatusEnum::CONFIRMED->value);
})->with(['edit', 'retry']);

\test('payroll close serializes ahead of a delayed adjustment mutation', function (): void {
    \require_mysql_fork_support();
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $item = Item::factory()->create(['user_id' => $admin->getKey()]);
    $report = PayrollReport::query()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'year' => 2026,
        'month' => 8,
        'status' => PayrollReportStatusEnum::OPEN->value,
    ]);
    DB::connection()->commit();

    \assert_mysql_delayed_mutation_is_blocked(
        static function () use ($admin, $store, $worker): void {
            (new PayrollReportService())->createAdjustment(
                $admin,
                $store,
                2026,
                8,
                $worker,
                PayrollAdjustmentTypeEnum::TIP,
                '1.00',
                'Late tip',
            );
        },
        static function (ConnectionInterface $connection) use ($admin, $store): void {
            (new PayrollReportService())->close($admin, $store, 2026, 8);
        },
        'payroll_reports',
    );

    \expect(PayrollAdjustment::query()->where('payroll_report_id', $report->getKey())->count())->toBe(0);
});

\test('store deactivation serializes ahead of delayed prospective workforce mutations', function (string $operation): void {
    \require_mysql_fork_support();
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $item = Item::factory()->create(['user_id' => $admin->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 9)->create();
    $statementDay = StatementDay::factory()->for($statement, 'statement')->create([
        'date' => '2026-09-01',
        'cash' => 0,
        'total' => 0,
    ]);
    DB::connection()->commit();

    \assert_mysql_delayed_mutation_is_blocked(
        static function () use ($admin, $store, $worker, $item, $statement, $statementDay, $operation): void {
            if ($operation === 'shift') {
                (new ShiftAssignmentService())->create(
                    $admin,
                    $store,
                    $worker,
                    '2026-09-30',
                    '08:00',
                    '09:00',
                );

                return;
            }

            $service = new WorkforceManagementService();
            if ($operation === 'preset') {
                $service->createPreset($admin, $store, 'Late preset', '08:00', '09:00');

                return;
            }
            if ($operation === 'stock_movement') {
                \app(StockMovementService::class)->createMovement([
                    'mode' => 'incoming',
                    'store_id' => $store->getKey(),
                    'items' => [['item_id' => $item->getKey(), 'quantity' => 1]],
                ], $admin);

                return;
            }
            if ($operation === 'inventory_count') {
                \app(InventorySessionService::class)->createSession($admin, $store, [[
                    'item_id' => $item->getKey(),
                    'quantity' => 1,
                ]]);

                return;
            }
            if ($operation === 'statement') {
                (new StatementService())->updateDays($statement, [[
                    'date' => $statementDay->getDate(),
                    'cash' => 1,
                    'card' => 0,
                    'wolt' => 0,
                    'bolt' => 0,
                    'bolt_cash' => 0,
                    'foodora' => 0,
                ]], $admin);

                return;
            }
            if ($operation === 'noticeboard') {
                (new NoticeboardCardService())->create(
                    $admin,
                    $store,
                    '<p>Late card</p>',
                    'information',
                    'yellow',
                    'medium',
                    null,
                    null,
                );

                return;
            }
            $service->createShareLink($admin, $store, 'Late share link');
        },
        static function (ConnectionInterface $connection) use ($store): void {
            $connection->table('stores')->where('id', $store->getKey())->lockForUpdate()->first();
            $connection->table('stores')->where('id', $store->getKey())->update(['status' => 'inactive']);
        },
    );

    if ($operation === 'statement') {
        \expect($statementDay->refresh()->getCash())->toBe(0.0);

        return;
    }

    $table = match ($operation) {
        'shift' => 'shifts',
        'preset' => 'shift_presets',
        'stock_movement' => 'stock_movements',
        'inventory_count' => 'inventory_sessions',
        'noticeboard' => 'noticeboard_cards',
        default => 'shift_share_links',
    };
    \expect(DB::table($table)->where('store_id', $store->getKey())->count())->toBe(0);
})->with(['shift', 'preset', 'share_link', 'stock_movement', 'inventory_count', 'statement', 'noticeboard']);

\test('password reset token consumption serializes before password mutation', function (): void {
    \require_mysql_fork_support();
    $user = UserFactory::new()->admin()->createOne(['password' => 'old-password']);
    $token = Resolver::resolvePasswordBroker('users')->createToken($user);
    DB::connection()->commit();

    \assert_mysql_delayed_mutation_is_blocked(
        static function () use ($user, $token): void {
            $result = (new PasswordResetService())->reset(
                'users',
                'users',
                $user->getEmail(),
                $token,
                'new-password',
            );
            if (\is_string($result)) {
                throw new InvalidArgumentException($result);
            }
        },
        static function (ConnectionInterface $connection) use ($user): void {
            $connection->table('user_password_resets')
                ->where('email', $user->getEmail())
                ->lockForUpdate()
                ->first();
            $connection->table('user_password_resets')->where('email', $user->getEmail())->delete();
        },
    );

    \expect(Resolver::resolveHasher()->check('old-password', $user->refresh()->getAuthPassword()))
        ->toBeTrue();
});

\test('store deactivation serializes ahead of delayed workforce history deletion', function (string $operation): void {
    \require_mysql_fork_support();
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $target = match ($operation) {
        'shift' => Shift::factory()->create([
            'user_id' => $admin->getKey(),
            'store_id' => $store->getKey(),
            'worker_id' => $worker->getKey(),
            'date' => '2026-01-01',
        ]),
        'preset' => ShiftPreset::factory()->create([
            'user_id' => $admin->getKey(),
            'store_id' => $store->getKey(),
        ]),
        default => ShiftShareLink::factory()->create([
            'user_id' => $admin->getKey(),
            'store_id' => $store->getKey(),
        ]),
    };
    DB::connection()->commit();

    \assert_mysql_delayed_mutation_is_blocked(
        static function () use ($admin, $store, $target, $operation): void {
            $service = new WorkforceManagementService();
            if ($operation === 'shift') {
                $service->deleteShift($admin, $store, Typer::assertInstance($target, Shift::class));

                return;
            }
            if ($operation === 'preset') {
                $service->deletePreset($admin, $store, Typer::assertInstance($target, ShiftPreset::class));

                return;
            }
            $service->deleteShareLink($admin, $store, Typer::assertInstance($target, ShiftShareLink::class));
        },
        static function (ConnectionInterface $connection) use ($store): void {
            $connection->table('stores')->where('id', $store->getKey())->lockForUpdate()->first();
            $connection->table('stores')->where('id', $store->getKey())->update(['status' => 'inactive']);
        },
    );

    \expect($target->fresh())->not->toBeNull();
})->with(['shift', 'preset', 'share_link']);

\test('inventory terminal transitions serialize close and cancel', function (string $first): void {
    \require_mysql_fork_support();
    [$user, $store] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $service = \app(InventorySessionService::class);
    $draft = $service->startDraft($user, $store);
    $service->saveDraftRow($user, $draft, InventoryDraftRowInput::fromPayload(['item_id' => $item->getKey(), 'quantity' => 5, 'expected_revision' => 0]));
    DB::connection()->commit();

    \assert_mysql_delayed_mutation_is_blocked(
        static function () use ($service, $user, $draft, $first): void {
            if ($first === 'close') {
                $service->cancelDraft($user, $draft);
            } else {
                $service->closeDraft($user, $draft);
            }
        },
        static function (ConnectionInterface $connection) use ($service, $user, $draft, $first): void {
            if ($first === 'close') {
                $service->closeDraft($user, $draft);
            } else {
                $service->cancelDraft($user, $draft);
            }
        },
        'inventory_sessions',
    );
    \expect($draft->fresh()?->getStatus())->toBe($first === 'close' ? 'closed' : 'cancelled')
        ->and(DB::table('stock_movements')->where('inventory_session_id', $draft->getKey())->count())->toBe($first === 'close' ? 1 : 0);
    if ($first === 'close') {
        \expect((int) DB::table('store_items')->where('store_id', $store->getKey())->where('item_id', $item->getKey())->value('quantity'))->toBe(5);
    }
})->with(['close', 'cancel']);
