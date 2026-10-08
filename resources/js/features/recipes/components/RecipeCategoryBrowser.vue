<script setup lang="ts">
import { BookOpen, Check, ChevronRight, LayoutGrid } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import FilterField from '@/components/ui/FilterField.vue';
import Modal from '@/components/ui/Modal.vue';
import RecipeIllustration from './RecipeIllustration.vue';
import type { RecipeCategory, RecipeSummary } from '../types';

const props = defineProps<{
    categories: RecipeCategory[];
    recipes: RecipeSummary[];
}>();
const category = defineModel<string>({ default: '' });
const { t } = useI18n();
const id = `recipe-category-${useId()}`;
const open = ref(false);
const selected = computed(() =>
    props.categories.find((item) => item.key === category.value),
);
const tiles = computed(() =>
    props.categories.map((item) => ({
        ...item,
        tags:
            props.recipes.find((recipe) => recipe.category.key === item.key)
                ?.tags ?? [],
    })),
);

function select(value: string): void {
    category.value = value;
    open.value = false;
}
</script>

<template>
    <FilterField
        :for="id"
        :label="t('recipes.browse_categories')"
        class="gap-2"
    >
        <Button
            :id="id"
            variant="secondary"
            class="h-12 w-full justify-between rounded-2xl text-sm"
            :aria-label="t('recipes.browse_categories')"
            :aria-describedby="`${id}-selection`"
            aria-haspopup="dialog"
            :aria-expanded="open"
            @click="open = true"
        >
            <LayoutGrid :size="18" class="shrink-0 text-primary" />
            <span
                :id="`${id}-selection`"
                class="min-w-0 flex-1 truncate text-left"
                >{{ selected?.name ?? t('recipes.all_categories') }}</span
            >
            <span class="text-xs text-on-surface-variant">{{
                selected?.recipe_count ?? recipes.length
            }}</span>
            <ChevronRight :size="16" class="shrink-0 text-primary" />
        </Button>
    </FilterField>

    <Modal
        :open="open"
        :title="t('recipes.browse_categories')"
        size="sm"
        class="absolute inset-y-0 right-0 flex h-full flex-col rounded-none border-y-0 border-r-0"
        body-class="min-h-0 flex-1 overflow-y-auto p-4"
        @close="open = false"
    >
        <div class="grid grid-cols-2 gap-3">
            <Button
                variant="secondary"
                class="col-span-2 h-auto min-h-18 justify-start rounded-2xl p-4 text-left text-sm"
                :class="!category ? 'border-primary bg-primary/6' : ''"
                :aria-pressed="!category"
                @click="select('')"
            >
                <BookOpen :size="24" class="shrink-0 text-primary" />
                <span class="flex-1">{{ t('recipes.all_categories') }}</span>
                <span class="text-xs text-on-surface-variant">{{
                    recipes.length
                }}</span>
                <Check v-if="!category" :size="16" class="text-primary" />
            </Button>
            <Button
                v-for="item in tiles"
                :key="item.key"
                variant="secondary"
                class="relative h-auto min-h-32 flex-col gap-1 rounded-2xl p-3 text-center"
                :class="
                    category === item.key ? 'border-primary bg-primary/6' : ''
                "
                :aria-pressed="category === item.key"
                @click="select(item.key)"
            >
                <Check
                    v-if="category === item.key"
                    :size="16"
                    class="absolute top-3 right-3 text-primary"
                />
                <span class="size-14 [&>svg]:h-full [&>svg]:w-full">
                    <RecipeIllustration :tags="item.tags" small />
                </span>
                <span class="text-xs leading-5 font-bold">{{ item.name }}</span>
                <span class="text-xs font-normal text-on-surface-variant">{{
                    t('recipes.category_count', { count: item.recipe_count })
                }}</span>
            </Button>
        </div>
    </Modal>
</template>
