import { expect, test } from '@playwright/test';

test('administrator uploads a synthetic anonymized Czech bank statement', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);

    await page.goto('/bank-statements');
    await expect(
        page.getByText(/external AI provider OpenRouter/i),
    ).toBeVisible();
    await page.locator('input[type="file"]').setInputFiles({
        name: 'synthetic-cs-statement.pdf',
        mimeType: 'application/pdf',
        buffer: Buffer.from(
            '%PDF-1.4\n% Synthetic anonymized Česká spořitelna CZK statement\n%%EOF',
        ),
    });
    await page.getByRole('button', { name: 'Upload and process' }).click();

    await page.waitForURL(/\/bank-statements\/\d+$/);
    await expect(
        page.getByText(/Processing failed|Queued|Processing/, { exact: true }),
    ).toBeVisible();
});

for (const locale of ['en', 'cs', 'sk']) {
    test(`bank-independent upload copy and beta badges in ${locale}`, async ({
        page,
    }) => {
        await page.goto('/login');
        await page.getByLabel('Email').fill('test@test.com');
        await page.getByLabel('Password', { exact: true }).fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL(/\/dashboard$/);
        // Localize the rendered app props without changing the shared test user's preferences.
        await page.route('**/bank-statements', async (route) => {
            const response = await route.fetch();
            const html = await response.text();
            await route.fulfill({
                response,
                body: html
                    .replaceAll('"locale":"en"', `"locale":"${locale}"`)
                    .replaceAll(
                        '&quot;locale&quot;:&quot;en&quot;',
                        `&quot;locale&quot;:&quot;${locale}&quot;`,
                    ),
            });
        });
        await page.setViewportSize({ width: 1280, height: 1100 });
        await page.goto('/bank-statements');
        await expect(page.getByText(/Česká spořitelna/)).toHaveCount(0);
        const desktop = page.locator('aside');
        for (const key of ['bank_statements', 'assistant']) {
            await expect(desktop.getByTestId(`nav-item-${key}`)).toContainText(
                'BETA',
            );
        }
        await expect(
            desktop.getByTestId('nav-item-bank_statements'),
        ).toContainText(locale === 'en' ? 'Bank statements' : 'Výpisy z účtu');
        await page.screenshot({
            path: test.info().outputPath(`sidebar-${locale}-desktop.png`),
        });
        await page.setViewportSize({ width: 390, height: 844 });
        await page.locator('[aria-controls="mobile-nav-drawer"]').click();
        const mobile = page.locator('#mobile-nav-drawer');
        await expect(mobile).toHaveCSS('opacity', '1');
        await expect(mobile).toHaveCSS('transform', 'none');
        for (const key of ['bank_statements', 'assistant']) {
            const badge = mobile
                .getByTestId(`nav-item-${key}`)
                .getByText('BETA');
            await badge.scrollIntoViewIfNeeded();
            await expect(badge).toBeInViewport();
        }
        await page.screenshot({
            path: test.info().outputPath(`sidebar-${locale}-mobile.png`),
        });
    });
}
