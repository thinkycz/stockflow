import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
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
export type RecipeEditorProps = {
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
};

export function useRecipeEditor(props: RecipeEditorProps) {
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
        return {
            name: '',
            instructions: [blankInstruction(), blankInstruction()],
        };
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

    function addInstruction(
        variant: Variant,
        type: 'ingredient' | 'action',
    ): void {
        variant.instructions.push(blankInstruction(type));
    }

    function removeInstruction(variant: Variant, index: number): void {
        if (variant.instructions.length > 2)
            variant.instructions.splice(index, 1);
    }

    function changeType(instruction: Instruction): void {
        instruction.action_key =
            instruction.type === 'ingredient' ? 'add' : 'other';
    }

    function rebuildIngredientText(instruction: Instruction): void {
        if (instruction.type !== 'ingredient') return;
        const quantity =
            instruction.quantity_text ||
            String(instruction.quantity_value || '');
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
    return {
        t,
        route,
        iconGroups,
        actionKeys,
        form,
        move,
        addVariant,
        removeVariant,
        addInstruction,
        removeInstruction,
        changeType,
        rebuildIngredientText,
        submit,
    };
}
