<script setup lang="ts">
import { Info } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';

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

        <DataTable
            v-if="guidance.components.length > 0"
            class="mt-4"
            density="compact"
            variant="nested"
            table-class="min-w-[32rem]"
        >
            <thead>
                <tr>
                    <th>{{ t('recipes.ingredient_name') }}</th>
                    <th>{{ t('recipes.topping_adjustments.base') }}</th>
                    <th>{{ t('recipes.topping_adjustments.two') }}</th>
                    <th>{{ t('recipes.topping_adjustments.three') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="component in guidance.components"
                    :key="component.ingredient_name"
                >
                    <th class="font-medium">
                        {{ component.ingredient_name }}
                    </th>
                    <td>{{ component.base_quantity }} {{ component.unit }}</td>
                    <td>
                        {{ component.two_toppings_quantity }}
                        {{ component.unit }}
                    </td>
                    <td>
                        {{ component.three_toppings_quantity }}
                        {{ component.unit }}
                    </td>
                </tr>
            </tbody>
        </DataTable>
        <p v-else class="mt-4 text-sm font-medium text-amber-950">
            {{ t('recipes.topping_adjustments.none') }}
        </p>
    </Card>
</template>
