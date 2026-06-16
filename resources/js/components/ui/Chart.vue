<script setup lang="ts">
import { computed } from 'vue';

type ChartType = 'line' | 'bar' | 'pie';

type Series = {
    key: string;
    label: string;
    color: string;
};

type LinePoint = { label: string; value: number };
type BarSlice = { label: string; value: number };

const props = withDefaults(
    defineProps<{
        type: ChartType;
        title?: string;
        data: LinePoint[] | BarSlice[];
        series?: Series[];
        height?: number;
        emptyText?: string;
    }>(),
    {
        title: '',
        height: 220,
        series: () => [],
        emptyText: '',
    },
);

const palette = [
    '#1f6feb',
    '#16a34a',
    '#f59e0b',
    '#dc2626',
    '#7c3aed',
    '#0891b2',
    '#db2777',
    '#65a30d',
];

function colorFor(index: number, override?: string): string {
    if (override !== undefined && override !== '') {
        return override;
    }
    return palette[index % palette.length] ?? '#1f6feb';
}

const total = computed((): number => {
    let sum = 0;
    for (const point of props.data) {
        sum += Math.max(0, point.value);
    }
    return sum;
});

const max = computed((): number => {
    let value = 0;
    for (const point of props.data) {
        if (point.value > value) {
            value = point.value;
        }
    }
    return value;
});

const isEmpty = computed((): boolean => total.value === 0);

const pieSlices = computed((): Array<{ label: string; value: number; percent: number; color: string }> => {
    if (total.value === 0) {
        return [];
    }
    return props.data.map((point, index) => ({
        label: point.label,
        value: point.value,
        percent: (point.value / total.value) * 100,
        color: colorFor(index, props.series[index]?.color),
    }));
});

const barGeometry = computed((): Array<{ x: number; y: number; width: number; height: number; color: string; label: string; value: number }> => {
    const padding = { top: 10, right: 12, bottom: 28, left: 44 };
    const width = 720;
    const height = props.height;
    const innerW = width - padding.left - padding.right;
    const innerH = height - padding.top - padding.bottom;
    const count = props.data.length;
    if (count === 0 || max.value === 0) {
        return [];
    }
    const barWidth = (innerW / count) * 0.7;
    const gap = (innerW / count) * 0.3;
    return props.data.map((point, index) => {
        const valueHeight = (point.value / max.value) * innerH;
        const x = padding.left + index * (barWidth + gap) + gap / 2;
        const y = padding.top + (innerH - valueHeight);
        return {
            x,
            y,
            width: barWidth,
            height: valueHeight,
            color: colorFor(index, props.series[index]?.color),
            label: point.label,
            value: point.value,
        };
    });
});

