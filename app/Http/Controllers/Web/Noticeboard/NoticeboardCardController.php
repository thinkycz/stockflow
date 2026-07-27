<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Noticeboard;

use App\Enums\FilesystemDiskEnum;
use App\Enums\NoticeboardCardSizeEnum;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\NoticeboardCardValidity;
use App\Models\NoticeboardCard;
use App\Models\Store;
use App\Models\User;
use App\Services\NoticeboardCardService;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class NoticeboardCardController
{
    use ValidatesWebRequests;

    /**
     * Create a card for the resolved store.
     */
    public function store(Request $request): RedirectResponse
    {
        $actor = User::mustAuth();
        $store = $this->resolveStore($request, $actor);
        $validity = NoticeboardCardValidity::inject();
        $validated = $this->validateRequest($request, [
            'body_html' => $validity->bodyHtml()->required()->toArray(),
            'label' => $validity->label()->required()->toArray(),
            'color' => $validity->color()->required()->toArray(),
            'size' => $validity->size()->nullable()->toArray(),
            'expires_on' => $validity->expiresOn()->nullable()->toArray(),
            'image' => $validity->image()->nullable()->toArray(),
        ]);

        try {
            (new NoticeboardCardService())->create(
                $actor,
                $store,
                $validated->assertString('body_html'),
                $validated->assertString('label'),
                $validated->assertString('color'),
                $validated->assertNullableString('size') ?? NoticeboardCardSizeEnum::Medium->value,
                $validated->assertNullableString('expires_on'),
                $validated->assertNullableFile('image'),
            );
        } catch (InvalidArgumentException) {
            Thrower::default()->message('body_html', \__('The card content must contain visible text.'))->throw();
        }

        Inertia::flash('success', \__('Card created.'));

        return Resolver::resolveRedirector()->route('dashboard');
    }

    /**
     * Update a card for the resolved store.
     */
    public function update(Request $request): RedirectResponse
    {
        $actor = User::mustAuth();
        $store = $this->resolveStore($request, $actor);
        $card = $this->resolveCard($request, $actor, $store);
        $validity = NoticeboardCardValidity::inject();
        $validated = $this->validateRequest($request, [
            'body_html' => $validity->bodyHtml()->required()->toArray(),
            'label' => $validity->label()->required()->toArray(),
            'color' => $validity->color()->required()->toArray(),
            'size' => $validity->size()->nullable()->toArray(),
            'expires_on' => $validity->expiresOn()->nullable()->toArray(),
            'image' => $validity->image()->nullable()->toArray(),
            'remove_image' => $validity->removeImage()->nullable()->toArray(),
            'lock_version' => $validity->lockVersion()->required()->toArray(),
        ]);

        try {
            (new NoticeboardCardService())->update(
                $card,
                $actor,
                $validated->assertString('body_html'),
                $validated->assertString('label'),
                $validated->assertString('color'),
                $validated->assertNullableString('size') ?? $card->getSize()->value,
                $validated->assertNullableString('expires_on'),
                $validated->assertNullableFile('image'),
                $validated->parseBool('remove_image'),
                $validated->parseInt('lock_version'),
            );
        } catch (InvalidArgumentException) {
            Thrower::default()->message('body_html', \__('The card content must contain visible text.'))->throw();
        }

        Inertia::flash('success', \__('Card updated.'));

        return Resolver::resolveRedirector()->route('dashboard');
    }

    /**
     * Move a card to the recycle bin.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $actor = User::mustAuth();
        $store = $this->resolveStore($request, $actor);
        (new NoticeboardCardService())->trash($this->resolveCard($request, $actor, $store));
        Inertia::flash('success', \__('Card moved to trash.'));

        return Resolver::resolveRedirector()->route('dashboard');
    }

    /**
     * Stream a private card image after store authorization.
     */
    public function image(Request $request): StreamedResponse
    {
        $actor = User::mustAuth();
        $store = $this->resolveStore($request, $actor);
        $card = $this->resolveCard($request, $actor, $store, $actor->isAdmin());
        $path = $card->getImagePath();

        if ($path === null) {
            \abort(404);
        }

        return Resolver::resolveFilesystemManager()
            ->disk(FilesystemDiskEnum::Private->value)
            ->response($path, null, [
                'Content-Type' => $card->getImageMime() ?? 'application/octet-stream',
                'Cache-Control' => 'private, no-store',
            ]);
    }

    /**
     * Restore a trashed card. The route is admin-only.
     */
    public function restore(Request $request): RedirectResponse
    {
        $actor = User::mustAuth();
        $store = $this->resolveStore($request, $actor);
        (new NoticeboardCardService())->restore($this->resolveCard($request, $actor, $store, true, true));
        Inertia::flash('success', \__('Card restored.'));

        return Resolver::resolveRedirector()->route('dashboard', ['status' => 'trash']);
    }

    /**
     * Permanently delete a trashed card. The route is admin-only.
     */
    public function forceDestroy(Request $request): RedirectResponse
    {
        $actor = User::mustAuth();
        $store = $this->resolveStore($request, $actor);
        $deleted = (new NoticeboardCardService())->forceDelete(
            $this->resolveCard($request, $actor, $store, true, true),
        );

        if (!$deleted) {
            Thrower::default()->message('card', \__('The card image could not be deleted.'))->throw();
        }

        Inertia::flash('success', \__('Card permanently deleted.'));

        return Resolver::resolveRedirector()->route('dashboard', ['status' => 'trash']);
    }

    /**
     * Resolve the server-owned store context.
     */
    private function resolveStore(Request $request, User $actor): Store
    {
        $store = ActiveStoreResolver::resolve($request, $actor);

        if (!$store instanceof Store) {
            \abort(404);
        }

        return $store;
    }

    /**
     * Resolve a card inside the company and store scope.
     */
    private function resolveCard(
        Request $request,
        User $actor,
        Store $store,
        bool $withTrashed = false,
        bool $onlyTrashed = false,
    ): NoticeboardCard {
        $id = Typer::parseInt($request->route('noticeboardCard'));
        $query = $withTrashed ? NoticeboardCard::query()->withTrashed() : NoticeboardCard::query();
        NoticeboardCard::scopeForUser($query, $actor->resolveScopeUser());
        NoticeboardCard::scopeForStore($query, $store->getKey());

        if ($onlyTrashed) {
            $query->onlyTrashed();
        }

        return Typer::assertInstance($query->whereKey($id)->firstOrFail(), NoticeboardCard::class);
    }
}
