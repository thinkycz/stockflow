import { router, useForm } from '@inertiajs/vue3';
import { ref, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import { withActionErrorToast } from '@/lib/action-errors';
import type { ShiftPreset } from './scheduling-types';

export function useShiftPresets(
    month: Ref<number>,
    year: Ref<number>,
    onDeletingPreset: (id: number) => void,
) {
    const route = useRoute();
    const dialog = useDialog();
    const { t } = useI18n();
    // --- Shift presets ---

    const presetModalOpen = ref<boolean>(false);
    const editingPresetId = ref<number | null>(null);

    type PresetForm = {
        name: string;
        start_time: string;
        end_time: string;
    };

    const presetForm = useForm<PresetForm>({
        name: '',
        start_time: '09:00',
        end_time: '17:00',
    });

    function openPresetModal(): void {
        editingPresetId.value = null;
        presetForm.reset();
        presetForm.start_time = '09:00';
        presetForm.end_time = '17:00';
        presetModalOpen.value = true;
    }

    function closePresetModal(): void {
        presetModalOpen.value = false;
        editingPresetId.value = null;
        presetForm.reset();
    }

    function editPreset(preset: ShiftPreset): void {
        editingPresetId.value = preset.id;
        presetForm.name = preset.name;
        presetForm.start_time = preset.start_time;
        presetForm.end_time = preset.end_time;
        presetForm.clearErrors();
    }

    function cancelPresetEdit(): void {
        editingPresetId.value = null;
        presetForm.reset();
        presetForm.start_time = '09:00';
        presetForm.end_time = '17:00';
    }

    function submitPreset(): void {
        const options = {
            preserveState: true,
            preserveScroll: true,
            onSuccess: cancelPresetEdit,
        };

        if (editingPresetId.value !== null) {
            presetForm.put(
                route('shift-presets.update', {
                    shiftPreset: editingPresetId.value,
                    month: month.value,
                    year: year.value,
                }),
                options,
            );
            return;
        }

        presetForm.post(
            route('shift-presets.store', {
                month: month.value,
                year: year.value,
            }),
            options,
        );
    }

    async function deletePreset(preset: ShiftPreset): Promise<void> {
        if (
            !(await dialog.confirm({
                title: t('common.delete'),
                message: `${preset.name}: ${t('shifts.presets.confirm_delete')}`,
                confirmLabel: t('common.delete'),
                variant: 'danger',
            }))
        ) {
            return;
        }

        onDeletingPreset(preset.id);

        router.delete(
            route('shift-presets.destroy', {
                shiftPreset: preset.id,
                month: month.value,
                year: year.value,
            }),
            withActionErrorToast({
                preserveState: true,
                preserveScroll: true,
            }),
        );
    }

    return {
        presetModalOpen,
        editingPresetId,
        presetForm,
        openPresetModal,
        closePresetModal,
        editPreset,
        cancelPresetEdit,
        submitPreset,
        deletePreset,
    };
}
