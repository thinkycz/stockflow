<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCircle2, ClipboardCheck, Sunrise, Sunset } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Card from '@/components/ui/Card.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import { useRoute } from '@/composables/useRoute';
import { withActionErrorToast } from '@/lib/action-errors';

export type ChecklistDashboardItem = {
    id: number;
    text: string;
    completed: boolean;
    completed_at: string | null;
    worker_name: string | null;
    version: number;
};

type ShiftPayload = {
    status:
        | 'not_configured'
        | 'in_progress'
        | 'completed'
        | 'incomplete'
        | 'excused';
    items: ChecklistDashboardItem[];
};

export type ChecklistDashboardPayload = {
    day_id: number;
    date: string;
    editable: boolean;
    excuse_reason: string | null;
    workers: Array<{ id: number; name: string }>;
    shifts: { morning: ShiftPayload; afternoon: ShiftPayload };
};

const props = defineProps<{ checklists: ChecklistDashboardPayload }>();
const { t } = useI18n();
const route = useRoute();
const selectedWorkers = reactive<Record<'morning' | 'afternoon', string>>({
    morning: '',
    afternoon: '',
});
const completionState = reactive<Record<number, boolean>>({});
const pendingItem = ref<number | null>(null);

const workerOptions = computed(() =>
    props.checklists.workers.map((worker) => ({
        value: String(worker.id),
        label: worker.name,
    })),
);

watch(
    () => props.checklists,
    (payload) => {
        for (const shift of Object.values(payload.shifts)) {
            for (const item of shift.items) {
                completionState[item.id] = item.completed;
            }
        }
    },
    { immediate: true, deep: true },
);

function toggle(
    shift: 'morning' | 'afternoon',
    item: ChecklistDashboardItem,
): void {
    const completed = completionState[item.id] ?? false;
    const workerId = Number(selectedWorkers[shift]);
    if (completed && workerId < 1) {
        completionState[item.id] = false;
        return;
    }
    pendingItem.value = item.id;
    router.put(
        route('checklist-items.update', item.id),
        {
            completed,
            worker_id: workerId > 0 ? workerId : null,
            lock_version: item.version,
        },
        withActionErrorToast({
            preserveScroll: true,
            only: ['checklists', 'flash'],
            onFinish: () => {
                pendingItem.value = null;
            },
            onError: () => {
                completionState[item.id] = item.completed;
            },
        }),
    );
}
</script>

<template>
    <section data-testid="dashboard-checklists" class="space-y-3">
        <div class="flex items-center gap-2">
            <ClipboardCheck :size="18" class="text-primary" />
            <div>
                <h2 class="font-heading text-lg font-bold text-on-surface">
                    {{ t('checklists.dashboard_title') }}
                </h2>
                <p class="text-xs text-on-surface-variant">
                    {{
                        t('checklists.dashboard_subtitle', {
                            date: checklists.date,
                        })
                    }}
                </p>
            </div>
        </div>

        <div
            v-if="checklists.excuse_reason"
            class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            {{
                t('checklists.excused_reason', {
                    reason: checklists.excuse_reason,
                })
            }}
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card
                v-for="shiftName in ['morning', 'afternoon'] as const"
                :key="shiftName"
                class="overflow-hidden"
                data-testid="checklist-shift-card"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Sunrise
                            v-if="shiftName === 'morning'"
                            :size="18"
                            class="text-amber-600"
                        />
                        <Sunset v-else :size="18" class="text-indigo-600" />
                        <div>
                            <h3
                                class="font-heading text-sm font-bold text-on-surface"
                            >
                                {{ t(`checklists.shifts.${shiftName}`) }}
                            </h3>
                            <p class="text-xs text-on-surface-variant">
                                {{
                                    t(
                                        `checklists.status.${checklists.shifts[shiftName].status}`,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <CheckCircle2
                        v-if="
                            checklists.shifts[shiftName].status === 'completed'
                        "
                        :size="20"
                        class="text-emerald-600"
                    />
                </div>

                <div
                    v-if="checklists.shifts[shiftName].items.length > 0"
                    class="mt-4 space-y-4"
                >
                    <Label
                        :for="`checklist-worker-${shiftName}`"
                        class="sr-only"
                        >{{ t('checklists.select_worker') }}</Label
                    >
                    <Select
                        :id="`checklist-worker-${shiftName}`"
                        v-model="selectedWorkers[shiftName]"
                        :options="workerOptions"
                        :placeholder="t('checklists.select_worker')"
                        :disabled="!checklists.editable"
                        density="compact"
                    />
                    <ul class="divide-y divide-outline-glass">
                        <li
                            v-for="item in checklists.shifts[shiftName].items"
                            :key="item.id"
                            class="flex items-start gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <Checkbox
                                :id="`checklist-item-${item.id}`"
                                v-model="completionState[item.id]"
                                class="mt-0.5 shrink-0"
                                :disabled="
                                    !checklists.editable ||
                                    pendingItem === item.id ||
                                    (!item.completed &&
                                        !selectedWorkers[shiftName])
                                "
                                @change="toggle(shiftName, item)"
                            />
                            <label
                                :for="`checklist-item-${item.id}`"
                                class="min-w-0 text-sm text-on-surface"
                            >
                                <span
                                    :class="
                                        item.completed
                                            ? 'line-through opacity-65'
                                            : ''
                                    "
                                    >{{ item.text }}</span
                                >
                                <span
                                    v-if="item.worker_name"
                                    class="mt-1 block text-[11px] text-on-surface-variant"
                                >
                                    {{ item.worker_name }}
                                </span>
                            </label>
                        </li>
                    </ul>
                </div>
                <p v-else class="mt-4 text-sm text-on-surface-variant">
                    {{ t('checklists.no_tasks') }}
                </p>
            </Card>
        </div>
    </section>
</template>
