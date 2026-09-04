import { readdirSync, readFileSync } from 'node:fs';
import { extname, resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

const jsRoot = resolve(process.cwd(), 'resources/js');

function sourceFiles(directory: string): string[] {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = resolve(directory, entry.name);

        if (entry.isDirectory()) return sourceFiles(path);
        return ['.ts', '.vue'].includes(extname(entry.name)) ? [path] : [];
    });
}

describe('UI consistency contract', () => {
    test('wrapped pages delegate document titles to their layout', () => {
        for (const file of sourceFiles(resolve(jsRoot, 'pages'))) {
            const source = readFileSync(file, 'utf8');
            if (!source.includes('<AppLayout')) continue;

            expect(source, file).not.toContain('<Head');
            expect(source, file).not.toMatch(/\bHead\b.*@inertiajs\/vue3/);
        }
    });

    test('standard forms use the documented width and shared header', () => {
        for (const page of [
            'items/Create.vue',
            'items/Edit.vue',
            'stores/Create.vue',
            'stores/Edit.vue',
            'users/Create.vue',
            'users/Edit.vue',
            'workers/Create.vue',
            'workers/Edit.vue',
            'settings/Index.vue',
        ]) {
            const source = readFileSync(resolve(jsRoot, 'pages', page), 'utf8');
            expect(source, page).toContain('max-w-3xl');
            expect(source, page).toContain('<PageHeader');
        }
    });

    test('shared tabs own requested tab families', () => {
        for (const page of [
            'pages/reports/Index.vue',
            'pages/recipes/Show.vue',
            'pages/checklists/Index.vue',
            'features/noticeboard/components/NoticeboardSection.vue',
        ]) {
            expect(readFileSync(resolve(jsRoot, page), 'utf8'), page).toContain(
                '<Tabs',
            );
        }
    });

    test('active store is the first left-aligned page context', () => {
        for (const page of [
            'income-expenses/Index.vue',
            'payroll/Index.vue',
            'checklists/Index.vue',
        ]) {
            const source = readFileSync(resolve(jsRoot, 'pages', page), 'utf8');
            const context = source.match(
                /<template[^>]*#context[^>]*>([\s\S]*?)<\/template>/,
            )?.[1];

            expect(context, page).toBeDefined();
            expect(context, page).toContain('<StoreContextIndicator');
            expect(
                context?.indexOf('<StoreContextIndicator'),
                page,
            ).toBeLessThan(
                context?.indexOf('<Badge') === -1
                    ? Number.POSITIVE_INFINITY
                    : (context?.indexOf('<Badge') ?? Number.POSITIVE_INFINITY),
            );
            expect(source, page).not.toMatch(
                /<template[^>]*#actions[^>]*>[\s\S]*?<StoreContextIndicator/,
            );
        }
    });

    test('native browser confirmation and prompt APIs are not used', () => {
        for (const file of sourceFiles(jsRoot)) {
            const source = readFileSync(file, 'utf8');

            expect(source, file).not.toMatch(/window\.(confirm|prompt)\s*\(/);
        }
    });

    test('ordinary page form controls use shared primitives', () => {
        const allowedSpecializedPages = new Set([
            resolve(jsRoot, 'pages/reports/Index.vue'),
        ]);

        for (const file of sourceFiles(resolve(jsRoot, 'pages'))) {
            if (allowedSpecializedPages.has(file)) continue;

            const source = readFileSync(file, 'utf8');
            expect(source, file).not.toMatch(/<(input|select|label)\b/);
            expect(source, file).not.toMatch(/<button\b/);
        }
    });

    test('detail summaries and navigation use shared components', () => {
        for (const page of [
            'items/Show.vue',
            'stores/Show.vue',
            'stock-movements/Show.vue',
        ]) {
            const source = readFileSync(resolve(jsRoot, 'pages', page), 'utf8');
            expect(source, page).toContain('<MetricCard');
            expect(source, page).toContain('<BackLink');
        }
    });

    test('page-level empty states use the shared component', () => {
        for (const page of [
            'inventory-counts/Index.vue',
            'shifts/Index.vue',
            'statements/Index.vue',
        ]) {
            expect(
                readFileSync(resolve(jsRoot, 'pages', page), 'utf8'),
                page,
            ).toContain('<EmptyState');
        }
    });
});
