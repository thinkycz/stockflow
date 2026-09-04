<script setup lang="ts">
import { useShiftSharing } from '@/features/shifts/useShiftSharing';
import { useShiftPresets } from '@/features/shifts/useShiftPresets';
import { useShiftEditor } from '@/features/shifts/useShiftEditor';
import { useShiftQuickAdd } from '@/features/shifts/useShiftQuickAdd';
import { router } from '@inertiajs/vue3';
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
import ShiftMonthCalendar from '@/features/shifts/components/ShiftMonthCalendar.vue';
import ShiftMonthlySummaryTable from '@/features/shifts/components/ShiftMonthlySummaryTable.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { withActionErrorToast } from '@/lib/action-errors';
import { formatDateTime } from '@/lib/format';
import { buildCalendarDays } from '@/features/shifts/shift-calendar';
import type { MonthlyShiftSummary } from '@/features/shifts/types';

import type {
    Worker,
    Shift,
    AttendanceRating,
    AttendanceRatingReason,
    ShiftRequest,
    ShiftPreset,
    ShiftShareLink,
    CalendarDay,
} from '@/features/shifts/scheduling-types';

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
    shift_share_links?: ShiftShareLink[];
    shift_presets?: ShiftPreset[];
    shift_requests?: ShiftRequest[];
    request_month_locked?: boolean;
    request_month_is_future?: boolean;
}>();

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

const calendarDays = computed(() =>
    buildCalendarDays(
        year.value,
        month.value,
        props.workers,
        localShifts.value,
        props.shift_requests ?? [],
    ),
);

const visibleCalendarDays = computed<CalendarDay[]>(() =>
    showRequests.value
        ? calendarDays.value
        : calendarDays.value.map((day) => ({ ...day, requests: [] })),
);

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

const {
    modalOpen,
    modalDate,
    editingShiftId,
    editingRequestId,
    approvingRequestId,
    form,
    requestApprovalForm,
    overlapError,
    requestOverlapError,
    workerOptions,
    presetOptions,
    timeSelectOptions,
    modalShifts,
    modalRequests,
    editingRequest,
    openDayModal,
    editShift,
    editRequest,
    cancelEdit,
    closeModal,
    approveRequest,
    submitRequestApproval,
    submitShift,
    deleteShift,
} = useShiftEditor(props, month, year, calendarDays);

const {
    shareLinksModalOpen,
    copyingShareLinkId,
    copiedShareLinkId,
    shareLinkError,
    shareLinkForm,
    openShareLinksModal,
    closeShareLinksModal,
    submitShareLink,
    copyShareLink,
    deleteShareLink,
} = useShiftSharing(props, month, year);

const {
    presetModalOpen,
    editingPresetId,
    presetForm,
    openPresetModal,
    closePresetModal,
    editPreset,
    cancelPresetEdit,
    submitPreset,
    deletePreset,
} = useShiftPresets(month, year, (id) => {
    if (selectedPresetId.value === String(id)) {
        stopQuickAdd();
        selectedPresetId.value = '';
    }
});

