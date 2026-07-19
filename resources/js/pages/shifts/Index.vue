<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Check,
    Link2,
    LoaderCircle,
    Pencil,
    Plus,
    Settings2,
    Trash2,
    X,
    Zap,
} from '@lucide/vue';
import { isAxiosError } from 'axios';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { formatMoney } from '@/lib/format';

type Worker = {
    id: number;
    first_name: string;
    last_name: string;
    color: string;
};

type Shift = {
    id: number;
    worker_id: number;
    date: string;
    start_time: string;
    end_time: string;
};

type CalendarShift = Shift & {
    worker_name: string;
    worker_color: string;
};

type WorkerSummary = {
    worker_id: number;
    worker_name: string;
    color: string;
    hours: number;
    salary: number;
};

type ShiftPreset = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
};

const props = defineProps<{
    store: { id: number; name: string } | null;
    shifts: Shift[];
    workers: Worker[];
    filters: {
        store_id: number | null;
        year: number;
        month: number;
    };
    is_admin: boolean;
    worker_summary?: WorkerSummary[];
    shift_presets?: ShiftPreset[];
}>();

const { t, locale } = useI18n();

useBoundLocale();

const route = useRoute();

const year = ref<number>(props.filters.year);
const month = ref<number>(props.filters.month);
const localShifts = ref<Shift[]>([...props.shifts]);
const localWorkerSummary = ref<WorkerSummary[]>([
    ...(props.worker_summary ?? []),
]);

watch(
    () => props.shifts,
    (shifts) => {
        localShifts.value = [...shifts];
    },
);

watch(
    () => props.worker_summary,
    (summary) => {
        localWorkerSummary.value = [...(summary ?? [])];
    },
);

const monthNames = computed<Record<string, string[]>>(() => ({
    cs: [
        'Leden',
        'Únor',
        'Březen',
        'Duben',
        'Květen',
        'Červen',
        'Červenec',
        'Srpen',
        'Září',
        'Říjen',
        'Listopad',
        'Prosinec',
    ],
    en: [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ],
    sk: [
        'Január',
        'Február',
        'Marec',
        'Apríl',
        'Máj',
        'Jún',
        'Júl',
        'August',
        'September',
        'Október',
        'November',
        'December',
    ],
}));

const currentMonthLabel = computed<string>(() => {
    const names = monthNames.value[locale.value] ?? monthNames.value.cs;
    return `${names[month.value - 1]} ${year.value}`;
});

const weekdayLabels = computed<string[]>(() => {
    const labels: Record<string, string[]> = {
        cs: ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'],
        en: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        sk: ['Po', 'Ut', 'St', 'Št', 'Pi', 'So', 'Ne'],
    };
    return labels[locale.value] ?? labels.cs;
});

type CalendarDay = {
    date: string;
    day: number;
    isCurrentMonth: boolean;
    shifts: CalendarShift[];
};

const calendarDays = computed<CalendarDay[]>(() => {
    const firstOfMonth = new Date(year.value, month.value - 1, 1);
    const lastOfMonth = new Date(year.value, month.value, 0);
    const daysInMonth = lastOfMonth.getDate();

    // Monday = 0
    let startWeekday = firstOfMonth.getDay() - 1;
    if (startWeekday < 0) startWeekday = 6;

    const workerMap = new Map<number, Worker>();
    for (const w of props.workers) {
        workerMap.set(w.id, w);
    }

    const shiftsByDate = new Map<string, CalendarShift[]>();
    for (const shift of localShifts.value) {
        const worker = workerMap.get(shift.worker_id);
        const enriched: CalendarShift = {
            ...shift,
            worker_name: worker
                ? `${worker.first_name} ${worker.last_name}`
                : '?',
            worker_color: worker?.color ?? '#64748B',
        };
        const list = shiftsByDate.get(shift.date) ?? [];
        list.push(enriched);
        shiftsByDate.set(shift.date, list);
    }

    const days: CalendarDay[] = [];

    // Leading days from previous month
    for (let i = 0; i < startWeekday; i++) {
        const d = new Date(
            year.value,
            month.value - 1,
            -(startWeekday - 1 - i),
        );
        const dateStr = formatDateKey(d);
        days.push({
            date: dateStr,
            day: d.getDate(),
            isCurrentMonth: false,
            shifts: (shiftsByDate.get(dateStr) ?? []).map((s) => ({ ...s })),
        });
    }

    // Current month days
    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(year.value, month.value - 1, d);
        const dateStr = formatDateKey(date);
        days.push({
            date: dateStr,
            day: d,
            isCurrentMonth: true,
            shifts: (shiftsByDate.get(dateStr) ?? []).map((s) => ({ ...s })),
        });
    }

    // Trailing days to fill the grid (6 rows × 7 = 42)
    while (days.length < 42) {
        const lastDate = new Date(days[days.length - 1].date);
        lastDate.setDate(lastDate.getDate() + 1);
        const dateStr = formatDateKey(lastDate);
        days.push({
            date: dateStr,
            day: lastDate.getDate(),
            isCurrentMonth: false,
            shifts: (shiftsByDate.get(dateStr) ?? []).map((s) => ({ ...s })),
        });
    }

    return days;
});

