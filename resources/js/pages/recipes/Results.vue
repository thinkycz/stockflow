<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Eye } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import BackLink from '@/components/ui/BackLink.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Label from '@/components/ui/Label.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Select from '@/components/ui/Select.vue';
import { useCzechDate } from '@/composables/useCzechDate';
import { useRoute } from '@/composables/useRoute';

type Latest = {
    id: number;
    variant_name: string | null;
    score: number;
    passed: boolean;
    submitted_at: string;
};
const props = defineProps<{
    workers: Array<{ id: number; name: string }>;
    selected_worker_id: number | null;
    selected_recipe_id: number | null;
    recipes: Array<{
        id: number;
        name: string;
        archived: boolean;
        attempt_count: number;
        latest: Latest | null;
    }>;
    history: {
        data: Array<Latest & { actor_name: string }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}>();

const { t } = useI18n();
const route = useRoute();
const { formatCzechDateTime } = useCzechDate();
const workerId = ref(
    props.selected_worker_id ? String(props.selected_worker_id) : '',
);
const selectedRecipe = computed(
    () =>
        props.recipes.find(
            (recipe) => recipe.id === props.selected_recipe_id,
        ) ?? null,
);

function chooseWorker(): void {
    router.get(route('recipe-test-results.index'), {
        worker_id: workerId.value || undefined,
    });
}
function showHistory(recipeId: number): void {
    router.get(
        route('recipe-test-results.index'),
        { worker_id: workerId.value, recipe_id: recipeId },
        { preserveState: true },
    );
}
</script>

<template>
    <AppLayout :title="t('recipes.results.title')">
        <div class="space-y-6">
            <header>
                <BackLink :href="route('recipes.index')">{{
                    t('recipes.back')
                }}</BackLink>
                <h1
                    class="mt-3 font-heading text-2xl font-bold text-on-surface"
                >
                    {{ t('recipes.results.title') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('recipes.results.subtitle') }}
                </p>
            </header>
            <Card>
                <Label for="result-worker">{{
                    t('recipes.results.worker')
                }}</Label>
                <Select
                    id="result-worker"
                    v-model="workerId"
                    class="mt-1 max-w-sm"
                    :options="
                        workers.map((worker) => ({
                            value: String(worker.id),
                            label: worker.name,
                        }))
                    "
                    :placeholder="t('recipes.test.choose_worker_placeholder')"
                    @change="chooseWorker"
                />
            </Card>

            <EmptyState
                v-if="!selected_worker_id"
                :title="t('recipes.results.choose_worker')"
            />
            <Card v-else :padded="false" class="overflow-hidden">
                <DataTable variant="nested" table-class="text-xs">
                    <thead
                        class="bg-surface-container-low text-on-surface-variant"
                    >
                        <tr>
                            <th class="px-5 py-3">
                                {{ t('recipes.name') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ t('recipes.results.latest') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ t('recipes.results.attempts') }}
                            </th>
                            <th class="px-5 py-3 text-right">
                                {{ t('common.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-glass">
                        <tr v-for="recipe in recipes" :key="recipe.id">
                            <td class="px-5 py-4 font-semibold text-on-surface">
                                {{ recipe.name }}
                                <Badge
                                    v-if="recipe.archived"
                                    variant="warning"
                                    class="ml-2"
                                    >{{ t('recipes.archived') }}</Badge
                                >
                            </td>
                            <td class="px-5 py-4">
                                <template v-if="recipe.latest"
                                    ><Badge
                                        :variant="
                                            recipe.latest.passed
                                                ? 'success'
                                                : 'danger'
                                        "
                                        >{{ recipe.latest.score }} %</Badge
                                    ><span class="ml-2 text-on-surface-variant"
                                        >{{
                                            recipe.latest.variant_name ||
                                            t('recipes.default_variant')
                                        }}
                                        ·
                                        {{
                                            formatCzechDateTime(
                                                recipe.latest.submitted_at,
                                            )
                                        }}</span
                                    ></template
                                ><span v-else class="text-on-surface-variant">{{
                                    t('recipes.results.not_tested')
                                }}</span>
                            </td>
                            <td class="px-5 py-4 font-semibold">
                                {{ recipe.attempt_count }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <Button
                                    v-if="recipe.attempt_count"
                                    variant="ghost"
                                    size="compact"
                                    @click="showHistory(recipe.id)"
                                    >{{ t('recipes.results.history') }}</Button
                                >
                            </td>
                        </tr>
                    </tbody>
                </DataTable>
            </Card>

            <Card v-if="selectedRecipe">
                <h2 class="font-heading text-lg font-bold text-on-surface">
                    {{
                        t('recipes.results.history_for', {
                            recipe: selectedRecipe.name,
                        })
                    }}
                </h2>
                <div v-if="history.data.length" class="mt-4 space-y-2">
                    <Link
                        v-for="attempt in history.data"
                        :key="attempt.id"
                        :href="route('recipe-test-results.show', attempt.id)"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-outline-glass p-3 transition hover:border-primary/40"
                        ><div>
                            <Badge
                                :variant="attempt.passed ? 'success' : 'danger'"
                                >{{ attempt.score }} %</Badge
                            ><span
                                class="ml-2 text-sm font-semibold text-on-surface"
                                >{{
                                    attempt.variant_name ||
                                    t('recipes.default_variant')
                                }}</span
                            >
                            <p class="mt-1 text-xs text-on-surface-variant">
                                {{ formatCzechDateTime(attempt.submitted_at) }}
                                · {{ attempt.actor_name }}
                            </p>
                        </div>
                        <Eye :size="17" class="text-primary"
                    /></Link>
                </div>
                <EmptyState
                    v-else
                    density="compact"
                    :title="t('recipes.results.no_attempts')"
                />
                <Pagination
                    v-if="history.last_page > 1"
                    class="mt-4"
                    :current-page="history.current_page"
                    :last-page="history.last_page"
                    :total="history.total"
                    :per-page="history.per_page"
                    :base-url="route('recipe-test-results.index')"
                    :query-params="{
                        worker_id: workerId,
                        recipe_id: selectedRecipe.id,
                    }"
                />
            </Card>
        </div>
    </AppLayout>
</template>
