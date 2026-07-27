<script setup lang="ts">
import { MapPin } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { useSharedProps } from '@/composables/useSharedProps';

const { t } = useI18n();
const { activeStore } = useSharedProps();
</script>

<template>
    <div
        v-if="activeStore"
        data-testid="active-store-pill"
        :aria-label="`${t('store_switcher.label')}: ${activeStore.name}`"
        class="mt-2 flex max-w-full items-center gap-1.5 text-xs font-semibold text-on-surface-variant"
    >
        <MapPin :size="13" class="shrink-0 text-primary" />
        <span class="truncate">{{ activeStore.name }}</span>
    </div>
</template>
