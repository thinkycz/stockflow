import { expect, test, type Page } from '@playwright/test';

function businessDate(offset = 0): { iso: string; display: string } {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Europe/Prague',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const part = (name: string) =>
        parts.find((value) => value.type === name)?.value ?? '';
    const value = new Date(
        `${part('year')}-${part('month')}-${part('day')}T12:00:00Z`,
    );
    value.setUTCDate(value.getUTCDate() + offset);
    return {
        iso: value.toISOString().slice(0, 10),
        display: `${value.getUTCDate()}.${value.getUTCMonth() + 1}.${value.getUTCFullYear()}`,
    };
}

async function addCard(
    page: Page,
    content: string,
    date: string,
): Promise<void> {
    await page
        .getByRole('button', { name: 'Add card', exact: true })
        .first()
        .click();
    const dialog = page.getByRole('dialog', { name: 'New card', exact: true });
    await dialog.locator('[contenteditable="true"]').fill(content);
    await dialog.getByLabel('Show on arrival', { exact: true }).fill(date);
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(dialog).not.toBeVisible();
}

test('first arrival requires each card and persists confirmation indicators on the noticeboard', async ({
    page,
}, testInfo) => {
    test.setTimeout(90_000);
    await page.goto('/login');
    await page.getByLabel('Email').fill('noticeboard@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);

    const today = businessDate();
    await addCard(page, 'Opening instructions', today.display);
    await addCard(page, 'Read the daily announcement', today.display);
    await addCard(page, 'Ordinary undated card', '');
    await expect(page.getByTestId('noticeboard-confirmed')).toHaveCount(0);

    await page.goto('/attendance');
    const first = page
        .getByTestId('attendance-table')
        .getByRole('row', { name: /First Arrival/ });
    await first.getByRole('button', { name: 'Arrival', exact: true }).click();
    const dialog = page.getByRole('dialog', {
        name: 'Cards for today’s shift',
        exact: true,
    });
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('First Arrival');
    await expect(first).toContainText('Working now');
    await expect(dialog).toContainText('Opening instructions');
    await expect(dialog).not.toContainText('Ordinary undated card');
    const confirm = dialog.getByRole('button', {
        name: 'Confirm cards',
        exact: true,
    });
    await expect(confirm).toBeDisabled();
    await expect(
        dialog.getByRole('button', { name: 'Close', exact: true }),
    ).toHaveCount(0);
    await page.keyboard.press('Escape');
    await expect(dialog).toBeVisible();
    await page.mouse.click(2, 2);
    await expect(dialog).toBeVisible();

    await page.goto('/dashboard');
    const opening = page
        .locator('article')
        .filter({ hasText: 'Opening instructions' });
    await opening.hover();
    await opening.getByRole('button', { name: 'Edit', exact: true }).click();
    const editor = page.getByRole('dialog', { name: 'Edit card', exact: true });
    await editor
        .locator('[contenteditable="true"]')
        .fill('Updated after arrival');
    await editor.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(editor).not.toBeVisible();
    await addCard(page, 'Late daily card', today.display);
    await page.goto('/attendance');
    await expect(dialog).toContainText('Opening instructions');
    await expect(dialog).not.toContainText('Updated after arrival');
    await expect(dialog).not.toContainText('Late daily card');
    await page.reload();
    await expect(dialog).toBeVisible();
    await expect
        .poll(() =>
            page.evaluate(
                () =>
                    document.activeElement?.closest('[role="dialog"]') !== null,
            ),
        )
        .toBe(true);

    await page.setViewportSize({ width: 390, height: 844 });
    await expect(dialog).toBeVisible();
    expect(
        await page.evaluate(
            () =>
                document.documentElement.scrollWidth <=
                document.documentElement.clientWidth,
        ),
    ).toBe(true);
    await page.screenshot({
        path: testInfo.outputPath('noticeboard-dialog-mobile.png'),
    });

    const checks = dialog.getByRole('checkbox');
    await expect(checks).toHaveCount(2);
    await checks.nth(0).check();
    await expect(confirm).toBeDisabled();
    await checks.nth(1).check();
    await expect(confirm).toBeEnabled();
    await page.route(
        '**/attendance/noticeboard-confirmations/*',
        (route) => route.abort(),
        { times: 1 },
    );
    await confirm.click();
    await expect(dialog).toBeVisible();
    await expect(confirm).toBeEnabled();
    await confirm.click();
    await expect(dialog).not.toBeVisible();

    const second = page
        .getByTestId('attendance-table')
        .getByRole('row', { name: /Second Arrival/ });
    await second.getByRole('button', { name: 'Arrival', exact: true }).click();
    await expect(second).toContainText('Working now');
    await expect(dialog).not.toBeVisible();
    await page.goto('/dashboard');
    await expect(page.getByTestId('noticeboard-confirmed')).toHaveCount(2);
    await expect(
        page.getByTestId('noticeboard-confirmed').first(),
    ).toHaveAttribute('aria-label', /Confirmed by First Arrival on/);
    await page.reload();
    await expect(page.getByTestId('noticeboard-confirmed')).toHaveCount(2);
    await page.screenshot({
        path: testInfo.outputPath('noticeboard-confirmed-mobile.png'),
        fullPage: true,
    });

    const changed = page
        .locator('article')
        .filter({ hasText: 'Updated after arrival' });
    await changed.getByRole('button', { name: 'Edit', exact: true }).click();
    await editor
        .getByLabel('Show on arrival', { exact: true })
        .fill(businessDate(1).display);
    await editor.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(editor).not.toBeVisible();
    await expect(changed.getByTestId('noticeboard-confirmed')).toHaveCount(0);
    await expect(page.getByTestId('noticeboard-confirmed')).toHaveCount(1);
    await changed.getByRole('button', { name: 'Edit', exact: true }).click();
    await editor.getByLabel('Show on arrival', { exact: true }).fill('');
    await editor.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(editor).not.toBeVisible();
    await expect(changed.getByTestId('noticeboard-confirmed')).toHaveCount(0);
});
