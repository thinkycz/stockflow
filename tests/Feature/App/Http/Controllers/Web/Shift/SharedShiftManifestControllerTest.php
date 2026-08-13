<?php

declare(strict_types=1);

use App\Models\ShiftShareLink;
use App\Models\Store;

\test('public shift calendar exposes a token-specific install manifest', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    ShiftShareLink::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'token' => 'employee-calendar-token',
    ]);

    $response = $this->get('/public/shifts/employee-calendar-token/manifest.webmanifest');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJson([
            'id' => '/public/shifts/employee-calendar-token',
            'name' => 'Teacha Shifts',
            'short_name' => 'Teacha Shifts',
            'start_url' => '/public/shifts/employee-calendar-token',
            'scope' => '/public/shifts/employee-calendar-token',
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
        ]);
});

\test('public shift manifest rejects an unknown or revoked token', function (): void {
    $this->get('/public/shifts/revoked-token/manifest.webmanifest')->assertNotFound();
});
