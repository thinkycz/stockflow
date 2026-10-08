import { expect, test, type Page } from '@playwright/test';

async function login(page: Page, email = 'test@test.com'): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);
}

async function noOverflow(page: Page): Promise<void> {
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
}

async function capture(
    page: Page,
    filename: string,
    fullPage = true,
): Promise<void> {
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({
        path: `output/playwright/${filename}.png`,
        fullPage,
        animations: 'disabled',
    });
}

test('the visual library filters instantly and keyboard lookup opens the correct hot drink', async ({
    page,
}) => {
    await login(page);
    await expect(page.getByTestId('recipe-lookup')).toHaveCount(0);
    await page.getByTestId('nav-item-recipes').click();
    await expect(page.getByTestId('recipe-catalog-row')).toHaveCount(54);
    await expect(
        page.getByRole('button', {
            name: /create|edit|test|archive|manage categories/i,
        }),
    ).toHaveCount(0);
    await capture(page, 'recipes-library-desktop');
    const category = page.getByRole('button', {
        name: 'Browse categories',
        exact: true,
    });
    const drawer = page.getByRole('dialog', {
        name: 'Browse categories',
        exact: true,
    });
    await expect(
        page.getByTestId('recipe-lookup').getByRole('combobox'),
    ).toHaveCount(1);
    await category.click();
    await expect(drawer.getByRole('button')).toHaveCount(11);
    await expect(
        drawer.getByRole('button', { name: /^All recipes/ }),
    ).toHaveAttribute('aria-pressed', 'true');
    await capture(page, 'recipes-categories-desktop', false);
    await drawer.getByRole('button', { name: /^PREPARATIONS/ }).click();
    await expect(drawer).toHaveCount(0);
    await expect(page.getByTestId('recipe-catalog-row')).toHaveCount(10);
    await category.click();
    await drawer.getByRole('button', { name: /^HOT DRINKS/ }).click();
    await expect(drawer).toHaveCount(0);
    await expect(category).toHaveAttribute('aria-expanded', 'false');
    await expect(category).toBeFocused();
    await expect(page.getByTestId('recipe-catalog-row')).toHaveCount(5);
    await expect(page).toHaveURL(/category=hot-drinks/);
    await page.reload();
    await expect(category).toContainText('HOT DRINKS');
    await expect(page.getByTestId('recipe-catalog-row')).toHaveCount(5);
    const search = page.getByRole('combobox', { name: 'Find a recipe' });
    await search.fill('straw cloud');
    await expect(page.getByTestId('recipe-catalog-row')).toHaveCount(1);
    await page.getByRole('button', { name: 'Clear search' }).click();
    await expect(page.getByTestId('recipe-catalog-row')).toHaveCount(5);
    await category.click();
    await expect(
        drawer.getByRole('button', { name: /^HOT DRINKS/ }),
    ).toHaveAttribute('aria-pressed', 'true');
    await page.keyboard.press('Escape');
    await expect(drawer).toHaveCount(0);
    await expect(category).toBeFocused();
    await category.click();
    await drawer.getByRole('button', { name: /^All recipes/ }).click();
    await expect(page.getByTestId('recipe-catalog-row')).toHaveCount(54);
    await expect(page).not.toHaveURL(/category=/);
    await category.click();
    await drawer.getByRole('button', { name: /^HOT DRINKS/ }).click();
    await search.fill('straw cloud');
    await expect(page.getByRole('listbox').getByRole('option')).toHaveCount(2);
    await expect(
        page.getByRole('option', { name: /Strawberry Cloud HOT DRINKS/ }),
    ).toBeVisible();
    await search.press('ArrowDown');
    await search.press('Enter');
    await page.waitForURL('/recipes/hot-drinks/strawberry-cloud');
    await expect(
        page.getByRole('heading', { name: 'Strawberry Cloud', exact: true }),
    ).toBeVisible();
    const ingredients = page.getByTestId('recipe-ingredients');
    await expect(ingredients.getByText('140 g', { exact: true })).toBeVisible();
    await expect(ingredients.getByText('3.5 g', { exact: true })).toBeVisible();
    await expect(
        ingredients.getByText('2 pieces', { exact: true }),
    ).toBeVisible();
    await expect(page.getByTestId('recipe-method-step')).toHaveCount(12);
    await capture(page, 'recipes-hot-reference-desktop');
    await noOverflow(page);
});

