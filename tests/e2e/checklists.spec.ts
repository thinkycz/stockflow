import { expect, test } from '@playwright/test';

test('admin manages checklist templates and dashboard keeps todays snapshot', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);

    const storeNavItems = page
        .getByTestId('nav-section-store')
        .locator('[data-testid^="nav-item-"]');
    const keys = await storeNavItems.evaluateAll((items) =>
        items.map((item) => item.getAttribute('data-testid')),
    );
    expect(keys.indexOf('nav-item-checklists')).toBe(
        keys.indexOf('nav-item-attendance') + 1,
    );

    await expect(page.getByTestId('dashboard-checklists')).toBeVisible();
    await expect(page.getByTestId('checklist-shift-card')).toHaveCount(2);

    await page.getByTestId('nav-item-checklists').click();
    await expect(
        page.getByRole('heading', { name: 'Checklists', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText('Morning shift', { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText('Afternoon shift', { exact: true }),
    ).toBeVisible();
});
