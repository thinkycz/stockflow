<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Store as StoreIcon, Search, Plus, Pencil, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Pagination from '@/components/ui/Pagination.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
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

const searchTerm = ref<string>(props.search || '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(searchTerm, (value) => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }
    searchTimer = setTimeout(() => {
        router.get(
            route('stores.index'),
            { search: value || undefined },
            { preserveState: true, preserveScroll: true },
        );
    }, 300);
});

function destroyStore(id: number): void {
    if (!window.confirm(t('stores.confirm_delete'))) {
        return;
    }
    router.delete(route('stores.destroy', id));
}
</script>

<template>
    <AppLayout :title="t('stores.title')">
        <Head :title="t('stores.title')" />

        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('stores.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('stores.subtitle') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('stores.create')">
                        <Button>
                            <Plus :size="14" />
                            {{ t('stores.add_store') }}
                        </Button>
                    </Link>
                </div>
            </header>

            <Card padded>
                <div class="relative flex-1">
                    <Search
                        :size="14"
                        class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-on-surface-variant"
                    />
                    <Input
                        v-model="searchTerm"
                        type="search"
                        :placeholder="t('stores.search_placeholder')"
                        class="pl-9"
                    />
                </div>
            </Card>

            <Card padded>
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
                <div v-else class="overflow-x-auto">
                    <DataTable>
                        <thead>
                            <tr>
                                <th class="w-10"></th>
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
                                <td>
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-container text-on-surface-variant"
                                    >
                                        <StoreIcon :size="14" />
                                    </div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="
                                                route('stores.show', store.id)
                                            "
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
                                            :href="
                                                route('stores.edit', store.id)
                                            "
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
                                            variant="ghost"
                                            type="button"
                                            :aria-label="t('common.delete')"
                                            @click="destroyStore(store.id)"
                                        >
                                            <Trash2 :size="14" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>
            </Card>

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
