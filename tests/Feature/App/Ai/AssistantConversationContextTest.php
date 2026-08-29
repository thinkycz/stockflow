<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Ai\AssistantConversationContext;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

\test('conversation context expands beyond one hundred rows and preserves complete recent turns', function (): void {
    Config::inject()->assign('ai.assistant.context_max_rows', 300);
    Config::inject()->assign('ai.assistant.context_max_characters', 500000);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = Typer::assertInstance($admin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Long conversation',
    ]), Conversation::class);

    foreach (\range(1, 160) as $turn) {
        foreach (['user', 'assistant'] as $role) {
            $conversation->messages()->create([
                'id' => Str::ulid()->toString(),
                'participant_type' => $admin->getMorphClass(),
                'participant_id' => $admin->getKey(),
                'agent' => StockflowAssistant::class,
                'role' => $role,
                'content' => "{$role} turn {$turn}",
                'attachments' => [],
                'tool_calls' => [],
                'tool_results' => [],
                'usage' => [],
                'meta' => [],
            ]);
        }
    }

    $context = new AssistantConversationContext();
    $messages = $context->recentMessages(Typer::assertString($conversation->getKey()));

    \expect($messages)->toHaveCount(300)
        ->and($messages[0]->role->value)->toBe('user')
        ->and($messages[299]->content)->toBe('assistant turn 160');

    Config::inject()->assign('ai.assistant.context_max_characters', 50);
    $bounded = $context->recentMessages(Typer::assertString($conversation->getKey()));

    \expect($bounded)->toHaveCount(2)
        ->and($bounded[0]->content)->toBe('user turn 160')
        ->and($bounded[1]->content)->toBe('assistant turn 160');
});

\test('older context is summarized without copying raw tool result values', function (): void {
    Config::inject()->assign('ai.assistant.context_max_rows', 2);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = Typer::assertInstance($admin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Summarized conversation',
    ]), Conversation::class);

    foreach (['Remember the staffing preference', 'Acknowledged', 'Current question', 'Current answer'] as $index => $content) {
        $conversation->messages()->create([
            'id' => Str::ulid()->toString(),
            'participant_type' => $admin->getMorphClass(),
            'participant_id' => $admin->getKey(),
            'agent' => StockflowAssistant::class,
            'role' => $index % 2 === 0 ? 'user' : 'assistant',
            'content' => $content,
            'attachments' => [],
            'tool_calls' => $index === 1 ? [['id' => 'call-1', 'name' => 'write_shifts', 'arguments' => []]] : [],
            'tool_results' => $index === 1 ? [['id' => 'call-1', 'result' => 'SECRET LIVE VALUE']] : [],
            'usage' => [],
            'meta' => [],
        ]);
    }

    $context = new AssistantConversationContext();
    $context->refreshSummary($conversation);
    $summary = $context->summary(Typer::assertString($conversation->getKey()));

    \expect($summary)->toContain('Remember the staffing preference')
        ->toContain('Completed action: write_shifts')
        ->not->toContain('SECRET LIVE VALUE');
});
