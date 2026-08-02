<script setup lang="ts">
import RecipeInstructionIcon from '@/components/recipes/RecipeInstructionIcon.vue';

export type RecipeInstructionData = {
    id?: number;
    type: 'ingredient' | 'action';
    text: string;
    action_key: string;
    quantity_value?: number | string | null;
    quantity_text?: string | null;
    unit?: string | null;
    ingredient_name?: string | null;
    target?: string | null;
    icon_group: string;
};

defineProps<{ instructions: RecipeInstructionData[] }>();
</script>

<template>
    <ol
        class="divide-y divide-outline-glass overflow-hidden rounded-xl border border-outline-glass bg-white"
        data-testid="recipe-instruction-list"
    >
        <li
            v-for="(instruction, index) in instructions"
            :key="instruction.id ?? `${instruction.text}-${index}`"
            class="flex items-center gap-2.5 px-3 py-2 text-sm text-on-surface"
            data-testid="recipe-instruction"
        >
            <span
                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-white"
            >
                {{ index + 1 }}
            </span>
            <span
                class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/8 text-primary"
            >
                <RecipeInstructionIcon
                    :type="instruction.type"
                    :action-key="instruction.action_key"
                    :icon-group="instruction.icon_group"
                />
            </span>
            <span class="min-w-0 flex-1 leading-5">{{ instruction.text }}</span>
        </li>
    </ol>
</template>
