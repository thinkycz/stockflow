<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import BackLink from '@/components/ui/BackLink.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { useRoute } from '@/composables/useRoute';

type Ingredient = {
    quantity_value: string | number;
    quantity_text: string;
    unit: string;
    name: string;
    icon_group: string;
    source_text: string;
};
type Step = { text: string; action_key: string; source_text: string };
type Variant = { name: string; ingredients: Ingredient[]; steps: Step[] };
type FormData = {
    category_id: string;
    name: string;
    note: string | null;
    variants: Variant[];
};

const props = defineProps<{
    recipe: {
        id: number;
        category_id: number;
        name: string;
        note: string | null;
        variants: Array<{
            name: string | null;
            ingredients?: Ingredient[];
            steps: Step[];
        }>;
    } | null;
    categories: Array<{ id: number; name: string }>;
}>();

const { t } = useI18n();
const route = useRoute();
const iconGroups = [
    'water_milk',
    'tea_matcha',
    'fruit',
    'syrup_sweetener',
    'powder',
    'milk_foam',
    'ice',
    'topping_garnish',
    'neutral',
];
const actionKeys = [
    'add',
    'mix',
    'stir',
    'whisk',
    'whip',
    'boil',
    'steep',
    'ice',
    'shake',
    'pour',
    'smash',
    'cook',
    'cover',
    'timer',
    'cool',
    'garnish',
    'serve',
    'wash',
    'other',
];

function blankIngredient(): Ingredient {
    return {
        quantity_value: '',
        quantity_text: '',
        unit: '',
        name: '',
        icon_group: 'neutral',
        source_text: '',
    };
}

function blankStep(): Step {
    return { text: '', action_key: 'other', source_text: '' };
}

function blankVariant(): Variant {
    return { name: '', ingredients: [], steps: [blankStep(), blankStep()] };
}

const form = useForm<FormData>({
    category_id: props.recipe ? String(props.recipe.category_id) : '',
    name: props.recipe?.name ?? '',
    note: props.recipe?.note ?? null,
    variants: props.recipe?.variants.map((variant) => ({
        name: variant.name ?? '',
        ingredients: (variant.ingredients ?? []).map((ingredient) => ({
            ...ingredient,
            quantity_text: ingredient.quantity_text ?? '',
            unit: ingredient.unit ?? '',
            source_text: ingredient.source_text ?? '',
        })),
        steps: variant.steps.map((step) => ({
            ...step,
            action_key: step.action_key ?? 'other',
            source_text: step.source_text ?? '',
        })),
    })) ?? [blankVariant()],
});

function move<T>(rows: T[], index: number, direction: -1 | 1): void {
    const target = index + direction;
    if (target < 0 || target >= rows.length) return;
    const [row] = rows.splice(index, 1);
    if (row !== undefined) rows.splice(target, 0, row);
}

function addVariant(): void {
    form.variants.push(blankVariant());
}

function removeVariant(index: number): void {
    if (form.variants.length > 1) form.variants.splice(index, 1);
}

function addIngredient(variant: Variant): void {
    variant.ingredients.push(blankIngredient());
}

function removeIngredient(variant: Variant, index: number): void {
    variant.ingredients.splice(index, 1);
}

function addStep(variant: Variant): void {
    variant.steps.push(blankStep());
}

function removeStep(variant: Variant, index: number): void {
    if (variant.steps.length > 2) variant.steps.splice(index, 1);
}

function submit(): void {
    if (props.recipe) form.put(route('recipes.update', props.recipe.id));
    else form.post(route('recipes.store'));
}
</script>

