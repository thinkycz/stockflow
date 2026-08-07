<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Check,
    CircleOff,
    ClipboardList,
    Gauge,
    Link2,
    LockKeyhole,
    LockOpen,
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
import EmptyState from '@/components/ui/EmptyState.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Select from '@/components/ui/Select.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import ShiftMonthCalendar from '@/components/ShiftMonthCalendar.vue';
import ShiftMonthlySummaryTable from '@/components/ShiftMonthlySummaryTable.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { showErrorToast, showSuccessToast } from '@/composables/useClientToast';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import { withActionErrorToast } from '@/lib/action-errors';
import { sortShiftsByTime } from '@/lib/shift-calendar';
import type { MonthlyShiftSummary } from '@/types/shifts';

type Worker = {
    id: number;
    first_name: string;
    last_name: string;
    color: string;
    attendance_rating_enabled: boolean;
};

type Shift = {
    id: number;
    worker_id: number;
    date: string;
    start_time: string;
    end_time: string;
    attendance_rating?: AttendanceRating;
};

type AttendanceRatingReason =
    | 'late_arrival'
    | 'early_departure'
    | 'excessive_break_duration'
    | 'excessive_break_count'
    | 'absence';

type AttendanceRating = {
    state: 'future' | 'pending' | 'scored' | 'disabled';
    score: number | null;
    band: 'good' | 'warning' | 'poor' | null;
    reason_codes: AttendanceRatingReason[];
    arrival_offset_minutes: number | null;
    departure_offset_minutes: number | null;
    break_minutes: number | null;
    break_count: number | null;
};

type CalendarShift = Shift & {
    worker_name: string;
    worker_color: string;
};

type ShiftRequest = {
    id: number;
    worker_id: number;
    date: string;
    start_time: string;
    end_time: string;
};

type CalendarRequest = ShiftRequest & {
    worker_name: string;
    worker_color: string;
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
    monthly_summary: MonthlyShiftSummary[];
    shift_presets?: ShiftPreset[];
    shift_requests?: ShiftRequest[];
    request_month_locked?: boolean;
    request_month_is_future?: boolean;
}>();

const dialog = useDialog();

const { t, locale } = useI18n();

useBoundLocale();

const route = useRoute();

const year = ref<number>(props.filters.year);
const month = ref<number>(props.filters.month);
const showRequests = ref<boolean>(false);
const localShifts = ref<Shift[]>([...props.shifts]);
const localMonthlySummary = ref<MonthlyShiftSummary[]>([
    ...props.monthly_summary,
]);

watch(
    () => props.shifts,
    (shifts) => {
        localShifts.value = [...shifts];
    },
);

