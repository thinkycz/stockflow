<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\GiftVoucher;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\GiftVoucherValidity;
use App\Models\GiftVoucherSetting;
use App\Models\User;
use App\Services\GiftVoucherBrandingService;
use App\Services\GiftVoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;

class GiftVoucherBatchController
{
    use ValidatesWebRequests;

    /**
     * Issue a new voucher batch from current branding.
     */
    public function store(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $validity = GiftVoucherValidity::inject();
        $validated = $this->validateRequest($request, [
            'quantity' => $validity->quantity()->required()->toArray(),
            'amount' => $validity->amount()->required()->toArray(),
            'expires_on' => $validity->expiresOn()->nullable()->toArray(),
        ]);
        $setting = GiftVoucherSetting::query()->where('user_id', $admin->getKey())->first();

        if (!$setting instanceof GiftVoucherSetting) {
            Thrower::default()->message('branding', \__('Configure gift voucher branding before issuing a batch.'))->throw();
        }

        $branding = new GiftVoucherBrandingService();
        $snapshot = $branding->snapshotLogo($setting);
        $batch = (new GiftVoucherService())->issue(
            $admin,
            $setting,
            $validated->parseInt('quantity'),
            $validated->assertString('amount'),
            $validated->assertNullableString('expires_on'),
            $snapshot,
        );
        Inertia::flash('success', \__('Gift voucher batch issued.'));

        return Resolver::resolveRedirector()->route('gift-vouchers.index', [
            'tab' => 'overview',
            'batch' => $batch->getKey(),
        ]);
    }
}
