<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Archive, Pencil, RotateCcw } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import BackLink from '@/components/ui/BackLink.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Tabs from '@/components/ui/Tabs.vue';
import RecipeInstructionList, {
    type RecipeInstructionData,
} from '@/features/recipes/components/RecipeInstructionList.vue';
import RecipeToppingAdjustments, {
    type RecipeToppingAdjustmentData,
} from '@/features/recipes/components/RecipeToppingAdjustments.vue';
import { useRoute } from '@/composables/useRoute';

const props = defineProps<{
    is_admin: boolean;
    recipe: {
        id: number;
        name: string;
        note: string | null;
        archived: boolean;
        category: { id: number; name: string };
        variants: Array<{
            id: number;
            name: string | null;
            topping_adjustments: RecipeToppingAdjustmentData | null;
            instructions: RecipeInstructionData[];
        }>;
    };
}>();

const { t } = useI18n();
const route = useRoute();
const selectedVariantId = ref(String(props.recipe.variants[0]?.id ?? ''));
const selectedVariant = computed(
    () =>
        props.recipe.variants.find(
            (variant) => String(variant.id) === selectedVariantId.value,
        ) ?? props.recipe.variants[0],
);
const variantTabs = computed(() =>
    props.recipe.variants.map((variant) => ({
        value: String(variant.id),
        label: variant.name || t('recipes.default_variant'),
    })),
);

function setArchived(archived: boolean): void {
    router.put(route('recipes.archive', props.recipe.id), { archived });
}
</script>

<template>
    <AppLayout :title="recipe.name">
        <div class="space-y-6">
            <header>
                <BackLink :href="route('recipes.index')">{{
                    t('recipes.back')
                }}</BackLink>
                <div
                    class="mt-3 flex flex-wrap items-start justify-between gap-4"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge>{{ recipe.category.name }}</Badge
                            ><Badge v-if="recipe.archived" variant="warning">{{
                                t('recipes.archived')
                            }}</Badge>
                        </div>
                        <h1
                            class="mt-3 font-heading text-2xl font-bold text-on-surface"
                        >
                            {{ recipe.name }}
                        </h1>
                        <p
                            v-if="recipe.note"
                            class="mt-2 max-w-3xl whitespace-pre-line text-sm text-on-surface-variant"
                        >
                            {{ recipe.note }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template v-if="is_admin">
                            <Link :href="route('recipes.edit', recipe.id)"
                                ><Button
                                    ><Pencil :size="15" />{{
                                        t('common.edit')
                                    }}</Button
                                ></Link
                            >
                            <Button
                                v-if="!recipe.archived"
                                variant="secondary"
                                @click="setArchived(true)"
                                ><Archive :size="15" />{{
                                    t('recipes.archive')
                                }}</Button
                            >
                            <Button
                                v-else
                                variant="secondary"
                                @click="setArchived(false)"
                                ><RotateCcw :size="15" />{{
                                    t('recipes.restore')
                                }}</Button
                            >
                        </template>
                    </div>
                </div>
            </header>

            <section v-if="selectedVariant" class="mx-auto w-full max-w-3xl">
                <Tabs
                    v-if="recipe.variants.length > 1"
                    v-model="selectedVariantId"
                    class="mb-3"
                    :items="variantTabs"
                    :label="t('recipes.variant_name')"
                />
                <RecipeToppingAdjustments
                    v-if="selectedVariant.topping_adjustments"
                    :guidance="selectedVariant.topping_adjustments"
                />
                <RecipeInstructionList
                    :instructions="selectedVariant.instructions"
                />
            </section>
        </div>
    </AppLayout>
</template>
