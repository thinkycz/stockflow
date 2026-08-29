<?php

declare(strict_types=1);

use App\Enums\AssistantTurnStatusEnum;
use App\Jobs\MaintainAssistantTurnsJob;
use App\Models\AssistantTurn;
use App\Models\AssistantTurnEvent;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Typer;

\test('assistant maintenance prunes expired chunks and fails abandoned active turns', function (): void {
    Carbon::setTestNow('2026-08-29 12:00:00');
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = $admin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Maintenance',
    ]);
    $turn = AssistantTurn::query()->forceCreate([
        'id' => Str::uuid()->toString(),
        'actor_user_id' => $admin->getKey(),
        'conversation_id' => $conversation->getKey(),
        'kind' => 'message',
        'status' => AssistantTurnStatusEnum::RUNNING->value,
        'input_hash' => Str::random(64),
        'input_payload' => ['message' => 'Recover me'],
        'queued_at' => \now()->subMinutes(10),
        'created_at' => \now()->subMinutes(10),
        'updated_at' => \now()->subMinutes(10),
    ]);
    $expired = AssistantTurnEvent::query()->create([
        'turn_id' => $turn->getTurnId(),
        'sequence' => 1,
        'event_type' => 'text-delta',
        'payload' => ['type' => 'text-delta', 'delta' => 'expired'],
        'created_at' => \now()->subHours(25),
        'updated_at' => \now()->subHours(25),
    ]);
    $recent = AssistantTurnEvent::query()->create([
        'turn_id' => $turn->getTurnId(),
        'sequence' => 2,
        'event_type' => 'text-delta',
        'payload' => ['type' => 'text-delta', 'delta' => 'recent'],
    ]);

    (new MaintainAssistantTurnsJob())->handle();

    $turn->refresh();
    \expect($turn->getStatus())->toBe(AssistantTurnStatusEnum::FAILED)
        ->and(AssistantTurnEvent::query()->whereKey($expired->getKey())->exists())->toBeFalse()
        ->and(AssistantTurnEvent::query()->whereKey($recent->getKey())->exists())->toBeTrue()
        ->and($turn->getInputPayload())->toBe(['message' => 'Recover me']);
});
