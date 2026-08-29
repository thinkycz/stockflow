<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Enums\AssistantTurnStatusEnum;
use App\Models\AssistantTurn;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

\test('an administrator can request cancellation only for their own durable turn', function (): void {
    Config::inject()->assign('ai.assistant.enabled', true);
    Config::inject()->assign('ai.assistant.durable_turns', true);
    Queue::fake();
    StockflowAssistant::fake();
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $other = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $turnId = Str::uuid()->toString();

    $this->withSession(['_token' => 'assistant-cancel-test-token'])
        ->withHeader('X-CSRF-TOKEN', 'assistant-cancel-test-token')
        ->be($admin, 'users')
        ->postJson('/assistant/chat', ['message' => 'Keep this turn', 'turn_id' => $turnId])
        ->assertOk();

    $this->be($other, 'users')->post('/assistant/turns/' . $turnId . '/cancel')->assertNotFound();
    $this->be($admin, 'users')->post('/assistant/turns/' . $turnId . '/cancel')->assertNoContent();

    $turn = Typer::assertInstance(AssistantTurn::query()->whereKey($turnId)->first(), AssistantTurn::class);
    \expect($turn->getStatus())->toBe(AssistantTurnStatusEnum::CANCEL_REQUESTED);
});
