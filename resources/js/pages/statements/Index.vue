<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Clock3, Save, UserRound } from '@lucide/vue';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Modal from '@/components/ui/Modal.vue';
import Select from '@/components/ui/Select.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { formatCzechDate } from '@/composables/useCzechDate';
import { useRoute } from '@/composables/useRoute';
import { formatMoney, formatMonth } from '@/lib/format';

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

const props = defineProps<{
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
    filters: {
        store_id: number | null;
        year: number;
        month: number;
    };
    is_admin: boolean;
    active_attendances: ActiveAttendance[];
}>();

const { t, locale } = useI18n();

useBoundLocale();

const route = useRoute();

type TodayForm = {
    cash: string;
    card: string;
    wolt: string;
    bolt: string;
    bolt_cash: string;
    foodora: string;
};

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
                  (attendanceClockMs.value - attendanceSnapshotMs.value) / 1000,
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

const months = computed(() => {
    const now = new Date();
    const result: Array<{ value: string; label: string }> = [];
    for (let offset = 0; offset < 12; offset++) {
        const date = new Date(now.getFullYear(), now.getMonth() - offset, 1);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const label = formatMonth(year, Number(month), locale.value);
        result.push({ value: `${year}-${month}`, label });
    }
    return result;
});

const monthValue = computed(() => {
    const m = String(props.filters.month).padStart(2, '0');
    return `${props.filters.year}-${m}`;
});

