<script setup lang="ts">
import type { Component } from 'vue';
import {
    Apple,
    Blend,
    Candy,
    ChefHat,
    CircleHelp,
    CookingPot,
    Droplets,
    FlaskConical,
    HandPlatter,
    Leaf,
    Milk,
    Snowflake,
    Sparkles,
    Timer,
    Utensils,
    Waves,
} from '@lucide/vue';
import { useI18n } from 'vue-i18n';

export type RecipeIngredientData = {
    id?: number;
    quantity_value: number | string | null;
    quantity_text: string | null;
    unit: string | null;
    name: string;
    icon_group: string;
    source_text?: string | null;
};

export type RecipeProcedureStepData = {
    id?: number;
    text: string;
    action_key: string;
    source_text?: string | null;
};

export type RecipeVariantData = {
    id?: number;
    name: string | null;
    ingredients: RecipeIngredientData[];
    steps: RecipeProcedureStepData[];
};

const props = withDefaults(
    defineProps<{
        variant: RecipeVariantData;
        isAdmin?: boolean;
        showProcedure?: boolean;
    }>(),
    { isAdmin: false, showProcedure: true },
);

const { t } = useI18n();

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

const actionIcons: Record<string, Component> = {
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
};

function iconFor(group: string, map: Record<string, Component>): Component {
    return map[group] ?? CircleHelp;
}

function quantityLabel(ingredient: RecipeIngredientData): string {
    if (ingredient.quantity_text) return ingredient.quantity_text;
    if (
        ingredient.quantity_value === null ||
        ingredient.quantity_value === ''
    ) {
        return '';
    }

    return String(ingredient.quantity_value);
}

function displaySource(ingredient: RecipeIngredientData): boolean {
    const quantity = quantityLabel(ingredient);
    const normalized = [quantity, ingredient.unit, ingredient.name]
        .filter(Boolean)
        .join(' ');

    return Boolean(
        props.isAdmin &&
        ingredient.source_text &&
        ingredient.source_text !== normalized,
    );
}
</script>

<template>
    <section
        class="rounded-2xl border border-outline-glass bg-surface-container-low/50 p-4"
        data-testid="recipe-variant"
    >
        <h3 class="font-heading text-base font-bold text-on-surface">
            {{ variant.name || t('recipes.default_variant') }}
        </h3>

        <div
            class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]"
        >
            <div>
                <h4
                    class="text-[11px] font-bold tracking-[0.16em] text-on-surface-variant uppercase"
                >
                    {{ t('recipes.ingredients') }}
                </h4>
                <ul v-if="variant.ingredients.length" class="mt-2 space-y-2">
                    <li
                        v-for="(ingredient, index) in variant.ingredients"
                        :key="ingredient.id ?? `ingredient-${index}`"
                        class="flex items-center gap-3 rounded-xl bg-white px-3 py-2.5"
                        data-testid="recipe-ingredient"
                    >
                        <span
                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                        >
                            <component
                                :is="
                                    iconFor(
                                        ingredient.icon_group,
                                        ingredientIcons,
                                    )
                                "
                                :size="16"
                                aria-hidden="true"
                            />
                        </span>
                        <span class="min-w-0 flex-1 text-sm text-on-surface">
                            <span class="font-semibold">
                                <template v-if="quantityLabel(ingredient)">
                                    {{ quantityLabel(ingredient) }}
                                    <span v-if="ingredient.unit" class="ml-1">{{
                                        ingredient.unit
                                    }}</span>
                                </template>
                            </span>
                            <span
                                :class="quantityLabel(ingredient) ? 'ml-2' : ''"
                            >
                                {{ ingredient.name }}
                            </span>
                            <details
                                v-if="displaySource(ingredient)"
                                class="mt-1 text-[11px] text-on-surface-variant"
                            >
                                <summary class="cursor-pointer">
                                    {{ t('recipes.source_wording') }}
                                </summary>
                                <span>{{ ingredient.source_text }}</span>
                            </details>
                        </span>
                    </li>
                </ul>
                <p v-else class="mt-2 text-xs text-on-surface-variant">
                    {{ t('recipes.no_ingredients') }}
                </p>
            </div>

            <div v-if="showProcedure">
                <h4
                    class="text-[11px] font-bold tracking-[0.16em] text-on-surface-variant uppercase"
                >
                    {{ t('recipes.procedure') }}
                </h4>
                <ol class="mt-2 space-y-2">
                    <li
                        v-for="(step, index) in variant.steps"
                        :key="step.id ?? `step-${index}`"
                        class="flex items-start gap-3 rounded-xl border border-outline-glass bg-white px-3 py-2.5"
                        data-testid="recipe-procedure-step"
                    >
                        <span
                            class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-white"
                        >
                            {{ index + 1 }}
                        </span>
                        <span
                            class="flex size-7 shrink-0 items-center justify-center rounded-full bg-surface-container-low text-primary"
                        >
                            <component
                                :is="iconFor(step.action_key, actionIcons)"
                                :size="15"
                                aria-hidden="true"
                            />
                        </span>
                        <span
                            class="min-w-0 flex-1 pt-1 text-sm text-on-surface"
                        >
                            {{ step.text }}
                            <details
                                v-if="
                                    isAdmin &&
                                    step.source_text &&
                                    step.source_text !== step.text
                                "
                                class="mt-1 text-[11px] text-on-surface-variant"
                            >
                                <summary class="cursor-pointer">
                                    {{ t('recipes.source_wording') }}
                                </summary>
                                <span>{{ step.source_text }}</span>
                            </details>
                        </span>
                    </li>
                </ol>
            </div>
        </div>
    </section>
</template>
