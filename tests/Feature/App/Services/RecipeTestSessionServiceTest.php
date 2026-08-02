<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\RecipeTestAttempt;
use App\Models\RecipeTestSession;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Services\RecipeCatalogService;
use App\Services\RecipeTestSessionService;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('session selects three distinct active recipes and snapshots their variants', function (): void {
    $owner = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->createOne(['user_id' => $owner->getKey()]);
    $actor = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $worker = Typer::assertInstance(Worker::factory()->createOne(['user_id' => $owner->getKey()]), Worker::class);
    (new RecipeCatalogService())->initialize($owner);

    $session = (new RecipeTestSessionService())->start($actor, $worker);

    \expect($session)->toBeInstanceOf(RecipeTestSession::class)
        ->and($session->getAttempts())->toHaveCount(3)
        ->and($session->getAttempts()->pluck('recipe_id')->unique()->count())->toBe(3)
        ->and($session->getAttempts()->pluck('session_position')->all())->toBe([1, 2, 3]);
});

\test('session rejects incomplete amounts atomically and requires three eligible recipes', function (): void {
    $owner = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->createOne(['user_id' => $owner->getKey()]);
    $actor = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $worker = Typer::assertInstance(Worker::factory()->createOne(['user_id' => $owner->getKey()]), Worker::class);
    (new RecipeCatalogService())->initialize($owner);
    Recipe::query()->whereNotIn('name', ['Classic Matcha Latte', 'Coconut Cloud', 'Strawberry Tea'])->update(['archived_at' => \now()]);
    $service = new RecipeTestSessionService();
    $session = $service->start($actor, $worker);
    $answers = [];
    foreach ($session->getAttempts() as $attempt) {
        $answers[] = [
            'attempt_id' => $attempt->getKey(),
            'tokens' => \array_column($attempt->getCorrectStepsSnapshot(), 'token'),
            'amounts' => [],
        ];
    }

    \expect(fn() => $service->submit($actor, $session, $answers))->toThrow(InvalidArgumentException::class)
        ->and($session->fresh()?->getSubmittedAt())->toBeNull()
        ->and(RecipeTestAttempt::query()->whereNotNull('submitted_at')->exists())->toBeFalse();

    Recipe::query()->where('name', 'Strawberry Tea')->update(['archived_at' => \now()]);
    \expect(fn() => $service->start($actor, $worker))->toThrow(InvalidArgumentException::class);
});

\test('session scores order and exact normalized gram and milliliter amounts atomically', function (): void {
    $owner = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->createOne(['user_id' => $owner->getKey()]);
    $actor = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $worker = Typer::assertInstance(Worker::factory()->createOne(['user_id' => $owner->getKey()]), Worker::class);
    (new RecipeCatalogService())->initialize($owner);
    Recipe::query()->whereNotIn('name', ['Classic Matcha Latte', 'Coconut Cloud', 'Strawberry Tea'])->update(['archived_at' => \now()]);
    $session = (new RecipeTestSessionService())->start($actor, $worker);

    $answers = [];
    foreach ($session->getAttempts() as $value) {
        $attempt = Typer::assertInstance($value, RecipeTestAttempt::class);
        $amounts = [];
        foreach (($attempt->getVariantSnapshot()['instructions'] ?? []) as $instruction) {
            if (\in_array(\mb_strtolower((string) ($instruction['unit'] ?? '')), ['g', 'ml'], true) && ($instruction['quantity_value'] ?? null) !== null) {
                $amounts[(string) $instruction['token']] = \str_replace('.', ',', (string) $instruction['quantity_value']);
            }
        }
        $answers[] = [
            'attempt_id' => $attempt->getKey(),
            'tokens' => \array_column($attempt->getCorrectStepsSnapshot(), 'token'),
            'amounts' => $amounts,
        ];
    }

    $submitted = (new RecipeTestSessionService())->submit($actor, $session, $answers);

    \expect($submitted->isPassed())->toBeTrue()->and($submitted->getScore())->toBe(100);
    foreach ($submitted->getAttempts() as $attempt) {
        \expect($attempt->getOrderScore())->toBe(100)->and($attempt->getAmountScore())->toBe(100);
    }
});