function selectMonth(value: string | number | null | undefined): void {
    const raw = value === null || value === undefined ? '' : String(value);
    const [year, month] = raw.split('-').map((part: string) => Number(part));
    if (!year || !month) {
        return;
    }
    router.get(
        route('statements.index'),
        {
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
    form.put(route('statements.update', { statement: props.statement.id }), {
        preserveScroll: true,
        onSuccess: finishPendingSave,
        onError: finishPendingSave,
        onFinish: () => {
            submitting.value = false;
        },
    });
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
</script>

<template>
    <AppLayout :title="t('statements.title')">
        <Head :title="t('statements.title')" />

        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('statements.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('statements.subtitle') }}
                    </p>
                    <StoreContextIndicator />
                </div>
                <Link
                    v-if="props.statement"
                    :href="
                        route('statements.history', {
                            statement: props.statement.id,
                        })
                    "
                >
                    <Button variant="secondary">
                        {{ t('statements.actions.history') }} →
                    </Button>
                </Link>
            </header>

            <Card v-if="showTodayPanel && props.today_day" padded>
                <form class="space-y-5" @submit.prevent="saveToday">
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <h2
                                class="font-heading text-lg font-bold text-on-surface"
                            >
                                {{ t('statements.quick_entry.title') }}
                            </h2>
                            <p class="mt-1 text-sm text-on-surface-variant">
                                {{
                                    t('statements.quick_entry.description', {
                                        date: formatCzechDate(
                                            props.today_day.date,
                                        ),
                                    })
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-outline-glass bg-surface-container-low px-4 py-2"
                        >
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant"
                            >
                                {{ t('statements.quick_entry.total') }}
                            </p>
                            <p
                                class="font-heading text-lg font-bold text-on-surface"
                            >
                                {{ formatMoney(todayTotal) }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
                    >
                        <div
                            v-for="field in todayFields"
                            :key="field"
                            class="space-y-2"
                        >
                            <label
                                :for="`today_${field}`"
                                class="text-xs font-semibold text-on-surface-variant"
                            >
                                {{ t(`statements.columns.${field}`) }}
                            </label>
                            <Input
                                :id="`today_${field}`"
                                v-model="todayForm[field]"
                                type="number"
                                step="0.01"
                                min="0"
                                class="text-right"
                            />
                            <FieldError :message="todayForm.errors[field]" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="
                                todayForm.processing || checkingAttendances
                            "
                        >
                            <Save :size="14" />
                            {{ t('statements.quick_entry.save') }}
                        </Button>
                    </div>
                </form>
            </Card>

            <Card padded>
                <div class="grid gap-4 sm:grid-cols-2 lg:max-w-2xl">
                    <div class="space-y-2">
                        <label
                            for="statement_month"
                            class="text-xs font-semibold text-on-surface-variant"
                        >
                            {{ t('statements.month') }}
                        </label>
                        <Select
                            id="statement_month"
                            :model-value="monthValue"
                            :options="months"
                            @update:model-value="selectMonth"
                        />
                    </div>
                </div>
            </Card>

            <div
                v-if="!props.statement"
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-8 text-center"
            >
                <p class="text-sm font-semibold text-on-surface">
                    {{ t('statements.empty.title') }}
                </p>
                <p class="mt-1 text-xs text-on-surface-variant">
                    {{ t('statements.empty.description') }}
                </p>
            </div>

            <template v-else>
                <section class="space-y-4">
                    <DataTable density="compact">
                        <thead>
                            <tr>
                                <th class="min-w-[6rem]">
                                    {{ t('statements.columns.day') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.cash') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.card') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.wolt') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.bolt') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.bolt_cash') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.foodora') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.total') }}
                                </th>
                                <th
                                    v-if="props.is_admin"
                                    class="min-w-[5rem] text-center"
                                >
                                    {{ t('statements.columns.cash_checked') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="day in editingRows"
                                :key="editingKey(day)"
                            >
                                <td
                                    class="font-mono text-xs text-on-surface-variant"
                                >
                                    {{ formatCzechDate(day.date) }}
                                </td>
                                <td class="text-right">
                                    <Input
                                        :model-value="String(day.cash || 0)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="text-right"
                                        @update:model-value="
                                            (value) =>
                                                updateEditing(
                                                    editingKey(day),
                                                    'cash',
                                                    String(value),
                                                )
                                        "
                                    />
                                </td>
                                <td class="text-right">
                                    <Input
                                        :model-value="String(day.card || 0)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="text-right"
                                        @update:model-value="
                                            (value) =>
                                                updateEditing(
                                                    editingKey(day),
                                                    'card',
                                                    String(value),
                                                )
                                        "
                                    />
                                </td>
                                <td class="text-right">
                                    <Input
                                        :model-value="String(day.wolt || 0)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="text-right"
                                        @update:model-value="
                                            (value) =>
                                                updateEditing(
                                                    editingKey(day),
                                                    'wolt',
                                                    String(value),
                                                )
                                        "
                                    />
                                </td>
                                <td class="text-right">
                                    <Input
                                        :model-value="String(day.bolt || 0)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="text-right"
                                        @update:model-value="
                                            (value) =>
                                                updateEditing(
                                                    editingKey(day),
                                                    'bolt',
                                                    String(value),
                                                )
                                        "
                                    />
                                </td>
                                <td class="text-right">
                                    <Input
                                        :model-value="
                                            String(day.bolt_cash || 0)
                                        "
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="text-right"
                                        @update:model-value="
                                            (value) =>
                                                updateEditing(
                                                    editingKey(day),
                                                    'bolt_cash',
                                                    String(value),
                                                )
                                        "
                                    />
                                </td>
                                <td class="text-right">
                                    <Input
                                        :model-value="String(day.foodora || 0)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="text-right"
                                        @update:model-value="
                                            (value) =>
                                                updateEditing(
                                                    editingKey(day),
                                                    'foodora',
                                                    String(value),
                                                )
                                        "
                                    />
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    <div>
                                        {{ formatMoney(rowTotal(day)) }}
                                    </div>
                                    <div
                                        class="mt-0.5 text-[0.65rem] font-normal text-on-surface-variant"
                                    >
                                        {{
                                            t(
                                                'statements.columns.cash_of_total',
                                                {
                                                    amount: formatMoney(
                                                        rowCashTotal(day),
                                                    ),
                                                },
                                            )
                                        }}
                                    </div>
                                </td>
                                <td v-if="props.is_admin" class="text-center">
                                    <input
                                        type="checkbox"
                                        :checked="day.cash_checked"
                                        class="h-4 w-4 cursor-pointer rounded border-outline-glass text-primary focus:ring-primary"
                                        @change="
                                            (event) =>
                                                updateEditing(
                                                    editingKey(day),
                                                    'cash_checked',
                                                    (
                                                        event.target as HTMLInputElement
                                                    ).checked,
                                                )
                                        "
                                    />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th
                                    class="text-left text-xs font-semibold text-on-surface-variant"
                                >
                                    Σ
                                </th>
                                <th
                                    class="text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals.cash) }}
                                </th>
                                <th
                                    class="text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals.card) }}
                                </th>
                                <th
                                    class="text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals.wolt) }}
                                </th>
                                <th
                                    class="text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals.bolt) }}
                                </th>
                                <th
                                    class="text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals.bolt_cash) }}
                                </th>
                                <th
                                    class="text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals.foodora) }}
                                </th>
                                <th
                                    class="text-right text-xs font-semibold text-on-surface"
                                >
                                    <div>
                                        {{ formatMoney(totals.total) }}
                                    </div>
                                    <div
                                        class="mt-0.5 text-[0.65rem] font-normal text-on-surface-variant"
                                    >
                                        {{
                                            t(
                                                'statements.columns.cash_of_total',
                                                {
                                                    amount: formatMoney(
                                                        totals.cash +
                                                            totals.bolt_cash,
                                                    ),
                                                },
                                            )
                                        }}
                                    </div>
                                </th>
                                <th v-if="props.is_admin">
                                    {{ t('statements.columns.cash_checked') }}
                                </th>
                            </tr>
                        </tfoot>
                    </DataTable>

                    <div
                        class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end"
                    >
                        <Button
                            type="button"
                            :disabled="submitting || checkingAttendances"
                            @click="save"
                        >
                            <Save :size="14" />
                            {{ t('statements.actions.save') }}
                        </Button>
                    </div>
                </section>
            </template>
        </div>

        <Modal
            :open="attendanceModalOpen"
            :title="t('statements.attendance_close.title')"
            @close="closeAttendanceModal"
        >
            <p class="text-sm leading-6 text-on-surface-variant">
                {{ t('statements.attendance_close.description') }}
            </p>
            <div
                class="mt-4 overflow-hidden rounded-xl border border-outline-glass"
            >
                <div
                    class="flex items-center justify-between bg-surface-container-low px-4 py-3"
                >
                    <span
                        id="active-attendance-workers-label"
                        class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant"
                    >
                        {{ t('statements.attendance_close.workers') }}
                    </span>
                    <Badge variant="success">
                        {{ props.active_attendances.length }}
                    </Badge>
                </div>
                <ul
                    aria-labelledby="active-attendance-workers-label"
                    class="max-h-64 divide-y divide-outline-glass overflow-y-auto bg-surface-container-lowest"
                >
                    <li
                        v-for="attendance in props.active_attendances"
                        :key="attendance.worker_id"
                        class="flex items-center gap-3 px-4 py-3"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                        >
                            <UserRound :size="18" aria-hidden="true" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <strong
                                class="block truncate text-sm font-semibold text-on-surface"
                            >
                                {{ attendance.worker_name }}
                            </strong>
                            <span
                                class="mt-0.5 flex items-center gap-1.5 text-xs font-medium"
                                :class="
                                    attendance.is_on_break
                                        ? 'text-amber-700'
                                        : 'text-emerald-700'
                                "
                            >
                                <span
                                    class="size-2 rounded-full"
                                    :class="
                                        attendance.is_on_break
                                            ? 'bg-amber-400'
                                            : 'bg-emerald-500'
                                    "
                                    aria-hidden="true"
                                ></span>
                                {{
                                    t(
                                        attendance.is_on_break
                                            ? 'statements.attendance_close.on_break'
                                            : 'statements.attendance_close.active_status',
                                    )
                                }}
                            </span>
                        </span>
                        <span class="shrink-0 text-right">
                            <span
                                class="block text-[10px] font-semibold uppercase tracking-wide text-on-surface-variant"
                            >
                                {{
                                    t('statements.attendance_close.worked_time')
                                }}
                            </span>
                            <span
                                class="mt-1 flex items-center justify-end gap-1.5 font-mono text-sm font-semibold tabular-nums text-on-surface"
                            >
                                <Clock3
                                    :size="14"
                                    class="text-on-surface-variant"
                                    aria-hidden="true"
                                />
                                {{
                                    attendanceDuration(
                                        attendanceWorkedSeconds(attendance),
                                    )
                                }}
                            </span>
                        </span>
                    </li>
                </ul>
            </div>

            <template #footer>
                <Button
                    type="button"
                    variant="secondary"
                    :disabled="attendanceModalProcessing"
                    @click="submitPendingSave(false)"
                >
                    {{ t('statements.attendance_close.save_only') }}
                </Button>
                <Button
                    type="button"
                    :disabled="attendanceModalProcessing"
                    @click="submitPendingSave(true)"
                >
                    {{ t('statements.attendance_close.save_and_close') }}
                </Button>
            </template>
        </Modal>
    </AppLayout>
</template>
