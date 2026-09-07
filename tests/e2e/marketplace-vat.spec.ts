import { expect, test } from '@playwright/test';

for (const [locale, title] of [
    ['en', 'Commission and VAT breakdown'],
    ['cs', 'Rozpis provize a DPH'],
    ['sk', 'Rozpis provízie a DPH'],
] as const) {
    test(`VAT breakdown is shared across reports, finance and receipts in ${locale}`, async ({
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
        await page.route(
            /\/(reports|income-expenses|statements|bank-statements)(\?|\/|$)/,
            async (route) => {
                if (route.request().method() !== 'GET') return route.continue();
                const response = await route.fetch();
                await route.fulfill({
                    response,
                    body: (await response.text())
                        .replaceAll('"locale":"en"', `"locale":"${locale}"`)
                        .replaceAll(
                            '&quot;locale&quot;:&quot;en&quot;',
                            `&quot;locale&quot;:&quot;${locale}&quot;`,
                        ),
                });
            },
        );
        await page.goto('/reports?year=2025&month=7');
        const reportFees = page.locator('[data-marketplace-fees]').first();
        await reportFees.locator('summary').focus();
        await page.keyboard.press('Enter');
        await expect(reportFees).toContainText(title);
        await expect(reportFees.locator('dd').nth(2)).toContainText(/12[,.]60/);
        await expect(reportFees.locator('dd').nth(3)).toContainText(/72[,.]60/);
        await page.screenshot({
            path: test.info().outputPath(`vat-${locale}-desktop.png`),
            fullPage: true,
        });
        await page.goto('/income-expenses?year=2025&month=7');
        const wolt = page.getByRole('row').filter({
            has: page.getByRole('link', { name: 'Wolt', exact: true }),
        });
        const financeFees = wolt.locator('[data-marketplace-fees]');
        await financeFees.locator('summary').click();
        await expect(financeFees.locator('dd').nth(2)).toContainText(
            /12[,.]60/,
        );
        await page.setViewportSize({ width: 390, height: 844 });
        const box = await financeFees.boundingBox();
        expect(box!.x + box!.width).toBeLessThanOrEqual(390);
        await page.goto('/statements?year=2025&month=7');
        const first = page.getByRole('row').filter({ hasText: '1.7.2025' });
        await first.locator('td').nth(3).getByRole('button').click();
        const receiptFees = page
            .getByRole('dialog')
            .locator('[data-marketplace-fees]');
        await receiptFees.locator('summary').click();
        await expect(receiptFees.locator('dd').nth(2)).toContainText(
            /12[,.]60/,
        );
        await page.screenshot({
            path: test.info().outputPath(`vat-${locale}-mobile.png`),
        });
        await page.getByRole('dialog').getByRole('link').click();
        const bankFees = page.locator('[data-marketplace-fees]').first();
        await bankFees.locator('summary').click();
        await expect(bankFees.locator('dd').nth(2)).toContainText(/12[,.]60/);
    });
}
