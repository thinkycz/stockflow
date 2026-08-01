<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    CircleCheck,
    Clock3,
    Coffee,
    LogIn,
    LogOut,
    TriangleAlert,
    UserRound,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import Select from '@/components/ui/Select.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import { useRoute } from '@/composables/useRoute';

type Worker = { id: number; first_name: string; last_name: string };
type WorkerState = {
    worker_id: number;
    status: 'absent' | 'present' | 'break' | 'stale';
    has_current_shift: boolean;
};
type BreakRow = {
    started_at: string;
    ended_at: string | null;
    seconds?: number;
};
type SessionRow = {
    id: number;
    worker_id: number;
    worker_name: string;
    date?: string;
    started_at: string;
    ended_at: string | null;
    breaks: BreakRow[];
};

const props = defineProps<{
    store: { id: number; name: string; is_warehouse: boolean } | null;
    workers: Worker[];
    worker_states: WorkerState[];
    recommended_worker_id: number | null;
    store_state: 'occupied' | 'empty' | 'unclear';
    today_sessions: SessionRow[];
    is_admin: boolean;
}>();

const { t, locale } = useI18n();
const route = useRoute();
const selectedWorkerId = ref(
    props.recommended_worker_id === null
        ? ''
        : String(props.recommended_worker_id),
);
const warningOpen = ref(false);
const actionForm = useForm({
    worker_id: '',
    action: '',
    confirm_without_shift: false,
});

const selectedState = computed(() =>
    props.worker_states.find(
        (row) => row.worker_id === Number(selectedWorkerId.value),
    ),
);
const selectedWorker = computed(() =>
    props.workers.find(
        (worker) => worker.id === Number(selectedWorkerId.value),
    ),
);
const selectedSession = computed(() =>
    props.today_sessions.find(
        (row) =>
            row.worker_id === Number(selectedWorkerId.value) &&
            row.ended_at === null,
    ),
);
const selectedOpenBreak = computed(() =>
    selectedSession.value?.breaks.find((pause) => pause.ended_at === null),
);
const nowMs = ref(Date.now());
const liveSeconds = computed(() => {
    const session = selectedSession.value;
    if (!session) return 0;
    if (selectedState.value?.status === 'break' && selectedOpenBreak.value) {
        return Math.max(
            0,
            Math.floor(
                (nowMs.value - Date.parse(selectedOpenBreak.value.started_at)) /
                    1000,
            ),
        );
    }
    const elapsed = Math.max(
        0,
        Math.floor((nowMs.value - Date.parse(session.started_at)) / 1000),
    );
    const breakSeconds = session.breaks.reduce((total, pause) => {
        const end = pause.ended_at ? Date.parse(pause.ended_at) : nowMs.value;
        return (
            total +
            Math.max(0, Math.floor((end - Date.parse(pause.started_at)) / 1000))
        );
    }, 0);
    return Math.max(0, elapsed - breakSeconds);
});

