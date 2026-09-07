import { expect, test } from '@playwright/test';

const labels = {
    en: {
        save: 'Save draft',
        confirm: 'Confirm statement',
        paired: 'Paired',
        matched: 'Within tolerance',
        candidates: 'Suggested periods',
        use: 'Use period',
        auto: 'Automatically suggested period · not saved',
        pending: 'Save to recalculate',
    },
    cs: {
        save: 'Uložit koncept',
        confirm: 'Potvrdit výpis',
        paired: 'Spárováno',
        matched: 'V toleranci',
        candidates: 'Navržená období',
        use: 'Použít období',
        auto: 'Automaticky navržené období · neuloženo',
        pending: 'Uložte pro přepočet',
    },
    sk: {
        save: 'Uložiť koncept',
        confirm: 'Potvrdiť výpis',
        paired: 'Spárované',
        matched: 'V tolerancii',
        candidates: 'Navrhnuté obdobia',
        use: 'Použiť obdobie',
        auto: 'Automaticky navrhnuté obdobie · neuložené',
        pending: 'Uložte pre prepočet',
    },
};

for (const locale of ['en', 'cs', 'sk'] as const) {
    test(`payout suggestions, draft save and standalone responsive review in ${locale}`, async ({
        page,
    }) => {
        const t = labels[locale];
        await page.goto('/login');
        await page.getByLabel('Email').fill('test@test.com');
        await page.getByLabel('Password', { exact: true }).fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL(/\/dashboard$/);
        await page
            .getByRole('combobox', { name: 'Active store', exact: true })
            .selectOption({ label: 'Brno pobočka' });
        await page.goto('/bank-statements');
        const href = await page
            .getByRole('row')
            .filter({ hasText: `synthetic-review-${locale}.pdf` })
            .getByRole('link')
            .getAttribute('href');
        expect(href).toBeTruthy();
        await page.route('**/bank-statements/*', async (route) => {
            if (route.request().method() !== 'GET') return route.continue();
            const response = await route.fetch();
            await route.fulfill({
                response,
                body: (await response.text())
                    .replaceAll('"locale":"en"', `"locale":"${locale}"`)
                    .replaceAll(
                        '&quot;locale&quot;:&quot;en&quot;',
                        `&quot;locale&quot;:&quot;${locale}&quot;`,
                    ),
            });
        });
        await page.setViewportSize({ width: 1440, height: 1100 });
        await page.goto(href!);
        const section = page.getByTestId('bank-transactions');
        const card = section
            .getByRole('row')
            .filter({ has: page.locator('input[value="Synthetic card"]') });
        const wolt = section
            .getByRole('row')
            .filter({ has: page.locator('input[value="Synthetic wolt"]') });
        const bolt = section
            .getByRole('row')
            .filter({ has: page.locator('input[value="Synthetic bolt"]') });
        await expect(card.getByText(t.paired, { exact: true })).toBeVisible();
        await expect(card.getByText(t.matched, { exact: true })).toBeVisible();
        await expect(wolt.getByText(t.auto, { exact: true })).toHaveCount(0);
        await wolt.getByText(t.candidates, { exact: true }).click();
        await wolt
            .getByRole('button', { name: t.use, exact: true })
            .and(page.locator(':enabled'))
            .first()
            .click();
        await expect(wolt.getByText(t.pending, { exact: true })).toBeVisible();
        await expect(
            page.getByRole('button', { name: t.confirm, exact: true }),
        ).toBeDisabled();
        await expect(section.locator('.data-table-frame--nested')).toHaveCount(
            0,
        );
        await expect(
            bolt.getByText(t.candidates, { exact: true }),
        ).toBeVisible();
        await bolt.getByText(t.candidates, { exact: true }).click();
        await expect(
            bolt.getByRole('button', { name: t.use, exact: true }),
        ).toHaveCount(3);
        await bolt
            .getByRole('button', { name: t.use, exact: true })
            .and(page.locator(':enabled'))
            .first()
            .click();
        await expect(bolt.getByText(t.pending, { exact: true })).toBeVisible();
        // A refresh must preserve the selected period before it is saved.
        await page.reload();
        await expect(bolt.getByText(t.pending, { exact: true })).toBeVisible();
        await page.screenshot({
            path: test.info().outputPath(`review-${locale}-desktop.png`),
        });
        await page.setViewportSize({ width: 390, height: 844 });
        await section.scrollIntoViewIfNeeded();
        for (const input of await section.locator('input').all()) {
            const box = await input.boundingBox();
            expect(box).not.toBeNull();
            expect(box!.x + box!.width).toBeLessThanOrEqual(390);
        }
        await page.screenshot({
            path: test.info().outputPath(`review-${locale}-mobile.png`),
            fullPage: true,
        });
        await page.getByRole('button', { name: t.save, exact: true }).click();
        await expect(wolt.getByText(t.pending, { exact: true })).toHaveCount(0);
        // The save redirect uses the test user's real English profile locale.
        await expect(
            wolt.getByText(labels.en.paired, { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: labels.en.confirm, exact: true }),
        ).toBeEnabled();
        await page.reload();
        await expect(wolt.getByText(t.paired, { exact: true })).toBeVisible();
        await expect(wolt.getByText(t.auto, { exact: true })).toHaveCount(0);
        await expect(wolt.locator('[data-payout-range]').first()).toBeVisible();
        await expect(wolt.getByText(t.matched, { exact: true })).toHaveCount(0);
    });
}
