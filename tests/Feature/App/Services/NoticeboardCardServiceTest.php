<?php

declare(strict_types=1);

use App\Domain\Noticeboard\NoticeboardCardService;
use App\Models\NoticeboardCard;
use App\Models\Store;
use Database\Factories\UserFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;

\test('noticeboard domain rejects foreign actors without changing card state', function (string $operation, string $actorType): void {
    [$owner, $store] = \createIsolatedUserWithWarehouse();
    $otherStore = Store::factory()->create(['user_id' => $owner->getKey()]);
    $actor = $actorType === 'foreign_admin'
        ? UserFactory::new()->admin()->createOne()
        : UserFactory::new()->limited($otherStore)->createOne();
    $card = NoticeboardCard::factory()->create([
        'user_id' => $owner->getKey(), 'store_id' => $store->getKey(),
        'created_by_user_id' => $owner->getKey(), 'updated_by_user_id' => $owner->getKey(),
        'title' => 'Original card', 'body_html' => '<p>Original card</p>', 'body_text' => 'Original card',
    ]);
    if (\in_array($operation, ['restore', 'forceDelete'], true)) {
        $card->delete();
    }
    $service = new NoticeboardCardService();
    $count = NoticeboardCard::withTrashed()->count();

    \expect(static function () use ($operation, $service, $actor, $store, $card): void {
        match ($operation) {
            'create' => $service->create($actor, $store, '<p>Forbidden</p>', 'information', 'yellow', 'medium', null, null),
            'update' => $service->update($card, $actor, '<p>Forbidden</p>', 'information', 'yellow', 'medium', null, null, false, 1),
            'trash' => $service->trash($card, $actor),
            'restore' => $service->restore($card, $actor),
            'forceDelete' => $service->forceDelete($card, $actor),
        };
    })->toThrow(HttpException::class);

    $persisted = NoticeboardCard::withTrashed()->whereKey($card->getKey())->firstOrFail();
    \expect($persisted->getTitle())->toBe('Original card')
        ->and($persisted->getLockVersion())->toBe(1)
        ->and($persisted->getDeletedAt() !== null)->toBe(\in_array($operation, ['restore', 'forceDelete'], true))
        ->and(NoticeboardCard::withTrashed()->count())->toBe($count);
})->with(['create', 'update', 'trash', 'restore', 'forceDelete'])->with(['foreign_admin', 'other_store_limited']);

\test('assigned limited users cannot restore or permanently delete noticeboard cards through domain methods', function (string $operation): void {
    [$owner, $store] = \createIsolatedUserWithWarehouse();
    $actor = UserFactory::new()->limited($store)->createOne();
    $card = NoticeboardCard::factory()->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey()]);
    $card->delete();
    $service = new NoticeboardCardService();

    \expect(static function () use ($operation, $service, $card, $actor): void {
        $operation === 'restore' ? $service->restore($card, $actor) : $service->forceDelete($card, $actor);
    })->toThrow(HttpException::class);
    \expect(NoticeboardCard::withTrashed()->whereKey($card->getKey())->firstOrFail()->getDeletedAt())->not->toBeNull();
})->with(['restore', 'forceDelete']);

\test('assigned limited users retain permitted direct noticeboard create update and trash operations', function (): void {
    [$owner, $store] = \createIsolatedUserWithWarehouse();
    $actor = UserFactory::new()->limited($store)->createOne();
    $service = new NoticeboardCardService();
    $card = $service->create($actor, $store, '<p>Created</p>', 'information', 'yellow', 'medium', null, null);
    $updated = $service->update($card, $actor, '<p>Updated</p>', 'task', 'blue', 'small', null, null, false, 1);
    \expect($updated->getTitle())->toBe('Updated')->and($updated->getUserId())->toBe($owner->getKey());
    $service->trash($updated, $actor);
    \expect(NoticeboardCard::withTrashed()->whereKey($card->getKey())->firstOrFail()->getDeletedAt())->not->toBeNull();
});
