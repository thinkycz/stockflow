import { expect, test, type Page } from '@playwright/test';

async function login(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);
}

test('attendance transitions are controlled directly from the worker row', async ({
    page,
}) => {
    await login(page);
    await page.goto('/attendance');

    const row = page.getByTestId('attendance-table').getByRole('row', {
        name: /Scheduled Worker/,
    });
    await expect(row).toContainText('Not rated');

    await row.getByRole('button', { name: 'Arrival' }).click();
    await expect(row).toContainText('Working now');
    const timerPanel = page.getByTestId('attendance-timer-panel');
    await timerPanel.getByLabel('Worker').selectOption({
        label: 'Scheduled Worker',
    });
    await expect(timerPanel).toContainText('Time worked today');
    await expect(timerPanel.locator('p.font-mono')).toHaveText(/^00:00:\d{2}$/);
    await row.getByRole('button', { name: 'Start break' }).click();
    await expect(row).toContainText('On a break');
    await expect(timerPanel).toContainText('Current break duration');
    await row.getByRole('button', { name: 'Return' }).click();
    await expect(row).toContainText('Working now');
    await row.getByRole('button', { name: 'Departure' }).click();
    await expect(row).toContainText('Not working');
});

test('timer panel lists every worker and controls an off-schedule session', async ({
    page,
}) => {
    await login(page);
    await page.goto('/attendance');

    const timerPanel = page.getByTestId('attendance-timer-panel');
    const workerSelect = timerPanel.getByLabel('Worker');
    await expect(workerSelect.getByRole('option')).toContainText([
        'Select a worker',
        'Active Employee',
        'E2E Worker',
        'Off Schedule Worker',
        'Outside Window Worker',
        'Scheduled Worker',
    ]);
    await workerSelect.selectOption({
        label: 'Off Schedule Worker',
    });
    await timerPanel
        .getByRole('button', { name: 'Arrival without shift' })
        .click();
    await expect(timerPanel).toContainText('Time worked today');
    await timerPanel.getByRole('button', { name: 'Start break' }).click();
    await expect(timerPanel).toContainText('Current break duration');
    await timerPanel.getByRole('button', { name: 'Return' }).click();
    await timerPanel.getByRole('button', { name: 'Departure' }).click();
    await expect(timerPanel).toContainText('Not working');
});

test('arrival outside the shift matching window requires confirmation', async ({
    page,
}) => {
    await login(page);
    await page.goto('/attendance');

    const row = page.getByTestId('attendance-table').getByRole('row', {
        name: /Outside Window Worker/,
    });
    await row.getByRole('button', { name: 'Arrival' }).click();

    const dialog = page.getByRole('dialog', {
        name: 'Worker has no current shift',
    });
    await expect(dialog).toContainText(
        'The selected worker has no shift in the matching window.',
    );
    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(row).toContainText('Not working');
});

test('attendance rows become mobile cards without horizontal overflow', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);
    await page.goto('/attendance');

    const row = page.getByTestId('attendance-table').getByRole('row', {
        name: /Scheduled Worker/,
    });
    await expect(row).toBeVisible();
    await expect(row.locator('td[data-label="Actions"]')).toBeVisible();
    expect(
        await page.evaluate(
            () =>
                document.documentElement.scrollWidth <=
                document.documentElement.clientWidth,
        ),
    ).toBe(true);
});

test('admin rejects and then approves a monthly attendance deviation', async ({
    page,
}) => {
    await login(page);
    await page.goto('/attendance/report?month=2031-02');

    await page.getByRole('button', { name: 'Review deviation' }).click();
    const dialog = page.getByRole('dialog', {
        name: 'Review attendance deviation',
    });
    await expect(dialog).toContainText('08:00–16:00');
    await expect(dialog).toContainText('08:20');
    await expect(dialog.getByLabel('Approved start')).toHaveValue('08:15');
    await expect(dialog.getByLabel('Approved end')).toHaveValue('16:30');
    await dialog.getByLabel('Review reason').fill('Keep original schedule');
    await dialog.getByRole('button', { name: 'Reject deviation' }).click();

    await page.getByRole('button', { name: 'Rejected' }).click();
    await dialog.getByLabel('Review reason').fill('Accept actual coverage');
    await dialog.getByRole('button', { name: 'Approve deviation' }).click();

    await expect(page.getByRole('button', { name: 'Approved' })).toBeVisible();
    await page.goto('/payroll?year=2031&month=2');
    const payrollRow = page
        .locator('[data-testid^="payroll-row-"]')
        .filter({ hasText: 'E2E Worker' });
    await expect(payrollRow).toContainText('8.25 h');
    await expect(payrollRow).toContainText('1,650');
});

