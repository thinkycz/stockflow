import { expect, test } from '@playwright/test';

async function confirmDialog(
    page: import('@playwright/test').Page,
    label: string,
): Promise<void> {
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: label, exact: true }).click();
}

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
    await expect(page.getByTestId('active-store-pill')).toBeVisible();

    await page.getByRole('button', { name: 'Add worker' }).click();
    await page.getByRole('combobox', { name: 'Worker' }).fill('Off Schedule');
    await page.getByRole('option', { name: 'Off Schedule Worker' }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Add worker' })
        .click();
    const addedRow = page
        .locator('[data-testid^="payroll-row-"]')
        .filter({ hasText: 'Off Schedule Worker' });
    await expect(addedRow).toContainText('0 h');
    await addedRow.getByRole('link', { name: 'Detail' }).click();
    await page.getByRole('button', { name: 'Remove from report' }).click();
    await confirmDialog(page, 'Remove from report');
    await expect(page.getByRole('heading', { name: 'Payslips' })).toBeVisible();
    await expect(
        page
            .locator('[data-testid^="payroll-row-"]')
            .filter({ hasText: 'Off Schedule Worker' }),
    ).toHaveCount(0);

    const row = page
        .locator('[data-testid^="payroll-row-"]')
        .filter({ hasText: 'E2E Worker' });
    await expect(row).toBeVisible();
    await expect(row.getByRole('link')).toHaveCount(1);
    await expect(row.getByRole('link', { name: 'Detail' })).toBeVisible();
    await row.getByRole('link', { name: 'Detail' }).click();
    await page.waitForURL((url) => {
        return (
            /\/payroll\/workers\/\d+$/.test(url.pathname) &&
            url.searchParams.get('year') === '2026' &&
            url.searchParams.get('month') === '8'
        );
    });
    await expect(
        page.getByRole('heading', { name: 'E2E Worker' }),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Edit hours and rate' }).click();
    await page.getByLabel('Payable hours').fill('10.5');
    await page.getByLabel('Hourly rate').fill('150');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Manually adjusted')).toBeVisible();

    await page.getByRole('button', { name: 'Add adjustment' }).first().click();
    await page.getByLabel('Adjustment type').selectOption('tip');
    await page.getByLabel('Amount').fill('25');
    await page.getByLabel('Reason').fill('E2E shared tips');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('E2E shared tips')).toBeVisible();

    let popupPromise = page.waitForEvent('popup');
    await page.getByRole('button', { name: 'Print', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Print simple payslip' }).click();
    let printPage = await popupPromise;
    await expect(printPage.getByText('Payslip', { exact: true })).toBeVisible();
    await expect(printPage.getByText('Base pay')).toBeVisible();
    await expect(printPage.getByText('Tips')).toBeVisible();
    await expect(printPage.getByText('Deduction')).toBeVisible();
    await expect(printPage.getByText('Final pay')).toBeVisible();
    await expect(printPage.locator('table')).toHaveCount(0);
    await printPage.close();

    popupPromise = page.waitForEvent('popup');
    await page.getByRole('button', { name: 'Print', exact: true }).click();
    await page
        .getByRole('menuitem', { name: 'Print detailed payslip' })
        .click();
    printPage = await popupPromise;
    await expect(printPage.getByText('Payslip', { exact: true })).toBeVisible();
    await expect(printPage.locator('table')).not.toHaveCount(0);
    await printPage.close();

    await page.getByRole('link', { name: 'Back to payslip overview' }).click();
    await expect(page.getByRole('heading', { name: 'Payslips' })).toBeVisible();
    await expect(
        page
            .locator('[data-testid^="payroll-row-"]')
            .filter({ hasText: 'E2E Worker' }),
    ).toContainText('Manually adjusted');

    await page.getByRole('button', { name: 'Close month' }).click();
    await confirmDialog(page, 'Close month');
    await expect(page.getByText('Closed', { exact: true })).toBeVisible();

    await page
        .locator('[data-testid^="payroll-row-"]')
        .filter({ hasText: 'E2E Worker' })
        .getByRole('link', { name: 'Detail' })
        .click();
    await expect(page.getByText('Closed', { exact: true })).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Edit hours and rate' }),
    ).toHaveCount(0);
    await expect(
        page.getByRole('button', { name: 'Add adjustment' }),
    ).toHaveCount(0);
    await expect(
        page.getByRole('button', { name: 'Print', exact: true }),
    ).toBeVisible();
    await page.getByRole('link', { name: 'Back to payslip overview' }).click();

    await page.getByRole('button', { name: 'Reopen' }).click();
    await confirmDialog(page, 'Reopen');
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
