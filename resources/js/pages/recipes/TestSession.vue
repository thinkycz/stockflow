<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    CheckCircle2,
    GripVertical,
    RotateCcw,
    XCircle,
} from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import {
    useRecipeTestSession,
    type RecipeTestSessionProps,
} from '@/features/recipes/useRecipeTestSession';

const props = defineProps<RecipeTestSessionProps>();
const {
    t,
    route,
    currentIndex,
    submitting,
    draggedIndex,
    currentRecipe,
    currentAnswer,
    orderedInstructions,
    currentComplete,
    allComplete,
    move,
    drop,
    submit,
    correctInstructions,
} = useRecipeTestSession(props);
</script>

<template>
    <AppLayout :title="t('recipes.test.session_title')">
        <div class="mx-auto max-w-3xl space-y-5">
            <header class="space-y-2">
                <Link
                    :href="route('recipes.index')"
                    class="text-sm font-semibold text-primary hover:underline"
                >
                    ← {{ t('recipes.back') }}
                </Link>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1
                            class="font-heading text-2xl font-bold text-on-surface"
                        >
                            {{ t('recipes.test.session_title') }}
                        </h1>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            {{ session.worker_name }}
                        </p>
                    </div>
                    <Badge v-if="!session.submitted">
                        {{ currentIndex + 1 }}/{{ recipes.length }}
                    </Badge>
                </div>
            </header>

            <template v-if="session.submitted && result">
                <section
                    class="rounded-2xl border p-5"
                    :class="
                        result.passed
                            ? 'border-success-green/30 bg-success-green/5'
                            : 'border-error-red/30 bg-error-red/5'
                    "
                >
                    <div class="flex items-center gap-3">
                        <CheckCircle2
                            v-if="result.passed"
                            class="text-success-green"
                            :size="24"
                        />
                        <XCircle v-else class="text-error-red" :size="24" />
                        <div>
                            <h2 class="font-heading text-lg font-bold">
                                {{
                                    result.passed
                                        ? t('recipes.test.passed')
                                        : t('recipes.test.failed')
                                }}
                            </h2>
                            <p class="text-sm text-on-surface-variant">
                                {{ t('recipes.test.combined_score') }}:
                                {{ result.score }} %
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    v-for="recipe in recipes"
                    :key="recipe.attempt_id"
                    class="rounded-2xl border border-outline-glass bg-white p-4"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <div>
                            <h2 class="font-heading font-bold">
                                {{ recipe.recipe_name }}
                            </h2>
                            <p
                                v-if="recipe.variant_name"
                                class="text-xs text-on-surface-variant"
                            >
                                {{ recipe.variant_name }} ·
                                {{ recipe.position }}/3
                            </p>
                        </div>
                        <Badge
                            :variant="
                                recipe.result?.passed ? 'success' : 'danger'
                            "
                        >
                            {{ recipe.result?.score }} %
                        </Badge>
                    </div>
                    <p class="mt-2 text-xs text-on-surface-variant">
                        {{ t('recipes.test.order_score') }}:
                        {{ recipe.result?.order_score }} % ·
                        {{ t('recipes.test.amount_score') }}:
                        {{ recipe.result?.amount_score }} %
                    </p>
                    <ol class="mt-3 space-y-1.5">
                        <li
                            v-for="(instruction, index) in correctInstructions(
                                recipe,
                            )"
                            :key="instruction.token"
                            class="flex gap-2 text-sm"
                        >
                            <span class="w-5 shrink-0 text-on-surface-variant"
                                >{{ index + 1 }}.</span
                            >
                            <span>
                                {{
                                    instruction.correct_text || instruction.text
                                }}
                                <span
                                    v-if="instruction.requires_amount"
                                    class="ml-1 text-xs text-on-surface-variant"
                                >
                                    ({{ t('recipes.test.your_amount') }}:
                                    {{ instruction.submitted_amount }},
                                    {{ t('recipes.test.correct_amount') }}:
                                    {{ instruction.correct_amount }}
                                    {{ instruction.unit }})
                                </span>
                            </span>
                        </li>
                    </ol>
                </section>

                <div class="flex justify-end">
                    <Link :href="route('recipes.index')">
                        <Button
                            ><RotateCcw :size="16" />{{
                                t('recipes.test.try_again')
                            }}</Button
                        >
                    </Link>
                </div>
            </template>

            <template v-else-if="currentRecipe && currentAnswer">
                <section
                    class="rounded-2xl border border-outline-glass bg-white p-4 sm:p-5"
                >
                    <div class="mb-4">
                        <h2 class="font-heading text-xl font-bold">
                            <span data-testid="session-recipe-name">
                                {{ currentRecipe.recipe_name }}
                            </span>
                        </h2>
                        <p
                            v-if="currentRecipe.variant_name"
                            data-testid="session-variant-name"
                            class="text-sm text-on-surface-variant"
                        >
                            {{ currentRecipe.variant_name }}
                        </p>
                    </div>
                    <p class="mb-3 text-sm text-on-surface-variant">
                        {{ t('recipes.test.session_instructions') }}
                    </p>
                    <ol class="space-y-2">
                        <li
                            v-for="(instruction, index) in orderedInstructions"
                            :key="instruction.token"
                            draggable="true"
                            data-testid="session-instruction"
                            :data-instruction-token="instruction.token"
                            :data-instruction-id="
                                instruction.instruction_id || undefined
                            "
                            :data-instruction-text="
                                instruction.text || undefined
                            "
                            :data-instruction-unit="
                                instruction.unit || undefined
                            "
                            :data-instruction-ingredient="
                                instruction.ingredient_name || undefined
                            "
                            :data-instruction-target="
                                instruction.target || undefined
                            "
                            class="flex items-center gap-2 rounded-xl border border-outline-glass bg-surface-container-lowest p-2"
                            @dragstart="draggedIndex = index"
                            @dragover.prevent
                            @drop="drop(index)"
                        >
                            <GripVertical
                                class="shrink-0 cursor-grab text-on-surface-variant"
                                :size="17"
                            />
                            <span
                                class="w-5 shrink-0 text-xs font-bold text-on-surface-variant"
                            >
                                {{ index + 1 }}
                            </span>
                            <div class="min-w-0 flex-1 text-sm">
                                <template v-if="instruction.requires_amount">
                                    <span>{{ t('recipes.test.add') }}</span>
                                    <Input
                                        v-model="
                                            currentAnswer.amounts[
                                                instruction.token
                                            ]
                                        "
                                        inputmode="decimal"
                                        class="mx-1 inline-flex h-8 w-20 px-2 text-center"
                                        :aria-label="
                                            t('recipes.test.amount_for', {
                                                ingredient:
                                                    instruction.ingredient_name,
                                            })
                                        "
                                        data-testid="amount-input"
                                    />
                                    <span>
                                        {{ instruction.unit }}
                                        {{ instruction.ingredient_name }}
                                        <template v-if="instruction.target">
                                            {{ t('recipes.test.into') }}
                                            {{ instruction.target }}
                                        </template>
                                    </span>
                                </template>
                                <template v-else>{{
                                    instruction.text
                                }}</template>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <Button
                                    type="button"
                                    size="icon-sm"
                                    variant="ghost"
                                    :disabled="index === 0"
                                    :aria-label="t('common.move_up')"
                                    @click="move(index, -1)"
                                    ><ArrowUp :size="15"
                                /></Button>
                                <Button
                                    type="button"
                                    size="icon-sm"
                                    variant="ghost"
                                    :disabled="
                                        index === orderedInstructions.length - 1
                                    "
                                    :aria-label="t('common.move_down')"
                                    @click="move(index, 1)"
                                    ><ArrowDown :size="15"
                                /></Button>
                            </div>
                        </li>
                    </ol>
                </section>

                <footer class="flex items-center justify-between gap-3">
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="currentIndex === 0"
                        @click="currentIndex--"
                        ><ArrowLeft :size="16" />{{ t('recipes.back') }}</Button
                    >
                    <Button
                        v-if="currentIndex < recipes.length - 1"
                        type="button"
                        :disabled="!currentComplete"
                        @click="currentIndex++"
                        >{{ t('common.next') }}<ArrowRight :size="16"
                    /></Button>
                    <Button
                        v-else
                        type="button"
                        :disabled="!allComplete || submitting"
                        @click="submit"
                        >{{
                            submitting
                                ? t('recipes.test.evaluating')
                                : t('recipes.test.submit_all')
                        }}</Button
                    >
                </footer>
            </template>
        </div>
    </AppLayout>
</template>
