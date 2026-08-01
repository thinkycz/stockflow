import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

const pagesRoot = resolve(process.cwd(), 'resources/js/pages');

function headerOf(page: string): string {
    const source = readFileSync(resolve(pagesRoot, page), 'utf8');
    const headerStart = source.indexOf('<header');
    const headerEnd = source.indexOf('</header>', headerStart);

    expect(headerStart).toBeGreaterThanOrEqual(0);
    expect(headerEnd).toBeGreaterThan(headerStart);

    return source.slice(headerStart, headerEnd);
}

describe('page filter layout contract', () => {
    test('single month filters live in the page header', () => {
        for (const page of [
            'income-expenses/Index.vue',
            'income-expenses/RecurringExpenses.vue',
            'payroll/Index.vue',
            'reports/Index.vue',
            'statements/Index.vue',
        ]) {
            const header = headerOf(page);

            expect(header).toContain('<FilterField');
            expect(header).toContain('<MonthPicker');
        }
    });

    test('single search filters live in the page header', () => {
        for (const page of [
            'items/Index.vue',
            'stores/Index.vue',
            'users/Index.vue',
            'workers/Index.vue',
        ]) {
            const header = headerOf(page);

            expect(header).toContain('<SearchFilter');
            expect(header).toContain(':label=');
        }
    });
});
