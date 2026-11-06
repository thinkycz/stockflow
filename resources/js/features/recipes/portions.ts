import type { RecipeIngredient, RecipeVariant } from './types';

export function nextPortions(current: number, direction: -1 | 1): number {
    return direction === 1
        ? current === 0.5
            ? 1
            : current + 1
        : current <= 1
          ? 0.5
          : current - 1;
}

export function scaleIngredients(
    ingredients: RecipeIngredient[],
    portions: number,
): RecipeIngredient[] {
    return ingredients.map((ingredient) => ({
        ...ingredient,
        quantity_value:
            ingredient.quantity_value === null
                ? null
                : ingredient.quantity_value * portions,
        quantity_scaling:
            ingredient.quantity_scaling === null
                ? null
                : {
                      ...ingredient.quantity_scaling,
                      values: ingredient.quantity_scaling.values.map(
                          (value) => value * portions,
                      ),
                  },
    }));
}

export function scaleToppingAdjustments(
    components: RecipeVariant['topping_adjustments'],
    portions: number,
): RecipeVariant['topping_adjustments'] {
    return components.map((component) => ({
        ...component,
        base_quantity: component.base_quantity * portions,
        reduction_step: component.reduction_step * portions,
        two_toppings_quantity: component.two_toppings_quantity * portions,
        three_toppings_quantity: component.three_toppings_quantity * portions,
    }));
}

type Translate = (key: string, values: Record<string, string>) => string;

export function formatQuantity(
    ingredient: RecipeIngredient,
    locale: string,
    translate: Translate,
): string {
    const format = (value: number) =>
        new Intl.NumberFormat(locale, { maximumFractionDigits: 3 }).format(
            value,
        );
    const scaling = ingredient.quantity_scaling;
    let quantity =
        ingredient.quantity_value === null
            ? (ingredient.quantity_text ?? '')
            : format(ingredient.quantity_value);
    if (scaling) {
        const [first = 0, second = 0] = scaling.values;
        switch (scaling.kind) {
            case 'range':
                quantity = `${format(first)}–${format(second)}`;
                break;
            case 'alternatives':
                quantity = translate('recipes.quantities.alternatives', {
                    regular: format(first),
                    small: format(second),
                });
                break;
            case 'batch':
            case 'mixture':
                quantity = translate(`recipes.quantities.${scaling.kind}`, {
                    amount: format(first),
                    unit: scaling.unit ?? '',
                });
                break;
            case 'cups':
                quantity = translate(
                    `recipes.quantities.cups.${new Intl.PluralRules(locale).select(first)}`,
                    { amount: format(first) },
                );
                break;
            case 'serving_line':
                quantity = translate('recipes.quantities.serving_line', {});
                break;
        }
    }
    return quantity;
}
