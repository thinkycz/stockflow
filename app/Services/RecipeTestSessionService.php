<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationalActivityTypeEnum;
use App\Models\Recipe;
use App\Models\RecipeTestAttempt;
use App\Models\RecipeTestSession;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeTestSessionService
{
    public const int RECIPE_COUNT = 3;

    /**
     * Start one session with three distinct active recipes.
     */
    public function start(User $actor, Worker $worker): RecipeTestSession
    {
        return DB::transaction(static function () use ($actor, $worker): RecipeTestSession {
            $worker = Typer::assertInstance(
                Worker::query()->whereKey($worker->getKey())->lockForUpdate()->firstOrFail(),
                Worker::class,
            );
            $owner = $actor->resolveScopeUser();
            if ($actor->isAdmin() || $worker->getUserId() !== $owner->getKey() || $worker->isArchived()) {
                throw new InvalidArgumentException('Recipe test is not available.');
            }

            $recipes = Recipe::query()->where('user_id', $owner->getKey())->whereNull('archived_at')
                ->whereHas('variants', static function (Builder $query): void {
                    $query->has('instructions', '>=', 2);
                })->get()->shuffle()->take(self::RECIPE_COUNT)->values();
            if ($recipes->count() !== self::RECIPE_COUNT) {
                throw new InvalidArgumentException('At least three testable recipes are required.');
            }

            $session = Typer::assertInstance(RecipeTestSession::query()->create([
                'user_id' => $owner->getKey(), 'worker_id' => $worker->getKey(), 'actor_user_id' => $actor->getKey(),
                'worker_name' => $worker->getFullName(), 'actor_name' => $actor->getEmail(),
                'score' => null, 'passed' => null, 'started_at' => Carbon::now(), 'submitted_at' => null,
            ]), RecipeTestSession::class);
            foreach ($recipes as $position => $value) {
                $recipe = Typer::assertInstance($value, Recipe::class);
                $attempt = (new RecipeTestService())->start($actor, $worker, $recipe);
                $attempt->setAttribute('recipe_test_session_id', $session->getKey());
                $attempt->setAttribute('session_position', $position + 1);
                $attempt->save();
            }

            return Typer::assertInstance($session->load('attempts'), RecipeTestSession::class);
        });
    }

    /**
     * Atomically submit all three child answers.
     *
     * @param list<array{attempt_id: int, tokens: list<string>, amounts: array<string, string>}> $answers
     */
    public function submit(User $actor, RecipeTestSession $session, array $answers): RecipeTestSession
    {
        return DB::transaction(function () use ($actor, $session, $answers): RecipeTestSession {
            $locked = Typer::assertInstance(RecipeTestSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail(), RecipeTestSession::class);
            if ($locked->getActorUserId() !== $actor->getKey() || $locked->getUserId() !== $actor->resolveScopeUser()->getKey()) {
                throw new InvalidArgumentException('Recipe test session does not belong to this account.');
            }
            if ($locked->getSubmittedAt() !== null) {
                throw new RuntimeException('Recipe test session was already submitted.');
            }

            $attempts = $locked->attempts()->lockForUpdate()->get();
            if ($attempts->count() !== self::RECIPE_COUNT || \count($answers) !== self::RECIPE_COUNT) {
                throw new InvalidArgumentException('All three recipe answers are required.');
            }
            $answersByAttempt = [];
            foreach ($answers as $answer) {
                $attemptId = Typer::assertInt($answer['attempt_id']);
                if (isset($answersByAttempt[$attemptId])) {
                    throw new InvalidArgumentException('Duplicate recipe answer.');
                }
                $answersByAttempt[$attemptId] = $answer;
            }

            $totalChecks = 0;
            $correctChecks = 0;
            $allPassed = true;
            $submittedAt = Carbon::now();
            foreach ($attempts as $value) {
                $attempt = Typer::assertInstance($value, RecipeTestAttempt::class);
                $answer = $answersByAttempt[$attempt->getKey()] ?? null;
                if (!\is_array($answer)) {
                    throw new InvalidArgumentException('Recipe answer does not belong to this session.');
                }
                $tokens = \array_values(Typer::assertStringArray($answer['tokens']));
                $correctTokens = \array_column($attempt->getCorrectStepsSnapshot(), 'token');
                $expectedTokens = $correctTokens;
                $receivedTokens = $tokens;
                \sort($expectedTokens);
                \sort($receivedTokens);
                if ($expectedTokens !== $receivedTokens || \count($tokens) !== \count(\array_unique($tokens))) {
                    throw new InvalidArgumentException('Submitted recipe steps are invalid.');
                }

                $correctPositions = 0;
                foreach ($correctTokens as $position => $token) {
                    if (($tokens[$position] ?? null) === $token) {
                        ++$correctPositions;
                    }
                }
                $requiredAmounts = $this->requiredAmounts($attempt);
                $submittedAmounts = [];
                foreach ($answer['amounts'] as $token => $amount) {
                    $submittedAmounts[$token] = $this->normalizeAmount($amount);
                }
                $requiredKeys = \array_keys($requiredAmounts);
                $submittedKeys = \array_keys($submittedAmounts);
                \sort($requiredKeys);
                \sort($submittedKeys);
                if ($requiredKeys !== $submittedKeys) {
                    throw new InvalidArgumentException('Submitted recipe amounts are incomplete.');
                }
                $correctAmounts = 0;
                foreach ($requiredAmounts as $token => $amount) {
                    if (($submittedAmounts[$token] ?? null) === $amount) {
                        ++$correctAmounts;
                    }
                }

                $orderTotal = \count($correctTokens);
                $amountTotal = \count($requiredAmounts);
                $orderScore = (int) \round(($correctPositions / $orderTotal) * 100);
                $amountScore = $amountTotal === 0 ? 100 : (int) \round(($correctAmounts / $amountTotal) * 100);
                $attemptChecks = $orderTotal + $amountTotal;
                $attemptCorrect = $correctPositions + $correctAmounts;
                $score = (int) \round(($attemptCorrect / $attemptChecks) * 100);
                $passed = $orderScore === 100 && $amountScore === 100;
                $attempt->setAttribute('submitted_tokens', $tokens);
                $attempt->setAttribute('submitted_amounts', $submittedAmounts);
                $attempt->setAttribute('score', $score);
                $attempt->setAttribute('order_score', $orderScore);
                $attempt->setAttribute('amount_score', $amountScore);
                $attempt->setAttribute('passed', $passed);
                $attempt->setAttribute('submitted_at', $submittedAt);
                $attempt->save();
                $totalChecks += $attemptChecks;
                $correctChecks += $attemptCorrect;
                $allPassed = $allPassed && $passed;
            }
            if (\count($answersByAttempt) !== $attempts->count()) {
                throw new InvalidArgumentException('Unknown recipe answer.');
            }

            $locked->setAttribute('score', (int) \round(($correctChecks / $totalChecks) * 100));
            $locked->setAttribute('passed', $allPassed);
            $locked->setAttribute('submitted_at', $submittedAt);
            $locked->save();

            OperationalActivityService::dispatchToCompany(
                $allPassed ? OperationalActivityTypeEnum::RECIPE_TEST_PASSED : OperationalActivityTypeEnum::RECIPE_TEST_FAILED,
                $actor,
                $submittedAt->toIso8601String(),
                Resolver::resolveUrlGenerator()->route('recipe-test-sessions.show', ['session' => $locked->getKey()]),
                [
                    'Slack worker' => $locked->getWorkerName(),
                    'Slack recipe test score' => (string) $locked->getScore() . ' %',
                    'Slack recipe test result' => $allPassed ? 'Úspěšný' : 'Neúspěšný',
                    'Slack recipe count' => (string) self::RECIPE_COUNT,
                ],
            );

            return Typer::assertInstance($locked->load('attempts'), RecipeTestSession::class);
        });
    }

    /**
     * @return array<string, string>
     */
    private function requiredAmounts(RecipeTestAttempt $attempt): array
    {
        $required = [];
        foreach (Typer::assertArray($attempt->getVariantSnapshot()['instructions'] ?? []) as $value) {
            $instruction = Typer::assertStringKeyArray(Typer::assertArray($value));
            $unit = \mb_strtolower(Typer::assertString($instruction['unit'] ?? ''));
            $quantity = $instruction['quantity_value'] ?? null;
            if (\in_array($unit, ['g', 'ml'], true) && $quantity !== null) {
                $required[Typer::assertString($instruction['token'] ?? null)] = $this->normalizeAmount((string) Typer::assertScalar($quantity));
            }
        }

        return $required;
    }

    /**
     * Normalize a non-negative decimal with up to three places.
     */
    private function normalizeAmount(string $amount): string
    {
        $normalized = \str_replace(',', '.', \mb_trim($amount));
        if (\preg_match('/^\\d+(?:\\.\\d{1,3})?$/', $normalized) !== 1) {
            throw new InvalidArgumentException('Recipe amount must be a valid decimal.');
        }
        [$whole, $fraction] = \array_pad(\explode('.', $normalized, 2), 2, '');

        return (string) ((int) $whole) . '.' . \mb_str_pad($fraction, 3, '0');
    }
}
