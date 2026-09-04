<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\GiftVoucher;

use App\Domain\GiftVouchers\GiftVoucherService;
use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\GiftVoucherValidity;
use App\Models\GiftVoucher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;

class GiftVoucherLookupController
{
    use ThrottlesWebRequests;
    use ValidatesWebRequests;

    public const SESSION_KEY = 'gift_voucher_redemption_candidate';

    /**
     * Validate a code and issue a short-lived session-bound ticket.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        self::$throttle = 30;
        self::$decay = 1;
        $validated = $this->validateRequest($request, [
            'code' => GiftVoucherValidity::inject()->code()->required()->toArray(),
        ]);
        $this->hit($this->limit());
        $actor = User::mustAuth();
        $voucher = (new GiftVoucherService())->findByCode($actor, $validated->assertString('code'));

        if (!$voucher instanceof GiftVoucher) {
            Thrower::default()->message('code', \__('Gift voucher code was not found.'))->throw();
        }

        $request->session()->put(self::SESSION_KEY, [
            'voucher_id' => $voucher->getKey(),
            'actor_user_id' => $actor->getKey(),
            'ticket' => \bin2hex(\random_bytes(32)),
            'expires_at' => CarbonImmutable::now()->addMinutes(5)->timestamp,
        ]);

        return Resolver::resolveRedirector()->route('gift-vouchers.redeem-page');
    }
}
