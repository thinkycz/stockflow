<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { Coffee, Plus, Printer } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import BackLink from '@/components/ui/BackLink.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import FilterField from '@/components/ui/FilterField.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import MonthPicker from '@/components/ui/MonthPicker.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Select from '@/components/ui/Select.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import { useRoute } from '@/composables/useRoute';
import { useDialog } from '@/composables/useDialog';
import { withActionErrorToast } from '@/lib/action-errors';
import { formatMoney } from '@/lib/format';

type Worker = { id: number; first_name: string; last_name: string };
type BreakRow = {
    started_at: string;
    ended_at: string | null;
    seconds: number;
};
type SessionRow = {
    id: number;
    worker_id: number;
    worker_name: string;
    worker_color: string;
    date: string;
    started_at: string;
    ended_at: string | null;
    breaks: BreakRow[];
    break_seconds: number;
    actual_seconds: number | null;
    planned_seconds: number | null;
    difference_seconds: number | null;
    wage: number | null;
    voided: boolean;
};
type DeviationStatus = 'pending' | 'approved' | 'rejected';
type DeviationRow = {
    shift_id: number;
    primary_session_id: number;
    status: DeviationStatus;
    planned_start_time: string;
    planned_end_time: string;
    actual_started_at: string;
    actual_ended_at: string;
    arrival_offset_seconds: number;
    departure_offset_seconds: number;
    can_approve: boolean;
    reason: string | null;
    reviewed_at: string | null;
};
type SummaryRow = {
    actual_seconds: number;
    planned_seconds: number;
    wage: number;
};

const props = defineProps<{
    store: { id: number; name: string } | null;
    workers: Worker[];
    filters: { month: string; worker_id: number | null } | null;
    report: {
        month: string;
        rows: SessionRow[];
        summary: SummaryRow[];
        deviations: DeviationRow[];
    } | null;
}>();

