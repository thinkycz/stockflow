import { expect, test, type Page } from '@playwright/test';

async function login(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);
}

async function logout(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'User menu' }).click();
    await page.getByRole('menuitem', { name: 'Log out' }).click();
    await page.waitForURL(/\/login$/);
}

async function editLimitedUser(page: Page): Promise<void> {
    await page.goto('/users');
    const row = page.getByRole('row').filter({ hasText: 'limited@test.com' });
    await row.getByRole('button', { name: 'Edit' }).click();
    await page.waitForURL(/\/users\/\d+\/edit$/);
}

test('admin can disable and restore shifts for a limited user', async ({
    page,
}) => {
    await login(page, 'test@test.com');
    await editLimitedUser(page);

    await page.getByLabel('Shifts', { exact: true }).uncheck();
    await page.getByRole('button', { name: 'Save changes' }).click();
    await page.waitForURL(/\/users$/);
    await logout(page);

    await login(page, 'limited@test.com');
    await expect(page.getByRole('link', { name: 'Shifts' })).toHaveCount(0);

    await page.goto('/shifts');
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(
        page.getByText('You do not have permission for this section.'),
    ).toBeVisible();
    await logout(page);

    await login(page, 'test@test.com');
    await editLimitedUser(page);
    await page.getByLabel('Shifts', { exact: true }).check();
    await page.getByRole('button', { name: 'Save changes' }).click();
    await page.waitForURL(/\/users$/);
    await logout(page);

    await login(page, 'limited@test.com');
    await expect(page.getByRole('link', { name: 'Shifts' })).toBeVisible();
    await page.goto('/shifts');
    await expect(page).toHaveURL(/\/shifts$/);
});
