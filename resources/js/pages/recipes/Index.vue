<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, BookOpen, ListFilter } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import RecipeCategoryBrowser from '@/features/recipes/components/RecipeCategoryBrowser.vue';
import RecipeQuickLookup from '@/features/recipes/components/RecipeQuickLookup.vue';
import RecipeIllustration from '@/features/recipes/components/RecipeIllustration.vue';
import { searchRecipes } from '@/features/recipes/search';
import type {
    RecipeCategory,
    RecipeLookupEntry,
    RecipeSummary,
} from '@/features/recipes/types';

const props = defineProps<{
    categories: RecipeCategory[];
    lookup: RecipeLookupEntry[];
    recipes: RecipeSummary[];
    filters: { search: string; category: string };
}>();
const { t } = useI18n();
const query = ref(props.filters.search);
const category = ref(
    props.categories.some((item) => item.key === props.filters.category)
        ? props.filters.category
        : '',
);
const matching = computed(
    () =>
        new Set(
            searchRecipes(props.lookup, query.value).map((entry) => entry.key),
        ),
);
const groups = computed(() =>
    props.categories
        .filter((item) => !category.value || item.key === category.value)
        .map((item) => ({
            ...item,
            recipes: props.recipes.filter(
                (recipe) =>
                    recipe.category.key === item.key &&
                    matching.value.has(recipe.key),
            ),
        }))
        .filter((item) => item.recipes.length),
);
const count = computed(() =>
    groups.value.reduce((total, group) => total + group.recipes.length, 0),
);
watch([query, category], () => {
    const url = new URL(window.location.href);
    for (const [key, value] of [
        ['search', query.value],
        ['category', category.value],
    ]) {
        if (value) url.searchParams.set(key!, value);
        else url.searchParams.delete(key!);
    }
    window.history.replaceState(window.history.state, '', url);
});
</script>

<template>
    <AppLayout :title="t('recipes.title')">
        <div class="mx-auto max-w-6xl space-y-7">
            <header class="flex items-start justify-between gap-4">
                <div>
                    <div
                        class="mb-2 flex items-center gap-2 text-xs font-semibold tracking-wide text-primary uppercase"
                    >
                        <BookOpen :size="15" />{{ t('recipes.library_label') }}
                    </div>
                    <h1 class="font-heading text-3xl font-bold text-on-surface">
                        {{ t('recipes.title') }}
                    </h1>
                    <p
                        class="mt-2 max-w-xl text-sm leading-6 text-on-surface-variant"
                    >
                        {{ t('recipes.subtitle') }}
                    </p>
                </div>
                <span
                    class="hidden rounded-2xl border border-outline-glass bg-white px-4 py-3 text-center sm:block"
                    ><strong class="block text-2xl font-bold text-primary">{{
                        recipes.length
                    }}</strong
                    ><span class="text-xs text-on-surface-variant">{{
                        t('recipes.count_label')
                    }}</span></span
                >
            </header>

            <section
                class="grid gap-4 rounded-2xl border border-outline-glass bg-primary/4 p-4 sm:p-5 md:grid-cols-[minmax(0,1fr)_18rem]"
            >
                <RecipeQuickLookup v-model="query" :entries="lookup" />
                <RecipeCategoryBrowser
                    v-model="category"
                    :categories="categories"
                    :recipes="recipes"
                />
            </section>

            <div
                class="flex items-center gap-2 text-xs text-on-surface-variant"
                role="status"
                aria-live="polite"
            >
                <ListFilter :size="14" />{{ t('recipes.showing', { count }) }}
            </div>
            <section v-for="group in groups" :key="group.key" class="space-y-3">
                <div class="flex items-center gap-3">
                    <h2
                        class="text-xs font-bold tracking-[0.12em] text-on-surface-variant"
                    >
                        {{ group.name }}
                    </h2>
                    <span class="h-px flex-1 bg-outline-glass" /><span
                        class="text-xs text-on-surface-variant"
                        >{{ group.recipes.length }}</span
                    >
                </div>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="recipe in group.recipes"
                        :key="recipe.key"
                        :href="recipe.url"
                        class="group flex items-center gap-3 rounded-2xl border border-outline-glass bg-white p-3 transition hover:border-primary/40 hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                        data-testid="recipe-catalog-row"
                    >
                        <RecipeIllustration
                            :tags="recipe.tags"
                            small
                            class="shrink-0"
                        />
                        <div class="min-w-0 flex-1">
                            <h3
                                class="text-sm leading-5 font-bold text-on-surface group-hover:text-primary"
                            >
                                {{ recipe.name }}
                            </h3>
                            <p
                                class="mt-1.5 line-clamp-2 text-xs leading-5 text-on-surface-variant"
                            >
                                {{ recipe.summary }}
                            </p>
                            <span
                                class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-primary"
                                >{{
                                    t(
                                        recipe.variant_count > 1
                                            ? 'recipes.variants_count'
                                            : 'recipes.view_recipe',
                                        { count: recipe.variant_count },
                                    )
                                }}<ArrowUpRight :size="13"
                            /></span>
                        </div>
                    </Link>
                </div>
            </section>
            <EmptyState
                v-if="!count"
                :title="t('recipes.no_matches')"
                :description="t('recipes.empty_help')"
            />
        </div>
    </AppLayout>
</template>
