<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    CircleCheck,
    CircleHelp,
    Clock3,
    Coffee,
    Frown,
    LogIn,
    LogOut,
    Meh,
    Smile,
    TriangleAlert,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import Select from '@/components/ui/Select.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';

type AttendanceStatus = 'absent' | 'present' | 'break' | 'stale';
type BreakRow = { started_at: string; ended_at: string | null };
type SessionRow = {
    id: number;
    started_at: string;
    ended_at: string | null;
    breaks: BreakRow[];
};
type AttendanceRow = {
    worker_id: number;
    worker_name: string;
    status: AttendanceStatus;
    has_current_shift: boolean;
    shifts: Array<{ id: number; start_time: string; end_time: string }>;
    sessions: SessionRow[];
    quality: {
        average_score: number | null;
        evaluated_shifts: number;
        band: 'good' | 'warning' | 'poor' | null;
    };
};

const props = defineProps<{
    store: { id: number; name: string; is_warehouse: boolean } | null;
    attendance_rows: AttendanceRow[];
    off_schedule_workers: Array<{ id: number; name: string }>;
    store_state: 'occupied' | 'empty' | 'unclear';
    is_admin: boolean;
}>();

const { t, locale } = useI18n();
const route = useRoute();
const dialog = useDialog();
const nowMs = ref(Date.now());
const offScheduleOpen = ref(false);
const offScheduleWorkerId = ref('');
const pendingWorkerId = ref<number | null>(null);
const actionForm = useForm({
    worker_id: '',
    action: '',
    confirm_without_shift: false,
});

const offScheduleOptions = computed(() =>
    props.off_schedule_workers.map((worker) => ({
        value: String(worker.id),
        label: worker.name,
    })),
);

