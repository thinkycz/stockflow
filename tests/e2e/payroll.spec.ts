import { expect, test } from '@playwright/test';

test('admin adjusts closes and prints payroll while limited users are denied', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);

    await expect(page.getByTestId('nav-item-payroll')).toBeVisible();
    await page.goto('/payroll?year=2026&month=8');
    await expect(page.getByRole('heading', { name: 'Payslips' })).toBeVisible();

    const row = page
        .locator('[data-testid^="payroll-row-"]')
        .filter({ hasText: 'E2E Worker' });
    await expect(row).toBeVisible();
    await row.getByTitle('Edit hours and rate').click();
    await page.getByLabel('Payable hours').fill('10.5');
    await page.getByLabel('Hourly rate').fill('150');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(row).toContainText('Manually adjusted');

    await row.getByRole('button', { name: 'Add adjustment' }).click();
    await page.getByLabel('Adjustment type').selectOption('tip');
    await page.getByLabel('Amount').fill('25');
    await page.getByLabel('Reason').fill('E2E shared tips');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(row).toContainText('E2E shared tips');

    const popupPromise = page.waitForEvent('popup');
    await row.getByRole('link', { name: 'Simple' }).click();
    const printPage = await popupPromise;
    await expect(printPage.getByText('Payslip', { exact: true })).toBeVisible();
    await expect(printPage.getByText('Base pay')).toBeVisible();
    await expect(printPage.getByText('Tips')).toBeVisible();
    await expect(printPage.getByText('Deduction')).toBeVisible();
    await expect(printPage.getByText('Final pay')).toBeVisible();
    await expect(printPage.locator('table')).toHaveCount(0);
    await printPage.close();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Close month' }).click();
    await expect(page.getByText('Closed', { exact: true })).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Reopen' }).click();
    await expect(page.getByText('In progress', { exact: true })).toBeVisible();

    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByLabel('Email').fill('limited@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);
    await expect(page.getByTestId('nav-item-payroll')).toHaveCount(0);
    await page.goto('/payroll');
    await expect(page).toHaveURL(/\/dashboard$/);
});
