import { expect, test } from '@playwright/test';

test('limited user sees and closes every active attendance while saving today', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('limited@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);

    await page.goto('/statements');
    await page.getByRole('button', { name: "Save today's data" }).click();

    const modal = page.getByRole('heading', {
        name: 'Close active attendances?',
    });
    await expect(modal).toBeVisible();
    await expect(
        page.getByText('Active Employee', { exact: true }),
    ).toBeVisible();
    await expect(page.getByText('E2E Worker', { exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Save and close all' }).click();

    await expect(modal).toBeHidden();
    await expect(
        page.getByRole('status').filter({ hasText: 'Statement saved.' }),
    ).toBeVisible();
});
