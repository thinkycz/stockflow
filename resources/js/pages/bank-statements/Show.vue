<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { Download, Plus, RefreshCw, Save, Trash2 } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Select from '@/components/ui/Select.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';

type Transaction = {
    id?: number;
    booked_on: string;
    executed_on: string | null;
    item_type: string;
    amount: string;
    currency: string;
    counterparty_name: string | null;
    counterparty_account: string | null;
    variable_symbol: string | null;
    constant_symbol: string | null;
    specific_symbol: string | null;
    description: string | null;
    category: string;
    sales_from: string | null;
    sales_to: string | null;
    review_note: string | null;
    manually_edited: boolean;
};

type ReconciliationRow = {
    transaction_id: number;
    status: string;
    actual: string;
    expected: string | null;
    difference: string | null;
    reason: string | null;
};

const props = defineProps<{
    statement: {
        id: number;
        status: string;
        bank_name: string | null;
        statement_number: string | null;
        period_from: string | null;
        period_to: string | null;
        original_name: string;
        store_name: string;
        account_number: string | null;
        iban: string | null;
        opening_balance: string | null;
        total_credits: string | null;
        total_debits: string | null;
        closing_balance: string | null;
        credit_count: number | null;
        debit_count: number | null;
        parse_warnings: string[];
        last_error: string | null;
        attempt_count: number;
        editable: boolean;
        terminal: boolean;
    };
    transactions: Transaction[];
    reconciliation: {
        counts: Record<
            'matched' | 'mismatch' | 'unresolved' | 'excluded',
            number
        >;
        rows: ReconciliationRow[];
    };
}>();

const { t } = useI18n();
const route = useRoute();
const dialog = useDialog();
const filter = ref('all');
const resultFilter = ref('all');
const form = useForm<{ transactions: Transaction[] }>({
    transactions: props.transactions.map((row) => ({ ...row })),
});
let polling: ReturnType<typeof setInterval> | null = null;

watch(
    () => props.transactions,
    (rows) => {
        form.transactions = rows.map((row) => ({ ...row }));
    },
);

watch(
    () => props.statement.terminal,
    (terminal) => {
        if (terminal) stopPolling();
    },
);

onMounted(() => {
    if (!props.statement.terminal) {
        polling = setInterval(() => {
            router.reload({
                only: ['statement', 'transactions', 'reconciliation'],
            });
        }, 3000);
    }
});

onUnmounted(stopPolling);

function stopPolling(): void {
    if (polling !== null) clearInterval(polling);
    polling = null;
}

const reconciliationById = computed(
    () =>
        new Map(
            props.reconciliation.rows.map((row) => [row.transaction_id, row]),
        ),
);

const visibleRows = computed(() =>
    form.transactions
        .map((transaction, index) => ({ transaction, index }))
        .filter(({ transaction }) => {
            const categoryMatches =
                filter.value === 'all' || transaction.category === filter.value;
            const resultMatches =
                resultFilter.value === 'all' ||
                (resultFor(transaction)?.status ?? 'unresolved') ===
                    resultFilter.value;

            return categoryMatches && resultMatches;
        }),
);

const categoryOptions = computed(() => [
    { value: 'card', label: t('bank_statements.category.card') },
    { value: 'wolt', label: t('bank_statements.category.wolt') },
    { value: 'bolt', label: t('bank_statements.category.bolt') },
    { value: 'foodora', label: t('bank_statements.category.foodora') },
    {
        value: 'other_incoming',
        label: t('bank_statements.category.other_incoming'),
    },
    { value: 'outgoing', label: t('bank_statements.category.outgoing') },
]);

const filterOptions = computed(() => [
    { value: 'all', label: t('bank_statements.filter.all') },
    ...categoryOptions.value,
]);

const resultFilterOptions = computed(() => [
    { value: 'all', label: t('bank_statements.filter.all_results') },
    { value: 'matched', label: t('bank_statements.result.matched') },
    { value: 'mismatch', label: t('bank_statements.result.mismatch') },
    { value: 'unresolved', label: t('bank_statements.result.unresolved') },
    { value: 'excluded', label: t('bank_statements.result.excluded') },
]);

