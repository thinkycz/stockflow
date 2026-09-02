<?php

declare(strict_types=1);

use App\Enums\ChecklistShiftEnum;
use App\Enums\ChecklistTemplateScopeEnum;
use App\Models\ChecklistDay;
use App\Models\Item;
use App\Models\NoticeboardCard;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\StatementVersion;
use App\Models\Store;
use App\Services\ChecklistService;
use App\Services\InventorySessionService;
use App\Services\NoticeboardCardService;
use App\Services\StatementService;
use App\Services\StockMovementService;
use App\Services\WorkforceManagementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

\test('prospective mutations recheck the locked store after deactivation', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
        'checklists_initialized_at' => null,
    ]);
    $day = ChecklistDay::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-09-02',
    ]);
    $item = Item::factory()->create(['user_id' => $admin->getKey()]);
    $stock = \app(StockMovementService::class);
    $stock->createMovement([
        'mode' => 'incoming',
        'store_id' => $store->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 1]],
    ], $admin);
    $consumption = $stock->createMovement([
        'mode' => 'consumption',
        'store_id' => $store->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 1]],
    ], $admin);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 9)->create();
    $statementDay = StatementDay::factory()->for($statement, 'statement')->create([
        'date' => '2026-09-02',
        'cash' => 10,
        'total' => 10,
    ]);
    $version = StatementVersion::factory()->forStatement($statement)->byCreator($admin)->create();
    $card = NoticeboardCard::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'updated_by_user_id' => $admin->getKey(),
        'image_path' => null,
        'image_mime' => null,
    ]);
    $trashedCard = NoticeboardCard::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'updated_by_user_id' => $admin->getKey(),
        'image_path' => null,
        'image_mime' => null,
    ]);
    $trashedCard->delete();
    DB::table('stores')->where('id', $store->getKey())->update(['status' => 'inactive']);

    $workforce = new WorkforceManagementService();
    \expect(fn() => $workforce->createPreset($admin, $store, 'Late preset', '08:00', '09:00'))
        ->toThrow(HttpException::class)
        ->and(fn() => $workforce->createShareLink($admin, $store, 'Late share link'))
        ->toThrow(HttpException::class)
        ->and(fn() => $stock->createMovement([
            'mode' => 'incoming',
            'store_id' => $store->getKey(),
            'items' => [['item_id' => $item->getKey(), 'quantity' => 1]],
        ], $admin))->toThrow(ValidationException::class)
        ->and(fn() => $stock->reverseMovement($consumption, $admin, 'Late reversal'))
        ->toThrow(ValidationException::class);

    $checklists = new ChecklistService();
    \expect(fn() => $checklists->initializeStore($store))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn() => $checklists->replaceTemplateGroup(
            $store,
            ChecklistTemplateScopeEnum::Daily,
            null,
            ChecklistShiftEnum::Morning,
            ['Late task'],
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn() => $checklists->excuseDay($day, $admin, 'Late excuse', true))
        ->toThrow(InvalidArgumentException::class);

    $statements = new StatementService();
    \expect(fn() => $statements->updateDays($statement, [[
        'date' => $statementDay->getDate(),
        'cash' => 20,
        'card' => 0,
        'wolt' => 0,
        'bolt' => 0,
        'bolt_cash' => 0,
        'foodora' => 0,
    ]], $admin))->toThrow(HttpException::class)
        ->and(fn() => $statements->clear($statement, $admin))->toThrow(HttpException::class)
        ->and(fn() => $statements->restoreVersion($version, $admin))->toThrow(HttpException::class);

    \expect(fn() => \app(InventorySessionService::class)->createSession($admin, $store, [[
        'item_id' => $item->getKey(),
        'quantity' => 3,
    ]]))->toThrow(HttpException::class);

    $noticeboard = new NoticeboardCardService();
    $assertBlocked = static function (Closure $callback): void {
        try {
            $callback();
            \expect(false)->toBeTrue('Expected inactive-store mutation to fail.');
        } catch (Throwable $throwable) {
            \expect($throwable)->toBeInstanceOf(HttpException::class, $throwable->getMessage());
        }
    };
    $assertBlocked(fn() => $noticeboard->create($admin, $store, '<p>Late card</p>', 'information', 'yellow', 'medium', null, null));
    $assertBlocked(fn() => $noticeboard->update($card, $admin, '<p>Late update</p>', 'information', 'yellow', 'medium', null, null, false, 1));
    $assertBlocked(fn() => $noticeboard->trash($card));
    $assertBlocked(fn() => $noticeboard->restore($trashedCard));
    $assertBlocked(fn() => $noticeboard->forceDelete($trashedCard));

    \expect(DB::table('shift_presets')->where('store_id', $store->getKey())->count())->toBe(0)
        ->and(DB::table('shift_share_links')->where('store_id', $store->getKey())->count())->toBe(0)
        ->and(DB::table('stock_movements')->where('store_id', $store->getKey())->count())->toBe(2)
        ->and((float) DB::table('store_items')->where('store_id', $store->getKey())->where('item_id', $item->getKey())->value('quantity'))->toBe(0.0)
        ->and(DB::table('checklist_template_tasks')->where('store_id', $store->getKey())->count())->toBe(0)
        ->and($day->refresh()->getExcuseReason())->toBeNull()
        ->and($statementDay->refresh()->getCash())->toBe(10.0)
        ->and(StatementVersion::query()->where('statement_id', $statement->getKey())->count())->toBe(1)
        ->and(DB::table('inventory_sessions')->where('store_id', $store->getKey())->count())->toBe(0)
        ->and($card->fresh())->not->toBeNull()
        ->and(NoticeboardCard::query()->withTrashed()->whereKey($trashedCard->getKey())->exists())->toBeTrue();
});