test('admin disables attendance rating without disabling attendance actions', async ({
    page,
}) => {
    await login(page);
    await page.goto('/workers');

    let workerRow = page.getByRole('row', { name: /Scheduled Worker/ });
    await workerRow.getByRole('button', { name: 'Edit' }).click();
    const ratingCheckbox = page.getByLabel('Rate attendance');
    await expect(ratingCheckbox).toBeChecked();
    await ratingCheckbox.uncheck();
    await page.getByRole('button', { name: 'Save' }).click();
    await page.waitForURL(/\/workers$/);

    workerRow = page.getByRole('row', { name: /Scheduled Worker/ });
    await expect(workerRow).toContainText('Disabled');

    await page.goto('/attendance');
    const attendanceRow = page
        .getByTestId('attendance-table')
        .getByRole('row', { name: /Scheduled Worker/ });
    await expect(
        attendanceRow.getByTitle('Attendance rating disabled'),
    ).toBeVisible();
    await expect(
        attendanceRow.getByRole('button', { name: 'Arrival' }),
    ).toBeVisible();

    await page.goto('/shifts');
    await expect(
        page.getByLabel('Attendance rating disabled').first(),
    ).toBeVisible();

    await page.goto('/workers');
    workerRow = page.getByRole('row', { name: /Scheduled Worker/ });
    await workerRow.getByRole('button', { name: 'Edit' }).click();
    await page.getByLabel('Rate attendance').check();
    await page.getByRole('button', { name: 'Save' }).click();
    await page.waitForURL(/\/workers$/);
    await expect(
        page.getByRole('row', { name: /Scheduled Worker/ }),
    ).toContainText('Enabled');
});

test('report restores validity, matches shifts and uses Czech dates in English', async ({
    page,
}) => {
    await login(page);
    await page.goto('/attendance/report?month=2032-03');
    const row = (date: string) =>
        page.getByRole('row').filter({
            has: page.getByRole('cell', { name: date, exact: true }),
        });
    await expect(row('10.3.2032')).toContainText('08:00');
    await page
        .getByRole('button', { name: 'Match attendances', exact: true })
        .click();
    let dialog = page.getByRole('dialog', {
        name: 'Match attendances',
        exact: true,
    });
    await dialog
        .getByLabel('Reason', { exact: true })
        .fill('Repair missing links');
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(dialog).not.toBeVisible();
    await expect(
        row('10.3.2032').getByRole('button', { name: 'Change shift' }),
    ).toBeVisible();
    await expect(page).toHaveURL(/month=2032-03/);

    await row('11.3.2032')
        .getByRole('button', { name: 'Match to shift' })
        .click();
    dialog = page.getByRole('dialog', { name: 'Match to shift', exact: true });
    await dialog
        .getByLabel('Shift', { exact: true })
        .selectOption({ label: '18:00–22:00' });
    await dialog
        .getByLabel('Reason', { exact: true })
        .fill('Manual assignment outside window');
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(dialog).not.toBeVisible();
    await expect(row('11.3.2032')).toContainText('08:00');
    await expect(
        row('11.3.2032').getByRole('button', { name: 'Change shift' }),
    ).toBeVisible();

    await row('12.3.2032')
        .getByRole('button', { name: 'Restore validity' })
        .click();
    dialog = page.getByRole('dialog', {
        name: 'Restore validity',
        exact: true,
    });
    await dialog.getByLabel('Departure time').fill('31.2.2032 16:00');
    await dialog
        .getByLabel('Reason', { exact: true })
        .fill('Restore completed work');
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(dialog).toBeVisible();
    expect(
        await dialog
            .getByLabel('Departure time')
            .evaluate((element: HTMLInputElement) => element.checkValidity()),
    ).toBe(false);
    await dialog.getByLabel('Departure time').fill('12.3.2032 16:00');
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(dialog).not.toBeVisible();
    await expect(
        row('12.3.2032').getByRole('button', { name: 'Void', exact: true }),
    ).toBeVisible();
    await expect(row('12.3.2032')).toContainText('16:00');
    await expect(page).toHaveURL(/month=2032-03/);

    await page.goto('/attendance/print?month=2032-03');
    await expect(
        page.getByRole('cell', { name: '10.3.2032', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('cell', { name: '12.3.2032', exact: true }),
    ).toBeVisible();
});
