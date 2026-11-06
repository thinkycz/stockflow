import { describe, expect, test } from 'vitest';
import { createI18n } from 'vue-i18n';
import {
    formatQuantity,
    nextPortions,
    scaleIngredients,
    scaleToppingAdjustments,
} from '@/features/recipes/portions';
import type { RecipeIngredient } from '@/features/recipes/types';
import en from '@/i18n/en.json';
import cs from '@/i18n/cs.json';
import sk from '@/i18n/sk.json';

function ingredient(
    value: number | null,
    scaling: RecipeIngredient['quantity_scaling'] = null,
): RecipeIngredient {
    return {
        key: 'milk',
        group: 'Matcha',
        name: 'water at 70–80 °C',
        quantity_value: value,
        quantity_text: value === null ? 'as needed' : null,
        quantity_scaling: scaling,
        unit: 'g',
        icon_group: 'water',
    };
}

test('portion controls offer half a portion followed by whole portions', () => {
    expect(nextPortions(1, -1)).toBe(0.5);
    expect(nextPortions(0.5, -1)).toBe(0.5);
    expect(nextPortions(0.5, 1)).toBe(1);
    expect(nextPortions(1, 1)).toBe(2);
    expect(nextPortions(3, -1)).toBe(2);
});

test.each([0.5, 1, 3])(
    'scales quantities from their original values for %s portions',
    (portions) => {
        const original = [
            ingredient(3.5),
            ingredient(null, { kind: 'range', values: [2, 3], unit: null }),
            ingredient(null),
        ];
        const scaled = scaleIngredients(original, portions);
        expect(scaled[0]?.quantity_value).toBe(3.5 * portions);
        expect(scaled[0]?.name).toBe('water at 70–80 °C');
        expect(scaled[1]?.quantity_scaling?.values).toEqual([
            2 * portions,
            3 * portions,
        ]);
        expect(scaled[2]?.quantity_text).toBe('as needed');
        expect(original[0]?.quantity_value).toBe(3.5);
        expect(scaleIngredients(original, 1)).toEqual(original);
        expect(scaleIngredients(original, 3)[0]?.quantity_value).toBe(10.5);
    },
);

test('topping reductions apply before scaling including the zero floor', () => {
    const original = [
        {
            ingredient_name: 'syrup',
            unit: 'g',
            reduction_step: 5,
            base_quantity: 8,
            two_toppings_quantity: 3,
            three_toppings_quantity: 0,
        },
    ];
    expect(scaleToppingAdjustments(original, 3)[0]).toMatchObject({
        base_quantity: 24,
        reduction_step: 15,
        two_toppings_quantity: 9,
        three_toppings_quantity: 0,
    });
    expect(
        scaleToppingAdjustments(original, 0.5)[0]?.two_toppings_quantity,
    ).toBe(1.5);
    expect(original[0]?.base_quantity).toBe(8);
});

for (const locale of ['en', 'cs', 'sk'] as const) {
    describe(locale, () => {
        const i18n = createI18n({
            legacy: false,
            locale,
            messages: { en, cs, sk },
        });
        const render = (
            scaling: RecipeIngredient['quantity_scaling'],
            portions = 3,
        ) =>
            formatQuantity(
                scaleIngredients([ingredient(null, scaling)], portions)[0]!,
                locale,
                (key, values) => i18n.global.t(key, values),
            );
        test('formats ranges alternatives and target volumes without parsing translated text', () => {
            expect(render({ kind: 'range', values: [2, 3], unit: null })).toBe(
                '6–9',
            );
            expect(
                render({ kind: 'alternatives', values: [1, 2], unit: null }),
            ).toBe(
                locale === 'en'
                    ? '3 regular or 6 small'
                    : locale === 'cs'
                      ? '3 běžné nebo 6 malé'
                      : '3 bežné alebo 6 malé',
            );
            expect(
                render({ kind: 'batch', values: [3.5], unit: 'L' }),
            ).toContain(locale === 'en' ? '10.5 L' : '10,5 L');
            expect(
                render({ kind: 'mixture', values: [300], unit: 'ml' }),
            ).toContain('900 ml');
            expect(
                render({ kind: 'serving_line', values: [], unit: null }),
            ).toContain(
                locale === 'en'
                    ? 'each cup'
                    : locale === 'cs'
                      ? 'každém kelímku'
                      : 'každom pohári',
            );
        });
        test('formats full and half cups with localized grammar and decimal separators', () => {
            expect(render({ kind: 'cups', values: [1], unit: null })).toBe(
                locale === 'en'
                    ? '3 full serving cups'
                    : locale === 'cs'
                      ? '3 plné kelímky'
                      : '3 plné poháre',
            );
            expect(render({ kind: 'cups', values: [1], unit: null }, 0.5)).toBe(
                locale === 'en'
                    ? '0.5 full serving cups'
                    : locale === 'cs'
                      ? '0,5 plného kelímku'
                      : '0,5 plného pohára',
            );
            expect(
                formatQuantity(ingredient(1.23456), locale, (key, values) =>
                    i18n.global.t(key, values),
                ),
            ).toBe(locale === 'en' ? '1.235' : '1,235');
        });
    });
}