const lineGeometry = computed((): {
    path: string;
    area: string;
    points: Array<{ x: number; y: number; label: string; value: number }>;
    yTicks: Array<{ y: number; label: string }>;
} => {
    const padding = { top: 16, right: 12, bottom: 28, left: 56 };
    const width = 720;
    const height = props.height;
    const innerW = width - padding.left - padding.right;
    const innerH = height - padding.top - padding.bottom;
    const count = props.data.length;
    if (count === 0) {
        return { path: '', area: '', points: [], yTicks: [] };
    }
    const effectiveMax = max.value > 0 ? max.value : 1;
    const stepX = count > 1 ? innerW / (count - 1) : 0;
    const points = props.data.map((point, index) => {
        const x = padding.left + index * stepX;
        const y = padding.top + innerH - (point.value / effectiveMax) * innerH;
        return { x, y, label: point.label, value: point.value };
    });
    const path = points
        .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`)
        .join(' ');
    const first = points[0];
    const last = points[points.length - 1];
    const area = first && last
        ? `${path} L ${last.x.toFixed(2)} ${(padding.top + innerH).toFixed(2)} L ${first.x.toFixed(2)} ${(padding.top + innerH).toFixed(2)} Z`
        : '';
    const yTicks = [0, 0.25, 0.5, 0.75, 1].map((ratio) => {
        const y = padding.top + innerH - ratio * innerH;
        const labelValue = (effectiveMax * ratio).toLocaleString('cs-CZ', {
            maximumFractionDigits: 0,
        });
        return { y, label: labelValue };
    });
    return { path, area, points, yTicks };
});

const chartWidth = 720;
</script>

<template>
    <div
        class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-5"
    >
        <p
            v-if="title !== ''"
            class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant"
        >
            {{ title }}
        </p>
        <div v-if="isEmpty" class="flex items-center justify-center py-12">
            <p class="text-xs text-on-surface-variant">
                {{ emptyText !== '' ? emptyText : '—' }}
            </p>
        </div>
        <svg
            v-else-if="type === 'bar'"
            :viewBox="`0 0 ${chartWidth} ${props.height}`"
            class="mt-3 w-full"
            preserveAspectRatio="none"
        >
            <line
                v-for="tick in [
                    { y: props.height - 28, label: '0' },
                    { y: (props.height - 28) * 0.5, label: (max / 2).toLocaleString('cs-CZ', { maximumFractionDigits: 0 }) },
                    { y: 16, label: max.toLocaleString('cs-CZ', { maximumFractionDigits: 0 }) },
                ]"
                :key="tick.label"
                :x1="44"
                :x2="chartWidth - 12"
                :y1="tick.y"
                :y2="tick.y"
                stroke="currentColor"
                stroke-opacity="0.08"
                stroke-width="1"
            />
            <text
                v-for="tick in [
                    { y: props.height - 28, label: '0' },
                    { y: (props.height - 28) * 0.5, label: (max / 2).toLocaleString('cs-CZ', { maximumFractionDigits: 0 }) },
                    { y: 16, label: max.toLocaleString('cs-CZ', { maximumFractionDigits: 0 }) },
                ]"
                :key="`label-${tick.label}`"
                :x="6"
                :y="tick.y + 3"
                class="fill-current text-[9px] opacity-50"
            >
                {{ tick.label }}
            </text>
            <g v-for="bar in barGeometry" :key="`bar-${bar.label}`">
                <rect
                    :x="bar.x"
                    :y="bar.y"
                    :width="bar.width"
                    :height="bar.height"
                    :fill="bar.color"
                    rx="4"
                    ry="4"
                />
                <text
                    :x="bar.x + bar.width / 2"
                    :y="bar.y - 4"
                    class="fill-current text-[9px] opacity-70"
                    text-anchor="middle"
                >
                    {{ bar.value.toLocaleString('cs-CZ', { maximumFractionDigits: 0 }) }}
                </text>
            </g>
            <text
                v-for="(point, index) in props.data"
                :key="`bar-label-${index}`"
                :x="44 + (index * ((chartWidth - 56) / props.data.length)) + ((chartWidth - 56) / props.data.length) / 2"
                :y="props.height - 8"
                class="fill-current text-[9px] opacity-60"
                text-anchor="middle"
            >
                {{ point.label }}
            </text>
        </svg>
        <svg
            v-else-if="type === 'line'"
            :viewBox="`0 0 ${chartWidth} ${props.height}`"
            class="mt-3 w-full"
            preserveAspectRatio="none"
        >
            <line
                v-for="tick in lineGeometry.yTicks"
                :key="`grid-${tick.y}`"
                :x1="56"
                :x2="chartWidth - 12"
                :y1="tick.y"
                :y2="tick.y"
                stroke="currentColor"
                stroke-opacity="0.08"
                stroke-width="1"
            />
            <text
                v-for="tick in lineGeometry.yTicks"
                :key="`y-${tick.y}`"
                :x="6"
                :y="tick.y + 3"
                class="fill-current text-[9px] opacity-50"
            >
                {{ tick.label }}
            </text>
            <path
                v-if="lineGeometry.area !== ''"
                :d="lineGeometry.area"
                fill="url(#chart-gradient)"
                fill-opacity="0.25"
            />
            <path
                v-if="lineGeometry.path !== ''"
                :d="lineGeometry.path"
                fill="none"
                stroke="#1f6feb"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
            <circle
                v-for="point in lineGeometry.points"
                :key="`pt-${point.x}-${point.y}`"
                :cx="point.x"
                :cy="point.y"
                r="3"
                fill="#1f6feb"
            />
            <defs>
                <linearGradient id="chart-gradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#1f6feb" stop-opacity="0.4" />
                    <stop offset="100%" stop-color="#1f6feb" stop-opacity="0" />
                </linearGradient>
            </defs>
            <text
                v-for="(point, index) in lineGeometry.points"
                :key="`lx-${index}`"
                :x="point.x"
                :y="props.height - 8"
                class="fill-current text-[9px] opacity-60"
                text-anchor="middle"
            >
                {{ point.label }}
            </text>
        </svg>
        <div v-else class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-center">
            <svg
                :viewBox="`0 0 200 200`"
                class="h-44 w-44 shrink-0"
            >
                <g transform="translate(100, 100)">
                    <template v-for="(slice, index) in pieSlices" :key="`pie-${index}`">
                        <path
                            v-if="pieSlices.length === 1"
                            d="M 0 -80 A 80 80 0 1 1 0 80 L 0 0 Z"
                            :fill="slice.color"
                        />
                        <path
                            v-else
                            :d="((): string => {
                                const startAngle = pieSlices
                                    .slice(0, index)
                                    .reduce((sum, s) => sum + (s.percent * 3.6), 0);
                                const endAngle = startAngle + slice.percent * 3.6;
                                const rad = (deg: number): number => ((deg - 90) * Math.PI) / 180;
                                const x1 = 80 * Math.cos(rad(startAngle));
                                const y1 = 80 * Math.sin(rad(startAngle));
                                const x2 = 80 * Math.cos(rad(endAngle));
                                const y2 = 80 * Math.sin(rad(endAngle));
                                const largeArc = slice.percent > 50 ? 1 : 0;
                                return `M 0 0 L ${x1} ${y1} A 80 80 0 ${largeArc} 1 ${x2} ${y2} Z`;
                            })()"
                            :fill="slice.color"
                        />
                    </template>
                    <circle r="36" fill="rgb(252, 252, 252)" class="dark:hidden" />
                    <circle r="36" fill="rgb(20, 20, 24)" class="hidden dark:inline" />
                </g>
            </svg>
            <ul class="flex-1 space-y-1.5">
                <li
                    v-for="(slice, index) in pieSlices"
                    :key="`legend-${index}`"
                    class="flex items-center justify-between gap-3 text-xs"
                >
                    <span class="flex items-center gap-2">
                        <span
                            class="inline-block h-2.5 w-2.5 rounded-sm"
                            :style="{ backgroundColor: slice.color }"
                        ></span>
                        <span class="text-on-surface">{{ slice.label }}</span>
                    </span>
                    <span class="font-mono text-on-surface-variant">
                        {{ slice.percent.toFixed(1) }} %
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
