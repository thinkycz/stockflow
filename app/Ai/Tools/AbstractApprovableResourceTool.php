<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\StockflowAssistant;
use App\Ai\AssistantActionAuditService;
use App\Enums\AssistantActionClassificationEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

abstract class AbstractApprovableResourceTool implements Approvable, AuditableAssistantTool, Tool
{
    use InteractsWithApprovals {
        shouldRequestApproval as private laravelShouldRequestApproval;
    }

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $preflightFailures = [];

    /**
     * Bind the tool to its authorized actor and persisted conversation.
     */
    public function __construct(
        protected readonly User $actor,
        protected readonly string $conversationId,
    ) {}

    /**
     * Execute one approved native resource action exactly once.
     */
    final public function handle(Request $request): string
    {
        $arguments = Typer::assertStringKeyArray($request->all());
        $toolCallId = Typer::assertString($request->toolCallId());
        $audit = Resolver::resolve(AssistantActionAuditService::class);
        $agent = new StockflowAssistant($this->actor, $this->conversationId);

        if (\array_key_exists($toolCallId, $this->preflightFailures)) {
            $result = $this->preflightFailures[$toolCallId];
            $audit->invalidProposal($agent, $this, $arguments, $toolCallId, $result);

            return $this->encode($result);
        }

        $existing = $audit->start($agent, $this, $arguments, $toolCallId, $request->toolInvocationId());

        if ($existing !== null) {
            return $this->encode($existing);
        }

        $startedAt = \microtime(true);

        try {
            $result = $this->execute($arguments);
            $audit->succeeded($this->conversationId, $toolCallId, $result, $startedAt);
        } catch (Throwable $exception) {
            $audit->failed($this->conversationId, $toolCallId, $exception, $startedAt);

            throw $exception;
        }

        return $this->encode($result);
    }

    /**
     * Convert expected proposal failures into a bounded repairable tool result.
     */
    final public function shouldRequestApproval(Request $request): Approval|null
    {
        try {
            return $this->laravelShouldRequestApproval($request);
        } catch (Throwable $exception) {
            $toolCallId = Typer::assertString($request->toolCallId());
            $this->preflightFailures[$toolCallId] = [
                'operation' => $this->name(),
                'status' => 'failed',
                'error' => $this->preflightMessage($exception),
                'repairable' => true,
            ];

            return null;
        }
    }

    /**
     * All resource writers mutate application state unless overridden per action.
     *
     * @param array<string, mixed> $arguments
     */
    public function auditClassification(array $arguments): AssistantActionClassificationEnum
    {
        return AssistantActionClassificationEnum::MUTATION;
    }

    /**
     * Resolve the stable action from the typed request branch.
     *
     * @param array<string, mixed> $arguments
     */
    final public function auditOperation(array $arguments): string
    {
        return $this->action($arguments);
    }

    /**
     * Resolve paths that may change in an edited approval.
     *
     * @param array<string, mixed> $arguments
     *
     * @return list<string>
     */
    abstract public function safeEditablePaths(array $arguments): array;

    /**
     * Resolve an exact side-effect-free native approval preview.
     */
    final protected function needsApproval(Request $request): Approval
    {
        return Approval::required($this->encode($this->preview(Typer::assertStringKeyArray($request->all()))));
    }

    /**
     * Build an exact preview without side effects.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    abstract protected function preview(array $arguments): array;

    /**
     * Execute the already approved action through the human-facing service.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    abstract protected function execute(array $arguments): array;

    /**
     * Extract a typed native request object.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    final protected function request(array $arguments): array
    {
        return Typer::assertStringKeyArray(Typer::assertArray($arguments['request'] ?? null));
    }

    /**
     * Extract the selected resource action.
     *
     * @param array<string, mixed> $arguments
     */
    final protected function action(array $arguments): string
    {
        return Typer::assertString($this->request($arguments)['action'] ?? null);
    }

    /**
     * Extract typed editable values when the branch defines them.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    final protected function values(array $arguments): array
    {
        $values = $this->request($arguments)['values'] ?? [];

        if (!\is_array($values)) {
            throw new InvalidArgumentException('Tool values must be an object.');
        }

        return Typer::assertStringKeyArray($values);
    }

    /**
     * Encode a bounded tool or approval payload.
     *
     * @param array<string, mixed> $value
     */
    final protected function encode(array $value): string
    {
        return \json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * Convert an expected preflight exception into a bounded model-facing message.
     */
    private function preflightMessage(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return \mb_substr(\implode(' ', $exception->validator->errors()->all()), 0, 1000);
        }

        if ($exception instanceof HttpExceptionInterface) {
            return 'The selected store or target does not exist or is not accessible.';
        }

        if ($exception instanceof ModelNotFoundException) {
            return 'The selected store or target does not exist or is not accessible.';
        }

        if ($exception instanceof InvalidArgumentException) {
            return \mb_substr($exception->getMessage(), 0, 1000);
        }

        return 'The proposal could not be validated safely. Check the action fields and try again.';
    }
}
