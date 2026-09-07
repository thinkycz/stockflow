<script setup lang="ts">
import PayoutEstimate from '@/components/PayoutEstimate.vue';
import MarketplaceFeeBreakdown from '@/components/MarketplaceFeeBreakdown.vue';

import { ref } from 'vue';
import Modal from '@/components/ui/Modal.vue';
import {
    formatCzechDate,
    formatCzechDateRange,
} from '@/composables/useCzechDate';
import { useBankStatementActions } from '@/features/bank-statements/useBankStatementActions';
import { Link } from '@inertiajs/vue3';
import { Download, Eye, Plus, RefreshCw, Save, Trash2 } from '@lucide/vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import FilterField from '@/components/ui/FilterField.vue';
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

const {
    busy: actionBusy,
    deleteStatement,
    reanalyze,
} = useBankStatementActions();
const props = defineProps<BankReviewProps>();
const detailIndex = ref<number | null>(null);
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
    confirmationReasons,
    reviewCounts,
    isPending,
    applyCandidate,
    candidatesFor,
    recommendPeriod,
    recommendationStale,
    requests,
    reasonFor,
    automaticSource,
    statementError,
    transactionError,
    addRow,
    removeRow,
    save,
    confirmStatement,
    reopenStatement,
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
                            ['failed', 'review', 'confirmed'].includes(
                                props.statement.status,
                            )
                        "
                        variant="secondary"
                        @click="reanalyze(props.statement.id)"
                        :disabled="actionBusy"
                    >
                        <RefreshCw :size="15" />{{
                            t('bank_statements.actions.reanalyze')
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
                    <Button
                        variant="ghost"
                        :disabled="actionBusy"
                        @click="deleteStatement(props.statement.id)"
                        ><Trash2 :size="15" />{{
                            t('bank_statements.actions.delete')
                        }}</Button
                    >
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
                        {{
                            statement.period_from && statement.period_to
                                ? formatCzechDateRange(
                                      statement.period_from,
                                      statement.period_to,
                                  )
                                : '—'
                        }}
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

            <section
                v-if="props.transactions.length > 0 || props.statement.editable"
                data-testid="bank-transactions"
            >
                <Alert
                    v-if="
                        props.statement.editable && confirmationReasons.length
                    "
                    variant="info"
                    class="mb-4"
                >
                    <p v-for="reason in confirmationReasons" :key="reason">
                        {{ reason }}
                    </p>
                </Alert>
                <div class="mb-4 flex flex-wrap items-end gap-3">
                    <FilterField
                        for="bank-category"
                        :label="t('bank_statements.transaction.category')"
                    >
                        <Select
                            id="bank-category"
                            v-model="filter"
                            :options="filterOptions"
                            class="max-w-64"
                        />
                    </FilterField>
                    <FilterField
                        for="bank-result"
                        :label="t('bank_statements.transaction.result')"
                    >
                        <Select
                            id="bank-result"
                            v-model="resultFilter"
                            :options="resultFilterOptions"
                            class="max-w-64"
                        />
                    </FilterField>
                    <div class="flex flex-1 flex-wrap gap-2 text-xs">
                        <Badge variant="neutral"
                            >{{ t('bank_statements.result.paired') }}:
                            {{ reviewCounts.paired }}</Badge
                        >
                        <Badge v-if="reviewCounts.pending" variant="neutral"
                            >{{ t('bank_statements.result.pending') }}:
                            {{ reviewCounts.pending }}</Badge
                        >
                        <Badge variant="warning"
                            >{{ t('bank_statements.result.within_estimate') }}:
                            {{ reviewCounts.within_estimate }}</Badge
                        >
                        <Badge variant="warning"
                            >{{ t('bank_statements.result.outside_estimate') }}:
                            {{ reviewCounts.outside_estimate }}</Badge
                        >
                        <Badge variant="success"
                            >{{ t('bank_statements.result.matched') }}:
                            {{ reviewCounts.matched }}</Badge
                        >
                        <Badge variant="danger"
                            >{{ t('bank_statements.result.mismatch') }}:
                            {{ reviewCounts.mismatch }}</Badge
                        >
                        <Badge variant="warning"
                            >{{ t('bank_statements.result.unresolved') }}:
                            {{ reviewCounts.unresolved }}</Badge
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
                    <DataTable density="compact">
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
                                <th>
                                    {{
                                        t('bank_statements.transaction.details')
                                    }}
                                </th>
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
                                        formatCzechDate(transaction.booked_on)
                                    }}</span>
                                </td>
                                <td>
                                    <div v-if="props.statement.editable">
                                        <Input
                                            v-model="transaction.item_type"
                                            class="min-w-32"
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
                                    <span
                                        v-else
                                        class="line-clamp-2 max-w-56"
                                        >{{ transaction.item_type }}</span
                                    >
                                    <p
                                        class="mt-1 line-clamp-1 max-w-56 text-xs text-on-surface-variant"
                                    >
                                        {{ transaction.counterparty_name }}
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
                                <td data-mobile-layout="stack">
                                    <div
                                        v-if="props.statement.editable"
                                        class="flex min-w-32 flex-col gap-1"
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
                                    <span v-else class="whitespace-nowrap">{{
                                        transaction.sales_from &&
                                        transaction.sales_to
                                            ? formatCzechDateRange(
                                                  transaction.sales_from,
                                                  transaction.sales_to,
                                              )
                                            : '—'
                                    }}</span>
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
                                    <PayoutEstimate
                                        :expected="
                                            resultFor(transaction)?.expected
                                        "
                                        :fees="resultFor(transaction)?.fees"
                                    />
                                </td>
                                <td data-mobile-layout="stack">
                                    <div
                                        class="flex min-w-28 flex-col items-start gap-1"
                                    >
                                        <p
                                            v-if="isPending(transaction)"
                                            class="text-xs"
                                        >
                                            {{
                                                t(
                                                    'bank_statements.result.pending',
                                                )
                                            }}
                                        </p>
                                        <template v-else>
                                            <Badge
                                                v-if="
                                                    resultFor(transaction)
                                                        ?.pairing === 'paired'
                                                "
                                                variant="neutral"
                                                >{{
                                                    t(
                                                        'bank_statements.result.paired',
                                                    )
                                                }}</Badge
                                            >
                                            <Badge
                                                :variant="
                                                    badgeVariant(
                                                        resultFor(transaction)
                                                            ?.status ??
                                                            'unresolved',
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
                                                    !resultFor(transaction)
                                                        ?.range &&
                                                    resultFor(transaction)
                                                        ?.difference !== null &&
                                                    resultFor(transaction)
                                                        ?.difference !==
                                                        undefined
                                                "
                                                class="mt-1 text-xs"
                                            >
                                                Δ
                                                {{
                                                    resultFor(transaction)
                                                        ?.difference
                                                }}
                                                CZK
                                            </p>
                                        </template>
                                    </div>
                                </td>
                                <td>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        :aria-label="
                                            t(
                                                'bank_statements.transaction.details',
                                            )
                                        "
                                        :title="
                                            t(
                                                'bank_statements.transaction.details',
                                            )
                                        "
                                        @click="detailIndex = index"
                                        ><Eye :size="16"
                                    /></Button>
                                    <Modal
                                        :open="detailIndex === index"
                                        :title="
                                            t(
                                                'bank_statements.transaction.details',
                                            )
                                        "
                                        size="lg"
                                        body-class="max-h-[75dvh] overflow-y-auto space-y-5"
                                        @close="detailIndex = null"
                                    >
                                        <div>
                                            <p class="font-semibold">
                                                {{ transaction.item_type }}
                                            </p>
                                            <p>
                                                {{
                                                    transaction.counterparty_name
                                                }}
                                            </p>
                                            <p>
                                                {{
                                                    formatCzechDate(
                                                        transaction.booked_on,
                                                    )
                                                }}
                                                · {{ transaction.amount }} CZK
                                            </p>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold">
                                                {{
                                                    t(
                                                        'bank_statements.transaction.sales_period',
                                                    )
                                                }}
                                            </h3>
                                            <p>
                                                {{
                                                    transaction.sales_from &&
                                                    transaction.sales_to
                                                        ? formatCzechDateRange(
                                                              transaction.sales_from,
                                                              transaction.sales_to,
                                                          )
                                                        : '—'
                                                }}
                                            </p>
                                            <p
                                                v-if="
                                                    automaticSource(transaction)
                                                "
                                                class="mt-2 text-xs text-on-surface-variant"
                                            >
                                                {{
                                                    t(
                                                        `bank_statements.suggestions.${automaticSource(transaction)}`,
                                                    )
                                                }}
                                            </p>
                                            <div
                                                v-if="
                                                    ['wolt', 'bolt'].includes(
                                                        transaction.category,
                                                    ) &&
                                                    [
                                                        'review',
                                                        'confirmed',
                                                    ].includes(
                                                        props.statement.status,
                                                    )
                                                "
                                                class="mt-2 space-y-1 text-xs"
                                            >
                                                <Button
                                                    variant="secondary"
                                                    size="compact"
                                                    :loading="
                                                        requests.has(
                                                            transaction,
                                                        )
                                                    "
                                                    @click="
                                                        recommendPeriod(
                                                            transaction,
                                                        )
                                                    "
                                                    >{{
                                                        t(
                                                            'bank_statements.suggestions.recommend',
                                                        )
                                                    }}</Button
                                                >
                                                <p
                                                    v-if="
                                                        recommendationStale(
                                                            transaction,
                                                        ) &&
                                                        candidatesFor(
                                                            transaction,
                                                        ).length
                                                    "
                                                >
                                                    {{
                                                        t(
                                                            'bank_statements.suggestions.stale',
                                                        )
                                                    }}
                                                </p>
                                                <p
                                                    v-if="
                                                        !props.statement
                                                            .editable
                                                    "
                                                >
                                                    {{
                                                        t(
                                                            'bank_statements.suggestions.reopen',
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <section
                                                data-period-suggestions
                                                v-if="
                                                    candidatesFor(transaction)
                                                        .length
                                                "
                                                class="mt-2 text-xs"
                                            >
                                                <h4 class="font-semibold">
                                                    {{
                                                        t(
                                                            'bank_statements.suggestions.title',
                                                        )
                                                    }}
                                                </h4>
                                                <div
                                                    v-for="candidate in candidatesFor(
                                                        transaction,
                                                    )"
                                                    :key="`${candidate.from}-${candidate.to}`"
                                                    class="mt-2 space-y-1"
                                                >
                                                    <p>
                                                        {{
                                                            formatCzechDateRange(
                                                                candidate.from,
                                                                candidate.to,
                                                            )
                                                        }}
                                                    </p>
                                                    <div>
                                                        {{
                                                            t(
                                                                'bank_statements.transaction.expected',
                                                            )
                                                        }}:
                                                        <PayoutEstimate
                                                            :expected="
                                                                candidate.expected
                                                            "
                                                            :fees="
                                                                candidate.fees
                                                            "
                                                            :range="
                                                                candidate.range
                                                            "
                                                        />
                                                        <template
                                                            v-if="
                                                                !candidate.range
                                                            "
                                                            >CZK · Δ
                                                            {{
                                                                candidate.difference ??
                                                                '—'
                                                            }}
                                                            CZK</template
                                                        >
                                                    </div>
                                                    <p>
                                                        {{
                                                            t(
                                                                `bank_statements.suggestions.source_${candidate.source}`,
                                                            )
                                                        }}
                                                    </p>
                                                    <p v-if="candidate.reason">
                                                        {{
                                                            t(
                                                                `bank_statements.reasons.${candidate.reason}`,
                                                            )
                                                        }}
                                                    </p>
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                        size="compact"
                                                        :disabled="
                                                            candidate.reason !==
                                                                null ||
                                                            recommendationStale(
                                                                transaction,
                                                            ) ||
                                                            !props.statement
                                                                .editable
                                                        "
                                                        @click="
                                                            applyCandidate(
                                                                transaction,
                                                                candidate,
                                                            )
                                                        "
                                                        >{{
                                                            t(
                                                                'bank_statements.suggestions.use',
                                                            )
                                                        }}</Button
                                                    >
                                                </div>
                                            </section>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold">
                                                {{
                                                    t(
                                                        'bank_statements.transaction.expected',
                                                    )
                                                }}
                                            </h3>
                                            <p v-if="isPending(transaction)">
                                                {{
                                                    t(
                                                        'bank_statements.result.pending',
                                                    )
                                                }}
                                            </p>
                                            <PayoutEstimate
                                                :expected="
                                                    resultFor(transaction)
                                                        ?.expected
                                                "
                                                :fees="
                                                    resultFor(transaction)?.fees
                                                "
                                                :range="
                                                    resultFor(transaction)
                                                        ?.range
                                                "
                                            />
                                            <p
                                                v-if="
                                                    !resultFor(transaction)
                                                        ?.range &&
                                                    resultFor(transaction)
                                                        ?.difference != null
                                                "
                                                class="text-sm"
                                            >
                                                {{
                                                    t(
                                                        'statements.receipts.difference',
                                                    )
                                                }}:
                                                {{
                                                    resultFor(transaction)
                                                        ?.difference
                                                }}
                                                CZK
                                            </p>
                                            <p
                                                v-if="
                                                    !resultFor(transaction)
                                                        ?.range &&
                                                    resultFor(transaction)
                                                        ?.tolerance
                                                "
                                                class="mt-1 text-xs text-on-surface-variant"
                                            >
                                                {{
                                                    t(
                                                        'bank_statements.tolerance',
                                                        {
                                                            amount: resultFor(
                                                                transaction,
                                                            )?.tolerance,
                                                        },
                                                    )
                                                }}
                                            </p>
                                            <p
                                                v-if="reasonFor(transaction)"
                                                class="mt-1 text-xs text-on-surface-variant"
                                            >
                                                {{
                                                    t(
                                                        `bank_statements.reasons.${reasonFor(transaction)}`,
                                                    )
                                                }}
                                            </p>
                                            <MarketplaceFeeBreakdown
                                                inline
                                                :fees="
                                                    resultFor(transaction)?.fees
                                                "
                                            />
                                        </div>
                                        <details v-if="transaction.review_note">
                                            <summary
                                                class="cursor-pointer text-sm"
                                            >
                                                {{
                                                    t(
                                                        'bank_statements.transaction.extraction_note',
                                                    )
                                                }}
                                            </summary>
                                            <p
                                                v-if="transaction.review_note"
                                                class="mt-1 text-[10px] text-amber-700"
                                            >
                                                {{ transaction.review_note }}
                                            </p>
                                        </details>
                                    </Modal>
                                    <Button
                                        v-if="props.statement.editable"
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
            </section>
        </div>
    </AppLayout>
</template>