const {
    selectedWorkerId,
    selectedPresetId,
    quickAddActive,
    pendingDates,
    startQuickAdd,
    stopQuickAdd,
    handleDayClick,
} = useShiftQuickAdd(props, localShifts, localMonthlySummary, openDayModal);
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
                    <div v-if="store && is_admin" class="flex flex-wrap gap-2">
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
                            @click="openShareLinksModal"
                        >
                            <Link2 :size="14" />
                            {{ t('shifts.public_links.manage') }}
                        </Button>
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
                    :days="visibleCalendarDays"
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

                <div
                    v-if="is_admin && modalRequests.length > 0"
                    class="space-y-2"
                    data-testid="modal-shift-requests"
                >
                    <h3
                        class="text-xs font-semibold uppercase text-on-surface-variant"
                    >
                        {{ t('shifts.requests.heading') }}
                    </h3>
                    <div
                        v-for="shiftRequest in modalRequests"
                        :key="shiftRequest.id"
                        class="flex flex-col gap-3 rounded-lg border border-dashed border-primary/45 bg-primary-fixed/30 px-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                        data-testid="modal-shift-request"
                        :style="{
                            borderLeft: `3px dashed ${shiftRequest.worker_color}`,
                        }"
                    >
                        <div class="min-w-0 text-sm">
                            <p class="font-semibold text-on-surface">
                                {{ shiftRequest.start_time }}–{{
                                    shiftRequest.end_time
                                }}
                            </p>
                            <p class="truncate text-on-surface-variant">
                                {{ shiftRequest.worker_name }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <Button
                                variant="success"
                                size="compact"
                                type="button"
                                :loading="
                                    approvingRequestId === shiftRequest.id
                                "
                                :loading-label="t('common.saving')"
                                @click="approveRequest(shiftRequest)"
                            >
                                <Check :size="14" />
                                {{ t('shifts.requests.approve') }}
                            </Button>
                            <Button
                                variant="secondary"
                                size="compact"
                                type="button"
                                :disabled="approvingRequestId !== null"
                                @click="editRequest(shiftRequest)"
                            >
                                <Pencil :size="14" />
                                {{ t('common.edit') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <form
                    v-if="is_admin && editingRequestId !== null"
                    class="space-y-4 rounded-xl border border-primary/20 bg-primary-fixed/20 p-4"
                    data-testid="shift-request-approval-form"
                    @submit.prevent="submitRequestApproval"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3
                                class="text-xs font-semibold uppercase text-on-surface-variant"
                            >
                                {{ t('shifts.requests.edit_heading') }}
                            </h3>
                            <p
                                class="mt-1 text-sm font-semibold text-on-surface"
                            >
                                {{ editingRequest?.worker_name }} ·
                                {{ modalDate }}
                            </p>
                            <p class="mt-1 text-xs text-on-surface-variant">
                                {{ t('shifts.requests.edit_help') }}
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            type="button"
                            @click="cancelEdit"
                        >
                            <X :size="14" />
                            {{ t('common.cancel') }}
                        </Button>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="request_start_time" :required="true">
                                {{ t('shifts.columns.start_time') }}
                            </Label>
                            <Select
                                id="request_start_time"
                                v-model="requestApprovalForm.start_time"
                                required
                                :options="timeSelectOptions"
                            />
                            <FieldError
                                :message="requestApprovalForm.errors.start_time"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="request_end_time" :required="true">
                                {{ t('shifts.columns.end_time') }}
                            </Label>
                            <Select
                                id="request_end_time"
                                v-model="requestApprovalForm.end_time"
                                required
                                :options="timeSelectOptions"
                            />
                            <FieldError
                                :message="requestApprovalForm.errors.end_time"
                            />
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-end gap-3 border-t border-outline-glass pt-4"
                    >
                        <FieldError :message="requestOverlapError" />
                        <Button
                            type="submit"
                            :loading="requestApprovalForm.processing"
                            :loading-label="t('common.saving')"
                        >
                            <Check :size="14" />
                            {{ t('shifts.requests.approve_adjusted') }}
                        </Button>
                    </div>
                </form>

                <form
                    v-if="is_admin && editingRequestId === null"
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
            :open="shareLinksModalOpen"
            :title="t('shifts.public_links.title')"
            size="lg"
            @close="closeShareLinksModal"
        >
            <div class="space-y-5">
                <EmptyState
                    v-if="(shift_share_links?.length ?? 0) === 0"
                    :title="t('shifts.public_links.empty')"
                    :description="t('shifts.public_links.empty_description')"
                    density="compact"
                />
                <div v-else class="space-y-2">
                    <div
                        v-for="link in shift_share_links ?? []"
                        :key="link.id"
                        class="flex flex-col gap-3 rounded-xl border border-outline-glass px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-on-surface">
                                {{ link.name }}
                            </p>
                            <p class="text-xs text-on-surface-variant">
                                {{
                                    t('shifts.public_links.created_at', {
                                        date: formatDateTime(link.created_at),
                                    })
                                }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <Button
                                variant="secondary"
                                size="compact"
                                type="button"
                                :loading="copyingShareLinkId === link.id"
                                :loading-label="t('common.loading')"
                                @click="copyShareLink(link)"
                            >
                                <Check
                                    v-if="copiedShareLinkId === link.id"
                                    :size="14"
                                />
                                <Link2 v-else :size="14" />
                                {{
                                    copiedShareLinkId === link.id
                                        ? t('shifts.public_links.copied')
                                        : t('shifts.public_links.copy')
                                }}
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                type="button"
                                :aria-label="t('common.delete')"
                                @click="deleteShareLink(link)"
                            >
                                <Trash2 :size="14" />
                            </Button>
                        </div>
                    </div>
                </div>

                <p v-if="shareLinkError" class="text-xs text-error-red">
                    {{ shareLinkError }}
                </p>

                <form class="space-y-4" @submit.prevent="submitShareLink">
                    <h3
                        class="text-xs font-semibold uppercase text-on-surface-variant"
                    >
                        {{ t('shifts.public_links.add') }}
                    </h3>
                    <div class="space-y-2">
                        <Label for="share_link_name" :required="true">{{
                            t('shifts.public_links.name')
                        }}</Label>
                        <Input
                            id="share_link_name"
                            v-model="shareLinkForm.name"
                            type="text"
                            maxlength="100"
                            required
                        />
                        <FieldError :message="shareLinkForm.errors.name" />
                    </div>
                    <p class="text-xs text-on-surface-variant">
                        {{ t('shifts.public_links.delete_warning') }}
                    </p>
                    <div
                        class="flex justify-end border-t border-outline-glass pt-4"
                    >
                        <Button
                            type="submit"
                            :loading="shareLinkForm.processing"
                            :loading-label="t('common.saving')"
                        >
                            <Plus :size="14" />
                            {{ t('shifts.public_links.create') }}
                        </Button>
                    </div>
                </form>
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
