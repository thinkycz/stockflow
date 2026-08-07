<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Thinkycz\LaravelCore\Support\Resolver;

class SharedShiftManifestController
{
    /**
     * Return an install manifest scoped to one public shift calendar.
     */
    public function __invoke(string $token): JsonResponse
    {
        $storeQuery = Store::query();
        Store::scopeForShiftShareToken($storeQuery, $token);

        if (!$storeQuery->exists()) {
            \abort(404);
        }

        $startUrl = Resolver::resolveUrlGenerator()->route('public-shifts.index', ['token' => $token], false);

        return new JsonResponse([
            'id' => $startUrl,
            'name' => 'Teacha Shifts',
            'short_name' => 'Teacha Shifts',
            'start_url' => $startUrl,
            'scope' => $startUrl,
            'display' => 'standalone',
            'theme_color' => '#344c28',
            'background_color' => '#f8fafc',
            'icons' => [
                [
                    'src' => '/pwa-192x192.png?v=2',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/pwa-512x512.png?v=2',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/pwa-maskable-512x512.png?v=2',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ], headers: ['Content-Type' => 'application/manifest+json']);
    }
}
