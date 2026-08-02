<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\GiftVoucher;

use App\Enums\GiftVoucherStatusEnum;
use App\Models\GiftVoucher;
use App\Models\GiftVoucherBatch;
use App\Models\User;
use App\Services\GiftVoucherBrandingService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class GiftVoucherPrintController
{
    /**
     * Print all currently active vouchers from one owned batch.
     */
    public function batch(Request $request): Response
    {
        $admin = User::mustAuth();
        $query = GiftVoucherBatch::query()->with('giftVouchers.giftVoucherBatch');
        GiftVoucherBatch::scopeForUser($query, $admin);
        $batch = Typer::assertInstance(
            $query->whereKey(Typer::parseInt($request->route('batch')))->firstOrFail(),
            GiftVoucherBatch::class,
        );
        $vouchers = \array_values($batch->getGiftVouchers()
            ->filter(static fn(GiftVoucher $voucher): bool => $voucher->getEffectiveStatus() === GiftVoucherStatusEnum::Active)
            ->values()
            ->all());

        return $this->render($batch, $vouchers);
    }

    /**
     * Print one currently active owned voucher.
     */
    public function voucher(Request $request): Response
    {
        $admin = User::mustAuth();
        $query = GiftVoucher::query()->with('giftVoucherBatch');
        GiftVoucher::scopeForUser($query, $admin);
        $voucher = Typer::assertInstance(
            $query->whereKey(Typer::parseInt($request->route('voucher')))->firstOrFail(),
            GiftVoucher::class,
        );

        if ($voucher->getEffectiveStatus() !== GiftVoucherStatusEnum::Active) {
            \abort(404);
        }

        return $this->render($voucher->getGiftVoucherBatch(), [$voucher]);
    }

    /**
     * Build three-up printable sheets with pre-rendered SVG QR data URIs.
     *
     * @param list<GiftVoucher> $vouchers
     */
    private function render(GiftVoucherBatch $batch, array $vouchers): Response
    {
        $rows = \array_map(fn(GiftVoucher $voucher): array => [
            'id' => $voucher->getKey(),
            'code' => $voucher->getCode(),
            'amount' => $batch->getAmount(),
            'qr' => $this->qr($voucher->getCode()),
        ], $vouchers);

        return Inertia::render('gift-vouchers/Print', [
            'batch' => [
                'id' => $batch->getKey(),
                'brand_name' => $batch->getBrandName(),
                'brand_message' => $batch->getBrandMessage(),
                'brand_logo' => (new GiftVoucherBrandingService())->dataUri(
                    $batch->getBrandLogoPath(),
                    $batch->getBrandLogoMime(),
                ),
                'expires_at' => $batch->getExpiresAt()?->toJSON(),
            ],
            'sheets' => \array_chunk($rows, 3),
        ]);
    }

    /**
     * Generate a sharp scanner-compatible SVG data URI.
     */
    private function qr(string $code): string
    {
        return (new Builder(
            writer: new SvgWriter(),
            writerOptions: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true],
            data: $code,
            encoding: new Encoding('ISO-8859-1'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 320,
            margin: 12,
            roundBlockSizeMode: RoundBlockSizeMode::None,
        ))->build()->getDataUri();
    }
}
