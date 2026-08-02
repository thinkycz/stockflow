<script setup lang="ts">
import {
    Apple,
    Blend,
    ChefHat,
    CircleHelp,
    CookingPot,
    Droplets,
    HandPlatter,
    Leaf,
    Milk,
    Snowflake,
    Sparkles,
    Timer,
    Utensils,
    Waves,
} from '@lucide/vue';

withDefaults(
    defineProps<{
        actionKey: string;
        size?: number;
    }>(),
    { size: 15 },
);

const icons = {
    add: HandPlatter,
    mix: Blend,
    stir: Utensils,
    whisk: Waves,
    whip: Milk,
    boil: CookingPot,
    steep: Leaf,
    ice: Snowflake,
    shake: Blend,
    pour: Droplets,
    smash: Apple,
    cook: CookingPot,
    cover: ChefHat,
    timer: Timer,
    cool: Snowflake,
    garnish: Sparkles,
    serve: HandPlatter,
    wash: Droplets,
    other: CircleHelp,
} as const;
</script>

<template>
    <component
        :is="icons[actionKey as keyof typeof icons] ?? CircleHelp"
        :size="size"
        aria-hidden="true"
    />
</template>
