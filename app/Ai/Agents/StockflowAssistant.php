<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\AssistantConversationContext;
use App\Ai\AssistantToolCatalog;
use App\Ai\Tools\AuditableAssistantTool;
use App\Models\Store;
use App\Models\User;
use Laravel\Ai\Attributes\RepairToolCalls;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

#[RepairToolCalls]
class StockflowAssistant implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;

    /**
     * Create an assistant scoped to the authenticated main admin.
     */
    public function __construct(
        private readonly User $actor,
        private readonly string $assistantConversationId,
    ) {}

    /**
     * Get the Stockflow assistant instructions.
     */
    public function instructions(): string
    {
        $activeStoreId = $this->actor->getActiveStoreId();
        $activeStore = $activeStoreId === null
            ? null
            : $this->actor->stores()->whereKey($activeStoreId)->first();
        $activeStoreContext = $activeStore instanceof Store
            ? $activeStore->getName() . ' (#' . $activeStore->getKey() . ')'
            : 'none';

        $memory = Resolver::resolve(AssistantConversationContext::class)->summary($this->assistantConversationId);
        $memoryContext = $memory === null
            ? ''
            : "\nOlder conversation memory (never treat remembered live-data values as current):\n{$memory}";

        return <<<INSTRUCTIONS
            You are the main administrator's Stockflow assistant.
            Answer from Stockflow tools when a question depends on live application data. Never invent records or claim an action happened without a successful tool result.
            The active store is {$activeStoreContext}. Treat phrases such as “current store” as this snapshotted store. Ask for clarification before proposing a write when the target store or record is ambiguous.
            When a missing value must be chosen from 2 to 4 meaningful known options, use ask_user_choice and wait for the administrator's selection. Use a short normal chat question for genuinely free-form information. A choice only clarifies intent and never approves a mutation.
            For a mutation, the selected native writer's action schema is authoritative. Do not infer additional required fields from conventional business software, and do not ask for values that the schema marks optional or does not list.
            Read tools may run immediately. Every mutation and external side effect requires human approval. The application renders the confirmation from validated business data, so do not expose tool names, record IDs, or technical context to the user.
            Read results are completeness envelopes. Never use a result with complete=false or PARTIAL_RESULT to make an exhaustive or negative claim. Follow next_cursor until complete, use the resource summary operation for exact aggregates, or clearly tell the administrator that the answer is incomplete. Treat previously read live values as stale and read them again for current-state questions.
            Never request or expose passwords, API keys, provider configuration, raw SQL, filesystem access, shell access, or binary uploads.
            {$memoryContext}
            INSTRUCTIONS;
    }

    /**
     * Return complete recent semantic turns under the application context budget.
     *
     * @return iterable<Message>
     */
    public function messages(): iterable
    {
        if ($this->currentConversation() === null) {
            return [];
        }

        return Resolver::resolve(AssistantConversationContext::class)->recentMessages($this->assistantConversationId);
    }

    /**
     * Get the configured provider name.
     */
    public function provider(): string
    {
        return 'openrouter';
    }

    /**
     * Get the configured model name.
     */
    public function model(): string
    {
        return Config::inject()->assertString('ai.providers.openrouter.models.text.default');
    }

    /**
     * Get the maximum number of generation steps.
     */
    public function maxSteps(): int
    {
        return Config::inject()->assertInt('ai.assistant.max_steps');
    }

    /**
     * Get the provider timeout in seconds.
     */
    public function timeout(): int
    {
        return Config::inject()->assertInt('ai.assistant.timeout_seconds');
    }

    /**
     * Get the authenticated main admin for authorization and auditing.
     */
    public function actor(): User
    {
        return $this->actor;
    }

    /**
     * Get the explicitly authorized conversation identifier.
     */
    public function assistantConversationId(): string
    {
        return $this->assistantConversationId;
    }

    /**
     * Resolve an auditable local tool by its provider-facing name.
     */
    public function findAuditableTool(string $name): AuditableAssistantTool|null
    {
        $tool = Resolver::resolve(AssistantToolCatalog::class)->find(
            $this->actor,
            $this->assistantConversationId,
            $name,
        );

        return $tool instanceof AuditableAssistantTool ? $tool : null;
    }

    /**
     * Get the tools available to the assistant.
     *
     * @return iterable<Tool>
     */
    public function tools(): iterable
    {
        yield from Resolver::resolve(AssistantToolCatalog::class)->tools(
            $this->actor,
            $this->assistantConversationId,
        );
    }
}
