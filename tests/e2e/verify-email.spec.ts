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

    test('send verification email shows success flash', async ({ page }) => {
        const email = `verify-${Date.now()}@example.com`;

        const response = await page.request.post('/api/v1/auth/register', {
            data: {
                email,
                password: 'password1',
                locale: 'en',
            },
            headers: {
                Accept: 'application/vnd.api+json',
            },
        });

        expect(response.status()).toBe(201);

        await page.goto('/login');
        await page.getByLabel('Email').fill(email);
        await page.getByLabel('Password', { exact: true }).fill('password1');
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL(/\/dashboard$/);

        await page.goto('/verify-email');
        await page
            .getByRole('button', { name: 'Send verification email' })
            .click();

        await expect(
            page
                .getByRole('status')
                .filter({ hasText: 'Verification email sent.' })
                .first(),
        ).toBeVisible();
    });

    test('verify-email page is reachable while logged in', async ({ page }) => {
        const email = `verify-${Date.now()}@example.com`;

        const response = await page.request.post('/api/v1/auth/register', {
            data: {
                email,
                password: 'password1',
                locale: 'en',
            },
            headers: {
                Accept: 'application/vnd.api+json',
            },
        });

        expect(response.status()).toBe(201);

        await page.goto('/login');
        await page.getByLabel('Email').fill(email);
        await page.getByLabel('Password', { exact: true }).fill('password1');
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL(/\/dashboard$/);

        await page.goto('/verify-email');
        await expect(
            page.getByRole('button', { name: 'Send verification email' }),
        ).toBeVisible();
    });
});
