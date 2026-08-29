<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Recipe;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\RecipeValidity;
use App\Models\RecipeTestAttempt;
use App\Models\RecipeTestSession;
use App\Models\User;
use App\Models\Worker;
use App\Services\RecipeTestSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeTestSessionController
{
    use ValidatesWebRequests;

    /**
     * Start a three-recipe test for a selected company worker.
     */
    public function store(Request $request): RedirectResponse
    {
        $actor = User::mustAuth();
        if ($actor->isAdmin()) {
            \abort(403);
        }
        $owner = $actor->resolveScopeUser();
        $validity = RecipeValidity::inject($owner->getKey());
        $validated = $this->validateRequest($request, ['worker_id' => $validity->workerId()->required()->toArray()]);
        $worker = Typer::assertInstance(Worker::query()->where('user_id', $owner->getKey())->whereKey($validated->assertInt('worker_id'))->firstOrFail(), Worker::class);
        try {
            $session = (new RecipeTestSessionService())->start($actor, $worker);
        } catch (InvalidArgumentException $exception) {
            Thrower::default()->message('worker_id', $exception->getMessage())->throw();
        }

        return Resolver::resolveRedirector()->route('recipe-test-sessions.show', $session->getKey());
    }

    /**
     * Display an owned session without revealing answers before submission.
     */
    public function show(RecipeTestSession $session): Response
    {
        $actor = User::mustAuth();
        $this->authorize($actor, $session, false);
        $session->load('attempts');

        return Inertia::render('recipes/TestSession', $this->payload($session));
    }

    /**
     * Atomically submit all answers in the session.
     */
    public function update(Request $request, RecipeTestSession $session): Response
    {
        $actor = User::mustAuth();
        $this->authorize($actor, $session, true);
        $validity = RecipeValidity::inject($actor->resolveScopeUser()->getKey());
        $validated = $this->validateRequest($request, [
            'answers' => $validity->sessionAnswers()->required()->toArray(),
            'answers.*.attempt_id' => $validity->attemptId()->required()->toArray(),
            'answers.*.tokens' => $validity->tokens()->required()->toArray(),
            'answers.*.tokens.*' => $validity->token()->required()->toArray(),
            'answers.*.amounts' => $validity->amountAnswers()->required()->toArray(),
            'answers.*.amounts.*' => $validity->amountAnswer()->required()->toArray(),
        ]);
        $answers = [];
        foreach ($validated->assertArray('answers') as $value) {
            $answer = Typer::assertStringKeyArray(Typer::assertArray($value));
            $amounts = [];
            foreach (Typer::assertArray($answer['amounts'] ?? null) as $token => $amount) {
                $amounts[Typer::assertString($token)] = Typer::assertString($amount);
            }
            $answers[] = [
                'attempt_id' => Typer::assertInt($answer['attempt_id'] ?? null),
                'tokens' => \array_values(Typer::assertStringArray(Typer::assertArray($answer['tokens'] ?? null))),
                'amounts' => $amounts,
            ];
        }
        try {
            $submitted = (new RecipeTestSessionService())->submit($actor, $session, $answers);
        } catch (RuntimeException) {
            \abort(409);
        } catch (InvalidArgumentException $exception) {
            Thrower::default()->message('answers', $exception->getMessage())->throw();
        }

        return Inertia::render('recipes/TestSession', $this->payload($submitted));
    }

    /**
     * Ensure only the creating limited account or owning admin can open a session.
     */
    private function authorize(User $actor, RecipeTestSession $session, bool $submitting): void
    {
        if ($session->getUserId() !== $actor->resolveScopeUser()->getKey()) {
            \abort(404);
        }
        if ($submitting && $actor->isAdmin()) {
            \abort(403);
        }
        if (!$actor->isAdmin() && $session->getActorUserId() !== $actor->getKey()) {
            \abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(RecipeTestSession $session): array
    {
        $submitted = $session->getSubmittedAt() !== null;
        $recipes = [];
        foreach ($session->getAttempts() as $attempt) {
            $recipes[] = $this->attemptPayload($attempt, $submitted);
        }

        return [
            'session' => [
                'id' => $session->getKey(), 'worker_name' => $session->getWorkerName(),
                'submitted' => $submitted,
            ],
            'recipes' => $recipes,
            'result' => $submitted ? [
                'score' => $session->getScore(), 'passed' => $session->isPassed(),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attemptPayload(RecipeTestAttempt $attempt, bool $submitted): array
    {
        $snapshot = Typer::assertStringKeyArray($attempt->getVariantSnapshot() ?? []);
        $byToken = [];
        foreach (Typer::assertArray($snapshot['instructions'] ?? []) as $value) {
            $instruction = Typer::assertStringKeyArray(Typer::assertArray($value));
            $token = Typer::assertString($instruction['token'] ?? null);
            $unit = \mb_strtolower(Typer::assertString($instruction['unit'] ?? ''));
            $requiresAmount = \in_array($unit, ['g', 'ml'], true) && ($instruction['quantity_value'] ?? null) !== null;
            $row = [
                'token' => $token,
                'instruction_id' => isset($instruction['instruction_id'])
                    ? Typer::parseInt($instruction['instruction_id'])
                    : null,
                'type' => Typer::assertString($instruction['type'] ?? 'action'),
                'text' => $requiresAmount ? null : Typer::assertString($instruction['text'] ?? ''),
                'action_key' => Typer::assertString($instruction['action_key'] ?? 'other'),
                'icon_group' => Typer::assertString($instruction['icon_group'] ?? 'neutral'),
                'requires_amount' => $requiresAmount,
                'unit' => $requiresAmount ? $unit : null,
                'ingredient_name' => $requiresAmount ? Typer::assertNullableString($instruction['ingredient_name'] ?? null) : null,
                'target' => $requiresAmount ? Typer::assertNullableString($instruction['target'] ?? null) : null,
            ];
            if ($submitted) {
                $row['correct_text'] = Typer::assertString($instruction['text'] ?? '');
                $row['correct_amount'] = $requiresAmount ? (string) Typer::assertScalar($instruction['quantity_value'] ?? null) : null;
                $row['submitted_amount'] = $requiresAmount ? ($attempt->getSubmittedAmounts()[$token] ?? null) : null;
            }
            $byToken[$token] = $row;
        }
        $instructions = [];
        foreach ($attempt->getPresentedTokens() as $token) {
            $instructions[] = Typer::assertStringKeyArray(Typer::assertArray($byToken[$token] ?? null));
        }

        return [
            'attempt_id' => $attempt->getKey(), 'position' => $attempt->getSessionPosition(),
            'recipe_name' => $attempt->getRecipeName(), 'variant_name' => $attempt->getVariantName(),
            'instructions' => $instructions,
            'submitted_tokens' => $submitted ? $attempt->getSubmittedTokens() : null,
            'correct_tokens' => $submitted ? \array_column($attempt->getCorrectStepsSnapshot(), 'token') : null,
            'result' => $submitted ? [
                'score' => $attempt->getScore(), 'passed' => $attempt->isPassed(),
                'order_score' => $attempt->getOrderScore(), 'amount_score' => $attempt->getAmountScore(),
            ] : null,
        ];
    }
}
