<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2, Boxes } from '@lucide/vue';
import { ref, watch } from 'vue';
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
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { formatMoney } from '@/lib/format';

type ItemRow = {
    id: number;
    title: string;
    sku: string | null;
    unit: string | null;
    purchase_price: number;
    store_quantity: number | null;
};

const props = defineProps<{
    items: ItemRow[];
    search: string;
    store: { id: number; name: string } | null;
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
const submitting = ref<boolean>(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(searchTerm, (value) => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }
    searchTimer = setTimeout(() => {
        submitting.value = true;
        router.get(
            route('items.index'),
            { search: value || undefined },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: (): void => {
                    submitting.value = false;
                },
            },
        );
    }, 300);
});

function destroyItem(id: number): void {
    if (!window.confirm(t('items.confirm_delete'))) {
        return;
    }
    router.delete(route('items.destroy', id));
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
                    <Link :href="route('items.create')">
                        <Button>
                            <Plus :size="14" />
                            {{ t('items.add_item') }}
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
                        :placeholder="t('items.search_placeholder')"
                        class="pl-9"
                    />
                </div>

                <div
                    v-if="search"
                    class="mt-3 flex items-center gap-2 text-xs text-on-surface-variant"
                >
                    <span>{{ t('common.searching_for') }}:</span>
                    <Badge variant="neutral">{{ search }}</Badge>
                </div>
            </Card>

            <Card padded>
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
                        <Link :href="route('items.create')">
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
                                <th v-if="store" class="text-right">
                                    {{ store.name }}
                                </th>
                                <th class="text-right">
                                    {{ t('items.columns.price') }}
                                </th>
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
                                        :href="route('items.show', item.id)"
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
                                    v-if="store"
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ item.store_quantity ?? 0 }}
                                </td>
                                <td class="text-right text-on-surface-variant">
                                    {{ formatMoney(item.purchase_price) }}
                                </td>
                                <td>
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <Link
                                            :href="route('items.edit', item.id)"
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
                    :base-url="route('items.index')"
                    :query-params="{ search: search }"
                    class="mt-4"
                />
            </Card>
        </div>
    </AppLayout>
</template>
