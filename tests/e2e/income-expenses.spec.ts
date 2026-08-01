import { expect, test } from '@playwright/test';

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

    await page.getByRole('button', { name: 'Add row' }).click();
    await page.getByLabel('Type').selectOption('income');
    await page.getByLabel('Date').fill('2030-01-15');
    await page.getByLabel('Item').fill('E2E extra income');
    await page.getByLabel('Amount').fill('125.50');
    await page.getByLabel('Note').fill('Created by Playwright');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('E2E extra income')).toBeVisible();

    const cashRow = page.getByTestId('financial-row-revenue-cash');
    await cashRow.getByRole('button', { name: 'Edit amount' }).click();
    await page.getByLabel('Used').fill('50');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(cashRow).toContainText('50.00');
    await expect(cashRow).toContainText('Manually adjusted');

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Close month' }).click();
    await expect(page.getByText('Closed', { exact: true })).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Reopen' }).click();
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
