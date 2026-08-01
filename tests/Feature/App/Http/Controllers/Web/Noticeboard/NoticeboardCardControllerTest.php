<?php

declare(strict_types=1);

use App\Enums\FilesystemDiskEnum;
use App\Models\NoticeboardCard;
use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin can create a sanitized card for the active store', function (): void {
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $admin->setActiveStoreId($store->getKey());

    $response = $this->be($admin, 'users')->post('/noticeboard-cards', [
        'title' => 'Důležitá zpráva',
        'body_html' => '<p>Ahoj <strong>týme</strong><script>alert(1)</script></p>',
        'label' => 'important',
        'color' => 'yellow',
        'size' => 'large',
    ]);

    $response->assertRedirect('/dashboard');
    $card = Typer::assertInstance(NoticeboardCard::query()->first(), NoticeboardCard::class);
    \expect($card->getStoreId())->toBe($store->getKey())
        ->and($card->getSize()->value)->toBe('large')
        ->and($card->getTitle())->toBe('Ahoj týme')
        ->and($card->getBodyHtml())->toContain('<strong>týme</strong>')
        ->not->toContain('<script');
});

\test('card validation rejects invalid rich text and stores expiration at Prague end of day in UTC', function (): void {
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $admin->setActiveStoreId($store->getKey());
    $base = [
        'title' => 'Platná kartička',
        'body_html' => '<p>Viditelný obsah</p>',
        'label' => 'information',
        'color' => 'yellow',
    ];

    $this->be($admin, 'users')->post('/noticeboard-cards', [
        ...$base,
        'body_html' => \str_repeat('a', 20_001),
    ])->assertUnprocessable();
    $this->be($admin, 'users')->post('/noticeboard-cards', [
        ...$base,
        'body_html' => '<p><br></p>',
    ])->assertUnprocessable();
    $this->be($admin, 'users')->post('/noticeboard-cards', [
        ...$base,
        'size' => 'extra-large',
    ])->assertUnprocessable();

    $this->be($admin, 'users')->post('/noticeboard-cards', [
        ...$base,
        'expires_on' => '2026-08-10',
    ])->assertRedirect('/dashboard');

    $card = Typer::assertInstance(NoticeboardCard::query()->first(), NoticeboardCard::class);
    \expect($card->getExpiresAt()?->utc()->toDateTimeString())->toBe('2026-08-10 21:59:59');
});

\test('admin without any store cannot create a card', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $this->be($admin, 'users')->post('/noticeboard-cards', [
        'title' => 'Bez prodejny',
        'body_html' => '<p>Obsah</p>',
        'label' => 'information',
        'color' => 'yellow',
    ])->assertNotFound();
});

\test('limited user can update any card in their assigned store but not another store', function (): void {
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $local = NoticeboardCard::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'updated_by_user_id' => $admin->getKey(),
    ]);
    $foreign = NoticeboardCard::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $otherStore->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'updated_by_user_id' => $admin->getKey(),
    ]);

    $payload = [
        'title' => 'Upraveno',
        'body_html' => '<p>Nový obsah</p>',
        'label' => 'task',
        'color' => 'blue',
        'size' => 'small',
        'lock_version' => 1,
    ];

    $this->be($limited, 'users')->post('/noticeboard-cards/' . $local->getKey(), [
        ...$payload,
        '_method' => 'put',
    ])->assertRedirect('/dashboard');
    \expect($local->fresh()?->getTitle())->toBe('Nový obsah')
        ->and($local->fresh()?->getSize()->value)->toBe('small');
    $this->be($limited, 'users')->put('/noticeboard-cards/' . $foreign->getKey(), $payload)->assertNotFound();
});

\test('stale card update is rejected without overwriting newer content', function (): void {
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $admin->setActiveStoreId($store->getKey());
    $card = NoticeboardCard::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'updated_by_user_id' => $admin->getKey(),
        'lock_version' => 2,
        'title' => 'Newer title',
    ]);

    $response = $this->be($admin, 'users')
        ->withHeader('X-Inertia', 'true')
        ->put('/noticeboard-cards/' . $card->getKey(), [
            'title' => 'Stale title',
            'body_html' => '<p>Stale</p>',
            'label' => 'information',
            'color' => 'green',
            'lock_version' => 1,
        ]);

    $response->assertRedirect()->assertSessionHasErrors();
    \expect($card->fresh()?->getTitle())->toBe('Newer title');
});

