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
    await page.getByRole('button', { name: 'Done' }).click();

    const desktopShiftDayNumberTop = await page
        .getByTestId('calendar-day-2026-07-15')
        .locator('span')
        .first()
        .evaluate((element) => element.getBoundingClientRect().top);
    const desktopEmptyDayNumberTop = await page
        .getByTestId('calendar-day-2026-07-16')
        .locator('span')
        .first()
        .evaluate((element) => element.getBoundingClientRect().top);
    expect(desktopShiftDayNumberTop).toBe(desktopEmptyDayNumberTop);

    await page.setViewportSize({ width: 390, height: 844 });
    const shiftDayNumberTop = await page
        .getByTestId('mobile-calendar-day-2026-07-15')
        .locator('span')
        .first()
        .evaluate((element) => element.getBoundingClientRect().top);
    const emptyDayNumberTop = await page
        .getByTestId('mobile-calendar-day-2026-07-16')
        .locator('span')
        .first()
        .evaluate((element) => element.getBoundingClientRect().top);
    expect(shiftDayNumberTop).toBe(emptyDayNumberTop);

    await page.getByRole('button', { name: 'Whole month' }).click();
    const mobileMonthDay = page.getByTestId(
        'mobile-month-calendar-day-2026-07-15',
    );
    await expect(
        mobileMonthDay.getByTestId('mobile-month-calendar-shift'),
    ).toHaveText([/06:30–12:00/, /18:00–22:00/]);
    const mobileMonthShiftDayNumberTop = await mobileMonthDay
        .locator('span')
        .first()
        .evaluate((element) => element.getBoundingClientRect().top);
    const mobileMonthEmptyDayNumberTop = await page
        .getByTestId('mobile-month-calendar-day-2026-07-16')
        .locator('span')
        .first()
        .evaluate((element) => element.getBoundingClientRect().top);
    expect(mobileMonthShiftDayNumberTop).toBe(mobileMonthEmptyDayNumberTop);
});

test('shift calendar switches to a selectable day agenda on mobile', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);

    await page.goto('/shifts?year=2026&month=7');
    const mobileDay = page.getByTestId('mobile-calendar-day-2026-07-15');

    await expect(mobileDay).toBeVisible();
    await expect(page.getByTestId('calendar-day-2026-07-15')).toBeHidden();
    await mobileDay.click();
    await expect(mobileDay).toHaveAttribute('aria-pressed', 'true');

    await page.getByRole('button', { name: 'Whole month' }).click();
    await expect(page.getByTestId('mobile-month-view')).toBeVisible();
    await expect(page.getByTestId('mobile-day-view')).toBeHidden();
    await expect(
        page.getByTestId('mobile-month-calendar-day-2026-07-15'),
    ).toBeVisible();

    const hasHorizontalOverflow = await page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );
    expect(hasHorizontalOverflow).toBe(false);
});
