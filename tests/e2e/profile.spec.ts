import { expect, test } from '@playwright/test';

test.describe('Profile management', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Email').fill('test@test.com');
        await page.getByLabel('Password', { exact: true }).fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL(/\/dashboard$/);
    });

    test('saved profile shows a viewport toast that can repeat and auto-dismiss', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 400 });
        await page.goto('/settings');

        await expect(
            page.getByRole('heading', { name: 'Password' }),
        ).toBeVisible();

        const saveButton = page.getByRole('button', { name: 'Save profile' });
        await saveButton.scrollIntoViewIfNeeded();
        await saveButton.click();

        await expect(page).toHaveURL(/\/settings$/);
        const successToast = page
            .getByRole('status')
            .filter({ hasText: 'Profile updated.' });
        await expect(successToast).toBeVisible();

        const toastBox = await successToast.boundingBox();
        if (toastBox === null) {
            throw new Error(
                'Expected the success toast to have a bounding box.',
            );
        }
        expect(toastBox.y).toBeGreaterThanOrEqual(0);
        expect(toastBox.y + toastBox.height).toBeLessThanOrEqual(400);

        await successToast
            .getByRole('button', { name: 'Dismiss notification' })
            .click();
        await expect(successToast).toBeHidden();

        await saveButton.click();
        await expect(successToast).toBeVisible();
        await expect(successToast).toBeHidden({ timeout: 7000 });
    });
});
