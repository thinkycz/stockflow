import { expect, test } from '@playwright/test';

test('noticeboard uses one store-scoped heading and navigation context', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);

    await expect(
        page.getByRole('heading', { name: 'Noticeboard', exact: true }),
    ).toHaveCount(1);
    await expect(
        page.getByRole('heading', { name: 'Dashboard', exact: true }),
    ).toHaveCount(0);
    await expect(page.getByTestId('active-store-pill')).toContainText(
        'Brno pobočka',
    );

    const storeSection = page.getByTestId('nav-section-store');
    const sidebarSections = page.locator('aside [data-testid^="nav-section-"]');
    await expect(sidebarSections.first()).toHaveAttribute(
        'data-testid',
        'nav-section-store',
    );
    await expect(
        storeSection.locator('[data-testid^="nav-item-"]').first(),
    ).toHaveAttribute('data-testid', 'nav-item-dashboard');

    await expect(
        page.getByText('Inventory Value', { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Monthly Flow' }),
    ).toHaveCount(0);
    await expect(
        page.getByRole('heading', { name: 'Stock Status' }),
    ).toHaveCount(0);
    await expect(
        page.getByRole('main').getByRole('link', { name: 'Statistics' }),
    ).toHaveCount(0);

    await page.goto('/statements');
    await expect(page.getByTestId('active-store-pill')).toContainText(
        'Brno pobočka',
    );

    await page.goto('/reports/statistics');
    await expect(page.getByTestId('active-store-pill')).toContainText(
        'Brno pobočka',
    );

    await page.goto('/items');
    await expect(page.getByTestId('active-store-pill')).toHaveCount(0);

    await page.goto('/dashboard');
    await page
        .getByRole('combobox', { name: 'Active store' })
        .selectOption({ label: 'Praha centrála' });
    await expect(page.getByTestId('active-store-pill')).toContainText(
        'Praha centrála',
    );

    await page
        .getByRole('combobox', { name: 'Active store' })
        .selectOption({ label: 'Brno pobočka' });
    await expect(page.getByTestId('active-store-pill')).toContainText(
        'Brno pobočka',
    );

    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('button', { name: 'Menu' }).click();
    const mobileDrawer = page.getByRole('dialog');
    await expect(mobileDrawer).toBeVisible();
    await expect(
        mobileDrawer
            .getByTestId('nav-section-store')
            .locator('[data-testid^="nav-item-"]')
            .first(),
    ).toHaveAttribute('data-testid', 'nav-item-dashboard');
});

test('limited user creates a noticeboard card that admin can edit', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('limited@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);

    await page.getByRole('button', { name: 'Add card' }).first().click();
    await page
        .locator('.noticeboard-editor')
        .fill('Shared E2E notice created by limited user');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(
        page.getByText('Shared E2E notice created by limited user', {
            exact: true,
        }),
    ).toBeVisible();

    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);
    await expect(
        page.getByText('Shared E2E notice created by limited user', {
            exact: true,
        }),
    ).toBeVisible();

    const card = page.locator('article').filter({
        has: page.getByText('Shared E2E notice created by limited user', {
            exact: true,
        }),
    });
    await card.getByRole('button', { name: 'Edit' }).click();
    await page.locator('.noticeboard-editor').fill('Admin edited notice');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(
        page.getByText('Admin edited notice', { exact: true }),
    ).toBeVisible();
});

test('noticeboard editor, preview, detail, filters and validation work together', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);

    await page.getByRole('button', { name: 'Add card' }).first().click();
    await page.getByRole('button', { name: 'Bold' }).click();
    await page
        .locator('.noticeboard-editor')
        .fill('Formatted E2E notice content');
    await page.getByRole('button', { name: 'Task', exact: true }).click();
    await page.getByRole('button', { name: 'Blue', exact: true }).click();
    await page.getByRole('button', { name: 'Large', exact: true }).click();
    await page.locator('input[type="file"]').setInputFiles({
        name: 'preview.png',
        mimeType: 'image/png',
        buffer: Buffer.from(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nH0AAAAASUVORK5CYII=',
            'base64',
        ),
    });
    await expect(page.locator('form img')).toBeVisible();
    await page.getByRole('button', { name: 'Save' }).click();

    const card = page.locator('article').filter({
        has: page.getByText('Formatted E2E notice content', { exact: true }),
    });
    await expect(
        card.getByText('Formatted E2E notice content', { exact: true }),
    ).toBeVisible();
    await expect(card.locator('strong')).toHaveText(
        'Formatted E2E notice content',
    );
    await expect(card.locator('img')).toBeVisible();
    await expect(card).toHaveAttribute('data-card-color', 'blue');
    await expect(card).toHaveAttribute('data-card-size', 'large');
    await expect(card.getByText('Task', { exact: true })).toBeVisible();

    await card
        .getByText('Formatted E2E notice content', { exact: true })
        .click();
    await expect(page.getByRole('dialog')).toHaveCount(0);

    await page.getByPlaceholder('Search cards…').fill('Formatted E2E notice');
    await page.waitForURL(/search=Formatted(\+|%20)E2E(\+|%20)notice/);
    await expect(
        page.getByText('Formatted E2E notice content', { exact: true }),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Expired' }).click();
    await page.waitForURL(/status=expired/);
    await expect(page.getByText('No cards', { exact: true })).toBeVisible();
});
