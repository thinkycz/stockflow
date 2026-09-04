<?php

declare(strict_types=1);

use App\Domain\Recipes\RecipeCatalogService;
use App\Domain\Recipes\RecipeTestService;
use App\Domain\Recipes\RecipeTestSessionService;
use App\Models\Recipe;
use App\Models\RecipeTestAttempt;
use App\Models\RecipeTestSession;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Notifications\OperationalActivitySlackNotification;
use Database\Factories\UserFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Support\Config;
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
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $owner = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $owner->update(['company_slack_channel' => '#company-operations']);
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

    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 1);
    Notification::assertSentOnDemand(
        OperationalActivitySlackNotification::class,
        static function (OperationalActivitySlackNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            $payload = \json_encode($notification->toSlack($notifiable)->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

            return $notifiable->routeNotificationFor('slack') === '#company-operations' &&
                \str_contains($payload, 'Receptový test splněn') &&
                !\str_contains($payload, 'correct_steps');
        },
    );
});

\test('standalone legacy recipe attempt sends one failed company notification', function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $owner = Typer::assertInstance(UserFactory::new()->admin()->createOne(['company_slack_channel' => '#company-operations']), User::class);
    $store = Store::factory()->createOne(['user_id' => $owner->getKey()]);
    $actor = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $worker = Typer::assertInstance(Worker::factory()->createOne(['user_id' => $owner->getKey()]), Worker::class);
    (new RecipeCatalogService())->initialize($owner);
    $recipe = Typer::assertInstance(Recipe::query()->where('user_id', $owner->getKey())->firstOrFail(), Recipe::class);
    $service = new RecipeTestService();
    $attempt = $service->start($actor, $worker, $recipe);
    $tokens = \array_reverse(\array_column($attempt->getCorrectStepsSnapshot(), 'token'));

    $submitted = $service->submit($actor, $attempt, $tokens);

    \expect($submitted->isPassed())->toBeFalse();
    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 1);
});

\test('legacy service cannot submit a session child even when called without an HTTP controller', function (): void {
    Notification::fake();
    [$owner] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $owner->getKey()]);
    $actor = UserFactory::new()->limited($store)->createOne();
    $worker = Worker::factory()->create(['user_id' => $owner->getKey()]);
    (new RecipeCatalogService())->initialize($owner);
    $session = (new RecipeTestSessionService())->start($actor, $worker);
    $attempt = $session->getAttempts()->firstOrFail();
    $before = $attempt->getRawOriginal();

    \expect(fn() => (new RecipeTestService())->submit($actor, $attempt, \array_column($attempt->getCorrectStepsSnapshot(), 'token')))
        ->toThrow(InvalidArgumentException::class)
        ->and($attempt->fresh()?->getRawOriginal())->toBe($before)
        ->and($session->fresh()?->getSubmittedAt())->toBeNull();
    Notification::assertNothingSent();
});

\test('parent submission rejects a previously submitted child without changing any sibling or result', function (): void {
    Notification::fake();
    [$owner] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $owner->getKey()]);
    $actor = UserFactory::new()->limited($store)->createOne();
    $worker = Worker::factory()->create(['user_id' => $owner->getKey()]);
    (new RecipeCatalogService())->initialize($owner);
    $service = new RecipeTestSessionService();
    $session = $service->start($actor, $worker);
    // Put the corrupt child last so a sequential implementation must roll back earlier siblings.
    $session->getAttempts()->last()->update(['submitted_at' => \now(), 'score' => 17, 'passed' => false]);
    $attempts = $session->attempts()->orderBy('session_position')->get();
    $before = $attempts->map(static fn(RecipeTestAttempt $attempt): array => $attempt->getRawOriginal())->all();
    $sessionBefore = $session->fresh()?->getRawOriginal();
    $answers = [];
    foreach ($attempts as $attempt) {
        $amounts = [];
        foreach (($attempt->getVariantSnapshot()['instructions'] ?? []) as $instruction) {
            if (\in_array(\mb_strtolower((string) ($instruction['unit'] ?? '')), ['g', 'ml'], true) && ($instruction['quantity_value'] ?? null) !== null) {
                $amounts[(string) $instruction['token']] = (string) $instruction['quantity_value'];
            }
        }
        $answers[] = ['attempt_id' => $attempt->getKey(), 'tokens' => \array_column($attempt->getCorrectStepsSnapshot(), 'token'), 'amounts' => $amounts];
    }

    \expect(fn() => $service->submit($actor, $session, $answers))->toThrow(RuntimeException::class, 'Recipe test session contains a submitted attempt.')
        ->and($session->fresh()?->getRawOriginal())->toBe($sessionBefore)
        ->and($session->attempts()->orderBy('session_position')->get()->map(static fn(RecipeTestAttempt $attempt): array => $attempt->getRawOriginal())->all())->toBe($before);
    Notification::assertNothingSent();
});
