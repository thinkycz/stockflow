import { expect, test, type Page } from '@playwright/test';

async function login(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);
}

test('confirmation dialogs trap focus, close with Escape, and restore focus', async ({
    page,
}) => {
    await login(page);
    await page.goto('/items');

    const trigger = page.getByRole('button', { name: 'Delete' }).first();
    await trigger.focus();
    await trigger.click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('heading')).toContainText(/Delete:/);
    await expect(dialog).toHaveAttribute('aria-modal', 'true');
    await expect(page.locator('body')).toHaveCSS('overflow', 'hidden');

    const close = dialog.getByRole('button', { name: 'Close' });
    await expect(close).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(dialog.getByRole('button', { name: 'Delete' })).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(close).toBeFocused();

    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(trigger).toBeFocused();
    await expect(page.locator('body')).not.toHaveCSS('overflow', 'hidden');
});

test('representative pages keep consistent headings and do not overflow on mobile', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);

    for (const path of [
        '/dashboard',
        '/items',
        '/stores',
        '/workers',
        '/users',
        '/stock-movements',
        '/inventory-counts',
        '/shifts',
        '/reports',
        '/statements',
        '/income-expenses',
        '/payroll',
        '/attendance',
    ]) {
        await page.goto(path);
        await expect(page.locator('main h1').first(), path).toBeVisible();
        expect(
            await page.evaluate(
                () =>
                    document.documentElement.scrollWidth <=
                    document.documentElement.clientWidth,
            ),
            path,
        ).toBe(true);
    }
});
