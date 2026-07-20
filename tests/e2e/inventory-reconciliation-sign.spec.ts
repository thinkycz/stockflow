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
    await page.locator('[data-testid^="qty-"]').first().fill('1');
    await page.getByRole('button', { name: 'Save stock count' }).click();
    await page.waitForURL(/\/inventory-counts\/\d+$/);

    await page.goto('/stock-movements');
    const newestReconciliation = page
        .locator('tbody tr')
        .filter({ hasText: 'Inventory reconciliation' })
        .first();
    await newestReconciliation.getByRole('link').first().click();
    await expect(
        page.getByRole('cell', { name: '-1', exact: true }),
    ).toBeVisible();

    await page.goto('/stock-movements');
    const oldestReconciliation = page
        .locator('tbody tr')
        .filter({ hasText: 'Inventory reconciliation' })
        .nth(1);
    await oldestReconciliation.getByRole('link').first().click();
    await expect(
        page.getByRole('cell', { name: '+2', exact: true }),
    ).toBeVisible();
});
