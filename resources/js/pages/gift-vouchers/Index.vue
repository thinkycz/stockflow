<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Ban,
    Plus,
    Printer,
    RotateCcw,
    Search,
    Settings,
    TicketCheck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Select from '@/components/ui/Select.vue';
import FilterField from '@/components/ui/FilterField.vue';
import SearchFilter from '@/components/ui/SearchFilter.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import type {
    GiftVoucherBatch,
    GiftVoucherRow,
    GiftVoucherStatus,
} from '@/types/gift-vouchers';

const props = defineProps<{
    batches: GiftVoucherBatch[];
    filters: { status: string | null; search: string | null };
}>();

const { t } = useI18n();
useBoundLocale();
const route = useRoute();
const dialog = useDialog();

const filtering = ref(false);
const filterStatus = ref(props.filters.status ?? '');
const filterSearch = ref(props.filters.search ?? '');

const statusOptions = computed(() => [
    { value: '', label: t('gift_vouchers.filters.all') },
    { value: 'active', label: t('gift_vouchers.status.active') },
    { value: 'expired', label: t('gift_vouchers.status.expired') },
    { value: 'redeemed', label: t('gift_vouchers.status.redeemed') },
    { value: 'voided', label: t('gift_vouchers.status.voided') },
]);

function applyFilters(): void {
    router.get(
        route('gift-vouchers.index'),
        {
            status: filterStatus.value || undefined,
            search: filterSearch.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                filtering.value = true;
            },
            onFinish: () => {
                filtering.value = false;
            },
        },
    );
}

function openPrint(url: string): void {
    const printWindow = window.open(url, '_blank');

    if (printWindow !== null) printWindow.opener = null;
}

async function voidVoucher(voucher: GiftVoucherRow): Promise<void> {
    const reason = await dialog.prompt({
        title: t('gift_vouchers.actions.void'),
        message: t('gift_vouchers.actions.void_help'),
        label: t('gift_vouchers.reason'),
        confirmLabel: t('gift_vouchers.actions.void'),
        required: true,
        maxLength: 500,
        variant: 'danger',
    });
    if (reason === null) return;
    router.post(route('gift-vouchers.void', voucher.id), { reason });
}

async function reverseVoucher(voucher: GiftVoucherRow): Promise<void> {
    const reason = await dialog.prompt({
        title: t('gift_vouchers.actions.reverse'),
        message: t('gift_vouchers.actions.reverse_help'),
        label: t('gift_vouchers.reason'),
        confirmLabel: t('gift_vouchers.actions.reverse'),
        required: true,
        maxLength: 500,
        variant: 'warning',
    });
    if (reason === null) return;
    router.post(route('gift-vouchers.reverse-redemption', voucher.id), {
        reason,
    });
}

