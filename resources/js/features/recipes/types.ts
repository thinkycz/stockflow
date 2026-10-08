export type RecipeCategory = {
    key: string;
    name: string;
    position: number;
    recipe_count: number;
};
export type RecipeLookupEntry = {
    key: string;
    name: string;
    category_name: string;
    category_key: string;
    aliases: string[];
    keywords: string[];
    url: string;
};
export type RecipeIngredient = {
    key: string;
    group: string;
    name: string;
    quantity_value: number | null;
    quantity_text: string | null;
    unit: string | null;
    icon_group: string;
};
export type RecipeStep = {
    key: string;
    title: string;
    text: string;
    action_key: string;
    timer_seconds: number | null;
};
export type RecipeVariant = {
    key: string;
    name: string;
    selectors: Record<string, string>;
    ingredients: RecipeIngredient[];
    steps: RecipeStep[];
    tips_html: string;
    topping_adjustments: Array<{
        ingredient_name: string;
        unit: string;
        base_quantity: number;
        two_toppings_quantity: number;
        three_toppings_quantity: number;
    }>;
};
export type RecipeSummary = {
    key: string;
    slug: string;
    name: string;
    category: RecipeCategory;
    position: number;
    summary: string;
    tags: string[];
    aliases: string[];
    equipment: string[];
    notes_html: string;
    related: Array<{ key: string; name: string; url: string }>;
    url: string;
    variant_count: number;
};
export type RecipeDocument = Omit<RecipeSummary, 'variant_count'> & {
    variants: RecipeVariant[];
};