function formatDateKey(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function navigateMonth(delta: number): void {
    let newMonth = month.value + delta;
    let newYear = year.value;

    if (newMonth < 1) {
        newMonth = 12;
        newYear--;
    } else if (newMonth > 12) {
        newMonth = 1;
        newYear++;
    }

    month.value = newMonth;
    year.value = newYear;

    router.get(
        route('shifts.index'),
        { month: newMonth, year: newYear },
        { preserveState: true, preserveScroll: true },
    );
}

function formatHours(value: number): string {
    return new Intl.NumberFormat(locale.value, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value);
}

// --- Modal / shift form ---

const modalOpen = ref<boolean>(false);
const modalDate = ref<string>('');
const editingShiftId = ref<number | null>(null);
const copyingPublicLink = ref<boolean>(false);
const publicLinkCopied = ref<boolean>(false);
const publicLinkError = ref<string>('');

type ShiftForm = {
    worker_id: string;
    date: string;
    start_time: string;
    end_time: string;
    allow_overlap: boolean;
};

const form = useForm<ShiftForm>({
    worker_id: '',
    date: '',
    start_time: '',
    end_time: '',
    allow_overlap: false,
});

const overlapError = computed<string | undefined>(
    () => (form.errors as Record<string, string>).overlap,
);

const timeOptions: string[] = [];
for (let h = 0; h < 24; h++) {
    for (const m of [0, 15, 30, 45]) {
        timeOptions.push(
            `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`,
        );
    }
}

const modalShifts = computed<CalendarShift[]>(() => {
    const day = calendarDays.value.find((d) => d.date === modalDate.value);
    return day?.shifts ?? [];
});

function openDayModal(date: string): void {
    if (!props.is_admin) return;
    modalDate.value = date;
    editingShiftId.value = null;
    form.reset();
    form.date = date;
    form.start_time = '09:00';
    form.end_time = '16:00';
    form.allow_overlap = false;
    form.worker_id =
        props.workers.length > 0 ? String(props.workers[0].id) : '';
    modalOpen.value = true;
}

function editShift(shift: Shift): void {
    editingShiftId.value = shift.id;
    form.worker_id = String(shift.worker_id);
    form.date = shift.date;
    form.start_time = shift.start_time;
    form.end_time = shift.end_time;
    form.allow_overlap = false;
}

function cancelEdit(): void {
    editingShiftId.value = null;
    form.worker_id =
        props.workers.length > 0 ? String(props.workers[0].id) : '';
    form.date = modalDate.value;
    form.start_time = '09:00';
    form.end_time = '16:00';
    form.allow_overlap = false;
}

function closeModal(): void {
    modalOpen.value = false;
    editingShiftId.value = null;
    form.reset();
}

function submitShift(): void {
    const confirmOverlap = (errors: Record<string, string>): void => {
        if (
            errors.overlap !== undefined &&
            !form.allow_overlap &&
            window.confirm(t('shifts.overlap_confirm'))
        ) {
            form.allow_overlap = true;
            submitShift();
        }
    };

    if (editingShiftId.value !== null) {
        form.put(
            route('shifts.update', {
                shift: editingShiftId.value,
                month: month.value,
                year: year.value,
            }),
            {
                preserveState: true,
                onError: confirmOverlap,
                onSuccess: () => {
                    editingShiftId.value = null;
                    form.reset();
                    form.date = modalDate.value;
                    form.start_time = '09:00';
                    form.end_time = '16:00';
                    form.allow_overlap = false;
                    form.worker_id =
                        props.workers.length > 0
                            ? String(props.workers[0].id)
                            : '';
                },
            },
        );
    } else {
        const nextShiftStart = form.end_time;

        form.post(
            route('shifts.store', {
                month: month.value,
                year: year.value,
            }),
            {
                preserveState: true,
                onError: confirmOverlap,
                onSuccess: () => {
                    form.reset();
                    form.date = modalDate.value;
                    form.start_time = nextShiftStart;
                    form.end_time = '21:00';
                    form.allow_overlap = false;
                    form.worker_id =
                        props.workers.length > 0
                            ? String(props.workers[0].id)
                            : '';
                },
            },
        );
    }
}

function deleteShift(id: number): void {
    if (!window.confirm(t('shifts.confirm_delete'))) return;
    router.delete(
        route('shifts.destroy', {
            shift: id,
            month: month.value,
            year: year.value,
        }),
        { preserveState: true },
    );
}

// --- Shift presets ---

const presetModalOpen = ref<boolean>(false);
const editingPresetId = ref<number | null>(null);

type PresetForm = {
    name: string;
    start_time: string;
    end_time: string;
};

const presetForm = useForm<PresetForm>({
    name: '',
    start_time: '09:00',
    end_time: '17:00',
});

function openPresetModal(): void {
    editingPresetId.value = null;
    presetForm.reset();
    presetForm.start_time = '09:00';
    presetForm.end_time = '17:00';
    presetModalOpen.value = true;
}

function closePresetModal(): void {
    presetModalOpen.value = false;
    editingPresetId.value = null;
    presetForm.reset();
}

function editPreset(preset: ShiftPreset): void {
    editingPresetId.value = preset.id;
    presetForm.name = preset.name;
    presetForm.start_time = preset.start_time;
    presetForm.end_time = preset.end_time;
    presetForm.clearErrors();
}

function cancelPresetEdit(): void {
    editingPresetId.value = null;
    presetForm.reset();
    presetForm.start_time = '09:00';
    presetForm.end_time = '17:00';
}

function submitPreset(): void {
    const options = {
        preserveState: true,
        preserveScroll: true,
        onSuccess: cancelPresetEdit,
    };

    if (editingPresetId.value !== null) {
        presetForm.put(
            route('shift-presets.update', {
                shiftPreset: editingPresetId.value,
                month: month.value,
                year: year.value,
            }),
            options,
        );
        return;
    }

    presetForm.post(
        route('shift-presets.store', {
            month: month.value,
            year: year.value,
        }),
        options,
    );
}

function deletePreset(preset: ShiftPreset): void {
    if (!window.confirm(t('shifts.presets.confirm_delete'))) return;

    if (selectedPresetId.value === String(preset.id)) {
        stopQuickAdd();
        selectedPresetId.value = '';
    }

    router.delete(
        route('shift-presets.destroy', {
            shiftPreset: preset.id,
            month: month.value,
            year: year.value,
        }),
        { preserveState: true, preserveScroll: true },
    );
}

// --- Quick add ---

type QuickFeedback = 'created' | 'exists' | 'conflict' | 'failed';

type QuickAddSuccess = {
    status: 'created' | 'exists';
    shift: Shift;
    contribution?: {
        minutes: number;
        salary: number;
    };
};

type QuickAddConflict = {
    status: 'overlap';
    conflicts: Array<{
        id: number;
        start_time: string;
        end_time: string;
    }>;
};

const selectedWorkerId = ref<string>('');
const selectedPresetId = ref<string>('');
const quickAddActive = ref<boolean>(false);
const pendingDates = ref<Set<string>>(new Set());
const quickFeedback = ref<Record<string, QuickFeedback>>({});

watch(
    () => props.store?.id,
    () => {
        stopQuickAdd();
        selectedPresetId.value = '';
        quickFeedback.value = {};
    },
);

function startQuickAdd(): void {
    if (selectedWorkerId.value === '' || selectedPresetId.value === '') return;
    quickAddActive.value = true;
    quickFeedback.value = {};
}

function stopQuickAdd(): void {
    quickAddActive.value = false;
    pendingDates.value = new Set();
}

function quickFeedbackLabel(status: QuickFeedback | undefined): string {
    return status === undefined ? '' : t('shifts.quick_add.' + status);
}

function handleDayClick(day: CalendarDay): void {
    if (!day.isCurrentMonth || !props.is_admin) return;

    if (quickAddActive.value) {
        void quickAddShift(day.date);
        return;
    }

    openDayModal(day.date);
}

async function quickAddShift(
    date: string,
    allowOverlap = false,
): Promise<void> {
    if (
        pendingDates.value.has(date) ||
        selectedWorkerId.value === '' ||
        selectedPresetId.value === ''
    ) {
        return;
    }

    pendingDates.value = new Set(pendingDates.value).add(date);
    let retryWithOverlap = false;

    try {
        const response = await window.axios.post<QuickAddSuccess>(
            route('shifts.quick-add'),
            {
                worker_id: Number(selectedWorkerId.value),
                shift_preset_id: Number(selectedPresetId.value),
                date,
                allow_overlap: allowOverlap,
            },
        );

        if (response.data.status === 'exists') {
            quickFeedback.value = {
                ...quickFeedback.value,
                [date]: 'exists',
            };
            return;
        }

        localShifts.value = [...localShifts.value, response.data.shift];
        const contribution = response.data.contribution;
        const summary = localWorkerSummary.value.find(
            (row) => row.worker_id === response.data.shift.worker_id,
        );

        if (summary !== undefined && contribution !== undefined) {
            localWorkerSummary.value = localWorkerSummary.value.map((row) =>
                row.worker_id === summary.worker_id
                    ? {
                          ...row,
                          hours: row.hours + contribution.minutes / 60,
                          salary: row.salary + contribution.salary,
                      }
                    : row,
            );
        }

        quickFeedback.value = {
            ...quickFeedback.value,
            [date]: 'created',
        };
    } catch (error: unknown) {
        if (
            isAxiosError<QuickAddConflict>(error) &&
            error.response?.status === 409
        ) {
            const conflicts = error.response.data.conflicts
                .map(
                    (conflict) => `${conflict.start_time}–${conflict.end_time}`,
                )
                .join(', ');
            quickFeedback.value = {
                ...quickFeedback.value,
                [date]: 'conflict',
            };
            retryWithOverlap = window.confirm(
                t('shifts.quick_add.overlap_confirm', { conflicts }),
            );
        } else {
            quickFeedback.value = {
                ...quickFeedback.value,
                [date]: 'failed',
            };
        }
    } finally {
        const nextPending = new Set(pendingDates.value);
        nextPending.delete(date);
        pendingDates.value = nextPending;
    }

    if (retryWithOverlap) {
        await quickAddShift(date, true);
    }
}

async function copyPublicLink(): Promise<void> {
    copyingPublicLink.value = true;
    publicLinkCopied.value = false;
    publicLinkError.value = '';

    try {
        const response = await window.axios.post<{ url: string }>(
            route('shifts.share'),
        );

        await copyText(response.data.url);
        publicLinkCopied.value = true;
    } catch {
        publicLinkError.value = t('shifts.public_link_error');
    } finally {
        copyingPublicLink.value = false;
    }
}

async function copyText(value: string): Promise<void> {
    if (navigator.clipboard !== undefined) {
        try {
            await navigator.clipboard.writeText(value);
            return;
        } catch {
            // Fall back for browsers that expose Clipboard API but deny it.
        }
    }

    const input = document.createElement('textarea');
    input.value = value;
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.select();

    const copied = document.execCommand('copy');
    input.remove();

    if (!copied) {
        throw new Error('Clipboard copy failed.');
    }
}
</script>

<template>
    <AppLayout :title="t('shifts.title')">
        <Head :title="t('shifts.title')" />

        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('shifts.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('shifts.subtitle') }}
                    </p>
                </div>
                <div
                    v-if="store && is_admin"
                    class="flex flex-col items-start gap-2 sm:items-end"
                >
                    <div class="flex flex-wrap gap-2">
                        <Button
                            variant="secondary"
                            type="button"
                            @click="openPresetModal"
                        >
                            <Settings2 :size="14" />
                            {{ t('shifts.presets.manage') }}
                        </Button>
                        <Button
                            variant="secondary"
                            type="button"
                            :disabled="copyingPublicLink"
                            @click="copyPublicLink"
                        >
                            <Check v-if="publicLinkCopied" :size="14" />
                            <Link2 v-else :size="14" />
                            {{
                                publicLinkCopied
                                    ? t('shifts.public_link_copied')
                                    : t('shifts.copy_public_link')
                            }}
                        </Button>
                    </div>
                    <p v-if="publicLinkError" class="text-xs text-error-red">
                        {{ publicLinkError }}
                    </p>
                </div>
            </header>

            <Card v-if="store && is_admin" padded>
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="grid flex-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="quick_worker">{{
                                t('shifts.quick_add.employee')
                            }}</Label>
                            <select
                                id="quick_worker"
                                v-model="selectedWorkerId"
                                :disabled="quickAddActive"
                                class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface transition focus:border-primary focus:outline-none disabled:opacity-60"
                            >
                                <option value="" disabled>
                                    {{ t('shifts.select_worker') }}
                                </option>
                                <option
                                    v-for="worker in workers"
                                    :key="worker.id"
                                    :value="String(worker.id)"
                                >
                                    {{ worker.first_name }}
                                    {{ worker.last_name }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <Label for="quick_preset">{{
                                t('shifts.quick_add.preset')
                            }}</Label>
                            <select
                                id="quick_preset"
                                v-model="selectedPresetId"
                                :disabled="quickAddActive"
                                class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface transition focus:border-primary focus:outline-none disabled:opacity-60"
                            >
                                <option value="" disabled>
                                    {{
                                        shift_presets?.length
                                            ? t(
                                                  'shifts.quick_add.select_preset',
                                              )
                                            : t('shifts.quick_add.no_presets')
                                    }}
                                </option>
                                <option
                                    v-for="preset in shift_presets ?? []"
                                    :key="preset.id"
                                    :value="String(preset.id)"
                                >
                                    {{ preset.name }} ({{
                                        preset.start_time
                                    }}–{{ preset.end_time }})
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <p
                            v-if="quickAddActive"
                            class="text-xs font-semibold text-primary"
                        >
                            {{ t('shifts.quick_add.active_help') }}
                        </p>
                        <Button
                            v-if="!quickAddActive"
                            type="button"
                            :disabled="
                                selectedWorkerId === '' ||
                                selectedPresetId === ''
                            "
                            @click="startQuickAdd"
                        >
                            <Zap :size="14" />
                            {{ t('shifts.quick_add.start') }}
                        </Button>
                        <Button
                            v-else
                            variant="secondary"
                            type="button"
                            @click="stopQuickAdd"
                        >
                            <Check :size="14" />
                            {{ t('shifts.quick_add.done') }}
                        </Button>
                        <Button
                            v-if="(shift_presets?.length ?? 0) === 0"
                            variant="ghost"
                            type="button"
                            @click="openPresetModal"
                        >
                            {{ t('shifts.quick_add.configure_first') }}
                        </Button>
                    </div>
                </div>
            </Card>

            <Card padded>
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <CalendarDays
                            :size="18"
                            class="text-on-surface-variant"
                        />
                        <span
                            class="font-heading text-lg font-bold text-on-surface"
                        >
                            {{ currentMonthLabel }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            type="button"
                            :aria-label="t('shifts.prev_month')"
                            @click="navigateMonth(-1)"
                        >
                            <ChevronLeft :size="16" />
                        </Button>
                        <Button
                            variant="ghost"
                            type="button"
                            :aria-label="t('shifts.next_month')"
                            @click="navigateMonth(1)"
                        >
                            <ChevronRight :size="16" />
                        </Button>
                    </div>
                </div>

                <div
                    v-if="!store"
                    class="py-12 text-center text-sm text-on-surface-variant"
                >
                    {{ t('shifts.no_store') }}
                </div>

                <div v-else class="overflow-x-auto">
                    <div class="grid grid-cols-7 gap-px">
                        <div
                            v-for="label in weekdayLabels"
                            :key="label"
                            class="bg-surface-container-low py-2 text-center text-xs font-semibold text-on-surface-variant"
                        >
                            {{ label }}
                        </div>
                        <div
                            v-for="day in calendarDays"
                            :key="day.date"
                            :class="[
                                'min-h-[100px] border border-outline-glass p-1.5 transition',
                                day.isCurrentMonth
                                    ? 'bg-surface-container-lowest'
                                    : 'bg-surface-container-high opacity-50',
                                is_admin && day.isCurrentMonth
                                    ? 'cursor-pointer hover:border-primary/40'
                                    : '',
                                quickAddActive && day.isCurrentMonth
                                    ? 'hover:bg-primary/5'
                                    : '',
                            ]"
                            @click="handleDayClick(day)"
                        >
                            <div
                                class="mb-1 text-xs font-semibold"
                                :class="
                                    day.isCurrentMonth
                                        ? 'text-on-surface'
                                        : 'text-on-surface-variant'
                                "
                            >
                                {{ day.day }}
                                <LoaderCircle
                                    v-if="pendingDates.has(day.date)"
                                    :size="12"
                                    class="ml-1 inline animate-spin text-primary"
                                />
                            </div>
                            <div
                                v-if="quickFeedback[day.date]"
                                class="mb-1 rounded px-1 py-0.5 text-[9px] font-semibold"
                                :class="{
                                    'bg-emerald-50 text-emerald-700':
                                        quickFeedback[day.date] === 'created',
                                    'bg-sky-50 text-sky-700':
                                        quickFeedback[day.date] === 'exists',
                                    'bg-amber-50 text-amber-700':
                                        quickFeedback[day.date] === 'conflict',
                                    'bg-red-50 text-error-red':
                                        quickFeedback[day.date] === 'failed',
                                }"
                            >
                                {{
                                    quickFeedbackLabel(quickFeedback[day.date])
                                }}
                            </div>
                            <div class="space-y-1">
                                <div
                                    v-for="shift in day.shifts"
                                    :key="shift.id"
                                    class="rounded-md border px-1.5 py-1 text-[10px] leading-tight"
                                    :style="{
                                        backgroundColor: `${shift.worker_color}18`,
                                        borderColor: `${shift.worker_color}40`,
                                        color: shift.worker_color,
                                    }"
                                >
                                    <div class="font-semibold">
                                        {{ shift.start_time }}–{{
                                            shift.end_time
                                        }}
                                    </div>
                                    <div class="truncate">
                                        {{ shift.worker_name }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>

            <Card v-if="store && is_admin" padded>
                <div class="mb-4">
                    <h2 class="font-heading text-lg font-bold text-on-surface">
                        {{ t('shifts.summary.title') }}
                    </h2>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{
                            t('shifts.summary.subtitle', {
                                month: currentMonthLabel,
                            })
                        }}
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <DataTable>
                        <thead>
                            <tr>
                                <th>{{ t('shifts.summary.worker') }}</th>
                                <th class="text-right">
                                    {{ t('shifts.summary.hours') }}
                                </th>
                                <th class="text-right">
                                    {{ t('shifts.summary.salary') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in localWorkerSummary"
                                :key="row.worker_id"
                            >
                                <td>
                                    <div
                                        class="flex items-center gap-2 font-semibold text-on-surface"
                                    >
                                        <span
                                            class="size-2.5 shrink-0 rounded-full"
                                            :style="{
                                                backgroundColor: row.color,
                                            }"
                                            aria-hidden="true"
                                        />
                                        {{ row.worker_name }}
                                    </div>
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatHours(row.hours) }} h
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatMoney(row.salary) }}
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>
            </Card>
        </div>

        <Modal
            :open="modalOpen"
            :title="t('shifts.day_title', { date: modalDate })"
            @close="closeModal"
        >
            <div class="space-y-4">
                <div v-if="modalShifts.length > 0" class="space-y-2">
                    <h3
                        class="text-xs font-semibold uppercase text-on-surface-variant"
                    >
                        {{ t('shifts.existing_shifts') }}
                    </h3>
                    <div
                        v-for="shift in modalShifts"
                        :key="shift.id"
                        class="flex items-center justify-between rounded-lg border border-l-4 border-outline-glass px-3 py-2"
                        :style="{ borderLeftColor: shift.worker_color }"
                    >
                        <div class="text-sm">
                            <span class="font-semibold text-on-surface">
                                {{ shift.start_time }}–{{ shift.end_time }}
                            </span>
                            <span class="ml-2 text-on-surface-variant">
                                {{ shift.worker_name }}
                            </span>
                        </div>
                        <div v-if="is_admin" class="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                type="button"
                                :aria-label="t('common.edit')"
                                @click="editShift(shift)"
                            >
                                <Pencil :size="14" />
                            </Button>
                            <Button
                                variant="ghost"
                                type="button"
                                :aria-label="t('common.delete')"
                                @click="deleteShift(shift.id)"
                            >
                                <Trash2 :size="14" />
                            </Button>
                        </div>
                    </div>
                </div>

                <form
                    v-if="is_admin"
                    class="space-y-4"
                    @submit.prevent="submitShift"
                >
                    <div class="flex items-center justify-between">
                        <h3
                            class="text-xs font-semibold uppercase text-on-surface-variant"
                        >
                            {{
                                editingShiftId !== null
                                    ? t('shifts.edit_shift')
                                    : t('shifts.add_shift')
                            }}
                        </h3>
                        <Button
                            v-if="editingShiftId !== null"
                            variant="ghost"
                            type="button"
                            @click="cancelEdit"
                        >
                            <X :size="14" />
                            {{ t('common.cancel') }}
                        </Button>
                    </div>

                    <div class="space-y-2">
                        <Label for="worker_id" :required="true">{{
                            t('shifts.columns.worker')
                        }}</Label>
                        <select
                            id="worker_id"
                            v-model="form.worker_id"
                            required
                            class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface transition focus:border-primary focus:outline-none"
                        >
                            <option value="" disabled>
                                {{ t('shifts.select_worker') }}
                            </option>
                            <option
                                v-for="worker in workers"
                                :key="worker.id"
                                :value="String(worker.id)"
                            >
                                {{ worker.first_name }} {{ worker.last_name }}
                            </option>
                        </select>
                        <FieldError :message="form.errors.worker_id" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="start_time" :required="true">{{
                                t('shifts.columns.start_time')
                            }}</Label>
                            <select
                                id="start_time"
                                v-model="form.start_time"
                                required
                                class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface transition focus:border-primary focus:outline-none"
                            >
                                <option
                                    v-for="time in timeOptions"
                                    :key="time"
                                    :value="time"
                                >
                                    {{ time }}
                                </option>
                            </select>
                            <FieldError :message="form.errors.start_time" />
                        </div>
                        <div class="space-y-2">
                            <Label for="end_time" :required="true">{{
                                t('shifts.columns.end_time')
                            }}</Label>
                            <select
                                id="end_time"
                                v-model="form.end_time"
                                required
                                class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface transition focus:border-primary focus:outline-none"
                            >
                                <option
                                    v-for="time in timeOptions"
                                    :key="time"
                                    :value="time"
                                >
                                    {{ time }}
                                </option>
                            </select>
                            <FieldError :message="form.errors.end_time" />
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-end gap-3 border-t border-outline-glass pt-4"
                    >
                        <FieldError :message="overlapError" />
                        <Button type="submit" :disabled="form.processing">
                            <Plus :size="14" />
                            {{
                                editingShiftId !== null
                                    ? t('common.save')
                                    : t('shifts.add_shift')
                            }}
                        </Button>
                    </div>
                </form>

                <div
                    v-else
                    class="rounded-lg bg-surface-container-high px-3 py-2 text-xs text-on-surface-variant"
                >
                    {{ t('shifts.read_only_notice') }}
                </div>
            </div>
        </Modal>

        <Modal
            :open="presetModalOpen"
            :title="t('shifts.presets.title')"
            class="max-w-2xl"
            @close="closePresetModal"
        >
            <div class="space-y-5">
                <div
                    v-if="(shift_presets?.length ?? 0) === 0"
                    class="rounded-xl bg-surface-container-low p-4 text-sm text-on-surface-variant"
                >
                    {{ t('shifts.presets.empty') }}
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="preset in shift_presets ?? []"
                        :key="preset.id"
                        class="flex items-center justify-between rounded-xl border border-outline-glass px-4 py-3"
                    >
                        <div>
                            <p class="font-semibold text-on-surface">
                                {{ preset.name }}
                            </p>
                            <p class="text-xs text-on-surface-variant">
                                {{ preset.start_time }}–{{ preset.end_time }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                type="button"
                                :aria-label="t('common.edit')"
                                @click="editPreset(preset)"
                            >
                                <Pencil :size="14" />
                            </Button>
                            <Button
                                variant="ghost"
                                type="button"
                                :aria-label="t('common.delete')"
                                @click="deletePreset(preset)"
                            >
                                <Trash2 :size="14" />
                            </Button>
                        </div>
                    </div>
                </div>

                <form class="space-y-4" @submit.prevent="submitPreset">
                    <div class="flex items-center justify-between">
                        <h3
                            class="text-xs font-semibold uppercase text-on-surface-variant"
                        >
                            {{
                                editingPresetId === null
                                    ? t('shifts.presets.add')
                                    : t('shifts.presets.edit')
                            }}
                        </h3>
                        <Button
                            v-if="editingPresetId !== null"
                            variant="ghost"
                            type="button"
                            @click="cancelPresetEdit"
                        >
                            <X :size="14" />
                            {{ t('common.cancel') }}
                        </Button>
                    </div>
                    <div class="space-y-2">
                        <Label for="preset_name" :required="true">{{
                            t('shifts.presets.name')
                        }}</Label>
                        <input
                            id="preset_name"
                            v-model="presetForm.name"
                            type="text"
                            maxlength="100"
                            required
                            class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface transition focus:border-primary focus:outline-none"
                        />
                        <FieldError :message="presetForm.errors.name" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="preset_start" :required="true">{{
                                t('shifts.columns.start_time')
                            }}</Label>
                            <select
                                id="preset_start"
                                v-model="presetForm.start_time"
                                required
                                class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                            >
                                <option
                                    v-for="time in timeOptions"
                                    :key="time"
                                    :value="time"
                                >
                                    {{ time }}
                                </option>
                            </select>
                            <FieldError
                                :message="presetForm.errors.start_time"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="preset_end" :required="true">{{
                                t('shifts.columns.end_time')
                            }}</Label>
                            <select
                                id="preset_end"
                                v-model="presetForm.end_time"
                                required
                                class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                            >
                                <option
                                    v-for="time in timeOptions"
                                    :key="time"
                                    :value="time"
                                >
                                    {{ time }}
                                </option>
                            </select>
                            <FieldError :message="presetForm.errors.end_time" />
                        </div>
                    </div>
                    <div
                        class="flex justify-end border-t border-outline-glass pt-4"
                    >
                        <Button type="submit" :disabled="presetForm.processing">
                            <Plus :size="14" />
                            {{ t('common.save') }}
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    </AppLayout>
</template>
