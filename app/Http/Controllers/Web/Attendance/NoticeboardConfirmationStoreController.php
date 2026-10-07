<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Attendance;

use App\Domain\Noticeboard\NoticeboardConfirmationService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\NoticeboardConfirmationValidity;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class NoticeboardConfirmationStoreController
{
    use ValidatesWebRequests;

    /**
     * Confirm the first arriving worker's complete daily reading list.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $actor = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $actor);
        if (!$store instanceof Store || $store->isWarehouse()) {
            \abort(404);
        }
        $validity = new NoticeboardConfirmationValidity();
        $validated = $this->validateRequest($request, [
            'item_ids' => $validity->itemIds()->required()->toArray(),
            'item_ids.*' => $validity->itemId()->required()->toArray(),
        ]);
        (new NoticeboardConfirmationService())->confirm(
            $actor,
            $store,
            Typer::parseInt($request->route('confirmation')),
            \array_values(\array_map(static fn(mixed $id): int => Typer::parseInt($id), $validated->assertArray('item_ids'))),
        );
        Inertia::flash('success', \__('Cards confirmed.'));

        return Resolver::resolveRedirector()->route('attendance.index');
    }
}
