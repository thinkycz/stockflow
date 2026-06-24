<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useCzechDate } from '@/composables/useCzechDate';
import { useRoute } from '@/composables/useRoute';
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

defineProps<{
    users: UserRow[];
    filters: Filters;
}>();

const { t } = useI18n();
const route = useRoute();
const page = usePage<SharedProps>();
const { formatCzechDateTime } = useCzechDate();

useBoundLocale();

function confirmDelete(user: UserRow): void {
    if (!window.confirm(t('users.confirm_delete_with_data'))) {
        return;
    }

    router.delete(route('users.destroy', user.id));
}

const currentUserId = (): number | null => page.props.auth?.user?.id ?? null;
</script>

<template>
    <AppLayout :title="t('users.title')">
        <Head :title="t('users.title')" />

        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
            <header class="flex items-end justify-between gap-3">
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
                <Link :href="route('users.create')">
                    <Button>{{ t('users.create.title') }}</Button>
                </Link>
            </header>

            <Card padded>
                <EmptyState
                    v-if="users.length === 0"
                    :title="t('users.title')"
                    :description="
                        t('inventory_counts.history.empty.description')
                    "
                />

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-outline-glass text-xs uppercase tracking-wide text-on-surface-variant"
                        >
                            <tr>
                                <th class="px-3 py-2 font-semibold">
                                    {{ t('users.columns.email') }}
                                </th>
                                <th class="px-3 py-2 font-semibold">
                                    {{ t('users.columns.role') }}
                                </th>
                                <th class="px-3 py-2 font-semibold">
                                    {{ t('users.columns.store') }}
                                </th>
                                <th class="px-3 py-2 font-semibold">
                                    {{ t('users.columns.created') }}
                                </th>
                                <th class="px-3 py-2 text-right font-semibold">
                                    &nbsp;
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-glass/70">
                            <tr
                                v-for="user in users"
                                :key="user.id"
                                class="hover:bg-surface-variant/30"
                            >
                                <td
                                    class="px-3 py-3 font-medium text-on-surface"
                                >
                                    {{ user.email }}
                                </td>
                                <td class="px-3 py-3">
                                    <Badge
                                        :variant="
                                            user.is_admin
                                                ? 'success'
                                                : 'neutral'
                                        "
                                    >
                                        {{
                                            user.is_admin
                                                ? t('users.role.admin')
                                                : t('users.role.limited')
                                        }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-3 text-on-surface">
                                    <span v-if="user.assigned_store_name">{{
                                        user.assigned_store_name
                                    }}</span>
                                    <span v-else class="text-on-surface-variant"
                                        >—</span
                                    >
                                </td>
                                <td class="px-3 py-3 text-on-surface-variant">
                                    {{ formatCzechDateTime(user.created_at) }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Link
                                            :href="route('users.edit', user.id)"
                                        >
                                            <Button variant="secondary">
                                                {{ t('common.edit') }}
                                            </Button>
                                        </Link>
                                        <Button
                                            v-if="
                                                !user.is_admin &&
                                                user.id !== currentUserId()
                                            "
                                            variant="danger"
                                            @click="confirmDelete(user)"
                                        >
                                            {{ t('users.actions.delete') }}
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
