<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    CircleCheck,
    CircleOff,
    Plus,
    Pencil,
    RotateCcw,
    Trash2,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import SearchFilter from '@/components/ui/SearchFilter.vue';
import Select from '@/components/ui/Select.vue';
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
    attendance_rating_enabled: boolean;
    archived: boolean;
};

const props = defineProps<{
    workers: WorkerRow[];
    search: string;
    status: 'active' | 'archived' | 'all';
}>();

const { t } = useI18n();

useBoundLocale();

const route = useRoute();
const dialog = useDialog();

const searchTerm = ref<string>(props.search || '');
const status = ref<'active' | 'archived' | 'all'>(props.status);
const filtering = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch([searchTerm, status], ([value, lifecycleStatus]) => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }
    searchTimer = setTimeout(() => {
        router.get(
            route('workers.index'),
            {
                search: value || undefined,
                status:
                    lifecycleStatus === 'active' ? undefined : lifecycleStatus,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => (filtering.value = true),
                onFinish: () => (filtering.value = false),
            },
        );
    }, 300);
});

const statusOptions = [
    { value: 'active', label: t('workers.filters.active') },
    { value: 'archived', label: t('workers.filters.archived') },
    { value: 'all', label: t('workers.filters.all') },
];

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

function restoreWorker(worker: WorkerRow): void {
    router.post(
        route('workers.restore', worker.id),
        {},
        withActionErrorToast(),
    );
}
</script>

<template>
    <AppLayout :title="t('workers.title')">
        <div class="flex flex-col gap-6">
            <PageHeader
                :title="t('workers.title')"
                :subtitle="t('workers.subtitle')"
            >
                <template #actions>
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
                        <Select
                            v-model="status"
                            :options="statusOptions"
                            :aria-label="t('workers.filters.label')"
                            class="w-full sm:w-40"
                        />
                        <Link :href="route('workers.create')">
                            <Button>
                                <Plus :size="14" />
                                {{ t('workers.add_worker') }}
                            </Button>
                        </Link>
                    </div>
                </template>
            </PageHeader>

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
                <DataTable
                    v-else
                    :loading="filtering"
                    :loading-label="t('common.loading')"
                >
                    <thead>
                        <tr>
                            <th>
                                {{ t('workers.columns.first_name') }}
                            </th>
                            <th>{{ t('workers.columns.last_name') }}</th>
                            <th class="text-right">
                                {{ t('workers.columns.hourly_rate') }}
                            </th>
                            <th>
                                {{ t('workers.columns.attendance_rating') }}
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
                                <Badge
                                    :variant="
                                        worker.attendance_rating_enabled
                                            ? 'success'
                                            : 'neutral'
                                    "
                                >
                                    <CircleCheck
                                        v-if="worker.attendance_rating_enabled"
                                        :size="13"
                                        aria-hidden="true"
                                    />
                                    <CircleOff
                                        v-else
                                        :size="13"
                                        aria-hidden="true"
                                    />
                                    {{
                                        worker.attendance_rating_enabled
                                            ? t('workers.rating_status.enabled')
                                            : t(
                                                  'workers.rating_status.disabled',
                                              )
                                    }}
                                </Badge>
                            </td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <Link
                                        v-if="!worker.archived"
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
                                        v-if="worker.archived"
                                        variant="ghost"
                                        type="button"
                                        :aria-label="t('workers.restore')"
                                        @click="restoreWorker(worker)"
                                    >
                                        <RotateCcw :size="14" />
                                    </Button>
                                    <Button
                                        v-else
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
