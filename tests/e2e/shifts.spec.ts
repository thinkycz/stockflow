import { expect, test } from '@playwright/test';

test('quick-added shifts are time ordered and feedback uses the global toast', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);

    await page.goto('/shifts?year=2026&month=7');
    const day = page.getByTestId('calendar-day-2026-07-15');

    await page.getByLabel('Employee').selectOption({ label: 'E2E Worker' });
    await page
        .getByLabel('Shift preset')
        .selectOption({ label: 'Evening (18:00–22:00)' });
    await page.getByRole('button', { name: 'Start quick add' }).click();
    await day.click();

    const successToast = page
        .getByRole('status')
        .filter({ hasText: 'Shift added.' });
    await expect(successToast).toBeVisible();
    await expect(day.getByText('Shift added.')).toHaveCount(0);
    await successToast
        .getByRole('button', { name: 'Dismiss notification' })
        .click();
    await page.getByRole('button', { name: 'Done' }).click();

    await page
        .getByLabel('Shift preset')
        .selectOption({ label: 'Morning (06:30–12:00)' });
    await page.getByRole('button', { name: 'Start quick add' }).click();
    await day.click();

    await expect(successToast).toBeVisible();
    await expect(day.getByTestId('calendar-shift')).toHaveText([
        /06:30–12:00/,
        /18:00–22:00/,
    ]);
});
