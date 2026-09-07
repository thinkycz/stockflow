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
        const trigger = page.getByRole('button', { name: title }).first();
        await expect(reportFees).toHaveCount(0);
        await trigger.focus();
        await page.keyboard.press('Enter');
        await expect(reportFees).toContainText(title);
        await expect(
            page.locator('[data-estimate-range]').first(),
        ).toBeVisible();
        await expect(
            reportFees.locator('[data-fee-field="transaction_fee"] dd'),
        ).toContainText(/2[,.]00/);
        await expect(
            reportFees.locator('[data-fee-field="transaction_vat"] dd'),
        ).toContainText(/0[,.]42/);
        await expect(
            reportFees.locator('[data-fee-field="vat"] dd'),
        ).toContainText(/14[,.]70/);
        await expect(
            reportFees.locator('[data-fee-field="deduction"] dd'),
        ).toContainText(/87[,.]12/);
        await page.screenshot({
            path: test.info().outputPath(`vat-${locale}-desktop.png`),
            fullPage: true,
        });
        await page.goto('/income-expenses?year=2025&month=7');
        const wolt = page.getByRole('row').filter({
            has: page.getByRole('link', { name: 'Wolt', exact: true }),
        });
        const financeFees = page
            .getByRole('dialog')
            .locator('[data-marketplace-fees]');
        await expect(wolt.locator('[data-estimate-summary]')).toBeVisible();
        await wolt.getByRole('button', { name: title }).click();
        await expect(
            financeFees.locator('[data-fee-field="vat"] dd'),
        ).toContainText(/14[,.]70/);
        await page.setViewportSize({ width: 390, height: 844 });
        const box = await financeFees.boundingBox();
        expect(box!.x + box!.width).toBeLessThanOrEqual(390);
        await page.goto('/statements?year=2025&month=7');
        const first = page.getByRole('row').filter({ hasText: '1.7.2025' });
        await first.locator('td').nth(3).getByRole('button').click();
        const receiptFees = page
            .getByRole('dialog')
            .locator('[data-marketplace-fees]');
        await expect(page.getByRole('dialog')).toHaveCount(1);
        await expect(
            receiptFees.locator('[data-fee-field="vat"] dd'),
        ).toContainText(/14[,.]70/);
        await page.screenshot({
            path: test.info().outputPath(`vat-${locale}-mobile.png`),
        });
        await page.getByRole('dialog').getByRole('link').click();
        const bankFees = page.locator('[data-marketplace-fees]').first();
        await page
            .getByRole('row')
            .filter({ hasText: 'Wolt' })
            .first()
            .getByRole('button', {
                name: locale === 'en' ? 'Payment details' : 'Detail platby',
            })
            .click();
        await expect(
            bankFees.locator('[data-fee-field="vat"] dd'),
        ).toContainText(/14[,.]70/);
    });
}