watch(
    () => props.monthly_summary,
    (summary) => {
        localMonthlySummary.value = [...summary];
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
    requests: CalendarRequest[];
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

    for (const [date, shifts] of shiftsByDate) {
        shiftsByDate.set(date, sortShiftsByTime(shifts));
    }

    const requestsByDate = new Map<string, CalendarRequest[]>();
    if (showRequests.value) {
        for (const shiftRequest of props.shift_requests ?? []) {
            const worker = workerMap.get(shiftRequest.worker_id);
            const enriched: CalendarRequest = {
                ...shiftRequest,
                worker_name: worker
                    ? `${worker.first_name} ${worker.last_name}`
                    : '?',
                worker_color: worker?.color ?? '#64748B',
            };
            const list = requestsByDate.get(shiftRequest.date) ?? [];
            list.push(enriched);
            requestsByDate.set(shiftRequest.date, list);
        }
    }

    for (const [date, requests] of requestsByDate) {
        requestsByDate.set(date, sortShiftsByTime(requests));
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
            requests: (requestsByDate.get(dateStr) ?? []).map((request) => ({
                ...request,
            })),
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
            requests: (requestsByDate.get(dateStr) ?? []).map((request) => ({
                ...request,
            })),
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
            requests: (requestsByDate.get(dateStr) ?? []).map((request) => ({
                ...request,
            })),
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

function setRequestMonthLocked(locked: boolean): void {
    router.post(
        route('shift-request-month-locks.update'),
        { year: year.value, month: month.value, locked },
        withActionErrorToast({ preserveState: true, preserveScroll: true }),
    );
}

function ratingClass(rating: AttendanceRating | undefined): string {
    if (rating?.band === 'good') return 'bg-emerald-100 text-emerald-800';
    if (rating?.band === 'warning') return 'bg-amber-100 text-amber-800';
    if (rating?.band === 'poor') return 'bg-rose-100 text-rose-800';

    return 'bg-surface-container-high text-on-surface-variant';
}

function ratingStateLabel(rating: AttendanceRating | undefined): string {
    if (rating === undefined || rating.state === 'future') {
        return t('shifts.rating.state.future');
    }
    if (rating.state === 'pending') {
        return t('shifts.rating.state.pending');
    }
    if (rating.state === 'disabled') {
        return t('shifts.rating.state.disabled');
    }

    return t(`shifts.rating.band.${rating.band ?? 'poor'}`);
}

function ratingReasonLabel(reason: AttendanceRatingReason): string {
    return t(`shifts.rating.reasons.${reason}`);
}

function formatOffset(value: number): string {
    if (value > 0) return `+${value} min`;
    if (value < 0) return `−${Math.abs(value)} min`;
    return '0 min';
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

const workerOptions = computed(() =>
    props.workers.map((worker) => ({
        value: String(worker.id),
        label: `${worker.first_name} ${worker.last_name}`,
    })),
);
const presetOptions = computed(() =>
    (props.shift_presets ?? []).map((preset) => ({
        value: String(preset.id),
        label: `${preset.name} (${preset.start_time}–${preset.end_time})`,
    })),
);
const timeSelectOptions = timeOptions.map((time) => ({
    value: time,
    label: time,
}));

const modalShifts = computed<CalendarShift[]>(() => {
    const day = calendarDays.value.find((d) => d.date === modalDate.value);
    return day?.shifts ?? [];
});

function openDayModal(date: string): void {
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
    const confirmOverlap = async (
        errors: Record<string, string>,
    ): Promise<void> => {
        if (
            errors.overlap !== undefined &&
            !form.allow_overlap &&
            (await dialog.confirm({
                title: t('common.confirm'),
                message: t('shifts.overlap_confirm'),
                confirmLabel: t('common.continue'),
                variant: 'warning',
            }))
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
                onError: (errors) => void confirmOverlap(errors),
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
                onError: (errors) => void confirmOverlap(errors),
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

async function deleteShift(id: number): Promise<void> {
    if (
        !(await dialog.confirm({
            title: t('common.delete'),
            message: t('shifts.confirm_delete'),
            confirmLabel: t('common.delete'),
            variant: 'danger',
        }))
    ) {
        return;
    }
    router.delete(
        route('shifts.destroy', {
            shift: id,
            month: month.value,
            year: year.value,
        }),
        withActionErrorToast({ preserveState: true }),
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

async function deletePreset(preset: ShiftPreset): Promise<void> {
    if (
        !(await dialog.confirm({
            title: t('common.delete'),
            message: `${preset.name}: ${t('shifts.presets.confirm_delete')}`,
            confirmLabel: t('common.delete'),
            variant: 'danger',
        }))
    ) {
        return;
    }

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
        withActionErrorToast({
            preserveState: true,
            preserveScroll: true,
        }),
    );
}

// --- Quick add ---

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

watch(
    () => props.store?.id,
    () => {
        stopQuickAdd();
        selectedPresetId.value = '';
    },
);

function startQuickAdd(): void {
    if (selectedWorkerId.value === '' || selectedPresetId.value === '') return;
    quickAddActive.value = true;
}

function stopQuickAdd(): void {
    quickAddActive.value = false;
    pendingDates.value = new Set();
}

function handleDayClick(
    day: Pick<CalendarDay, 'date' | 'isCurrentMonth'>,
): void {
    if (!day.isCurrentMonth) return;

    if (quickAddActive.value && props.is_admin) {
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
            showSuccessToast(t('shifts.quick_add.exists'));
            return;
        }

        localShifts.value = [...localShifts.value, response.data.shift];
        const contribution = response.data.contribution;
        const summary = localMonthlySummary.value.find(
            (row) => row.worker_id === response.data.shift.worker_id,
        );

        if (summary !== undefined && contribution !== undefined) {
            localMonthlySummary.value = localMonthlySummary.value.map((row) =>
                row.worker_id === summary.worker_id
                    ? {
                          ...row,
                          hours: row.hours + contribution.minutes / 60,
                          salary: (row.salary ?? 0) + contribution.salary,
                      }
                    : row,
            );
        } else if (contribution !== undefined) {
            const worker = props.workers.find(
                (row) => row.id === response.data.shift.worker_id,
            );

            if (worker !== undefined) {
                localMonthlySummary.value = [
                    ...localMonthlySummary.value,
                    {
                        worker_id: worker.id,
                        worker_name: `${worker.first_name} ${worker.last_name}`,
                        color: worker.color,
                        hours: contribution.minutes / 60,
                        salary: contribution.salary,
                        attendance_rating_enabled:
                            worker.attendance_rating_enabled,
                        average_score: null,
                        evaluated_shifts: worker.attendance_rating_enabled
                            ? 0
                            : null,
                        good_shifts: worker.attendance_rating_enabled
                            ? 0
                            : null,
                        late_arrivals: worker.attendance_rating_enabled
                            ? 0
                            : null,
                        early_departures: worker.attendance_rating_enabled
                            ? 0
                            : null,
                        break_issues: worker.attendance_rating_enabled
                            ? 0
                            : null,
                        absences: worker.attendance_rating_enabled ? 0 : null,
                    },
                ];
            }
        }

        showSuccessToast(t('shifts.quick_add.created'));
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
            retryWithOverlap = await dialog.confirm({
                title: t('shifts.quick_add.conflict'),
                message: t('shifts.quick_add.overlap_confirm', { conflicts }),
                confirmLabel: t('common.continue'),
                variant: 'warning',
            });
            if (!retryWithOverlap) {
                showErrorToast(t('shifts.quick_add.conflict'));
            }
        } else {
            showErrorToast(t('shifts.quick_add.failed'));
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
        <div class="flex flex-col gap-6">
            <PageHeader
                :title="t('shifts.title')"
                :subtitle="t('shifts.subtitle')"
            >
                <template #context>
                    <StoreContextIndicator />
                </template>
                <template #actions>
                    <div
                        v-if="store"
                        class="flex flex-col items-start gap-2 sm:items-end"
                    >
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="is_admin"
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
                        <p
                            v-if="publicLinkError"
                            class="text-xs text-error-red"
                        >
                            {{ publicLinkError }}
                        </p>
                    </div>
                </template>
            </PageHeader>

            <Card v-if="store && is_admin" padded>
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="grid flex-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="quick_worker">{{
                                t('shifts.quick_add.employee')
                            }}</Label>
                            <Select
                                id="quick_worker"
                                v-model="selectedWorkerId"
                                :disabled="quickAddActive"
                                :options="workerOptions"
                                :placeholder="t('shifts.select_worker')"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="quick_preset">{{
                                t('shifts.quick_add.preset')
                            }}</Label>
                            <Select
                                id="quick_preset"
                                v-model="selectedPresetId"
                                :disabled="quickAddActive"
                                :options="presetOptions"
                                :placeholder="
                                    shift_presets?.length
                                        ? t('shifts.quick_add.select_preset')
                                        : t('shifts.quick_add.no_presets')
                                "
                            />
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

            <section class="space-y-4">
                <div
                    class="flex flex-col gap-3 px-1 sm:flex-row sm:items-center sm:justify-between"
                >
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
                    <div class="flex flex-wrap items-center gap-1">
                        <Button
                            v-if="is_admin"
                            variant="secondary"
                            type="button"
                            :aria-pressed="showRequests"
                            @click="showRequests = !showRequests"
                        >
                            <ClipboardList :size="14" />
                            {{
                                t(
                                    showRequests
                                        ? 'shifts.requests.hide'
                                        : 'shifts.requests.show',
                                )
                            }}
                        </Button>
                        <Button
                            v-if="is_admin && request_month_is_future"
                            :variant="
                                request_month_locked ? 'secondary' : 'ghost'
                            "
                            type="button"
                            @click="
                                setRequestMonthLocked(!request_month_locked)
                            "
                        >
                            <LockOpen v-if="request_month_locked" :size="14" />
                            <LockKeyhole v-else :size="14" />
                            {{
                                request_month_locked
                                    ? t('shifts.requests.unlock')
                                    : t('shifts.requests.lock')
                            }}
                        </Button>
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

                <EmptyState
                    v-if="!store"
                    :title="t('shifts.no_store')"
                    :description="t('shifts.no_store_help')"
                />

                <ShiftMonthCalendar
                    v-else
                    :days="calendarDays"
                    :weekday-labels="weekdayLabels"
                    :interactive="true"
                    :editable="is_admin"
                    :quick-add-active="quickAddActive"
                    :pending-dates="pendingDates"
                    @activate="handleDayClick"
                />
            </section>

            <section v-if="store" class="space-y-4">
                <div class="mb-4 flex items-start gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-fixed text-primary"
                    >
                        <Gauge :size="20" />
                    </span>
                    <div>
                        <h2
                            class="font-heading text-lg font-bold text-on-surface"
                        >
                            {{ t('shifts.rating.summary.title') }}
                        </h2>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            {{
                                t('shifts.rating.summary.subtitle', {
                                    month: currentMonthLabel,
                                })
                            }}
                        </p>
                    </div>
                </div>

                <ShiftMonthlySummaryTable
                    :rows="localMonthlySummary"
                    :show-salary="is_admin"
                />
            </section>
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
                        <div class="min-w-0 flex-1 text-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-on-surface">
                                    {{ shift.start_time }}–{{ shift.end_time }}
                                </span>
                                <span class="text-on-surface-variant">
                                    {{ shift.worker_name }}
                                </span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-bold"
                                    :class="
                                        ratingClass(shift.attendance_rating)
                                    "
                                >
                                    <CircleOff
                                        v-if="
                                            shift.attendance_rating?.state ===
                                            'disabled'
                                        "
                                        :size="14"
                                        :aria-label="
                                            t('shifts.rating.state.disabled')
                                        "
                                    />
                                    <template v-else>
                                        {{
                                            shift.attendance_rating?.score !==
                                                null &&
                                            shift.attendance_rating?.score !==
                                                undefined
                                                ? t(
                                                      'shifts.rating.score_label',
                                                      {
                                                          score: shift
                                                              .attendance_rating
                                                              .score,
                                                      },
                                                  )
                                                : ratingStateLabel(
                                                      shift.attendance_rating,
                                                  )
                                        }}
                                    </template>
                                </span>
                            </div>
                            <div
                                v-if="
                                    shift.attendance_rating?.state === 'scored'
                                "
                                class="mt-2 space-y-1 text-xs text-on-surface-variant"
                            >
                                <p
                                    v-if="
                                        shift.attendance_rating
                                            .arrival_offset_minutes !== null
                                    "
                                >
                                    {{
                                        t('shifts.rating.arrival_offset', {
                                            value: formatOffset(
                                                shift.attendance_rating
                                                    .arrival_offset_minutes,
                                            ),
                                        })
                                    }}
                                </p>
                                <p
                                    v-if="
                                        shift.attendance_rating
                                            .departure_offset_minutes !== null
                                    "
                                >
                                    {{
                                        t('shifts.rating.departure_offset', {
                                            value: formatOffset(
                                                shift.attendance_rating
                                                    .departure_offset_minutes,
                                            ),
                                        })
                                    }}
                                </p>
                                <p>
                                    {{
                                        t('shifts.rating.break_detail', {
                                            minutes:
                                                shift.attendance_rating
                                                    .break_minutes,
                                            count: shift.attendance_rating
                                                .break_count,
                                        })
                                    }}
                                </p>
                                <ul
                                    v-if="
                                        shift.attendance_rating.reason_codes
                                            .length > 0
                                    "
                                    class="mt-2 space-y-1"
                                >
                                    <li
                                        v-for="reason in shift.attendance_rating
                                            .reason_codes"
                                        :key="reason"
                                        class="font-semibold text-on-surface"
                                    >
                                        • {{ ratingReasonLabel(reason) }}
                                    </li>
                                </ul>
                                <p
                                    v-else
                                    class="font-semibold text-emerald-700"
                                >
                                    {{ t('shifts.rating.no_issues') }}
                                </p>
                            </div>
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
                        <Select
                            id="worker_id"
                            v-model="form.worker_id"
                            required
                            :options="workerOptions"
                            :placeholder="t('shifts.select_worker')"
                        />
                        <FieldError :message="form.errors.worker_id" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="start_time" :required="true">{{
                                t('shifts.columns.start_time')
                            }}</Label>
                            <Select
                                id="start_time"
                                v-model="form.start_time"
                                required
                                :options="timeSelectOptions"
                            />
                            <FieldError :message="form.errors.start_time" />
                        </div>
                        <div class="space-y-2">
                            <Label for="end_time" :required="true">{{
                                t('shifts.columns.end_time')
                            }}</Label>
                            <Select
                                id="end_time"
                                v-model="form.end_time"
                                required
                                :options="timeSelectOptions"
                            />
                            <FieldError :message="form.errors.end_time" />
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-end gap-3 border-t border-outline-glass pt-4"
                    >
                        <FieldError :message="overlapError" />
                        <Button
                            type="submit"
                            :loading="form.processing"
                            :loading-label="t('common.saving')"
                        >
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
            size="lg"
            @close="closePresetModal"
        >
            <div class="space-y-5">
                <EmptyState
                    v-if="(shift_presets?.length ?? 0) === 0"
                    :title="t('shifts.presets.empty')"
                    density="compact"
                />
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
                        <Input
                            id="preset_name"
                            v-model="presetForm.name"
                            type="text"
                            maxlength="100"
                            required
                        />
                        <FieldError :message="presetForm.errors.name" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="preset_start" :required="true">{{
                                t('shifts.columns.start_time')
                            }}</Label>
                            <Select
                                id="preset_start"
                                v-model="presetForm.start_time"
                                required
                                :options="timeSelectOptions"
                            />
                            <FieldError
                                :message="presetForm.errors.start_time"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="preset_end" :required="true">{{
                                t('shifts.columns.end_time')
                            }}</Label>
                            <Select
                                id="preset_end"
                                v-model="presetForm.end_time"
                                required
                                :options="timeSelectOptions"
                            />
                            <FieldError :message="presetForm.errors.end_time" />
                        </div>
                    </div>
                    <div
                        class="flex justify-end border-t border-outline-glass pt-4"
                    >
                        <Button
                            type="submit"
                            :loading="presetForm.processing"
                            :loading-label="t('common.saving')"
                        >
                            <Plus :size="14" />
                            {{ t('common.save') }}
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    </AppLayout>
</template>