const { t, locale } = useI18n();
const route = useRoute();
const dialog = useDialog();
const reportMonth = ref(props.filters?.month ?? '');
const reportWorkerId = ref(
    props.filters?.worker_id === null || props.filters?.worker_id === undefined
        ? ''
        : String(props.filters.worker_id),
);
const correctionOpen = ref(false);
const editingSessionId = ref<number | null>(null);
const correctionForm = useForm({
    worker_id: '',
    started_at: '',
    ended_at: '',
    breaks: [] as Array<{ started_at: string; ended_at: string }>,
    reason: '',
});
const reviewOpen = ref(false);
const activeDeviation = ref<DeviationRow | null>(null);
const reviewForm = useForm({
    decision: 'approved' as 'approved' | 'rejected',
    reason: '',
    start_time: '',
    end_time: '',
    allow_overlap: false,
    expected_started_at: '',
    expected_ended_at: '',
    expected_start_time: '',
    expected_end_time: '',
});
const reviewErrors = computed(
    () => reviewForm.errors as Record<string, string | undefined>,
);
const timeSelectOptions = Array.from({ length: 96 }, (_, index) => {
    const minutes = index * 15;
    const time = `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
    return { value: time, label: time };
});
const deviationsBySession = computed(
    () =>
        new Map(
            (props.report?.deviations ?? []).map((deviation) => [
                deviation.primary_session_id,
                deviation,
            ]),
        ),
);
const reportTotals = computed(() =>
    (props.report?.summary ?? []).reduce(
        (total, row) => ({
            actual: total.actual + row.actual_seconds,
            planned: total.planned + row.planned_seconds,
            wage: total.wage + row.wage,
        }),
        { actual: 0, planned: 0, wage: 0 },
    ),
);

function timeOnly(value: string | null): string {
    if (value === null) return t('attendance.now');
    return new Intl.DateTimeFormat(locale.value, {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}
function localInput(value: string | null): string {
    if (value === null) return '';
    const date = new Date(value);
    const offset = date.getTimezoneOffset() * 60_000;
    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}
function duration(seconds: number | null | undefined): string {
    if (seconds === null || seconds === undefined) return '—';
    const sign = seconds < 0 ? '−' : '';
    const minutes = Math.round(Math.abs(seconds) / 60);
    return `${sign}${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}`;
}
function roundedQuarter(value: string): string {
    const parts = new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        timeZone: 'Europe/Prague',
    }).formatToParts(new Date(value));
    const hour = Number(parts.find((part) => part.type === 'hour')?.value ?? 0);
    const minute = Number(
        parts.find((part) => part.type === 'minute')?.value ?? 0,
    );
    const slot = Math.min(95, Math.floor((hour * 60 + minute + 7) / 15));
    return timeSelectOptions[slot]?.value ?? '00:00';
}
function openReview(deviation: DeviationRow): void {
    activeDeviation.value = deviation;
    reviewForm.reset();
    reviewForm.clearErrors();
    reviewForm.decision = 'approved';
    reviewForm.reason = '';
    reviewForm.start_time = roundedQuarter(deviation.actual_started_at);
    reviewForm.end_time = roundedQuarter(deviation.actual_ended_at);
    reviewForm.allow_overlap = false;
    reviewForm.expected_started_at = deviation.actual_started_at;
    reviewForm.expected_ended_at = deviation.actual_ended_at;
    reviewForm.expected_start_time = deviation.planned_start_time;
    reviewForm.expected_end_time = deviation.planned_end_time;
    reviewOpen.value = true;
}
function closeReview(): void {
    reviewOpen.value = false;
    activeDeviation.value = null;
    reviewForm.reset();
}
function submitReview(decision: 'approved' | 'rejected'): void {
    if (activeDeviation.value === null) return;
    reviewForm.decision = decision;
    const confirmOverlap = async (
        errors: Record<string, string>,
    ): Promise<void> => {
        if (
            errors.overlap !== undefined &&
            !reviewForm.allow_overlap &&
            (await dialog.confirm({
                title: t('common.confirm'),
                message: t('shifts.overlap_confirm'),
                confirmLabel: t('common.continue'),
                variant: 'warning',
            }))
        ) {
            reviewForm.allow_overlap = true;
            submitReview(decision);
        }
    };
    reviewForm.post(
        route('attendance.deviation-reviews.store', {
            shift: activeDeviation.value.shift_id,
        }),
        {
            preserveScroll: true,
            onError: (errors) => void confirmOverlap(errors),
            onSuccess: closeReview,
        },
    );
}
function applyFilters(): void {
    router.get(
        route('attendance.report'),
        { month: reportMonth.value, worker_id: reportWorkerId.value || null },
        { preserveState: true, preserveScroll: true },
    );
}
function openCreate(): void {
    editingSessionId.value = null;
    correctionForm.reset();
    correctionForm.breaks = [];
    correctionOpen.value = true;
}
function openEdit(row: SessionRow): void {
    editingSessionId.value = row.id;
    correctionForm.worker_id = String(row.worker_id);
    correctionForm.started_at = localInput(row.started_at);
    correctionForm.ended_at = localInput(row.ended_at);
    correctionForm.breaks = row.breaks.map((item) => ({
        started_at: localInput(item.started_at),
        ended_at: localInput(item.ended_at),
    }));
    correctionForm.reason = '';
    correctionOpen.value = true;
}
function saveCorrection(): void {
    if (editingSessionId.value === null) {
        correctionForm.post(route('attendance.corrections.store'), {
            onSuccess: () => (correctionOpen.value = false),
        });
        return;
    }
    correctionForm.put(
        route('attendance.sessions.update', editingSessionId.value),
        { onSuccess: () => (correctionOpen.value = false) },
    );
}
async function voidSession(id: number): Promise<void> {
    const reason = await dialog.prompt({
        title: t('attendance.correction.void'),
        message: t('attendance.correction.reason_prompt'),
        label: t('common.reason'),
        confirmLabel: t('attendance.correction.void'),
        variant: 'danger',
        required: true,
    });
    if (reason?.trim()) {
        router.post(
            route('attendance.sessions.void', id),
            { reason: reason.trim() },
            withActionErrorToast(),
        );
    }
}
function addBreak(): void {
    correctionForm.breaks.push({ started_at: '', ended_at: '' });
}
function removeBreak(index: number): void {
    correctionForm.breaks.splice(index, 1);
}
</script>

<template>
    <AppLayout :title="t('attendance.report.title')">
        <div class="flex flex-col gap-6">
            <PageHeader
                :title="t('attendance.report.title')"
                :subtitle="t('attendance.report.help')"
            >
                <template #before>
                    <BackLink :href="route('attendance.index')">
                        {{ t('attendance.report.back') }}
                    </BackLink>
                </template>
                <template #context>
                    <StoreContextIndicator />
                </template>
            </PageHeader>

            <Card v-if="!store || !filters || !report" padded>
                <p class="text-sm text-on-surface-variant">
                    {{ t('attendance.retail_required') }}
                </p>
            </Card>

            <template v-else>
                <Card padded>
                    <div class="flex flex-col gap-3 xl:flex-row xl:items-end">
                        <FilterField
                            for="report-month"
                            :label="t('attendance.report.month')"
                            class="xl:w-48"
                        >
                            <MonthPicker
                                id="report-month"
                                v-model="reportMonth"
                                class="w-full"
                            />
                        </FilterField>
                        <div class="flex flex-col gap-1 xl:w-64">
                            <Label for="report-worker">{{
                                t('attendance.worker')
                            }}</Label>
                            <Select
                                id="report-worker"
                                v-model="reportWorkerId"
                                :options="[
                                    {
                                        value: '',
                                        label: t(
                                            'attendance.report.all_workers',
                                        ),
                                    },
                                    ...workers.map((worker) => ({
                                        value: String(worker.id),
                                        label: `${worker.first_name} ${worker.last_name}`,
                                    })),
                                ]"
                            />
                        </div>
                        <Button @click="applyFilters">{{
                            t('common.apply')
                        }}</Button>
                        <div class="flex-1"></div>
                        <Link
                            :href="
                                route('attendance.print', {
                                    month: reportMonth,
                                    worker_id: reportWorkerId || null,
                                })
                            "
                            target="_blank"
                        >
                            <Button variant="secondary">
                                <Printer :size="15" />
                                {{ t('attendance.report.print') }}
                            </Button>
                        </Link>
                        <Button variant="secondary" @click="openCreate">
                            <Plus :size="15" />
                            {{ t('attendance.correction.create') }}
                        </Button>
                    </div>
                    <div
                        class="mt-5 grid gap-3 border-t border-outline-glass pt-5 sm:grid-cols-3"
                    >
                        <div class="rounded-xl bg-surface-container-low p-4">
                            <p
                                class="text-xs font-semibold text-on-surface-variant"
                            >
                                {{ t('attendance.report.total_actual') }}
                            </p>
                            <p class="mt-1 text-xl font-bold">
                                {{ duration(reportTotals.actual) }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-surface-container-low p-4">
                            <p
                                class="text-xs font-semibold text-on-surface-variant"
                            >
                                {{ t('attendance.report.total_planned') }}
                            </p>
                            <p class="mt-1 text-xl font-bold">
                                {{ duration(reportTotals.planned) }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-surface-container-low p-4">
                            <p
                                class="text-xs font-semibold text-on-surface-variant"
                            >
                                {{ t('attendance.report.total_wage') }}
                            </p>
                            <p class="mt-1 text-xl font-bold">
                                {{ formatMoney(reportTotals.wage) }}
                            </p>
                        </div>
                    </div>
                </Card>

                <DataTable
                    density="compact"
                    table-class="text-xs md:min-w-[1160px]"
                >
                    <thead>
                        <tr>
                            <th class="py-3">
                                {{ t('attendance.report.date') }}
                            </th>
                            <th>{{ t('attendance.worker') }}</th>
                            <th>
                                {{ t('attendance.report.interval') }}
                            </th>
                            <th>{{ t('attendance.breaks') }}</th>
                            <th>
                                {{ t('attendance.report.planned') }}
                            </th>
                            <th>{{ t('attendance.report.actual') }}</th>
                            <th>
                                {{ t('attendance.report.difference') }}
                            </th>
                            <th>{{ t('attendance.report.wage') }}</th>
                            <th>
                                <span class="sr-only">{{
                                    t('common.actions')
                                }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in report.rows"
                            :key="row.id"
                            :class="row.voided ? 'opacity-50' : ''"
                        >
                            <td class="py-3">{{ row.date }}</td>
                            <td class="font-medium">
                                <span class="flex items-center gap-2">
                                    <span
                                        class="size-2.5 shrink-0 rounded-full border border-black/10"
                                        :style="{
                                            backgroundColor: row.worker_color,
                                        }"
                                        aria-hidden="true"
                                    />
                                    <span>{{ row.worker_name }}</span>
                                </span>
                            </td>
                            <td>
                                <span :class="row.voided ? 'line-through' : ''">
                                    {{ timeOnly(row.started_at) }} –
                                    {{ timeOnly(row.ended_at) }}
                                </span>
                            </td>
                            <td class="min-w-56 py-2 pr-4">
                                <span
                                    v-if="row.breaks.length === 0"
                                    class="text-on-surface-variant"
                                    >{{ t('attendance.table.no_breaks') }}</span
                                >
                                <div v-else class="space-y-1.5">
                                    <div
                                        v-for="(pause, index) in row.breaks"
                                        :key="`${pause.started_at}-${index}`"
                                        class="flex items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-amber-950"
                                    >
                                        <span
                                            class="flex items-center gap-1.5 font-medium"
                                        >
                                            <Coffee :size="13" />
                                            {{ timeOnly(pause.started_at) }}–{{
                                                timeOnly(pause.ended_at)
                                            }}
                                        </span>
                                        <span class="text-amber-800">{{
                                            duration(pause.seconds)
                                        }}</span>
                                    </div>
                                    <p
                                        class="text-right font-semibold text-on-surface-variant"
                                    >
                                        {{
                                            t('attendance.report.breaks_total')
                                        }}:
                                        {{ duration(row.break_seconds) }}
                                    </p>
                                </div>
                            </td>
                            <td>{{ duration(row.planned_seconds) }}</td>
                            <td>{{ duration(row.actual_seconds) }}</td>
                            <td>
                                {{ duration(row.difference_seconds) }}
                            </td>
                            <td>
                                {{
                                    row.wage === null
                                        ? '—'
                                        : formatMoney(row.wage)
                                }}
                            </td>
                            <td class="space-x-2">
                                <Button
                                    v-if="deviationsBySession.get(row.id)"
                                    variant="ghost"
                                    size="compact"
                                    @click="
                                        openReview(
                                            deviationsBySession.get(row.id)!,
                                        )
                                    "
                                >
                                    <Badge
                                        :variant="
                                            deviationsBySession.get(row.id)
                                                ?.status === 'approved'
                                                ? 'success'
                                                : deviationsBySession.get(
                                                        row.id,
                                                    )?.status === 'rejected'
                                                  ? 'danger'
                                                  : 'warning'
                                        "
                                    >
                                        {{
                                            t(
                                                `attendance.deviation.status.${deviationsBySession.get(row.id)?.status}`,
                                            )
                                        }}
                                    </Badge>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="compact"
                                    @click="openEdit(row)"
                                >
                                    {{ t('common.edit') }}
                                </Button>
                                <Button
                                    v-if="!row.voided"
                                    variant="ghost"
                                    size="compact"
                                    class="text-error-red hover:text-error-red"
                                    @click="voidSession(row.id)"
                                >
                                    {{ t('attendance.correction.void') }}
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="report.rows.length === 0">
                            <td
                                colspan="9"
                                data-label=""
                                data-mobile-layout="stack"
                                class="py-10 text-center text-sm text-on-surface-variant"
                            >
                                {{ t('attendance.report.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </DataTable>
            </template>
        </div>

        <Modal
            :open="reviewOpen"
            :title="t('attendance.deviation.title')"
            size="md"
            @close="closeReview"
        >
            <form
                v-if="activeDeviation"
                class="space-y-5"
                @submit.prevent="submitReview('approved')"
            >
                <p class="text-sm text-on-surface-variant">
                    {{ t('attendance.deviation.help') }}
                </p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-surface-container-low p-4">
                        <p
                            class="text-xs font-semibold text-on-surface-variant"
                        >
                            {{ t('attendance.deviation.planned') }}
                        </p>
                        <p class="mt-1 font-mono font-semibold">
                            {{ activeDeviation.planned_start_time }}–{{
                                activeDeviation.planned_end_time
                            }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-surface-container-low p-4">
                        <p
                            class="text-xs font-semibold text-on-surface-variant"
                        >
                            {{ t('attendance.deviation.actual') }}
                        </p>
                        <p class="mt-1 font-mono font-semibold">
                            {{ timeOnly(activeDeviation.actual_started_at) }}–{{
                                timeOnly(activeDeviation.actual_ended_at)
                            }}
                        </p>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <Label for="deviation-start">{{
                            t('attendance.deviation.start_time')
                        }}</Label>
                        <Select
                            id="deviation-start"
                            v-model="reviewForm.start_time"
                            :options="timeSelectOptions"
                        />
                        <FieldError :message="reviewForm.errors.start_time" />
                    </div>
                    <div>
                        <Label for="deviation-end">{{
                            t('attendance.deviation.end_time')
                        }}</Label>
                        <Select
                            id="deviation-end"
                            v-model="reviewForm.end_time"
                            :options="timeSelectOptions"
                        />
                        <FieldError :message="reviewForm.errors.end_time" />
                    </div>
                </div>
                <div>
                    <Label for="deviation-reason" required>{{
                        t('attendance.deviation.reason')
                    }}</Label>
                    <Input
                        id="deviation-reason"
                        v-model="reviewForm.reason"
                        required
                    />
                    <FieldError :message="reviewForm.errors.reason" />
                </div>
                <FieldError :message="reviewErrors.shift" />
                <FieldError :message="reviewErrors.payroll" />
                <FieldError :message="reviewErrors.overlap" />
                <p
                    v-if="!activeDeviation.can_approve"
                    class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-medium text-amber-900"
                >
                    {{ t('attendance.deviation.closed_payroll') }}
                </p>
                <div
                    class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <Button variant="secondary" @click="closeReview">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        variant="danger"
                        :disabled="reviewForm.processing"
                        @click="submitReview('rejected')"
                    >
                        {{ t('attendance.deviation.reject') }}
                    </Button>
                    <Button
                        type="submit"
                        variant="success"
                        :disabled="
                            reviewForm.processing ||
                            !activeDeviation.can_approve
                        "
                    >
                        {{ t('attendance.deviation.approve') }}
                    </Button>
                </div>
            </form>
        </Modal>

        <Modal
            :open="correctionOpen"
            :title="
                editingSessionId === null
                    ? t('attendance.correction.create')
                    : t('attendance.correction.edit')
            "
            size="lg"
            @close="correctionOpen = false"
        >
            <form class="space-y-4" @submit.prevent="saveCorrection">
                <div>
                    <Label>{{ t('attendance.worker') }}</Label>
                    <Select
                        v-model="correctionForm.worker_id"
                        :options="
                            workers.map((worker) => ({
                                value: String(worker.id),
                                label: `${worker.first_name} ${worker.last_name}`,
                            }))
                        "
                        required
                    />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <Label>{{ t('attendance.actions.arrival') }}</Label>
                        <Input
                            v-model="correctionForm.started_at"
                            type="datetime-local"
                            required
                        />
                    </div>
                    <div>
                        <Label>{{ t('attendance.actions.departure') }}</Label>
                        <Input
                            v-model="correctionForm.ended_at"
                            type="datetime-local"
                            required
                        />
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <Label>{{ t('attendance.breaks') }}</Label>
                        <Button
                            type="button"
                            variant="ghost"
                            size="compact"
                            @click="addBreak"
                        >
                            {{ t('attendance.correction.add_break') }}
                        </Button>
                    </div>
                    <div
                        v-for="(pause, index) in correctionForm.breaks"
                        :key="index"
                        class="grid grid-cols-[1fr_1fr_auto] gap-2"
                    >
                        <Input
                            v-model="pause.started_at"
                            type="datetime-local"
                            required
                        />
                        <Input
                            v-model="pause.ended_at"
                            type="datetime-local"
                            required
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="text-error-red hover:text-error-red"
                            :aria-label="t('common.delete')"
                            @click="removeBreak(index)"
                        >
                            ×
                        </Button>
                    </div>
                </div>
                <div>
                    <Label>{{ t('attendance.correction.reason') }}</Label>
                    <Input v-model="correctionForm.reason" required />
                </div>
                <div class="flex justify-end gap-2">
                    <Button
                        variant="secondary"
                        @click="correctionOpen = false"
                        >{{ t('common.cancel') }}</Button
                    >
                    <Button
                        type="submit"
                        :disabled="correctionForm.processing"
                        >{{ t('common.save') }}</Button
                    >
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
