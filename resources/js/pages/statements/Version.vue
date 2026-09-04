<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import BackLink from '@/components/ui/BackLink.vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import DataTable from '@/components/ui/DataTable.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import { formatCzechDateTime } from '@/composables/useCzechDate';
import { formatMoney } from '@/lib/format';
import {
    useStatementVersion,
    type StatementVersionProps,
} from '@/features/statements/useStatementVersion';

const props = defineProps<StatementVersionProps>();
const { t, route, rowCashTotal, totals, restore } = useStatementVersion(props);
</script>

<template>
    <AppLayout :title="`${t('statements.session.title')} #${version.id}`">
        <div class="flex flex-col gap-6">
            <div>
                <BackLink
                    :href="
                        route('statements.history', {
                            statement: props.statement.id,
                        })
                    "
                >
                    {{ t('statements.history.back') }}
                </BackLink>
            </div>

            <div class="flex flex-col gap-2">
                <h1
                    class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                >
                    {{ t('statements.session.title') }}
                    <span class="text-on-surface-variant"
                        >#{{ version.id }}</span
                    >
                </h1>
                <StoreContextIndicator
                    :store="{ name: props.statement.store_name }"
                />
                <div
                    class="flex flex-wrap items-center gap-2 text-xs text-on-surface-variant"
                >
                    <span>{{ formatCzechDateTime(version.snapshot_at) }}</span>
                    <span>·</span>
                    <span class="font-semibold text-on-surface">{{
                        statement.store_name
                    }}</span>
                    <template v-if="version.created_by_email">
                        <span>·</span>
                        <span>{{ version.created_by_email }}</span>
                    </template>
                </div>
            </div>

            <section class="space-y-4">
                <DataTable density="compact">
                    <thead>
                        <tr>
                            <th class="min-w-[6rem]">
                                {{ t('statements.columns.day') }}
                            </th>
                            <th class="min-w-[7rem] text-right">
                                {{ t('statements.columns.cash') }}
                            </th>
                            <th class="min-w-[7rem] text-right">
                                {{ t('statements.columns.card') }}
                            </th>
                            <th class="min-w-[7rem] text-right">
                                {{ t('statements.columns.wolt') }}
                            </th>
                            <th class="min-w-[7rem] text-right">
                                {{ t('statements.columns.bolt') }}
                            </th>
                            <th class="min-w-[7rem] text-right">
                                {{ t('statements.columns.bolt_cash') }}
                            </th>
                            <th class="min-w-[7rem] text-right">
                                {{ t('statements.columns.foodora') }}
                            </th>
                            <th class="min-w-[7rem] text-right">
                                {{ t('statements.columns.total') }}
                            </th>
                            <th
                                v-if="props.is_admin"
                                class="min-w-[5rem] text-center"
                            >
                                {{ t('statements.columns.cash_checked') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in props.rows" :key="row.date">
                            <td
                                class="font-mono text-xs text-on-surface-variant"
                            >
                                {{ row.date }}
                            </td>
                            <td
                                class="text-right font-semibold text-on-surface"
                            >
                                {{ formatMoney(row.cash) }}
                            </td>
                            <td
                                class="text-right font-semibold text-on-surface"
                            >
                                {{ formatMoney(row.card) }}
                            </td>
                            <td
                                class="text-right font-semibold text-on-surface"
                            >
                                {{ formatMoney(row.wolt) }}
                            </td>
                            <td
                                class="text-right font-semibold text-on-surface"
                            >
                                {{ formatMoney(row.bolt) }}
                            </td>
                            <td
                                class="text-right font-semibold text-on-surface"
                            >
                                {{ formatMoney(row.bolt_cash) }}
                            </td>
                            <td
                                class="text-right font-semibold text-on-surface"
                            >
                                {{ formatMoney(row.foodora) }}
                            </td>
                            <td
                                class="text-right font-semibold text-on-surface"
                            >
                                <div>{{ formatMoney(row.total) }}</div>
                                <div
                                    class="mt-0.5 text-[0.65rem] font-normal text-on-surface-variant"
                                >
                                    {{
                                        t('statements.columns.cash_of_total', {
                                            amount: formatMoney(
                                                rowCashTotal(row),
                                            ),
                                        })
                                    }}
                                </div>
                            </td>
                            <td v-if="props.is_admin" class="text-center">
                                <Checkbox
                                    :model-value="row.cash_checked"
                                    disabled
                                />
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th
                                class="text-left text-xs font-semibold text-on-surface-variant"
                            >
                                Σ
                            </th>
                            <th
                                class="text-right text-xs font-semibold text-on-surface-variant"
                            >
                                {{ formatMoney(totals().cash) }}
                            </th>
                            <th
                                class="text-right text-xs font-semibold text-on-surface-variant"
                            >
                                {{ formatMoney(totals().card) }}
                            </th>
                            <th
                                class="text-right text-xs font-semibold text-on-surface-variant"
                            >
                                {{ formatMoney(totals().wolt) }}
                            </th>
                            <th
                                class="text-right text-xs font-semibold text-on-surface-variant"
                            >
                                {{ formatMoney(totals().bolt) }}
                            </th>
                            <th
                                class="text-right text-xs font-semibold text-on-surface-variant"
                            >
                                {{ formatMoney(totals().bolt_cash) }}
                            </th>
                            <th
                                class="text-right text-xs font-semibold text-on-surface-variant"
                            >
                                {{ formatMoney(totals().foodora) }}
                            </th>
                            <th
                                class="text-right text-xs font-semibold text-on-surface"
                            >
                                <div>{{ formatMoney(totals().total) }}</div>
                                <div
                                    class="mt-0.5 text-[0.65rem] font-normal text-on-surface-variant"
                                >
                                    {{
                                        t('statements.columns.cash_of_total', {
                                            amount: formatMoney(
                                                totals().cash +
                                                    totals().bolt_cash,
                                            ),
                                        })
                                    }}
                                </div>
                            </th>
                            <th v-if="props.is_admin">
                                {{ t('statements.columns.cash_checked') }}
                            </th>
                        </tr>
                    </tfoot>
                </DataTable>

                <div
                    v-if="props.statement.store_active"
                    class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end"
                >
                    <Button type="button" @click="restore">
                        {{ t('statements.history.restore') }}
                    </Button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
