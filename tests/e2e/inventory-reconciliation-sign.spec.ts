import { expect, test } from '@playwright/test';

test('inventory reconciliation shows explicit increase and decrease signs', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);

    await page.goto('/inventory-counts');
    await page.getByRole('button', { name: 'Start inventory count' }).click();
    await page.waitForURL(/\/inventory-counts$/);
    await page.locator('[data-testid^="qty-"]').first().fill('2');
    await page.getByRole('button', { name: 'Save stock count' }).click();
    await page.waitForURL(/\/inventory-counts\/\d+$/);

    await page.goto('/inventory-counts');
    await page.getByRole('button', { name: 'Start inventory count' }).click();
    await page.waitForURL(/\/inventory-counts$/);
    const quantity = page.locator('[data-testid^="qty-"]').first();
    await quantity.fill('1');
    await quantity.blur();
    await expect(page.getByText('Saved').first()).toBeVisible();
    await page.reload();

    const increase = page.locator('[data-testid^="inc-"]').first();
    const decrease = page.locator('[data-testid^="dec-"]').first();
    const reason = page.locator('tbody select').first();
    const waitForAutosave = () =>
        page.waitForResponse(
            (response) =>
                response.request().method() === 'PUT' &&
                response.url().includes('/inventory-counts/drafts/'),
        );
    await Promise.all([waitForAutosave(), increase.click()]);
    await page.reload();
    await expect(quantity).toHaveValue('2');

    await Promise.all([waitForAutosave(), increase.click()]);
    await expect(reason).toHaveValue('inventory_correction');
    await Promise.all([waitForAutosave(), decrease.click()]);
    await Promise.all([waitForAutosave(), decrease.click()]);
    await page.reload();
    await expect(quantity).toHaveValue('1');
    await expect(reason).toHaveValue('consumption');

    await page.getByRole('button', { name: 'Save stock count' }).click();
    await page.waitForURL(/\/inventory-counts\/\d+$/);

    await page.goto('/stock-movements');
    const newestReconciliation = page
        .locator('tbody tr')
        .filter({ hasText: 'Inventory' })
        .first();
    await expect(newestReconciliation).toContainText(/-CZK\s*[\d,.]+/);
    await newestReconciliation.getByRole('link').first().click();
    await expect(
        page.getByRole('cell', { name: '-1', exact: true }),
    ).toBeVisible();
    await expect(page.getByText(/-CZK\s*[\d,.]+/).first()).toBeVisible();

    await page.goto('/stock-movements');
    const oldestReconciliation = page
        .locator('tbody tr')
        .filter({ hasText: 'Inventory' })
        .nth(1);
    await expect(oldestReconciliation).toContainText(/\+CZK\s*[\d,.]+/);
    await oldestReconciliation.getByRole('link').first().click();
    await expect(
        page.getByRole('cell', { name: '+2', exact: true }),
    ).toBeVisible();
    await expect(page.getByText(/\+CZK\s*[\d,.]+/).first()).toBeVisible();
});

test('inventory conflicts preserve both editors values and require explicit resolution', async ({
    page,
    context,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);
    await page.goto('/inventory-counts');
    await page.getByRole('button', { name: 'Start inventory count' }).click();
    await expect(page.locator('[data-testid^="qty-"]').first()).toBeVisible();
    const second = await context.newPage();
    await second.goto('/inventory-counts');
    const firstInput = page.locator('[data-testid^="qty-"]').first();
    const secondInput = second.locator('[data-testid^="qty-"]').first();
    await firstInput.fill('5');
    await firstInput.blur();
    await expect(
        page.getByText('Saved', { exact: true }).first(),
    ).toBeVisible();
    await secondInput.fill('9');
    await secondInput.blur();
    await expect(second.getByRole('alert')).toContainText('9');
    await expect(second.getByRole('alert')).toContainText('5');
    await second.getByRole('button', { name: 'Save stock count' }).click();
    await expect(
        second.getByRole('button', { name: 'Reapply my value' }),
    ).toBeVisible();
    await second.getByRole('button', { name: 'Reapply my value' }).click();
    await expect(
        second.getByText('Saved', { exact: true }).first(),
    ).toBeVisible();
    await second.reload();
    await expect(secondInput).toHaveValue('9');
    await firstInput.fill('7');
    await firstInput.blur();
    await page.getByRole('button', { name: 'Use saved value' }).click();
    await expect(firstInput).toHaveValue('9');
    await page.getByRole('button', { name: 'Save stock count' }).click();
    await page.waitForURL(/\/inventory-counts\/\d+$/);
    await second.close();
});
