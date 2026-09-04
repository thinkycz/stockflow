<?php

declare(strict_types=1);

use App\Domain\Recipes\RecipeCatalogService;
use App\Models\RecipeTestSession;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('limited account starts and atomically submits a three recipe session', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $worker = Typer::assertInstance(Worker::factory()->createOne(['user_id' => $admin->getKey()]), Worker::class);
    (new RecipeCatalogService())->initialize($admin);

    $this->be($limited, 'users')->post('/recipe-test-sessions', ['worker_id' => $worker->getKey()])->assertRedirect();

    $session = Typer::assertInstance(RecipeTestSession::query()->with('attempts')->firstOrFail(), RecipeTestSession::class);
    $response = $this->be($limited, 'users')->get('/recipe-test-sessions/' . $session->getKey(), $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'recipes/TestSession')->assertJsonCount(3, 'props.recipes');
    $payload = Typer::assertStringKeyArray(Typer::assertArray($response->json('props')));
    $recipes = Typer::assertArray($payload['recipes'] ?? null);
    $answers = [];
    foreach ($session->getAttempts() as $attempt) {
        $amounts = [];
        foreach (Typer::assertArray($attempt->getVariantSnapshot()['instructions'] ?? []) as $value) {
            $instruction = Typer::assertStringKeyArray(Typer::assertArray($value));
            if (\in_array(\mb_strtolower(Typer::assertString($instruction['unit'] ?? '')), ['g', 'ml'], true) && ($instruction['quantity_value'] ?? null) !== null) {
                $amounts[Typer::assertString($instruction['token'] ?? null)] = (string) Typer::assertScalar($instruction['quantity_value']);
            }
        }
        $answers[] = ['attempt_id' => $attempt->getKey(), 'tokens' => \array_column($attempt->getCorrectStepsSnapshot(), 'token'), 'amounts' => $amounts];
    }

    \expect(\json_encode($recipes, \JSON_THROW_ON_ERROR))->not->toContain('quantity_value');
    $this->be($limited, 'users')->put('/recipe-test-sessions/' . $session->getKey(), ['answers' => $answers], $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('props.result.passed', true)->assertJsonPath('props.result.score', 100);
    $this->be($limited, 'users')->put('/recipe-test-sessions/' . $session->getKey(), ['answers' => $answers], $this->inertiaHeaders())
        ->assertConflict();
});

\test('session ownership and roles are enforced and legacy single starts are disabled', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $worker = Typer::assertInstance(Worker::factory()->createOne(['user_id' => $admin->getKey()]), Worker::class);
    (new RecipeCatalogService())->initialize($admin);

    $this->be($admin, 'users')->post('/recipe-test-sessions', ['worker_id' => $worker->getKey()])->assertForbidden();
    $this->be($limited, 'users')->post('/recipe-tests', [])->assertNotFound();
    $this->be($limited, 'users')->post('/recipe-test-sessions', ['worker_id' => $worker->getKey()])->assertRedirect();
    $session = Typer::assertInstance(RecipeTestSession::query()->firstOrFail(), RecipeTestSession::class);
    $foreign = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $this->be($foreign, 'users')->get('/recipe-test-sessions/' . $session->getKey())->assertNotFound();
    $this->be($admin, 'users')->get('/recipe-test-sessions/' . $session->getKey(), $this->inertiaHeaders())->assertOk();
});
