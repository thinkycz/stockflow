<script setup lang="ts">
import { computed, useId } from 'vue';
import { cn } from '@/lib/utils';

const model = defineModel<string | number | null>();

const props = withDefaults(
    defineProps<{
        id?: string;
        name?: string;
        autocomplete?: string;
        required?: boolean;
        class?: string;
        options: Array<{ value: string; label: string; disabled?: boolean }>;
        placeholder?: string;
        defaultValue?: string;
        invalid?: boolean;
        describedBy?: string;
        disabled?: boolean;
        density?: 'default' | 'compact';
    }>(),
    {
        id: undefined,
        name: undefined,
        autocomplete: undefined,
        required: false,
        class: '',
        placeholder: undefined,
        defaultValue: undefined,
        invalid: false,
        describedBy: undefined,
        disabled: false,
        density: 'default',
    },
);

const generatedId = useId();

const selectId = computed(() => props.id ?? `select-${generatedId}`);
</script>

<template>
    <select
        :id="selectId"
        v-model="model"
        :name="props.name"
        :autocomplete="props.autocomplete"
        :required="props.required"
        :disabled="props.disabled"
        :aria-invalid="props.invalid ? 'true' : undefined"
        :aria-describedby="props.describedBy"
        :class="
            cn(
                'w-full rounded-xl border bg-white px-3 text-xs text-on-surface outline-none transition focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60',
                props.density === 'compact' ? 'h-8' : 'h-10',
                props.invalid
                    ? 'border-error-red focus-visible:border-error-red'
                    : 'border-outline-glass focus-visible:border-primary',
                props.class,
            )
        "
    >
        <option v-if="props.placeholder" value="">
            {{ props.placeholder }}
        </option>
        <option
            v-for="option in props.options"
            :key="option.value"
            :value="option.value"
            :disabled="option.disabled"
            :selected="props.defaultValue === option.value"
        >
            {{ option.label }}
        </option>
    </select>
</template>
