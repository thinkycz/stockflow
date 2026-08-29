import { expect, test } from '@playwright/test';

test.describe('Full user journey', () => {
    test('seeded administrator can view dashboard, settings, and log out', async ({
        page,
    }) => {
        await page.goto('/login');
        await page.getByLabel('Email').fill('test@test.com');
        await page.getByLabel('Password', { exact: true }).fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();

        await page.waitForURL(/\/dashboard/);
        await expect(
            page.getByRole('heading', { name: 'Noticeboard' }),
        ).toBeVisible();

        await page.goto('/settings');
        await page.getByRole('button', { name: 'Save profile' }).click();

        await page.waitForURL(/\/settings$/);

        await page.getByRole('button', { name: 'User menu' }).click();
        await page.getByRole('menuitem', { name: 'Log out' }).click();
        await page.waitForURL(/\/login|\/$/);
    });

    test('login form shows error for unknown user', async ({ page }) => {
        await page.goto('/login');

        await page.getByLabel('Email').fill('unknown-e2e@example.com');
        await page.getByLabel('Password', { exact: true }).fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();

        await expect(page.getByRole('alert').first()).toBeVisible();
    });
});
