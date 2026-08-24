import { expect, test } from '@playwright/test';

async function login(page: import('@playwright/test').Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);
}

test('tables share the payslip frame and become labelled cards on mobile', async ({
    page,
}) => {
    await login(page);
    await page.goto('/payroll?year=2026&month=8');

    const frame = page.locator('.data-table-frame').first();
    await expect(frame).toBeVisible();
    await expect(frame).toHaveCSS('border-radius', '16px');
    await expect(
        frame.locator(':scope > .data-table-scroll > table > thead'),
    ).toHaveCSS('background-color', 'rgb(243, 246, 249)');
    await expect(
        frame.locator(':scope > .data-table-scroll > table > thead th').first(),
    ).toHaveCSS('background-color', 'rgb(243, 246, 249)');

    await page.setViewportSize({ width: 390, height: 844 });

    const row = page
        .locator('[data-testid^="payroll-row-"]')
        .filter({ hasText: 'E2E Worker' });
    await expect(row).toBeVisible();
    await expect(row).toHaveCSS('display', 'block');

    const cells = row.locator(':scope > td');
    const cellCount = await cells.count();
    expect(cellCount).toBeGreaterThan(0);
    for (let index = 0; index < cellCount; index += 1) {
        await expect(cells.nth(index)).toHaveAttribute('data-label', /.+/);
    }

    await expect(row.locator('details')).toHaveCount(0);
    await expect(row.locator('.data-table-frame--nested')).toHaveCount(0);

    const hasHorizontalOverflow = await page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );
    expect(hasHorizontalOverflow).toBe(false);
});

test('print media restores a compact semantic table', async ({ page }) => {
    await login(page);
    await page.emulateMedia({ media: 'print' });
    await page.goto('/payroll/print?year=2026&month=8');

    const frame = page.locator('.data-table-frame').first();
    await expect(frame).toBeVisible();
    await expect(frame).toHaveCSS('border-radius', '0px');
    await expect(frame.locator('.data-table')).toHaveCSS('display', 'table');
    await expect(frame.locator('thead')).toHaveCSS(
        'display',
        'table-header-group',
    );
});

test('simple payslips print consecutively with cut lines', async ({ page }) => {
    await login(page);
    await page.emulateMedia({ media: 'print' });
    await page.goto('/payroll/print?year=2026&month=8&simple=1');

    const payslip = page.locator('.payslip--simple').first();
    await expect(payslip).toBeVisible();
    await expect(payslip).toHaveCSS('break-after', 'auto');
    await expect(payslip).toHaveCSS('break-inside', 'avoid');
    await expect(payslip).toHaveCSS('min-height', '0px');
    await expect(payslip).toHaveCSS('border-bottom-style', 'dashed');

    const confirmations = page.getByTestId('payroll-receipt-confirmation');
    await expect(confirmations).toHaveCount(
        await page.locator('.payslip--simple').count(),
    );
    await expect(confirmations.first()).toBeVisible();
    await expect(confirmations.first()).toHaveCSS('break-inside', 'avoid');
});
