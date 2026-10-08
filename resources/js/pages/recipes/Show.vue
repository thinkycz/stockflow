<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    CheckCheck,
    ListOrdered,
    Play,
    RotateCcw,
    Timer,
    Pause,
    Utensils,
    Info,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import BackLink from '@/components/ui/BackLink.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Tabs from '@/components/ui/Tabs.vue';
import RecipeQuickLookup from '@/features/recipes/components/RecipeQuickLookup.vue';
import RecipeIllustration from '@/features/recipes/components/RecipeIllustration.vue';
import RecipeIngredients from '@/features/recipes/components/RecipeIngredients.vue';
import RecipeActionIcon from '@/features/recipes/components/RecipeActionIcon.vue';
import RecipeToppingAdjustments from '@/features/recipes/components/RecipeToppingAdjustments.vue';
import { nextVariant } from '@/features/recipes/search';
import { useRecipePreparation } from '@/features/recipes/useRecipePreparation';
import type {
    RecipeDocument,
    RecipeLookupEntry,
} from '@/features/recipes/types';

const props = defineProps<{
    recipe: RecipeDocument;
    lookup: RecipeLookupEntry[];
}>();
const { t } = useI18n();
const query = ref('');
const variantKey = ref(props.recipe.variants[0]?.key ?? '');
const selected = computed(
    () =>
        props.recipe.variants.find(
            (variant) => variant.key === variantKey.value,
        ) ?? props.recipe.variants[0],
);
const mode = ref('reference');
const guide = useRecipePreparation(selected);
const { checked, stepIndex, step, finished, progress } = guide;
const dimensions = computed(() =>
    [
        ...new Set(
            props.recipe.variants.flatMap((variant) =>
                Object.keys(variant.selectors),
            ),
        ),
    ]
        .map((key) => ({
            key,
            values: [
                ...new Set(
                    props.recipe.variants
                        .map((variant) => variant.selectors[key])
                        .filter((value): value is string => !!value),
                ),
            ],
        }))
        .filter((dimension) => dimension.values.length > 1),
);
watch(
    () => props.recipe.key,
    () => {
        variantKey.value = props.recipe.variants[0]?.key ?? '';
        mode.value = 'reference';
        query.value = '';
        guide.restart();
    },
);
function choose(dimension: string, value: string): void {
    if (selected.value)
        variantKey.value = nextVariant(
            props.recipe.variants,
            selected.value,
            dimension,
            value,
        ).key;
}
function selectorLabel(dimension: string, value: string): string {
    return (
        props.recipe.variants.find(
            (variant) => variant.selectors[dimension] === value,
        )?.selector_labels[dimension] ?? value
    );
}
function formatTimer(seconds: number): string {
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}
const timerSeconds = computed(() =>
    step.value?.timer_seconds
        ? guide.seconds(step.value.key, step.value.timer_seconds)
        : 0,
);
const running = computed(
    () =>
        !!step.value &&
        !!guide.timers.value[step.value.key]?.endsAt &&
        timerSeconds.value > 0,
);
const timerStarted = computed(
    () => !!step.value && !!guide.timers.value[step.value.key]?.started,
);
</script>

