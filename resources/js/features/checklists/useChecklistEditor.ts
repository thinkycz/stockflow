import { router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import { withActionErrorToast } from '@/lib/action-errors';

type Task = { id: number; text: string };

type ShiftTasks = { morning: Task[]; afternoon: Task[] };

type Status =
    | 'not_configured'
    | 'in_progress'
    | 'completed'
    | 'incomplete'
    | 'excused';

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
export type ChecklistEditorProps = {
    active_store: {
        id: number;
        name: string;
        is_warehouse: boolean;
        is_active: boolean;
    } | null;
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
};

export function useChecklistEditor(props: ChecklistEditorProps) {
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
        worker_id: props.filters.worker_id
            ? String(props.filters.worker_id)
            : '',
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

    const primaryTabs = computed(() =>
        props.active_store?.is_active
            ? [
                  { value: 'templates', label: t('checklists.tabs.templates') },
                  { value: 'history', label: t('checklists.tabs.history') },
              ]
            : [{ value: 'history', label: t('checklists.tabs.history') }],
    );

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
            props.history_detail?.items.filter(
                (item) => item.completed_at !== null,
            ).length ?? 0,
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
        return route('checklists.index', {
            store_id: props.active_store?.id ?? null,
            scope,
            weekday,
            tab: 'templates',
        });
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
                store_id: props.active_store?.id ?? null,
                scope: props.filters.scope,
                weekday:
                    props.filters.scope === 'weekly'
                        ? props.filters.weekday
                        : null,
                shift,
                tasks: drafts[shift].map((task) => ({
                    text: task.text.trim(),
                })),
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
            {
                store_id: props.active_store?.id ?? null,
                tab: 'history',
                ...historyFilters,
            },
            { preserveState: true },
        );
    }

    function selectPrimaryTab(tab: string): void {
        if (tab !== 'templates' && tab !== 'history') return;
        router.get(
            route('checklists.index', {
                store_id: props.active_store?.id ?? null,
                tab,
            }),
            {},
            { preserveState: true },
        );
    }

    function detailUrl(dayId: number): string {
        return route('checklists.index', {
            store_id: props.active_store?.id ?? null,
            tab: 'history',
            day_id: dayId,
            ...historyFilters,
        });
    }

    function closeDetail(): void {
        router.get(
            route('checklists.index'),
            {
                store_id: props.active_store?.id ?? null,
                tab: 'history',
                ...historyFilters,
            },
            { preserveState: true },
        );
    }

    async function changeExcuse(excused: boolean): Promise<void> {
        if (!props.history_detail || !props.active_store?.is_active) return;
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
                { store_id: props.active_store.id, reason },
                options,
            );
        else
            router.delete(
                route('checklist-days.excuse.destroy', props.history_detail.id),
                {
                    ...options,
                    data: { store_id: props.active_store.id, reason },
                },
            );
    }
    return {
        t,
        route,
        drafts,
        saving,
        historyFilters,
        weekdayOptions,
        workerOptions,
        statusOptions,
        primaryTabs,
        historyDetailItems,
        historyDetailCompletedCount,
        statusVariant,
        shiftStatus,
        scopeUrl,
        addTask,
        removeTask,
        moveTask,
        save,
        applyHistoryFilters,
        selectPrimaryTab,
        detailUrl,
        closeDetail,
        changeExcuse,
    };
}
