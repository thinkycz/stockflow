<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\GiftVoucher;
use App\Models\GiftVoucherBatch;
use App\Models\GiftVoucherEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadGiftVouchersTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string { return 'read_gift_vouchers'; }

    /**
     * Explain the redacted voucher datasets available to the model.
     */
    public function description(): string { return 'Read safe voucher batches or voucher lifecycle data with amounts, expiry, status and events. Voucher codes, hashes, bearer data, and binary branding are never returned.'; }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $voucherFilters = ['status' => $schema->string()->enum(['active', 'expired', 'redeemed', 'voided']), 'date_from' => $schema->string(), 'date_to' => $schema->string(), 'batch_id' => $schema->integer()];
        $batchFilters = ['date_from' => $schema->string(), 'date_to' => $schema->string()];

        return ['request' => $schema->anyOf([
            $schema->object(['operation' => $schema->string()->enum(['list'])->required(), 'dataset' => $schema->string()->enum(['vouchers'])->required(), ...$voucherFilters, 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(),
            $schema->object(['operation' => $schema->string()->enum(['list'])->required(), 'dataset' => $schema->string()->enum(['batches'])->required(), ...$batchFilters, 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(),
            $schema->object(['operation' => $schema->string()->enum(['detail'])->required(), 'dataset' => $schema->string()->enum(['batches', 'vouchers'])->required(), 'id' => $schema->integer()->required()])->withoutAdditionalProperties(),
            $schema->object(['operation' => $schema->string()->enum(['summary'])->required(), 'dataset' => $schema->string()->enum(['vouchers'])->required(), ...$voucherFilters])->withoutAdditionalProperties(),
            $schema->object(['operation' => $schema->string()->enum(['summary'])->required(), 'dataset' => $schema->string()->enum(['batches'])->required(), ...$batchFilters])->withoutAdditionalProperties(),
        ])->required()];
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    protected function execute(array $request): array { $operation = Typer::parseNullableString($request['operation'] ?? null) ?? 'list';
        $dataset = $this->dataset($request);
        if ($dataset === 'batches') { return $this->batches($request, $operation); } if ($dataset !== 'vouchers') { throw new InvalidArgumentException('Unknown gift voucher dataset.'); } $query = GiftVoucher::query()->with(['giftVoucherBatch', 'giftVoucherEvents']);
        GiftVoucher::scopeForUser($query, $this->actor->resolveScopeUser());
        $batchId = Typer::parseNullableInt($request['batch_id'] ?? null);
        if ($batchId !== null) { $query->where('gift_voucher_batch_id', $batchId); } $status = Typer::parseNullableString($request['status'] ?? null);
        if ($status === 'expired') { $query->where('status', 'active')->whereHas('giftVoucherBatch', static fn($batches) => $batches->whereNotNull('expires_at')->where('expires_at', '<', Carbon::now())); } elseif ($status === 'active') { $query->where('status', 'active')->whereHas('giftVoucherBatch', static fn($batches) => $batches->whereNull('expires_at')->orWhere('expires_at', '>=', Carbon::now())); } elseif ($status !== null) { $query->where('status', $status); } $from = Typer::parseNullableString($request['date_from'] ?? null);
        if ($from !== null) { $query->where('created_at', '>=', $from . ' 00:00:00'); } $to = Typer::parseNullableString($request['date_to'] ?? null);
        if ($to !== null) { $query->where('created_at', '<=', $to . ' 23:59:59'); } if ($operation === 'detail') { $id = Typer::parseNullableInt($request['id'] ?? null);
            if ($id === null) { throw new InvalidArgumentException('A voucher identifier is required.'); }

            return $this->detailResult($request, 'vouchers', $this->voucherRecord($query->findOrFail($id), true)); } if ($operation === 'summary') { $vouchers = $query->get();

                return $this->summaryResult($request, 'vouchers', ['voucher_count' => $vouchers->count(), 'nominal_value' => \round($vouchers->sum(static fn(GiftVoucher $voucher): float => $voucher->getGiftVoucherBatch()->getAmount()), 2), 'by_status' => $vouchers->countBy(static fn(GiftVoucher $voucher): string => $voucher->getEffectiveStatus()->value)->all()], $vouchers->isEmpty() ? 'NO_MATCHING_DATA' : null); } if ($operation !== 'list') { throw new InvalidArgumentException('Unknown voucher read operation.'); }

        return $this->paginateById($query, $request, 'vouchers', $request, fn(GiftVoucher $voucher): array => $this->voucherRecord($voucher, false)); }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string { return 'gift_vouchers'; }

    /**
     * @param array<string, mixed> $request
     */
    protected function dataset(array $request): string { return Typer::parseNullableString($request['dataset'] ?? null) ?? 'vouchers'; }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function batches(array $request, string $operation): array { $query = GiftVoucherBatch::query()->where('user_id', $this->actor->resolveScopeUser()->getKey())->withCount([
        'giftVouchers as active_vouchers_count' => static fn(Builder $vouchers): Builder => $vouchers->where('status', 'active'),
        'giftVouchers as redeemed_vouchers_count' => static fn(Builder $vouchers): Builder => $vouchers->where('status', 'redeemed'),
        'giftVouchers as voided_vouchers_count' => static fn(Builder $vouchers): Builder => $vouchers->where('status', 'voided'),
    ]);
        $from = Typer::parseNullableString($request['date_from'] ?? null);
        if ($from !== null) { $query->where('created_at', '>=', $from . ' 00:00:00'); }
        $to = Typer::parseNullableString($request['date_to'] ?? null);
        if ($to !== null) { $query->where('created_at', '<=', $to . ' 23:59:59'); }
        $map = static function (GiftVoucherBatch $batch): array { $active = Typer::parseInt($batch->getAttribute('active_vouchers_count'));
            $expired = $batch->getExpiresAt()?->isPast() === true ? $active : 0;

            return ['id' => $batch->getKey(), 'quantity' => $batch->getQuantity(), 'amount' => $batch->getAmount(), 'total_nominal_value' => \round($batch->getQuantity() * $batch->getAmount(), 2), 'expires_at' => $batch->getExpiresAt()?->toJSON(), 'brand_name' => $batch->getBrandName(), 'brand_message' => $batch->getBrandMessage(), 'has_logo' => $batch->getBrandLogoPath() !== null, 'binary_content_excluded' => $batch->getBrandLogoPath() !== null, 'status_counts' => ['active' => $expired === 0 ? $active : 0, 'expired' => $expired, 'redeemed' => Typer::parseInt($batch->getAttribute('redeemed_vouchers_count')), 'voided' => Typer::parseInt($batch->getAttribute('voided_vouchers_count'))], 'url' => Resolver::resolveUrlGenerator()->route('gift-vouchers.index')]; };
        if ($operation === 'detail') { $id = Typer::parseNullableInt($request['id'] ?? null);
            if ($id === null) { throw new InvalidArgumentException('A voucher batch identifier is required.'); }

            return $this->detailResult($request, 'batches', $map($query->findOrFail($id))); } if ($operation === 'summary') { $batches = $query->get();

                return $this->summaryResult($request, 'batches', ['batch_count' => $batches->count(), 'voucher_count' => $batches->sum(static fn(GiftVoucherBatch $batch): int => $batch->getQuantity()), 'nominal_value' => \round($batches->sum(static fn(GiftVoucherBatch $batch): float => $batch->getQuantity() * $batch->getAmount()), 2)], $batches->isEmpty() ? 'NO_MATCHING_DATA' : null); } if ($operation !== 'list') { throw new InvalidArgumentException('Unknown voucher batch operation.'); }

        return $this->paginateById($query, $request, 'batches', $request, $map); }

    /**
     * @return array<string, mixed>
     */
    private function voucherRecord(GiftVoucher $voucher, bool $events): array { $batch = $voucher->getGiftVoucherBatch();
        $record = ['id' => $voucher->getKey(), 'batch_id' => $batch->getKey(), 'amount' => $batch->getAmount(), 'expires_at' => $batch->getExpiresAt()?->toJSON(), 'status' => $voucher->getEffectiveStatus()->value, 'redeemed_at' => $voucher->getRedeemedAt()?->toJSON(), 'redeemed_store_id' => $voucher->getRedeemedStoreId(), 'code_redacted' => true, 'url' => Resolver::resolveUrlGenerator()->route('gift-vouchers.index')];
        if ($events) { $record['events'] = $voucher->getGiftVoucherEvents()->map(static fn(GiftVoucherEvent $event): array => ['id' => $event->getKey(), 'type' => $event->getType()->value, 'reason' => $event->getReason()])->values()->all(); }

        return $record; }
}
