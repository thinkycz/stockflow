import { expect, test } from '@playwright/test';

test.describe('Auth flow', () => {
    test('guest can view the login page', async ({ page }) => {
        await page.goto('/login');

        await expect(page).toHaveTitle(/Log in/);
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(
            page.getByLabel('Password', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Log in' }),
        ).toBeVisible();
    });

    test('public registration is unavailable', async ({ page }) => {
        const response = await page.goto('/register');
        expect(response?.status()).toBe(404);
    });

    test('login form links only to password recovery', async ({ page }) => {
        await page.goto('/login');

        await expect(
            page.getByRole('link', { name: 'Forgot password?' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Create account' }),
        ).toHaveCount(0);
    });

    test('forgot password page is reachable', async ({ page }) => {
        await page.goto('/forgot-password');

        await expect(page).toHaveTitle(/Forgot password/);
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Send password' }),
        ).toBeVisible();
    });
});
