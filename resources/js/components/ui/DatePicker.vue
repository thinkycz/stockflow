<script setup lang="ts">
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import { formatDateInput, parseCzechDateInput } from '@/lib/date-input';

const props = defineProps<{
    value: string;
    withTime: boolean;
    disabled?: boolean;
    min?: string | number;
    max?: string | number;
    step?: string | number;
}>();
const emit = defineEmits<{ select: [value: string] }>();
const { t } = useI18n();
const open = ref(false);
const selected = ref('');
const time = ref('00:00');
const month = ref('');
const focused = ref('');
const grid = ref<HTMLElement | null>(null);
function dateString(date: Date): string {
    return date.toISOString().slice(0, 10);
}
function dateOf(value: string): Date {
    return new Date(`${value}T12:00:00Z`);
}
function shift(value: string, days: number): string {
    const date = dateOf(value);
    date.setUTCDate(date.getUTCDate() + days);
    return dateString(date);
}
const title = computed(() =>
    month.value
        ? new Intl.DateTimeFormat('cs-CZ', {
              month: 'long',
              year: 'numeric',
              timeZone: 'UTC',
          }).format(dateOf(`${month.value}-01`))
        : '',
);
const days = computed(() => {
    if (!month.value) return [];
    const first = `${month.value}-01`;
    const start = shift(first, -((dateOf(first).getUTCDay() + 6) % 7));
    return Array.from({ length: 42 }, (_, index) => shift(start, index));
});
function unavailable(day: string): boolean {
    return (
        (props.min !== undefined && day < String(props.min).slice(0, 10)) ||
        (props.max !== undefined && day > String(props.max).slice(0, 10))
    );
}
const result = computed(() =>
    selected.value
        ? selected.value + (props.withTime ? `T${time.value}` : '')
        : '',
);
const valid = computed(
    () =>
        parseCzechDateInput(formatDateInput(result.value), props.withTime) !==
            null &&
        (props.min === undefined || result.value >= String(props.min)) &&
        (props.max === undefined || result.value <= String(props.max)),
);
function show(): void {
    const parsed = parseCzechDateInput(
        formatDateInput(props.value),
        props.withTime,
    );
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    selected.value = parsed?.slice(0, 10) ?? today;
    if (
        props.min !== undefined &&
        selected.value < String(props.min).slice(0, 10)
    )
        selected.value = String(props.min).slice(0, 10);
    if (
        props.max !== undefined &&
        selected.value > String(props.max).slice(0, 10)
    )
        selected.value = String(props.max).slice(0, 10);
    time.value = parsed?.slice(11, 16) || '00:00';
    month.value = selected.value.slice(0, 7);
    focused.value = selected.value;
    open.value = true;
}
function moveMonth(amount: number): void {
    const date = dateOf(`${month.value}-01`);
    date.setUTCMonth(date.getUTCMonth() + amount);
    month.value = dateString(date).slice(0, 7);
    focused.value = `${month.value}-01`;
}
async function keydown(event: KeyboardEvent): Promise<void> {
    const offsets: Record<string, number> = {
        ArrowLeft: -1,
        ArrowRight: 1,
        ArrowUp: -7,
        ArrowDown: 7,
        Home: -((dateOf(focused.value).getUTCDay() + 6) % 7),
        End: 6 - ((dateOf(focused.value).getUTCDay() + 6) % 7),
    };
    if (!(event.key in offsets)) return;
    event.preventDefault();
    focused.value = shift(focused.value, offsets[event.key]);
    month.value = focused.value.slice(0, 7);
    await nextTick();
    grid.value
        ?.querySelector<HTMLButtonElement>(`[data-day="${focused.value}"]`)
        ?.focus();
}
function apply(value: string): void {
    emit('select', value);
    open.value = false;
}
</script>

<template>
    <Button
        variant="ghost"
        size="icon-sm"
        class="absolute right-1 top-1"
        :disabled="disabled"
        :aria-label="t('common.date_picker.open')"
        @click="show"
        ><CalendarDays :size="16" aria-hidden="true"
    /></Button>
    <Modal
        v-if="open"
        :open="open"
        :title="t('common.date_picker.title')"
        size="sm"
        @close="open = false"
    >
        <div class="flex items-center justify-between mb-3">
            <Button
                variant="ghost"
                size="icon-sm"
                :aria-label="t('common.date_picker.previous')"
                @click="moveMonth(-1)"
                ><ChevronLeft :size="16"
            /></Button>
            <span aria-live="polite" class="font-semibold">{{ title }}</span>
            <Button
                variant="ghost"
                size="icon-sm"
                :aria-label="t('common.date_picker.next')"
                @click="moveMonth(1)"
                ><ChevronRight :size="16"
            /></Button>
        </div>
        <div ref="grid" role="grid" :aria-label="title" @keydown="keydown">
            <div
                role="row"
                class="grid grid-cols-7 text-center text-xs text-on-surface-variant"
            >
                <span
                    v-for="day in ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne']"
                    :key="day"
                    role="columnheader"
                    >{{ day }}</span
                >
            </div>
            <div
                v-for="week in 6"
                :key="week"
                role="row"
                class="grid grid-cols-7"
            >
                <div
                    v-for="day in days.slice((week - 1) * 7, week * 7)"
                    :key="day"
                    role="gridcell"
                    :aria-selected="day === selected"
                >
                    <button
                        type="button"
                        :data-day="day"
                        :tabindex="day === focused ? 0 : -1"
                        :aria-label="formatDateInput(day)"
                        :aria-disabled="unavailable(day)"
                        class="h-10 w-full rounded-lg text-sm focus-visible:ring-2 focus-visible:ring-primary"
                        :class="[
                            day === selected
                                ? 'bg-primary text-white'
                                : 'hover:bg-surface-container-low',
                            day.slice(0, 7) !== month || unavailable(day)
                                ? 'opacity-40'
                                : '',
                        ]"
                        @focus="focused = day"
                        @click="!unavailable(day) && (selected = day)"
                    >
                        {{ Number(day.slice(8)) }}
                    </button>
                </div>
            </div>
        </div>
        <label
            v-if="withTime"
            class="mt-3 flex items-center justify-between gap-3 text-sm"
            >{{ t('common.date_picker.time')
            }}<input
                v-model="time"
                type="time"
                :step="step"
                class="rounded-lg border border-outline-glass p-2"
        /></label>
        <p v-if="!valid" class="mt-2 text-xs text-error-red">
            {{ t('common.date_out_of_range') }}
        </p>
        <div class="mt-4 flex justify-between gap-2">
            <Button variant="ghost" @click="apply('')">{{
                t('common.date_picker.clear')
            }}</Button
            ><Button :disabled="!valid" @click="apply(result)">{{
                t('common.date_picker.apply')
            }}</Button>
        </div>
    </Modal>
</template>
