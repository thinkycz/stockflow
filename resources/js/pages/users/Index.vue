<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import SearchFilter from '@/components/ui/SearchFilter.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useCzechDate } from '@/composables/useCzechDate';
import { useRoute } from '@/composables/useRoute';
import { useDialog } from '@/composables/useDialog';
import type { SharedProps } from '@/types';

type UserRow = {
    id: number;
    email: string;
    is_admin: boolean;
    assigned_store_id: number | null;
    assigned_store_name: string | null;
    parent_user_id: number | null;
    created_at: string | null;
};

type Filters = {
    search: string | null;
};

const props = defineProps<{
    users: UserRow[];
    filters: Filters;
}>();

const { t } = useI18n();
const route = useRoute();
const page = usePage<SharedProps>();
const dialog = useDialog();
const { formatCzechDateTime } = useCzechDate();
const searchTerm = ref<string>(props.filters.search ?? '');
const filtering = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

useBoundLocale();

watch(searchTerm, (value) => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        router.get(
            route('users.index'),
            { search: value || undefined },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => (filtering.value = true),
                onFinish: () => (filtering.value = false),
            },
        );
    }, 300);
});

async function confirmDelete(user: UserRow): Promise<void> {
    if (
        !(await dialog.confirm({
            title: `${t('common.delete')}: ${user.email}`,
            message: t('users.confirm_delete_with_data'),
            confirmLabel: t('common.delete'),
            variant: 'danger',
            verification: {
                label: t('common.type_to_confirm', { value: user.email }),
                expected: user.email,
            },
        }))
    )
        return;

    router.delete(route('users.destroy', user.id));
}

const currentUserId = (): number | null => page.props.auth?.user?.id ?? null;
</script>

<template>
    <AppLayout :title="t('users.title')">
        <Head :title="t('users.title')" />

        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('users.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('users.subtitle') }}
                    </p>
                </div>
                <div
                    class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-end"
                >
                    <SearchFilter
                        id="users_search"
                        v-model="searchTerm"
                        :label="t('common.search')"
                        :placeholder="t('users.search_placeholder')"
                        :busy="filtering"
                        class="w-full sm:w-72"
                    />
                    <Link :href="route('users.create')">
                        <Button>
                            <Plus :size="14" />
                            {{ t('users.create.title') }}
                        </Button>
                    </Link>
                </div>
            </header>

            <section class="space-y-4">
                <EmptyState
                    v-if="users.length === 0"
                    :title="
                        props.filters.search
                            ? t('common.no_results')
                            : t('users.empty.title')
                    "
                    :description="
                        props.filters.search
                            ? t('users.empty.search_description')
                            : t('users.empty.description')
                    "
                >
                    <template v-if="!props.filters.search" #action>
                        <Link :href="route('users.create')">
                            <Button>
                                <Plus :size="14" />
                                {{ t('users.create.title') }}
                            </Button>
                        </Link>
                    </template>
                </EmptyState>

                <DataTable v-else>
                    <thead>
                        <tr>
                            <th>
                                {{ t('users.columns.email') }}
                            </th>
                            <th>
                                {{ t('users.columns.role') }}
                            </th>
                            <th>
                                {{ t('users.columns.store') }}
                            </th>
                            <th>
                                {{ t('users.columns.created') }}
                            </th>
                            <th class="w-0 text-right">
                                {{ t('users.columns.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id">
                            <td class="font-semibold text-on-surface">
                                {{ user.email }}
                            </td>
                            <td>
                                <Badge
                                    :variant="
                                        user.is_admin ? 'success' : 'neutral'
                                    "
                                >
                                    {{
                                        user.is_admin
                                            ? t('users.role.admin')
                                            : t('users.role.limited')
                                    }}
                                </Badge>
                            </td>
                            <td class="text-on-surface">
                                <span v-if="user.assigned_store_name">{{
                                    user.assigned_store_name
                                }}</span>
                                <span v-else class="text-on-surface-variant"
                                    >—</span
                                >
                            </td>
                            <td class="text-on-surface-variant">
                                {{ formatCzechDateTime(user.created_at) }}
                            </td>
                            <td>
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Link :href="route('users.edit', user.id)">
                                        <Button
                                            variant="ghost"
                                            type="button"
                                            :aria-label="t('common.edit')"
                                        >
                                            <Pencil :size="14" />
                                        </Button>
                                    </Link>
                                    <Button
                                        v-if="
                                            !user.is_admin &&
                                            user.id !== currentUserId()
                                        "
                                        variant="ghost"
                                        type="button"
                                        :aria-label="t('common.delete')"
                                        @click="confirmDelete(user)"
                                    >
                                        <Trash2 :size="14" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </DataTable>
            </section>
        </div>
    </AppLayout>
</template>