const confirmationBlocked = computed(
    () =>
        form.isDirty ||
        props.statement.parse_warnings.length > 0 ||
        form.transactions.some(
            (transaction) =>
                ['card', 'wolt', 'bolt', 'foodora'].includes(
                    transaction.category,
                ) &&
                (!transaction.sales_from || !transaction.sales_to),
        ),
);

function addRow(): void {
    form.transactions.push({
        booked_on: props.statement.period_from ?? '',
        executed_on: null,
        item_type: '',
        amount: '0.00',
        currency: 'CZK',
        counterparty_name: null,
        counterparty_account: null,
        variable_symbol: null,
        constant_symbol: null,
        specific_symbol: null,
        description: null,
        category: 'other_incoming',
        sales_from: null,
        sales_to: null,
        review_note: null,
        manually_edited: true,
    });
}

function removeRow(index: number): void {
    form.transactions.splice(index, 1);
}

function save(): void {
    form.put(
        route('bank-statements.update', { bankStatement: props.statement.id }),
        {
            preserveScroll: true,
        },
    );
}

async function confirmStatement(): Promise<void> {
    if (
        !(await dialog.confirm({
            title: t('bank_statements.confirm.title'),
            message: t('bank_statements.confirm.message'),
            confirmLabel: t('bank_statements.actions.confirm'),
        }))
    )
        return;
    router.post(
        route('bank-statements.confirm', { bankStatement: props.statement.id }),
    );
}

async function reopenStatement(): Promise<void> {
    if (
        !(await dialog.confirm({
            title: t('bank_statements.reopen.title'),
            message: t('bank_statements.reopen.message'),
            confirmLabel: t('bank_statements.actions.reopen'),
            variant: 'warning',
        }))
    )
        return;
    router.post(
        route('bank-statements.reopen', { bankStatement: props.statement.id }),
    );
}

function retry(): void {
    router.post(
        route('bank-statements.retry', { bankStatement: props.statement.id }),
    );
}

function resultFor(transaction: Transaction): ReconciliationRow | null {
    return transaction.id
        ? (reconciliationById.value.get(transaction.id) ?? null)
        : null;
}

function badgeVariant(
    status: string,
): 'neutral' | 'success' | 'warning' | 'danger' {
    if (status === 'confirmed' || status === 'matched') return 'success';
    if (status === 'failed' || status === 'mismatch') return 'danger';
    if (status === 'review' || status === 'unresolved') return 'warning';
    return 'neutral';
}
</script>

