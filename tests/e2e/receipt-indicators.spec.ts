import { expect, test } from '@playwright/test';

for (const [locale, month, pending] of [
    ['en', 7, 'Awaiting recalculation'],
    ['cs', 8, 'Čeká na přepočet'],
    ['sk', 9, 'Čaká na prepočet'],
] as const) {
    test(`confirmed receipt indicators and recalculation in ${locale}`, async ({
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
        await page.route('**/statements?**', async (route) => {
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
        });
        await page.goto(`/statements?year=2025&month=${month}`);
        const first = page
            .getByRole('row')
            .filter({ hasText: `1.${month}.2025` });
        const second = page
            .getByRole('row')
            .filter({ hasText: `2.${month}.2025` });
        await expect(
            first.locator('[data-receipt-state="verified"]'),
        ).toHaveCount(3);
        await expect(
            first.locator('[data-receipt-state="review"]'),
        ).toHaveCount(1);
        await expect(
            first.locator('td').nth(5).locator('[data-receipt-state]'),
        ).toHaveCount(0);
        const indicator = first.locator('td').nth(4).getByRole('button');
        await indicator.focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('dialog')).toBeVisible();
        await expect(
            page.getByRole('dialog').getByRole('link'),
        ).toHaveAttribute('href', /bank-statements\/\d+/);
        await page.keyboard.press('Escape');
        await expect(indicator).toBeFocused();
        await first.locator('td').nth(5).getByRole('spinbutton').fill('100');
        await expect(indicator).toHaveAccessibleName(pending);
        await expect(
            second.locator('td').nth(4).getByRole('button'),
        ).toHaveAccessibleName(pending);
        await expect(
            first
                .locator('td')
                .nth(2)
                .locator('[data-receipt-state="verified"]'),
        ).toHaveCount(1);
        await page.screenshot({
            path: test.info().outputPath(`receipts-${locale}-desktop.png`),
            fullPage: true,
        });
        await page.setViewportSize({ width: 390, height: 844 });
        await indicator.scrollIntoViewIfNeeded();
        await indicator.click();
        await expect(page.getByRole('dialog')).toBeVisible();
        const bounds = await page.getByRole('dialog').boundingBox();
        expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(390);
        await page.screenshot({
            path: test.info().outputPath(`receipts-${locale}-mobile.png`),
        });
        await page.keyboard.press('Escape');
        const save = {
            en: 'Save statement',
            cs: 'Uložit výkaz',
            sk: 'Uložiť výkaz',
        }[locale];
        await page.getByRole('button', { name: save, exact: true }).click();
        await expect(
            first.locator('td').nth(4).locator('[data-receipt-state="review"]'),
        ).toHaveCount(1);
        await expect(
            second
                .locator('td')
                .nth(4)
                .locator('[data-receipt-state="review"]'),
        ).toHaveCount(1);
    });
}
