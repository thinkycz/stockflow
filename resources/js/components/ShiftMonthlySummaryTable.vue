<script setup lang="ts">
import { CircleOff } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { formatMoney } from '@/lib/format';
import type { MonthlyShiftSummary } from '@/types/shifts';

const props = withDefaults(
    defineProps<{
        rows: MonthlyShiftSummary[];
        showSalary?: boolean;
    }>(),
    { showSalary: false },
);

const { t, locale } = useI18n();

const totals = computed(() =>
    props.rows.reduce(
        (result, row) => ({
            hours: result.hours + row.hours,
            goodShifts: result.goodShifts + (row.good_shifts ?? 0),
            evaluatedShifts:
                result.evaluatedShifts + (row.evaluated_shifts ?? 0),
            lateArrivals: result.lateArrivals + (row.late_arrivals ?? 0),
            earlyDepartures:
                result.earlyDepartures + (row.early_departures ?? 0),
            breakIssues: result.breakIssues + (row.break_issues ?? 0),
            absences: result.absences + (row.absences ?? 0),
            salary: result.salary + (row.salary ?? 0),
        }),
        {
            hours: 0,
            goodShifts: 0,
            evaluatedShifts: 0,
            lateArrivals: 0,
            earlyDepartures: 0,
            breakIssues: 0,
            absences: 0,
            salary: 0,
        },
    ),
);

function formatHours(value: number): string {
    return new Intl.NumberFormat(locale.value, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value);
}
</script>

<template>
    <EmptyState
        v-if="rows.length === 0"
        :title="t('shifts.rating.summary.empty')"
        density="compact"
    />
    <DataTable v-else>
        <thead>
            <tr>
                <th>{{ t('shifts.rating.summary.worker') }}</th>
                <th class="text-right">
                    {{ t('shifts.rating.summary.hours') }}
                </th>
                <th class="text-right">
                    {{ t('shifts.rating.summary.score') }}
                </th>
                <th class="text-right">
                    {{ t('shifts.rating.summary.good') }}
                </th>
                <th class="text-right">
                    {{ t('shifts.rating.summary.late') }}
                </th>
                <th class="text-right">
                    {{ t('shifts.rating.summary.early') }}
                </th>
                <th class="text-right">
                    {{ t('shifts.rating.summary.breaks') }}
                </th>
                <th class="text-right">
                    {{ t('shifts.rating.summary.absences') }}
                </th>
                <th v-if="showSalary" class="text-right">
                    {{ t('shifts.rating.summary.salary') }}
                </th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="row in rows" :key="row.worker_id">
                <td>
                    <div
                        class="flex items-center gap-2 font-semibold text-on-surface"
                    >
                        <span
                            class="size-2.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: row.color }"
                            aria-hidden="true"
                        />
                        {{ row.worker_name }}
                    </div>
                </td>
                <td class="text-right font-semibold text-on-surface">
                    {{ formatHours(row.hours) }} h
                </td>
                <td class="text-right">
                    <span
                        v-if="!row.attendance_rating_enabled"
                        class="inline-flex text-on-surface-variant"
                        :title="t('shifts.rating.state.disabled')"
                        :aria-label="t('shifts.rating.state.disabled')"
                    >
                        <CircleOff :size="18" aria-hidden="true" />
                    </span>
                    <span
                        v-else-if="row.average_score !== null"
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold"
                        :class="
                            row.average_score >= 90
                                ? 'bg-emerald-100 text-emerald-800'
                                : row.average_score >= 70
                                  ? 'bg-amber-100 text-amber-800'
                                  : 'bg-rose-100 text-rose-800'
                        "
                    >
                        {{ row.average_score }}/100
                    </span>
                    <span v-else class="text-xs text-on-surface-variant">
                        {{ t('shifts.rating.summary.no_data') }}
                    </span>
                </td>
                <td class="text-right font-semibold">
                    <template v-if="row.attendance_rating_enabled">
                        {{ row.good_shifts ?? 0 }}/{{
                            row.evaluated_shifts ?? 0
                        }}
                    </template>
                    <span v-else aria-hidden="true">—</span>
                    <span v-if="!row.attendance_rating_enabled" class="sr-only">
                        {{ t('shifts.rating.state.disabled') }}
                    </span>
                </td>
                <td class="text-right">
                    {{
                        row.attendance_rating_enabled ? row.late_arrivals : '—'
                    }}
                </td>
                <td class="text-right">
                    {{
                        row.attendance_rating_enabled
                            ? row.early_departures
                            : '—'
                    }}
                </td>
                <td class="text-right">
                    {{ row.attendance_rating_enabled ? row.break_issues : '—' }}
                </td>
                <td class="text-right">
                    {{ row.attendance_rating_enabled ? row.absences : '—' }}
                </td>
                <td
                    v-if="showSalary"
                    class="text-right font-semibold text-on-surface"
                >
                    {{ formatMoney(row.salary ?? 0) }}
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <th class="text-left">Σ</th>
                <th class="text-right">{{ formatHours(totals.hours) }} h</th>
                <th
                    class="text-right"
                    :aria-label="
                        t('shifts.rating.summary.aggregate_score_note')
                    "
                >
                    —
                </th>
                <th class="text-right">
                    {{ totals.goodShifts }}/{{ totals.evaluatedShifts }}
                </th>
                <th class="text-right">
                    {{ totals.lateArrivals }}
                </th>
                <th class="text-right">
                    {{ totals.earlyDepartures }}
                </th>
                <th class="text-right">
                    {{ totals.breakIssues }}
                </th>
                <th class="text-right">
                    {{ totals.absences }}
                </th>
                <th v-if="showSalary" class="text-right text-on-surface">
                    {{ formatMoney(totals.salary) }}
                </th>
            </tr>
        </tfoot>
    </DataTable>
</template>
