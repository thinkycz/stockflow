import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import type { Shift, Worker } from './scheduling-types';

export function useShiftBulkDelete(
    props: {
        store: { id: number; name: string } | null;
        workers: Worker[];
        is_admin: boolean;
    },
    shifts: Ref<Shift[]>,
    month: Ref<number>,
    year: Ref<number>,
    monthLabel: Ref<string>,
    stopQuickAdd: () => void,
) {
    const { t } = useI18n();
    const route = useRoute();
    const dialog = useDialog();
    const open = ref(false);
    const confirming = ref(false);
    const selectedIds = ref<number[]>([]);
    const form = useForm({
        store_id: 0,
        year: 0,
        month: 0,
        shift_ids: [] as number[],
    });
    const busy = computed(() => confirming.value || form.processing);
    let contextVersion = 0;

    const rows = computed(() => {
        const workers = new Map(
            props.workers.map((worker) => [worker.id, worker]),
        );
        const prefix = `${year.value}-${String(month.value).padStart(2, '0')}-`;
        return shifts.value
            .filter((shift) => shift.date.startsWith(prefix))
            .map((shift) => {
                const worker = workers.get(shift.worker_id);
                return {
                    ...shift,
                    worker_name:
                        worker === undefined
                            ? '—'
                            : `${worker.first_name} ${worker.last_name}`,
                };
            })
            .sort(
                (a, b) =>
                    a.date.localeCompare(b.date) ||
                    a.start_time.localeCompare(b.start_time) ||
                    a.id - b.id,
            );
    });
    const selectedSet = computed(() => new Set(selectedIds.value));
    const error = computed(() => Object.values(form.errors)[0]);

    watch(
        () => [props.store?.id, props.is_admin, year.value, month.value],
        () => {
            contextVersion++;
            open.value = false;
            selectedIds.value = [];
            form.clearErrors();
        },
        { flush: 'sync' },
    );

    watch(rows, (available) => {
        const ids = new Set(available.map((shift) => shift.id));
        selectedIds.value = selectedIds.value.filter((id) => ids.has(id));
    });

    function show(): void {
        if (!props.is_admin || props.store === null || busy.value) return;
        stopQuickAdd();
        selectedIds.value = [];
        form.clearErrors();
        open.value = true;
    }

    function close(): void {
        if (busy.value) return;
        open.value = false;
        selectedIds.value = [];
    }

    function toggle(id: number, selected: boolean): void {
        if (busy.value) return;
        selectedIds.value = selected
            ? [...new Set([...selectedIds.value, id])]
            : selectedIds.value.filter((value) => value !== id);
    }

    function selectAll(): void {
        if (!busy.value)
            selectedIds.value = rows.value.map((shift) => shift.id);
    }

    function clearSelection(): void {
        if (!busy.value) selectedIds.value = [];
    }

    async function submit(): Promise<void> {
        if (
            busy.value ||
            !open.value ||
            selectedIds.value.length === 0 ||
            props.store === null
        )
            return;
        const version = contextVersion;
        const selection = [...selectedIds.value];
        const context = {
            store_id: props.store.id,
            year: year.value,
            month: month.value,
        };
        confirming.value = true;
        const confirmed = await dialog.confirm({
            title: t('shifts.bulk_delete.confirm_title'),
            message: t('shifts.bulk_delete.confirm_message', {
                count: selection.length,
                store: props.store.name,
                month: monthLabel.value,
            }),
            confirmLabel: t('shifts.bulk_delete.delete_selected', {
                count: selection.length,
            }),
            variant: 'danger',
        });
        confirming.value = false;
        if (!confirmed || version !== contextVersion || !open.value) return;

        form.clearErrors();
        Object.assign(form, context, { shift_ids: selection });
        form.post(route('shifts.bulk-destroy'), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
                selectedIds.value = [];
            },
        });
    }

    return {
        open,
        confirming,
        selectedIds,
        selectedSet,
        rows,
        busy,
        error,
        form,
        show,
        close,
        toggle,
        selectAll,
        clearSelection,
        submit,
    };
}
