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

    const e2eWorkerRow = page
        .locator('[data-testid^="payroll-row-"]')
        .filter({ hasText: 'E2E Worker' });
    const activeEmployeeRow = page
        .locator('[data-testid^="payroll-row-"]')
        .filter({ hasText: 'Active Employee' });
    await page.getByRole('button', { name: 'Distribute tips' }).click();
    const tipDialog = page.getByRole('dialog');
    await expect(tipDialog).toContainText('Eligible workers: 2');
    await expect(tipDialog).toContainText('Payable hours: 3 h');
    await tipDialog.getByLabel('Total tip amount').fill('600');
    await tipDialog
        .getByRole('button', { name: 'Distribute tips', exact: true })
        .click();
    await expect(
        page.getByText('Tips distributed proportionally.'),
    ).toBeVisible();
    await expect(e2eWorkerRow.locator('td').nth(3)).toContainText('400.00');
    await expect(activeEmployeeRow.locator('td').nth(3)).toContainText(
        '200.00',
    );
    const payrollTotals = page.getByTestId('payroll-totals');
    await expect(payrollTotals.locator('th').first()).toContainText('Σ');
    await expect(payrollTotals.locator('td').nth(0)).toContainText('3 h');
    await expect(payrollTotals.locator('td').nth(1)).toContainText('600.00');
    await expect(payrollTotals.locator('td').nth(2)).toContainText('600.00');
    await expect(payrollTotals.locator('td').nth(3)).toContainText('0.00');
    await expect(payrollTotals.locator('td').nth(4)).toContainText('1,200.00');

    await page.getByRole('button', { name: 'Add worker' }).click();
    await page.getByRole('combobox', { name: 'Worker' }).fill('Payroll Only');
    await page.getByRole('option', { name: 'Payroll Only Worker' }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Add worker' })
        .click();
    const addedRow = page
        .locator('[data-testid^="payroll-row-"]')
        .filter({ hasText: 'Payroll Only Worker' });
    await expect(addedRow).toContainText('0 h');
    await addedRow.getByRole('link', { name: 'Detail' }).click();
    await page.getByRole('button', { name: 'Remove from report' }).click();
    await confirmDialog(page, 'Remove from report');
    await expect(page.getByRole('heading', { name: 'Payslips' })).toBeVisible();
    await expect(
        page
            .locator('[data-testid^="payroll-row-"]')
            .filter({ hasText: 'Payroll Only Worker' }),
    ).toHaveCount(0);

    await expect(e2eWorkerRow).toBeVisible();
    await expect(e2eWorkerRow.getByRole('link')).toHaveCount(1);
    await expect(
        e2eWorkerRow.getByRole('link', { name: 'Detail' }),
    ).toBeVisible();
    await e2eWorkerRow.getByRole('link', { name: 'Detail' }).click();
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

    const detailUrl = page.url();
    await page.getByRole('button', { name: 'Print', exact: true }).click();
    const simplePrintHref = await page
        .getByRole('menuitem', { name: 'Print simple payslip' })
        .getAttribute('href');
    expect(simplePrintHref).not.toBeNull();
    await page.goto(simplePrintHref!);
    const printPage = page;
    await expect(printPage.getByText('Payslip', { exact: true })).toBeVisible();
    await expect(printPage.getByText('Base pay')).toBeVisible();
    await expect(
        printPage.getByText('Tips', { exact: true }).last(),
    ).toBeVisible();
    await expect(
        printPage.getByText('Deduction', { exact: true }).last(),
    ).toBeVisible();
    await expect(printPage.getByText('Final pay')).toBeVisible();
    await expect(printPage.getByText('Adjustments')).toBeVisible();
    await expect(printPage.getByText('E2E shared tips')).toBeVisible();
    await expect(printPage.locator('table')).toHaveCount(1);
    await expect(printPage.getByTestId('payroll-wage-calculation')).toHaveCount(
        0,
    );
    await expect(printPage.getByText('Payable hours')).toHaveCount(0);
    await expect(
        printPage.getByTestId('payroll-receipt-confirmation'),
    ).toContainText(
        'By signing, I confirm proper receipt of the payment in the amount shown above for the stated period.',
    );
    await expect(printPage.getByText('Receipt date')).toBeVisible();
    await expect(printPage.getByText('Worker signature')).toBeVisible();
    await page.goto(detailUrl);

    await page.getByRole('button', { name: 'Print', exact: true }).click();
    const detailedPrintHref = await page
        .getByRole('menuitem', { name: 'Print detailed payslip' })
        .getAttribute('href');
    expect(detailedPrintHref).not.toBeNull();
    await page.goto(detailedPrintHref!);
    await expect(printPage.getByText('Payslip', { exact: true })).toBeVisible();
    await expect(printPage.locator('table')).not.toHaveCount(0);
    await expect(
        printPage.getByRole('columnheader', { name: 'Rate' }),
    ).toBeVisible();
    await expect(
        printPage.getByTestId('payroll-receipt-confirmation'),
    ).toHaveCount(0);
    await page.goto(detailUrl);

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
