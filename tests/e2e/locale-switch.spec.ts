import { expect, test } from '@playwright/test';

test.describe('Locale switcher', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Email', { exact: true }).fill('test@test.com');
        await page.getByLabel('Password', { exact: true }).fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL(/\/dashboard/);
    });

    test.afterEach(async ({ page }) => {
        await page.goto('/settings');

        const switcher = page.locator('select#locale');
        await switcher.selectOption('en');
        await page
            .getByRole('button', {
                name: /Save profile|Uložit profil|Uložiť profil/,
            })
            .click();
    });

    test('switching the locale flips the nav and heading strings', async ({
        page,
    }) => {
        await expect(
            page.getByRole('heading', { name: 'Dashboard' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Log out' }),
        ).toBeVisible();

        await page.goto('/settings');

        const switcher = page.locator('select#locale');
        await switcher.selectOption('cs');
        await page.getByRole('button', { name: 'Save profile' }).click();

        await expect(
            page.getByRole('heading', { name: 'Nastavení' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Odhlásit se' }),
        ).toBeVisible();

        await switcher.selectOption('sk');
        await page.getByRole('button', { name: 'Uložit profil' }).click();

        await expect(
            page.getByRole('heading', { name: 'Nastavenia' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Odhlásiť sa' }),
        ).toBeVisible();

        await switcher.selectOption('en');
        await page.getByRole('button', { name: 'Uložiť profil' }).click();

        await expect(
            page.getByRole('heading', { name: 'Settings' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Log out' }),
        ).toBeVisible();
    });

    test('navigating to settings shows the localized page title', async ({
        page,
    }) => {
        await page.goto('/settings');

        const switcher = page.locator('select#locale');
        await switcher.selectOption('cs');
        await page.getByRole('button', { name: 'Save profile' }).click();

        await page.getByRole('link', { name: 'Nástěnka', exact: true }).click();
        await page.waitForURL(/\/dashboard$/);
        await page
            .getByRole('link', { name: 'Nastavení', exact: true })
            .click();
        await page.waitForURL(/\/settings$/);

        await expect(
            page.getByRole('heading', { name: 'Nastavení' }),
        ).toBeVisible();
    });
});
