<script setup lang="ts">
import { Search } from '@lucide/vue';
import FilterField from '@/components/ui/FilterField.vue';
import Input from '@/components/ui/Input.vue';

const model = defineModel<string>({ required: true });

withDefaults(
    defineProps<{
        id: string;
        label: string;
        placeholder?: string;
        busy?: boolean;
        class?: string;
    }>(),
    { placeholder: undefined, busy: false, class: '' },
);
</script>

<template>
    <FilterField
        :for="$props.id"
        :label="$props.label"
        :busy="$props.busy"
        :class="$props.class"
    >
        <div class="relative">
            <Search
                :size="14"
                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-on-surface-variant"
                aria-hidden="true"
            />
            <Input
                :id="$props.id"
                v-model="model"
                type="search"
                :placeholder="$props.placeholder"
                class="pl-9"
            />
        </div>
    </FilterField>
</template>
