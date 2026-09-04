import { mkdirSync } from 'node:fs';
import { expect, test } from '@playwright/test';

async function login(
    page: import('@playwright/test').Page,
    email: string,
): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);
}

async function logout(page: import('@playwright/test').Page): Promise<void> {
    await page.getByRole('button', { name: 'User menu' }).click();
    await page.getByRole('menuitem', { name: 'Log out' }).click();
    await page.waitForURL(/\/login$/);
}

test('admin issues and prints three-up vouchers and limited account redeems one', async ({
    page,
}) => {
    await login(page, 'test@test.com');
    await expect(page.getByTestId('nav-item-gift_vouchers')).toBeVisible();

    await page.goto('/gift-voucher-settings');
    await page.getByLabel('Public business name').fill('StockFlow Coffee');
    await page.getByLabel('Short message').fill('A small gift, a great cup.');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.getByText('Gift voucher branding saved.')).toBeVisible();

    await page.goto('/gift-vouchers');
    await page
        .getByRole('button', { name: 'Issue a new batch', exact: true })
        .click();
    await page.getByLabel('Voucher count').fill('4');
    await page.getByLabel('Value of one voucher').fill('450');
    await page.getByRole('button', { name: 'Issue vouchers' }).click();
    await page.waitForURL(/\/gift-vouchers\?batch=/);
    await expect(page.getByText('Gift voucher batch issued.')).toBeVisible();

    const firstVoucher = page.locator('tbody tr').first();
    const code = (await firstVoucher.locator('td').first().innerText()).trim();
    expect(code).toMatch(/^[A-HJ-NP-Z2-9]{4}(?:-[A-HJ-NP-Z2-9]{4}){3}$/);

    const popupPromise = page.waitForEvent('popup');
    await page
        .getByRole('button', { name: 'Print active vouchers' })
        .first()
        .click();
    const printPage = await popupPromise;
    await expect(printPage.getByTestId('gift-voucher-sheet')).toHaveCount(2);
    await expect(
        printPage
            .getByTestId('gift-voucher-sheet')
            .first()
            .getByTestId('gift-voucher-print-item'),
    ).toHaveCount(3);
    await expect(
        printPage
            .getByTestId('gift-voucher-sheet')
            .last()
            .getByTestId('gift-voucher-print-item'),
    ).toHaveCount(1);
    await printPage.emulateMedia({ media: 'print' });
    mkdirSync('output/pdf', { recursive: true });
    await printPage.screenshot({
        path: 'output/playwright/gift-vouchers-print-4.png',
        fullPage: true,
    });
    await printPage.pdf({
        path: 'output/pdf/gift-vouchers-print-4.pdf',
        printBackground: true,
        preferCSSPageSize: true,
    });
    await printPage.close();

    await logout(page);
    await login(page, 'limited@test.com');
    await page.goto('/gift-vouchers');
    await page.getByLabel('Voucher code').fill(code);
    await page.getByRole('button', { name: 'Check code' }).click();
    await expect(page.getByText('CZK 450.00')).toBeVisible();
    await page.getByRole('button', { name: 'Confirm redemption' }).click();
    await expect(page.getByText('Gift voucher redeemed.')).toBeVisible();

    await logout(page);
    await login(page, 'test@test.com');
    await page.goto('/gift-vouchers?search=' + code);
    await page.getByRole('button', { name: 'Reverse redemption' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Reason for change').fill('E2E correction');
    await dialog
        .getByRole('button', { name: 'Reverse redemption', exact: true })
        .click();
    await expect(
        page.getByText('Gift voucher redemption reversed.'),
    ).toBeVisible();
});

test('print media preserves three-up sheets for representative batch sizes', async ({
    page,
}) => {
    await login(page, 'test@test.com');
    await page.goto('/gift-voucher-settings');
    await page.getByLabel('Public business name').fill('StockFlow Coffee');
    await page.getByRole('button', { name: 'Save', exact: true }).click();

    const cases = [
        { quantity: 1, rows: [1] },
        { quantity: 3, rows: [3] },
        { quantity: 4, rows: [3, 1] },
        { quantity: 10, rows: [3, 3, 3, 1] },
        { quantity: 20, rows: [3, 3, 3, 3, 3, 3, 2] },
    ];

    for (const batch of cases) {
        await page.goto('/gift-vouchers');
        await page
            .getByRole('button', { name: 'Issue a new batch', exact: true })
            .click();
        await page.getByLabel('Voucher count').fill(String(batch.quantity));
        await page.getByLabel('Value of one voucher').fill('100');
        await page.getByRole('button', { name: 'Issue vouchers' }).click();
        await expect(
            page.getByText('Gift voucher batch issued.'),
        ).toBeVisible();

        const popupPromise = page.waitForEvent('popup');
        await page
            .getByRole('button', { name: 'Print active vouchers' })
            .first()
            .click();
        const printPage = await popupPromise;
        const sheets = printPage.getByTestId('gift-voucher-sheet');
        await expect(sheets).toHaveCount(batch.rows.length);

        for (const [index, count] of batch.rows.entries()) {
            await expect(
                sheets.nth(index).getByTestId('gift-voucher-print-item'),
            ).toHaveCount(count);
        }

        await printPage.close();
    }
});

test('voucher header navigation and forms work on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, 'test@test.com');
    await page.goto('/gift-vouchers');
    await expect(page.getByRole('tab')).toHaveCount(0);
    await page.getByRole('button', { name: 'Settings', exact: true }).click();
    await expect(page).toHaveURL(/\/gift-voucher-settings$/);
    await expect(page.getByLabel('Public business name')).toBeVisible();
    await page.getByRole('link', { name: 'Back to overview' }).click();
    await page
        .getByRole('button', { name: 'Issue a new batch', exact: true })
        .click();
    await expect(page).toHaveURL(/\/gift-voucher-batches\/create$/);
    await page.getByLabel('Voucher count').fill('0');
    await page.getByLabel('Value of one voucher').fill('100');
    await page
        .getByRole('button', { name: 'Issue vouchers', exact: true })
        .click();
    await expect(page).toHaveURL(/\/gift-voucher-batches\/create$/);
    expect(
        await page
            .getByLabel('Voucher count')
            .evaluate(
                (input: HTMLInputElement) => input.validity.rangeUnderflow,
            ),
    ).toBe(true);
    await page.getByRole('link', { name: 'Back to overview' }).click();
    await page.getByRole('button', { name: 'Redeem', exact: true }).click();
    await expect(page).toHaveURL(/\/gift-vouchers\/redeem$/);
    await expect(page.getByLabel('Voucher code')).toBeVisible();
    await expect(page.locator('body')).toHaveJSProperty('scrollWidth', 390);
});
