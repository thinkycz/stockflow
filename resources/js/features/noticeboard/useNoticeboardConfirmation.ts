import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useRoute } from '@/composables/useRoute';
import type { NoticeboardConfirmation } from '@/types/noticeboard';

export function useNoticeboardConfirmation(props: {
    confirmation: NoticeboardConfirmation | null;
}) {
    const route = useRoute();
    const checked = ref<Record<number, boolean>>({});
    const form = useForm({ item_ids: [] as number[] });
    const errors = computed(() => form.errors as Record<string, string>);
    const selectedIds = computed(
        () =>
            props.confirmation?.items
                .filter((item) => checked.value[item.id])
                .map((item) => item.id) ?? [],
    );
    const allSelected = computed(
        () =>
            props.confirmation !== null &&
            props.confirmation.items.length > 0 &&
            selectedIds.value.length === props.confirmation.items.length,
    );

    watch(
        () => props.confirmation?.id,
        () => {
            checked.value = {};
            form.clearErrors();
        },
    );

    function submit(): void {
        if (!props.confirmation || !allSelected.value || form.processing)
            return;
        form.item_ids = selectedIds.value;
        form.post(
            route(
                'attendance.noticeboard-confirmations.store',
                props.confirmation.id,
            ),
            {
                preserveScroll: true,
            },
        );
    }

    return { checked, form, errors, allSelected, selectedIds, submit };
}
