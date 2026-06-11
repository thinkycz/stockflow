<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Thinkycz\LaravelCore\Support\Typer;

\pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Architecture', 'Feature');

/**
 * @return array{0: User, 1: Store}
 */
function createIsolatedUserWithWarehouse(): array
{
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $warehouse = Store::factory()->warehouse()->create([
        'user_id' => $user->getKey(),
    ]);

    return [$user, $warehouse];
}

/**
 * Assert that the response carries an Inertia flash message
 * (success or error) under the given key.
 *
 * Works for both redirect responses (via the Inertia re-flash
 * mechanism) and 200 OK Inertia render responses (via the
 * `flash` prop the HandleInertiaRequests middleware injects).
 */
function assertInertiaFlash(TestResponse $response, string $key, mixed $message): void
{
    try {
        $response->assertInertiaFlash($key, $message);

        return;
    } catch (Throwable) {
        // Fall through to the props check for 200 OK render responses.
    }

    $flashed = $response->json('props.flash.' . $key);

    \expect($flashed)->toBe($message);
}
