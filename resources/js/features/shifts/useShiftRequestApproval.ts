import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import type { CalendarRequest } from './scheduling-types';

export function useShiftRequestApproval(month: Ref<number>, year: Ref<number>) {
    const route = useRoute();
    const { t } = useI18n();
    const dialog = useDialog();
    const editingRequestId = ref<number | null>(null);
    const approvingRequestId = ref<number | null>(null);

    type RequestApprovalForm = {
        start_time: string;
        end_time: string;
        allow_overlap: boolean;
    };

    const requestApprovalForm = useForm<RequestApprovalForm>({
        start_time: '',
        end_time: '',
        allow_overlap: false,
    });

    const requestOverlapError = computed<string | undefined>(
        () => (requestApprovalForm.errors as Record<string, string>).overlap,
    );

    async function confirmRequestOverlap(
        errors: Record<string, string>,
        retry: () => void,
        allowOverlap: boolean,
    ): Promise<void> {
        if (
            errors.overlap !== undefined &&
            !allowOverlap &&
            (await dialog.confirm({
                title: t('common.confirm'),
                message: t('shifts.overlap_confirm'),
                confirmLabel: t('common.continue'),
                variant: 'warning',
            }))
        ) {
            retry();
        }
    }

    function approveRequest(
        shiftRequest: CalendarRequest,
        allowOverlap = false,
    ): void {
        approvingRequestId.value = shiftRequest.id;
        router.post(
            route('shift-requests.approve', {
                shiftRequest: shiftRequest.id,
                month: month.value,
                year: year.value,
            }),
            {
                start_time: shiftRequest.start_time,
                end_time: shiftRequest.end_time,
                allow_overlap: allowOverlap,
            },
            {
                preserveState: true,
                onError: (errors) =>
                    void confirmRequestOverlap(
                        errors,
                        () => approveRequest(shiftRequest, true),
                        allowOverlap,
                    ),
                onFinish: () => {
                    if (approvingRequestId.value === shiftRequest.id) {
                        approvingRequestId.value = null;
                    }
                },
            },
        );
    }

    function submitRequestApproval(): void {
        if (editingRequestId.value === null) return;

        requestApprovalForm.post(
            route('shift-requests.approve', {
                shiftRequest: editingRequestId.value,
                month: month.value,
                year: year.value,
            }),
            {
                preserveState: true,
                onError: (errors) =>
                    void confirmRequestOverlap(
                        errors,
                        () => {
                            requestApprovalForm.allow_overlap = true;
                            submitRequestApproval();
                        },
                        requestApprovalForm.allow_overlap,
                    ),
                onSuccess: () => {
                    editingRequestId.value = null;
                    requestApprovalForm.reset();
                },
            },
        );
    }

    return {
        editingRequestId,
        approvingRequestId,
        requestApprovalForm,
        requestOverlapError,
        approveRequest,
        submitRequestApproval,
    };
}
