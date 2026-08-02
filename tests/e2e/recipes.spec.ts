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
    await expect(page.getByText('CLASSIC MATCHA LATTE')).toBeVisible();
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

test('limited account reads a recipe and submits a mobile reorder test', async ({
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
        .filter({ hasText: 'CLASSIC MATCHA LATTE' });
    await expect(classicRow).toBeVisible();
    await classicRow
        .getByRole('link', { name: 'CLASSIC MATCHA LATTE' })
        .click();
    await expect(page.getByRole('button', { name: 'Edit' })).toHaveCount(0);
    await expect(page.getByTestId('recipe-instruction')).toHaveCount(8);
    await expect(page.getByTestId('recipe-instruction').first()).toContainText(
        'Add 100 ml milk into cup',
    );

    await page.getByRole('button', { name: 'Start test' }).click();
    await page.getByLabel('Worker').selectOption({ label: 'E2E Worker' });
    await page.getByRole('button', { name: 'Start test' }).last().click();
    await expect(page.getByText('Recipe test')).toBeVisible();

    const rows = page.locator('[data-step-index]');
    expect(await rows.count()).toBeGreaterThan(1);
    await rows.first().getByRole('button', { name: 'Move down' }).click();
    await page.getByRole('button', { name: 'Submit for evaluation' }).click();
    await expect(page.getByText(/Test (passed|failed)/)).toBeVisible();
    await expect(page.getByText('Correct order')).toBeVisible();
});
