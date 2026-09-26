<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import Modal from '@/components/ui/Modal.vue';
import { formatDate } from '@/lib/format';

defineProps<{
    open: boolean;
    storeName: string;
    monthLabel: string;
    rows: {
        id: number;
        date: string;
        worker_name: string;
        start_time: string;
        end_time: string;
    }[];
    selectedIds: ReadonlySet<number>;
    busy: boolean;
    error?: string;
}>();
const emit = defineEmits<{
    close: [];
    toggle: [id: number, selected: boolean];
    selectAll: [];
    clearSelection: [];
    submit: [];
}>();
const { t } = useI18n();
</script>

<template>
    <Modal
        :open="open"
        :title="t('shifts.bulk_delete.manage')"
        size="lg"
        class="flex max-h-[calc(100dvh-2rem)] flex-col"
        body-class="min-h-0 flex-1 overflow-y-auto"
        :close-on-backdrop="!busy"
        @close="emit('close')"
    >
        <div class="space-y-4">
            <p class="text-sm font-semibold text-on-surface">
                {{ storeName }} · {{ monthLabel }}
            </p>
            <div class="flex flex-wrap gap-2">
                <Button
                    variant="secondary"
                    :disabled="busy || rows.length === 0"
                    @click="emit('selectAll')"
                    >{{ t('shifts.bulk_delete.select_month') }}</Button
                >
                <Button
                    variant="ghost"
                    :disabled="busy || selectedIds.size === 0"
                    @click="emit('clearSelection')"
                    >{{ t('shifts.bulk_delete.clear') }}</Button
                >
            </div>
            <p v-if="error" role="alert" class="text-sm text-error-red">
                {{ error }}
            </p>
            <p
                v-if="rows.length === 0"
                class="py-6 text-sm text-on-surface-variant"
            >
                {{ t('shifts.bulk_delete.empty') }}
            </p>
            <ul
                v-else
                class="divide-y divide-outline-glass"
                data-testid="bulk-shift-list"
            >
                <li v-for="shift in rows" :key="shift.id">
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-3 hover:bg-surface-container-low"
                        :class="{
                            'bg-primary-fixed/40': selectedIds.has(shift.id),
                        }"
                    >
                        <Checkbox
                            :model-value="selectedIds.has(shift.id)"
                            :disabled="busy"
                            @update:model-value="
                                emit('toggle', shift.id, $event)
                            "
                        />
                        <span
                            class="grid min-w-0 flex-1 gap-1 text-sm sm:grid-cols-[7rem_1fr_auto] sm:items-center sm:gap-3"
                        >
                            <span class="text-on-surface-variant">{{
                                formatDate(shift.date)
                            }}</span>
                            <span class="font-semibold text-on-surface">{{
                                shift.worker_name
                            }}</span>
                            <span
                                class="whitespace-nowrap text-on-surface-variant"
                                >{{ shift.start_time }}–{{
                                    shift.end_time
                                }}</span
                            >
                        </span>
                    </label>
                </li>
            </ul>
        </div>
        <template #footer>
            <div
                class="flex w-full flex-wrap items-center justify-between gap-3"
            >
                <span
                    aria-live="polite"
                    class="text-sm text-on-surface-variant"
                    >{{
                        t('shifts.bulk_delete.selected_count', {
                            count: selectedIds.size,
                            total: rows.length,
                        })
                    }}</span
                >
                <div class="flex flex-wrap gap-2">
                    <Button
                        variant="secondary"
                        :disabled="busy"
                        @click="emit('close')"
                        >{{ t('common.cancel') }}</Button
                    >
                    <Button
                        variant="danger"
                        :disabled="busy || selectedIds.size === 0"
                        :loading="busy"
                        @click="emit('submit')"
                        >{{
                            t('shifts.bulk_delete.delete_selected', {
                                count: selectedIds.size,
                            })
                        }}</Button
                    >
                </div>
            </div>
        </template>
    </Modal>
</template>
