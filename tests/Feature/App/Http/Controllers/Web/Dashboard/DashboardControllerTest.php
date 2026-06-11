<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest is redirected from dashboard to login', function (): void {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

\test('authenticated user can view dashboard with metrics', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->get('/dashboard', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'Dashboard');
    $response->assertJsonPath('props.auth.user.email', $user->getEmail());
    $response->assertJsonStructure([
        'props' => [
            'metrics' => [
                'total_inventory_value',
                'total_items',
                'low_stock_items',
                'today_movements',
            ],
            'recent_movements',
        ],
    ]);
});
