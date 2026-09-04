<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Store;

use App\Domain\Inventory\InventoryReadService;
use App\Domain\Stores\StoreDetailService;
use App\Models\Store;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;

class StoreShowController
{
    /**
     * Show the store detail with bounded movement history.
     */
    public function __invoke(Store $store): Response
    {
        return Inertia::render('stores/Show', (new StoreDetailService())->build($store, Resolver::resolve(InventoryReadService::class)));
    }
}
