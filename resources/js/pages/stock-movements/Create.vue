<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Alert from '@/components/ui/Alert.vue';
import BackLink from '@/components/ui/BackLink.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import Combobox from '@/components/ui/Combobox.vue';
import DataTable from '@/components/ui/DataTable.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import MovementTypeBadge from '@/features/stock-movements/components/MovementTypeBadge.vue';
import Select from '@/components/ui/Select.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import { formatMoney, formatNumber } from '@/lib/format';
import {
    useMovementDraft,
    type MovementDraftProps,
    type ItemOption,
} from '@/features/stock-movements/useMovementDraft';

const props = defineProps<MovementDraftProps>();
const {
    t,
    route,
    form,
    rows,
    isAdjustmentMode,
    isConsumptionMode,
    isIncomingMode,
    pageTitle,
    pageSubtitle,
    destinationStoreOptions,
    inferredLabelKey,
    isOutgoingTransfer,
    removesStock,
    serverError,
    addRow,
    removeRow,
    searchLoading,
    searchItems,
    availableItems,
    onItemSelect,
    reasonOptions,
    findItem,
    displayedQuantity,
    lineTotal,
    remainingQuantity,
    difference,
    totals,
    isOutOfStockError,
    hasOutOfStockErrors,
    outOfStockRows,
    submit,
} = useMovementDraft(props);
</script>

