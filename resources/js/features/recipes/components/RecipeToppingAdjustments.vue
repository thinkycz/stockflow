<script setup lang="ts">
import { Candy } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Tabs from '@/components/ui/Tabs.vue';
import type { RecipeVariant } from '../types';

const props = defineProps<{
    components: RecipeVariant['topping_adjustments'];
}>();
const { t, locale } = useI18n();
const count = ref('base');
const choices = computed(() => [
    { value: 'base', label: '0–1' },
    { value: 'two', label: '2' },
    { value: 'three', label: '3' },
]);
const amounts = computed(() =>
    props.components.map((component) => ({
        ...component,
        quantity:
            count.value === 'two'
                ? component.two_toppings_quantity
                : count.value === 'three'
                  ? component.three_toppings_quantity
                  : component.base_quantity,
    })),
);
const reduction = computed(() =>
    count.value === 'base'
        ? t('recipes.topping_adjustments.unchanged')
        : t('recipes.topping_adjustments.reduction', {
              amount: count.value === 'two' ? 5 : 10,
          }),
);
watch(
    () => props.components,
    () => (count.value = 'base'),
);
function format(value: number): string {
    return `${new Intl.NumberFormat(locale.value).format(value)} ml`;
}
</script>

<template>
    <section
        class="mx-auto max-w-3xl rounded-2xl border border-outline-glass bg-white p-5 sm:p-6"
        data-testid="recipe-topping-adjustments"
    >
        <div class="flex items-start gap-3">
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/8 text-primary"
                ><Candy :size="20" aria-hidden="true"
            /></span>
            <div class="min-w-0">
                <h2 class="font-heading text-base font-bold text-on-surface">
                    {{ t('recipes.topping_adjustments.title') }}
                </h2>
                <p class="mt-1 text-sm leading-5 text-on-surface-variant">
                    {{ t('recipes.topping_adjustments.choose') }}
                </p>
            </div>
        </div>
        <div class="mt-5 grid items-center gap-3 sm:grid-cols-2">
            <div>
                <p class="mb-2 text-xs font-semibold text-on-surface-variant">
                    {{ t('recipes.topping_adjustments.count') }}
                </p>
                <Tabs
                    v-model="count"
                    :items="choices"
                    :label="t('recipes.topping_adjustments.count')"
                    class="grid w-full grid-cols-3"
                />
            </div>
            <p
                class="text-xs font-medium text-primary sm:pt-6 sm:text-right"
                data-testid="recipe-topping-rule"
            >
                {{ reduction }}
            </p>
        </div>
        <div class="mt-4 space-y-2" aria-live="polite" aria-atomic="true">
            <div
                v-for="component in amounts"
                :key="component.ingredient_name"
                class="flex min-h-20 items-center justify-between gap-4 rounded-xl bg-surface-container-low px-4 py-3"
                data-testid="recipe-topping-component"
            >
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-on-surface">
                        {{ component.ingredient_name }}
                    </p>
                    <p class="mt-1 text-xs leading-5 text-on-surface-variant">
                        {{
                            t('recipes.topping_adjustments.original', {
                                amount: format(component.base_quantity),
                            })
                        }}
                    </p>
                </div>
                <div class="shrink-0 text-right">
                    <p
                        class="text-[10px] font-semibold tracking-wide text-primary uppercase"
                    >
                        {{
                            component.quantity === 0
                                ? t('recipes.topping_adjustments.omit')
                                : t('recipes.topping_adjustments.use')
                        }}
                    </p>
                    <p
                        class="mt-0.5 font-heading text-2xl font-bold text-primary tabular-nums"
                        data-testid="recipe-topping-amount"
                    >
                        {{ format(component.quantity) }}
                    </p>
                </div>
            </div>
        </div>
        <p class="mt-4 text-xs leading-5 text-on-surface-variant">
            {{ t('recipes.topping_adjustments.informational') }}
        </p>
    </section>
</template>
