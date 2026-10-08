<script setup lang="ts">
import { Info, ChevronDown } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import DataTable from '@/components/ui/DataTable.vue';
import type { RecipeVariant } from '../types';
defineProps<{ components: RecipeVariant['topping_adjustments'] }>();
const { t } = useI18n();
</script>

<template>
    <details
        class="group overflow-hidden rounded-2xl border border-amber-200 bg-amber-50/70"
        data-testid="recipe-topping-adjustments"
    >
        <summary
            class="flex min-h-14 cursor-pointer list-none items-center gap-3 px-5 py-4 text-sm font-semibold text-amber-950"
        >
            <Info :size="18" class="shrink-0" /><span class="flex-1">{{
                t('recipes.topping_adjustments.title')
            }}</span
            ><ChevronDown :size="16" class="transition group-open:rotate-180" />
        </summary>
        <div class="space-y-4 border-t border-amber-200 px-5 py-4">
            <p class="text-sm leading-6 text-amber-950">
                {{ t('recipes.topping_adjustments.rule') }}
            </p>
            <p class="text-xs leading-5 text-amber-900">
                {{ t('recipes.topping_adjustments.informational') }}
            </p>
            <DataTable
                density="compact"
                variant="nested"
                class="text-amber-950"
            >
                <thead>
                    <tr
                        class="border-b border-amber-200 text-xs text-amber-900"
                    >
                        <th class="py-3 pr-3">
                            {{ t('recipes.ingredient') }}
                        </th>
                        <th class="px-2 py-3">
                            {{ t('recipes.topping_adjustments.base') }}
                        </th>
                        <th class="px-2 py-3">
                            {{ t('recipes.topping_adjustments.two') }}
                        </th>
                        <th class="py-3 pl-2">
                            {{ t('recipes.topping_adjustments.three') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="component in components"
                        :key="component.ingredient_name"
                        class="border-b border-amber-200/60 last:border-0"
                        data-testid="recipe-topping-component"
                    >
                        <th class="py-3 pr-3 font-medium text-amber-950">
                            {{ component.ingredient_name }}
                        </th>
                        <td class="px-2 py-3 whitespace-nowrap">
                            {{ component.base_quantity }} ml
                        </td>
                        <td class="px-2 py-3 whitespace-nowrap">
                            {{ component.two_toppings_quantity }} ml
                        </td>
                        <td class="py-3 pl-2 whitespace-nowrap">
                            {{ component.three_toppings_quantity }} ml
                        </td>
                    </tr>
                </tbody>
            </DataTable>
        </div>
    </details>
</template>
