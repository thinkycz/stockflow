import { isAxiosError } from 'axios';
import { ref, watch, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import { showErrorToast, showSuccessToast } from '@/composables/useClientToast';
import type { Worker, Shift } from './scheduling-types';
import type { MonthlyShiftSummary } from './types';

export function useShiftQuickAdd(
    props: {
        store: { id: number } | null;
        workers: Worker[];
        is_admin: boolean;
    },
    localShifts: Ref<Shift[]>,
    localMonthlySummary: Ref<MonthlyShiftSummary[]>,
    openDayModal: (date: string) => void,
) {
    const route = useRoute();
    const { t } = useI18n();
    const dialog = useDialog();
    // --- Quick add ---

    type QuickAddSuccess = {
        status: 'created' | 'exists';
        shift: Shift;
        contribution?: {
            minutes: number;
            salary: number;
        };
    };

    type QuickAddConflict = {
        status: 'overlap';
        conflicts: Array<{
            id: number;
            start_time: string;
            end_time: string;
        }>;
    };

    const selectedWorkerId = ref<string>('');
    const selectedPresetId = ref<string>('');
    const quickAddActive = ref<boolean>(false);
    const pendingDates = ref<Set<string>>(new Set());

    watch(
        () => props.store?.id,
        () => {
            stopQuickAdd();
            selectedPresetId.value = '';
        },
    );

    function startQuickAdd(): void {
        if (selectedWorkerId.value === '' || selectedPresetId.value === '')
            return;
        quickAddActive.value = true;
    }

    function stopQuickAdd(): void {
        quickAddActive.value = false;
        pendingDates.value = new Set();
    }

    function handleDayClick(day: {
        date: string;
        isCurrentMonth: boolean;
    }): void {
        if (!day.isCurrentMonth) return;

        if (quickAddActive.value && props.is_admin) {
            void quickAddShift(day.date);
            return;
        }

        openDayModal(day.date);
    }

    async function quickAddShift(
        date: string,
        allowOverlap = false,
    ): Promise<void> {
        if (
            pendingDates.value.has(date) ||
            selectedWorkerId.value === '' ||
            selectedPresetId.value === ''
        ) {
            return;
        }

        pendingDates.value = new Set(pendingDates.value).add(date);
        let retryWithOverlap = false;

        try {
            const response = await window.axios.post<QuickAddSuccess>(
                route('shifts.quick-add'),
                {
                    worker_id: Number(selectedWorkerId.value),
                    shift_preset_id: Number(selectedPresetId.value),
                    date,
                    allow_overlap: allowOverlap,
                },
            );

            if (response.data.status === 'exists') {
                showSuccessToast(t('shifts.quick_add.exists'));
                return;
            }

            localShifts.value = [...localShifts.value, response.data.shift];
            const contribution = response.data.contribution;
            const summary = localMonthlySummary.value.find(
                (row) => row.worker_id === response.data.shift.worker_id,
            );

            if (summary !== undefined && contribution !== undefined) {
                localMonthlySummary.value = localMonthlySummary.value.map(
                    (row) =>
                        row.worker_id === summary.worker_id
                            ? {
                                  ...row,
                                  hours: row.hours + contribution.minutes / 60,
                                  salary:
                                      (row.salary ?? 0) + contribution.salary,
                              }
                            : row,
                );
            } else if (contribution !== undefined) {
                const worker = props.workers.find(
                    (row) => row.id === response.data.shift.worker_id,
                );

                if (worker !== undefined) {
                    localMonthlySummary.value = [
                        ...localMonthlySummary.value,
                        {
                            worker_id: worker.id,
                            worker_name: `${worker.first_name} ${worker.last_name}`,
                            color: worker.color,
                            hours: contribution.minutes / 60,
                            salary: contribution.salary,
                            attendance_rating_enabled:
                                worker.attendance_rating_enabled,
                            average_score: null,
                            evaluated_shifts: worker.attendance_rating_enabled
                                ? 0
                                : null,
                            good_shifts: worker.attendance_rating_enabled
                                ? 0
                                : null,
                            late_arrivals: worker.attendance_rating_enabled
                                ? 0
                                : null,
                            early_departures: worker.attendance_rating_enabled
                                ? 0
                                : null,
                            break_issues: worker.attendance_rating_enabled
                                ? 0
                                : null,
                            absences: worker.attendance_rating_enabled
                                ? 0
                                : null,
                        },
                    ];
                }
            }

            showSuccessToast(t('shifts.quick_add.created'));
        } catch (error: unknown) {
            if (
                isAxiosError<QuickAddConflict>(error) &&
                error.response?.status === 409
            ) {
                const conflicts = error.response.data.conflicts
                    .map(
                        (conflict) =>
                            `${conflict.start_time}–${conflict.end_time}`,
                    )
                    .join(', ');
                retryWithOverlap = await dialog.confirm({
                    title: t('shifts.quick_add.conflict'),
                    message: t('shifts.quick_add.overlap_confirm', {
                        conflicts,
                    }),
                    confirmLabel: t('common.continue'),
                    variant: 'warning',
                });
                if (!retryWithOverlap) {
                    showErrorToast(t('shifts.quick_add.conflict'));
                }
            } else {
                showErrorToast(t('shifts.quick_add.failed'));
            }
        } finally {
            const nextPending = new Set(pendingDates.value);
            nextPending.delete(date);
            pendingDates.value = nextPending;
        }

        if (retryWithOverlap) {
            await quickAddShift(date, true);
        }
    }

    return {
        selectedWorkerId,
        selectedPresetId,
        quickAddActive,
        pendingDates,
        startQuickAdd,
        stopQuickAdd,
        handleDayClick,
    };
}
