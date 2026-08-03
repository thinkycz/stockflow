<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2 } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import BackLink from '@/components/ui/BackLink.vue';
import Button from '@/components/ui/Button.vue';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';

type Category = { id: number; name: string; recipes_count: number };
defineProps<{ categories: Category[] }>();

const { t } = useI18n();
const route = useRoute();
const dialog = useDialog();

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

async function editCategory(category: Category): Promise<void> {
    const name = await dialog.prompt({
        title: t('recipes.categories.edit'),
        message: t('recipes.categories.help'),
        label: t('recipes.category'),
        defaultValue: category.name,
        required: true,
        maxLength: 120,
    });
    if (name) {
        router.put(route('recipe-categories.update', category.id), { name });
    }
}

async function deleteCategory(category: Category): Promise<void> {
    if (category.recipes_count > 0) return;
    const confirmed = await dialog.confirm({
        title: t('recipes.categories.delete'),
        message: t('recipes.categories.delete_help', { name: category.name }),
        confirmLabel: t('common.delete'),
        variant: 'danger',
    });
    if (confirmed) {
        router.delete(route('recipe-categories.destroy', category.id));
    }
}

function moveCategory(id: number, direction: 'up' | 'down'): void {
    router.put(
        route('recipe-categories.position', id),
        { direction },
        { preserveScroll: true },
    );
}
</script>

<template>
    <AppLayout :title="t('recipes.categories.title')">
        <div class="mx-auto max-w-3xl space-y-5">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <BackLink :href="route('recipes.index')">
                        {{ t('recipes.back') }}
                    </BackLink>
                    <h1
                        class="mt-3 font-heading text-2xl font-bold text-on-surface"
                    >
                        {{ t('recipes.categories.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('recipes.categories.help') }}
                    </p>
                </div>
                <Button size="compact" @click="createCategory">
                    <Plus :size="15" />{{ t('recipes.categories.create') }}
                </Button>
            </header>

            <ul
                class="divide-y divide-outline-glass overflow-hidden rounded-xl border border-outline-glass bg-white"
            >
                <li
                    v-for="(category, index) in categories"
                    :key="category.id"
                    class="flex min-h-12 items-center gap-2 px-3"
                >
                    <span class="min-w-0 flex-1 text-sm font-semibold">
                        {{ category.name }}
                    </span>
                    <span class="text-xs text-on-surface-variant">
                        {{ category.recipes_count }}
                    </span>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :disabled="index === 0"
                        :aria-label="t('common.move_up')"
                        @click="moveCategory(category.id, 'up')"
                    >
                        <ArrowUp :size="14" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :disabled="index === categories.length - 1"
                        :aria-label="t('common.move_down')"
                        @click="moveCategory(category.id, 'down')"
                    >
                        <ArrowDown :size="14" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="t('recipes.categories.edit')"
                        @click="editCategory(category)"
                    >
                        <Pencil :size="14" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :disabled="category.recipes_count > 0"
                        :aria-label="t('recipes.categories.delete')"
                        @click="deleteCategory(category)"
                    >
                        <Trash2 :size="14" />
                    </Button>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
