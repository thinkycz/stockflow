<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    Check,
    Circle,
    Moon,
    Plus,
    Sun,
    Trash2,
    UserRound,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Select from '@/components/ui/Select.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import Tabs from '@/components/ui/Tabs.vue';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import { withActionErrorToast } from '@/lib/action-errors';

type Task = { id: number; text: string };
type ShiftTasks = { morning: Task[]; afternoon: Task[] };
type Status =
    'not_configured' | 'in_progress' | 'completed' | 'incomplete' | 'excused';
type HistoryRow = {
    id: number;
    date: string;
    morning_status: Status;
    afternoon_status: Status;
    excuse_reason: string | null;
};
type HistoryDetail = HistoryRow & {
    items: Array<{
        id: number;
        shift: 'morning' | 'afternoon';
        text: string;
        completed_at: string | null;
        worker_name: string | null;
    }>;
};

const props = defineProps<{
    active_store: { id: number; name: string; is_warehouse: boolean } | null;
    templates: { daily: ShiftTasks; weekly: Record<number, ShiftTasks> };
    history: {
        data: HistoryRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    history_detail: HistoryDetail | null;
    workers: Array<{ id: number; name: string }>;
    filters: {
        tab: 'templates' | 'history';
        scope: 'daily' | 'weekly';
        weekday: number;
        from: string;
        to: string;
        status: string;
        worker_id: number | null;
    };
}>();

const { t } = useI18n();
const route = useRoute();
const dialog = useDialog();
const drafts = reactive<Record<'morning' | 'afternoon', Task[]>>({
    morning: [],
    afternoon: [],
});
const saving = ref<'morning' | 'afternoon' | null>(null);
const historyFilters = reactive({
    from: props.filters.from,
    to: props.filters.to,
    status: props.filters.status,
    worker_id: props.filters.worker_id ? String(props.filters.worker_id) : '',
});

const currentSource = computed<ShiftTasks>(() =>
    props.filters.scope === 'daily'
        ? props.templates.daily
        : (props.templates.weekly[props.filters.weekday] ?? {
              morning: [],
              afternoon: [],
          }),
);

watch(
    currentSource,
    (source) => {
        drafts.morning = source.morning.map((task) => ({ ...task }));
        drafts.afternoon = source.afternoon.map((task) => ({ ...task }));
    },
    { immediate: true, deep: true },
);

const weekdayOptions = computed(() =>
    Array.from({ length: 7 }, (_, index) => ({
        value: String(index + 1),
        label: t(`checklists.weekdays.${index + 1}`),
    })),
);
const workerOptions = computed(() => [
    { value: '', label: t('checklists.history.all_workers') },
    ...props.workers.map((worker) => ({
        value: String(worker.id),
        label: worker.name,
    })),
]);
const statusOptions = computed(() =>
    [
        '',
        'in_progress',
        'completed',
        'incomplete',
        'excused',
        'not_configured',
    ].map((value) => ({
        value,
        label:
            value === ''
                ? t('checklists.history.all_statuses')
                : t(`checklists.status.${value}`),
    })),
);
const primaryTabs = computed(() => [
    { value: 'templates', label: t('checklists.tabs.templates') },
    { value: 'history', label: t('checklists.tabs.history') },
]);
const historyDetailItems = computed(() => ({
    morning:
        props.history_detail?.items.filter(
            (item) => item.shift === 'morning',
        ) ?? [],
    afternoon:
        props.history_detail?.items.filter(
            (item) => item.shift === 'afternoon',
        ) ?? [],
}));
const historyDetailCompletedCount = computed(
    () =>
        props.history_detail?.items.filter((item) => item.completed_at !== null)
            .length ?? 0,
);

function statusVariant(
    status: Status,
): 'neutral' | 'success' | 'warning' | 'danger' {
    switch (status) {
        case 'completed':
            return 'success';
        case 'in_progress':
            return 'warning';
        case 'incomplete':
            return 'danger';
        case 'not_configured':
        case 'excused':
            return 'neutral';
    }
}

function shiftStatus(shift: 'morning' | 'afternoon'): Status {
    return props.history_detail?.[`${shift}_status`] ?? 'not_configured';
}

function scopeUrl(
    scope: 'daily' | 'weekly',
    weekday = props.filters.weekday,
): string {
    return route('checklists.index', { scope, weekday, tab: 'templates' });
}
function addTask(shift: 'morning' | 'afternoon'): void {
    drafts[shift].push({ id: 0, text: '' });
}
function removeTask(shift: 'morning' | 'afternoon', index: number): void {
    drafts[shift].splice(index, 1);
}
function moveTask(
    shift: 'morning' | 'afternoon',
    index: number,
    direction: -1 | 1,
): void {
    const target = index + direction;
    if (target < 0 || target >= drafts[shift].length) return;
    const [task] = drafts[shift].splice(index, 1);
    if (task) drafts[shift].splice(target, 0, task);
}
function save(shift: 'morning' | 'afternoon'): void {
    saving.value = shift;
    router.put(
        route('checklists.templates.update'),
        {
            scope: props.filters.scope,
            weekday:
                props.filters.scope === 'weekly' ? props.filters.weekday : null,
            shift,
            tasks: drafts[shift].map((task) => ({ text: task.text.trim() })),
        },
        withActionErrorToast({
            preserveScroll: true,
            onFinish: () => {
                saving.value = null;
            },
        }),
    );
}
function applyHistoryFilters(): void {
    router.get(
        route('checklists.index'),
        { tab: 'history', ...historyFilters },
        { preserveState: true },
    );
}
function selectPrimaryTab(tab: string): void {
    if (tab !== 'templates' && tab !== 'history') return;
    router.get(route('checklists.index', { tab }), {}, { preserveState: true });
}
function detailUrl(dayId: number): string {
    return route('checklists.index', {
        tab: 'history',
        day_id: dayId,
        ...historyFilters,
    });
}
function closeDetail(): void {
    router.get(
        route('checklists.index'),
        { tab: 'history', ...historyFilters },
        { preserveState: true },
    );
}
async function changeExcuse(excused: boolean): Promise<void> {
    if (!props.history_detail) return;
    const reason = await dialog.prompt({
        title: excused
            ? t('checklists.history.excuse')
            : t('checklists.history.restore'),
        message: t('checklists.history.reason_help'),
        label: t('common.reason'),
        required: true,
        maxLength: 2000,
    });
    if (!reason) return;
    const options = withActionErrorToast({ preserveScroll: true });
    if (excused)
        router.put(
            route('checklist-days.excuse', props.history_detail.id),
            { reason },
            options,
        );
    else
        router.delete(
            route('checklist-days.excuse.destroy', props.history_detail.id),
            { ...options, data: { reason } },
        );
}
</script>

<template>
    <AppLayout :title="t('checklists.title')">
        <div class="space-y-6">
            <PageHeader
                :title="t('checklists.title')"
                :subtitle="t('checklists.subtitle')"
            >
                <template v-if="active_store" #actions>
                    <StoreContextIndicator />
                </template>
            </PageHeader>

            <EmptyState
                v-if="!active_store || active_store.is_warehouse"
                :title="t('checklists.retail_required')"
                :description="t('checklists.retail_required_help')"
            />

            <template v-else>
                <Tabs
                    :model-value="filters.tab"
                    :items="primaryTabs"
                    :label="t('checklists.title')"
                    variant="underline"
                    @update:model-value="selectPrimaryTab"
                />

                <template v-if="filters.tab === 'templates'">
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="scopeUrl('daily')"
                            :class="[
                                'rounded-xl px-4 py-2 text-xs font-semibold',
                                filters.scope === 'daily'
                                    ? 'bg-primary text-white'
                                    : 'bg-surface-container-low text-on-surface-variant',
                            ]"
                            >{{ t('checklists.every_day') }}</Link
                        >
                        <Link
                            v-for="option in weekdayOptions"
                            :key="option.value"
                            :href="scopeUrl('weekly', Number(option.value))"
                            :class="[
                                'rounded-xl px-4 py-2 text-xs font-semibold',
                                filters.scope === 'weekly' &&
                                filters.weekday === Number(option.value)
                                    ? 'bg-primary text-white'
                                    : 'bg-surface-container-low text-on-surface-variant',
                            ]"
                            >{{ option.label }}</Link
                        >
                    </div>

                    <div class="grid gap-4 xl:grid-cols-2">
                        <Card
                            v-for="shift in ['morning', 'afternoon'] as const"
                            :key="shift"
                        >
                            <div
                                class="mb-4 flex items-center justify-between gap-3"
                            >
                                <h2 class="font-heading text-base font-bold">
                                    {{ t(`checklists.shifts.${shift}`) }}
                                </h2>
                                <Button
                                    variant="secondary"
                                    size="compact"
                                    @click="addTask(shift)"
                                    ><Plus :size="14" />{{
                                        t('checklists.add_task')
                                    }}</Button
                                >
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="(task, index) in drafts[shift]"
                                    :key="`${task.id}-${index}`"
                                    class="flex items-center gap-2"
                                >
                                    <Input
                                        v-model="task.text"
                                        :aria-label="t('checklists.task')"
                                    />
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :disabled="index === 0"
                                        :aria-label="t('common.increase')"
                                        @click="moveTask(shift, index, -1)"
                                        ><ArrowUp :size="15"
                                    /></Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :disabled="
                                            index === drafts[shift].length - 1
                                        "
                                        :aria-label="t('common.decrease')"
                                        @click="moveTask(shift, index, 1)"
                                        ><ArrowDown :size="15"
                                    /></Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="t('common.delete')"
                                        @click="removeTask(shift, index)"
                                        ><Trash2 :size="15"
                                    /></Button>
                                </div>
                                <p
                                    v-if="drafts[shift].length === 0"
                                    class="py-4 text-sm text-on-surface-variant"
                                >
                                    {{ t('checklists.no_tasks') }}
                                </p>
                            </div>
                            <Button
                                class="mt-5"
                                :disabled="saving !== null"
                                @click="save(shift)"
                                >{{ t('common.save') }}</Button
                            >
                        </Card>
                    </div>
                </template>

                <template v-else>
                    <Card>
                        <div class="grid gap-3 md:grid-cols-4">
                            <div>
                                <Label for="checklist-from">{{
                                    t('checklists.history.from')
                                }}</Label
                                ><Input
                                    id="checklist-from"
                                    v-model="historyFilters.from"
                                    type="date"
                                />
                            </div>
                            <div>
                                <Label for="checklist-to">{{
                                    t('checklists.history.to')
                                }}</Label
                                ><Input
                                    id="checklist-to"
                                    v-model="historyFilters.to"
                                    type="date"
                                />
                            </div>
                            <div>
                                <Label for="checklist-status">{{
                                    t('checklists.history.status')
                                }}</Label
                                ><Select
                                    id="checklist-status"
                                    v-model="historyFilters.status"
                                    :options="statusOptions"
                                />
                            </div>
                            <div>
                                <Label for="checklist-worker">{{
                                    t('checklists.history.worker')
                                }}</Label
                                ><Select
                                    id="checklist-worker"
                                    v-model="historyFilters.worker_id"
                                    :options="workerOptions"
                                />
                            </div>
                        </div>
                        <Button class="mt-4" @click="applyHistoryFilters">{{
                            t('common.apply')
                        }}</Button>
                    </Card>

                    <div v-if="history.data.length > 0">
                        <DataTable>
                            <thead>
                                <tr>
                                    <th>
                                        {{ t('checklists.history.date') }}
                                    </th>
                                    <th>
                                        {{ t('checklists.shifts.morning') }}
                                    </th>
                                    <th>
                                        {{ t('checklists.shifts.afternoon') }}
                                    </th>
                                    <th>{{ t('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in history.data" :key="row.id">
                                    <td>{{ row.date }}</td>
                                    <td>
                                        <Badge
                                            :variant="
                                                statusVariant(
                                                    row.morning_status,
                                                )
                                            "
                                            >{{
                                                t(
                                                    `checklists.status.${row.morning_status}`,
                                                )
                                            }}</Badge
                                        >
                                    </td>
                                    <td>
                                        <Badge
                                            :variant="
                                                statusVariant(
                                                    row.afternoon_status,
                                                )
                                            "
                                            >{{
                                                t(
                                                    `checklists.status.${row.afternoon_status}`,
                                                )
                                            }}</Badge
                                        >
                                    </td>
                                    <td>
                                        <Link
                                            :href="detailUrl(row.id)"
                                            class="inline-flex h-8 items-center justify-center rounded-xl border border-outline-glass bg-white px-2.5 text-xs font-semibold text-on-surface transition hover:bg-surface-container-low focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:ring-offset-2"
                                            >{{ t('common.detail') }}</Link
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </DataTable>
                        <div class="pt-4">
                            <Pagination
                                :current-page="history.current_page"
                                :last-page="history.last_page"
                                :total="history.total"
                                :per-page="history.per_page"
                                :base-url="route('checklists.index')"
                                :query-params="{
                                    tab: 'history',
                                    ...historyFilters,
                                }"
                            />
                        </div>
                    </div>
                    <EmptyState
                        v-else
                        :title="t('checklists.history.empty')"
                        :description="t('checklists.history.empty_help')"
                    />
                </template>
            </template>
        </div>

        <Modal
            :open="history_detail !== null"
            :title="
                history_detail
                    ? `${t('checklists.history.detail')} · ${history_detail.date}`
                    : ''
            "
            size="full"
            class="max-h-[calc(100vh-2rem)] overflow-hidden"
            body-class="max-h-[calc(100vh-9rem)] overflow-y-auto p-0"
            @close="closeDetail"
        >
            <div v-if="history_detail">
                <div
                    class="border-b border-outline-glass bg-surface-container-low px-6 py-5"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold tracking-wide text-on-surface-variant uppercase"
                            >
                                {{ t('checklists.history.progress') }}
                            </p>
                            <p class="mt-1 font-heading text-2xl font-bold">
                                {{
                                    t('checklists.history.completed_tasks', {
                                        completed: historyDetailCompletedCount,
                                        total: history_detail.items.length,
                                    })
                                }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                :variant="
                                    statusVariant(history_detail.morning_status)
                                "
                                >{{
                                    t(
                                        `checklists.status.${history_detail.morning_status}`,
                                    )
                                }}</Badge
                            >
                            <Badge
                                :variant="
                                    statusVariant(
                                        history_detail.afternoon_status,
                                    )
                                "
                                >{{
                                    t(
                                        `checklists.status.${history_detail.afternoon_status}`,
                                    )
                                }}</Badge
                            >
                        </div>
                    </div>
                </div>

                <div
                    v-if="history_detail.excuse_reason"
                    class="mx-6 mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"
                >
                    <p class="text-xs font-semibold text-amber-900 uppercase">
                        {{ t('checklists.status.excused') }}
                    </p>
                    <p class="mt-1 text-sm text-amber-950">
                        {{ history_detail.excuse_reason }}
                    </p>
                </div>

                <div class="grid gap-4 p-6 md:grid-cols-2">
                    <section
                        v-for="shift in ['morning', 'afternoon'] as const"
                        :key="shift"
                        class="overflow-hidden rounded-2xl border border-outline-glass bg-white"
                    >
                        <div
                            class="flex items-center justify-between gap-3 border-b border-outline-glass bg-surface-container-low px-4 py-3"
                        >
                            <div class="flex items-center gap-2.5">
                                <div
                                    class="flex size-9 items-center justify-center rounded-xl bg-white text-on-surface shadow-sm"
                                >
                                    <Sun
                                        v-if="shift === 'morning'"
                                        :size="18"
                                    />
                                    <Moon v-else :size="18" />
                                </div>
                                <h3 class="font-heading text-sm font-bold">
                                    {{ t(`checklists.shifts.${shift}`) }}
                                </h3>
                            </div>
                            <Badge
                                :variant="statusVariant(shiftStatus(shift))"
                                >{{
                                    t(`checklists.status.${shiftStatus(shift)}`)
                                }}</Badge
                            >
                        </div>

                        <ul
                            v-if="historyDetailItems[shift].length > 0"
                            class="divide-y divide-outline-glass"
                        >
                            <li
                                v-for="item in historyDetailItems[shift]"
                                :key="item.id"
                                class="flex gap-3 px-4 py-3.5"
                            >
                                <span
                                    :class="[
                                        'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full',
                                        item.completed_at
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-surface-container text-on-surface-variant',
                                    ]"
                                >
                                    <Check
                                        v-if="item.completed_at"
                                        :size="13"
                                        :stroke-width="3"
                                    />
                                    <Circle
                                        v-else
                                        :size="9"
                                        fill="currentColor"
                                    />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-sm font-medium text-on-surface"
                                    >
                                        {{ item.text }}
                                    </p>
                                    <p
                                        class="mt-1 flex items-center gap-1.5 text-xs text-on-surface-variant"
                                    >
                                        <UserRound :size="13" />
                                        {{
                                            item.worker_name ??
                                            t('checklists.history.no_worker')
                                        }}
                                    </p>
                                </div>
                            </li>
                        </ul>
                        <p
                            v-else
                            class="px-4 py-8 text-center text-sm text-on-surface-variant"
                        >
                            {{ t('checklists.no_tasks') }}
                        </p>
                    </section>
                </div>
            </div>

            <template v-if="history_detail" #footer>
                <Button
                    v-if="!history_detail.excuse_reason"
                    variant="warning"
                    @click="changeExcuse(true)"
                    >{{ t('checklists.history.excuse') }}</Button
                ><Button
                    v-else
                    variant="secondary"
                    @click="changeExcuse(false)"
                    >{{ t('checklists.history.restore') }}</Button
                >
            </template>
        </Modal>
    </AppLayout>
</template>
