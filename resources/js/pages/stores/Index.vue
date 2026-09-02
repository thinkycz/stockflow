<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Store as StoreIcon, Plus, Pencil, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Pagination from '@/components/ui/Pagination.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import SearchFilter from '@/components/ui/SearchFilter.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { useDialog } from '@/composables/useDialog';
import { withActionErrorToast } from '@/lib/action-errors';
import { formatDate, formatMoney } from '@/lib/format';

type StoreRow = {
    id: number;
    name: string;
    address: string | null;
    status: 'active' | 'inactive';
    is_warehouse: boolean;
    inventory_value: number;
    sku_count: number;
    out_of_stock: number;
    risk_count: number;
    last_inventory_at: string | null;
};

const props = defineProps<{
    stores: StoreRow[];
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

const route = useRoute();
const dialog = useDialog();

const searchTerm = ref<string>(props.search || '');
const filtering = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(searchTerm, (value) => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }
    searchTimer = setTimeout(() => {
        router.get(
            route('stores.index'),
            { search: value || undefined },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => (filtering.value = true),
                onFinish: () => (filtering.value = false),
            },
        );
    }, 300);
});

async function destroyStore(store: StoreRow): Promise<void> {
    if (
        !(await dialog.confirm({
            title: `${t('common.delete')}: ${store.name}`,
            message: t('stores.confirm_delete'),
            confirmLabel: t('common.delete'),
            variant: 'danger',
        }))
    )
        return;
    router.delete(route('stores.destroy', store.id), withActionErrorToast());
}
</script>

<template>
    <AppLayout :title="t('stores.title')">
        <div class="flex flex-col gap-6">
            <PageHeader
                :title="t('stores.title')"
                :subtitle="t('stores.subtitle')"
            >
                <template #actions>
                    <div
                        class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-end"
                    >
                        <SearchFilter
                            id="stores_search"
                            v-model="searchTerm"
                            :label="t('common.search')"
                            :placeholder="t('stores.search_placeholder')"
                            :busy="filtering"
                            class="w-full sm:w-72"
                        />
                        <Link :href="route('stores.create')">
                            <Button>
                                <Plus :size="14" />
                                {{ t('stores.add_store') }}
                            </Button>
                        </Link>
                    </div>
                </template>
            </PageHeader>

            <section class="space-y-4">
                <EmptyState
                    v-if="stores.length === 0"
                    :title="t('stores.empty.title')"
                    :description="t('stores.empty.description')"
                >
                    <template #action>
                        <Link :href="route('stores.create')">
                            <Button>
                                <Plus :size="14" />
                                {{ t('stores.add_store') }}
                            </Button>
                        </Link>
                    </template>
                </EmptyState>
                <DataTable
                    v-else
                    :loading="filtering"
                    :loading-label="t('common.loading')"
                >
                    <thead>
                        <tr>
                            <th class="w-10">
                                <span class="sr-only">{{
                                    t('stores.columns.name')
                                }}</span>
                            </th>
                            <th>{{ t('stores.columns.name') }}</th>
                            <th>{{ t('stores.columns.address') }}</th>
                            <th>{{ t('stores.columns.status') }}</th>
                            <th class="text-right">
                                {{ t('stores.columns.inventory_value') }}
                            </th>
                            <th class="text-right">
                                {{ t('stores.columns.sku_count') }}
                            </th>
                            <th class="text-right">
                                {{ t('stores.columns.out_of_stock') }}
                            </th>
                            <th class="text-right">
                                {{ t('stores.columns.risk_count') }}
                            </th>
                            <th>
                                {{ t('stores.columns.last_inventory') }}
                            </th>
                            <th class="w-0">
                                {{ t('stores.columns.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="store in stores" :key="store.id">
                            <td data-mobile-hidden>
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-container text-on-surface-variant"
                                >
                                    <StoreIcon :size="14" />
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="route('stores.show', store.id)"
                                        class="font-semibold text-on-surface hover:text-primary"
                                    >
                                        {{ store.name }}
                                    </Link>
                                    <Badge
                                        v-if="store.is_warehouse"
                                        variant="neutral"
                                    >
                                        {{ t('stores.warehouse') }}
                                    </Badge>
                                </div>
                            </td>
                            <td class="text-xs text-on-surface-variant">
                                {{ store.address ?? '—' }}
                            </td>
                            <td>
                                <Badge
                                    :variant="
                                        store.status === 'active'
                                            ? 'success'
                                            : 'neutral'
                                    "
                                >
                                    {{
                                        store.status === 'active'
                                            ? t('stores.status.active')
                                            : t('stores.status.inactive')
                                    }}
                                </Badge>
                            </td>
                            <td
                                class="text-right font-semibold text-on-surface"
                            >
                                {{ formatMoney(store.inventory_value) }}
                            </td>
                            <td
                                class="text-right font-semibold text-on-surface"
                            >
                                {{ store.sku_count }}
                            </td>
                            <td class="text-right text-on-surface-variant">
                                {{ store.out_of_stock }}
                            </td>
                            <td class="text-right text-on-surface-variant">
                                {{ store.risk_count }}
                            </td>
                            <td class="text-xs text-on-surface-variant">
                                {{ formatDate(store.last_inventory_at) }}
                            </td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <Link
                                        :href="route('stores.edit', store.id)"
                                    >
                                        <Button
                                            variant="ghost"
                                            type="button"
                                            :aria-label="t('common.edit')"
                                        >
                                            <Pencil :size="14" />
                                        </Button>
                                    </Link>
                                    <Button
                                        v-if="!store.is_warehouse"
                                        variant="ghost"
                                        type="button"
                                        :aria-label="t('common.delete')"
                                        @click="destroyStore(store)"
                                    >
                                        <Trash2 :size="14" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </DataTable>
            </section>

            <Pagination
                v-if="stores.length > 0"
                :current-page="pagination.current_page"
                :last-page="pagination.last_page"
                :total="pagination.total"
                :per-page="pagination.per_page"
                :base-url="route('stores.index')"
                :query-params="{ search: searchTerm || undefined }"
            />
        </div>
    </AppLayout>
</template>
