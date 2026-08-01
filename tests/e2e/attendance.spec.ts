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
