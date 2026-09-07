import { expect, test } from '@playwright/test';

const labels = {
    en: {
        recommend: 'Recommend period',
        calendar: 'Open calendar',
        choose: 'Use date',
        stale: 'Suggestions are outdated.',
        reanalyze: 'Analyze again with AI',
        remove: 'Delete statement',
        save: 'Save draft',
    },
    cs: {
        recommend: 'Doporučit období',
        calendar: 'Otevřít kalendář',
        choose: 'Použít datum',
        stale: 'Návrhy nejsou aktuální.',
        reanalyze: 'Znovu analyzovat AI',
        remove: 'Smazat výpis',
        save: 'Uložit koncept',
    },
    sk: {
        recommend: 'Odporučiť obdobie',
        calendar: 'Otvoriť kalendár',
        choose: 'Použiť dátum',
        stale: 'Návrhy nie sú aktuálne.',
        reanalyze: 'Znovu analyzovať AI',
        remove: 'Zmazať výpis',
        save: 'Uložiť koncept',
    },
};
for (const [locale, month] of [
    ['en', 1],
    ['cs', 2],
    ['sk', 3],
] as const) {
    test(`date picker recommendations reanalysis and deletion in ${locale}`, async ({
        page,
    }) => {
        const t = labels[locale];
        const detailLabel =
            locale === 'en' ? 'Payment details' : 'Detail platby';
        const dialog = page.getByRole('dialog');
        await page.goto('/login');
        await page.getByLabel('Email').fill('test@test.com');
        await page.getByLabel('Password', { exact: true }).fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL(/\/dashboard$/);
        await page
            .getByRole('combobox', { name: 'Active store', exact: true })
            .selectOption({ label: 'Brno pobočka' });
        await page.goto('/bank-statements');
        const link = await page
            .getByRole('row')
            .filter({ hasText: `synthetic-actions-${locale}.pdf` })
            .getByRole('link')
            .getAttribute('href');
        expect(link).toBeTruthy();
        await page.route('**/bank-statements/**', async (route) => {
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
        await page.goto(link!);
        await expect(
            page.getByText(`1.${month}.2027 – 5.${month}.2027`, {
                exact: true,
            }),
        ).toBeVisible();
        const row = page.getByRole('row').filter({
            has: page.locator('input[value="Calendar action row"]'),
        });
        await row.getByRole('button', { name: t.calendar }).first().click();
        await expect(page.getByRole('dialog')).toBeVisible();
        const day = page
            .getByRole('dialog')
            .getByRole('button', { name: `7.${month}.2027`, exact: true });
        await day.focus();
        await page.keyboard.press('ArrowRight');
        await page.keyboard.press('Enter');
        await page.getByRole('button', { name: t.choose, exact: true }).click();
        await expect(row.getByRole('textbox').first()).toHaveValue(
            `8.${month}.2027`,
        );
        await row.getByRole('button', { name: detailLabel }).click();
        await dialog
            .getByRole('button', { name: t.recommend, exact: true })
            .click();
        const suggestions = dialog.locator('[data-period-suggestions]');
        await expect(suggestions).toContainText(`1.${month}.2027`);
        await expect(suggestions.getByRole('button').first()).toBeEnabled();
        let releaseResponse!: () => void;
        let responseReady!: () => void;
        const release = new Promise<void>((resolve) => {
            releaseResponse = resolve;
        });
        const ready = new Promise<void>((resolve) => {
            responseReady = resolve;
        });
        await page.route('**/bank-statements/*/recommend', async (route) => {
            const response = await route.fetch();
            responseReady();
            await release;
            await route.fulfill({ response });
        });
        await dialog
            .getByRole('button', { name: t.recommend, exact: true })
            .click();
        await ready;
        await page.keyboard.press('Escape');
        await row.getByRole('spinbutton').fill('317.5');
        await row.getByRole('button', { name: detailLabel }).click();
        releaseResponse();
        await expect(
            dialog.getByRole('button', { name: t.recommend, exact: true }),
        ).toBeEnabled();
        await page.unroute('**/bank-statements/*/recommend');
        await expect(dialog.getByText(t.stale, { exact: false })).toBeVisible();
        await expect(suggestions.getByRole('button').first()).toBeDisabled();
        await dialog
            .getByRole('button', { name: t.recommend, exact: true })
            .click();
        await expect(suggestions.getByRole('button').first()).toBeEnabled();
        await suggestions.getByRole('button').first().click();
        await page.setViewportSize({ width: 390, height: 844 });
        const detailBox = await dialog.boundingBox();
        expect(detailBox!.x + detailBox!.width).toBeLessThanOrEqual(390);
        await page.keyboard.press('Escape');
        await expect(
            row.getByRole('button', { name: detailLabel }),
        ).toBeFocused();
        await row.getByRole('button', { name: t.calendar }).last().click();
        await expect(page.getByRole('dialog')).toBeVisible();
        const box = await page.getByRole('dialog').boundingBox();
        expect(box!.x + box!.width).toBeLessThanOrEqual(390);
        await page.screenshot({
            path: test.info().outputPath(`calendar-${locale}-mobile.png`),
        });
        await page.keyboard.press('Escape');
        const savedRequest = page.waitForRequest(
            (request) => request.method() === 'PUT' && request.url() === link,
        );
        await page.getByRole('button', { name: t.save, exact: true }).click();
        expect(
            (await savedRequest).postDataJSON().transactions[0].booked_on,
        ).toBe(`2027-${String(month).padStart(2, '0')}-08`);
        await page.reload();
        await expect(
            row.getByRole('button', { name: detailLabel, exact: true }),
        ).toBeVisible();
        await page
            .getByRole('button', { name: t.reanalyze, exact: true })
            .click();
        await page
            .getByRole('dialog')
            .getByRole('button', { name: t.reanalyze, exact: true })
            .click();
        // Missing synthetic source fails before any AI request; the saved draft stays editable.
        await expect(
            page.getByRole('alert').filter({ hasText: /AI/ }).first(),
        ).toBeVisible();
        await page.reload();
        await expect(row.getByRole('spinbutton')).toHaveValue('317.50');
        await page.getByRole('button', { name: t.remove, exact: true }).click();
        await page
            .getByRole('dialog')
            .getByRole('button', { name: t.remove, exact: true })
            .click();
        await page.waitForURL(/\/bank-statements$/);
        await expect(
            page.getByText(`synthetic-actions-${locale}.pdf`, { exact: true }),
        ).toHaveCount(0);
    });
}
