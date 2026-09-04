import { router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';

type DayRow = {
    id: number | null;
    date: string;
    cash: number;
    card: number;
    wolt: number;
    bolt: number;
    bolt_cash: number;
    foodora: number;
    total: number;
    cash_checked: boolean;
};

type ActiveAttendance = {
    worker_id: number;
    worker_name: string;
    worked_seconds: number;
    is_on_break: boolean;
};

type TodayForm = {
    cash: string;
    card: string;
    wolt: string;
    bolt: string;
    bolt_cash: string;
    foodora: string;
};
export type StatementEditorProps = {
    statement: {
        id: number;
        store_id: number;
        year: number;
        month: number;
    } | null;
    days: DayRow[];
    today_statement: {
        id: number;
        store_id: number;
        year: number;
        month: number;
    } | null;
    today_day: DayRow | null;
    store: {
        id: number;
        name: string;
        is_active: boolean;
    } | null;
    editable: boolean;
    filters: {
        store_id: number | null;
        year: number;
        month: number;
    };
    is_admin: boolean;
    bank_reconciliation: {
        statement_id: number | null;
        status: string;
        counts: {
            matched: number;
            mismatch: number;
            unresolved: number;
            excluded: number;
        };
    } | null;
    active_attendances: ActiveAttendance[];
};

export function useStatementEditor(props: StatementEditorProps) {
    const { t } = useI18n();

    useBoundLocale();

    const route = useRoute();

    const todayFields: Array<keyof TodayForm> = [
        'cash',
        'card',
        'wolt',
        'bolt',
        'bolt_cash',
        'foodora',
    ];

    const form = useForm<{ days: DayRow[]; close_attendances: boolean }>({
        days: props.days.map((day) => ({ ...day })),
        close_attendances: false,
    });

    const todayForm = useForm<TodayForm & { close_attendances: boolean }>({
        cash: String(props.today_day?.cash ?? 0),
        card: String(props.today_day?.card ?? 0),
        wolt: String(props.today_day?.wolt ?? 0),
        bolt: String(props.today_day?.bolt ?? 0),
        bolt_cash: String(props.today_day?.bolt_cash ?? 0),
        foodora: String(props.today_day?.foodora ?? 0),
        close_attendances: false,
    });

    watch(
        () => props.today_day,
        (day) => {
            todayForm.cash = String(day?.cash ?? 0);
            todayForm.card = String(day?.card ?? 0);
            todayForm.wolt = String(day?.wolt ?? 0);
            todayForm.bolt = String(day?.bolt ?? 0);
            todayForm.bolt_cash = String(day?.bolt_cash ?? 0);
            todayForm.foodora = String(day?.foodora ?? 0);
            todayForm.clearErrors();
        },
    );

    const editing = reactive<Record<string, DayRow>>({});

    function syncEditing(days: DayRow[]): void {
        for (const key of Object.keys(editing)) {
            delete editing[key];
        }
        for (const day of days) {
            editing[day.id !== null ? String(day.id) : day.date] = { ...day };
        }
    }

    syncEditing(props.days);

    // Keep `editing` aligned with `props.days` so that re-mounts, redirects
    // after save and other Inertia-driven prop refreshes re-seed the form
    // with the latest values from the server.
    watch(
        () => props.days,
        (newDays) => {
            syncEditing(newDays);
        },
    );

    const editingRows = computed(() =>
        Object.values(editing).sort((a, b) => a.date.localeCompare(b.date)),
    );

    const submitting = ref(false);

    const checkingAttendances = ref(false);

    const pendingSave = ref<'statement' | 'today' | null>(null);

    const attendanceModalOpen = computed(() => pendingSave.value !== null);

    const attendanceModalProcessing = computed(
        () => form.processing || todayForm.processing,
    );

    const attendanceClockMs = ref(Date.now());

    const attendanceSnapshotMs = ref(Date.now());

    let attendanceClockTimer: ReturnType<typeof setInterval> | null = null;

    watch(
        () => props.active_attendances,
        () => {
            attendanceClockMs.value = Date.now();
            attendanceSnapshotMs.value = attendanceClockMs.value;
        },
    );

    onMounted(() => {
        attendanceClockTimer = setInterval(() => {
            attendanceClockMs.value = Date.now();
        }, 1000);
    });

    onUnmounted(() => {
        if (attendanceClockTimer !== null) {
            clearInterval(attendanceClockTimer);
        }
    });

    function attendanceWorkedSeconds(attendance: ActiveAttendance): number {
        const liveSeconds = attendance.is_on_break
            ? 0
            : Math.max(
                  0,
                  Math.floor(
                      (attendanceClockMs.value - attendanceSnapshotMs.value) /
                          1000,
                  ),
              );

        return attendance.worked_seconds + liveSeconds;
    }

    function attendanceDuration(seconds: number): string {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainingSeconds = seconds % 60;

        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
    }

    const monthValue = computed(() => {
        const m = String(props.filters.month).padStart(2, '0');
        return `${props.filters.year}-${m}`;
    });

    function selectMonth(value: string): void {
        const [year, month] = value.split('-').map(Number);
        if (!year || !month) {
            return;
        }
        router.get(
            route('statements.index'),
            {
                store_id: props.filters.store_id,
                year,
                month,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function rowTotal(row: DayRow): number {
        return (
            Number(row.cash || 0) +
            Number(row.card || 0) +
            Number(row.wolt || 0) +
            Number(row.bolt || 0) +
            Number(row.bolt_cash || 0) +
            Number(row.foodora || 0)
        );
    }

    function rowCashTotal(row: DayRow): number {
        return Number(row.cash || 0) + Number(row.bolt_cash || 0);
    }

    const showTodayPanel = computed(
        () =>
            props.editable &&
            props.today_statement !== null &&
            props.today_day !== null &&
            rowTotal(props.today_day) === 0,
    );

    const todayTotal = computed(
        () =>
            Number(todayForm.cash || 0) +
            Number(todayForm.card || 0) +
            Number(todayForm.wolt || 0) +
            Number(todayForm.bolt || 0) +
            Number(todayForm.bolt_cash || 0) +
            Number(todayForm.foodora || 0),
    );

    const totals = computed(() => {
        let cash = 0;
        let card = 0;
        let wolt = 0;
        let bolt = 0;
        let boltCash = 0;
        let foodora = 0;
        let total = 0;
        for (const day of editingRows.value) {
            cash += Number(day.cash || 0);
            card += Number(day.card || 0);
            wolt += Number(day.wolt || 0);
            bolt += Number(day.bolt || 0);
            boltCash += Number(day.bolt_cash || 0);
            foodora += Number(day.foodora || 0);
            total += rowTotal(day);
        }
        return {
            cash,
            card,
            wolt,
            bolt,
            bolt_cash: boltCash,
            foodora,
            total,
        };
    });

    function updateEditing(
        key: string,
        field: keyof DayRow,
        value: string | boolean,
    ): void {
        const day = editing[key];
        if (!day) {
            return;
        }
        if (field === 'date') {
            day.date = String(value);
        } else if (field === 'cash_checked') {
            day.cash_checked = Boolean(value);
        } else {
            const numeric = Number(value);
            (day as unknown as Record<string, number>)[field] = Number.isFinite(
                numeric,
            )
                ? numeric
                : 0;
        }
        day.total = rowTotal(day);
    }

    function editingKey(day: DayRow): string {
        return day.id !== null ? String(day.id) : day.date;
    }

    function finishPendingSave(): void {
        pendingSave.value = null;
    }

    function closeAttendanceModal(): void {
        if (!attendanceModalProcessing.value) {
            finishPendingSave();
        }
    }

    function submitStatement(closeAttendances: boolean): void {
        if (!props.statement) {
            return;
        }
        submitting.value = true;
        form.days = editingRows.value;
        form.close_attendances = closeAttendances;
        form.put(
            route('statements.update', { statement: props.statement.id }),
            {
                preserveScroll: true,
                onSuccess: finishPendingSave,
                onError: finishPendingSave,
                onFinish: () => {
                    submitting.value = false;
                },
            },
        );
    }

    function submitToday(closeAttendances: boolean): void {
        if (!props.today_statement) {
            return;
        }

        todayForm.close_attendances = closeAttendances;
        todayForm.put(
            route('statements.today.update', {
                statement: props.today_statement.id,
            }),
            {
                preserveScroll: true,
                onSuccess: finishPendingSave,
                onError: finishPendingSave,
            },
        );
    }

    function shouldCheckAttendances(kind: 'statement' | 'today'): boolean {
        return (
            kind === 'today' ||
            (props.statement !== null &&
                props.today_statement !== null &&
                props.statement.id === props.today_statement.id)
        );
    }

    function checkAttendancesBeforeSave(kind: 'statement' | 'today'): void {
        checkingAttendances.value = true;
        router.reload({
            only: ['active_attendances'],
            onSuccess: () => {
                if (props.active_attendances.length > 0) {
                    pendingSave.value = kind;
                } else if (kind === 'statement') {
                    submitStatement(false);
                } else {
                    submitToday(false);
                }
            },
            onFinish: () => {
                checkingAttendances.value = false;
            },
        });
    }

    function save(): void {
        if (shouldCheckAttendances('statement')) {
            checkAttendancesBeforeSave('statement');
            return;
        }

        submitStatement(false);
    }

    function saveToday(): void {
        if (shouldCheckAttendances('today')) {
            checkAttendancesBeforeSave('today');
            return;
        }

        submitToday(false);
    }

    function submitPendingSave(closeAttendances: boolean): void {
        if (pendingSave.value === 'statement') {
            submitStatement(closeAttendances);
        } else if (pendingSave.value === 'today') {
            submitToday(closeAttendances);
        }
    }
    return {
        t,
        route,
        todayFields,
        form,
        todayForm,
        editingRows,
        submitting,
        checkingAttendances,
        attendanceModalOpen,
        attendanceModalProcessing,
        attendanceWorkedSeconds,
        attendanceDuration,
        monthValue,
        selectMonth,
        rowTotal,
        rowCashTotal,
        showTodayPanel,
        todayTotal,
        totals,
        updateEditing,
        editingKey,
        closeAttendanceModal,
        save,
        saveToday,
        submitPendingSave,
    };
}
