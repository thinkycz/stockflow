<script setup lang="ts">
import { CheckCircle2, XCircle } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import BackLink from '@/components/ui/BackLink.vue';
import Badge from '@/components/ui/Badge.vue';
import Card from '@/components/ui/Card.vue';
import RecipeActionIcon from '@/components/recipes/RecipeActionIcon.vue';
import RecipeInstructionIcon from '@/components/recipes/RecipeInstructionIcon.vue';
import RecipeVariantBlock, {
    type RecipeIngredientData,
} from '@/components/recipes/RecipeVariantBlock.vue';
import { useCzechDate } from '@/composables/useCzechDate';
import { useRoute } from '@/composables/useRoute';

defineProps<{
    attempt: {
        id: number;
        recipe_name: string;
        variant_name: string | null;
        worker_name: string;
        actor_name: string;
        score: number;
        order_score: number | null;
        amount_score: number | null;
        session_id: number | null;
        session_position: number | null;
        passed: boolean;
        started_at: string;
        submitted_at: string;
        correct_steps: string[];
        submitted_steps: string[];
        ingredients: RecipeIngredientData[];
        correct_step_details: Array<{
            text: string;
            type?: string;
            action_key: string;
            icon_group?: string;
            quantity_value?: string | null;
            unit?: string | null;
            submitted_amount?: string | null;
        }>;
    };
}>();
const { t } = useI18n();
const route = useRoute();
const { formatCzechDateTime } = useCzechDate();
</script>

<template>
    <AppLayout :title="attempt.recipe_name">
        <div class="mx-auto max-w-4xl space-y-6">
            <header>
                <BackLink :href="route('recipe-test-results.index')">{{
                    t('recipes.results.back')
                }}</BackLink>
                <div class="mt-3 flex items-center gap-3">
                    <component
                        :is="attempt.passed ? CheckCircle2 : XCircle"
                        :class="
                            attempt.passed
                                ? 'text-emerald-600'
                                : 'text-error-red'
                        "
                        :size="32"
                    />
                    <div>
                        <h1
                            class="font-heading text-2xl font-bold text-on-surface"
                        >
                            {{ attempt.recipe_name }}
                        </h1>
                        <p class="text-sm text-on-surface-variant">
                            {{ attempt.worker_name }} ·
                            {{
                                attempt.variant_name ||
                                t('recipes.default_variant')
                            }}
                            <template v-if="attempt.session_position">
                                · {{ attempt.session_position }}/3
                            </template>
                        </p>
                    </div>
                </div>
            </header>
            <Card class="grid gap-4 sm:grid-cols-4"
                ><div>
                    <p class="text-xs text-on-surface-variant">
                        {{ t('recipes.results.result') }}
                    </p>
                    <Badge
                        class="mt-1"
                        :variant="attempt.passed ? 'success' : 'danger'"
                        >{{ attempt.score }} %</Badge
                    >
                    <p
                        v-if="attempt.order_score !== null"
                        class="mt-1 text-xs text-on-surface-variant"
                    >
                        {{ t('recipes.test.order_score') }}:
                        {{ attempt.order_score }} % ·
                        {{ t('recipes.test.amount_score') }}:
                        {{ attempt.amount_score }} %
                    </p>
                </div>
                <div>
                    <p class="text-xs text-on-surface-variant">
                        {{ t('recipes.results.actor') }}
                    </p>
                    <p class="mt-1 text-sm font-semibold">
                        {{ attempt.actor_name }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-on-surface-variant">
                        {{ t('recipes.results.started') }}
                    </p>
                    <p class="mt-1 text-sm font-semibold">
                        {{ formatCzechDateTime(attempt.started_at) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-on-surface-variant">
                        {{ t('recipes.results.submitted') }}
                    </p>
                    <p class="mt-1 text-sm font-semibold">
                        {{ formatCzechDateTime(attempt.submitted_at) }}
                    </p>
                </div></Card
            >
            <RecipeVariantBlock
                v-if="attempt.ingredients.length"
                :variant="{
                    name: attempt.variant_name,
                    ingredients: attempt.ingredients,
                    steps: [],
                }"
                :show-procedure="false"
            />
            <div class="grid gap-5 md:grid-cols-2">
                <Card
                    ><h2 class="font-heading text-lg font-bold">
                        {{ t('recipes.results.submitted_order') }}
                    </h2>
                    <ol class="mt-4 space-y-2">
                        <li
                            v-for="(step, index) in attempt.submitted_steps"
                            :key="index"
                            class="flex gap-2 rounded-xl bg-surface-container-low p-3 text-sm"
                        >
                            <span class="font-bold text-primary"
                                >{{ index + 1 }}.</span
                            >{{ step }}
                        </li>
                    </ol></Card
                ><Card
                    ><h2 class="font-heading text-lg font-bold">
                        {{ t('recipes.test.correct_order') }}
                    </h2>
                    <ol
                        v-if="attempt.correct_step_details.length"
                        class="mt-4 space-y-2"
                    >
                        <li
                            v-for="(
                                step, index
                            ) in attempt.correct_step_details"
                            :key="index"
                            class="flex items-center gap-2 rounded-xl bg-surface-container-low p-3 text-sm"
                        >
                            <span class="font-bold text-primary"
                                >{{ index + 1 }}.</span
                            >
                            <RecipeInstructionIcon
                                v-if="step.type"
                                :type="step.type"
                                :action-key="step.action_key"
                                :icon-group="step.icon_group || 'neutral'"
                            />
                            <RecipeActionIcon
                                v-else
                                :action-key="step.action_key"
                            />
                            <span>{{ step.text }}</span>
                            <span
                                v-if="
                                    step.quantity_value &&
                                    ['g', 'ml'].includes(
                                        (step.unit || '').toLowerCase(),
                                    )
                                "
                                class="text-xs text-on-surface-variant"
                            >
                                {{ t('recipes.test.your_amount') }}:
                                {{ step.submitted_amount }} ·
                                {{ t('recipes.test.correct_amount') }}:
                                {{ step.quantity_value }} {{ step.unit }}
                            </span>
                        </li>
                    </ol>
                    <ol v-else class="mt-4 space-y-2">
                        <li
                            v-for="(step, index) in attempt.correct_steps"
                            :key="index"
                            class="flex gap-2 rounded-xl bg-surface-container-low p-3 text-sm"
                        >
                            <span class="font-bold text-primary"
                                >{{ index + 1 }}.</span
                            >{{ step }}
                        </li>
                    </ol></Card
                >
            </div>
        </div>
    </AppLayout>
</template>
