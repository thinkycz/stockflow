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
