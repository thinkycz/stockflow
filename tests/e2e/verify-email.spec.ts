import { expect, test } from '@playwright/test';

test.describe('Email verification', () => {
    test('global error toast persists until dismissed', async ({ page }) => {
        await page.goto(
            '/email/verify?guard=users&email=nobody%40example.com&token=any-token',
        );

        await expect(page).toHaveURL(/\/login$/);
        const errorToast = page.getByRole('alert').filter({
            hasText: "We can't find a user with that email address.",
        });
        await expect(errorToast).toBeVisible();

        await page.waitForTimeout(5500);
        await expect(errorToast).toBeVisible();

        await errorToast
            .getByRole('button', { name: 'Dismiss notification' })
            .click();
        await expect(errorToast).toBeHidden();
    });

    test('public registration API is unavailable', async ({ page }) => {
        const response = await page.request.post('/api/v1/auth/register', {
            data: {
                email: 'public-registration@example.com',
                password: 'password1',
                locale: 'en',
            },
            headers: {
                Accept: 'application/vnd.api+json',
            },
        });

        expect(response.status()).toBe(404);
    });

    test('verify-email page is reachable while logged in', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Email').fill('test@test.com');
        await page.getByLabel('Password', { exact: true }).fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL(/\/dashboard$/);

        await page.goto('/verify-email');
        await expect(
            page.getByRole('button', { name: 'Send verification email' }),
        ).toBeVisible();
    });
});
