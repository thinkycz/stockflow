<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Download, Plus, RefreshCw, Save, Trash2 } from '@lucide/vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import {
    useBankReview,
    type BankReviewProps,
} from '@/features/bank-statements/useBankReview';

const props = defineProps<BankReviewProps>();
const {
    t,
    route,
    filter,
    resultFilter,
    form,
    visibleRows,
    categoryOptions,
    filterOptions,
    resultFilterOptions,
    confirmationBlocked,
    statementError,
    transactionError,
    addRow,
    removeRow,
    save,
    confirmStatement,
    reopenStatement,
    retry,
    resultFor,
    badgeVariant,
} = useBankReview(props);
</script>

<template>
    <AppLayout :title="t('bank_statements.detail.title')">
        <div class="flex flex-col gap-6">
            <PageHeader
                :title="t('bank_statements.detail.title')"
                :subtitle="props.statement.original_name"
            >
                <template #context>
                    <StoreContextIndicator
                        :store="{ name: statement.store_name }"
                    />
                </template>
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
                        v-if="
                            props.statement.store_active &&
                            props.statement.status === 'failed'
                        "
                        variant="secondary"
                        @click="retry"
                    >
                        <RefreshCw :size="15" />{{
                            t('bank_statements.actions.retry')
                        }}
                    </Button>
                    <Button
                        v-if="
                            props.statement.store_active &&
                            props.statement.status === 'confirmed'
                        "
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
            <Alert v-if="statementError" variant="error">
                {{ statementError }}
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
                                    <div v-if="props.statement.editable">
                                        <Input
                                            v-model="transaction.booked_on"
                                            type="date"
                                            class="min-w-32"
                                        />
                                        <FieldError
                                            :message="
                                                transactionError(
                                                    index,
                                                    'booked_on',
                                                )
                                            "
                                        />
                                    </div>
                                    <span v-else>{{
                                        transaction.booked_on
                                    }}</span>
                                </td>
                                <td>
                                    <div v-if="props.statement.editable">
                                        <Input
                                            v-model="transaction.item_type"
                                            class="min-w-44"
                                        />
                                        <FieldError
                                            :message="
                                                transactionError(
                                                    index,
                                                    'item_type',
                                                )
                                            "
                                        />
                                    </div>
                                    <span v-else>{{
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
                                    <div v-if="props.statement.editable">
                                        <Select
                                            v-model="transaction.category"
                                            :options="categoryOptions"
                                            class="min-w-36"
                                            density="compact"
                                        />
                                        <FieldError
                                            :message="
                                                transactionError(
                                                    index,
                                                    'category',
                                                )
                                            "
                                        />
                                    </div>
                                    <span v-else>{{
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
                                        <div class="flex-1">
                                            <Input
                                                :model-value="
                                                    transaction.sales_from ?? ''
                                                "
                                                type="date"
                                                @update:model-value="
                                                    transaction.sales_from =
                                                        String($event || '')
                                                "
                                            />
                                            <FieldError
                                                :message="
                                                    transactionError(
                                                        index,
                                                        'sales_from',
                                                    )
                                                "
                                            />
                                        </div>
                                        <div class="flex-1">
                                            <Input
                                                :model-value="
                                                    transaction.sales_to ?? ''
                                                "
                                                type="date"
                                                @update:model-value="
                                                    transaction.sales_to =
                                                        String($event || '')
                                                "
                                            />
                                            <FieldError
                                                :message="
                                                    transactionError(
                                                        index,
                                                        'sales_to',
                                                    )
                                                "
                                            />
                                        </div>
                                    </div>
                                    <span v-else
                                        >{{ transaction.sales_from ?? '—' }} –
                                        {{ transaction.sales_to ?? '—' }}</span
                                    >
                                </td>
                                <td class="text-right">
                                    <div v-if="props.statement.editable">
                                        <Input
                                            v-model="transaction.amount"
                                            type="number"
                                            step="0.01"
                                            class="min-w-28 text-right"
                                        />
                                        <FieldError
                                            :message="
                                                transactionError(
                                                    index,
                                                    'amount',
                                                )
                                            "
                                        />
                                    </div>
                                    <span v-else
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
