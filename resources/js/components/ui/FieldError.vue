<script setup lang="ts">
withDefaults(
    defineProps<{
        id?: string;
        message?: string | null | undefined;
    }>(),
    {
        id: undefined,
        message: undefined,
    },
);
</script>

<template>
    <p
        v-if="message"
        :id="$props.id"
        class="text-xs font-semibold text-error-red"
        role="alert"
    >
        {{ message }}
    </p>
</template>