function timeOnly(value: string | null): string {
    if (value === null) return t('attendance.now');
    return new Intl.DateTimeFormat(locale.value, {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function intervalSeconds(start: string, end: string | null): number {
    return Math.max(
        0,
        Math.floor(
            ((end === null ? nowMs.value : Date.parse(end)) -
                Date.parse(start)) /
                1000,
        ),
    );
}

function breakSeconds(session: SessionRow): number {
    return session.breaks.reduce(
        (total, pause) =>
            total + intervalSeconds(pause.started_at, pause.ended_at),
        0,
    );
}

function workedSeconds(row: AttendanceRow): number {
    return row.sessions.reduce(
        (total, session) =>
            total +
            Math.max(
                0,
                intervalSeconds(session.started_at, session.ended_at) -
                    breakSeconds(session),
            ),
        0,
    );
}

function conciseDuration(seconds: number): string {
    const minutes = Math.floor(seconds / 60);
    if (minutes < 1) return t('attendance.duration.less_than_minute');
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    if (hours === 0) return `${minutes} min`;
    if (remainingMinutes === 0) return `${hours} h`;
    return `${hours} h ${remainingMinutes} min`;
}

function allBreaks(row: AttendanceRow): BreakRow[] {
    return row.sessions.flatMap((session) => session.breaks);
}

function qualityText(row: AttendanceRow): string {
    if (row.quality.average_score === null)
        return t('attendance.quality.unrated');
    return t(`attendance.quality.${row.quality.band}`, {
        score: row.quality.average_score,
    });
}

function qualityClass(row: AttendanceRow): string {
    if (row.quality.band === 'good') return 'text-emerald-700';
    if (row.quality.band === 'warning') return 'text-amber-700';
    if (row.quality.band === 'poor') return 'text-error-red';
    return 'text-on-surface-variant';
}

function statusClass(status: AttendanceStatus): string {
    if (status === 'present') return 'bg-emerald-100 text-emerald-800';
    if (status === 'break') return 'bg-amber-100 text-amber-800';
    if (status === 'stale') return 'bg-red-100 text-error-red';
    return 'bg-surface-container text-on-surface-variant';
}

function postAction(
    workerId: number,
    action: string,
    confirmed: boolean,
): void {
    pendingWorkerId.value = workerId;
    actionForm.worker_id = String(workerId);
    actionForm.action = action;
    actionForm.confirm_without_shift = confirmed;
    actionForm.post(route('attendance.actions.store'), {
        preserveScroll: true,
        onFinish: () => {
            pendingWorkerId.value = null;
        },
    });
}

async function perform(row: AttendanceRow, action: string): Promise<void> {
    let confirmed = false;
    if (action === 'arrival' && !row.has_current_shift) {
        confirmed = await dialog.confirm({
            title: t('attendance.no_shift.title'),
            message: t('attendance.no_shift.description'),
            confirmLabel: t('attendance.no_shift.confirm'),
            variant: 'warning',
        });
        if (!confirmed) return;
    }
    postAction(row.worker_id, action, confirmed);
}

function performOffScheduleArrival(): void {
    const workerId = Number(offScheduleWorkerId.value);
    if (!workerId) return;
    postAction(workerId, 'arrival', true);
    offScheduleOpen.value = false;
    offScheduleWorkerId.value = '';
}

let refreshTimer: ReturnType<typeof setInterval> | null = null;
let clockTimer: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
    clockTimer = setInterval(() => {
        nowMs.value = Date.now();
    }, 1000);
    refreshTimer = setInterval(() => {
        if (!offScheduleOpen.value && !actionForm.processing)
            router.reload({
                only: [
                    'attendance_rows',
                    'off_schedule_workers',
                    'store_state',
                ],
            });
    }, 30_000);
});
onUnmounted(() => {
    if (refreshTimer !== null) clearInterval(refreshTimer);
    if (clockTimer !== null) clearInterval(clockTimer);
});
</script>

<template>
    <AppLayout :title="t('attendance.title')">
        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('attendance.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('attendance.subtitle') }}
                    </p>
                    <StoreContextIndicator />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="
                            store &&
                            !store.is_warehouse &&
                            off_schedule_workers.length > 0
                        "
                        size="compact"
                        variant="secondary"
                        @click="offScheduleOpen = true"
                    >
                        <LogIn :size="14" />
                        {{ t('attendance.actions.off_schedule_arrival') }}
                    </Button>
                    <Link
                        v-if="is_admin && store && !store.is_warehouse"
                        :href="route('attendance.report')"
                    >
                        <Button variant="secondary">
                            {{ t('attendance.report.title') }}
                            <ArrowRight :size="15" />
                        </Button>
                    </Link>
                </div>
            </header>

            <EmptyState
                v-if="!store || store.is_warehouse"
                :title="t('attendance.retail_required')"
                icon="inbox"
            />
            <template v-else>
                <div
                    role="alert"
                    class="flex items-start gap-4 rounded-2xl border p-5 shadow-sm"
                    :class="
                        store_state === 'occupied'
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                            : store_state === 'unclear'
                              ? 'border-warning-amber/40 bg-warning-amber/10 text-warning-amber'
                              : 'border-error-red/35 bg-error-red/10 text-error-red'
                    "
                >
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/80"
                    >
                        <CircleCheck
                            v-if="store_state === 'occupied'"
                            :size="23"
                        />
                        <TriangleAlert v-else :size="23" />
                    </span>
                    <div>
                        <p class="text-base font-bold">
                            {{ t(`attendance.store_state.${store_state}`) }}
                        </p>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            {{
                                t(
                                    `attendance.store_state_description.${store_state}`,
                                )
                            }}
                        </p>
                    </div>
                </div>

                <section aria-labelledby="attendance-today-title">
                    <div class="mb-4">
                        <h2
                            id="attendance-today-title"
                            class="font-heading text-lg font-bold text-on-surface"
                        >
                            {{ t('attendance.today') }}
                        </h2>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            {{ t('attendance.today_help') }}
                        </p>
                    </div>

                    <EmptyState
                        v-if="attendance_rows.length === 0"
                        :title="t('attendance.empty.title')"
                        :description="t('attendance.empty.description')"
                        icon="inbox"
                    />
                    <DataTable
                        v-else
                        data-testid="attendance-table"
                        table-class="md:min-w-[1080px]"
                    >
                        <thead>
                            <tr>
                                <th>{{ t('attendance.worker') }}</th>
                                <th>{{ t('attendance.table.shift') }}</th>
                                <th>{{ t('attendance.table.work') }}</th>
                                <th>{{ t('attendance.breaks') }}</th>
                                <th>{{ t('attendance.table.status') }}</th>
                                <th>{{ t('attendance.table.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in attendance_rows"
                                :key="row.worker_id"
                                :data-testid="`attendance-row-${row.worker_id}`"
                            >
                                <td :data-label="t('attendance.worker')">
                                    <p class="font-semibold text-on-surface">
                                        {{ row.worker_name }}
                                    </p>
                                    <div
                                        class="mt-2 flex items-center gap-1.5 text-xs font-semibold"
                                        :class="qualityClass(row)"
                                        :title="
                                            t('attendance.quality.tooltip', {
                                                count: row.quality
                                                    .evaluated_shifts,
                                            })
                                        "
                                    >
                                        <Smile
                                            v-if="row.quality.band === 'good'"
                                            :size="18"
                                            aria-hidden="true"
                                        />
                                        <Meh
                                            v-else-if="
                                                row.quality.band === 'warning'
                                            "
                                            :size="18"
                                            aria-hidden="true"
                                        />
                                        <Frown
                                            v-else-if="
                                                row.quality.band === 'poor'
                                            "
                                            :size="18"
                                            aria-hidden="true"
                                        />
                                        <CircleHelp
                                            v-else
                                            :size="18"
                                            aria-hidden="true"
                                        />
                                        <span aria-hidden="true">
                                            {{
                                                row.quality.average_score ===
                                                null
                                                    ? t(
                                                          'attendance.quality.unrated_short',
                                                      )
                                                    : `${row.quality.average_score}/100`
                                            }}
                                        </span>
                                        <span class="sr-only">{{
                                            qualityText(row)
                                        }}</span>
                                    </div>
                                </td>
                                <td :data-label="t('attendance.table.shift')">
                                    <div
                                        v-if="row.shifts.length > 0"
                                        class="flex flex-col gap-1"
                                    >
                                        <span
                                            v-for="shift in row.shifts"
                                            :key="shift.id"
                                            class="text-sm font-medium text-on-surface"
                                        >
                                            {{ shift.start_time }}–{{
                                                shift.end_time
                                            }}
                                        </span>
                                    </div>
                                    <span
                                        v-else
                                        class="text-sm text-on-surface-variant"
                                        >{{
                                            t('attendance.table.no_shift')
                                        }}</span
                                    >
                                </td>
                                <td :data-label="t('attendance.table.work')">
                                    <div
                                        v-if="row.sessions.length > 0"
                                        class="space-y-1"
                                    >
                                        <p
                                            v-for="session in row.sessions"
                                            :key="session.id"
                                            class="flex items-center gap-1.5 text-sm font-medium text-on-surface"
                                        >
                                            <Clock3
                                                :size="14"
                                                class="text-primary"
                                            />
                                            {{
                                                timeOnly(session.started_at)
                                            }}–{{ timeOnly(session.ended_at) }}
                                        </p>
                                        <p
                                            class="text-xs text-on-surface-variant"
                                        >
                                            {{ t('attendance.table.worked') }}:
                                            <strong class="text-on-surface">{{
                                                conciseDuration(
                                                    workedSeconds(row),
                                                )
                                            }}</strong>
                                        </p>
                                    </div>
                                    <span
                                        v-else
                                        class="text-sm text-on-surface-variant"
                                        >{{
                                            t('attendance.table.not_arrived')
                                        }}</span
                                    >
                                </td>
                                <td :data-label="t('attendance.breaks')">
                                    <div
                                        v-if="allBreaks(row).length > 0"
                                        class="space-y-1.5"
                                    >
                                        <p
                                            v-for="(pause, index) in allBreaks(
                                                row,
                                            )"
                                            :key="`${pause.started_at}-${index}`"
                                            class="flex items-center gap-1.5 text-xs font-medium text-amber-800"
                                        >
                                            <Coffee :size="14" />
                                            {{ timeOnly(pause.started_at) }}–{{
                                                timeOnly(pause.ended_at)
                                            }}
                                            ·
                                            {{
                                                conciseDuration(
                                                    intervalSeconds(
                                                        pause.started_at,
                                                        pause.ended_at,
                                                    ),
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <span
                                        v-else
                                        class="text-sm text-on-surface-variant"
                                        >{{
                                            t('attendance.table.no_breaks')
                                        }}</span
                                    >
                                </td>
                                <td :data-label="t('attendance.table.status')">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="statusClass(row.status)"
                                    >
                                        {{
                                            t(`attendance.status.${row.status}`)
                                        }}
                                    </span>
                                    <Alert
                                        v-if="row.status === 'stale'"
                                        variant="warning"
                                        class="mt-2 max-w-xs"
                                    >
                                        {{ t('attendance.stale_help') }}
                                    </Alert>
                                </td>
                                <td
                                    :data-label="t('attendance.table.actions')"
                                    data-mobile-layout="stack"
                                >
                                    <div
                                        class="flex flex-wrap gap-2 md:justify-end"
                                    >
                                        <Button
                                            v-if="row.status === 'absent'"
                                            size="compact"
                                            :disabled="
                                                actionForm.processing &&
                                                pendingWorkerId ===
                                                    row.worker_id
                                            "
                                            @click="perform(row, 'arrival')"
                                        >
                                            <LogIn :size="14" />
                                            {{
                                                t('attendance.actions.arrival')
                                            }}
                                        </Button>
                                        <Button
                                            v-if="row.status === 'present'"
                                            size="compact"
                                            variant="warning"
                                            :disabled="
                                                actionForm.processing &&
                                                pendingWorkerId ===
                                                    row.worker_id
                                            "
                                            @click="perform(row, 'break_start')"
                                        >
                                            <Coffee :size="14" />
                                            {{
                                                t(
                                                    'attendance.actions.break_start',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            v-if="row.status === 'break'"
                                            size="compact"
                                            variant="success"
                                            :disabled="
                                                actionForm.processing &&
                                                pendingWorkerId ===
                                                    row.worker_id
                                            "
                                            @click="perform(row, 'break_end')"
                                        >
                                            <LogIn :size="14" />
                                            {{
                                                t(
                                                    'attendance.actions.break_end',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            v-if="
                                                row.status === 'present' ||
                                                row.status === 'break'
                                            "
                                            size="compact"
                                            variant="danger"
                                            :disabled="
                                                actionForm.processing &&
                                                pendingWorkerId ===
                                                    row.worker_id
                                            "
                                            @click="perform(row, 'departure')"
                                        >
                                            <LogOut :size="14" />
                                            {{
                                                t(
                                                    'attendance.actions.departure',
                                                )
                                            }}
                                        </Button>
                                        <Link
                                            v-if="
                                                row.status === 'stale' &&
                                                is_admin
                                            "
                                            :href="route('attendance.report')"
                                            class="inline-flex h-8 items-center rounded-xl border border-outline-glass bg-white px-2.5 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                                        >
                                            {{
                                                t(
                                                    'attendance.actions.correct_record',
                                                )
                                            }}
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </section>
            </template>
        </div>

        <Modal
            :open="offScheduleOpen"
            :title="t('attendance.off_schedule.title')"
            @close="offScheduleOpen = false"
        >
            <p class="mb-4 text-sm text-on-surface-variant">
                {{ t('attendance.off_schedule.description') }}
            </p>
            <div class="space-y-2">
                <Label for="off-schedule-worker">{{
                    t('attendance.worker')
                }}</Label>
                <Select
                    id="off-schedule-worker"
                    v-model="offScheduleWorkerId"
                    autofocus
                    :placeholder="t('attendance.select_worker')"
                    :options="offScheduleOptions"
                />
            </div>
            <template #footer>
                <Button variant="secondary" @click="offScheduleOpen = false">{{
                    t('common.cancel')
                }}</Button>
                <Button
                    :disabled="!offScheduleWorkerId || actionForm.processing"
                    @click="performOffScheduleArrival"
                >
                    <LogIn :size="15" />
                    {{ t('attendance.actions.arrival') }}
                </Button>
            </template>
        </Modal>
    </AppLayout>
</template>
