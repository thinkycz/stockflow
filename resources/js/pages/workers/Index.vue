<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import SearchFilter from '@/components/ui/SearchFilter.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { useDialog } from '@/composables/useDialog';
import { withActionErrorToast } from '@/lib/action-errors';
import { formatMoney } from '@/lib/format';

type WorkerRow = {
    id: number;
    first_name: string;
    last_name: string;
    color: string;
    hourly_rate: number;
};

const props = defineProps<{
    workers: WorkerRow[];
    search: string;
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
            route('workers.index'),
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

async function destroyWorker(worker: WorkerRow): Promise<void> {
    if (
        !(await dialog.confirm({
            title: `${t('common.delete')}: ${worker.first_name} ${worker.last_name}`,
            message: t('workers.confirm_delete'),
            confirmLabel: t('common.delete'),
            variant: 'danger',
        }))
    )
        return;
    router.delete(route('workers.destroy', worker.id), withActionErrorToast());
}
</script>

<template>
    <AppLayout :title="t('workers.title')">
        <Head :title="t('workers.title')" />

        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('workers.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('workers.subtitle') }}
                    </p>
                </div>
                <div
                    class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-end"
                >
                    <SearchFilter
                        id="workers_search"
                        v-model="searchTerm"
                        :label="t('common.search')"
                        :placeholder="t('workers.search_placeholder')"
                        :busy="filtering"
                        class="w-full sm:w-72"
                    />
                    <Link :href="route('workers.create')">
                        <Button>
                            <Plus :size="14" />
                            {{ t('workers.add_worker') }}
                        </Button>
                    </Link>
                </div>
            </header>

            <section class="space-y-4">
                <EmptyState
                    v-if="workers.length === 0"
                    :title="t('workers.empty.title')"
                    :description="t('workers.empty.description')"
                >
                    <template #action>
                        <Link :href="route('workers.create')">
                            <Button>
                                <Plus :size="14" />
                                {{ t('workers.add_worker') }}
                            </Button>
                        </Link>
                    </template>
                </EmptyState>
                <DataTable v-else>
                    <thead>
                        <tr>
                            <th>
                                {{ t('workers.columns.first_name') }}
                            </th>
                            <th>{{ t('workers.columns.last_name') }}</th>
                            <th class="text-right">
                                {{ t('workers.columns.hourly_rate') }}
                            </th>
                            <th class="w-0">
                                {{ t('workers.columns.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="worker in workers" :key="worker.id">
                            <td class="font-semibold text-on-surface">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="size-2.5 shrink-0 rounded-full"
                                        :style="{
                                            backgroundColor: worker.color,
                                        }"
                                        aria-hidden="true"
                                    />
                                    {{ worker.first_name }}
                                </div>
                            </td>
                            <td class="text-on-surface">
                                {{ worker.last_name }}
                            </td>
                            <td
                                class="text-right font-semibold text-on-surface"
                            >
                                {{ formatMoney(worker.hourly_rate) }}
                            </td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <Link
                                        :href="route('workers.edit', worker.id)"
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
                                        @click="destroyWorker(worker)"
                                    >
                                        <Trash2 :size="14" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </DataTable>
            </section>
        </div>
    </AppLayout>
</template>
