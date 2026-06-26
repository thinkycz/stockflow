<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        id?: string;
        message?: string | string[] | null | undefined;
    }>(),
    {
        id: undefined,
        message: undefined,
    },
);

const flat = computed<string | null>(() => {
    if (props.message === null || props.message === undefined) {
        return null;
    }
    if (Array.isArray(props.message)) {
        return props.message.length > 0 ? props.message[0] : null;
    }
    return props.message;
});
</script>

<template>
    <p
        v-if="flat"
        :id="props.id"
        class="text-xs font-semibold text-error-red"
        role="alert"
    >
        {{ flat }}
    </p>
</template>
