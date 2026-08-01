<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, Boxes } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Pagination from '@/components/ui/Pagination.vue';
import SearchFilter from '@/components/ui/SearchFilter.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { useDialog } from '@/composables/useDialog';
import { withActionErrorToast } from '@/lib/action-errors';
import { formatMoney, formatNumber } from '@/lib/format';

type ItemRow = {
    id: number;
    title: string;
    sku: string | null;
    unit: string | null;
    purchase_price: number;
    total_quantity: number;
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

const route = useRoute();
const dialog = useDialog();

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

async function destroyItem(item: ItemRow): Promise<void> {
    if (
        !(await dialog.confirm({
            title: `${t('common.delete')}: ${item.title}`,
            message: t('items.confirm_delete'),
            confirmLabel: t('common.delete'),
            variant: 'danger',
        }))
    )
        return;
    router.delete(route('items.destroy', item.id), withActionErrorToast());
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
                <div
                    class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-end"
                >
                    <SearchFilter
                        id="items_search"
                        v-model="searchTerm"
                        :label="t('common.search')"
                        :placeholder="t('items.search_placeholder')"
                        :busy="submitting"
                        class="w-full sm:w-72"
                    />
                    <Link :href="route('items.create')">
                        <Button>
                            <Plus :size="14" />
                            {{ t('items.add_item') }}
                        </Button>
                    </Link>
                </div>
            </header>

            <section class="space-y-4">
                <EmptyState
                    v-if="items.length === 0"
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
                <DataTable v-else>
                    <thead>
                        <tr>
                            <th class="w-10">
                                <span class="sr-only">{{
                                    t('items.columns.title')
                                }}</span>
                            </th>
                            <th>{{ t('items.columns.title') }}</th>
                            <th>{{ t('items.columns.sku') }}</th>
                            <th class="text-right">
                                {{ t('items.columns.total_quantity') }}
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
                            <td data-mobile-hidden>
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
                                class="text-right font-semibold text-on-surface"
                            >
                                {{ formatNumber(item.total_quantity) }}
                            </td>
                            <td class="text-right text-on-surface-variant">
                                {{ formatMoney(item.purchase_price) }}
                            </td>
                            <td>
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Link :href="route('items.edit', item.id)">
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
                                        @click="destroyItem(item)"
                                    >
                                        <Trash2 :size="14" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </DataTable>

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
            </section>
        </div>
    </AppLayout>
</template>
