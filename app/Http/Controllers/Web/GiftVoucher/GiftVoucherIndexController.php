<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\GiftVoucher;

use App\Enums\GiftVoucherStatusEnum;
use App\Models\GiftVoucher;
use App\Models\GiftVoucherBatch;
use App\Models\GiftVoucherEvent;
use App\Models\GiftVoucherSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\GiftVoucherBrandingService;
use App\Services\GiftVoucherService;
use App\Support\ActiveStoreResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class GiftVoucherIndexController
{
    /**
     * Maximum number of recent batches rendered in the overview.
     */
    public const int TAKE = 50;

    /**
     * Render the role-aware voucher section.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        if (!User::mustAuth()->isAdmin()) {
            return Resolver::resolveRedirector()->route('gift-vouchers.redeem-page');
        }
        $legacy = Typer::parseNullableString($request->query('tab'));
        $destination = match ($legacy) {
            'issue' => 'gift-voucher-batches.create',
            'settings' => 'gift-voucher-settings.edit',
            'redeem' => 'gift-vouchers.redeem-page',
            'overview' => 'gift-vouchers.index',
            default => null,
        };
        if ($destination !== null) {
            return Resolver::resolveRedirector()->route($destination, $request->except('tab'));
        }

        return $this->render($request, 'Index');
    }

    /**
     * Render the batch issuance form.
     */
    public function create(Request $request): Response
    {
        return $this->render($request, 'Create');
    }

    /**
     * Render voucher branding settings.
     */
    public function settings(Request $request): Response
    {
        return $this->render($request, 'Settings');
    }

    /**
     * Render voucher lookup and redemption.
     */
    public function redeem(Request $request): Response
    {
        return $this->render($request, 'Redeem');
    }

    /**
     * Render only the data needed by the requested voucher page.
     */
    private function render(Request $request, string $page): Response
    {
        $actor = User::mustAuth();
        $owner = $actor->resolveScopeUser();
        $store = ActiveStoreResolver::resolve($request, $actor);
        $setting = $actor->isAdmin() && \in_array($page, ['Create', 'Settings'], true)
            ? GiftVoucherSetting::query()->where('user_id', $owner->getKey())->first()
            : null;

        return Inertia::render('gift-vouchers/' . $page, [
            'is_admin' => $actor->isAdmin(),
            'can_redeem' => $store instanceof Store && !$store->isWarehouse(),
            'lookup' => $page === 'Redeem' ? $this->lookup($request, $owner) : null,
            'setting' => $setting instanceof GiftVoucherSetting ? [
                'public_name' => $setting->getPublicName(),
                'message' => $setting->getMessage(),
                'logo' => (new GiftVoucherBrandingService())->dataUri($setting->getLogoPath(), $setting->getLogoMime()),
            ] : null,
            'batches' => $actor->isAdmin() && $page === 'Index' ? $this->batches($request, $owner) : [],
            'filters' => [
                'status' => Typer::parseNullableString($request->query('status')),
                'search' => Typer::parseNullableString($request->query('search')),
            ],
        ]);
    }

    /**
     * Resolve a still-valid lookup candidate from the session.
     *
     * @return array<string, mixed>|null
     */
    private function lookup(Request $request, User $owner): array|null
    {
        $candidate = $request->session()->get(GiftVoucherLookupController::SESSION_KEY);
        if (!\is_array($candidate)) {
            return null;
        }

        $voucherId = Typer::parseNullableInt($candidate['voucher_id'] ?? null);
        $actorId = Typer::parseNullableInt($candidate['actor_user_id'] ?? null);
        $expiresAt = Typer::parseNullableInt($candidate['expires_at'] ?? null);
        $ticket = Typer::parseNullableString($candidate['ticket'] ?? null);
        $actor = User::mustAuth();

        if (
            $voucherId === null ||
            $actorId !== $actor->getKey() ||
            $expiresAt === null ||
            $expiresAt < CarbonImmutable::now()->timestamp ||
            $ticket === null
        ) {
            $request->session()->forget(GiftVoucherLookupController::SESSION_KEY);

            return null;
        }

        $query = GiftVoucher::query()->with('giftVoucherBatch');
        GiftVoucher::scopeForUser($query, $owner);
        $voucher = $query->whereKey($voucherId)->first();

        return $voucher instanceof GiftVoucher ? [
            'voucher_id' => $voucher->getKey(),
            'ticket' => $ticket,
            'amount' => $voucher->getGiftVoucherBatch()->getAmount(),
            'expires_at' => $voucher->getGiftVoucherBatch()->getExpiresAt()?->toJSON(),
            'status' => $voucher->getEffectiveStatus()->value,
            'code_suffix' => \mb_substr($voucher->getCode(), -4),
        ] : null;
    }

    /**
     * Build the latest admin-visible batches and voucher rows.
     *
     * @return list<array<string, mixed>>
     */
    private function batches(Request $request, User $owner): array
    {
        $query = GiftVoucherBatch::query()->with(['giftVouchers.giftVoucherEvents']);
        GiftVoucherBatch::scopeForUser($query, $owner);
        $batches = $query->latest('id')->limit(self::TAKE)->get();
        $status = Typer::parseNullableString($request->query('status'));
        $search = Typer::parseNullableString($request->query('search'));
        $searchHash = $search === null || $search === '' ? null : GiftVoucherService::hashCode($search);

        return \array_values($batches->map(function (GiftVoucherBatch $batch) use ($status, $searchHash): array {
            $vouchers = $batch->getGiftVouchers()
                ->filter(static function (GiftVoucher $voucher) use ($status, $searchHash): bool {
                    return ($status === null || $status === '' || $status === $voucher->getEffectiveStatus()->value) &&
                        ($searchHash === null || $searchHash === $voucher->getAttribute('code_hash'));
                })
                ->map(static fn(GiftVoucher $voucher): array => [
                    'id' => $voucher->getKey(),
                    'code' => $voucher->getCode(),
                    'status' => $voucher->getEffectiveStatus()->value,
                    'redeemed_at' => $voucher->getRedeemedAt()?->toJSON(),
                    'redeemed_store_id' => $voucher->getRedeemedStoreId(),
                    'events' => $voucher->getGiftVoucherEvents()->map(static fn(GiftVoucherEvent $event): array => [
                        'type' => $event->getType()->value,
                        'reason' => $event->getReason(),
                        'created_at' => $event->getCreatedAt()->toJSON(),
                    ])->values()->all(),
                ])
                ->values()
                ->all();

            $all = $batch->getGiftVouchers();
            $counts = [];
            foreach (GiftVoucherStatusEnum::cases() as $case) {
                $counts[$case->value] = $all->filter(
                    static fn(GiftVoucher $voucher): bool => $case === $voucher->getEffectiveStatus(),
                )->count();
            }

            return [
                'id' => $batch->getKey(),
                'quantity' => $batch->getQuantity(),
                'amount' => $batch->getAmount(),
                'expires_at' => $batch->getExpiresAt()?->toJSON(),
                'brand_name' => $batch->getBrandName(),
                'created_at' => $batch->getCreatedAt()->toJSON(),
                'counts' => $counts,
                'vouchers' => $vouchers,
            ];
        })->filter(static fn(array $batch): bool => $status === null && $searchHash === null || $batch['vouchers'] !== [])->values()->all());
    }
}
