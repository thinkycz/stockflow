<script setup lang="ts">
import { computed } from 'vue';

/**
 * Compact inline SVG line chart used for per-row trend visualisation
 * (stock counts over time). Days without a recorded value are treated
 * as gaps — the line is split at those points so the UI can communicate
 * that the count is unknown for those days.
 *
 * The component is intentionally dependency-free so the bundle stays
 * lean and the rendering cost is negligible even when each row in a
 * long table embeds one.
 */
const props = withDefaults(
    defineProps<{
        data: Array<{ label: string; value: number | null }>;
        width?: number;
        height?: number;
        color?: string;
        strokeWidth?: number;
    }>(),
    {
        width: 120,
        height: 32,
        color: 'currentColor',
        strokeWidth: 1.5,
    },
);

const geometry = computed<{
    segments: Array<{ d: string }>;
    empty: boolean;
    viewBox: string;
}>(() => {
    const points = props.data;
    const width = props.width;
    const height = props.height;
    const padding = 2;

    if (points.length === 0) {
        return { segments: [], empty: true, viewBox: `0 0 ${width} ${height}` };
    }

    const numbers = points
        .map((point) => point.value)
        .filter((value): value is number => value !== null);

    if (numbers.length === 0) {
        return { segments: [], empty: true, viewBox: `0 0 ${width} ${height}` };
    }

    const min = Math.min(...numbers);
    const max = Math.max(...numbers);
    const range = max - min === 0 ? 1 : max - min;
    const innerWidth = width - padding * 2;
    const innerHeight = height - padding * 2;
    const step = points.length > 1 ? innerWidth / (points.length - 1) : 0;

    const segments: Array<{ d: string }> = [];
    let current: Array<[number, number]> = [];

    points.forEach((point, index) => {
        if (point.value === null) {
            if (current.length >= 2) {
                segments.push({ d: buildPath(current) });
            }
            current = [];
            return;
        }

        const x = padding + index * step;
        const y =
            padding + innerHeight - ((point.value - min) / range) * innerHeight;
        current.push([x, y]);

        if (index === points.length - 1 && current.length >= 2) {
            segments.push({ d: buildPath(current) });
        }
    });

    return { segments, empty: false, viewBox: `0 0 ${width} ${height}` };
});

function buildPath(points: Array<[number, number]>): string {
    return points
        .map(
            ([x, y], index) =>
                `${index === 0 ? 'M' : 'L'}${x.toFixed(2)} ${y.toFixed(2)}`,
        )
        .join(' ');
}
</script>

<template>
    <svg
        v-if="!geometry.empty"
        :width="width"
        :height="height"
        :viewBox="geometry.viewBox"
        role="img"
        aria-hidden="true"
        class="text-on-surface-variant"
    >
        <path
            v-for="(segment, index) in geometry.segments"
            :key="index"
            :d="segment.d"
            fill="none"
            :stroke="color"
            :stroke-width="strokeWidth"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
    <span v-else class="inline-block h-8 w-32 rounded bg-surface-variant/40" />
</template>
