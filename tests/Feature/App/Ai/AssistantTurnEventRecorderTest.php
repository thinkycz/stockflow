<?php

declare(strict_types=1);

use App\Ai\AssistantTurnEventRecorder;
use App\Enums\AssistantTurnStatusEnum;
use App\Models\AssistantTurn;
use App\Models\AssistantTurnEvent;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Str;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\TextStart;
use Thinkycz\LaravelCore\Support\Typer;

\test('text deltas become replayable while the provider is still generating', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $turn = AssistantTurn::query()->forceCreate([
        'id' => Str::uuid()->toString(),
        'actor_user_id' => $admin->getKey(),
        'conversation_id' => Str::uuid()->toString(),
        'parent_turn_id' => null,
        'kind' => 'message',
        'recovery_mode' => 'normal',
        'status' => AssistantTurnStatusEnum::RUNNING->value,
        'input_hash' => \hash('sha256', 'stream this'),
        'input_payload' => ['message' => 'stream this'],
        'queued_at' => \now(),
        'started_at' => \now(),
    ]);
    $recorder = new AssistantTurnEventRecorder();
    $messageId = Str::ulid()->toString();

    $recorder->record($turn, new TextStart(Str::ulid()->toString(), $messageId, \time()));
    $recorder->record($turn, new TextDelta(Str::ulid()->toString(), $messageId, 'First', \time()));

    \expect(AssistantTurnEvent::query()
        ->where('turn_id', $turn->getTurnId())
        ->where('event_type', 'text-delta')
        ->count())->toBe(1);

    $recorder->record($turn, new TextDelta(Str::ulid()->toString(), $messageId, ' visible', \time()));
    \usleep(125000);
    $recorder->record($turn, new TextDelta(Str::ulid()->toString(), $messageId, ' chunk', \time()));

    $deltas = AssistantTurnEvent::query()
        ->where('turn_id', $turn->getTurnId())
        ->where('event_type', 'text-delta')
        ->orderBy('sequence')
        ->get()
        ->map(static fn(AssistantTurnEvent $event): string => Typer::assertString($event->getPayload()['delta'] ?? null))
        ->all();

    \expect($deltas)->toBe(['First', ' visible chunk']);
});
