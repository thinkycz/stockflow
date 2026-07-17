<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Check,
    Link2,
    Pencil,
    Plus,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
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
}>();

const { t, locale } = useI18n();

useBoundLocale();

const route = useRoute();

const year = ref<number>(props.filters.year);
const month = ref<number>(props.filters.month);

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
    for (const shift of props.shifts) {
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
};

const form = useForm<ShiftForm>({
    worker_id: '',
    date: '',
    start_time: '',
    end_time: '',
});

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
}

function cancelEdit(): void {
    editingShiftId.value = null;
    form.worker_id =
        props.workers.length > 0 ? String(props.workers[0].id) : '';
    form.date = modalDate.value;
    form.start_time = '09:00';
    form.end_time = '16:00';
}

function closeModal(): void {
    modalOpen.value = false;
    editingShiftId.value = null;
    form.reset();
}

function submitShift(): void {
    if (editingShiftId.value !== null) {
        form.put(
            route('shifts.update', {
                shift: editingShiftId.value,
                month: month.value,
                year: year.value,
            }),
            {
                preserveState: true,
                onSuccess: () => {
                    editingShiftId.value = null;
                    form.reset();
                    form.date = modalDate.value;
                    form.start_time = '09:00';
                    form.end_time = '16:00';
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
                onSuccess: () => {
                    form.reset();
                    form.date = modalDate.value;
                    form.start_time = nextShiftStart;
                    form.end_time = '21:00';
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
                    <p v-if="publicLinkError" class="text-xs text-error-red">
                        {{ publicLinkError }}
                    </p>
                </div>
            </header>

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
                            ]"
                            @click="
                                day.isCurrentMonth
                                    ? openDayModal(day.date)
                                    : undefined
                            "
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
                                v-for="row in worker_summary ?? []"
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
    </AppLayout>
</template>
