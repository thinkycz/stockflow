<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Coffee, Plus, Printer } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import Select from '@/components/ui/Select.vue';
import { useRoute } from '@/composables/useRoute';
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
type SummaryRow = {
    actual_seconds: number;
    planned_seconds: number;
    wage: number;
};

const props = defineProps<{
    store: { id: number; name: string } | null;
    workers: Worker[];
    filters: { month: string; worker_id: number | null } | null;
    report: { month: string; rows: SessionRow[]; summary: SummaryRow[] } | null;
}>();

const { t, locale } = useI18n();
const route = useRoute();
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
function voidSession(id: number): void {
    const reason = window.prompt(t('attendance.correction.reason_prompt'));
    if (reason) router.post(route('attendance.sessions.void', id), { reason });
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
        <Head :title="t('attendance.report.title')" />
        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('attendance.report.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('attendance.report.help') }}
                    </p>
                </div>
                <Link :href="route('attendance.index')">
                    <Button variant="secondary">
                        <ArrowLeft :size="15" />
                        {{ t('attendance.report.back') }}
                    </Button>
                </Link>
            </header>

            <Card v-if="!store || !filters || !report" padded>
                <p class="text-sm text-on-surface-variant">
                    {{ t('attendance.retail_required') }}
                </p>
            </Card>

            <template v-else>
                <Card padded>
                    <div class="flex flex-col gap-3 xl:flex-row xl:items-end">
                        <div class="flex flex-col gap-1 xl:w-48">
                            <Label for="report-month">{{
                                t('attendance.report.month')
                            }}</Label>
                            <Input
                                id="report-month"
                                v-model="reportMonth"
                                type="month"
                            />
                        </div>
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

                <Card padded>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1160px] text-left text-xs">
                            <thead>
                                <tr class="border-b border-outline-glass">
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
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in report.rows"
                                    :key="row.id"
                                    class="border-b border-outline-glass/60 last:border-0"
                                    :class="row.voided ? 'opacity-50' : ''"
                                >
                                    <td class="py-3">{{ row.date }}</td>
                                    <td class="font-medium">
                                        {{ row.worker_name }}
                                    </td>
                                    <td>
                                        <span
                                            :class="
                                                row.voided ? 'line-through' : ''
                                            "
                                        >
                                            {{ timeOnly(row.started_at) }} –
                                            {{ timeOnly(row.ended_at) }}
                                        </span>
                                    </td>
                                    <td class="min-w-56 py-2 pr-4">
                                        <span
                                            v-if="row.breaks.length === 0"
                                            class="text-on-surface-variant"
                                            >{{
                                                t('attendance.table.no_breaks')
                                            }}</span
                                        >
                                        <div v-else class="space-y-1.5">
                                            <div
                                                v-for="(
                                                    pause, index
                                                ) in row.breaks"
                                                :key="`${pause.started_at}-${index}`"
                                                class="flex items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-amber-950"
                                            >
                                                <span
                                                    class="flex items-center gap-1.5 font-medium"
                                                >
                                                    <Coffee :size="13" />
                                                    {{
                                                        timeOnly(
                                                            pause.started_at,
                                                        )
                                                    }}–{{
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
                                                    t(
                                                        'attendance.report.breaks_total',
                                                    )
                                                }}:
                                                {{
                                                    duration(row.break_seconds)
                                                }}
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
                                        <button
                                            class="font-semibold text-primary"
                                            @click="openEdit(row)"
                                        >
                                            {{ t('common.edit') }}
                                        </button>
                                        <button
                                            v-if="!row.voided"
                                            class="font-semibold text-error-red"
                                            @click="voidSession(row.id)"
                                        >
                                            {{
                                                t('attendance.correction.void')
                                            }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="report.rows.length === 0">
                                    <td
                                        colspan="9"
                                        class="py-10 text-center text-sm text-on-surface-variant"
                                    >
                                        {{ t('attendance.report.empty') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </template>
        </div>

        <Modal
            :open="correctionOpen"
            :title="
                editingSessionId === null
                    ? t('attendance.correction.create')
                    : t('attendance.correction.edit')
            "
            class="max-w-2xl"
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
                        <button
                            type="button"
                            class="text-xs text-primary"
                            @click="addBreak"
                        >
                            {{ t('attendance.correction.add_break') }}
                        </button>
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
                        <button
                            type="button"
                            class="text-error-red"
                            @click="removeBreak(index)"
                        >
                            ×
                        </button>
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
