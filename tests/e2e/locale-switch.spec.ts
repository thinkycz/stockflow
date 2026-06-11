import { expect, test } from '@playwright/test';
import { faker } from '@faker-js/faker';

test.describe('Locale switcher', () => {
    test.beforeEach(async ({ page }) => {
        const email = faker.internet.email();
        await page.goto('/register');
        await page.getByLabel('Email', { exact: true }).fill(email);
        await page.getByLabel('Password', { exact: true }).fill('password1');
        await page.getByLabel('Locale').selectOption('en');
        await page.getByRole('button', { name: 'Register' }).click();
        await page.waitForURL(/\/dashboard/);
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

        const switcher = page
            .locator('aside select, header select')
            .filter({ visible: true });
        await switcher.selectOption('cs');

        await expect(
            page.getByRole('heading', { name: 'Nastavení profilu' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Odhlásit se' }),
        ).toBeVisible();

        await switcher.selectOption('sk');
        await expect(
            page.getByRole('heading', { name: 'Nastavenia profilu' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Odhlásiť sa' }),
        ).toBeVisible();

        await switcher.selectOption('en');
        await expect(
            page.getByRole('heading', { name: 'Profile settings' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Log out' }),
        ).toBeVisible();
    });

    test('navigating to settings shows the localized page title', async ({
        page,
    }) => {
        const switcher = page
            .locator('aside select, header select')
            .filter({ visible: true });
        await switcher.selectOption('cs');

        await page.getByRole('link', { name: 'Profil', exact: true }).click();
        await page.waitForURL(/\/settings\/profile$/);

        await expect(
            page.getByRole('heading', { name: 'Nastavení profilu' }),
        ).toBeVisible();
    });
});
