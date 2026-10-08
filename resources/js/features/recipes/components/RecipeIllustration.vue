<script setup lang="ts">
import { computed, useId } from 'vue';
const props = defineProps<{ tags: string[]; small?: boolean }>();
const id = useId();
const base = computed(() =>
    props.tags.includes('matcha')
        ? '#80a477'
        : props.tags.includes('taro')
          ? '#b69bc7'
          : props.tags.includes('strawberry')
            ? '#dc929a'
            : props.tags.includes('mango')
              ? '#dfb35c'
              : props.tags.includes('tea')
                ? '#b58968'
                : '#c8ba9d',
);
</script>

<template>
    <svg
        viewBox="0 0 160 150"
        fill="none"
        aria-hidden="true"
        :class="small ? 'h-24 w-24' : 'h-44 w-44 sm:h-52 sm:w-52'"
    >
        <defs>
            <linearGradient
                :id="id"
                x1="45"
                y1="50"
                x2="116"
                y2="132"
                gradientUnits="userSpaceOnUse"
            >
                <stop :stop-color="base" />
                <stop offset="1" :stop-color="base" stop-opacity="0.75" />
            </linearGradient>
        </defs>
        <circle cx="80" cy="78" r="66" :fill="base" fill-opacity="0.08" />
        <ellipse
            cx="81"
            cy="135"
            rx="39"
            ry="5"
            fill="#15221d"
            fill-opacity="0.07"
        />
        <template v-if="tags.includes('batch')">
            <path
                d="M39 56H121L114 122Q80 138 46 122Z"
                fill="#e8ede5"
                stroke="#638273"
                stroke-width="2.5"
            />
            <path d="M51 75H111L107 114Q80 125 55 114Z" :fill="`url(#${id})`" />
            <path
                d="M36 54H124M61 45H99"
                stroke="#638273"
                stroke-width="4"
                stroke-linecap="round"
            />
        </template>
        <template v-else>
            <path
                d="M46 47H116L109 123Q80 138 53 123Z"
                fill="white"
                stroke="#638273"
                stroke-opacity="0.5"
                stroke-width="2"
            />
            <path d="M51 75H111L107 121Q80 131 57 121Z" :fill="`url(#${id})`" />
            <path d="M51 60H111L110 77H52Z" fill="#f2e9db" />
            <ellipse cx="81" cy="60" rx="30" ry="5" fill="#f9f3e8" />
            <path
                v-if="tags.includes('hot')"
                d="M65 36Q58 27 66 18M82 36Q75 27 83 18M99 36Q92 27 100 18"
                stroke="#638273"
                stroke-width="2"
                stroke-linecap="round"
            />
            <path
                v-else
                d="M96 16L85 66"
                stroke="#638273"
                stroke-width="4"
                stroke-linecap="round"
            />
        </template>
        <path
            d="M123 91Q133 78 142 79Q139 92 123 91Z"
            :fill="base"
            fill-opacity="0.6"
        />
        <circle cx="29" cy="60" r="3" :fill="base" fill-opacity="0.5" />
        <circle cx="127" cy="36" r="2" :fill="base" fill-opacity="0.5" />
    </svg>
</template>
