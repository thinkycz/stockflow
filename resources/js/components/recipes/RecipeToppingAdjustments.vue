<script setup lang="ts">
import { Info } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import Card from '@/components/ui/Card.vue';

export type RecipeToppingAdjustmentData = {
    base_toppings: '0–1';
    two_toppings_reduction_ml: 5;
    three_toppings_reduction_ml: 10;
    components: Array<{
        ingredient_name: string;
        unit: 'ml';
        base_quantity: number;
        two_toppings_quantity: number;
        three_toppings_quantity: number;
    }>;
};

defineProps<{ guidance: RecipeToppingAdjustmentData }>();

const { t } = useI18n();
</script>

<template>
    <Card
        class="mb-4 border-amber-300 bg-amber-50/80"
        data-testid="recipe-topping-adjustments"
    >
        <div class="flex items-start gap-3">
            <span
                class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800"
            >
                <Info :size="19" />
            </span>
            <div class="min-w-0 flex-1">
                <h2 class="font-heading text-base font-bold text-amber-950">
                    {{ t('recipes.topping_adjustments.title') }}
                </h2>
                <p class="mt-1 text-sm font-medium text-amber-900">
                    {{ t('recipes.topping_adjustments.informational') }}
                </p>
                <p class="mt-2 text-sm leading-6 text-amber-950/80">
                    {{ t('recipes.topping_adjustments.rule') }}
                </p>
            </div>
        </div>

        <div v-if="guidance.components.length > 0" class="mt-4 space-y-3">
            <section
                v-for="component in guidance.components"
                :key="component.ingredient_name"
                class="overflow-hidden rounded-xl border border-amber-200 bg-white/80"
                data-testid="recipe-topping-component"
            >
                <h3
                    class="border-b border-amber-200 px-3 py-2 text-sm font-semibold text-amber-950"
                >
                    {{ component.ingredient_name }}
                </h3>
                <div class="grid grid-cols-3 divide-x divide-amber-200">
                    <div class="px-2 py-3 text-center sm:px-3">
                        <span class="block text-xs font-medium text-amber-800">
                            {{ t('recipes.topping_adjustments.base') }}
                        </span>
                        <strong
                            class="mt-1 block text-base font-bold text-amber-950"
                        >
                            {{ component.base_quantity }} {{ component.unit }}
                        </strong>
                    </div>
                    <div class="px-2 py-3 text-center sm:px-3">
                        <span class="block text-xs font-medium text-amber-800">
                            {{ t('recipes.topping_adjustments.two') }}
                        </span>
                        <strong
                            class="mt-1 block text-base font-bold text-amber-950"
                        >
                            {{ component.two_toppings_quantity }}
                            {{ component.unit }}
                        </strong>
                    </div>
                    <div class="px-2 py-3 text-center sm:px-3">
                        <span class="block text-xs font-medium text-amber-800">
                            {{ t('recipes.topping_adjustments.three') }}
                        </span>
                        <strong
                            class="mt-1 block text-base font-bold text-amber-950"
                        >
                            {{ component.three_toppings_quantity }}
                            {{ component.unit }}
                        </strong>
                    </div>
                </div>
            </section>
        </div>
        <p v-else class="mt-4 text-sm font-medium text-amber-950">
            {{ t('recipes.topping_adjustments.none') }}
        </p>
    </Card>
</template>
