<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\GiftVoucher;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\GiftVoucherValidity;
use App\Models\GiftVoucher;
use App\Models\User;
use App\Services\GiftVoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class GiftVoucherLifecycleController
{
    use ValidatesWebRequests;

    /**
     * Void an active voucher.
     */
    public function void(Request $request): RedirectResponse
    {
        return $this->transition($request, false);
    }

    /**
     * Reverse an erroneous redemption.
     */
    public function reverseRedemption(Request $request): RedirectResponse
    {
        return $this->transition($request, true);
    }

    /**
     * Validate and execute an admin transition.
     */
    private function transition(Request $request, bool $reverse): RedirectResponse
    {
        $admin = User::mustAuth();
        $validated = $this->validateRequest($request, [
            'reason' => GiftVoucherValidity::inject()->reason()->required()->toArray(),
        ]);
        $query = GiftVoucher::query()->with('giftVoucherBatch');
        GiftVoucher::scopeForUser($query, $admin);
        $voucher = Typer::assertInstance(
            $query->whereKey(Typer::parseInt($request->route('voucher')))->firstOrFail(),
            GiftVoucher::class,
        );
        $service = new GiftVoucherService();

        if ($reverse) {
            $service->reverseRedemption($admin, $voucher, $validated->assertString('reason'));
            Inertia::flash('success', \__('Gift voucher redemption reversed.'));
        } else {
            $service->void($admin, $voucher, $validated->assertString('reason'));
            Inertia::flash('success', \__('Gift voucher voided.'));
        }

        return Resolver::resolveRedirector()->back();
    }
}
