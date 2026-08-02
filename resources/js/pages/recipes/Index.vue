<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Archive,
    ArrowDown,
    ArrowUp,
    BookOpen,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Trash2,
    Trophy,
} from '@lucide/vue';
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Select from '@/components/ui/Select.vue';
import { useRoute } from '@/composables/useRoute';
import { useDialog } from '@/composables/useDialog';

type RecipeRow = {
    id: number;
    name: string;
    note: string | null;
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
}>();

const { t } = useI18n();
const route = useRoute();
const dialog = useDialog();
const filters = reactive({
    search: props.filters.search,
    category_id: props.filters.category_id
        ? String(props.filters.category_id)
        : '',
    archived: props.filters.archived,
});

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

async function createCategory(): Promise<void> {
    const name = await dialog.prompt({
        title: t('recipes.categories.create'),
        message: t('recipes.categories.help'),
        label: t('recipes.category'),
        required: true,
        maxLength: 120,
    });
    if (name) router.post(route('recipe-categories.store'), { name });
}

async function editCategory(category: {
    id: number;
    name: string;
}): Promise<void> {
    const name = await dialog.prompt({
        title: t('recipes.categories.edit'),
        message: t('recipes.categories.help'),
        label: t('recipes.category'),
        defaultValue: category.name,
        required: true,
        maxLength: 120,
    });
    if (name)
        router.put(route('recipe-categories.update', category.id), { name });
}

async function deleteCategory(category: {
    id: number;
    name: string;
    recipes_count: number;
}): Promise<void> {
    if (category.recipes_count > 0) return;
    const confirmed = await dialog.confirm({
        title: t('recipes.categories.delete'),
        message: t('recipes.categories.delete_help', { name: category.name }),
        confirmLabel: t('common.delete'),
        variant: 'danger',
    });
    if (confirmed)
        router.delete(route('recipe-categories.destroy', category.id));
}

