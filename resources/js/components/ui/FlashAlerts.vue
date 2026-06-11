<script setup lang="ts">
import { computed } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import { useSharedProps } from '@/composables/useSharedProps';

const props = withDefaults(
    defineProps<{
        successKey?: string;
        errorKey?: string;
    }>(),
    {
        successKey: 'success',
        errorKey: 'error',
    },
);

const { flash } = useSharedProps();

const successMessage = computed<string | null>(() => {
    const value = flash.value[props.successKey as keyof typeof flash.value];
    return typeof value === 'string' ? value : null;
});

const errorMessage = computed<string | null>(() => {
    const value = flash.value[props.errorKey as keyof typeof flash.value];
    return typeof value === 'string' ? value : null;
});
</script>

<template>
    <div v-if="successMessage" class="mb-4">
        <Alert variant="success">
            {{ successMessage }}
        </Alert>
    </div>
    <div v-if="errorMessage" class="mb-4">
        <Alert variant="error">
            {{ errorMessage }}
        </Alert>
    </div>
</template>
