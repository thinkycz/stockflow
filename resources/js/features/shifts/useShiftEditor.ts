import { useShiftRequestApproval } from './useShiftRequestApproval';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch, type Ref, type ComputedRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import { withActionErrorToast } from '@/lib/action-errors';
import type {
    Worker,
    Shift,
    ShiftPreset,
    CalendarDay,
    CalendarShift,
    CalendarRequest,
} from './scheduling-types';

export function useShiftEditor(
    props: {
        store: { id: number } | null;
        workers: Worker[];
        shift_presets?: ShiftPreset[];
    },
    month: Ref<number>,
    year: Ref<number>,
    calendarDays: ComputedRef<CalendarDay[]>,
) {
    const route = useRoute();
    const { t } = useI18n();
    const dialog = useDialog();
    const {
        editingRequestId,
        approvingRequestId,
        requestApprovalForm,
        requestOverlapError,
        approveRequest,
        submitRequestApproval,
    } = useShiftRequestApproval(month, year);
    // --- Modal / shift form ---

    const modalOpen = ref<boolean>(false);
    const modalDate = ref<string>('');
    const editingShiftId = ref<number | null>(null);
    const deletingRequestId = ref<number | null>(null);
    let requestContextVersion = 0;
    watch(
        () => [
            props.store?.id,
            month.value,
            year.value,
            modalDate.value,
            modalOpen.value,
        ],
        () => requestContextVersion++,
        { flush: 'sync' },
    );
    type ShiftForm = {
        worker_id: string;
        date: string;
        start_time: string;
        end_time: string;
        allow_overlap: boolean;
    };

    const form = useForm<ShiftForm>({
        worker_id: '',
        date: '',
        start_time: '',
        end_time: '',
        allow_overlap: false,
    });

    const overlapError = computed<string | undefined>(
        () => (form.errors as Record<string, string>).overlap,
    );
    const timeOptions: string[] = [];
    for (let h = 0; h < 24; h++) {
        for (const m of [0, 15, 30, 45]) {
            timeOptions.push(
                `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`,
            );
        }
    }

    const workerOptions = computed(() =>
        props.workers
            .filter((worker) => !worker.archived)
            .map((worker) => ({
                value: String(worker.id),
                label: `${worker.first_name} ${worker.last_name}`,
            })),
    );
    const presetOptions = computed(() =>
        (props.shift_presets ?? []).map((preset) => ({
            value: String(preset.id),
            label: `${preset.name} (${preset.start_time}–${preset.end_time})`,
        })),
    );
    const timeSelectOptions = timeOptions.map((time) => ({
        value: time,
        label: time,
    }));

    const modalShifts = computed<CalendarShift[]>(() => {
        const day = calendarDays.value.find((d) => d.date === modalDate.value);
        return day?.shifts ?? [];
    });

    const modalRequests = computed<CalendarRequest[]>(() => {
        const day = calendarDays.value.find((d) => d.date === modalDate.value);
        return day?.requests ?? [];
    });

    const editingRequest = computed<CalendarRequest | undefined>(() =>
        modalRequests.value.find(
            (shiftRequest) => shiftRequest.id === editingRequestId.value,
        ),
    );

    function openDayModal(date: string): void {
        modalDate.value = date;
        editingShiftId.value = null;
        editingRequestId.value = null;
        form.reset();
        requestApprovalForm.reset();
        form.date = date;
        form.start_time = '09:00';
        form.end_time = '16:00';
        form.allow_overlap = false;
        form.worker_id = workerOptions.value[0]?.value ?? '';
        modalOpen.value = true;
    }

    function editShift(shift: Shift): void {
        editingShiftId.value = shift.id;
        editingRequestId.value = null;
        requestApprovalForm.reset();
        form.worker_id = String(shift.worker_id);
        form.date = shift.date;
        form.start_time = shift.start_time;
        form.end_time = shift.end_time;
        form.allow_overlap = false;
    }

    function editRequest(shiftRequest: CalendarRequest): void {
        editingShiftId.value = null;
        editingRequestId.value = shiftRequest.id;
        form.reset();
        requestApprovalForm.clearErrors();
        requestApprovalForm.start_time = shiftRequest.start_time;
        requestApprovalForm.end_time = shiftRequest.end_time;
        requestApprovalForm.allow_overlap = false;
    }

    function cancelEdit(): void {
        editingShiftId.value = null;
        editingRequestId.value = null;
        requestApprovalForm.reset();
        form.worker_id = workerOptions.value[0]?.value ?? '';
        form.date = modalDate.value;
        form.start_time = '09:00';
        form.end_time = '16:00';
        form.allow_overlap = false;
    }

    function closeModal(): void {
        modalOpen.value = false;
        editingShiftId.value = null;
        editingRequestId.value = null;
        form.reset();
        requestApprovalForm.reset();
    }

    function submitShift(): void {
        const confirmOverlap = async (
            errors: Record<string, string>,
        ): Promise<void> => {
            if (
                errors.overlap !== undefined &&
                !form.allow_overlap &&
                (await dialog.confirm({
                    title: t('common.confirm'),
                    message: t('shifts.overlap_confirm'),
                    confirmLabel: t('common.continue'),
                    variant: 'warning',
                }))
            ) {
                form.allow_overlap = true;
                submitShift();
            }
        };

        if (editingShiftId.value !== null) {
            form.put(
                route('shifts.update', {
                    shift: editingShiftId.value,
                    month: month.value,
                    year: year.value,
                }),
                {
                    preserveState: true,
                    onError: (errors) => void confirmOverlap(errors),
                    onSuccess: () => {
                        editingShiftId.value = null;
                        form.reset();
                        form.date = modalDate.value;
                        form.start_time = '09:00';
                        form.end_time = '16:00';
                        form.allow_overlap = false;
                        form.worker_id = workerOptions.value[0]?.value ?? '';
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
                    onError: (errors) => void confirmOverlap(errors),
                    onSuccess: () => {
                        form.reset();
                        form.date = modalDate.value;
                        form.start_time = nextShiftStart;
                        form.end_time = '21:00';
                        form.allow_overlap = false;
                        form.worker_id = workerOptions.value[0]?.value ?? '';
                    },
                },
            );
        }
    }

    async function deleteShift(id: number): Promise<void> {
        if (
            !(await dialog.confirm({
                title: t('common.delete'),
                message: t('shifts.confirm_delete'),
                confirmLabel: t('common.delete'),
                variant: 'danger',
            }))
        ) {
            return;
        }
        router.delete(
            route('shifts.destroy', {
                shift: id,
                month: month.value,
                year: year.value,
            }),
            withActionErrorToast({ preserveState: true }),
        );
    }

    async function deleteRequest(shiftRequest: CalendarRequest): Promise<void> {
        if (
            deletingRequestId.value !== null ||
            approvingRequestId.value !== null ||
            requestApprovalForm.processing ||
            props.store === null
        )
            return;
        const version = requestContextVersion;
        const storeId = props.store.id;
        deletingRequestId.value = shiftRequest.id;
        const confirmed = await dialog.confirm({
            title: t('shifts.requests.delete'),
            message: t('shifts.requests.confirm_delete', {
                worker: shiftRequest.worker_name,
                date: shiftRequest.date,
                start: shiftRequest.start_time,
                end: shiftRequest.end_time,
            }),
            confirmLabel: t('common.delete'),
            variant: 'danger',
        });
        if (
            !confirmed ||
            version !== requestContextVersion ||
            !modalRequests.value.some(
                (request) => request.id === shiftRequest.id,
            )
        ) {
            deletingRequestId.value = null;
            return;
        }
        router.delete(
            route('shift-requests.destroy', {
                shiftRequest: shiftRequest.id,
                store_id: storeId,
                month: month.value,
                year: year.value,
            }),
            withActionErrorToast({
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    if (editingRequestId.value === shiftRequest.id)
                        cancelEdit();
                },
                onFinish: () => (deletingRequestId.value = null),
            }),
        );
    }

    return {
        modalOpen,
        modalDate,
        editingShiftId,
        editingRequestId,
        approvingRequestId,
        deletingRequestId,
        form,
        requestApprovalForm,
        overlapError,
        requestOverlapError,
        workerOptions,
        presetOptions,
        timeSelectOptions,
        modalShifts,
        modalRequests,
        editingRequest,
        openDayModal,
        editShift,
        editRequest,
        cancelEdit,
        closeModal,
        approveRequest,
        submitRequestApproval,
        submitShift,
        deleteShift,
        deleteRequest,
    };
}
