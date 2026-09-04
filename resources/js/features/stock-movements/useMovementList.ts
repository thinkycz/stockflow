import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';

type MovementRow = {
    id: number;
    number: string;
    type:
        | 'incoming'
        | 'transfer'
        | 'consumption'
        | 'adjustment'
        | 'inventory_reconciliation'
        | 'reversal';
    display_label_key:
        | 'incoming'
        | 'outgoing'
        | 'transfer'
        | 'consumption'
        | 'adjustment'
        | 'inventory_reconciliation'
        | 'reversal';
    store_id: number | null;
    store_name: string | null;
    source_store_id: number | null;
    source_store_name: string | null;
    created_at: string;
    total_value: number;
    net_value: number;
    items_count: number;
    created_by: string | null;
};

type StoreOption = {
    id: number;
    name: string;
};
export type MovementListProps = {
    movements: MovementRow[];
    stores: StoreOption[];
    filters: {
        search: string;
        type: string | null;
        source_store_id: number | null;
        destination_store_id: number | null;
        date_from: string | null;
        date_to: string | null;
    };
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};

export function useMovementList(props: MovementListProps) {
    const { t } = useI18n();

    useBoundLocale();

    const route = useRoute();

    const formSearch = ref<string>(props.filters.search || '');

    const formType = ref<string>(props.filters.type || '');

    const formSourceStoreId = ref<string>(
        props.filters.source_store_id !== null
            ? String(props.filters.source_store_id)
            : '',
    );

    const formDestinationStoreId = ref<string>(
        props.filters.destination_store_id !== null
            ? String(props.filters.destination_store_id)
            : '',
    );

    const formDateFrom = ref<string>(props.filters.date_from || '');

    const formDateTo = ref<string>(props.filters.date_to || '');

    const filtering = ref(false);

    let filterTimer: ReturnType<typeof setTimeout> | null = null;

    const totals = computed(() =>
        props.movements.reduce(
            (summary, movement) => ({
                items_count: summary.items_count + movement.items_count,
                total_value:
                    summary.total_value +
                    (movement.type === 'inventory_reconciliation'
                        ? movement.net_value
                        : movement.total_value),
            }),
            {
                items_count: 0,
                total_value: 0,
            },
        ),
    );

    function applyFilters(): void {
        const params: Record<string, string | number> = {};
        if (formSearch.value) {
            params.search = formSearch.value;
        }
        if (formType.value) {
            params.type = formType.value;
        }
        if (formSourceStoreId.value) {
            params.source_store_id = formSourceStoreId.value;
        }
        if (formDestinationStoreId.value) {
            params.destination_store_id = formDestinationStoreId.value;
        }
        if (formDateFrom.value) {
            params.date_from = formDateFrom.value;
        }
        if (formDateTo.value) {
            params.date_to = formDateTo.value;
        }
        router.get(route('stock-movements.index'), params, {
            preserveState: true,
            preserveScroll: true,
            onStart: () => (filtering.value = true),
            onFinish: () => (filtering.value = false),
        });
    }

    watch(
        [
            formSearch,
            formType,
            formSourceStoreId,
            formDestinationStoreId,
            formDateFrom,
            formDateTo,
        ],
        () => {
            if (filterTimer !== null) {
                clearTimeout(filterTimer);
            }
            filterTimer = setTimeout(applyFilters, 300);
        },
    );
    return {
        t,
        route,
        formSearch,
        formType,
        formSourceStoreId,
        formDestinationStoreId,
        formDateFrom,
        formDateTo,
        filtering,
        totals,
    };
}
