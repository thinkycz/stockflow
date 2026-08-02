<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\GiftVoucher;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\GiftVoucherValidity;
use App\Models\User;
use App\Services\GiftVoucherBrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class GiftVoucherSettingController
{
    use ValidatesWebRequests;

    /**
     * Save current customer-facing voucher branding.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validity = GiftVoucherValidity::inject();
        $validated = $this->validateRequest($request, [
            'public_name' => $validity->publicName()->required()->toArray(),
            'message' => $validity->message()->nullable()->toArray(),
            'logo' => $validity->logo()->nullable()->toArray(),
            'remove_logo' => $validity->removeLogo()->nullable()->toArray(),
        ]);
        (new GiftVoucherBrandingService())->update(
            User::mustAuth(),
            $validated->assertString('public_name'),
            $validated->assertNullableString('message'),
            $validated->assertNullableFile('logo'),
            $validated->parseBool('remove_logo'),
        );
        Inertia::flash('success', \__('Gift voucher branding saved.'));

        return Resolver::resolveRedirector()->route('gift-vouchers.index', ['tab' => 'settings']);
    }
}
