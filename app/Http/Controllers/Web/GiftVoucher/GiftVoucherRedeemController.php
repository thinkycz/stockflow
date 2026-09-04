<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\GiftVoucher;

use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\GiftVoucherValidity;
use App\Models\GiftVoucher;
use App\Models\Store;
use App\Models\User;
use App\Services\GiftVoucherService;
use App\Support\ActiveStoreResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class GiftVoucherRedeemController
{
    use ThrottlesWebRequests;
    use ValidatesWebRequests;

    /**
     * Consume a matching single-use lookup ticket and redeem the voucher.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        self::$throttle = 15;
        self::$decay = 1;
        $validated = $this->validateRequest($request, [
            'ticket' => GiftVoucherValidity::inject()->ticket()->required()->toArray(),
        ]);
        $this->hit($this->limit());
        $actor = User::mustAuth();
        $candidate = $request->session()->pull(GiftVoucherLookupController::SESSION_KEY);
        $voucherId = Typer::parseInt($request->route('voucher'));

        if (
            !\is_array($candidate) ||
            $voucherId !== Typer::parseNullableInt($candidate['voucher_id'] ?? null) ||
            Typer::parseNullableInt($candidate['actor_user_id'] ?? null) !== $actor->getKey() ||
            Typer::parseNullableInt($candidate['expires_at'] ?? null) < CarbonImmutable::now()->timestamp ||
            !\hash_equals(
                Typer::parseNullableString($candidate['ticket'] ?? null) ?? '',
                $validated->assertString('ticket'),
            )
        ) {
            Thrower::default()->message('ticket', \__('Gift voucher confirmation expired. Check the code again.'))->throw();
        }

        $store = ActiveStoreResolver::resolve($request, $actor);
        if (!$store instanceof Store || $store->isWarehouse()) {
            Thrower::default()->message('store', \__('Gift vouchers can only be redeemed at a retail store.'))->throw();
        }

        $query = GiftVoucher::query()->with('giftVoucherBatch');
        GiftVoucher::scopeForUser($query, $actor->resolveScopeUser());
        $voucher = Typer::assertInstance($query->whereKey($voucherId)->firstOrFail(), GiftVoucher::class);
        (new GiftVoucherService())->redeem($actor, $store, $voucher);
        Inertia::flash('success', \__('Gift voucher redeemed.'));

        return Resolver::resolveRedirector()->route('gift-vouchers.redeem-page');
    }
}
