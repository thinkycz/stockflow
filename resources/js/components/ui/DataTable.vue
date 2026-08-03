<script setup lang="ts">
import { nextTick, onMounted, onUpdated, ref } from 'vue';
import { cn } from '@/lib/utils';
import LoadingState from '@/components/ui/LoadingState.vue';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        density?: 'default' | 'compact';
        variant?: 'standalone' | 'nested';
        tableClass?: string;
        loading?: boolean;
        loadingLabel?: string;
    }>(),
    {
        density: 'default',
        variant: 'standalone',
        tableClass: '',
        loading: false,
        loadingLabel: '',
    },
);

const table = ref<HTMLTableElement | null>(null);

function headerLabels(): string[] {
    const headerRow = table.value?.tHead?.rows.item(0);
    if (!headerRow) return [];

    return Array.from(headerRow.cells).flatMap((cell) =>
        Array.from(
            { length: cell.colSpan },
            () => cell.textContent?.trim() ?? '',
        ),
    );
}

function labelCells(
    rows: Iterable<HTMLTableRowElement>,
    labels: string[],
): void {
    for (const row of rows) {
        let column = 0;

        for (const cell of row.cells) {
            const generated = cell.dataset.generatedLabel === 'true';
            const label = labels[column] ?? '';

            if ((!cell.hasAttribute('data-label') || generated) && label) {
                cell.dataset.label = label;
                cell.dataset.generatedLabel = 'true';
            }

            column += cell.colSpan;
        }
    }
}

async function syncMobileLabels(): Promise<void> {
    await nextTick();

    const labels = headerLabels();
    if (!table.value || labels.length === 0) return;

    for (const body of table.value.tBodies) {
        labelCells(body.rows, labels);
    }

    if (table.value.tFoot) {
        labelCells(table.value.tFoot.rows, labels);
    }
}

onMounted(syncMobileLabels);
onUpdated(syncMobileLabels);
</script>

<template>
    <div
        v-if="props.loading"
        :aria-busy="true"
        :class="
            cn(
                'data-table-frame',
                props.variant === 'nested' && 'data-table-frame--nested',
            )
        "
    >
        <LoadingState :label="props.loadingLabel" />
    </div>
    <div
        v-else
        :class="
            cn(
                'data-table-frame',
                props.variant === 'nested' && 'data-table-frame--nested',
            )
        "
    >
        <div class="data-table-scroll">
            <table
                ref="table"
                v-bind="$attrs"
                :class="
                    cn(
                        'data-table',
                        props.density === 'compact' && 'data-table--compact',
                        props.tableClass,
                        $attrs.class as string,
                    )
                "
            >
                <slot />
            </table>
        </div>
    </div>
</template>
