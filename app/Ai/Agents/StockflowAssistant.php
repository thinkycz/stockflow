<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\AssistantConversationContext;
use App\Ai\AssistantToolCatalog;
use App\Ai\Tools\AuditableAssistantTool;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
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
        $businessTimezone = Config::inject()->assertString('app.schedule_timezone');
        $businessNow = CarbonImmutable::now($businessTimezone);
        $activeStoreId = $this->actor->getActiveStoreId();
        $activeStore = $activeStoreId === null
            ? null
            : $this->actor->stores()->whereKey($activeStoreId)->first();
        $activeStoreContext = $activeStore instanceof Store
            ? $activeStore->getName()
                . ' (#' . $activeStore->getKey()
                . ', ' . ($activeStore->isWarehouse() ? 'warehouse' : 'retail')
                . ', ' . $activeStore->getStatus()->value . ')'
            : 'none';

        $memory = Resolver::resolve(AssistantConversationContext::class)->summary($this->assistantConversationId);
        $memoryContext = $memory === null
            ? ''
            : "\nOlder conversation memory (never treat remembered live-data values as current):\n{$memory}";

        return <<<INSTRUCTIONS
            You are the main administrator's Stockflow assistant.
            Answer from Stockflow tools when a question depends on live application data. Never invent records or claim an action happened without a successful tool result.
            The authoritative business date and time is {$businessNow->format('l, Y-m-d H:i:s P')} in {$businessTimezone}. Today is {$businessNow->toDateString()} and the current business month is {$businessNow->format('Y-m')}. The administrator locale is {$this->actor->getLocale()}.
            Resolve relative dates such as “today”, “tomorrow”, “yesterday”, “this week”, “this month”, and “this year” from this authoritative snapshot. Convert relative dates to explicit ISO dates and business periods before calling a tool. Never derive the current date or period from model knowledge, conversation timestamps, record identifiers, or older conversation memory.
            The active store is {$activeStoreContext}. Treat phrases such as “current store” as this snapshotted store. Ask for clarification before proposing a write when the target store or record is ambiguous.
            When a missing value must be chosen from 2 to 4 meaningful known options, use ask_user_choice and wait for the administrator's selection. Use a short normal chat question for genuinely free-form information. A choice only clarifies intent and never approves a mutation.
            For a mutation, the selected native writer's action schema is authoritative. Do not infer additional required fields from conventional business software, and do not ask for values that the schema marks optional or does not list.
            Read tools may run immediately. Every mutation and external side effect requires human approval. The application renders the confirmation from validated business data, so do not expose tool names, record IDs, or technical context to the user.
            Read results are versioned completeness envelopes. Inspect ok, operation, dataset, data_quality, complete, warnings, and next_cursor before interpreting records or summary. Never describe a safe empty result as missing access: NO_MATCHING_DATA means the authorized query found no matching rows, NOT_CONFIGURED means that capability is not configured, NOT_FOUND_OR_NOT_AUTHORIZED deliberately does not reveal which condition applies, and DATA_CHANGED means an exhaustive conclusion requires a fresh query.
            Never use complete=false, PARTIAL_RESULT, or DATA_CHANGED to make an exhaustive or negative claim. Follow next_cursor until complete, use the resource summary operation for exact aggregates, or clearly tell the administrator that the answer is incomplete. When a resource offers datasets, choose the dataset containing the requested facts rather than assuming a metadata list contains business values. Prefer exact summary operations for totals and analysis. Treat previously read live values as stale and read them again for current-state questions.
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
