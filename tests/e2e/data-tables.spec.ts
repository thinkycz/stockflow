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
    await expect(frame.locator('thead')).toHaveCSS(
        'background-color',
        'rgba(0, 0, 0, 0)',
    );
    await expect(frame.locator('thead th').first()).toHaveCSS(
        'background-color',
        'rgb(243, 246, 249)',
    );

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

    await row.locator('summary').click();
    await expect(row.locator('.data-table-frame--nested')).toBeVisible();

    const hasHorizontalOverflow = await page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );
    expect(hasHorizontalOverflow).toBe(false);

    await page.goto('/stock-movements');
    const totalRow = page.locator('.data-table tfoot tr');
    await expect(totalRow).toBeVisible();
    await expect(totalRow).toHaveCSS('display', 'block');
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
