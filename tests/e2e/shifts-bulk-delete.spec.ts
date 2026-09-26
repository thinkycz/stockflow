import { expect, test } from '@playwright/test';

for (const viewport of ['desktop', 'mobile'] as const) {
    test(`bulk shift selection, cancellation, errors and deletion on ${viewport}`, async ({
        page,
    }) => {
        const month = viewport === 'desktop' ? 7 : 8;
        const monthKey = String(month).padStart(2, '0');
        await page.goto('/login');
        await page.getByLabel('Email').fill('test@test.com');
        await page.getByLabel('Password', { exact: true }).fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL(/\/dashboard$/);
        await page.goto(`/shifts?year=2098&month=${month}`);

        // Create isolated fixtures through the existing form before switching viewport.
        for (const day of Array.from({ length: 12 }, (_, i) => i + 15)) {
            await page
                .getByTestId(`calendar-day-2098-${monthKey}-${day}`)
                .click();
            const editor = page.getByRole('dialog');
            await editor
                .getByLabel('Worker', { exact: false })
                .selectOption({ label: 'E2E Worker' });
            await editor.locator('#start_time').selectOption('09:00');
            await editor.locator('#end_time').selectOption('10:00');
            await editor.locator('button[type="submit"]').click();
            await expect(
                editor.getByText('09:00–10:00', { exact: false }).first(),
            ).toBeVisible();
            await editor
                .getByRole('button', { name: 'Close', exact: true })
                .click();
        }
        if (viewport === 'mobile')
            await page.setViewportSize({ width: 390, height: 844 });

        const manage = page.getByRole('button', {
            name: 'Manage shifts',
            exact: true,
        });
        await manage.click();
        const modal = page.getByRole('dialog', {
            name: 'Manage shifts',
            exact: true,
        });
        await expect(modal.getByRole('checkbox')).toHaveCount(12);
        await expect(
            modal.getByRole('button', {
                name: 'Delete selected (0)',
                exact: true,
            }),
        ).toBeDisabled();
        await modal
            .getByRole('button', { name: 'Select entire month' })
            .click();
        await modal.getByRole('checkbox').first().focus();
        await page.keyboard.press('Space');
        await expect(modal.getByText('Selected 11 of 12')).toBeVisible();
        await page.keyboard.press('Space');
        for (let i = 1; i < 11; i++)
            await modal.getByRole('checkbox').nth(i).uncheck();
        await expect(modal.getByText('Selected 2 of 12')).toBeVisible();
        await modal
            .getByRole('button', { name: 'Delete selected (2)', exact: true })
            .click();
        const confirmation = page.getByRole('dialog', {
            name: 'Delete selected shifts?',
            exact: true,
        });
        await expect(confirmation).toContainText('2098');
        await confirmation
            .getByRole('button', { name: 'Cancel', exact: true })
            .click();
        await expect(modal.getByText('Selected 2 of 12')).toBeVisible();

        // A validation failure from the real server must keep the complete selection.
        await page.route(
            '**/shifts/bulk-delete',
            async (route) => {
                const payload = route.request().postDataJSON() as {
                    shift_ids: number[];
                };
                payload.shift_ids.push(99999999);
                await route.continue({ postData: JSON.stringify(payload) });
            },
            { times: 1 },
        );
        await modal
            .getByRole('button', { name: 'Delete selected (2)', exact: true })
            .click();
        await confirmation
            .getByRole('button', { name: 'Delete selected (2)', exact: true })
            .click();
        await expect(modal.getByRole('alert')).toContainText(
            'Some selected shifts are no longer available',
        );
        await expect(modal.getByRole('checkbox')).toHaveCount(12);
        await expect(modal.getByText('Selected 2 of 12')).toBeVisible();

        await modal
            .getByRole('button', { name: 'Delete selected (2)', exact: true })
            .click();
        await confirmation
            .getByRole('button', { name: 'Delete selected (2)', exact: true })
            .click();
        await expect(modal).not.toBeVisible();
        await expect(
            page.getByText('Selected shifts deleted: 2.', { exact: true }),
        ).toBeVisible();
        await expect(page.getByText(/^10\s*h$/).first()).toBeVisible();
        await manage.click();
        await expect(modal.getByRole('checkbox')).toHaveCount(10);
        await expect(modal.getByText('Selected 0 of 10')).toBeVisible();
        await modal
            .getByRole('button', { name: 'Select entire month' })
            .click();
        await modal.getByRole('button', { name: 'Clear selection' }).click();
        await expect(
            modal.getByRole('button', {
                name: 'Delete selected (0)',
                exact: true,
            }),
        ).toBeDisabled();
        await modal
            .getByRole('button', { name: 'Select entire month' })
            .click();
        await page.screenshot({
            path: `test-results/shift-bulk-delete-${viewport}.png`,
            fullPage: false,
        });
        await expect(
            modal.getByRole('button', {
                name: 'Delete selected (10)',
                exact: true,
            }),
        ).toBeInViewport();
        const bounds = await modal.boundingBox();
        expect(bounds).not.toBeNull();
        expect(bounds!.y).toBeGreaterThanOrEqual(0);
        expect(bounds!.y + bounds!.height).toBeLessThanOrEqual(
            page.viewportSize()!.height,
        );
        expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(
            page.viewportSize()!.width,
        );
        await modal
            .getByRole('button', { name: 'Delete selected (10)', exact: true })
            .click();
        await confirmation
            .getByRole('button', { name: 'Delete selected (10)', exact: true })
            .click();
        await expect(modal).not.toBeVisible();
        await page.reload();
        await manage.click();
        await expect(
            modal.getByText('There are no shifts this month.'),
        ).toBeVisible();
    });
}
