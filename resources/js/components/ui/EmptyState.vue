<script setup lang="ts">
import { Inbox, Package, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: 'package' | 'inbox' | 'trending';
    }>(),
    {
        description: '',
        icon: 'package',
    },
);

const { t } = useI18n();

const iconComponent = computed(() => {
    switch (props.icon) {
        case 'inbox':
            return Inbox;
        case 'trending':
            return TrendingUp;
        case 'package':
        default:
            return Package;
    }
});
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-outline-glass bg-surface-container-lowest/50 px-6 py-12 text-center"
    >
        <div
            aria-hidden="true"
            class="flex h-12 w-12 items-center justify-center rounded-full bg-surface-container text-on-surface-variant"
        >
            <component :is="iconComponent" :size="20" />
        </div>
        <div>
            <p class="font-heading text-sm font-semibold text-on-surface">
                {{ title }}
            </p>
            <p
                v-if="description"
                class="mt-1 text-xs font-medium text-on-surface-variant"
            >
                {{ description }}
            </p>
            <p v-else class="mt-1 text-xs font-medium text-on-surface-variant">
                {{ t('common.no_results') }}
            </p>
        </div>
        <div v-if="$slots.action" class="mt-2">
            <slot name="action" />
        </div>
    </div>
</template>
