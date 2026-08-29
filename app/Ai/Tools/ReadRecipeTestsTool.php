<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\RecipeTestAttempt;
use App\Models\RecipeTestSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadRecipeTestsTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string { return 'read_recipe_tests'; }

    /**
     * Explain the recipe-test datasets available to the model.
     */
    public function description(): string { return 'Read recipe test sessions or attempts with workers, recipes, variants, scores, correctness, pass rates, and date filters.'; }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array { $filters = ['dataset' => $schema->string()->enum(['sessions', 'attempts'])->required(), 'worker_id' => $schema->integer(), 'recipe_id' => $schema->integer(), 'passed' => $schema->boolean(), 'date_from' => $schema->string(), 'date_to' => $schema->string()];

        return ['request' => $schema->anyOf([$schema->object(['operation' => $schema->string()->enum(['list'])->required(), ...$filters, 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['detail'])->required(), 'dataset' => $schema->string()->enum(['sessions', 'attempts'])->required(), 'id' => $schema->integer()->required()])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['summary'])->required(), ...$filters])->withoutAdditionalProperties()])->required()]; }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    protected function execute(array $request): array
    {
        $operation = Typer::parseNullableString($request['operation'] ?? null) ?? 'list';
        $dataset = $this->dataset($request);
        if ($dataset === 'sessions') {
            return $this->sessions($request, $operation);
        }
        if ($dataset === 'attempts') {
            return $this->attempts($request, $operation);
        }

        throw new InvalidArgumentException('Unknown recipe-test dataset.');
    }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string { return 'recipe_tests'; }

    /**
     * @param array<string, mixed> $request
     */
    protected function dataset(array $request): string { return Typer::parseNullableString($request['dataset'] ?? null) ?? 'sessions'; }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function sessions(array $request, string $operation): array
    {
        $query = RecipeTestSession::query()->where('user_id', $this->actor->resolveScopeUser()->getKey());
        $this->applyFilters($query, $request);

        $workerId = Typer::parseNullableInt($request['worker_id'] ?? null);
        if ($workerId !== null) {
            $query->where('worker_id', $workerId);
        }
        if ($operation === 'detail') {
            return $this->detailResult($request, 'sessions', $this->sessionRecord($query->findOrFail($this->requiredId($request)), true));
        }
        if ($operation === 'summary') {
            $models = $query->get();

            return $this->testSummary($request, 'sessions', \array_values($models->map(static fn(RecipeTestSession $model): array => ['score' => $model->getScore(), 'passed' => $model->isPassed()])->all()));
        }
        if ($operation !== 'list') {
            throw new InvalidArgumentException('Unknown recipe-test operation.');
        }

        return $this->paginateById($query, $request, 'sessions', $request, fn(RecipeTestSession $model): array => $this->sessionRecord($model, false));
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function attempts(array $request, string $operation): array
    {
        $query = RecipeTestAttempt::query()->where('user_id', $this->actor->resolveScopeUser()->getKey());
        $this->applyFilters($query, $request);
        $workerId = Typer::parseNullableInt($request['worker_id'] ?? null);
        if ($workerId !== null) {
            $query->where('worker_id', $workerId);
        }
        $recipeId = Typer::parseNullableInt($request['recipe_id'] ?? null);
        if ($recipeId !== null) {
            $query->where('recipe_id', $recipeId);
        }
        if ($operation === 'detail') {
            return $this->detailResult($request, 'attempts', $this->attemptRecord($query->findOrFail($this->requiredId($request)), true));
        }
        if ($operation === 'summary') {
            $models = $query->get();

            return $this->testSummary($request, 'attempts', \array_values($models->map(static fn(RecipeTestAttempt $model): array => ['score' => $model->getScore(), 'passed' => $model->isPassed()])->all()));
        }
        if ($operation !== 'list') {
            throw new InvalidArgumentException('Unknown recipe-test operation.');
        }

        return $this->paginateById($query, $request, 'attempts', $request, fn(RecipeTestAttempt $model): array => $this->attemptRecord($model, false));
    }

    /**
     * @template TModel of RecipeTestSession|RecipeTestAttempt
     *
     * @param Builder<TModel> $query
     * @param array<string, mixed> $request
     */
    private function applyFilters(Builder $query, array $request): void
    {
        if (\array_key_exists('passed', $request)) {
            $query->where('passed', (bool) $request['passed']);
        }
        $from = Typer::parseNullableString($request['date_from'] ?? null);
        if ($from !== null) {
            $query->where('created_at', '>=', $from . ' 00:00:00');
        }
        $to = Typer::parseNullableString($request['date_to'] ?? null);
        if ($to !== null) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }
    }

    /**
     * @param array<string, mixed> $request
     * @param list<array{score: int|null, passed: bool}> $rows
     *
     * @return array<string, mixed>
     */
    private function testSummary(array $request, string $dataset, array $rows): array
    {
        $scores = \array_values(\array_filter(\array_column($rows, 'score'), static fn(int|null $score): bool => $score !== null));
        $passed = \count(\array_filter($rows, static fn(array $row): bool => $row['passed']));

        return $this->summaryResult($request, $dataset, [
            'record_count' => \count($rows),
            'passed_count' => $passed,
            'pass_rate' => $rows === [] ? null : \round(($passed / \count($rows)) * 100, 2),
            'average_score' => $scores === [] ? null : \round(\array_sum($scores) / \count($scores), 2),
        ], $rows === [] ? 'NO_MATCHING_DATA' : null);
    }

    /**
     * @param array<string, mixed> $request
     */
    private function requiredId(array $request): int
    {
        $id = Typer::parseNullableInt($request['id'] ?? null);
        if ($id === null) {
            throw new InvalidArgumentException('A recipe-test identifier is required.');
        }

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionRecord(RecipeTestSession $session, bool $includeAttempts): array { $record = ['id' => $session->getKey(), 'worker_name' => $session->getWorkerName(), 'actor_user_id' => $session->getActorUserId(), 'score' => $session->getScore(), 'passed' => $session->isPassed(), 'submitted_at' => $session->getSubmittedAt()?->toJSON(), 'attempts_count' => $session->getAttempts()->count(), 'url' => Resolver::resolveUrlGenerator()->route('recipe-test-results.index')];
        if ($includeAttempts) { $record['attempts'] = $session->getAttempts()->map(fn(RecipeTestAttempt $attempt): array => $this->attemptRecord($attempt, true))->values()->all(); }

        return $record; }

    /**
     * @return array<string, mixed>
     */
    private function attemptRecord(RecipeTestAttempt $attempt, bool $includeAnswers): array { $record = ['id' => $attempt->getKey(), 'session_id' => $attempt->getSessionId(), 'worker_id' => $attempt->getWorkerId(), 'worker_name' => $attempt->getWorkerName(), 'recipe_id' => $attempt->getRecipeId(), 'recipe_name' => $attempt->getRecipeName(), 'variant_id' => $attempt->getVariantId(), 'variant_name' => $attempt->getVariantName(), 'score' => $attempt->getScore(), 'order_score' => $attempt->getOrderScore(), 'amount_score' => $attempt->getAmountScore(), 'passed' => $attempt->isPassed(), 'started_at' => $attempt->getStartedAt()->toJSON(), 'submitted_at' => $attempt->getSubmittedAt()?->toJSON()];
        if ($includeAnswers) { $record['correct_steps'] = $attempt->getCorrectStepsSnapshot();
            $record['submitted_tokens'] = $attempt->getSubmittedTokens();
            $record['submitted_amounts'] = $attempt->getSubmittedAmounts(); }

        return $record; }
}