function timeOnly(value: string | null): string {
    if (value === null) return t('attendance.now');
    return new Intl.DateTimeFormat(locale.value, {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}
function liveDuration(seconds: number): string {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
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
function workedSeconds(row: SessionRow): number {
    const elapsed = intervalSeconds(row.started_at, row.ended_at);
    const pauses = row.breaks.reduce(
        (total, pause) =>
            total + intervalSeconds(pause.started_at, pause.ended_at),
        0,
    );
    return Math.max(0, elapsed - pauses);
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
function sessionStatus(row: SessionRow): 'working' | 'break' | 'completed' {
    if (row.ended_at !== null) return 'completed';
    return props.worker_states.find(
        (state) => state.worker_id === row.worker_id,
    )?.status === 'break'
        ? 'break'
        : 'working';
}
function perform(action: string, confirmed = false): void {
    if (!selectedWorkerId.value) return;
    if (
        action === 'arrival' &&
        selectedState.value?.has_current_shift !== true &&
        !confirmed
    ) {
        warningOpen.value = true;
        return;
    }
    actionForm.worker_id = selectedWorkerId.value;
    actionForm.action = action;
    actionForm.confirm_without_shift = confirmed;
    actionForm.post(route('attendance.actions.store'), {
        preserveScroll: true,
    });
    warningOpen.value = false;
}
let refreshTimer: ReturnType<typeof setInterval> | null = null;
let clockTimer: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
    clockTimer = setInterval(() => {
        nowMs.value = Date.now();
    }, 1000);
    refreshTimer = setInterval(() => {
        if (!warningOpen.value)
            router.reload({
                only: [
                    'worker_states',
                    'recommended_worker_id',
                    'store_state',
                    'today_sessions',
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
                <Link
                    v-if="is_admin && store && !store.is_warehouse"
                    :href="route('attendance.report')"
                >
                    <Button variant="secondary">
                        {{ t('attendance.report.title') }}
                        <ArrowRight :size="15" />
                    </Button>
                </Link>
            </header>

            <Card v-if="!store || store.is_warehouse" padded
                ><p class="text-sm text-on-surface-variant">
                    {{ t('attendance.retail_required') }}
                </p></Card
            >
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

                <Card padded>
                    <div
                        class="grid gap-6 lg:grid-cols-[minmax(0,0.85fr)_minmax(20rem,1.15fr)]"
                    >
                        <div class="space-y-5">
                            <div class="space-y-2">
                                <Label for="attendance-worker">{{
                                    t('attendance.worker')
                                }}</Label>
                                <Select
                                    id="attendance-worker"
                                    v-model="selectedWorkerId"
                                    :placeholder="t('attendance.select_worker')"
                                    :options="
                                        workers.map((worker) => ({
                                            value: String(worker.id),
                                            label: `${worker.first_name} ${worker.last_name}`,
                                        }))
                                    "
                                />
                            </div>

                            <div
                                v-if="selectedWorker"
                                class="flex items-center gap-3"
                            >
                                <span
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary"
                                >
                                    <UserRound :size="18" />
                                </span>
                                <div>
                                    <p class="font-semibold text-on-surface">
                                        {{ selectedWorker.first_name }}
                                        {{ selectedWorker.last_name }}
                                    </p>
                                    <p class="text-xs text-on-surface-variant">
                                        {{
                                            t(
                                                `attendance.status.${selectedState?.status ?? 'absent'}`,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 sm:flex-row">
                                <Button
                                    v-if="
                                        selectedState?.status === 'absent' ||
                                        !selectedState
                                    "
                                    class="h-12 w-full text-sm sm:flex-1"
                                    :disabled="
                                        actionForm.processing ||
                                        !selectedWorkerId
                                    "
                                    @click="perform('arrival')"
                                >
                                    <LogIn :size="17" />
                                    {{ t('attendance.actions.arrival') }}
                                </Button>
                                <Button
                                    v-if="selectedState?.status === 'present'"
                                    variant="warning"
                                    class="h-12 w-full text-sm sm:flex-1"
                                    :disabled="actionForm.processing"
                                    @click="perform('break_start')"
                                >
                                    <Coffee :size="17" />
                                    {{ t('attendance.actions.break_start') }}
                                </Button>
                                <Button
                                    v-if="selectedState?.status === 'break'"
                                    variant="success"
                                    class="h-12 w-full text-sm sm:flex-1"
                                    :disabled="actionForm.processing"
                                    @click="perform('break_end')"
                                >
                                    <LogIn :size="17" />
                                    {{ t('attendance.actions.break_end') }}
                                </Button>
                                <Button
                                    v-if="
                                        selectedState?.status === 'present' ||
                                        selectedState?.status === 'break'
                                    "
                                    variant="danger"
                                    class="h-12 w-full text-sm sm:flex-1"
                                    :disabled="actionForm.processing"
                                    @click="perform('departure')"
                                >
                                    <LogOut :size="17" />
                                    {{ t('attendance.actions.departure') }}
                                </Button>
                            </div>
                        </div>

                        <div
                            class="flex min-h-52 flex-col items-center justify-center rounded-2xl border px-6 py-8 text-center"
                            :class="
                                selectedState?.status === 'present'
                                    ? 'border-emerald-200 bg-emerald-50'
                                    : selectedState?.status === 'break'
                                      ? 'border-warning-amber/25 bg-warning-amber/5'
                                      : selectedState?.status === 'stale'
                                        ? 'border-warning-amber/25 bg-warning-amber/5'
                                        : 'border-outline-glass bg-surface-container-low'
                            "
                        >
                            <Clock3
                                :size="22"
                                class="mb-3"
                                :class="
                                    selectedState?.status === 'present'
                                        ? 'text-emerald-700'
                                        : selectedState?.status === 'break' ||
                                            selectedState?.status === 'stale'
                                          ? 'text-warning-amber'
                                          : 'text-on-surface-variant'
                                "
                            />
                            <p
                                class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant"
                            >
                                {{
                                    !selectedWorkerId
                                        ? t('attendance.timer.select')
                                        : selectedState?.status === 'present'
                                          ? t('attendance.timer.working')
                                          : selectedState?.status === 'break'
                                            ? t('attendance.timer.break')
                                            : selectedState?.status === 'stale'
                                              ? t('attendance.timer.stale')
                                              : t('attendance.timer.absent')
                                }}
                            </p>
                            <p
                                v-if="
                                    selectedState?.status === 'present' ||
                                    selectedState?.status === 'break'
                                "
                                class="mt-2 font-mono text-4xl font-bold tracking-tight text-on-surface tabular-nums sm:text-5xl"
                            >
                                {{ liveDuration(liveSeconds) }}
                            </p>
                            <p
                                v-else
                                class="mt-2 text-lg font-semibold text-on-surface"
                            >
                                {{
                                    selectedWorkerId
                                        ? t(
                                              `attendance.status.${selectedState?.status ?? 'absent'}`,
                                          )
                                        : t('attendance.select_worker')
                                }}
                            </p>
                            <p
                                v-if="selectedSession"
                                class="mt-3 text-xs text-on-surface-variant"
                            >
                                {{
                                    t('attendance.arrived_at', {
                                        time: timeOnly(
                                            selectedSession.started_at,
                                        ),
                                    })
                                }}
                            </p>
                        </div>
                    </div>
                    <p
                        v-if="selectedState?.status === 'stale'"
                        class="mt-4 text-sm font-medium text-warning-amber"
                    >
                        {{ t('attendance.stale_help') }}
                    </p>
                </Card>

                <Card padded>
                    <div class="mb-5">
                        <h2 class="font-heading text-lg font-bold">
                            {{ t('attendance.today') }}
                        </h2>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            {{ t('attendance.today_help') }}
                        </p>
                    </div>
                    <p
                        v-if="today_sessions.length === 0"
                        class="text-sm text-on-surface-variant"
                    >
                        {{ t('attendance.empty_today') }}
                    </p>
                    <div
                        v-else
                        class="overflow-hidden rounded-xl border border-outline-glass"
                    >
                        <div
                            class="hidden grid-cols-[minmax(10rem,1fr)_minmax(11rem,1fr)_minmax(14rem,1.4fr)_auto] gap-4 bg-surface-container-low px-4 py-3 text-xs font-semibold uppercase tracking-wide text-on-surface-variant md:grid"
                        >
                            <span>{{ t('attendance.worker') }}</span>
                            <span>{{ t('attendance.table.work') }}</span>
                            <span>{{ t('attendance.breaks') }}</span>
                            <span>{{ t('attendance.table.status') }}</span>
                        </div>
                        <div
                            v-for="row in today_sessions"
                            :key="row.id"
                            class="grid gap-4 border-t border-outline-glass p-4 first:border-t-0 md:grid-cols-[minmax(10rem,1fr)_minmax(11rem,1fr)_minmax(14rem,1.4fr)_auto] md:items-start"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
                                    :class="
                                        sessionStatus(row) === 'working'
                                            ? 'bg-emerald-500'
                                            : sessionStatus(row) === 'break'
                                              ? 'bg-amber-400'
                                              : 'bg-neutral'
                                    "
                                ></span>
                                <div>
                                    <span
                                        class="mb-1 block text-xs font-semibold text-on-surface-variant md:hidden"
                                        >{{ t('attendance.worker') }}</span
                                    >
                                    <strong class="text-sm text-on-surface">{{
                                        row.worker_name
                                    }}</strong>
                                </div>
                            </div>
                            <div>
                                <span
                                    class="mb-1 block text-xs font-semibold text-on-surface-variant md:hidden"
                                    >{{ t('attendance.table.work') }}</span
                                >
                                <div
                                    class="flex items-center gap-2 text-sm font-semibold text-on-surface"
                                >
                                    <Clock3 :size="15" class="text-primary" />
                                    {{ timeOnly(row.started_at) }}–{{
                                        timeOnly(row.ended_at)
                                    }}
                                </div>
                                <p class="mt-1 text-xs text-on-surface-variant">
                                    {{ t('attendance.table.worked') }}:
                                    <strong
                                        class="font-semibold text-on-surface"
                                        >{{
                                            conciseDuration(workedSeconds(row))
                                        }}</strong
                                    >
                                </p>
                            </div>
                            <div>
                                <span
                                    class="mb-2 block text-xs font-semibold text-on-surface-variant md:hidden"
                                    >{{ t('attendance.breaks') }}</span
                                >
                                <p
                                    v-if="row.breaks.length === 0"
                                    class="text-sm text-on-surface-variant"
                                >
                                    {{ t('attendance.table.no_breaks') }}
                                </p>
                                <div v-else class="flex flex-col gap-2">
                                    <div
                                        v-for="(
                                            pause, pauseIndex
                                        ) in row.breaks"
                                        :key="`${pause.started_at}-${pauseIndex}`"
                                        class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs"
                                    >
                                        <span
                                            class="flex items-center gap-2 font-semibold text-amber-950"
                                        >
                                            <Coffee :size="14" />
                                            {{ timeOnly(pause.started_at) }}–{{
                                                timeOnly(pause.ended_at)
                                            }}
                                        </span>
                                        <span
                                            class="font-medium text-amber-800"
                                            >{{
                                                conciseDuration(
                                                    intervalSeconds(
                                                        pause.started_at,
                                                        pause.ended_at,
                                                    ),
                                                )
                                            }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                            <div>
                                <span
                                    class="mb-1 block text-xs font-semibold text-on-surface-variant md:hidden"
                                    >{{ t('attendance.table.status') }}</span
                                >
                                <span
                                    class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="
                                        sessionStatus(row) === 'working'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : sessionStatus(row) === 'break'
                                              ? 'bg-amber-100 text-amber-800'
                                              : 'bg-surface-container text-on-surface-variant'
                                    "
                                    >{{
                                        t(
                                            `attendance.status.${sessionStatus(row)}`,
                                        )
                                    }}</span
                                >
                            </div>
                        </div>
                    </div>
                </Card>
            </template>
        </div>

        <Modal
            :open="warningOpen"
            :title="t('attendance.no_shift.title')"
            @close="warningOpen = false"
            ><p class="text-sm text-on-surface-variant">
                {{ t('attendance.no_shift.description') }}
            </p>
            <template #footer
                ><Button variant="secondary" @click="warningOpen = false">{{
                    t('common.cancel')
                }}</Button
                ><Button @click="perform('arrival', true)">{{
                    t('attendance.no_shift.confirm')
                }}</Button></template
            ></Modal
        >
    </AppLayout>
</template>
