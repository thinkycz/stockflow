import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from '@/composables/useRoute';

type Instruction = {
    token: string;
    instruction_id: number | null;
    type: 'ingredient' | 'action';
    text: string | null;
    action_key: string;
    icon_group: string;
    requires_amount: boolean;
    unit: string | null;
    ingredient_name: string | null;
    target: string | null;
    correct_text?: string;
    correct_amount?: string | null;
    submitted_amount?: string | null;
};

type SessionRecipe = {
    attempt_id: number;
    position: number;
    recipe_name: string;
    variant_name: string | null;
    instructions: Instruction[];
    submitted_tokens: string[] | null;
    correct_tokens: string[] | null;
    result: {
        score: number;
        passed: boolean;
        order_score: number;
        amount_score: number;
    } | null;
};
export type RecipeTestSessionProps = {
    session: { id: number; worker_name: string; submitted: boolean };
    recipes: SessionRecipe[];
    result: { score: number; passed: boolean } | null;
};

export function useRecipeTestSession(props: RecipeTestSessionProps) {
    const { t } = useI18n();

    const route = useRoute();

    const currentIndex = ref(0);

    const submitting = ref(false);

    const draggedIndex = ref<number | null>(null);

    const answers = ref(
        props.recipes.map((recipe) => ({
            attempt_id: recipe.attempt_id,
            tokens: [
                ...recipe.instructions.map((instruction) => instruction.token),
            ],
            amounts: Object.fromEntries(
                recipe.instructions
                    .filter((instruction) => instruction.requires_amount)
                    .map((instruction) => [instruction.token, '']),
            ),
        })),
    );

    const currentRecipe = computed(() => props.recipes[currentIndex.value]);

    const currentAnswer = computed(() => answers.value[currentIndex.value]);

    const instructionByToken = computed(() =>
        Object.fromEntries(
            (currentRecipe.value?.instructions ?? []).map((instruction) => [
                instruction.token,
                instruction,
            ]),
        ),
    );

    const orderedInstructions = computed(() =>
        (currentAnswer.value?.tokens ?? []).map(
            (token) => instructionByToken.value[token],
        ),
    );

    const currentComplete = computed(() =>
        Object.values(currentAnswer.value?.amounts ?? {}).every(
            (amount) => amount.trim() !== '',
        ),
    );

    const allComplete = computed(() =>
        answers.value.every((answer) =>
            Object.values(answer.amounts).every(
                (amount) => amount.trim() !== '',
            ),
        ),
    );

    function move(index: number, direction: -1 | 1): void {
        const target = index + direction;
        const tokens = currentAnswer.value?.tokens;
        if (!tokens || target < 0 || target >= tokens.length) return;
        [tokens[index], tokens[target]] = [tokens[target], tokens[index]];
    }

    function drop(targetIndex: number): void {
        const sourceIndex = draggedIndex.value;
        const tokens = currentAnswer.value?.tokens;
        draggedIndex.value = null;
        if (!tokens || sourceIndex === null || sourceIndex === targetIndex)
            return;
        const [token] = tokens.splice(sourceIndex, 1);
        if (token) tokens.splice(targetIndex, 0, token);
    }

    function submit(): void {
        if (!allComplete.value || submitting.value) return;
        submitting.value = true;
        router.put(
            route('recipe-test-sessions.update', props.session.id),
            { answers: answers.value },
            { onFinish: () => (submitting.value = false) },
        );
    }

    function correctInstructions(recipe: SessionRecipe): Instruction[] {
        const byToken = Object.fromEntries(
            recipe.instructions.map((instruction) => [
                instruction.token,
                instruction,
            ]),
        );
        return (recipe.correct_tokens ?? []).map((token) => byToken[token]);
    }
    return {
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
    };
}