<template>
    <AppLayout :title="pageTitle">
        <div class="flex flex-col gap-6">
            <div>
                <BackLink
                    v-if="props.is_admin"
                    :href="route('stock-movements.index')"
                >
                    {{ t('stock_movements.back_to_list') }}
                </BackLink>
            </div>

            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ pageTitle }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ pageSubtitle }}
                    </p>
                    <StoreContextIndicator v-if="!props.is_admin" />
                </div>
                <div v-if="props.is_admin" class="flex items-center gap-2">
                    <Link
                        v-if="isAdjustmentMode"
                        :href="route('stock-movements.create')"
                    >
                        <Button variant="secondary" type="button">
                            {{ t('stock_movements.back_to_transfer') }}
                        </Button>
                    </Link>
                    <Link
                        v-else-if="!isConsumptionMode"
                        :href="
                            route('stock-movements.create', {
                                mode: 'adjustment',
                            })
                        "
                    >
                        <Button variant="secondary" type="button">
                            {{ t('stock_movements.open_adjustment') }}
                        </Button>
                    </Link>
                    <Link
                        v-if="!isConsumptionMode"
                        :href="
                            route('stock-movements.create', {
                                mode: 'consumption',
                            })
                        "
                    >
                        <Button variant="secondary" type="button">
                            {{ t('stock_movements.open_consumption') }}
                        </Button>
                    </Link>
                    <Link v-else :href="route('stock-movements.create')">
                        <Button variant="secondary" type="button">
                            {{ t('stock_movements.back_to_transfer') }}
                        </Button>
                    </Link>
                </div>
            </div>

            <Alert v-if="serverError" variant="error">
                {{ serverError }}
            </Alert>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <Card padded>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <template
                            v-if="
                                !isAdjustmentMode &&
                                !isConsumptionMode &&
                                !isIncomingMode
                            "
                        >
                            <div class="space-y-2">
                                <Label for="source_store_id">{{
                                    t('stock_movements.form.source_store')
                                }}</Label>
                                <Select
                                    id="source_store_id"
                                    v-model="form.source_store_id"
                                    :options="[
                                        {
                                            value: '',
                                            label: t(
                                                'stock_movements.form.no_source',
                                            ),
                                        },
                                        ...stores.map((s) => ({
                                            value: String(s.id),
                                            label: s.name,
                                        })),
                                    ]"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="store_id" :required="true">{{
                                    t('stock_movements.form.destination_store')
                                }}</Label>
                                <Select
                                    id="store_id"
                                    v-model="form.store_id"
                                    :options="[
                                        {
                                            value: '',
                                            label: t(
                                                'stock_movements.form.select_store',
                                            ),
                                        },
                                        ...destinationStoreOptions.map((s) => ({
                                            value: String(s.id),
                                            label: s.name,
                                        })),
                                    ]"
                                    required
                                />
                            </div>
                            <div
                                v-if="inferredLabelKey"
                                class="space-y-2 sm:col-span-2"
                            >
                                <Label>{{
                                    t('stock_movements.form.inferred_type')
                                }}</Label>
                                <div class="flex h-10 items-center">
                                    <MovementTypeBadge
                                        :type="
                                            inferredLabelKey === 'outgoing'
                                                ? 'transfer'
                                                : inferredLabelKey
                                        "
                                        :label-key="inferredLabelKey"
                                    />
                                </div>
                            </div>
                        </template>
                        <div v-else class="space-y-2 sm:col-span-2">
                            <Label for="adjustment_store_id" :required="true">{{
                                t(
                                    isConsumptionMode
                                        ? 'stock_movements.form.consumption_store'
                                        : isIncomingMode
                                          ? 'stock_movements.form.incoming_store'
                                          : 'stock_movements.form.adjustment_store',
                                )
                            }}</Label>
                            <Select
                                id="adjustment_store_id"
                                v-model="form.store_id"
                                :options="[
                                    {
                                        value: '',
                                        label: t(
                                            'stock_movements.form.select_store',
                                        ),
                                    },
                                    ...stores.map((s) => ({
                                        value: String(s.id),
                                        label: s.name,
                                    })),
                                ]"
                                required
                            />
                        </div>
                    </div>
                    <div class="mt-4 space-y-2">
                        <Label for="note">{{
                            t('stock_movements.form.note')
                        }}</Label>
                        <Input id="note" v-model="form.note" type="text" />
                    </div>
                    <div v-if="props.is_admin" class="mt-4 space-y-2">
                        <Label for="occurred_at">{{
                            t('stock_movements.form.occurred_at')
                        }}</Label>
                        <Input
                            id="occurred_at"
                            v-model="form.occurred_at"
                            type="datetime-local"
                        />
                        <p class="text-xs text-on-surface-variant">
                            {{ t('stock_movements.form.occurred_at_help') }}
                        </p>
                    </div>
                </Card>

                <section class="space-y-4">
                    <CardHeader class="mb-3">
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <CardTitle>
                                {{ t('stock_movements.form.items') }}
                            </CardTitle>
                            <div
                                class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-on-surface-variant"
                            >
                                <span>
                                    <span
                                        class="font-semibold text-on-surface"
                                        >{{ rows.length }}</span
                                    >
                                    {{ t('stock_movements.summary.rows') }}
                                </span>
                                <span
                                    v-if="props.is_admin"
                                    class="text-outline-glass"
                                    >·</span
                                >
                                <span v-if="props.is_admin">
                                    <span
                                        class="font-semibold text-on-surface"
                                        >{{
                                            formatNumber(totals.quantity)
                                        }}</span
                                    >
                                    {{ t('stock_movements.summary.quantity') }}
                                </span>
                                <span class="text-outline-glass">·</span>
                                <span>
                                    <span
                                        class="font-semibold text-on-surface"
                                        >{{ formatMoney(totals.value) }}</span
                                    >
                                    {{ t('stock_movements.summary.value') }}
                                </span>
                            </div>
                        </div>
                    </CardHeader>

                    <DataTable density="compact">
                        <thead>
                            <tr>
                                <th class="min-w-[14rem]">
                                    {{ t('stock_movements.form.item') }}
                                </th>
                                <th class="min-w-[6rem] text-right">
                                    {{
                                        t(
                                            'stock_movements.form.current_quantity',
                                        )
                                    }}
                                </th>
                                <th
                                    v-if="props.is_admin && !isAdjustmentMode"
                                    class="min-w-[7rem]"
                                >
                                    {{
                                        t(
                                            removesStock
                                                ? 'stock_movements.form.quantity_out'
                                                : 'stock_movements.form.quantity_in',
                                        )
                                    }}
                                </th>
                                <th v-else class="min-w-[7rem]">
                                    {{
                                        t('stock_movements.form.quantity_after')
                                    }}
                                </th>
                                <th
                                    v-if="removesStock"
                                    class="min-w-[6rem] text-right"
                                >
                                    {{ t('stock_movements.form.remaining') }}
                                </th>
                                <th
                                    v-if="isAdjustmentMode"
                                    class="min-w-[6rem] text-right"
                                >
                                    {{ t('stock_movements.form.difference') }}
                                </th>
                                <th
                                    v-if="isAdjustmentMode"
                                    class="min-w-[9rem]"
                                >
                                    {{ t('stock_movements.form.reason') }}
                                </th>
                                <th
                                    v-if="!isAdjustmentMode"
                                    class="min-w-[6rem] text-right"
                                >
                                    {{ t('stock_movements.form.line_total') }}
                                </th>
                                <th class="w-0">
                                    <span class="sr-only">{{
                                        t('common.actions')
                                    }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.id">
                                <td>
                                    <Combobox
                                        v-model="row.item_id"
                                        :items="availableItems"
                                        :loading="searchLoading"
                                        :placeholder="
                                            t(
                                                'stock_movements.form.select_item',
                                            )
                                        "
                                        required
                                        @search="searchItems"
                                        @select="
                                            (item) =>
                                                onItemSelect(
                                                    row,
                                                    item as unknown as ItemOption,
                                                )
                                        "
                                    />
                                </td>
                                <td class="text-right text-on-surface-variant">
                                    {{
                                        findItem(row.item_id)
                                            ? formatNumber(
                                                  displayedQuantity(row),
                                              )
                                            : '—'
                                    }}
                                </td>
                                <td v-if="!isAdjustmentMode">
                                    <Input
                                        v-model="row.quantity"
                                        type="number"
                                        step="0.001"
                                        min="1"
                                        :invalid="isOutOfStockError(row)"
                                        required
                                    />
                                </td>
                                <td v-else>
                                    <Input
                                        v-model="row.quantity_after"
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        required
                                    />
                                </td>
                                <td
                                    v-if="isOutgoingTransfer"
                                    class="text-right text-on-surface-variant"
                                >
                                    {{ formatNumber(remainingQuantity(row)) }}
                                </td>
                                <td
                                    v-if="isAdjustmentMode"
                                    class="text-right font-semibold"
                                    :class="
                                        difference(row) >= 0
                                            ? 'text-emerald-600'
                                            : 'text-rose-600'
                                    "
                                >
                                    {{ formatNumber(difference(row)) }}
                                </td>
                                <td v-if="isAdjustmentMode">
                                    <Select
                                        v-model="row.adjustment_reason"
                                        :options="reasonOptions"
                                        required
                                    />
                                </td>
                                <td
                                    v-if="props.is_admin && !isAdjustmentMode"
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatMoney(lineTotal(row)) }}
                                </td>
                                <td>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        class="hover:text-error-red"
                                        :aria-label="
                                            t('stock_movements.form.remove_row')
                                        "
                                        @click="removeRow(row.id)"
                                    >
                                        <Trash2 :size="14" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>

                    <div v-if="hasOutOfStockErrors" class="mt-3 space-y-1">
                        <div
                            v-for="row in outOfStockRows"
                            :key="row.id"
                            class="rounded-lg border border-error-red/30 bg-error-red/5 p-2 text-xs text-error-red"
                        >
                            <span class="font-semibold">
                                {{ findItem(row.item_id)?.title ?? '—' }}:
                            </span>
                            {{ t('stock_movements.errors.out_of_stock') }}
                        </div>
                    </div>

                    <div
                        class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <Button
                            type="button"
                            variant="secondary"
                            @click="addRow"
                        >
                            <Plus :size="14" />
                            {{ t('stock_movements.form.add_row') }}
                        </Button>
                        <div class="flex items-center gap-3">
                            <Link
                                v-if="props.is_admin"
                                :href="route('stock-movements.index')"
                            >
                                <Button variant="secondary" type="button">
                                    {{ t('common.cancel') }}
                                </Button>
                            </Link>
                            <Button
                                type="submit"
                                :disabled="
                                    form.processing || hasOutOfStockErrors
                                "
                            >
                                {{ t('stock_movements.form.save') }}
                            </Button>
                        </div>
                    </div>
                </section>
            </form>
        </div>
    </AppLayout>
</template>