<template>
    <AppLayout :title="recipe.name">
        <div class="mx-auto max-w-6xl space-y-6">
            <BackLink href="/recipes">{{ t('recipes.back') }}</BackLink>
            <div class="max-w-xl">
                <RecipeQuickLookup v-model="query" :entries="lookup" />
            </div>

            <header
                class="flex items-center justify-between gap-4 rounded-2xl border border-outline-glass bg-white px-5 py-5 sm:px-7"
            >
                <div class="min-w-0">
                    <Badge>{{ recipe.category.name }}</Badge>
                    <h1
                        class="mt-3 font-heading text-2xl leading-tight font-bold text-on-surface sm:text-3xl"
                    >
                        {{ recipe.name }}
                    </h1>
                    <p
                        class="mt-3 max-w-xl text-sm leading-6 text-on-surface-variant"
                    >
                        {{ recipe.summary }}
                    </p>
                </div>
                <RecipeIllustration
                    :tags="recipe.tags"
                    class="hidden shrink-0 sm:block"
                />
            </header>

            <section v-if="selected" class="space-y-6">
                <div class="flex flex-wrap items-end justify-between gap-5">
                    <div class="flex flex-wrap gap-5">
                        <fieldset
                            v-for="dimension in dimensions"
                            :key="dimension.key"
                            class="min-w-0"
                        >
                            <legend
                                class="mb-2 text-xs font-semibold text-on-surface-variant"
                            >
                                {{ t(`recipes.selectors.${dimension.key}`) }}
                            </legend>
                            <Tabs
                                :model-value="
                                    selected.selectors[dimension.key] ?? ''
                                "
                                :label="t(`recipes.selectors.${dimension.key}`)"
                                :items="
                                    dimension.values.map((value) => ({
                                        value,
                                        label: selectorLabel(
                                            dimension.key,
                                            value,
                                        ),
                                    }))
                                "
                                @update:model-value="
                                    choose(dimension.key, $event)
                                "
                            />
                        </fieldset>
                    </div>
                    <Tabs
                        v-model="mode"
                        class="w-full [&>button]:h-auto [&>button]:min-h-10 [&>button]:min-w-0 [&>button]:flex-1 [&>button]:whitespace-normal sm:w-fit"
                        :label="t('recipes.view_mode')"
                        :items="[
                            {
                                value: 'reference',
                                label: t('recipes.reference'),
                                icon: ListOrdered,
                            },
                            {
                                value: 'guided',
                                label: t('recipes.guided'),
                                icon: Play,
                            },
                        ]"
                    />
                </div>
                <p
                    v-if="dimensions.length"
                    class="text-sm font-semibold text-primary"
                    data-testid="recipe-selected-variant"
                >
                    {{ selected.name }}
                </p>

                <div
                    v-if="mode === 'reference'"
                    class="grid items-start gap-5 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.4fr)]"
                >
                    <aside class="space-y-5">
                        <section
                            class="rounded-2xl border border-outline-glass bg-white p-5 sm:p-6"
                        >
                            <h2
                                class="mb-5 font-heading text-lg font-bold text-on-surface"
                            >
                                {{ t('recipes.ingredients') }}
                            </h2>
                            <RecipeIngredients
                                :ingredients="selected.ingredients"
                            />
                            <p
                                class="mt-5 border-t border-outline-glass pt-4 text-xs leading-5 text-on-surface-variant"
                            >
                                {{ t('recipes.units_hint') }}
                            </p>
                        </section>
                        <section
                            class="rounded-2xl border border-outline-glass bg-white p-5"
                        >
                            <h2
                                class="mb-3 flex items-center gap-2 text-sm font-bold text-on-surface"
                            >
                                <Utensils :size="17" class="text-primary" />{{
                                    t('recipes.equipment')
                                }}
                            </h2>
                            <ul class="flex flex-wrap gap-2">
                                <li
                                    v-for="item in recipe.equipment"
                                    :key="item"
                                    class="rounded-lg bg-surface-container-low px-2.5 py-1.5 text-xs text-on-surface-variant"
                                >
                                    {{ item }}
                                </li>
                            </ul>
                        </section>
                    </aside>
                    <section
                        class="rounded-2xl border border-outline-glass bg-white p-5 sm:p-6"
                    >
                        <div
                            class="mb-5 flex items-center justify-between gap-3"
                        >
                            <h2
                                class="font-heading text-lg font-bold text-on-surface"
                            >
                                {{ t('recipes.method') }}
                            </h2>
                            <Button
                                size="compact"
                                variant="secondary"
                                @click="mode = 'guided'"
                                ><Play :size="14" />{{
                                    t('recipes.follow_steps')
                                }}</Button
                            >
                        </div>
                        <ol class="space-y-5">
                            <li
                                v-for="(item, index) in selected.steps"
                                :key="item.key"
                                class="flex gap-3"
                                data-testid="recipe-method-step"
                            >
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/8 text-sm font-bold text-primary"
                                    >{{ index + 1 }}</span
                                >
                                <div class="min-w-0 pt-1">
                                    <h3
                                        class="flex items-center gap-2 text-sm font-bold text-on-surface"
                                    >
                                        <RecipeActionIcon
                                            :action-key="item.action_key"
                                            :size="16"
                                            class="shrink-0 text-primary"
                                        />{{ item.title }}
                                    </h3>
                                    <p
                                        class="mt-1.5 text-sm leading-6 text-on-surface-variant"
                                    >
                                        {{ item.text }}
                                    </p>
                                    <span
                                        v-if="item.timer_seconds"
                                        class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-surface-container-low px-2 py-1 text-xs font-semibold text-primary"
                                        ><Timer :size="13" />{{
                                            formatTimer(item.timer_seconds)
                                        }}</span
                                    >
                                </div>
                            </li>
                        </ol>
                    </section>
                </div>

                <section
                    v-else
                    class="mx-auto max-w-3xl overflow-hidden rounded-2xl border border-outline-glass bg-white"
                    data-testid="recipe-guide"
                >
                    <div
                        class="border-b border-outline-glass bg-primary/4 px-5 py-4 sm:px-7"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold text-primary">
                                {{
                                    finished
                                        ? t('recipes.complete')
                                        : stepIndex < 0
                                          ? t('recipes.get_ready')
                                          : t('recipes.step_progress', {
                                                current: stepIndex + 1,
                                                total: selected.steps.length,
                                            })
                                }}
                            </p>
                            <Button
                                variant="ghost"
                                size="compact"
                                @click="guide.restart"
                                ><RotateCcw :size="14" />{{
                                    t('recipes.restart')
                                }}</Button
                            >
                        </div>
                        <div
                            class="mt-3 h-1.5 overflow-hidden rounded-full bg-primary/10"
                            role="progressbar"
                            :aria-label="t('recipes.preparation_progress')"
                            :aria-valuenow="progress"
                            :aria-valuemin="0"
                            :aria-valuemax="100"
                        >
                            <div
                                class="h-full rounded-full bg-primary transition-[width]"
                                :style="{ width: `${progress}%` }"
                            />
                        </div>
                    </div>
                    <div
                        class="p-5 sm:p-7"
                        aria-live="polite"
                        aria-atomic="false"
                    >
                        <template v-if="finished"
                            ><div
                                class="flex flex-col items-center py-8 text-center"
                            >
                                <span
                                    class="mb-5 flex size-16 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                                    ><CheckCheck :size="32"
                                /></span>
                                <h2
                                    class="font-heading text-2xl font-bold text-on-surface"
                                >
                                    {{
                                        t(
                                            recipe.tags.includes('batch')
                                                ? 'recipes.batch_ready'
                                                : 'recipes.ready_to_serve',
                                        )
                                    }}
                                </h2>
                                <p class="mt-3 text-sm text-on-surface-variant">
                                    {{ t('recipes.complete_hint') }}
                                </p>
                                <Button class="mt-6" @click="guide.restart"
                                    ><RotateCcw :size="16" />{{
                                        t('recipes.make_another')
                                    }}</Button
                                >
                            </div></template
                        >
                        <template v-else-if="stepIndex < 0"
                            ><h2
                                class="font-heading text-xl font-bold text-on-surface"
                            >
                                {{ t('recipes.get_ready') }}
                            </h2>
                            <p
                                class="mt-2 text-sm leading-6 text-on-surface-variant"
                            >
                                {{ t('recipes.get_ready_hint') }}
                            </p>
                            <div class="my-5 flex flex-wrap gap-2">
                                <span
                                    v-for="item in recipe.equipment"
                                    :key="item"
                                    class="rounded-lg bg-surface-container-low px-2.5 py-1.5 text-xs text-on-surface-variant"
                                    >{{ item }}</span
                                >
                            </div>
                            <RecipeIngredients
                                v-model="checked"
                                :ingredients="selected.ingredients"
                                checklist
                            />
                            <p class="mt-4 text-xs font-semibold text-primary">
                                {{
                                    t('recipes.ingredients_ready', {
                                        count: checked.length,
                                        total: selected.ingredients.length,
                                    })
                                }}
                            </p></template
                        >
                        <template v-else-if="step"
                            ><span
                                class="mb-5 flex size-16 items-center justify-center rounded-2xl bg-primary/8 text-primary"
                                ><RecipeActionIcon
                                    :action-key="step.action_key"
                                    :size="32"
                            /></span>
                            <h2
                                class="font-heading text-2xl font-bold text-on-surface"
                                data-testid="guide-step-title"
                            >
                                {{ step.title }}
                            </h2>
                            <p
                                class="mt-4 text-lg leading-8 text-on-surface"
                                data-testid="guide-step-text"
                            >
                                {{ step.text }}
                            </p>
                            <div
                                v-if="step.timer_seconds"
                                class="mt-6 rounded-2xl border border-outline-glass bg-surface-container-low p-5"
                                data-testid="recipe-timer"
                            >
                                <p
                                    class="flex items-center gap-2 text-xs font-semibold text-on-surface-variant"
                                >
                                    <Timer :size="15" />{{ t('recipes.timer') }}
                                </p>
                                <p
                                    class="mt-2 font-heading text-4xl font-bold tabular-nums text-primary"
                                    role="timer"
                                    aria-live="off"
                                >
                                    {{ formatTimer(timerSeconds) }}
                                </p>
                                <p
                                    v-if="timerStarted && timerSeconds === 0"
                                    class="mt-3 text-sm font-semibold text-primary"
                                    role="status"
                                >
                                    {{ t('recipes.timer_done') }}
                                </p>
                                <div class="mt-4 flex gap-2">
                                    <Button
                                        variant="secondary"
                                        @click="
                                            guide.toggleTimer(
                                                step.key,
                                                step.timer_seconds,
                                            )
                                        "
                                        ><Pause
                                            v-if="running"
                                            :size="15"
                                        /><Play v-else :size="15" />{{
                                            t(
                                                running
                                                    ? 'recipes.pause_timer'
                                                    : timerStarted &&
                                                        timerSeconds > 0
                                                      ? 'recipes.resume_timer'
                                                      : 'recipes.start_timer',
                                            )
                                        }}</Button
                                    ><Button
                                        variant="ghost"
                                        :aria-label="t('recipes.reset_timer')"
                                        @click="guide.resetTimer(step.key)"
                                        ><RotateCcw :size="16"
                                    /></Button>
                                </div></div
                        ></template>
                    </div>
                    <div
                        v-if="!finished"
                        class="flex items-center justify-between gap-3 border-t border-outline-glass px-5 py-4 sm:px-7"
                    >
                        <Button
                            variant="secondary"
                            :disabled="stepIndex < 0"
                            @click="guide.previous"
                            ><ArrowLeft :size="15" />{{
                                t('recipes.previous')
                            }}</Button
                        ><Button @click="guide.next"
                            ><Check
                                v-if="stepIndex === selected.steps.length - 1"
                                :size="15" />{{
                                t(
                                    stepIndex < 0
                                        ? 'recipes.begin'
                                        : stepIndex ===
                                            selected.steps.length - 1
                                          ? 'recipes.finish'
                                          : 'recipes.next_step',
                                )
                            }}<ArrowRight
                                v-if="stepIndex < selected.steps.length - 1"
                                :size="15"
                        /></Button>
                    </div>
                </section>

                <div
                    v-if="selected.tips_html || recipe.notes_html"
                    class="recipe-copy rounded-2xl border border-outline-glass bg-primary/4 p-5 text-sm leading-6 text-on-surface-variant"
                >
                    <h2
                        class="mb-3 flex items-center gap-2 text-sm font-bold text-on-surface"
                    >
                        <Info :size="17" class="text-primary" />{{
                            t('recipes.notes')
                        }}
                    </h2>
                    <div
                        v-if="selected.tips_html"
                        v-html="selected.tips_html"
                    />
                    <div v-if="recipe.notes_html" v-html="recipe.notes_html" />
                </div>
                <RecipeToppingAdjustments
                    v-if="selected.topping_adjustments.length"
                    :components="selected.topping_adjustments"
                />
            </section>

            <section v-if="recipe.related.length" class="space-y-3">
                <h2 class="text-sm font-bold text-on-surface">
                    {{ t('recipes.related_preparations') }}
                </h2>
                <div class="flex flex-wrap gap-3">
                    <Link
                        v-for="item in recipe.related"
                        :key="item.key"
                        :href="item.url"
                        class="flex items-center gap-3 rounded-xl border border-outline-glass bg-white px-4 py-3 text-sm font-semibold text-primary"
                        >{{ item.name }}<ArrowRight :size="16"
                    /></Link>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.recipe-copy :deep(ul) {
    list-style: disc;
    padding-left: 1.2rem;
}
.recipe-copy :deep(p + p),
.recipe-copy :deep(li + li) {
    margin-top: 0.5rem;
}
</style>
