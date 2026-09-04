import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from '@/composables/useRoute';

type RecipeRow = {
    id: number;
    name: string;
    category: { id: number; name: string };
    archived: boolean;
    variant_count: number;
};
export type RecipeCatalogProps = {
    is_admin: boolean;
    categories: Array<{
        id: number;
        name: string;
        recipes_count: number;
        active_recipes_count: number;
    }>;
    recipes: {
        data: RecipeRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: { search: string; category_id: number | null; archived: boolean };
    workers: Array<{ id: number; name: string }>;
    testable_recipe_count: number;
};

export function useRecipeCatalog(props: RecipeCatalogProps) {
    const { t } = useI18n();

    const route = useRoute();

    const filters = reactive({
        search: props.filters.search,
        category_id: props.filters.category_id
            ? String(props.filters.category_id)
            : '',
        archived: props.filters.archived,
    });

    const testModalOpen = ref(false);

    const workerId = ref('');

    const starting = ref(false);

    const canStartTest = computed(
        () => props.workers.length > 0 && props.testable_recipe_count >= 3,
    );

    const groupedRecipes = computed(() =>
        props.categories
            .map((category) => ({
                ...category,
                recipes: props.recipes.data.filter(
                    (recipe) => recipe.category.id === category.id,
                ),
            }))
            .filter((category) => category.recipes.length > 0),
    );

    function applyFilters(): void {
        router.get(
            route('recipes.index'),
            {
                search: filters.search || undefined,
                category_id: filters.category_id || undefined,
                archived: filters.archived || undefined,
            },
            { preserveState: true },
        );
    }

    function setArchived(recipe: RecipeRow, archived: boolean): void {
        router.put(
            route('recipes.archive', recipe.id),
            { archived },
            { preserveScroll: true },
        );
    }

    function moveRecipe(id: number, direction: 'up' | 'down'): void {
        router.put(
            route('recipes.position', id),
            { direction },
            { preserveScroll: true },
        );
    }

    function startTest(): void {
        if (!workerId.value || !canStartTest.value) return;
        starting.value = true;
        router.post(
            route('recipe-test-sessions.store'),
            { worker_id: Number(workerId.value) },
            { onFinish: () => (starting.value = false) },
        );
    }
    return {
        t,
        route,
        filters,
        testModalOpen,
        workerId,
        starting,
        canStartTest,
        groupedRecipes,
        applyFilters,
        setArchived,
        moveRecipe,
        startTest,
    };
}
