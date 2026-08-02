<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Archive,
    ArrowDown,
    ArrowUp,
    ClipboardCheck,
    FolderCog,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Trophy,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import DropdownMenu from '@/components/ui/DropdownMenu.vue';
import DropdownMenuItem from '@/components/ui/DropdownMenuItem.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Select from '@/components/ui/Select.vue';
import { useRoute } from '@/composables/useRoute';

type RecipeRow = {
    id: number;
    name: string;
    category: { id: number; name: string };
    archived: boolean;
    variant_count: number;
};

const props = defineProps<{
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
}>();

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
</script>

<template>
    <AppLayout :title="t('recipes.title')">
        <div class="mx-auto max-w-5xl space-y-5">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="font-heading text-2xl font-bold text-on-surface">
                        {{ t('recipes.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('recipes.subtitle') }}
                    </p>
                </div>
                <div v-if="is_admin" class="flex flex-wrap gap-2">
                    <Link :href="route('recipe-categories.index')">
                        <Button variant="ghost" size="compact">
                            <FolderCog :size="15" />{{
                                t('recipes.categories.manage')
                            }}
                        </Button>
                    </Link>
                    <Link :href="route('recipe-test-results.index')">
                        <Button variant="secondary" size="compact">
                            <Trophy :size="15" />{{
                                t('recipes.results.title')
                            }}
                        </Button>
                    </Link>
                    <Link :href="route('recipes.create')">
                        <Button size="compact">
                            <Plus :size="15" />{{ t('recipes.create') }}
                        </Button>
                    </Link>
                </div>
                <div v-else class="flex flex-col items-end gap-1">
                    <Button
                        :disabled="!canStartTest"
                        size="compact"
                        @click="testModalOpen = true"
                    >
                        <ClipboardCheck :size="16" />{{
                            t('recipes.test.start')
                        }}
                    </Button>
                    <p
                        v-if="testable_recipe_count < 3"
                        class="max-w-64 text-right text-xs text-on-surface-variant"
                    >
                        {{ t('recipes.test.not_enough_recipes') }}
                    </p>
                </div>
            </header>

            <form
                class="flex flex-col gap-2 sm:flex-row"
                @submit.prevent="applyFilters"
            >
                <div class="relative min-w-0 flex-1">
                    <Search
                        class="absolute top-1/2 left-3 -translate-y-1/2 text-on-surface-variant"
                        :size="15"
                    />
                    <Input
                        v-model="filters.search"
                        class="pl-9"
                        :placeholder="t('recipes.search_placeholder')"
                        :aria-label="t('common.search')"
                    />
                </div>
                <Select
                    v-model="filters.category_id"
                    class="sm:w-56"
                    :aria-label="t('recipes.category')"
                    :options="[
                        { value: '', label: t('recipes.all_categories') },
                        ...categories.map((category) => ({
                            value: String(category.id),
                            label: category.name,
                        })),
                    ]"
                />
                <Button type="submit" variant="secondary" size="compact">
                    {{ t('common.apply') }}
                </Button>
                <Button
                    v-if="is_admin"
                    type="button"
                    :variant="filters.archived ? 'primary' : 'ghost'"
                    size="compact"
                    @click="
                        filters.archived = !filters.archived;
                        applyFilters();
                    "
                >
                    <Archive :size="14" />{{
                        filters.archived
                            ? t('recipes.archived')
                            : t('recipes.active')
                    }}
                </Button>
            </form>

            <template v-if="recipes.data.length">
                <section
                    v-for="category in groupedRecipes"
                    :key="category.id"
                    class="space-y-1"
                >
                    <h2
                        class="px-2 pt-3 text-xs font-bold tracking-[0.14em] text-on-surface-variant uppercase"
                    >
                        {{ category.name }}
                    </h2>
                    <ul
                        class="divide-y divide-outline-glass overflow-visible rounded-xl border border-outline-glass bg-white"
                    >
                        <li
                            v-for="recipe in category.recipes"
                            :key="recipe.id"
                            class="flex min-h-11 items-center gap-2 px-3"
                            data-testid="recipe-catalog-row"
                        >
                            <Link
                                :href="route('recipes.show', recipe.id)"
                                class="min-w-0 flex-1 py-2.5 text-sm font-semibold text-on-surface hover:text-primary"
                            >
                                {{ recipe.name }}
                            </Link>
                            <Badge v-if="recipe.archived" variant="warning">
                                {{ t('recipes.archived') }}
                            </Badge>
                            <DropdownMenu
                                v-if="is_admin"
                                :label="t('recipes.row_actions')"
                            >
                                <DropdownMenuItem
                                    :href="route('recipes.edit', recipe.id)"
                                >
                                    <Pencil :size="16" />{{ t('common.edit') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    @select="moveRecipe(recipe.id, 'up')"
                                >
                                    <ArrowUp :size="16" />{{
                                        t('common.move_up')
                                    }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    @select="moveRecipe(recipe.id, 'down')"
                                >
                                    <ArrowDown :size="16" />{{
                                        t('common.move_down')
                                    }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    :tone="
                                        recipe.archived ? 'default' : 'danger'
                                    "
                                    @select="
                                        setArchived(recipe, !recipe.archived)
                                    "
                                >
                                    <RotateCcw
                                        v-if="recipe.archived"
                                        :size="16"
                                    />
                                    <Archive v-else :size="16" />
                                    {{
                                        recipe.archived
                                            ? t('recipes.restore')
                                            : t('recipes.archive')
                                    }}
                                </DropdownMenuItem>
                            </DropdownMenu>
                        </li>
                    </ul>
                </section>
            </template>
            <EmptyState
                v-else
                :title="t('recipes.empty')"
                :description="t('recipes.empty_help')"
            />

            <Pagination
                v-if="recipes.last_page > 1"
                :current-page="recipes.current_page"
                :last-page="recipes.last_page"
                :total="recipes.total"
                :per-page="recipes.per_page"
                :base-url="route('recipes.index')"
                :query-params="{
                    search: filters.search || undefined,
                    category_id: filters.category_id || undefined,
                    archived: filters.archived ? 1 : undefined,
                }"
            />
        </div>

        <Modal
            :open="testModalOpen"
            :title="t('recipes.test.choose_worker')"
            @close="testModalOpen = false"
        >
            <Label for="test-worker" required>{{
                t('recipes.test.worker')
            }}</Label>
            <Select
                id="test-worker"
                v-model="workerId"
                class="mt-1"
                :options="
                    workers.map((worker) => ({
                        value: String(worker.id),
                        label: worker.name,
                    }))
                "
                :placeholder="t('recipes.test.choose_worker_placeholder')"
            />
            <p class="mt-3 text-xs text-on-surface-variant">
                {{ t('recipes.test.session_explanation') }}
            </p>
            <template #footer>
                <Button variant="secondary" @click="testModalOpen = false">{{
                    t('common.cancel')
                }}</Button>
                <Button :disabled="!workerId || starting" @click="startTest">{{
                    starting ? t('common.saving') : t('recipes.test.start')
                }}</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
