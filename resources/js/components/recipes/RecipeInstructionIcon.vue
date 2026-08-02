<script setup lang="ts">
import type { Component } from 'vue';
import {
    Apple,
    Candy,
    CircleHelp,
    Droplets,
    FlaskConical,
    Leaf,
    Milk,
    Snowflake,
    Sparkles,
} from '@lucide/vue';
import RecipeActionIcon from '@/components/recipes/RecipeActionIcon.vue';

const props = defineProps<{
    type: string;
    actionKey: string;
    iconGroup: string;
}>();

const ingredientIcons: Record<string, Component> = {
    water_milk: Droplets,
    tea_matcha: Leaf,
    fruit: Apple,
    syrup_sweetener: Candy,
    powder: FlaskConical,
    milk_foam: Milk,
    ice: Snowflake,
    topping_garnish: Sparkles,
    neutral: CircleHelp,
};
</script>

<template>
    <component
        :is="ingredientIcons[props.iconGroup] ?? CircleHelp"
        v-if="props.type === 'ingredient'"
        :size="15"
        aria-hidden="true"
    />
    <RecipeActionIcon v-else :action-key="props.actionKey" />
</template>
