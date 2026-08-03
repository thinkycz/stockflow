<?php

declare(strict_types=1);

use App\Enums\OperationalDailyDigestStatusEnum;
use App\Models\OperationalDailyDigest;
use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin can browse the company daily digest archive', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $digest = OperationalDailyDigest::factory()->create([
        'company_user_id' => $admin->getKey(),
        'digest_date' => '2026-08-02',
        'status' => OperationalDailyDigestStatusEnum::SENT->value,
        'activity_count' => 7,
    ]);

    $this->be($admin, 'users')
        ->get('/settings/slack-digests', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'settings/slack-digests/Index')
        ->assertJsonPath('props.digests.0.id', $digest->getKey())
        ->assertJsonPath('props.digests.0.status', 'sent')
        ->assertJsonPath('props.digests.0.activity_count', 7);
});

\test('digest archive index is admin only', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);

    $this->be($limited, 'users')
        ->get('/settings/slack-digests', $this->inertiaHeaders())
        ->assertRedirect('/dashboard');
});