<template>
    <AppLayout :title="t('bank_statements.detail.title')">
        <div class="flex flex-col gap-6">
            <PageHeader
                :title="t('bank_statements.detail.title')"
                :subtitle="props.statement.original_name"
            >
                <template #context><StoreContextIndicator /></template>
                <template #actions>
                    <Link
                        :href="
                            route('bank-statements.original', {
                                bankStatement: props.statement.id,
                            })
                        "
                    >
                        <Button variant="secondary"
                            ><Download :size="15" />{{
                                t('bank_statements.actions.download')
                            }}</Button
                        >
                    </Link>
                    <Button
                        v-if="props.statement.status === 'failed'"
                        variant="secondary"
                        @click="retry"
                    >
                        <RefreshCw :size="15" />{{
                            t('bank_statements.actions.retry')
                        }}
                    </Button>
                    <Button
                        v-if="props.statement.status === 'confirmed'"
                        variant="warning"
                        @click="reopenStatement"
                    >
                        {{ t('bank_statements.actions.reopen') }}
                    </Button>
                    <Button
                        v-if="props.statement.editable"
                        variant="success"
                        :disabled="confirmationBlocked"
                        @click="confirmStatement"
                    >
                        {{ t('bank_statements.actions.confirm') }}
                    </Button>
                </template>
            </PageHeader>

            <Alert v-if="!props.statement.terminal" variant="info">
                {{ t('bank_statements.processing') }}
            </Alert>
            <Alert v-if="props.statement.last_error" variant="error">
                {{ t(`bank_statements.errors.${props.statement.last_error}`) }}
            </Alert>
            <Alert
                v-if="props.statement.parse_warnings.length > 0"
                variant="error"
            >
                {{ t('bank_statements.integrity_blocked') }}
                <span
                    v-for="warning in props.statement.parse_warnings"
                    :key="warning"
                    class="ml-2"
                >
                    {{ t(`bank_statements.warnings.${warning}`) }}
                </span>
            </Alert>

            <Card class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" padded>
                <div>
                    <p class="text-[10px] uppercase text-on-surface-variant">
                        {{ t('bank_statements.columns.status') }}
                    </p>
                    <Badge :variant="badgeVariant(props.statement.status)">{{
                        t(`bank_statements.status.${props.statement.status}`)
                    }}</Badge>
                </div>
                <div>
                    <p class="text-[10px] uppercase text-on-surface-variant">
                        {{ t('bank_statements.columns.account') }}
                    </p>
                    <p class="text-sm font-semibold">
                        {{
                            props.statement.account_number ??
                            props.statement.iban ??
                            '—'
                        }}
                    </p>
                </div>
                <div>
                    <p class="text-[10px] uppercase text-on-surface-variant">
                        {{ t('bank_statements.columns.period') }}
                    </p>
                    <p class="text-sm font-semibold">
                        {{ props.statement.period_from ?? '—' }} –
                        {{ props.statement.period_to ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-[10px] uppercase text-on-surface-variant">
                        {{ t('bank_statements.columns.balance') }}
                    </p>
                    <p class="text-sm font-semibold">
                        {{ props.statement.opening_balance ?? '—' }} →
                        {{ props.statement.closing_balance ?? '—' }} CZK
                    </p>
                </div>
            </Card>

            <Card v-if="props.transactions.length > 0" padded>
                <div class="mb-4 flex flex-wrap items-end gap-3">
                    <Select
                        v-model="filter"
                        :options="filterOptions"
                        class="max-w-64"
                    />
                    <Select
                        v-model="resultFilter"
                        :options="resultFilterOptions"
                        class="max-w-64"
                    />
                    <div class="flex flex-1 flex-wrap gap-2 text-xs">
                        <Badge variant="success"
                            >{{ t('bank_statements.result.matched') }}:
                            {{ props.reconciliation.counts.matched }}</Badge
                        >
                        <Badge variant="danger"
                            >{{ t('bank_statements.result.mismatch') }}:
                            {{ props.reconciliation.counts.mismatch }}</Badge
                        >
                        <Badge variant="warning"
                            >{{ t('bank_statements.result.unresolved') }}:
                            {{ props.reconciliation.counts.unresolved }}</Badge
                        >
                    </div>
                    <Button
                        v-if="props.statement.editable"
                        variant="secondary"
                        size="compact"
                        @click="addRow"
                        ><Plus :size="14" />{{
                            t('bank_statements.actions.add')
                        }}</Button
                    >
                </div>

                <form @submit.prevent="save">
                    <DataTable density="compact" variant="nested">
                        <thead>
                            <tr>
                                <th>
                                    {{ t('bank_statements.transaction.date') }}
                                </th>
                                <th>
                                    {{
                                        t(
                                            'bank_statements.transaction.description',
                                        )
                                    }}
                                </th>
                                <th>
                                    {{
                                        t(
                                            'bank_statements.transaction.category',
                                        )
                                    }}
                                </th>
                                <th>
                                    {{
                                        t(
                                            'bank_statements.transaction.sales_period',
                                        )
                                    }}
                                </th>
                                <th class="text-right">
                                    {{
                                        t('bank_statements.transaction.actual')
                                    }}
                                </th>
                                <th class="text-right">
                                    {{
                                        t(
                                            'bank_statements.transaction.expected',
                                        )
                                    }}
                                </th>
                                <th>
                                    {{
                                        t('bank_statements.transaction.result')
                                    }}
                                </th>
                                <th v-if="props.statement.editable"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="{ transaction, index } in visibleRows"
                                :key="transaction.id ?? `new-${index}`"
                                :class="
                                    resultFor(transaction)?.status ===
                                    'unresolved'
                                        ? 'bg-amber-50/60'
                                        : ''
                                "
                            >
                                <td>
                                    <Input
                                        v-if="props.statement.editable"
                                        v-model="transaction.booked_on"
                                        type="date"
                                        class="min-w-32"
                                    /><span v-else>{{
                                        transaction.booked_on
                                    }}</span>
                                </td>
                                <td>
                                    <Input
                                        v-if="props.statement.editable"
                                        v-model="transaction.item_type"
                                        class="min-w-44"
                                    /><span v-else>{{
                                        transaction.item_type
                                    }}</span>
                                    <p
                                        class="mt-1 text-[10px] text-on-surface-variant"
                                    >
                                        {{ transaction.counterparty_name }}
                                    </p>
                                    <p
                                        v-if="transaction.review_note"
                                        class="mt-1 text-[10px] text-amber-700"
                                    >
                                        {{ transaction.review_note }}
                                    </p>
                                </td>
                                <td>
                                    <Select
                                        v-if="props.statement.editable"
                                        v-model="transaction.category"
                                        :options="categoryOptions"
                                        class="min-w-36"
                                        density="compact"
                                    /><span v-else>{{
                                        t(
                                            `bank_statements.category.${transaction.category}`,
                                        )
                                    }}</span>
                                </td>
                                <td>
                                    <div
                                        v-if="props.statement.editable"
                                        class="flex min-w-64 gap-1"
                                    >
                                        <Input
                                            :model-value="
                                                transaction.sales_from ?? ''
                                            "
                                            type="date"
                                            @update:model-value="
                                                transaction.sales_from = String(
                                                    $event || '',
                                                )
                                            "
                                        />
                                        <Input
                                            :model-value="
                                                transaction.sales_to ?? ''
                                            "
                                            type="date"
                                            @update:model-value="
                                                transaction.sales_to = String(
                                                    $event || '',
                                                )
                                            "
                                        />
                                    </div>
                                    <span v-else
                                        >{{ transaction.sales_from ?? '—' }} –
                                        {{ transaction.sales_to ?? '—' }}</span
                                    >
                                </td>
                                <td class="text-right">
                                    <Input
                                        v-if="props.statement.editable"
                                        v-model="transaction.amount"
                                        type="number"
                                        step="0.01"
                                        class="min-w-28 text-right"
                                    /><span v-else
                                        >{{ transaction.amount }} CZK</span
                                    >
                                </td>
                                <td class="text-right">
                                    {{
                                        resultFor(transaction)?.expected ?? '—'
                                    }}
                                </td>
                                <td>
                                    <Badge
                                        :variant="
                                            badgeVariant(
                                                resultFor(transaction)
                                                    ?.status ?? 'unresolved',
                                            )
                                        "
                                        >{{
                                            t(
                                                `bank_statements.result.${resultFor(transaction)?.status ?? 'unresolved'}`,
                                            )
                                        }}</Badge
                                    >
                                    <p
                                        v-if="
                                            resultFor(transaction)?.difference
                                        "
                                        class="mt-1 text-[10px]"
                                    >
                                        Δ
                                        {{ resultFor(transaction)?.difference }}
                                        CZK
                                    </p>
                                </td>
                                <td v-if="props.statement.editable">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        @click="removeRow(index)"
                                        ><Trash2 :size="14"
                                    /></Button>
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                    <FieldError :message="form.errors.transactions" />
                    <div
                        v-if="props.statement.editable"
                        class="mt-4 flex justify-end"
                    >
                        <Button type="submit" :loading="form.processing"
                            ><Save :size="15" />{{
                                t('bank_statements.actions.save')
                            }}</Button
                        >
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
