<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    CheckCircle2,
    GripVertical,
    RotateCcw,
    XCircle,
} from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import { useRoute } from '@/composables/useRoute';

type Step = { token: string; text: string };
const props = defineProps<{
    attempt: {
        id: number;
        recipe_id: number;
        recipe_name: string;
        variant_name: string | null;
        worker_name: string;
        steps: Step[];
    };
    result: { score: number; passed: boolean; correct_steps: string[] } | null;
}>();

const { t } = useI18n();
const route = useRoute();
const steps = ref(props.attempt.steps.map((step) => ({ ...step })));
const submitting = ref(false);
const dragIndex = ref<number | null>(null);
const touchIndex = ref<number | null>(null);

function move(index: number, target: number): void {
    if (target < 0 || target >= steps.value.length || index === target) return;
    const [step] = steps.value.splice(index, 1);
    if (step) steps.value.splice(target, 0, step);
}

function drop(target: number): void {
    if (dragIndex.value !== null) move(dragIndex.value, target);
    dragIndex.value = null;
}

function touchMove(event: TouchEvent): void {
    if (touchIndex.value === null) return;
    const touch = event.touches[0];
    if (!touch) return;
    const target = document
        .elementFromPoint(touch.clientX, touch.clientY)
        ?.closest<HTMLElement>('[data-step-index]');
    const targetIndex = target ? Number(target.dataset.stepIndex) : Number.NaN;
    if (Number.isInteger(targetIndex) && targetIndex !== touchIndex.value) {
        move(touchIndex.value, targetIndex);
        touchIndex.value = targetIndex;
    }
}

function submit(): void {
    submitting.value = true;
    router.put(
        route('recipe-tests.update', props.attempt.id),
        { tokens: steps.value.map((step) => step.token) },
        { preserveScroll: true, onFinish: () => (submitting.value = false) },
    );
}
</script>

<template>
    <AppLayout :title="t('recipes.test.title')">
        <div class="mx-auto max-w-3xl space-y-6">
            <header class="text-center">
                <p
                    class="text-xs font-bold tracking-widest text-primary uppercase"
                >
                    {{ t('recipes.test.title') }}
                </p>
                <h1
                    class="mt-2 font-heading text-2xl font-bold text-on-surface"
                >
                    {{ attempt.recipe_name }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ attempt.variant_name || t('recipes.default_variant') }} ·
                    {{ attempt.worker_name }}
                </p>
            </header>

            <Card v-if="result" class="text-center">
                <component
                    :is="result.passed ? CheckCircle2 : XCircle"
                    class="mx-auto"
                    :class="
                        result.passed ? 'text-emerald-600' : 'text-error-red'
                    "
                    :size="44"
                />
                <h2 class="mt-3 font-heading text-xl font-bold text-on-surface">
                    {{
                        result.passed
                            ? t('recipes.test.passed')
                            : t('recipes.test.failed')
                    }}
                </h2>
                <p class="mt-1 text-3xl font-bold text-primary">
                    {{ result.score }} %
                </p>
                <div class="mt-6 text-left">
                    <h3 class="text-sm font-bold text-on-surface">
                        {{ t('recipes.test.correct_order') }}
                    </h3>
                    <ol class="mt-3 space-y-2">
                        <li
                            v-for="(step, index) in result.correct_steps"
                            :key="index"
                            class="flex gap-3 rounded-xl bg-surface-container-low p-3 text-sm"
                        >
                            <span class="font-bold text-primary"
                                >{{ index + 1 }}.</span
                            >{{ step }}
                        </li>
                    </ol>
                </div>
                <div class="mt-6 flex justify-center gap-2">
                    <Button
                        variant="secondary"
                        @click="
                            router.get(route('recipes.show', attempt.recipe_id))
                        "
                        >{{ t('recipes.back_to_recipe') }}</Button
                    ><Button
                        @click="
                            router.get(route('recipes.show', attempt.recipe_id))
                        "
                        ><RotateCcw :size="15" />{{
                            t('recipes.test.try_again')
                        }}</Button
                    >
                </div>
            </Card>

            <template v-else>
                <Card>
                    <p class="mb-4 text-sm text-on-surface-variant">
                        {{ t('recipes.test.instructions') }}
                    </p>
                    <ol class="space-y-3">
                        <li
                            v-for="(step, index) in steps"
                            :key="step.token"
                            :data-step-index="index"
                            draggable="true"
                            class="flex items-center gap-2 rounded-xl border border-outline-glass bg-white p-3 shadow-sm transition hover:border-primary/30"
                            @dragstart="dragIndex = index"
                            @dragover.prevent
                            @drop="drop(index)"
                        >
                            <div
                                class="touch-none cursor-grab p-1 text-on-surface-variant"
                                @touchstart="touchIndex = index"
                                @touchmove.prevent="touchMove"
                                @touchend="touchIndex = null"
                            >
                                <GripVertical :size="18" />
                            </div>
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary"
                                >{{ index + 1 }}</span
                            >
                            <span
                                class="min-w-0 flex-1 text-sm text-on-surface"
                                >{{ step.text }}</span
                            >
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-8"
                                :disabled="index === 0"
                                :aria-label="t('common.move_up')"
                                @click="move(index, index - 1)"
                                ><ArrowUp :size="14"
                            /></Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-8"
                                :disabled="index === steps.length - 1"
                                :aria-label="t('common.move_down')"
                                @click="move(index, index + 1)"
                                ><ArrowDown :size="14"
                            /></Button>
                        </li>
                    </ol>
                </Card>
                <div class="flex justify-end">
                    <Button :disabled="submitting" @click="submit">{{
                        submitting
                            ? t('recipes.test.evaluating')
                            : t('recipes.test.submit')
                    }}</Button>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