<template>
    <AppLayout :title="recipe ? t('recipes.edit') : t('recipes.create')">
        <div class="mx-auto max-w-5xl space-y-6">
            <header>
                <BackLink
                    :href="
                        recipe
                            ? route('recipes.show', recipe.id)
                            : route('recipes.index')
                    "
                    >{{ t('recipes.back') }}</BackLink
                >
                <h1
                    class="mt-3 font-heading text-2xl font-bold text-on-surface"
                >
                    {{ recipe ? t('recipes.edit') : t('recipes.create') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('recipes.form_help') }}
                </p>
            </header>

            <form class="space-y-5" @submit.prevent="submit">
                <Card class="space-y-5">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label for="recipe-name" required>{{
                                t('recipes.name')
                            }}</Label>
                            <Input
                                id="recipe-name"
                                v-model="form.name"
                                class="mt-1"
                                required
                            />
                            <FieldError :message="form.errors.name" />
                        </div>
                        <div>
                            <Label for="recipe-category" required>{{
                                t('recipes.category')
                            }}</Label>
                            <Select
                                id="recipe-category"
                                v-model="form.category_id"
                                class="mt-1"
                                :options="
                                    categories.map((category) => ({
                                        value: String(category.id),
                                        label: category.name,
                                    }))
                                "
                                :placeholder="t('recipes.choose_category')"
                                required
                            />
                            <FieldError :message="form.errors.category_id" />
                        </div>
                    </div>
                    <div>
                        <Label for="recipe-note">{{ t('recipes.note') }}</Label>
                        <Textarea
                            id="recipe-note"
                            v-model="form.note"
                            class="mt-1"
                            :rows="3"
                        />
                        <FieldError :message="form.errors.note" />
                    </div>
                </Card>

                <Card
                    v-for="(variant, variantIndex) in form.variants"
                    :key="variantIndex"
                    class="space-y-5"
                >
                    <div class="flex items-end gap-2">
                        <div class="min-w-0 flex-1">
                            <Label :for="`variant-${variantIndex}`">{{
                                t('recipes.variant_name')
                            }}</Label>
                            <Input
                                :id="`variant-${variantIndex}`"
                                v-model="variant.name"
                                class="mt-1"
                                :placeholder="t('recipes.default_variant')"
                            />
                        </div>
                        <Button
                            size="icon"
                            variant="ghost"
                            :disabled="variantIndex === 0"
                            :aria-label="t('common.move_up')"
                            @click="move(form.variants, variantIndex, -1)"
                            ><ArrowUp :size="15"
                        /></Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            :disabled="
                                variantIndex === form.variants.length - 1
                            "
                            :aria-label="t('common.move_down')"
                            @click="move(form.variants, variantIndex, 1)"
                            ><ArrowDown :size="15"
                        /></Button>
                        <Button
                            size="icon"
                            variant="danger"
                            :disabled="form.variants.length === 1"
                            :aria-label="t('recipes.remove_variant')"
                            @click="removeVariant(variantIndex)"
                            ><Trash2 :size="15"
                        /></Button>
                    </div>

                    <section class="space-y-3">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <div>
                                <h3
                                    class="font-heading text-base font-bold text-on-surface"
                                >
                                    {{ t('recipes.ingredients') }}
                                </h3>
                                <p class="text-xs text-on-surface-variant">
                                    {{ t('recipes.ingredients_help') }}
                                </p>
                            </div>
                            <Button
                                variant="secondary"
                                size="compact"
                                @click="addIngredient(variant)"
                                ><Plus :size="14" />{{
                                    t('recipes.add_ingredient')
                                }}</Button
                            >
                        </div>
                        <div
                            v-if="variant.ingredients.length"
                            class="space-y-3"
                        >
                            <div
                                v-for="(
                                    ingredient, ingredientIndex
                                ) in variant.ingredients"
                                :key="ingredientIndex"
                                class="rounded-xl border border-outline-glass bg-surface-container-low/50 p-3"
                            >
                                <div
                                    class="grid gap-2 sm:grid-cols-[110px_110px_minmax(0,1fr)_160px_auto] sm:items-end"
                                >
                                    <div>
                                        <Label
                                            :for="`ingredient-quantity-${variantIndex}-${ingredientIndex}`"
                                            >{{ t('recipes.quantity') }}</Label
                                        >
                                        <Input
                                            :id="`ingredient-quantity-${variantIndex}-${ingredientIndex}`"
                                            v-model="ingredient.quantity_value"
                                            type="number"
                                            step="0.001"
                                            class="mt-1"
                                            :placeholder="
                                                t(
                                                    'recipes.quantity_placeholder',
                                                )
                                            "
                                        />
                                    </div>
                                    <div>
                                        <Label
                                            :for="`ingredient-unit-${variantIndex}-${ingredientIndex}`"
                                            >{{ t('recipes.unit') }}</Label
                                        >
                                        <Input
                                            :id="`ingredient-unit-${variantIndex}-${ingredientIndex}`"
                                            v-model="ingredient.unit"
                                            class="mt-1"
                                            :placeholder="
                                                t('recipes.unit_placeholder')
                                            "
                                        />
                                    </div>
                                    <div>
                                        <Label
                                            :for="`ingredient-name-${variantIndex}-${ingredientIndex}`"
                                            required
                                            >{{
                                                t('recipes.ingredient_name')
                                            }}</Label
                                        >
                                        <Input
                                            :id="`ingredient-name-${variantIndex}-${ingredientIndex}`"
                                            v-model="ingredient.name"
                                            class="mt-1"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label
                                            :for="`ingredient-icon-${variantIndex}-${ingredientIndex}`"
                                            >{{
                                                t('recipes.icon_group')
                                            }}</Label
                                        >
                                        <Select
                                            :id="`ingredient-icon-${variantIndex}-${ingredientIndex}`"
                                            v-model="ingredient.icon_group"
                                            class="mt-1"
                                            :options="
                                                iconGroups.map((group) => ({
                                                    value: group,
                                                    label: t(
                                                        `recipes.icon_groups.${group}`,
                                                    ),
                                                }))
                                            "
                                        />
                                    </div>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        :aria-label="
                                            t('recipes.remove_ingredient')
                                        "
                                        @click="
                                            removeIngredient(
                                                variant,
                                                ingredientIndex,
                                            )
                                        "
                                        ><Trash2 :size="14"
                                    /></Button>
                                </div>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <Label
                                            :for="`ingredient-fallback-${variantIndex}-${ingredientIndex}`"
                                            >{{
                                                t('recipes.quantity_fallback')
                                            }}</Label
                                        >
                                        <Input
                                            :id="`ingredient-fallback-${variantIndex}-${ingredientIndex}`"
                                            v-model="ingredient.quantity_text"
                                            class="mt-1"
                                            :placeholder="
                                                t(
                                                    'recipes.quantity_fallback_placeholder',
                                                )
                                            "
                                        />
                                    </div>
                                    <div>
                                        <Label
                                            :for="`ingredient-source-${variantIndex}-${ingredientIndex}`"
                                            >{{
                                                t('recipes.source_wording')
                                            }}</Label
                                        >
                                        <Input
                                            :id="`ingredient-source-${variantIndex}-${ingredientIndex}`"
                                            v-model="ingredient.source_text"
                                            class="mt-1"
                                            :placeholder="
                                                t('recipes.source_optional')
                                            "
                                        />
                                    </div>
                                </div>
                                <div class="mt-2 flex gap-1">
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="size-8"
                                        :disabled="ingredientIndex === 0"
                                        :aria-label="t('common.move_up')"
                                        @click="
                                            move(
                                                variant.ingredients,
                                                ingredientIndex,
                                                -1,
                                            )
                                        "
                                        ><ArrowUp :size="13"
                                    /></Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="size-8"
                                        :disabled="
                                            ingredientIndex ===
                                            variant.ingredients.length - 1
                                        "
                                        :aria-label="t('common.move_down')"
                                        @click="
                                            move(
                                                variant.ingredients,
                                                ingredientIndex,
                                                1,
                                            )
                                        "
                                        ><ArrowDown :size="13"
                                    /></Button>
                                </div>
                            </div>
                        </div>
                        <p
                            v-else
                            class="rounded-xl border border-dashed border-outline-glass p-3 text-xs text-on-surface-variant"
                        >
                            {{ t('recipes.no_ingredients') }}
                        </p>
                    </section>

                    <section
                        class="space-y-3 border-t border-outline-glass pt-4"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <div>
                                <h3
                                    class="font-heading text-base font-bold text-on-surface"
                                >
                                    {{ t('recipes.procedure') }}
                                </h3>
                                <p class="text-xs text-on-surface-variant">
                                    {{ t('recipes.procedure_help') }}
                                </p>
                            </div>
                            <Button
                                variant="secondary"
                                size="compact"
                                @click="addStep(variant)"
                                ><Plus :size="14" />{{
                                    t('recipes.add_step')
                                }}</Button
                            >
                        </div>
                        <div class="space-y-3">
                            <div
                                v-for="(step, stepIndex) in variant.steps"
                                :key="stepIndex"
                                class="rounded-xl border border-outline-glass bg-surface-container-low/50 p-3"
                            >
                                <div
                                    class="grid gap-2 sm:grid-cols-[42px_180px_minmax(0,1fr)_auto] sm:items-end"
                                >
                                    <span
                                        class="flex size-8 items-center justify-center rounded-full bg-primary text-xs font-bold text-white"
                                        >{{ stepIndex + 1 }}</span
                                    >
                                    <div>
                                        <Label
                                            :for="`step-action-${variantIndex}-${stepIndex}`"
                                            >{{ t('recipes.action') }}</Label
                                        >
                                        <Select
                                            :id="`step-action-${variantIndex}-${stepIndex}`"
                                            v-model="step.action_key"
                                            class="mt-1"
                                            :options="
                                                actionKeys.map((action) => ({
                                                    value: action,
                                                    label: t(
                                                        `recipes.actions.${action}`,
                                                    ),
                                                }))
                                            "
                                        />
                                    </div>
                                    <div>
                                        <Label
                                            :for="`step-text-${variantIndex}-${stepIndex}`"
                                            required
                                            >{{ t('recipes.step_text') }}</Label
                                        >
                                        <Input
                                            :id="`step-text-${variantIndex}-${stepIndex}`"
                                            v-model="step.text"
                                            class="mt-1"
                                            required
                                        />
                                    </div>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        :disabled="variant.steps.length <= 2"
                                        :aria-label="t('recipes.remove_step')"
                                        @click="removeStep(variant, stepIndex)"
                                        ><Trash2 :size="14"
                                    /></Button>
                                </div>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <div class="flex gap-1">
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            class="size-8"
                                            :disabled="stepIndex === 0"
                                            :aria-label="t('common.move_up')"
                                            @click="
                                                move(
                                                    variant.steps,
                                                    stepIndex,
                                                    -1,
                                                )
                                            "
                                            ><ArrowUp :size="13"
                                        /></Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            class="size-8"
                                            :disabled="
                                                stepIndex ===
                                                variant.steps.length - 1
                                            "
                                            :aria-label="t('common.move_down')"
                                            @click="
                                                move(
                                                    variant.steps,
                                                    stepIndex,
                                                    1,
                                                )
                                            "
                                            ><ArrowDown :size="13"
                                        /></Button>
                                    </div>
                                    <div>
                                        <Label
                                            :for="`step-source-${variantIndex}-${stepIndex}`"
                                            >{{
                                                t('recipes.source_wording')
                                            }}</Label
                                        >
                                        <Input
                                            :id="`step-source-${variantIndex}-${stepIndex}`"
                                            v-model="step.source_text"
                                            class="mt-1"
                                            :placeholder="
                                                t('recipes.source_optional')
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </Card>

                <div class="flex flex-wrap justify-between gap-3">
                    <Button variant="secondary" @click="addVariant"
                        ><Plus :size="15" />{{
                            t('recipes.add_variant')
                        }}</Button
                    >
                    <div class="flex gap-2">
                        <Link
                            :href="
                                recipe
                                    ? route('recipes.show', recipe.id)
                                    : route('recipes.index')
                            "
                            ><Button variant="secondary">{{
                                t('common.cancel')
                            }}</Button></Link
                        >
                        <Button type="submit" :disabled="form.processing">{{
                            form.processing
                                ? t('common.saving')
                                : t('common.save')
                        }}</Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
