import { expect, test } from '@playwright/test';

test('shared Czech calendar supports date bounds, manual leap dates and time selection', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);
    await page
        .getByRole('combobox', { name: 'Active store', exact: true })
        .selectOption({ label: 'Brno pobočka' });
    await page.goto('/inventory-counts');
    const date = page.locator('#inventory_counted_on');
    await date.fill('31.2.2024');
    expect(
        await date.evaluate((input: HTMLInputElement) => input.validity.valid),
    ).toBe(false);
    await date.fill('29.2.2024');
    await date.blur();
    await page
        .getByRole('button', { name: 'Open calendar', exact: true })
        .click();
    await expect(
        page
            .getByRole('dialog')
            .getByRole('button', { name: '29.2.2024', exact: true }),
    ).toBeVisible();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Clear', exact: true })
        .click();
    await expect(date).toHaveValue('');
    await page
        .getByRole('button', { name: 'Open calendar', exact: true })
        .click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Next month', exact: true })
        .click();
    const future = page
        .getByRole('dialog')
        .locator('[data-day][aria-disabled="true"]')
        .last();
    await expect(future).toBeVisible();
    const futureDate = await future.getAttribute('data-day');
    await future.click({ force: true });
    await expect(
        page
            .getByRole('dialog')
            .locator(`[data-day="${futureDate}"]`)
            .locator('..'),
    ).toHaveAttribute('aria-selected', 'false');
    await page.keyboard.press('Escape');
    await page.goto('/stock-movements/create');
    const timestamp = page.locator('#occurred_at');
    await timestamp.fill('29.2.2024 09:30');
    await timestamp.blur();
    await page
        .getByRole('button', { name: 'Open calendar', exact: true })
        .click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: '29.2.2024', exact: true })
        .click();
    await page
        .getByRole('dialog')
        .getByLabel('Time', { exact: true })
        .fill('14:45');
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Use date', exact: true })
        .click();
    await expect(timestamp).toHaveValue('29.2.2024 14:45');
});