for (const [locale, lookup, categoryLabel, guided, scoops] of [
    [
        'en',
        'Find a recipe',
        'Browse categories',
        'Guided preparation',
        '3 standard scoops',
    ],
    [
        'cs',
        'Najít recept',
        'Procházet kategorie',
        'Příprava krok za krokem',
        '3 standardní odměrky',
    ],
    [
        'sk',
        'Nájsť recept',
        'Prechádzať kategórie',
        'Príprava krok za krokom',
        '3 štandardné odmerky',
    ],
] as const) {
    test(`recipe quantities and preparation controls remain readable in ${locale}`, async ({
        page,
    }) => {
        await page.setViewportSize({ width: 320, height: 740 });
        await login(page, 'limited@test.com');
        await page.route('**/recipes**', async (route) => {
            const response = await route.fetch();
            const body = (await response.text())
                .replaceAll('"locale":"en"', `"locale":"${locale}"`)
                .replaceAll(
                    '&quot;locale&quot;:&quot;en&quot;',
                    `&quot;locale&quot;:&quot;${locale}&quot;`,
                );
            await route.fulfill({ response, body });
        });
        await page.goto('/recipes');
        const category = page.getByRole('button', {
            name: categoryLabel,
            exact: true,
        });
        await category.click();
        const drawer = page.getByRole('dialog', {
            name: categoryLabel,
            exact: true,
        });
        await expect(drawer.getByRole('button')).toHaveCount(11);
        await noOverflow(page);
        await capture(page, `recipes-categories-${locale}-small-mobile`, false);
        await drawer.getByRole('button', { name: /^PREPARATIONS/ }).click();
        await expect(drawer).toHaveCount(0);
        await expect(page.getByTestId('recipe-catalog-row')).toHaveCount(10);
        await category.click();
        await drawer.getByRole('button', { name: /^HOT DRINKS/ }).click();
        await expect(drawer).toHaveCount(0);
        await expect(category).toContainText('HOT DRINKS');
        await expect(category).toBeFocused();
        await expect(page.getByTestId('recipe-catalog-row')).toHaveCount(5);
        await noOverflow(page);
        await capture(page, `recipes-library-${locale}-small-mobile`);
        await page.goto('/recipes/hot-drinks/taro-milk-tea');
        await expect(
            page
                .getByTestId('recipe-ingredients')
                .getByText(scoops, { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('combobox', { name: lookup }),
        ).toBeVisible();
        await page.getByRole('tab', { name: guided, exact: true }).click();
        await expect(
            page.getByRole('tab', { name: guided, exact: true }),
        ).toHaveAttribute('aria-selected', 'true');
        await expect(
            page.getByRole('checkbox', { name: new RegExp(scoops) }),
        ).toBeVisible();
        const rowBounds = await page
            .getByTestId('recipe-ingredients')
            .locator('label')
            .evaluateAll((rows) =>
                rows.map((row) => {
                    const name = row.querySelector('span');
                    const amount = row.querySelector('strong');
                    if (!name || !amount)
                        throw new Error(
                            'Ingredient row is missing its name or amount.',
                        );
                    const range = document.createRange();
                    range.selectNodeContents(name);
                    return {
                        nameRight: range.getBoundingClientRect().right,
                        amountLeft: amount.getBoundingClientRect().left,
                    };
                }),
            );
        for (const row of rowBounds)
            expect(row.nameRight).toBeLessThanOrEqual(row.amountLeft);
        await noOverflow(page);
        await capture(page, `recipes-guided-${locale}-small-mobile`);
    });
}

test('variant selectors retain flavour size and ice choices with informational topping amounts', async ({
    page,
}) => {
    await login(page);
    await page.goto('/recipes/milk-tea/ceylon-jasmine-oolong-milk-tea');
    await page.getByRole('tab', { name: 'M', exact: true }).click();
    await page.getByRole('tab', { name: 'No ice', exact: true }).click();
    await page.getByRole('tab', { name: 'Jasmine', exact: true }).click();
    await expect(page.getByTestId('recipe-selected-variant')).toHaveText(
        'Jasmine — M — No ice',
    );
    await expect(
        page
            .getByTestId('recipe-ingredients')
            .getByText('2–3', { exact: true }),
    ).toBeVisible();
    await expect(page.getByText(/Top up with jasmine milk tea/)).toBeVisible();
    await page
        .getByText('Sweetness when adding toppings', { exact: true })
        .click();
    await expect(
        page.getByTestId('recipe-topping-component').first(),
    ).toContainText('liquid sugar');
    await expect(
        page.getByTestId('recipe-topping-component').first(),
    ).toContainText('30 ml');
    await noOverflow(page);
});

test('an employee finds a hot recipe and completes guided preparation on mobile', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, 'limited@test.com');
    await page.goto('/recipes');
    await page.getByRole('combobox', { name: 'Find a recipe' }).fill('banana');
    await page.getByRole('option', { name: /Banana Bread Matcha/ }).click();
    await page.waitForURL('/recipes/hot-drinks/banana-bread-matcha');
    await page
        .getByRole('tab', { name: 'Guided preparation', exact: true })
        .click();
    await page
        .getByRole('checkbox', { name: 'gingerbread syrup — 5 g' })
        .check();
    await expect(page.getByText('1 of 4 ingredients ready')).toBeVisible();
    await capture(page, 'recipes-gather-mobile');
    await page.getByRole('button', { name: 'Begin preparation' }).click();
    await expect(page.getByTestId('guide-step-text')).toContainText(
        '5 g gingerbread syrup',
    );
    await page.getByRole('button', { name: 'Done · Next' }).click();
    await page.getByRole('button', { name: 'Done · Next' }).click();
    await expect(page.getByTestId('guide-step-title')).toHaveText(
        'Steam the milk',
    );
    await page.getByRole('button', { name: 'Previous', exact: true }).click();
    await expect(page.getByTestId('guide-step-text')).toContainText(
        '200 g banana milk',
    );
    await page.getByRole('button', { name: 'Done · Next' }).click();
    await capture(page, 'recipes-guide-mobile');
    for (let position = 3; position < 8; position++)
        await page.getByRole('button', { name: 'Done · Next' }).click();
    await page.getByRole('button', { name: 'Finish', exact: true }).click();
    await expect(
        page.getByRole('heading', { name: 'Ready to serve' }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Make another' }).click();
    await expect(page.getByRole('checkbox').first()).not.toBeChecked();
    await noOverflow(page);
});

test('known preparation timers pause resume expire and reset without losing progress', async ({
    page,
}) => {
    await login(page);
    await page.clock.install();
    await page.goto('/recipes/preparations/ceylon-tea-preparation');
    await page
        .getByRole('tab', { name: 'Guided preparation', exact: true })
        .click();
    await page.getByRole('button', { name: 'Begin preparation' }).click();
    await page.getByRole('button', { name: 'Done · Next' }).click();
    await page.getByRole('button', { name: 'Done · Next' }).click();
    await expect(page.getByRole('timer')).toHaveText('10:00');
    await page.getByRole('button', { name: 'Start timer' }).click();
    await page.clock.fastForward(5000);
    await expect(page.getByRole('timer')).toHaveText('9:55');
    await page.getByRole('button', { name: 'Pause timer' }).click();
    await page.clock.fastForward(60000);
    await expect(page.getByRole('timer')).toHaveText('9:55');
    await page.getByRole('button', { name: 'Resume timer' }).click();
    await page.clock.fastForward(595000);
    await expect(page.getByRole('timer')).toHaveText('0:00');
    await expect(
        page.getByText('Time is up. Continue with the next step.'),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Reset timer' }).click();
    await expect(page.getByRole('timer')).toHaveText('10:00');
    await expect(page.getByTestId('guide-step-title')).toHaveText(
        'Heat the mixture',
    );
});

test('mobile search handles missing recipes escape and a second recipe on the detail page', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, 'limited@test.com');
    await page.goto('/recipes/hot-drinks/classic-matcha');
    const search = page.getByRole('combobox', { name: 'Find a recipe' });
    await search.fill('zzzz');
    await expect(page.getByText('No matching recipes')).toBeVisible();
    await search.press('Escape');
    await expect(page.getByRole('listbox')).toHaveCount(0);
    await search.fill('taro');
    await page
        .getByRole('option', { name: /Taro Milk Tea HOT DRINKS/ })
        .click();
    await page.waitForURL('/recipes/hot-drinks/taro-milk-tea');
    await expect(search).toHaveValue('');
    await expect(
        page
            .getByTestId('recipe-ingredients')
            .getByText('3 standard scoops', { exact: true }),
    ).toBeVisible();
    await noOverflow(page);
});

test('classic matcha lists a full cup of ice for iced drinks and the authored chilling cubes for no ice', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, 'limited@test.com');
    await page.goto('/recipes/matcha-latte/classic-matcha-latte');
    const ingredients = page.getByTestId('recipe-ingredients');
    for (const [size, icedMilk, noIceMilk] of [
        ['S', '100 ml', '150 ml'],
        ['M', '140 ml', '240 ml'],
    ] as const) {
        await page.getByRole('tab', { name: size, exact: true }).click();
        await page.getByRole('tab', { name: 'With ice', exact: true }).click();
        await expect(
            ingredients.getByText('full serving cup', { exact: true }),
        ).toBeVisible();
        await expect(
            ingredients.getByText(icedMilk, { exact: true }),
        ).toBeVisible();
        await page.getByRole('tab', { name: 'No ice', exact: true }).click();
        await expect(
            ingredients.getByText('2–3', { exact: true }),
        ).toBeVisible();
        await expect(
            ingredients.getByText(noIceMilk, { exact: true }),
        ).toBeVisible();
    }
    await page.getByRole('tab', { name: 'With ice', exact: true }).click();
    await page
        .getByRole('tab', { name: 'Guided preparation', exact: true })
        .click();
    await expect(
        page.getByRole('checkbox', { name: 'ice cubes — full serving cup' }),
    ).toBeVisible();
    await capture(page, 'recipes-classic-ice-mobile');
    await page.goto('/recipes/milk-tea/taro-coco-milk-tea');
    await expect(
        ingredients.getByText('coconut milk', { exact: true }),
    ).toBeVisible();
    await expect(
        ingredients.getByText('to 300 ml total mixture', { exact: true }),
    ).toBeVisible();
    await page.getByRole('tab', { name: 'No ice', exact: true }).click();
    await expect(
        ingredients.getByText('to serving line', { exact: true }),
    ).toBeVisible();
    await noOverflow(page);
});
