<script setup lang="ts">
import { cn } from '@/lib/utils';
</script>

<template>
    <table :class="cn('data-table', $attrs.class as string)">
        <slot />
    </table>
</template>
