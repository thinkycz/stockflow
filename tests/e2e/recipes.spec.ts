import { expect, test } from '@playwright/test';

async function login(
    page: import('@playwright/test').Page,
    email: string,
): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);
}

function normalized(value: string): string {
    return value.replace(/\s+/g, ' ').trim().toLowerCase();
}

function instructionKey(text: string): { key: string; amount: string | null } {
    const match = normalized(text).match(/^add ([\d.,]+) (g|ml) (.+)$/);
    if (!match) return { key: `text|${normalized(text)}`, amount: null };
    const [, amount = '', unit = '', remainder = ''] = match;
    const [ingredient = '', target = ''] = remainder.split(' into ');
    return {
        key: `amount|${unit}|${ingredient}|${target}`,
        amount,
    };
}

async function completeCurrentRecipe(
    page: import('@playwright/test').Page,
): Promise<void> {
    const recipeName =
        (await page.getByTestId('session-recipe-name').textContent())?.trim() ??
        '';
    const variantLabel = page.getByTestId('session-variant-name');
    const variantName =
        (await variantLabel.count()) > 0
            ? ((await variantLabel.textContent())?.trim() ?? '')
            : '';
    const reference = await page.context().newPage();
    await reference.goto(`/recipes?search=${encodeURIComponent(recipeName)}`);
    await reference
        .getByRole('link', { name: recipeName, exact: true })
        .click();
    if (variantName) {
        await reference
            .getByRole('tab', { name: variantName, exact: true })
            .click();
    }
    const correct = (
        await reference
            .getByTestId('recipe-instruction')
            .evaluateAll((elements) =>
                elements.map(
                    (element) => element.lastElementChild?.textContent ?? '',
                ),
            )
    ).map(instructionKey);
    await reference.close();

    const rows = page.getByTestId('session-instruction');
    for (let target = 0; target < correct.length; target += 1) {
        const keys = await rows.evaluateAll((elements) =>
            elements.map((element) => {
                const text = element.getAttribute('data-instruction-text');
                if (text)
                    return `text|${text.replace(/\s+/g, ' ').trim().toLowerCase()}`;
                return [
                    'amount',
                    element.getAttribute('data-instruction-unit') ?? '',
                    element.getAttribute('data-instruction-ingredient') ?? '',
                    element.getAttribute('data-instruction-target') ?? '',
                ]
                    .map((value) =>
                        value.replace(/\s+/g, ' ').trim().toLowerCase(),
                    )
                    .join('|');
            }),
        );
        let current = keys.indexOf(correct[target]?.key ?? '', target);
        expect(current).toBeGreaterThanOrEqual(target);
        while (current > target) {
            await rows
                .nth(current)
                .getByRole('button', { name: 'Move up' })
                .click();
            current -= 1;
        }
        if (correct[target]?.amount !== null) {
            await rows
                .nth(target)
                .getByTestId('amount-input')
                .fill(correct[target]?.amount ?? '');
        }
    }
}

test('admin browses recipes in the required sidebar position and opens results', async ({
    page,
}) => {
    await login(page, 'test@test.com');

    const storeNavItems = page
        .getByTestId('nav-section-store')
        .locator('[data-testid^="nav-item-"]');
    const keys = await storeNavItems.evaluateAll((items) =>
        items.map((item) => item.getAttribute('data-testid')),
    );
    expect(keys.indexOf('nav-item-recipes')).toBe(
        keys.indexOf('nav-item-checklists') + 1,
    );

    await page.getByTestId('nav-item-recipes').click();
    await expect(
        page.getByRole('heading', { name: 'Recipes', exact: true }),
    ).toBeVisible();
    await expect(page.getByText('Classic Matcha Latte')).toBeVisible();
    await page.getByRole('button', { name: 'Manage categories' }).click();
    await expect(
        page.getByRole('heading', { name: 'Recipe categories', exact: true }),
    ).toBeVisible();
    await page.getByRole('link', { name: 'Back to recipes' }).click();
    await page.getByRole('button', { name: 'Test results' }).click();
    await expect(
        page.getByRole('heading', { name: 'Test results', exact: true }),
    ).toBeVisible();
});

test('limited account reads a recipe and submits a three-recipe mobile test', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, 'limited@test.com');
    await page.getByRole('button', { name: 'Menu' }).click();
    await page
        .locator('#mobile-nav-drawer')
        .getByTestId('nav-item-recipes')
        .click();
    const classicRow = page
        .getByTestId('recipe-catalog-row')
        .filter({ hasText: 'Classic Matcha Latte' });
    await expect(classicRow).toBeVisible();
    await classicRow
        .getByRole('link', { name: 'Classic Matcha Latte' })
        .click();
    await expect(page.getByRole('button', { name: 'Edit' })).toHaveCount(0);
    await expect(page.getByTestId('recipe-instruction')).toHaveCount(8);
    await expect(page.getByTestId('recipe-instruction').first()).toContainText(
        'Add 100 ml milk into cup',
    );

    await expect(page.getByRole('button', { name: 'Start test' })).toHaveCount(
        0,
    );
    await page.getByRole('link', { name: 'Back to recipes' }).click();
    await page.getByRole('button', { name: 'Start test' }).click();
    await page.getByLabel('Worker').selectOption({ label: 'E2E Worker' });
    await page.getByRole('button', { name: 'Start test' }).last().click();
    await expect(page.getByText('Three-recipe test')).toBeVisible();

    for (let position = 1; position <= 3; position += 1) {
        await expect(
            page.getByText(`${position}/3`, { exact: true }),
        ).toBeVisible();
        const rows = page.getByTestId('session-instruction');
        expect(await rows.count()).toBeGreaterThan(1);
        await rows.first().getByRole('button', { name: 'Move down' }).click();
        for (const input of await page.getByTestId('amount-input').all()) {
            await input.fill('0');
        }
        if (position < 3) {
            await page.getByRole('button', { name: 'Next' }).click();
        }
    }
    await page.getByRole('button', { name: 'Submit all recipes' }).click();
    await expect(page.getByText('Test failed')).toBeVisible();
    await expect(page.getByText('Combined score')).toBeVisible();
});

test('limited account can pass all three recipes with exact amounts', async ({
    page,
}) => {
    await login(page, 'limited@test.com');
    await page.goto('/recipes');
    await page.getByRole('button', { name: 'Start test' }).click();
    await page.getByLabel('Worker').selectOption({ label: 'E2E Worker' });
    await page.getByRole('button', { name: 'Start test' }).last().click();

    for (let position = 1; position <= 3; position += 1) {
        await completeCurrentRecipe(page);
        if (position < 3) {
            await page.getByRole('button', { name: 'Next' }).click();
        }
    }
    await page.getByRole('button', { name: 'Submit all recipes' }).click();
    await expect(page.getByText('Test passed')).toBeVisible();
    await expect(page.getByText('Combined score: 100 %')).toBeVisible();
});