function statusVariant(
    status: GiftVoucherStatus,
): 'success' | 'neutral' | 'incoming' | 'danger' {
    return (
        {
            active: 'success',
            expired: 'neutral',
            redeemed: 'incoming',
            voided: 'danger',
        } as const
    )[status];
}
</script>
<template>
    <AppLayout :title="t('gift_vouchers.title')">
        <div class="flex flex-col gap-6">
            <PageHeader
                :title="t('gift_vouchers.title')"
                :subtitle="t('gift_vouchers.subtitle')"
            >
                <template #actions>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="route('gift-vouchers.redeem-page')">
                            <Button variant="secondary">
                                <TicketCheck :size="14" />{{
                                    t('gift_vouchers.tabs.redeem')
                                }}</Button
                            >
                        </Link>
                        <div class="flex items-center gap-2">
                            <Link :href="route('gift-voucher-batches.create')">
                                <Button>
                                    <Plus :size="14" />{{
                                        t('gift_vouchers.issue.title')
                                    }}</Button
                                >
                            </Link>
                            <Link :href="route('gift-voucher-settings.edit')">
                                <Button variant="secondary">
                                    <Settings :size="14" />{{
                                        t('gift_vouchers.tabs.settings')
                                    }}</Button
                                >
                            </Link>
                        </div>
                    </div>
                </template>
            </PageHeader>
            <section class="space-y-5">
                <Card class="flex flex-col gap-4 lg:flex-row lg:items-end">
                    <SearchFilter
                        id="voucher_search"
                        v-model="filterSearch"
                        :label="t('gift_vouchers.filters.code')"
                        :placeholder="
                            t('gift_vouchers.filters.code_placeholder')
                        "
                        :busy="filtering"
                        class="lg:flex-1"
                    />
                    <FilterField
                        for="voucher_status"
                        :label="t('gift_vouchers.filters.status')"
                        class="lg:w-48"
                    >
                        <Select
                            id="voucher_status"
                            v-model="filterStatus"
                            :options="statusOptions"
                        />
                    </FilterField>
                    <Button variant="secondary" @click="applyFilters">
                        <Search :size="15" />
                        {{ t('common.search') }}
                    </Button>
                </Card>

                <EmptyState
                    v-if="batches.length === 0"
                    :title="t('gift_vouchers.empty.title')"
                    :description="t('gift_vouchers.empty.description')"
                />

                <Card
                    v-for="batch in batches"
                    v-else
                    :key="batch.id"
                    class="space-y-5"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div>
                            <p
                                class="font-mono text-[10px] font-semibold tracking-wider text-on-surface-variant uppercase"
                            >
                                {{
                                    t('gift_vouchers.batch_number', {
                                        id: batch.id,
                                    })
                                }}
                            </p>
                            <div
                                class="mt-1 flex flex-wrap items-baseline gap-3"
                            >
                                <h2
                                    class="font-heading text-xl font-bold text-on-surface"
                                >
                                    {{ formatMoney(batch.amount) }}
                                </h2>
                                <span class="text-xs text-on-surface-variant">
                                    {{
                                        t('gift_vouchers.batch_count', {
                                            count: batch.quantity,
                                        })
                                    }}
                                    · {{ formatDate(batch.created_at) }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <Badge
                                    v-for="statusName in [
                                        'active',
                                        'redeemed',
                                        'expired',
                                        'voided',
                                    ] as GiftVoucherStatus[]"
                                    :key="statusName"
                                    :variant="statusVariant(statusName)"
                                >
                                    {{
                                        t('gift_vouchers.status.' + statusName)
                                    }}:
                                    {{ batch.counts[statusName] }}
                                </Badge>
                            </div>
                        </div>
                        <Button
                            v-if="batch.counts.active > 0"
                            variant="secondary"
                            @click="
                                openPrint(
                                    route(
                                        'gift-voucher-batches.print',
                                        batch.id,
                                    ),
                                )
                            "
                        >
                            <Printer :size="15" />
                            {{ t('gift_vouchers.actions.print_active') }}
                        </Button>
                    </div>

                    <DataTable v-if="batch.vouchers.length" density="compact">
                        <thead>
                            <tr>
                                <th>{{ t('gift_vouchers.code') }}</th>
                                <th>{{ t('gift_vouchers.columns.status') }}</th>
                                <th>
                                    {{ t('gift_vouchers.columns.redeemed_at') }}
                                </th>
                                <th class="w-0">
                                    {{ t('gift_vouchers.columns.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="voucher in batch.vouchers"
                                :key="voucher.id"
                            >
                                <td
                                    :data-label="t('gift_vouchers.code')"
                                    class="font-mono font-semibold tracking-wider"
                                >
                                    {{ voucher.code }}
                                </td>
                                <td
                                    :data-label="
                                        t('gift_vouchers.columns.status')
                                    "
                                >
                                    <Badge
                                        :variant="statusVariant(voucher.status)"
                                    >
                                        {{
                                            t(
                                                'gift_vouchers.status.' +
                                                    voucher.status,
                                            )
                                        }}
                                    </Badge>
                                </td>
                                <td
                                    :data-label="
                                        t('gift_vouchers.columns.redeemed_at')
                                    "
                                >
                                    {{
                                        voucher.redeemed_at
                                            ? formatDateTime(
                                                  voucher.redeemed_at,
                                              )
                                            : '—'
                                    }}
                                </td>
                                <td
                                    :data-label="
                                        t('gift_vouchers.columns.actions')
                                    "
                                >
                                    <div class="flex items-center gap-1">
                                        <Button
                                            v-if="voucher.status === 'active'"
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="
                                                t('gift_vouchers.actions.print')
                                            "
                                            @click="
                                                openPrint(
                                                    route(
                                                        'gift-vouchers.print',
                                                        voucher.id,
                                                    ),
                                                )
                                            "
                                        >
                                            <Printer :size="14" />
                                        </Button>
                                        <Button
                                            v-if="voucher.status === 'active'"
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="
                                                t('gift_vouchers.actions.void')
                                            "
                                            @click="voidVoucher(voucher)"
                                        >
                                            <Ban :size="14" />
                                        </Button>
                                        <Button
                                            v-if="voucher.status === 'redeemed'"
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="
                                                t(
                                                    'gift_vouchers.actions.reverse',
                                                )
                                            "
                                            @click="reverseVoucher(voucher)"
                                        >
                                            <RotateCcw :size="14" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </Card>
            </section>
        </div>
    </AppLayout>
</template>
