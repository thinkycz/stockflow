<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2, Boxes } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import Pagination from '@/components/ui/Pagination.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { formatMoney, formatNumber } from '@/lib/format';

type ItemRow = {
    id: number;
    title: string;
    sku: string | null;
    unit: string | null;
    warehouse_quantity: number;
    purchase_price: number;
    total_value: number;
    status: 'in_stock' | 'low_stock' | 'out_of_stock';
};

const props = defineProps<{
    items: ItemRow[];
    search: string;
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}>();

const { t } = useI18n();

useBoundLocale();

const searchTerm = ref<string>(props.search || '');
const submitting = ref<boolean>(false);

function performSearch(event?: Event): void {
    if (event) {
        event.preventDefault();
    }
    submitting.value = true;
    router.get(
        '/items',
        { search: searchTerm.value || undefined },
        {
            preserveState: true,
            onFinish: (): void => {
                submitting.value = false;
            },
        },
    );
}

function destroyItem(id: number): void {
    if (!window.confirm(t('items.confirm_delete'))) {
        return;
    }
    router.delete(`/items/${id}`);
}
</script>

<template>
    <AppLayout :title="t('items.title')">
        <Head :title="t('items.title')" />

        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('items.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('items.subtitle') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="secondary" type="button" disabled>
                        {{ t('common.export') }}
                    </Button>
                    <Link href="/items/create">
                        <Button>
                            <Plus :size="14" />
                            {{ t('items.add_item') }}
                        </Button>
                    </Link>
                </div>
            </header>

            <Card padded>
                <form
                    class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center"
                    @submit.prevent="performSearch"
                >
                    <div class="relative flex-1">
                        <Search
                            :size="14"
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-on-surface-variant"
                        />
                        <Input
                            v-model="searchTerm"
                            type="search"
                            :placeholder="t('items.search_placeholder')"
                            class="pl-9"
                        />
                    </div>
                    <Button
                        type="submit"
                        variant="secondary"
                        :disabled="submitting"
                    >
                        {{ t('common.search') }}
                    </Button>
                </form>

                <div
                    v-if="search"
                    class="mb-3 flex items-center gap-2 text-xs text-on-surface-variant"
                >
                    <span>{{ t('common.searching_for') }}:</span>
                    <Badge variant="neutral">{{ search }}</Badge>
                </div>

                <LoadingState
                    v-if="submitting"
                    :label="t('common.loading')"
                    inline
                />
                <EmptyState
                    v-else-if="items.length === 0"
                    :title="t('items.empty.title')"
                    :description="t('items.empty.description')"
                >
                    <template #action>
                        <Link href="/items/create">
                            <Button>
                                <Plus :size="14" />
                                {{ t('items.add_item') }}
                            </Button>
                        </Link>
                    </template>
                </EmptyState>
                <div v-else class="overflow-x-auto">
                    <DataTable>
                        <thead>
                            <tr>
                                <th class="w-10"></th>
                                <th>{{ t('items.columns.title') }}</th>
                                <th>{{ t('items.columns.sku') }}</th>
                                <th class="text-right">
                                    {{ t('items.columns.quantity') }}
                                </th>
                                <th class="text-right">
                                    {{ t('items.columns.price') }}
                                </th>
                                <th class="text-right">
                                    {{ t('items.columns.total_value') }}
                                </th>
                                <th>{{ t('items.columns.status') }}</th>
                                <th class="text-right">
                                    {{ t('items.columns.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in items" :key="item.id">
                                <td>
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-container text-on-surface-variant"
                                    >
                                        <Boxes :size="14" />
                                    </div>
                                </td>
                                <td>
                                    <Link
                                        :href="`/items/${item.id}`"
                                        class="font-semibold text-on-surface hover:text-primary"
                                    >
                                        {{ item.title }}
                                    </Link>
                                    <p
                                        v-if="item.unit"
                                        class="text-xs text-on-surface-variant"
                                    >
                                        {{ item.unit }}
                                    </p>
                                </td>
                                <td
                                    class="font-mono text-xs text-on-surface-variant"
                                >
                                    {{ item.sku ?? '—' }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatNumber(item.warehouse_quantity) }}
                                </td>
                                <td class="text-right text-on-surface-variant">
                                    {{ formatMoney(item.purchase_price) }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatMoney(item.total_value) }}
                                </td>
                                <td>
                                    <StatusBadge :status="item.status" />
                                </td>
                                <td>
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <Link :href="`/items/${item.id}/edit`">
                                            <Button
                                                variant="ghost"
                                                type="button"
                                                :aria-label="t('common.edit')"
                                            >
                                                <Pencil :size="14" />
                                            </Button>
                                        </Link>
                                        <Button
                                            variant="ghost"
                                            type="button"
                                            :aria-label="t('common.delete')"
                                            @click="destroyItem(item.id)"
                                        >
                                            <Trash2 :size="14" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>

                <Pagination
                    v-if="items.length > 0"
                    :current-page="pagination.current_page"
                    :last-page="pagination.last_page"
                    :total="pagination.total"
                    :per-page="pagination.per_page"
                    base-url="/items"
                    :query-params="{ search: search }"
                    class="mt-4"
                />
            </Card>
        </div>
    </AppLayout>
</template>
