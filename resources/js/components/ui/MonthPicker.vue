<script setup lang="ts">
import Input from '@/components/ui/Input.vue';
import { cn } from '@/lib/utils';

const model = defineModel<string>({ required: true });

const props = withDefaults(
    defineProps<{
        id?: string;
        class?: string;
    }>(),
    {
        id: undefined,
        class: '',
    },
);

const emit = defineEmits<{
    change: [value: string];
}>();
</script>

<template>
    <Input
        :id="props.id"
        v-model="model"
        type="month"
        :class="cn('w-44', props.class)"
        @change="emit('change', model)"
    />
</template>
