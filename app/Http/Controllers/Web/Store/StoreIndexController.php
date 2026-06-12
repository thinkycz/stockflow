<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Store;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\StoreValidity;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;
use Thinkycz\LaravelCore\Support\Typer;

class StoreIndexController
{
    use ValidatesWebRequests;

    /**
     * Default page size.
     */
    public const int TAKE = 20;

    /**
     * Show the stores list with per-store totals.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $storeValidity = StoreValidity::inject($user->getKey());

        $validated = $this->validateRequest($request, [
            'search' => $storeValidity->search()->nullable()->toArray(),
        ]);

        $search = $validated->assertNullableString('search') ?? '';

        $baseQuery = Store::query();
        Store::scopeForUser($baseQuery, $user);
        $query = Store::querySelect($baseQuery)->orderBy('name');

        if ($search !== '') {
            Store::scopeSearch($query, $search);
        }

        $stores = $query->get()->map(function (Store $store): array {
            /** @var stdClass|null $metrics */
            $metrics = DB::table('stock_movements')
                ->where(function (QueryBuilder $q) use ($store): void {
                    $q->where('store_id', $store->getKey())
                        ->orWhere('source_store_id', $store->getKey());
                })
                ->selectRaw('
                    SUM(CASE WHEN type = \'incoming\' THEN total_quantity ELSE 0 END) as total_received_quantity,
                    SUM(CASE WHEN type = \'incoming\' THEN total_value ELSE 0 END) as total_received_value,
                    SUM(CASE WHEN type = \'outgoing\' THEN total_value ELSE 0 END) as total_outgoing_value,
                    COUNT(*) as movimientos_count
                ')
                ->first();

            return [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'address' => $store->getAddress(),
                'status' => $store->getStatus()->value,
                'is_warehouse' => $store->isWarehouse(),
                'movements_count' => Typer::assertNullableInt($metrics->movements_count ?? null),
                'total_received_quantity' => Typer::parseInt($metrics->total_received_quantity ?? null),
                'total_received_value' => Typer::parseFloat($metrics->total_received_value ?? null),
                'total_outgoing_value' => Typer::parseFloat($metrics->total_outgoing_value ?? null),
            ];
        })->all();

        return Inertia::render('stores/Index', [
            'stores' => $stores,
            'search' => $search,
        ]);
    }
}
