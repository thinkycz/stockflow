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
