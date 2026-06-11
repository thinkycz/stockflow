<script setup lang="ts">
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        title: string;
        value: string | number;
        delta?: string;
        deltaTrend?: 'up' | 'down' | 'neutral';
        hint?: string;
    }>(),
    {
        delta: '',
        deltaTrend: 'neutral',
        hint: '',
    },
);
</script>

<template>
    <dl
        :class="
            cn(
                'rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm',
            )
        "
    >
        <div class="flex items-start justify-between">
            <dt
                class="font-mono text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
            >
                {{ title }}
            </dt>
            <span aria-hidden="true" class="text-on-surface-variant">
                <slot name="icon" />
            </span>
        </div>
        <dd
            class="mt-2 font-heading text-2xl font-bold tracking-tight text-on-surface"
        >
            {{ value }}
        </dd>
        <div class="mt-2 flex items-center gap-2 text-xs font-medium">
            <span
                v-if="delta"
                :class="
                    cn(
                        'inline-flex items-center gap-1',
                        props.deltaTrend === 'up' && 'text-emerald-600',
                        props.deltaTrend === 'down' && 'text-rose-600',
                        props.deltaTrend === 'neutral' &&
                            'text-on-surface-variant',
                    )
                "
            >
                {{ delta }}
            </span>
            <span v-if="hint" class="text-on-surface-variant">{{ hint }}</span>
        </div>
    </dl>
</template>