\test('card image is private and available only inside the assigned store', function (): void {
    Storage::fake(FilesystemDiskEnum::Private->value);
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $admin->setActiveStoreId($store->getKey());

    $this->be($admin, 'users')->post('/noticeboard-cards', [
        'title' => 'S obrázkem',
        'body_html' => '<p>Obsah</p>',
        'label' => 'event',
        'color' => 'pink',
        'image' => UploadedFile::fake()->image('notice.png', 1200, 800),
    ])->assertRedirect('/dashboard');

    $card = Typer::assertInstance(NoticeboardCard::query()->first(), NoticeboardCard::class);
    Storage::disk(FilesystemDiskEnum::Private->value)->assertExists(Typer::assertString($card->getImagePath()));
    $this->be($admin, 'users')
        ->get('/noticeboard-cards/' . $card->getKey() . '/image')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');
});

\test('card image can be replaced and removed and cannot be read through another active store', function (): void {
    Storage::fake(FilesystemDiskEnum::Private->value);
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $admin->setActiveStoreId($store->getKey());
    Storage::disk(FilesystemDiskEnum::Private->value)->put('noticeboard/old.png', 'old');
    $card = NoticeboardCard::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'updated_by_user_id' => $admin->getKey(),
        'image_path' => 'noticeboard/old.png',
        'image_mime' => 'image/png',
    ]);
    $payload = [
        'title' => 'Obrázek',
        'body_html' => '<p>Obsah</p>',
        'label' => 'information',
        'color' => 'blue',
    ];

    $this->be($admin, 'users')->post('/noticeboard-cards/' . $card->getKey(), [
        ...$payload,
        '_method' => 'put',
        'lock_version' => 1,
        'image' => UploadedFile::fake()->image('replacement.webp', 800, 600),
    ])->assertRedirect('/dashboard');

    $updated = Typer::assertInstance($card->fresh(), NoticeboardCard::class);
    $replacementPath = Typer::assertString($updated->getImagePath());
    Storage::disk(FilesystemDiskEnum::Private->value)->assertMissing('noticeboard/old.png');
    Storage::disk(FilesystemDiskEnum::Private->value)->assertExists($replacementPath);
    $this->be($admin, 'users')
        ->get('/noticeboard-cards/' . $card->getKey() . '/image?store_id=' . $otherStore->getKey())
        ->assertNotFound();

    $this->be($admin, 'users')->post('/noticeboard-cards/' . $card->getKey(), [
        ...$payload,
        '_method' => 'put',
        'lock_version' => 2,
        'remove_image' => true,
    ])->assertRedirect('/dashboard');

    \expect($card->fresh()?->getImagePath())->toBeNull();
    Storage::disk(FilesystemDiskEnum::Private->value)->assertMissing($replacementPath);
});

\test('only admin can restore and permanently delete a trashed card', function (): void {
    Storage::fake(FilesystemDiskEnum::Private->value);
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $admin->setActiveStoreId($store->getKey());
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $card = NoticeboardCard::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'created_by_user_id' => $limited->getKey(),
        'updated_by_user_id' => $limited->getKey(),
    ]);

    $this->be($limited, 'users')->delete('/noticeboard-cards/' . $card->getKey())->assertRedirect('/dashboard');
    \expect(NoticeboardCard::query()->find($card->getKey()))->toBeNull();
    $this->be($limited, 'users')->post('/noticeboard-cards/' . $card->getKey() . '/restore')->assertRedirect('/dashboard');
    $this->be($admin, 'users')->post('/noticeboard-cards/' . $card->getKey() . '/restore')->assertRedirect('/dashboard?status=trash');
    \expect($card->fresh())->not->toBeNull();

    $card->delete();
    $this->be($admin, 'users')->delete('/noticeboard-cards/' . $card->getKey() . '/force')->assertRedirect('/dashboard?status=trash');
    \expect(NoticeboardCard::query()->withTrashed()->find($card->getKey()))->toBeNull();
});
