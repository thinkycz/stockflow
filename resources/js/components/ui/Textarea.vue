<script setup lang="ts">
import { cn } from '@/lib/utils';

const model = defineModel<string | null>();

withDefaults(
    defineProps<{
        id?: string;
        name?: string;
        rows?: number;
        placeholder?: string;
        class?: string;
        required?: boolean;
        invalid?: boolean;
        describedBy?: string;
    }>(),
    {
        id: undefined,
        name: undefined,
        rows: 4,
        placeholder: undefined,
        class: '',
        required: false,
        invalid: false,
        describedBy: undefined,
    },
);
</script>

<template>
    <textarea
        :id="$props.id"
        v-model="model"
        :name="$props.name"
        :rows="$props.rows"
        :placeholder="$props.placeholder"
        :required="$props.required"
        :aria-invalid="$props.invalid ? 'true' : undefined"
        :aria-describedby="$props.describedBy"
        :class="
            cn(
                'w-full resize-y rounded-xl border bg-white px-3 py-2 text-xs text-on-surface outline-none transition placeholder:text-on-surface-variant/50 focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20',
                $props.invalid
                    ? 'border-error-red focus-visible:border-error-red'
                    : 'border-outline-glass focus-visible:border-primary',
                $props.class,
            )
        "
    ></textarea>
</template>
