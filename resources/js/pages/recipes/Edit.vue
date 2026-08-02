<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
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

type Instruction = {
    type: 'ingredient' | 'action';
    text: string;
    action_key: string;
    quantity_value: string | number;
    quantity_text: string;
    unit: string;
    ingredient_name: string;
    target: string;
    icon_group: string;
};
type Variant = { name: string; instructions: Instruction[] };

const props = defineProps<{
    recipe: {
        id: number;
        category_id: number;
        name: string;
        note: string | null;
        variants: Array<{
            name: string | null;
            instructions: Array<{
                type: 'ingredient' | 'action';
                text: string;
                action_key: string;
                quantity_value: number | string | null;
                quantity_text: string | null;
                unit: string | null;
                ingredient_name: string | null;
                target: string | null;
                icon_group: string;
            }>;
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

function blankInstruction(
    type: 'ingredient' | 'action' = 'action',
): Instruction {
    return {
        type,
        text: '',
        action_key: type === 'ingredient' ? 'add' : 'other',
        quantity_value: '',
        quantity_text: '',
        unit: '',
        ingredient_name: '',
        target: '',
        icon_group: 'neutral',
    };
}

function blankVariant(): Variant {
    return { name: '', instructions: [blankInstruction(), blankInstruction()] };
}

const form = useForm({
    category_id: props.recipe ? String(props.recipe.category_id) : '',
    name: props.recipe?.name ?? '',
    note: props.recipe?.note ?? '',
    variants: props.recipe
        ? props.recipe.variants.map((variant) => ({
              name: variant.name ?? '',
              instructions: variant.instructions.map((instruction) => ({
                  type: instruction.type,
                  text: instruction.text,
                  action_key: instruction.action_key,
                  quantity_value: instruction.quantity_value ?? '',
                  quantity_text: instruction.quantity_text ?? '',
                  unit: instruction.unit ?? '',
                  ingredient_name: instruction.ingredient_name ?? '',
                  target: instruction.target ?? '',
                  icon_group: instruction.icon_group,
              })),
          }))
        : [blankVariant()],
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
function addInstruction(variant: Variant, type: 'ingredient' | 'action'): void {
    variant.instructions.push(blankInstruction(type));
}
function removeInstruction(variant: Variant, index: number): void {
    if (variant.instructions.length > 2) variant.instructions.splice(index, 1);
}
function changeType(instruction: Instruction): void {
    instruction.action_key =
        instruction.type === 'ingredient' ? 'add' : 'other';
}
function rebuildIngredientText(instruction: Instruction): void {
    if (instruction.type !== 'ingredient') return;
    const quantity =
        instruction.quantity_text || String(instruction.quantity_value || '');
    const amount = [quantity, instruction.unit].filter(Boolean).join(' ');
    instruction.text = [
        'Add',
        amount,
        instruction.ingredient_name,
        instruction.target ? 'into ' + instruction.target : '',
    ]
        .filter(Boolean)
        .join(' ');
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
                >
                    {{ t('recipes.back') }}
                </BackLink>
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
                    </div>
                </Card>

                <Card
                    v-for="(variant, variantIndex) in form.variants"
                    :key="variantIndex"
                    class="space-y-4"
                >
                    <div class="flex items-end gap-2">
                        <div class="min-w-0 flex-1">
                            <Label :for="'variant-' + variantIndex">{{
                                t('recipes.variant_name')
                            }}</Label>
                            <Input
                                :id="'variant-' + variantIndex"
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
                        >
                            <ArrowUp :size="15" />
                        </Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            :disabled="
                                variantIndex === form.variants.length - 1
                            "
                            :aria-label="t('common.move_down')"
                            @click="move(form.variants, variantIndex, 1)"
                        >
                            <ArrowDown :size="15" />
                        </Button>
                        <Button
                            size="icon"
                            variant="danger"
                            :disabled="form.variants.length === 1"
                            :aria-label="t('recipes.remove_variant')"
                            @click="removeVariant(variantIndex)"
                        >
                            <Trash2 :size="15" />
                        </Button>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="(
                                instruction, instructionIndex
                            ) in variant.instructions"
                            :key="instructionIndex"
                            class="rounded-xl border border-outline-glass bg-surface-container-low/40 p-3"
                        >
                            <div class="flex flex-wrap items-end gap-2">
                                <span
                                    class="mb-1 flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-white"
                                >
                                    {{ instructionIndex + 1 }}
                                </span>
                                <div class="w-36">
                                    <Label>{{
                                        t('recipes.instruction_type')
                                    }}</Label>
                                    <Select
                                        v-model="instruction.type"
                                        class="mt-1"
                                        :options="[
                                            {
                                                value: 'ingredient',
                                                label: t(
                                                    'recipes.instruction_types.ingredient',
                                                ),
                                            },
                                            {
                                                value: 'action',
                                                label: t(
                                                    'recipes.instruction_types.action',
                                                ),
                                            },
                                        ]"
                                        @change="changeType(instruction)"
                                    />
                                </div>
                                <div class="min-w-60 flex-1">
                                    <Label required>{{
                                        t('recipes.instruction_text')
                                    }}</Label>
                                    <Input
                                        v-model="instruction.text"
                                        class="mt-1"
                                        required
                                    />
                                </div>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    :disabled="instructionIndex === 0"
                                    :aria-label="t('common.move_up')"
                                    @click="
                                        move(
                                            variant.instructions,
                                            instructionIndex,
                                            -1,
                                        )
                                    "
                                >
                                    <ArrowUp :size="14" />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    :disabled="
                                        instructionIndex ===
                                        variant.instructions.length - 1
                                    "
                                    :aria-label="t('common.move_down')"
                                    @click="
                                        move(
                                            variant.instructions,
                                            instructionIndex,
                                            1,
                                        )
                                    "
                                >
                                    <ArrowDown :size="14" />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    :disabled="variant.instructions.length <= 2"
                                    :aria-label="
                                        t('recipes.remove_instruction')
                                    "
                                    @click="
                                        removeInstruction(
                                            variant,
                                            instructionIndex,
                                        )
                                    "
                                >
                                    <Trash2 :size="14" />
                                </Button>
                            </div>

                            <div
                                v-if="instruction.type === 'ingredient'"
                                class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-6"
                            >
                                <div>
                                    <Label>{{ t('recipes.quantity') }}</Label>
                                    <Input
                                        v-model="instruction.quantity_value"
                                        type="number"
                                        step="0.001"
                                        class="mt-1"
                                        @input="
                                            rebuildIngredientText(instruction)
                                        "
                                    />
                                </div>
                                <div>
                                    <Label>{{
                                        t('recipes.quantity_fallback')
                                    }}</Label>
                                    <Input
                                        v-model="instruction.quantity_text"
                                        class="mt-1"
                                        @input="
                                            rebuildIngredientText(instruction)
                                        "
                                    />
                                </div>
                                <div>
                                    <Label>{{ t('recipes.unit') }}</Label>
                                    <Input
                                        v-model="instruction.unit"
                                        class="mt-1"
                                        @input="
                                            rebuildIngredientText(instruction)
                                        "
                                    />
                                </div>
                                <div>
                                    <Label>{{
                                        t('recipes.ingredient_name')
                                    }}</Label>
                                    <Input
                                        v-model="instruction.ingredient_name"
                                        class="mt-1"
                                        @input="
                                            rebuildIngredientText(instruction)
                                        "
                                    />
                                </div>
                                <div>
                                    <Label>{{ t('recipes.target') }}</Label>
                                    <Input
                                        v-model="instruction.target"
                                        class="mt-1"
                                        @input="
                                            rebuildIngredientText(instruction)
                                        "
                                    />
                                </div>
                                <div>
                                    <Label>{{ t('recipes.icon_group') }}</Label>
                                    <Select
                                        v-model="instruction.icon_group"
                                        class="mt-1"
                                        :options="
                                            iconGroups.map((group) => ({
                                                value: group,
                                                label: t(
                                                    'recipes.icon_groups.' +
                                                        group,
                                                ),
                                            }))
                                        "
                                    />
                                </div>
                            </div>
                            <div v-else class="mt-3 max-w-xs">
                                <Label>{{ t('recipes.action') }}</Label>
                                <Select
                                    v-model="instruction.action_key"
                                    class="mt-1"
                                    :options="
                                        actionKeys.map((action) => ({
                                            value: action,
                                            label: t(
                                                'recipes.actions.' + action,
                                            ),
                                        }))
                                    "
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            variant="secondary"
                            size="compact"
                            @click="addInstruction(variant, 'ingredient')"
                        >
                            <Plus :size="14" />{{
                                t('recipes.add_ingredient_instruction')
                            }}
                        </Button>
                        <Button
                            variant="secondary"
                            size="compact"
                            @click="addInstruction(variant, 'action')"
                        >
                            <Plus :size="14" />{{
                                t('recipes.add_action_instruction')
                            }}
                        </Button>
                    </div>
                </Card>

                <div class="flex flex-wrap justify-between gap-3">
                    <Button variant="secondary" @click="addVariant">
                        <Plus :size="15" />{{ t('recipes.add_variant') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? t('common.saving')
                                : t('common.save')
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
