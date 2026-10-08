<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import RecipeInstructionIcon from './RecipeInstructionIcon.vue';
import type { RecipeIngredient } from '../types';

const props = withDefaults(
    defineProps<{ ingredients: RecipeIngredient[]; checklist?: boolean }>(),
    { checklist: false },
);
const checked = defineModel<string[]>({ default: () => [] });
const { t, locale } = useI18n();
const groups = computed(() =>
    [...new Set(props.ingredients.map((ingredient) => ingredient.group))].map(
        (name) => ({
            name,
            ingredients: props.ingredients.filter(
                (ingredient) => ingredient.group === name,
            ),
        }),
    ),
);
function amount(ingredient: RecipeIngredient): string {
    const quantity =
        ingredient.quantity_value === null
            ? (ingredient.quantity_text ?? '')
            : new Intl.NumberFormat(locale.value, {
                  maximumFractionDigits: 3,
              }).format(ingredient.quantity_value);
    const unit =
        ingredient.unit === 'scoops'
            ? t(
                  `recipes.scoop_units.${new Intl.PluralRules(locale.value).select(ingredient.quantity_value ?? 2)}`,
              )
            : ingredient.unit === 'pieces'
              ? t('recipes.pieces', ingredient.quantity_value ?? 2)
              : ingredient.unit;
    return `${quantity}${unit ? ` ${unit}` : ''}`;
}
</script>

<template>
    <div class="space-y-5" data-testid="recipe-ingredients">
        <section v-for="group in groups" :key="group.name">
            <h3
                class="mb-2 text-xs font-bold tracking-wide text-on-surface-variant uppercase"
            >
                {{ group.name }}
            </h3>
            <div class="divide-y divide-outline-glass">
                <label
                    v-for="ingredient in group.ingredients"
                    :key="ingredient.key"
                    class="grid min-h-12 grid-cols-[auto_minmax(0,1fr)_minmax(0,1fr)] items-center gap-3 py-2.5 sm:grid-cols-[auto_minmax(0,1fr)_auto]"
                    :class="checklist ? 'cursor-pointer' : ''"
                >
                    <input
                        v-if="checklist"
                        v-model="checked"
                        type="checkbox"
                        :value="ingredient.key"
                        :aria-label="`${ingredient.name} — ${amount(ingredient)}`"
                        class="size-5 shrink-0 accent-primary"
                    />
                    <span
                        v-else
                        class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-primary/7 text-primary"
                        ><RecipeInstructionIcon
                            type="ingredient"
                            action-key=""
                            :icon-group="ingredient.icon_group"
                    /></span>
                    <span
                        class="min-w-0 text-sm wrap-break-word text-on-surface"
                        :class="
                            checklist && checked.includes(ingredient.key)
                                ? 'text-on-surface-variant line-through'
                                : ''
                        "
                        >{{ ingredient.name }}</span
                    >
                    <strong
                        class="min-w-0 text-right text-base font-bold wrap-break-word text-on-surface"
                        data-testid="ingredient-amount"
                        >{{ amount(ingredient) }}</strong
                    >
                </label>
            </div>
        </section>
    </div>
</template>
