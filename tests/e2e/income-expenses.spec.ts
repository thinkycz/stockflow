import { expect, test } from '@playwright/test';

async function confirmDialog(
    page: import('@playwright/test').Page,
    label: string,
): Promise<void> {
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: label, exact: true }).click();
}

function currencyValue(value: string): number {
    return Number(value.replace(/[^\d.-]/g, ''));
}

async function expectFinancialTotals(
    totalRow: import('@playwright/test').Locator,
): Promise<void> {
    const section = totalRow.locator('xpath=ancestor::section');
    const calculatedAmounts = await section
        .locator('tbody [data-testid^="financial-row-"] td:nth-child(4)')
        .allTextContents();
    const effectiveAmounts = await section
        .locator('tbody [data-testid^="financial-row-"] td:nth-child(5)')
        .allTextContents();
    const calculatedTotal = currencyValue(
        (await totalRow.locator('td').nth(0).textContent()) ?? '',
    );
    const effectiveTotal = currencyValue(
        (await totalRow.locator('td').nth(1).textContent()) ?? '',
    );

    expect(calculatedTotal).toBe(
        calculatedAmounts.reduce(
            (total, amount) => total + currencyValue(amount),
            0,
        ),
    );
    expect(effectiveTotal).toBe(
        effectiveAmounts.reduce(
            (total, amount) => total + currencyValue(amount),
            0,
        ),
    );
}

