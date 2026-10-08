import { computed, onBeforeUnmount, ref, watch, type Ref } from 'vue';
import type { RecipeVariant } from './types';

type TimerState = {
    remaining: number;
    endsAt: number | null;
    started: boolean;
};

export function useRecipePreparation(variant: Ref<RecipeVariant | undefined>) {
    const checked = ref<string[]>([]);
    const stepIndex = ref(-1);
    const timers = ref<Record<string, TimerState>>({});
    const now = ref(Date.now());
    const tick = setInterval(() => {
        now.value = Date.now();
    }, 500);
    onBeforeUnmount(() => clearInterval(tick));
    watch(variant, () => {
        checked.value = [];
        stepIndex.value = -1;
        timers.value = {};
    });
    const step = computed(() => variant.value?.steps[stepIndex.value]);
    const finished = computed(
        () => !!variant.value && stepIndex.value === variant.value.steps.length,
    );
    const progress = computed(() =>
        variant.value
            ? Math.round(
                  (Math.max(0, stepIndex.value) / variant.value.steps.length) *
                      100,
              )
            : 0,
    );
    function seconds(key: string, duration: number): number {
        const timer = timers.value[key];
        if (!timer) return duration;
        return timer.endsAt === null
            ? timer.remaining
            : Math.max(0, Math.ceil((timer.endsAt - now.value) / 1000));
    }
    function toggleTimer(key: string, duration: number): void {
        now.value = Date.now();
        const timer = timers.value[key];
        if (
            timer?.endsAt !== null &&
            timer?.endsAt !== undefined &&
            seconds(key, duration) > 0
        ) {
            timers.value[key] = {
                remaining: seconds(key, duration),
                endsAt: null,
                started: true,
            };
        } else {
            const remaining = seconds(key, duration) || duration;
            timers.value[key] = {
                remaining,
                endsAt: now.value + remaining * 1000,
                started: true,
            };
        }
    }
    function resetTimer(key: string): void {
        delete timers.value[key];
    }
    function restart(): void {
        checked.value = [];
        stepIndex.value = -1;
        timers.value = {};
    }
    function next(): void {
        if (variant.value)
            stepIndex.value = Math.min(
                variant.value.steps.length,
                stepIndex.value + 1,
            );
    }
    function previous(): void {
        stepIndex.value = Math.max(-1, stepIndex.value - 1);
    }
    return {
        checked,
        stepIndex,
        step,
        finished,
        progress,
        timers,
        seconds,
        toggleTimer,
        resetTimer,
        restart,
        next,
        previous,
    };
}
