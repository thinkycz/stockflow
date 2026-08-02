<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Ban,
    CheckCircle2,
    Gift,
    History,
    Printer,
    RotateCcw,
    Search,
    Settings,
    Sparkles,
    TicketCheck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import type {
    GiftVoucherBatch,
    GiftVoucherLookup,
    GiftVoucherRow,
    GiftVoucherStatus,
} from '@/types/gift-vouchers';

const props = defineProps<{
    is_admin: boolean;
    can_redeem: boolean;
    lookup: GiftVoucherLookup | null;
    setting: {
        public_name: string;
        message: string | null;
        logo: string | null;
    } | null;
    batches: GiftVoucherBatch[];
    filters: { status: string | null; search: string | null };
}>();

const { t } = useI18n();
useBoundLocale();
const route = useRoute();
const dialog = useDialog();

type Tab = 'redeem' | 'overview' | 'issue' | 'settings';
const initialTab = new URLSearchParams(window.location.search).get('tab');
const tab = ref<Tab>(
    props.is_admin &&
        ['overview', 'issue', 'settings'].includes(initialTab ?? '')
        ? (initialTab as Tab)
        : 'redeem',
);

const lookupForm = useForm({ code: '' });
const redeemForm = useForm({ ticket: '' });
const issueForm = useForm({
    quantity: 10,
    amount: '',
    expires_on: '',
    branding: '',
});
const brandingForm = useForm<{
    public_name: string;
    message: string;
    logo: File | null;
    remove_logo: boolean;
}>({
    public_name: props.setting?.public_name ?? '',
    message: props.setting?.message ?? '',
    logo: null,
    remove_logo: false,
});
const filterStatus = ref(props.filters.status ?? '');
const filterSearch = ref(props.filters.search ?? '');

const tabs = computed(() => {
    const rows: Array<{ key: Tab; label: string; icon: typeof Gift }> = [
        {
            key: 'redeem',
            label: t('gift_vouchers.tabs.redeem'),
            icon: TicketCheck,
        },
    ];
    if (props.is_admin) {
        rows.push(
            {
                key: 'overview',
                label: t('gift_vouchers.tabs.overview'),
                icon: History,
            },
            {
                key: 'issue',
                label: t('gift_vouchers.tabs.issue'),
                icon: Sparkles,
            },
            {
                key: 'settings',
                label: t('gift_vouchers.tabs.settings'),
                icon: Settings,
            },
        );
    }
    return rows;
});

const statusOptions = computed(() => [
    { value: '', label: t('gift_vouchers.filters.all') },
    { value: 'active', label: t('gift_vouchers.status.active') },
    { value: 'expired', label: t('gift_vouchers.status.expired') },
    { value: 'redeemed', label: t('gift_vouchers.status.redeemed') },
    { value: 'voided', label: t('gift_vouchers.status.voided') },
]);

function selectTab(value: Tab): void {
    tab.value = value;
    window.history.replaceState(
        {},
        '',
        route('gift-vouchers.index', value === 'redeem' ? {} : { tab: value }),
    );
}

function submitLookup(): void {
    lookupForm.post(route('gift-vouchers.lookup'), {
        preserveScroll: true,
        onSuccess: () => lookupForm.reset(),
    });
}

function redeem(): void {
    if (props.lookup === null) return;
    redeemForm.ticket = props.lookup.ticket;
    redeemForm.post(route('gift-vouchers.redeem', props.lookup.voucher_id), {
        preserveScroll: true,
    });
}

function issue(): void {
    issueForm.post(route('gift-voucher-batches.store'), {
        onSuccess: () => {
            tab.value = 'overview';
        },
    });
}

function saveBranding(): void {
    brandingForm.put(route('gift-voucher-settings.update'), {
        forceFormData: true,
        preserveScroll: true,
    });
}

function chooseLogo(event: Event): void {
    brandingForm.logo = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (brandingForm.logo !== null) brandingForm.remove_logo = false;
}

