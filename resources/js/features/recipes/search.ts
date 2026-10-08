import type { RecipeLookupEntry, RecipeVariant } from './types';

export function normalizeRecipeSearch(value: string): string {
    return value
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

export function searchRecipes(
    entries: RecipeLookupEntry[],
    query: string,
): RecipeLookupEntry[] {
    const needle = normalizeRecipeSearch(query);
    const terms = needle.split(' ').filter(Boolean);
    if (!terms.length) return entries;
    return entries
        .map((entry, index) => {
            const name = normalizeRecipeSearch(entry.name);
            const aliases = entry.aliases.map(normalizeRecipeSearch);
            const text = [
                name,
                ...aliases,
                normalizeRecipeSearch(entry.category_name),
                ...entry.keywords.map(normalizeRecipeSearch),
            ].join(' ');
            const matches = terms.every((term) => text.includes(term));
            const rank =
                name === needle || aliases.includes(needle)
                    ? 0
                    : name.startsWith(needle) ||
                        aliases.some((alias) => alias.startsWith(needle))
                      ? 1
                      : terms.every((term) =>
                              text
                                  .split(' ')
                                  .some((word) => word.startsWith(term)),
                          )
                        ? 2
                        : 3;
            return { entry, index, matches, rank };
        })
        .filter((item) => item.matches)
        .sort((a, b) => a.rank - b.rank || a.index - b.index)
        .map((item) => item.entry);
}

export function nextVariant(
    variants: RecipeVariant[],
    current: RecipeVariant,
    dimension: string,
    value: string,
): RecipeVariant {
    return (
        variants
            .filter((variant) => variant.selectors[dimension] === value)
            .sort((a, b) => {
                const score = (variant: RecipeVariant) =>
                    Object.entries(current.selectors).filter(
                        ([key, selected]) =>
                            key !== dimension &&
                            variant.selectors[key] === selected,
                    ).length;
                return score(b) - score(a);
            })[0] ?? current
    );
}
