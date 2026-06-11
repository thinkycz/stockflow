<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Store as StoreIcon, Search, Plus } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { formatMoney } from '@/lib/format';

type StoreRow = {
    id: number;
    name: string;
    address: string | null;
    status: 'active' | 'inactive';
    is_warehouse: boolean;
    movements_count: number;
    total_received_quantity: number;
    total_received_value: number;
    total_outgoing_value: number;
};

defineProps<{
    stores: StoreRow[];
    search: string;
}>();

const { t } = useI18n();

useBoundLocale();

const searchTerm = ref<string>('');
const submitting = ref<boolean>(false);

function performSearch(event: Event): void {
    event.preventDefault();
    submitting.value = true;
    router.get(
        '/stores',
        { search: searchTerm.value },
        { preserveState: true },
    );
}
</script>

<template>
    <AppLayout :title="t('stores.title')">
        <Head :title="t('stores.title')" />

        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('stores.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('stores.subtitle') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link href="/stores/create">
                        <Button>
                            <Plus :size="14" />
                            {{ t('stores.add_store') }}
                        </Button>
                    </Link>
                </div>
            </header>

            <Card padded>
                <form
                    class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center"
                    @submit.prevent="performSearch"
                >
                    <div class="relative flex-1">
                        <Search
                            :size="14"
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-on-surface-variant"
                        />
                        <Input
                            v-model="searchTerm"
                            type="search"
                            :placeholder="t('stores.search_placeholder')"
                            class="pl-9"
                        />
                    </div>
                    <Button
                        type="submit"
                        variant="secondary"
                        :disabled="submitting"
                    >
                        {{ t('common.search') }}
                    </Button>
                </form>

                <EmptyState
                    v-if="stores.length === 0"
                    :title="t('stores.empty.title')"
                    :description="t('stores.empty.description')"
                >
                    <template #action>
                        <Link href="/stores/create">
                            <Button>
                                <Plus :size="14" />
                                {{ t('stores.add_store') }}
                            </Button>
                        </Link>
                    </template>
                </EmptyState>
                <div v-else class="overflow-x-auto">
                    <DataTable>
                        <thead>
                            <tr>
                                <th class="w-10"></th>
                                <th>{{ t('stores.columns.name') }}</th>
                                <th>{{ t('stores.columns.address') }}</th>
                                <th>{{ t('stores.columns.status') }}</th>
                                <th class="text-right">
                                    {{ t('stores.columns.movements') }}
                                </th>
                                <th class="text-right">
                                    {{ t('stores.columns.received_value') }}
                                </th>
                                <th class="text-right">
                                    {{ t('stores.columns.outgoing_value') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="store in stores" :key="store.id">
                                <td>
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-container text-on-surface-variant"
                                    >
                                        <StoreIcon :size="14" />
                                    </div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="`/stores/${store.id}`"
                                            class="font-semibold text-on-surface hover:text-primary"
                                        >
                                            {{ store.name }}
                                        </Link>
                                        <Badge
                                            v-if="store.is_warehouse"
                                            variant="neutral"
                                        >
                                            {{ t('stores.warehouse') }}
                                        </Badge>
                                    </div>
                                </td>
                                <td class="text-xs text-on-surface-variant">
                                    {{ store.address ?? '—' }}
                                </td>
                                <td>
                                    <Badge
                                        :variant="
                                            store.status === 'active'
                                                ? 'success'
                                                : 'neutral'
                                        "
                                    >
                                        {{
                                            store.status === 'active'
                                                ? t('stores.status.active')
                                                : t('stores.status.inactive')
                                        }}
                                    </Badge>
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ store.movements_count }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{
                                        formatMoney(store.total_received_value)
                                    }}
                                </td>
                                <td class="text-right text-on-surface-variant">
                                    {{
                                        formatMoney(store.total_outgoing_value)
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
