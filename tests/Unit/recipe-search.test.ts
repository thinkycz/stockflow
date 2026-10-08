import { describe, expect, test } from 'vitest';
import {
    nextVariant,
    normalizeRecipeSearch,
    searchRecipes,
} from '@/features/recipes/search';
import type {
    RecipeLookupEntry,
    RecipeVariant,
} from '@/features/recipes/types';

const entries: RecipeLookupEntry[] = [
    {
        key: 'matcha-latte/classic-matcha-latte',
        name: 'Classic Matcha Latte',
        category_key: 'matcha-latte',
        category_name: 'MATCHA LATTE',
        aliases: [],
        keywords: ['iced'],
        url: '/recipes/matcha-latte/classic-matcha-latte',
    },
    {
        key: 'hot-drinks/classic-matcha',
        name: 'Classic Matcha',
        category_key: 'hot-drinks',
        category_name: 'HOT DRINKS',
        aliases: ['Himawari Classic Matcha'],
        keywords: ['hot'],
        url: '/recipes/hot-drinks/classic-matcha',
    },
    {
        key: 'matcha-specials/strawberry-cloud',
        name: 'Strawberry Cloud',
        category_key: 'matcha-specials',
        category_name: 'MATCHA SPECIALS',
        aliases: [],
        keywords: ['iced'],
        url: '/recipes/matcha-specials/strawberry-cloud',
    },
    {
        key: 'hot-drinks/strawberry-cloud',
        name: 'Strawberry Cloud',
        category_key: 'hot-drinks',
        category_name: 'HOT DRINKS',
        aliases: [],
        keywords: ['hot'],
        url: '/recipes/hot-drinks/strawberry-cloud',
    },
    {
        key: 'preparations/creme-brulee',
        name: 'Crème Brûlée',
        category_key: 'preparations',
        category_name: 'PREPARATIONS',
        aliases: [],
        keywords: ['batch'],
        url: '/recipes/preparations/creme-brulee',
    },
    {
        key: 'milk-tea/combined',
        name: 'Ceylon/Jasmine/Oolong Milk Tea',
        category_key: 'milk-tea',
        category_name: 'MILK TEA',
        aliases: ['Oolong milk tea'],
        keywords: ['iced'],
        url: '/recipes/milk-tea/combined',
    },
];

describe('quick recipe lookup', () => {
    test('matches partial names and aliases while ranking exact names first', () => {
        expect(
            searchRecipes(entries, 'classic matcha').map((entry) => entry.key),
        ).toEqual([
            'hot-drinks/classic-matcha',
            'matcha-latte/classic-matcha-latte',
        ]);
        expect(searchRecipes(entries, 'ool')).toEqual([entries[5]]);
        expect(searchRecipes(entries, 'himawari classic')).toEqual([
            entries[1],
        ]);
    });
    test('ignores accents and punctuation and requires every search word', () => {
        expect(normalizeRecipeSearch(' CRÈME — BRÛLÉE ')).toBe('creme brulee');
        expect(searchRecipes(entries, 'creme brulee')).toEqual([entries[4]]);
        expect(searchRecipes(entries, 'hot straw')).toEqual([entries[3]]);
        expect(searchRecipes(entries, 'iced classic')).toEqual([entries[0]]);
        expect(searchRecipes(entries, 'hot missing')).toEqual([]);
    });
    test('keeps matching duplicate names distinguishable by category and stable URL', () => {
        expect(
            searchRecipes(entries, 'straw').map((entry) => [
                entry.category_name,
                entry.url,
            ]),
        ).toEqual([
            ['MATCHA SPECIALS', '/recipes/matcha-specials/strawberry-cloud'],
            ['HOT DRINKS', '/recipes/hot-drinks/strawberry-cloud'],
        ]);
        expect(searchRecipes(entries, '')).toEqual(entries);
    });
});

function variant(
    key: string,
    selectors: Record<string, string>,
): RecipeVariant {
    return {
        key,
        name: key,
        selectors,
        selector_labels: selectors,
        ingredients: [],
        steps: [],
        tips_html: '',
        topping_adjustments: [],
    };
}

test('changing one selector preserves other choices and never invents a combination', () => {
    const variants = [
        variant('ceylon-s-ice', {
            flavour: 'Ceylon',
            size: 'S',
            ice: 'with-ice',
        }),
        variant('ceylon-m-no-ice', {
            flavour: 'Ceylon',
            size: 'M',
            ice: 'no-ice',
        }),
        variant('jasmine-s-ice', {
            flavour: 'Jasmine',
            size: 'S',
            ice: 'with-ice',
        }),
        variant('jasmine-m-no-ice', {
            flavour: 'Jasmine',
            size: 'M',
            ice: 'no-ice',
        }),
    ];
    expect(nextVariant(variants, variants[1]!, 'flavour', 'Jasmine')).toBe(
        variants[3],
    );
    expect(nextVariant(variants, variants[3]!, 'ice', 'with-ice')).toBe(
        variants[2],
    );
    expect(nextVariant(variants, variants[0]!, 'size', 'L')).toBe(variants[0]);
});
