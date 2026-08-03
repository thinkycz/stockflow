<?php

declare(strict_types=1);

use App\Models\OperationalDailyDigest;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin can open a company daily digest detail', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $digest = OperationalDailyDigest::factory()->create(['company_user_id' => $admin->getKey()]);

    $this->be($admin, 'users')
        ->get('/settings/slack-digests/' . $digest->getKey(), $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'settings/slack-digests/Show')
        ->assertJsonPath('props.digest.id', $digest->getKey())
        ->assertJsonPath('props.digest.snapshot.title', 'Denní provozní souhrn');
});

\test('digest detail is scoped to the deployment company', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $foreignAdmin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $foreignDigest = OperationalDailyDigest::factory()->create(['company_user_id' => $foreignAdmin->getKey()]);

    $this->be($admin, 'users')
        ->get('/settings/slack-digests/' . $foreignDigest->getKey(), $this->inertiaHeaders())
        ->assertNotFound();
});
