import { expect, test } from '@playwright/test';

test('public shift requests toggle and appear in the admin calendar overlay', async ({
    page,
}) => {
    const requestedMonth = new Date();
    requestedMonth.setDate(1);
    requestedMonth.setMonth(requestedMonth.getMonth() + 1);
    const year = requestedMonth.getFullYear();
    const month = requestedMonth.getMonth() + 1;
    const date = `${year}-${String(month).padStart(2, '0')}-15`;

    await page.goto('/public/shifts/e2e-shift-calendar-token');
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.getByTestId('mobile-month-view')).toBeVisible();
    await expect(page.getByTestId('mobile-day-view')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Day' })).toHaveCount(0);
    await expect(page.getByText('Full month calendar')).toHaveCount(0);
    await page.setViewportSize({ width: 1280, height: 720 });
    await page.getByRole('button', { name: 'Submit requests' }).click();
    await page.getByLabel('Employee').selectOption({ label: 'E2E Worker' });
    await page.getByLabel('Time from').selectOption('09:00');
    await page.getByLabel('Time to').selectOption('17:00');
    await page.getByRole('button', { name: 'Start selecting' }).click();
    const day = page.getByTestId(`calendar-day-${date}`);
    await day.click();
    await expect(day.getByTestId('calendar-shift-request')).toContainText(
        '09:00–17:00',
    );

    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.getByTestId('mobile-month-view')).toBeVisible();
    await expect(page.getByTestId('mobile-day-view')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Day' })).toHaveCount(0);
    await expect(page.getByText('Full month calendar')).toHaveCount(0);
    await expect(
        page
            .getByTestId(`mobile-month-calendar-day-${date}`)
            .getByTestId('mobile-month-calendar-shift-request'),
    ).toContainText('09:00–17:00');
    await page.setViewportSize({ width: 1280, height: 720 });

    await page.getByRole('button', { name: 'Done' }).click();
    await page.getByLabel('Time from').selectOption('10:00');
    await page.getByLabel('Time to').selectOption('18:00');
    await page.getByRole('button', { name: 'Start selecting' }).click();
    await day.click();
    await expect(day.getByTestId('calendar-shift-request')).toHaveText(
        /10:00–18:00.*Request/,
    );

    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.goto(`/shifts?year=${year}&month=${month}`);
    await page.getByRole('button', { name: 'Show requests' }).click();
    await expect(
        page
            .getByTestId(`calendar-day-${date}`)
            .getByTestId('calendar-shift-request'),
    ).toContainText('10:00–18:00');
});

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
