<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Resolver;

class ShiftShareController
{
    /**
     * Create or return the active store's public shift calendar link.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $admin = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $admin);

        if (!$store instanceof Store) {
            \abort(404);
        }

        $token = $store->getShiftShareToken();

        if ($token === null) {
            $token = Str::random(64);
            $store->update(['shift_share_token' => $token]);
        }

        return new JsonResponse([
            'url' => Resolver::resolveUrlGenerator()->to('public/shifts/' . $token),
        ]);
    }
}