function applyFilters(): void {
    router.get(
        route('gift-vouchers.index'),
        {
            tab: 'overview',
            status: filterStatus.value || undefined,
            search: filterSearch.value || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
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

function statusClass(status: GiftVoucherStatus): string {
    return {
        active: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        expired: 'bg-slate-100 text-slate-600 ring-slate-200',
        redeemed: 'bg-blue-50 text-blue-700 ring-blue-200',
        voided: 'bg-red-50 text-red-700 ring-red-200',
    }[status];
}
</script>

<template>
    <AppLayout :title="t('gift_vouchers.title')">
        <Head :title="t('gift_vouchers.title')" />

        <div class="flex flex-col gap-6">
            <header class="flex items-center gap-3">
                <div
                    class="flex size-11 items-center justify-center rounded-2xl bg-primary text-white shadow-sm"
                >
                    <Gift :size="21" />
                </div>
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('gift_vouchers.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('gift_vouchers.subtitle') }}
                    </p>
                </div>
            </header>

            <nav
                class="flex gap-1 overflow-x-auto rounded-2xl border border-outline-glass bg-white p-1.5"
                :aria-label="t('gift_vouchers.title')"
            >
                <Button
                    v-for="item in tabs"
                    :key="item.key"
                    type="button"
                    variant="ghost"
                    :class="
                        'min-w-fit ' +
                        (tab === item.key
                            ? 'bg-primary text-white shadow-sm'
                            : 'text-on-surface-variant hover:bg-surface-container-low')
                    "
                    @click="selectTab(item.key)"
                >
                    <component :is="item.icon" :size="15" />
                    {{ item.label }}
                </Button>
            </nav>

            <section v-if="tab === 'redeem'" class="mx-auto w-full max-w-2xl">
                <Card class="overflow-hidden p-0">
                    <div
                        class="bg-gradient-to-br from-primary via-primary-container to-secondary-cyan px-6 py-7 text-white sm:px-8"
                    >
                        <p
                            class="font-mono text-[10px] font-semibold tracking-[0.2em] text-white/70 uppercase"
                        >
                            {{ t('gift_vouchers.redeem.eyebrow') }}
                        </p>
                        <h2 class="mt-2 font-heading text-2xl font-bold">
                            {{ t('gift_vouchers.redeem.title') }}
                        </h2>
                        <p class="mt-2 max-w-lg text-sm text-white/75">
                            {{ t('gift_vouchers.redeem.description') }}
                        </p>
                    </div>

                    <div class="space-y-5 p-6 sm:p-8">
                        <div
                            v-if="!can_redeem"
                            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-medium text-amber-900"
                        >
                            {{ t('gift_vouchers.redeem.retail_required') }}
                        </div>

                        <form class="space-y-3" @submit.prevent="submitLookup">
                            <Label for="voucher_code" :required="true">
                                {{ t('gift_vouchers.code') }}
                            </Label>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <Input
                                    id="voucher_code"
                                    v-model="lookupForm.code"
                                    class="font-mono uppercase tracking-[0.16em]"
                                    autocomplete="off"
                                    :placeholder="
                                        t(
                                            'gift_vouchers.redeem.code_placeholder',
                                        )
                                    "
                                    :invalid="Boolean(lookupForm.errors.code)"
                                    required
                                />
                                <Button
                                    type="submit"
                                    :disabled="
                                        lookupForm.processing || !can_redeem
                                    "
                                >
                                    <Search :size="15" />
                                    {{ t('gift_vouchers.redeem.check') }}
                                </Button>
                            </div>
                            <FieldError :message="lookupForm.errors.code" />
                        </form>

                        <div
                            v-if="lookup"
                            class="rounded-2xl border border-outline-glass bg-surface-container-low p-5"
                        >
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <CheckCircle2
                                            v-if="lookup.status === 'active'"
                                            :size="18"
                                            class="text-emerald-600"
                                        />
                                        <Ban
                                            v-else
                                            :size="18"
                                            class="text-error-red"
                                        />
                                        <span
                                            :class="[
                                                'rounded-full px-2.5 py-1 text-[10px] font-bold uppercase ring-1',
                                                statusClass(lookup.status),
                                            ]"
                                        >
                                            {{
                                                t(
                                                    'gift_vouchers.status.' +
                                                        lookup.status,
                                                )
                                            }}
                                        </span>
                                    </div>
                                    <p
                                        class="mt-3 font-heading text-3xl font-bold text-on-surface"
                                    >
                                        {{ formatMoney(lookup.amount) }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-on-surface-variant"
                                    >
                                        •••• {{ lookup.code_suffix }}
                                        <template v-if="lookup.expires_at">
                                            ·
                                            {{
                                                t('gift_vouchers.valid_until', {
                                                    date: formatDate(
                                                        lookup.expires_at,
                                                    ),
                                                })
                                            }}
                                        </template>
                                    </p>
                                </div>
                                <Button
                                    variant="success"
                                    :disabled="
                                        lookup.status !== 'active' ||
                                        redeemForm.processing
                                    "
                                    @click="redeem"
                                >
                                    <TicketCheck :size="16" />
                                    {{ t('gift_vouchers.redeem.confirm') }}
                                </Button>
                            </div>
                            <FieldError :message="redeemForm.errors.ticket" />
                        </div>
                    </div>
                </Card>
            </section>

            <section v-else-if="tab === 'overview'" class="space-y-5">
                <Card class="flex flex-col gap-4 lg:flex-row lg:items-end">
                    <div class="grid flex-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="voucher_status">
                                {{ t('gift_vouchers.filters.status') }}
                            </Label>
                            <Select
                                id="voucher_status"
                                v-model="filterStatus"
                                :options="statusOptions"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="voucher_search">
                                {{ t('gift_vouchers.filters.code') }}
                            </Label>
                            <Input
                                id="voucher_search"
                                v-model="filterSearch"
                                class="font-mono uppercase"
                                :placeholder="
                                    t('gift_vouchers.filters.code_placeholder')
                                "
                            />
                        </div>
                    </div>
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
                                <span
                                    v-for="statusName in [
                                        'active',
                                        'redeemed',
                                        'expired',
                                        'voided',
                                    ] as GiftVoucherStatus[]"
                                    :key="statusName"
                                    :class="[
                                        'rounded-full px-2.5 py-1 text-[10px] font-bold ring-1',
                                        statusClass(statusName),
                                    ]"
                                >
                                    {{
                                        t('gift_vouchers.status.' + statusName)
                                    }}:
                                    {{ batch.counts[statusName] }}
                                </span>
                            </div>
                        </div>
                        <Link
                            v-if="batch.counts.active > 0"
                            :href="
                                route('gift-voucher-batches.print', batch.id)
                            "
                            target="_blank"
                        >
                            <Button variant="secondary">
                                <Printer :size="15" />
                                {{ t('gift_vouchers.actions.print_active') }}
                            </Button>
                        </Link>
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
                                    <span
                                        :class="[
                                            'rounded-full px-2.5 py-1 text-[10px] font-bold uppercase ring-1',
                                            statusClass(voucher.status),
                                        ]"
                                    >
                                        {{
                                            t(
                                                'gift_vouchers.status.' +
                                                    voucher.status,
                                            )
                                        }}
                                    </span>
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
                                        <Link
                                            v-if="voucher.status === 'active'"
                                            :href="
                                                route(
                                                    'gift-vouchers.print',
                                                    voucher.id,
                                                )
                                            "
                                            target="_blank"
                                        >
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                :aria-label="
                                                    t(
                                                        'gift_vouchers.actions.print',
                                                    )
                                                "
                                            >
                                                <Printer :size="14" />
                                            </Button>
                                        </Link>
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

            <section
                v-else-if="tab === 'issue'"
                class="mx-auto w-full max-w-2xl"
            >
                <Card class="space-y-6">
                    <div>
                        <h2 class="font-heading text-xl font-bold">
                            {{ t('gift_vouchers.issue.title') }}
                        </h2>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            {{ t('gift_vouchers.issue.description') }}
                        </p>
                    </div>
                    <div
                        v-if="setting === null"
                        class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                    >
                        {{ t('gift_vouchers.issue.branding_required') }}
                        <Button
                            type="button"
                            variant="ghost"
                            size="compact"
                            class="ml-1 underline"
                            @click="selectTab('settings')"
                        >
                            {{ t('gift_vouchers.tabs.settings') }}
                        </Button>
                    </div>
                    <form class="space-y-5" @submit.prevent="issue">
                        <div class="space-y-2">
                            <Label for="voucher_quantity" :required="true">
                                {{ t('gift_vouchers.issue.quantity') }}
                            </Label>
                            <div class="flex gap-2">
                                <Button
                                    v-for="quick in [10, 20]"
                                    :key="quick"
                                    type="button"
                                    variant="secondary"
                                    :class="
                                        issueForm.quantity === quick
                                            ? 'border-primary bg-primary text-white'
                                            : 'border-outline-glass bg-white'
                                    "
                                    @click="issueForm.quantity = quick"
                                >
                                    {{ quick }}
                                </Button>
                                <Input
                                    id="voucher_quantity"
                                    v-model="issueForm.quantity"
                                    type="number"
                                    min="1"
                                    max="100"
                                    required
                                />
                            </div>
                            <FieldError :message="issueForm.errors.quantity" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="voucher_amount" :required="true">
                                    {{ t('gift_vouchers.issue.amount') }}
                                </Label>
                                <Input
                                    id="voucher_amount"
                                    v-model="issueForm.amount"
                                    type="number"
                                    min="0.01"
                                    max="999999.99"
                                    step="0.01"
                                    required
                                />
                                <FieldError
                                    :message="issueForm.errors.amount"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="voucher_expires">
                                    {{ t('gift_vouchers.issue.expires_on') }}
                                </Label>
                                <Input
                                    id="voucher_expires"
                                    v-model="issueForm.expires_on"
                                    type="date"
                                />
                                <FieldError
                                    :message="issueForm.errors.expires_on"
                                />
                            </div>
                        </div>
                        <FieldError :message="issueForm.errors.branding" />
                        <div
                            class="flex justify-end border-t border-outline-glass pt-5"
                        >
                            <Button
                                type="submit"
                                :disabled="
                                    issueForm.processing || setting === null
                                "
                            >
                                <Sparkles :size="15" />
                                {{ t('gift_vouchers.issue.submit') }}
                            </Button>
                        </div>
                    </form>
                </Card>
            </section>

            <section v-else class="mx-auto w-full max-w-2xl">
                <Card class="space-y-6">
                    <div>
                        <h2 class="font-heading text-xl font-bold">
                            {{ t('gift_vouchers.settings.title') }}
                        </h2>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            {{ t('gift_vouchers.settings.description') }}
                        </p>
                    </div>
                    <form class="space-y-5" @submit.prevent="saveBranding">
                        <div class="space-y-2">
                            <Label for="voucher_public_name" :required="true">
                                {{ t('gift_vouchers.settings.public_name') }}
                            </Label>
                            <Input
                                id="voucher_public_name"
                                v-model="brandingForm.public_name"
                                required
                            />
                            <FieldError
                                :message="brandingForm.errors.public_name"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="voucher_message">
                                {{ t('gift_vouchers.settings.message') }}
                            </Label>
                            <Textarea
                                id="voucher_message"
                                v-model="brandingForm.message"
                                :maxlength="240"
                                :rows="3"
                            />
                            <FieldError
                                :message="brandingForm.errors.message"
                            />
                        </div>
                        <div class="space-y-3">
                            <Label for="voucher_logo">
                                {{ t('gift_vouchers.settings.logo') }}
                            </Label>
                            <div
                                v-if="setting?.logo"
                                class="flex items-center gap-4 rounded-xl border border-outline-glass bg-surface-container-low p-4"
                            >
                                <img
                                    :src="setting.logo"
                                    :alt="setting.public_name"
                                    class="h-14 max-w-40 object-contain"
                                />
                                <Button
                                    variant="ghost"
                                    size="compact"
                                    @click="brandingForm.remove_logo = true"
                                >
                                    {{ t('common.delete') }}
                                </Button>
                            </div>
                            <Input
                                id="voucher_logo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="block w-full rounded-xl border border-outline-glass bg-white p-2 text-xs"
                                @change="chooseLogo"
                            />
                            <FieldError :message="brandingForm.errors.logo" />
                        </div>
                        <div
                            class="flex justify-end border-t border-outline-glass pt-5"
                        >
                            <Button
                                type="submit"
                                :disabled="brandingForm.processing"
                            >
                                {{ t('common.save') }}
                            </Button>
                        </div>
                    </form>
                </Card>
            </section>
        </div>
    </AppLayout>
</template>
