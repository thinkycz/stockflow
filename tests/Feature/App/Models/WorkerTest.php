<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('attendance rating is enabled by the database default', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $worker = Typer::assertInstance(Worker::query()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Default',
        'last_name' => 'Rating',
        'hourly_rate' => 200,
    ]), Worker::class);

    \expect($worker->refresh()->isAttendanceRatingEnabled())->toBeTrue();
});

\test('calendar color is stable and differs between adjacent workers', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $firstWorker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $secondWorker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $reloadedWorker = Typer::assertInstance(
        Worker::query()->find($firstWorker->getKey()),
        Worker::class,
    );

    \expect($firstWorker->getCalendarColor())
        ->toMatch('/^#[0-9A-F]{6}$/')
        ->toBe($reloadedWorker->getCalendarColor())
        ->not->toBe($secondWorker->getCalendarColor());
});