function moveCategory(id: number, direction: 'up' | 'down'): void {
    router.put(
        route('recipe-categories.position', id),
        { direction },
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
</script>

<template>
    <AppLayout :title="t('recipes.title')">
        <div class="space-y-6">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="font-heading text-2xl font-bold text-on-surface">
                        {{ t('recipes.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('recipes.subtitle') }}
                    </p>
                </div>
                <div v-if="is_admin" class="flex flex-wrap gap-2">
                    <Link :href="route('recipe-test-results.index')"
                        ><Button variant="secondary"
                            ><Trophy :size="15" />{{
                                t('recipes.results.title')
                            }}</Button
                        ></Link
                    >
                    <Link :href="route('recipes.create')"
                        ><Button
                            ><Plus :size="15" />{{
                                t('recipes.create')
                            }}</Button
                        ></Link
                    >
                </div>
            </header>

            <Card padded>
                <form
                    class="grid gap-4 md:grid-cols-[minmax(0,1fr)_240px_auto] md:items-end"
                    @submit.prevent="applyFilters"
                >
                    <div>
                        <Label for="recipe-search">{{
                            t('common.search')
                        }}</Label>
                        <div class="relative mt-1">
                            <Search
                                class="absolute top-1/2 left-3 -translate-y-1/2 text-on-surface-variant"
                                :size="15"
                            />
                            <Input
                                id="recipe-search"
                                v-model="filters.search"
                                class="pl-9"
                                :placeholder="t('recipes.search_placeholder')"
                            />
                        </div>
                    </div>
                    <div>
                        <Label for="recipe-category">{{
                            t('recipes.category')
                        }}</Label>
                        <Select
                            id="recipe-category"
                            v-model="filters.category_id"
                            class="mt-1"
                            :options="[
                                {
                                    value: '',
                                    label: t('recipes.all_categories'),
                                },
                                ...categories.map((category) => ({
                                    value: String(category.id),
                                    label: `${category.name} (${category.active_recipes_count})`,
                                })),
                            ]"
                        />
                    </div>
                    <Button type="submit">{{ t('common.apply') }}</Button>
                </form>
                <div
                    v-if="is_admin"
                    class="mt-4 flex gap-2 border-t border-outline-glass pt-4"
                >
                    <Button
                        :variant="!filters.archived ? 'primary' : 'secondary'"
                        size="compact"
                        @click="
                            filters.archived = false;
                            applyFilters();
                        "
                        >{{ t('recipes.active') }}</Button
                    >
                    <Button
                        :variant="filters.archived ? 'primary' : 'secondary'"
                        size="compact"
                        @click="
                            filters.archived = true;
                            applyFilters();
                        "
                        ><Archive :size="14" />{{
                            t('recipes.archived')
                        }}</Button
                    >
                </div>
            </Card>

            <Card v-if="is_admin">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2
                            class="font-heading text-lg font-bold text-on-surface"
                        >
                            {{ t('recipes.categories.title') }}
                        </h2>
                        <p class="mt-1 text-xs text-on-surface-variant">
                            {{ t('recipes.categories.help') }}
                        </p>
                    </div>
                    <Button
                        variant="secondary"
                        size="compact"
                        @click="createCategory"
                        ><Plus :size="14" />{{
                            t('recipes.categories.create')
                        }}</Button
                    >
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <div
                        v-for="category in categories"
                        :key="category.id"
                        class="flex items-center gap-1 rounded-xl border border-outline-glass bg-surface-container-low px-3 py-2"
                    >
                        <span class="text-xs font-semibold text-on-surface">{{
                            category.name
                        }}</span
                        ><span class="text-[11px] text-on-surface-variant"
                            >({{ category.active_recipes_count }})</span
                        ><Button
                            variant="ghost"
                            size="icon"
                            class="ml-1 size-7"
                            :aria-label="t('common.move_up')"
                            @click="moveCategory(category.id, 'up')"
                            ><ArrowUp :size="13" /></Button
                        ><Button
                            variant="ghost"
                            size="icon"
                            class="size-7"
                            :aria-label="t('common.move_down')"
                            @click="moveCategory(category.id, 'down')"
                            ><ArrowDown :size="13" /></Button
                        ><Button
                            variant="ghost"
                            size="icon"
                            class="size-7"
                            :aria-label="t('recipes.categories.edit')"
                            @click="editCategory(category)"
                            ><Pencil :size="13" /></Button
                        ><Button
                            variant="ghost"
                            size="icon"
                            class="size-7"
                            :disabled="category.recipes_count > 0"
                            :aria-label="t('recipes.categories.delete')"
                            @click="deleteCategory(category)"
                            ><Trash2 :size="13"
                        /></Button>
                    </div>
                </div>
            </Card>

            <div
                v-if="recipes.data.length"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <Card
                    v-for="recipe in recipes.data"
                    :key="recipe.id"
                    class="flex h-full flex-col"
                >
                    <div class="flex items-start justify-between gap-3">
                        <Badge variant="neutral">{{
                            recipe.category.name
                        }}</Badge>
                        <Badge v-if="recipe.archived" variant="warning">{{
                            t('recipes.archived')
                        }}</Badge>
                    </div>
                    <h2
                        class="mt-4 font-heading text-lg font-bold text-on-surface"
                    >
                        {{ recipe.name }}
                    </h2>
                    <p
                        v-if="recipe.note"
                        class="mt-2 line-clamp-2 text-xs text-on-surface-variant"
                    >
                        {{ recipe.note }}
                    </p>
                    <p
                        class="mt-3 text-xs font-semibold text-on-surface-variant"
                    >
                        {{
                            t('recipes.variant_count', {
                                count: recipe.variant_count,
                            })
                        }}
                    </p>
                    <div class="mt-auto flex flex-wrap gap-2 pt-5">
                        <Link :href="route('recipes.show', recipe.id)"
                            ><Button variant="secondary" size="compact"
                                ><BookOpen :size="14" />{{
                                    t('common.detail')
                                }}</Button
                            ></Link
                        >
                        <Button
                            v-if="is_admin"
                            variant="ghost"
                            size="icon"
                            :aria-label="t('common.move_up')"
                            @click="moveRecipe(recipe.id, 'up')"
                            ><ArrowUp :size="14"
                        /></Button>
                        <Button
                            v-if="is_admin"
                            variant="ghost"
                            size="icon"
                            :aria-label="t('common.move_down')"
                            @click="moveRecipe(recipe.id, 'down')"
                            ><ArrowDown :size="14"
                        /></Button>
                        <Button
                            v-if="is_admin && !recipe.archived"
                            variant="ghost"
                            size="compact"
                            @click="setArchived(recipe, true)"
                            ><Archive :size="14" />{{
                                t('recipes.archive')
                            }}</Button
                        >
                        <Button
                            v-if="is_admin && recipe.archived"
                            variant="ghost"
                            size="compact"
                            @click="setArchived(recipe, false)"
                            ><RotateCcw :size="14" />{{
                                t('recipes.restore')
                            }}</Button
                        >
                    </div>
                </Card>
            </div>
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
    </AppLayout>
</template>
