<script setup lang="ts">
import { computed, nextTick, ref, watch, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatDateInput, parseCzechDateInput } from '@/lib/date-input';
import DatePicker from '@/components/ui/DatePicker.vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });
const model = defineModel<string | number>();

const props = withDefaults(
    defineProps<{
        id?: string;
        name?: string;
        type?: string;
        autocomplete?: string;
        placeholder?: string;
        class?: string;
        required?: boolean;
        disabled?: boolean;
        readonly?: boolean;
        defaultValue?: string;
        invalid?: boolean;
        describedBy?: string;
        min?: string | number;
        max?: string | number;
        step?: string | number;
    }>(),
    {
        id: undefined,
        name: undefined,
        type: 'text',
        autocomplete: undefined,
        placeholder: undefined,
        class: '',
        required: false,
        disabled: false,
        readonly: false,
        defaultValue: '',
        invalid: false,
        describedBy: undefined,
        min: undefined,
        max: undefined,
        step: undefined,
    },
);
const { t } = useI18n();
const input = ref<HTMLInputElement | null>(null);
const isDate = computed(
    () => props.type === 'date' || props.type === 'datetime-local',
);
const withTime = computed(() => props.type === 'datetime-local');
const draft = ref('');
watch(
    () => model.value ?? props.defaultValue,
    (value) => {
        const text = String(value);
        if (
            text !== parseCzechDateInput(draft.value, withTime.value) &&
            text !== draft.value
        ) {
            draft.value = formatDateInput(text);
        }
    },
    { immediate: true },
);
watchEffect(() => {
    if (!isDate.value || !input.value) return;
    const iso = parseCzechDateInput(draft.value, withTime.value);
    let error = '';
    if (draft.value !== '' && iso === null) error = t('common.invalid_date');
    else if (
        iso &&
        ((props.min !== undefined && iso < String(props.min)) ||
            (props.max !== undefined && iso > String(props.max)))
    )
        error = t('common.date_out_of_range');
    input.value.setCustomValidity(error);
});
function update(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    if (!isDate.value) {
        model.value = value;
        return;
    }
    draft.value = value;
    model.value =
        value === ''
            ? ''
            : (parseCzechDateInput(value, withTime.value) ?? value);
}
function normalize(): void {
    if (isDate.value) draft.value = formatDateInput(String(model.value ?? ''));
}
async function pick(value: string): Promise<void> {
    model.value = value;
    draft.value = formatDateInput(value);
    await nextTick();
    input.value?.dispatchEvent(new Event('change', { bubbles: true }));
}
</script>

<template>
    <input
        v-if="!isDate"
        v-bind="$attrs"
        :disabled="props.disabled"
        :readonly="props.readonly"
        ref="input"
        :id="$props.id"
        :value="isDate ? draft : (model ?? $props.defaultValue)"
        :name="$props.name"
        :type="isDate ? 'text' : $props.type"
        :autocomplete="$props.autocomplete"
        :placeholder="
            $props.placeholder ??
            (isDate ? (withTime ? 'd.m.rrrr HH:mm' : 'd.m.rrrr') : undefined)
        "
        :required="$props.required"
        :aria-invalid="$props.invalid ? 'true' : undefined"
        :aria-describedby="$props.describedBy"
        :min="$props.min"
        :max="$props.max"
        :step="$props.step"
        :class="
            cn(
                'h-10 w-full rounded-xl border bg-white px-3 text-xs text-on-surface outline-none transition placeholder:text-on-surface-variant/50 focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20',
                $props.invalid
                    ? 'border-error-red focus-visible:border-error-red'
                    : 'border-outline-glass focus-visible:border-primary',
                $props.class,
            )
        "
        @input="update"
        @blur="normalize"
    />
    <div v-else :class="cn('relative min-w-0 w-full', props.class)">
        <input
            v-bind="$attrs"
            :disabled="props.disabled"
            :readonly="props.readonly"
            ref="input"
            :id="$props.id"
            :value="isDate ? draft : (model ?? $props.defaultValue)"
            :name="$props.name"
            :type="isDate ? 'text' : $props.type"
            :autocomplete="$props.autocomplete"
            :placeholder="
                $props.placeholder ??
                (isDate
                    ? withTime
                        ? 'd.m.rrrr HH:mm'
                        : 'd.m.rrrr'
                    : undefined)
            "
            :required="$props.required"
            :aria-invalid="$props.invalid ? 'true' : undefined"
            :aria-describedby="$props.describedBy"
            :min="$props.min"
            :max="$props.max"
            :step="$props.step"
            :class="
                cn(
                    'h-10 w-full rounded-xl border bg-white px-3 text-xs text-on-surface outline-none transition placeholder:text-on-surface-variant/50 focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20',
                    $props.invalid
                        ? 'border-error-red focus-visible:border-error-red'
                        : 'border-outline-glass focus-visible:border-primary',
                    'pr-10',
                    $props.class,
                )
            "
            @input="update"
            @blur="normalize"
        />
        <DatePicker
            :value="String(model ?? props.defaultValue)"
            :with-time="withTime"
            :disabled="props.disabled || props.readonly"
            :min="props.min"
            :max="props.max"
            :step="props.step"
            @select="pick"
        />
    </div>
</template>
