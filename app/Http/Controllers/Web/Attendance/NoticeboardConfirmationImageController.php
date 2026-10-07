<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Attendance;

use App\Domain\Noticeboard\NoticeboardConfirmationService;
use App\Enums\FilesystemDiskEnum;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class NoticeboardConfirmationImageController
{
    /**
     * Stream an immutable private image inside the authorized retail store.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $actor = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $actor);
        if (!$store instanceof Store || $store->isWarehouse()) {
            \abort(404);
        }
        $item = (new NoticeboardConfirmationService())->imageItem($actor, $store, Typer::parseInt($request->route('item')));
        $path = $item->getImagePath();
        if ($path === null) {
            \abort(404);
        }

        return Resolver::resolveFilesystemManager()->disk(FilesystemDiskEnum::Private->value)->response($path, null, [
            'Content-Type' => $item->getImageMime() ?? 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
