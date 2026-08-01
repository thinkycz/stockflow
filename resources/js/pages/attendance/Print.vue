<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { nextTick, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import DataTable from '@/components/ui/DataTable.vue';
import { formatMoney } from '@/lib/format';

type Row = {
    id: number;
    worker_id: number;
    worker_name: string;
    date: string;
    started_at: string;
    ended_at: string | null;
    breaks: Array<{
        started_at: string;
        ended_at: string | null;
        seconds: number;
    }>;
    break_seconds: number;
    actual_seconds: number | null;
    planned_seconds: number | null;
    difference_seconds: number | null;
    wage: number | null;
    voided: boolean;
};
type Summary = {
    worker_id: number;
    worker_name: string;
    actual_seconds: number;
    planned_seconds: number;
    difference_seconds: number;
    wage: number;
    incomplete_count: number;
};
const props = defineProps<{
    store: { id: number; name: string };
    report: { month: string; rows: Row[]; summary: Summary[] };
}>();
const { t, locale } = useI18n();
function duration(seconds: number | null): string {
    if (seconds === null) return '—';
    const minutes = Math.round(Math.abs(seconds) / 60);
    return `${seconds < 0 ? '−' : ''}${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}`;
}
function time(value: string | null): string {
    return value === null
        ? '—'
        : new Intl.DateTimeFormat(locale.value, { timeStyle: 'short' }).format(
              new Date(value),
          );
}
onMounted(async () => {
    await nextTick();
    window.print();
});
</script>
<template>
    <Head :title="t('attendance.report.print_title')" />
    <main class="mx-auto max-w-5xl bg-white p-8 text-black">
        <header class="mb-8 border-b pb-4">
            <h1 class="text-2xl font-bold">
                {{ t('attendance.report.print_title') }}
            </h1>
            <p>{{ store.name }} · {{ report.month }}</p>
        </header>
        <section
            v-for="summary in report.summary"
            :key="summary.worker_id"
            class="mb-8 break-inside-avoid"
        >
            <h2 class="mb-2 text-lg font-bold">{{ summary.worker_name }}</h2>
            <p class="mb-3 text-sm">
                {{ t('attendance.report.actual') }}:
                {{ duration(summary.actual_seconds) }} ·
                {{ t('attendance.report.planned') }}:
                {{ duration(summary.planned_seconds) }} ·
                {{ t('attendance.report.difference') }}:
                {{ duration(summary.difference_seconds) }} ·
                {{ t('attendance.report.wage') }}:
                {{ formatMoney(summary.wage) }}
            </p>
            <DataTable density="compact">
                <thead>
                    <tr>
                        <th>{{ t('attendance.report.date') }}</th>
                        <th>{{ t('attendance.report.interval') }}</th>
                        <th>{{ t('attendance.breaks') }}</th>
                        <th>{{ t('attendance.report.actual') }}</th>
                        <th>{{ t('attendance.report.difference') }}</th>
                        <th>{{ t('attendance.report.wage') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in props.report.rows.filter(
                            (item) => item.worker_id === summary.worker_id,
                        )"
                        :key="row.id"
                        :class="row.voided ? 'line-through opacity-50' : ''"
                    >
                        <td>{{ row.date }}</td>
                        <td>
                            {{ time(row.started_at) }} –
                            {{ time(row.ended_at) }}
                        </td>
                        <td>
                            <template v-if="row.breaks.length">
                                <div
                                    v-for="(pause, index) in row.breaks"
                                    :key="`${pause.started_at}-${index}`"
                                >
                                    {{ time(pause.started_at) }}–{{
                                        time(pause.ended_at)
                                    }}
                                    ({{ duration(pause.seconds) }})
                                </div>
                                <strong>
                                    {{ t('attendance.report.breaks_total') }}:
                                    {{ duration(row.break_seconds) }}
                                </strong>
                            </template>
                            <template v-else>—</template>
                        </td>
                        <td>{{ duration(row.actual_seconds) }}</td>
                        <td>{{ duration(row.difference_seconds) }}</td>
                        <td>
                            {{
                                row.wage === null ? '—' : formatMoney(row.wage)
                            }}
                        </td>
                    </tr>
                </tbody>
            </DataTable>
        </section>
    </main>
</template>
<style scoped>
@media print {
    main {
        max-width: none;
        padding: 0;
    }
    @page {
        margin: 14mm;
    }
}
</style>
