<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { nextTick, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import DataTable from '@/components/ui/DataTable.vue';
import { formatMoney } from '@/lib/format';
import type { PayrollReport } from '@/types/payroll';

defineProps<{
    active_store: { id: number; name: string };
    payroll_report: PayrollReport;
    simple: boolean;
}>();

const { t, locale } = useI18n();

function duration(seconds: number | null): string {
    if (seconds === null) return '—';
    const minutes = Math.round(Math.abs(seconds) / 60);
    const sign = seconds < 0 ? '−' : '';
    return (
        sign +
        String(Math.floor(minutes / 60)) +
        ':' +
        String(minutes % 60).padStart(2, '0')
    );
}

function date(value: string): string {
    return new Intl.DateTimeFormat(locale.value).format(
        new Date(value + 'T12:00:00'),
    );
}

function hours(value: number): string {
    return `${new Intl.NumberFormat(locale.value, { maximumFractionDigits: 2 }).format(value)} h`;
}

onMounted(async () => {
    await nextTick();
    window.print();
});
</script>

<template>
    <Head :title="t('payroll.print_title')" />
    <main class="mx-auto max-w-5xl bg-white p-8 text-black">
        <article
            v-for="payslip in payroll_report.payslips"
            :key="payslip.worker_id"
            class="payslip"
            :class="{ 'payslip--simple': simple }"
        >
            <header class="mb-6 border-b pb-4">
                <h1 class="text-2xl font-bold">
                    {{ t('payroll.print_title') }}
                </h1>
                <p class="mt-1">{{ payslip.worker_name }}</p>
                <p>
                    {{ active_store.name }} · {{ payroll_report.year }}-{{
                        String(payroll_report.month).padStart(2, '0')
                    }}
                </p>
            </header>

            <DataTable v-if="!simple" density="compact">
                <thead>
                    <tr>
                        <th>{{ t('payroll.date') }}</th>
                        <th>{{ t('payroll.interval') }}</th>
                        <th>{{ t('payroll.planned') }}</th>
                        <th>{{ t('payroll.actual') }}</th>
                        <th>{{ t('payroll.rate') }}</th>
                        <th>{{ t('payroll.amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="shift in payslip.shifts" :key="shift.id">
                        <td>{{ date(shift.date) }}</td>
                        <td>{{ shift.start_time }}–{{ shift.end_time }}</td>
                        <td>{{ duration(shift.planned_minutes * 60) }}</td>
                        <td>
                            {{
                                shift.attendance_incomplete
                                    ? '—'
                                    : duration(shift.actual_seconds)
                            }}
                        </td>
                        <td>{{ formatMoney(shift.hourly_rate) }}</td>
                        <td>{{ formatMoney(shift.amount) }}</td>
                    </tr>
                </tbody>
            </DataTable>

            <section v-if="payslip.adjustments.length" class="mt-6">
                <h2 class="mb-2 text-lg font-bold">
                    {{ t('payroll.adjustments') }}
                </h2>
                <DataTable density="compact" variant="nested">
                    <tbody>
                        <tr
                            v-for="adjustment in payslip.adjustments"
                            :key="adjustment.id"
                        >
                            <td :data-label="t('payroll.adjustment_type')">
                                {{
                                    t(
                                        'payroll.adjustment_types.' +
                                            adjustment.type,
                                    )
                                }}
                            </td>
                            <td :data-label="t('payroll.reason')">
                                {{ adjustment.reason }}
                            </td>
                            <td
                                :data-label="t('payroll.amount')"
                                class="text-right"
                            >
                                {{ adjustment.type === 'deduction' ? '−' : '+'
                                }}{{ formatMoney(adjustment.amount) }}
                            </td>
                        </tr>
                    </tbody>
                </DataTable>
            </section>

            <section class="mt-6 ml-auto w-full max-w-sm space-y-2 text-sm">
                <div v-if="!simple" class="flex justify-between">
                    <span>{{ t('payroll.planned') }}</span>
                    <strong>{{
                        duration(payslip.planned_minutes * 60)
                    }}</strong>
                </div>
                <div v-if="!simple" class="flex justify-between">
                    <span>{{ t('payroll.actual') }}</span>
                    <strong>{{ duration(payslip.actual_seconds) }}</strong>
                </div>
                <div v-if="simple" class="flex justify-between gap-4">
                    <span>{{ t('payroll.payable_hours') }}</span>
                    <strong
                        data-testid="payroll-wage-calculation"
                        class="text-right"
                    >
                        {{ hours(payslip.payable_hours) }} ×
                        {{ formatMoney(payslip.payable_hourly_rate) }} / h
                    </strong>
                </div>
                <div class="flex justify-between">
                    <span>{{ t('payroll.base_amount') }}</span>
                    <strong>{{ formatMoney(payslip.base_amount) }}</strong>
                </div>
                <div class="flex justify-between">
                    <span>{{ t('payroll.adjustment_types.tip') }}</span>
                    <strong>+{{ formatMoney(payslip.tip_amount) }}</strong>
                </div>
                <div class="flex justify-between">
                    <span>{{ t('payroll.adjustment_types.deduction') }}</span>
                    <strong
                        >−{{ formatMoney(payslip.deduction_amount) }}</strong
                    >
                </div>
                <div
                    class="flex justify-between border-t pt-3 text-lg font-bold"
                >
                    <span>{{ t('payroll.final_amount') }}</span>
                    <span>{{ formatMoney(payslip.final_amount) }}</span>
                </div>
            </section>

            <p
                v-if="
                    !simple &&
                    (payslip.incomplete_count > 0 ||
                        payslip.unmatched_count > 0)
                "
                class="mt-6 text-xs"
            >
                {{
                    t('payroll.attendance_warning', {
                        incomplete: payslip.incomplete_count,
                        unmatched: payslip.unmatched_count,
                    })
                }}
            </p>
        </article>
    </main>
</template>

<style scoped>
.payslip {
    break-after: page;
    min-height: 90vh;
}

.payslip:last-child {
    break-after: auto;
}

@media print {
    main {
        max-width: none;
        padding: 0;
    }

    .payslip--simple {
        margin-bottom: 6mm;
        min-height: 0;
        break-after: auto;
        break-inside: avoid;
        border-bottom: 1px dashed #000;
        padding-bottom: 6mm;
    }

    .payslip--simple:last-child {
        margin-bottom: 0;
        border-bottom: 0;
        padding-bottom: 0;
    }

    @page {
        margin: 14mm;
    }
}
</style>
