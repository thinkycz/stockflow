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
            page.getByRole('heading', { name: 'Noticeboard' }),
        ).toBeVisible();
        await page.getByRole('button', { name: 'User menu' }).click();
        await expect(
            page.getByRole('menuitem', { name: 'Log out' }),
        ).toBeVisible();

        await page.goto('/settings');

        const switcher = page.locator('select#locale');
        await switcher.selectOption('cs');
        await page.getByRole('button', { name: 'Save profile' }).click();

        await expect(
            page.getByRole('heading', { name: 'Nastavení' }),
        ).toBeVisible();
        await page.getByRole('button', { name: 'Uživatelské menu' }).click();
        await expect(
            page.getByRole('menuitem', { name: 'Odhlásit se' }),
        ).toBeVisible();

        await switcher.selectOption('sk');
        await page.getByRole('button', { name: 'Uložit profil' }).click();

        await expect(
            page.getByRole('heading', { name: 'Nastavenia' }),
        ).toBeVisible();
        await page.getByRole('button', { name: 'Používateľské menu' }).click();
        await expect(
            page.getByRole('menuitem', { name: 'Odhlásiť sa' }),
        ).toBeVisible();

        await switcher.selectOption('en');
        await page.getByRole('button', { name: 'Uložiť profil' }).click();

        await expect(
            page.getByRole('heading', { name: 'Settings' }),
        ).toBeVisible();
        await page.getByRole('button', { name: 'User menu' }).click();
        await expect(
            page.getByRole('menuitem', { name: 'Log out' }),
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
        await page.getByRole('button', { name: 'Uživatelské menu' }).click();
        await page
            .getByRole('menuitem', { name: 'Nastavení', exact: true })
            .click();
        await page.waitForURL(/\/settings$/);

        await expect(
            page.getByRole('heading', { name: 'Nastavení' }),
        ).toBeVisible();
    });
});