test('admin manages and closes a monthly financial report while limited users are denied', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);

    const financeNav = page.getByTestId('nav-item-income_expenses');
    await expect(financeNav).toBeVisible();
    await page.goto('/income-expenses?year=2030&month=1');
    await expect(
        page.getByRole('heading', { name: 'Income & expenses' }),
    ).toBeVisible();
    await expect(page.getByTestId('active-store-pill')).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Copy previous month' }),
    ).toHaveCount(0);
    const cashRow = page.getByTestId('financial-row-revenue-cash');
    await expect(cashRow.getByRole('link', { name: 'Cash' })).toHaveAttribute(
        'href',
        /\/statements\?.*year=2030.*month=1/,
    );
    await expect(page.getByTestId('financial-row-revenue-bolt')).toContainText(
        'commission 35%',
    );
    const incomingMovementRow = page.getByRole('row').filter({
        has: page.getByRole('link', { name: 'IN-2030-E2E', exact: true }),
    });
    const transferMovementRow = page.getByRole('row').filter({
        has: page.getByRole('link', { name: 'TR-2030-E2E', exact: true }),
    });
    await expect(incomingMovementRow).toContainText('Incoming → Brno pobočka');
    await expect(transferMovementRow).toContainText('Warehouse → Brno pobočka');

    const incomeTotals = page.getByTestId('financial-totals-income');
    const expenseTotals = page.getByTestId('financial-totals-expense');
    await expect(incomeTotals).toBeVisible();
    await expect(expenseTotals).toBeVisible();
    await expect(incomeTotals.locator('th').first()).toContainText('Σ');
    await expect(expenseTotals.locator('th').first()).toContainText('Σ');
    await expect(incomeTotals.locator('td').nth(0)).toHaveAttribute(
        'data-label',
        'Calculated',
    );
    await expect(incomeTotals.locator('td').nth(1)).toHaveAttribute(
        'data-label',
        'Used',
    );
    await expect(expenseTotals.locator('td').nth(0)).toHaveAttribute(
        'data-label',
        'Calculated',
    );
    await expect(expenseTotals.locator('td').nth(1)).toHaveAttribute(
        'data-label',
        'Used',
    );
    await expectFinancialTotals(incomeTotals);
    await expectFinancialTotals(expenseTotals);

    await page.getByRole('button', { name: 'Recurring expenses' }).click();
    await page.waitForURL(
        (url) =>
            url.pathname === '/income-expenses/recurring-expenses' &&
            url.searchParams.get('year') === '2030' &&
            url.searchParams.get('month') === '1',
    );
    await expect(
        page.getByRole('heading', { name: 'Recurring expenses' }),
    ).toBeVisible();
    await expect(page.getByTestId('active-store-pill')).toBeVisible();
    await page.getByRole('button', { name: 'New expense' }).click();
    await expect(page.getByLabel('Item')).toBeFocused();
    await page.getByLabel('Item').fill('E2E rent');
    await page.getByLabel('Amount').fill('1000');
    await page.getByLabel('Day of month').fill('31');
    await page.getByLabel('First month').fill('2030-01');
    await page.getByLabel('Note').fill('Recurring E2E expense');
    await page.getByRole('button', { name: 'Save' }).click();
    const recurringDefinition = page
        .getByTestId(/recurring-expense-/)
        .filter({ hasText: 'E2E rent' });
    await expect(recurringDefinition).toBeVisible();

    await page.goto('/income-expenses?year=2030&month=1');
    const recurringRow = page.getByTestId(/financial-row-recurring_expense-/);
    await expect(recurringRow).toContainText('E2E rent');
    await expect(recurringRow).toContainText('1,000.00');

    await page.getByRole('button', { name: 'Recurring expenses' }).click();
    await recurringDefinition
        .getByRole('button', { name: 'Schedule change' })
        .click();
    await page.getByLabel('Amount').fill('1100');
    await page.getByLabel('Change effective from').fill('2030-02');
    await page.getByRole('button', { name: 'Save' }).click();
    await page.goto('/income-expenses?year=2030&month=2');
    await expect(page.getByText('E2E rent', { exact: true })).toBeVisible();
    await expect(
        page.getByTestId(/financial-row-recurring_expense-/),
    ).toContainText('1,100.00');

    await page.getByRole('button', { name: 'Recurring expenses' }).click();
    const recurringDefinitionForTermination = page
        .getByTestId(/recurring-expense-/)
        .filter({ hasText: 'E2E rent' });
    await recurringDefinitionForTermination
        .getByRole('button', { name: 'End' })
        .click();
    await page.getByLabel('Do not include from').fill('2030-03');
    await page.getByRole('button', { name: 'Confirm end' }).click();
    await page.goto('/income-expenses?year=2030&month=3');
    await expect(page.getByText('E2E rent', { exact: true })).toHaveCount(0);

    await page.goto('/income-expenses?year=2030&month=1');
    await expect(
        page.getByTestId(/financial-row-recurring_expense-/),
    ).toContainText('1,000.00');

    await page.getByRole('button', { name: 'Add income' }).click();
    await expect(page.getByLabel('Type')).toHaveValue('income');
    await page.getByLabel('Date').fill('2030-01-15');
    await page.getByLabel('Item').fill('E2E extra income');
    await page.getByLabel('Amount').fill('125.50');
    await page.getByLabel('Note').fill('Created by Playwright');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('E2E extra income')).toBeVisible();

    await cashRow.getByRole('button', { name: 'Edit amount' }).click();
    await page.getByLabel('Used').fill('50');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(cashRow).toContainText('50.00');
    await expect(cashRow).toContainText('Manually adjusted');

    await page.getByRole('button', { name: 'Close month' }).click();
    await confirmDialog(page, 'Close month');
    await expect(
        page.getByRole('alert').filter({
            hasText:
                'Close the payroll report before closing the financial report.',
        }),
    ).toBeVisible();
    await expect(page).toHaveURL(
        (url) =>
            url.pathname === '/income-expenses' &&
            url.searchParams.get('year') === '2030' &&
            url.searchParams.get('month') === '1',
    );
    await expect(
        page.getByRole('heading', { name: 'Income & expenses' }),
    ).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Log in' })).toHaveCount(0);

    await page.goto('/payroll?year=2030&month=1');
    await page.getByRole('button', { name: 'Close month' }).click();
    await confirmDialog(page, 'Close month');
    await expect(page.getByText('Closed', { exact: true })).toBeVisible();
    await page.goto('/income-expenses?year=2030&month=1');

    await page.getByRole('button', { name: 'Close month' }).click();
    await confirmDialog(page, 'Close month');
    await expect(page.getByText('Closed', { exact: true })).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Recurring expenses' }),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Reopen' }).click();
    await confirmDialog(page, 'Reopen');
    await expect(page.getByText('Open', { exact: true })).toBeVisible();

    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByLabel('Email').fill('limited@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);
    await expect(page.getByTestId('nav-item-income_expenses')).toHaveCount(0);
    await page.goto('/income-expenses');
    await expect(page).toHaveURL(/\/dashboard$/);
});
