<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    CircleCheck,
    CircleHelp,
    CircleOff,
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
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Label from '@/components/ui/Label.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
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
        attendance_rating_enabled: boolean;
        average_score: number | null;
        evaluated_shifts: number | null;
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
const timerWorkerId = ref(
    String(
        props.attendance_rows.find((row) => row.status !== 'absent')
            ?.worker_id ??
            props.attendance_rows[0]?.worker_id ??
            props.off_schedule_workers[0]?.id ??
            '',
    ),
);
const pendingWorkerId = ref<number | null>(null);
const actionForm = useForm({
    worker_id: '',
    action: '',
    confirm_without_shift: false,
});

const timerWorkerOptions = [
    ...props.attendance_rows.map((row) => ({
        value: String(row.worker_id),
        label: row.worker_name,
    })),
    ...props.off_schedule_workers.map((worker) => ({
        value: String(worker.id),
        label: worker.name,
    })),
].sort((left, right) => left.label.localeCompare(right.label, locale.value));
const timerWorkerModel = computed({
    get: () => timerWorkerId.value,
    set: (value: string | number | null) => {
        if (value !== null && String(value) !== '')
            timerWorkerId.value = String(value);
    },
});
const timerRow = computed(() =>
    props.attendance_rows.find(
        (row) => row.worker_id === Number(timerWorkerId.value),
    ),
);
const timerSession = computed(() =>
    timerRow.value?.sessions.find((session) => session.ended_at === null),
);
const timerOpenBreak = computed(() =>
    timerSession.value?.breaks.find((pause) => pause.ended_at === null),
);
const timerSeconds = computed(() => {
    const row = timerRow.value;
    if (!row) return 0;
    if (row.status === 'break' && timerOpenBreak.value)
        return intervalSeconds(timerOpenBreak.value.started_at, null);
    return workedSeconds(row);
});
const timerStatus = computed<AttendanceStatus>(
    () => timerRow.value?.status ?? 'absent',
);
const timerHasShift = computed(
    () => timerRow.value?.has_current_shift === true,
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

function liveDuration(seconds: number): string {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
}

function allBreaks(row: AttendanceRow): BreakRow[] {
    return row.sessions.flatMap((session) => session.breaks);
}

function qualityText(row: AttendanceRow): string {
    if (!row.quality.attendance_rating_enabled)
        return t('attendance.quality.disabled');
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

function statusVariant(
    status: AttendanceStatus,
): 'success' | 'warning' | 'danger' | 'neutral' {
    if (status === 'present') return 'success';
    if (status === 'break') return 'warning';
    if (status === 'stale') return 'danger';
    return 'neutral';
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
        onSuccess: () => {
            timerWorkerId.value = String(workerId);
        },
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

async function performTimerAction(action: string): Promise<void> {
    const workerId = Number(timerWorkerId.value);
    if (!workerId) return;
    if (timerRow.value) {
        await perform(timerRow.value, action);
        return;
    }
    postAction(workerId, action, action === 'arrival');
}

let refreshTimer: ReturnType<typeof setInterval> | null = null;
let clockTimer: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
    clockTimer = setInterval(() => {
        nowMs.value = Date.now();
    }, 1000);
    refreshTimer = setInterval(() => {
        if (!actionForm.processing)
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
            <PageHeader
                :title="t('attendance.title')"
                :subtitle="t('attendance.subtitle')"
            >
                <template #context>
                    <StoreContextIndicator />
                </template>
                <template #actions>
                    <Link
                        v-if="is_admin && store && !store.is_warehouse"
                        :href="route('attendance.report')"
                    >
                        <Button variant="secondary">
                            {{ t('attendance.report.title') }}
                            <ArrowRight :size="15" />
                        </Button>
                    </Link>
                </template>
            </PageHeader>

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

                <Card padded data-testid="attendance-timer-panel">
                    <div
                        class="grid gap-6 lg:grid-cols-[minmax(0,0.85fr)_minmax(20rem,1.15fr)]"
                    >
                        <div class="flex flex-col justify-center gap-4">
                            <div class="space-y-2">
                                <Label for="attendance-timer-worker">{{
                                    t('attendance.worker')
                                }}</Label>
                                <Select
                                    id="attendance-timer-worker"
                                    v-model="timerWorkerModel"
                                    :placeholder="t('attendance.select_worker')"
                                    :options="timerWorkerOptions"
                                />
                            </div>
                            <Button
                                v-if="timerStatus === 'absent'"
                                class="w-full sm:w-fit"
                                :disabled="
                                    !timerWorkerId || actionForm.processing
                                "
                                @click="performTimerAction('arrival')"
                            >
                                <LogIn :size="15" />
                                {{
                                    timerHasShift
                                        ? t('attendance.actions.arrival')
                                        : t(
                                              'attendance.actions.off_schedule_arrival',
                                          )
                                }}
                            </Button>
                            <div
                                v-else-if="
                                    timerStatus === 'present' ||
                                    timerStatus === 'break'
                                "
                                class="flex flex-col gap-2 sm:flex-row"
                            >
                                <Button
                                    v-if="timerStatus === 'present'"
                                    variant="warning"
                                    class="w-full sm:w-fit"
                                    :disabled="actionForm.processing"
                                    @click="performTimerAction('break_start')"
                                >
                                    <Coffee :size="15" />
                                    {{ t('attendance.actions.break_start') }}
                                </Button>
                                <Button
                                    v-else
                                    variant="success"
                                    class="w-full sm:w-fit"
                                    :disabled="actionForm.processing"
                                    @click="performTimerAction('break_end')"
                                >
                                    <LogIn :size="15" />
                                    {{ t('attendance.actions.break_end') }}
                                </Button>
                                <Button
                                    variant="danger"
                                    class="w-full sm:w-fit"
                                    :disabled="actionForm.processing"
                                    @click="performTimerAction('departure')"
                                >
                                    <LogOut :size="15" />
                                    {{ t('attendance.actions.departure') }}
                                </Button>
                            </div>
                            <Alert
                                v-else-if="timerStatus === 'stale'"
                                variant="warning"
                            >
                                {{ t('attendance.stale_help') }}
                            </Alert>
                        </div>

                        <div
                            class="flex min-h-48 flex-col items-center justify-center rounded-2xl border px-6 py-8 text-center"
                            :class="
                                timerRow?.status === 'present'
                                    ? 'border-emerald-200 bg-emerald-50'
                                    : timerRow?.status === 'break' ||
                                        timerRow?.status === 'stale'
                                      ? 'border-warning-amber/25 bg-warning-amber/5'
                                      : 'border-outline-glass bg-surface-container-low'
                            "
                        >
                            <Clock3
                                :size="22"
                                class="mb-3"
                                :class="
                                    timerRow?.status === 'present'
                                        ? 'text-emerald-700'
                                        : timerRow?.status === 'break' ||
                                            timerRow?.status === 'stale'
                                          ? 'text-warning-amber'
                                          : 'text-on-surface-variant'
                                "
                            />
                            <p
                                class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant"
                            >
                                {{
                                    !timerWorkerId
                                        ? t('attendance.timer.select')
                                        : timerStatus === 'present'
                                          ? t('attendance.timer.working')
                                          : timerStatus === 'break'
                                            ? t('attendance.timer.break')
                                            : timerStatus === 'stale'
                                              ? t('attendance.timer.stale')
                                              : t('attendance.timer.absent')
                                }}
                            </p>
                            <p
                                v-if="
                                    timerRow?.status === 'present' ||
                                    timerRow?.status === 'break'
                                "
                                class="mt-2 font-mono text-4xl font-bold tracking-tight text-on-surface tabular-nums sm:text-5xl"
                            >
                                {{ liveDuration(timerSeconds) }}
                            </p>
                            <p
                                v-else
                                class="mt-2 text-lg font-semibold text-on-surface"
                            >
                                {{
                                    timerWorkerId
                                        ? t(`attendance.status.${timerStatus}`)
                                        : t('attendance.select_worker')
                                }}
                            </p>
                            <p
                                v-if="timerSession"
                                class="mt-3 text-xs text-on-surface-variant"
                            >
                                {{
                                    t('attendance.arrived_at', {
                                        time: timeOnly(timerSession.started_at),
                                    })
                                }}
                            </p>
                        </div>
                    </div>
                </Card>

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
                                            row.quality
                                                .attendance_rating_enabled
                                                ? t(
                                                      'attendance.quality.tooltip',
                                                      {
                                                          count: row.quality
                                                              .evaluated_shifts,
                                                      },
                                                  )
                                                : t(
                                                      'attendance.quality.disabled',
                                                  )
                                        "
                                    >
                                        <CircleOff
                                            v-if="
                                                !row.quality
                                                    .attendance_rating_enabled
                                            "
                                            :size="18"
                                            aria-hidden="true"
                                        />
                                        <Smile
                                            v-else-if="
                                                row.quality.band === 'good'
                                            "
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
                                        <span
                                            v-if="
                                                row.quality
                                                    .attendance_rating_enabled
                                            "
                                            aria-hidden="true"
                                        >
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
                                    <Badge :variant="statusVariant(row.status)">
                                        {{
                                            t(`attendance.status.${row.status}`)
                                        }}
                                    </Badge>
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
    </AppLayout>
</template>
