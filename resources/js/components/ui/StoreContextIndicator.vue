<script setup lang="ts">
import { MapPin } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSharedProps } from '@/composables/useSharedProps';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{ class?: string; store?: { name: string } | null }>(),
    { class: '', store: null },
);

const { t } = useI18n();
const { activeStore } = useSharedProps();
const displayedStore = computed(() => props.store ?? activeStore.value);
</script>

<template>
    <div
        v-if="displayedStore"
        data-testid="active-store-pill"
        :aria-label="`${t('store_switcher.label')}: ${displayedStore.name}`"
        :class="
            cn(
                'mt-2 flex max-w-full items-center gap-1.5 text-xs font-semibold text-on-surface-variant',
                props.class,
            )
        "
    >
        <MapPin :size="13" class="shrink-0 text-primary" />
        <span class="truncate">{{ displayedStore.name }}</span>
    </div>
</template>
