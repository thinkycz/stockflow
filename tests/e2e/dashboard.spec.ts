import { expect, test } from '@playwright/test';

test('stock status keeps every availability category in its own card', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);

    const monthlyFlow = page.locator('section').filter({
        has: page.getByRole('heading', { name: 'Monthly Flow' }),
    });
    const stockStatus = page.locator('section').filter({
        has: page.getByRole('heading', { name: 'Stock Status' }),
    });

    await expect(
        monthlyFlow.getByText('Insufficient data', { exact: true }),
    ).toHaveCount(0);
    await expect(
        stockStatus.getByText('Insufficient data', { exact: true }),
    ).toBeVisible();
});
