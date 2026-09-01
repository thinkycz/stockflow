import { expect, test, type Page } from '@playwright/test';

async function login(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);
}

test('same account keeps active stores isolated by browser session', async ({
    browser,
}) => {
    const machineA = await browser.newContext();
    const machineB = await browser.newContext();

    try {
        const pageA = await machineA.newPage();
        const pageB = await machineB.newPage();

        await login(pageA);
        await pageA
            .getByRole('combobox', { name: 'Active store' })
            .selectOption({ label: 'Praha centrála' });
        await expect(pageA.getByTestId('active-store-pill')).toContainText(
            'Praha centrála',
        );
        await pageA.goto('/statements');

        await login(pageB);
        await pageB
            .getByRole('combobox', { name: 'Active store' })
            .selectOption({ label: 'Ostrava depo' });
        await expect(pageB.getByTestId('active-store-pill')).toContainText(
            'Ostrava depo',
        );

        await pageA.reload();
        await expect(pageA.getByTestId('active-store-pill')).toContainText(
            'Praha centrála',
        );
        await pageA.getByRole('button', { name: 'Save statement' }).click();
        await expect(
            pageA.getByRole('status').filter({ hasText: 'Statement saved.' }),
        ).toBeVisible();
        await expect(pageA.getByTestId('active-store-pill')).toContainText(
            'Praha centrála',
        );
    } finally {
        await machineA.close();
        await machineB.close();
    }
});
