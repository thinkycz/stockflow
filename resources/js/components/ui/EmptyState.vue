<script setup lang="ts">
import { Inbox, Package, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: 'package' | 'inbox' | 'trending';
        density?: 'default' | 'compact';
    }>(),
    {
        description: '',
        icon: 'package',
        density: 'default',
    },
);

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
        :class="
            cn(
                'flex flex-col items-center justify-center rounded-2xl border border-dashed border-outline-glass bg-surface-container-lowest/50 text-center',
                density === 'compact' ? 'gap-2 px-4 py-6' : 'gap-3 px-6 py-12',
            )
        "
    >
        <div
            aria-hidden="true"
            :class="
                cn(
                    'flex items-center justify-center rounded-full bg-surface-container text-on-surface-variant',
                    density === 'compact' ? 'size-9' : 'size-12',
                )
            "
        >
            <component
                :is="iconComponent"
                :size="density === 'compact' ? 16 : 20"
            />
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
        </div>
        <div v-if="$slots.action" class="mt-2">
            <slot name="action" />
        </div>
    </div>
</template>
