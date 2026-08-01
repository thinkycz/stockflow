import { readFileSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

const resourcesRoot = resolve(process.cwd(), 'resources/js');
const dataTablePath = resolve(resourcesRoot, 'components/ui/DataTable.vue');

function vueFiles(directory: string): string[] {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = resolve(directory, entry.name);

        if (entry.isDirectory()) return vueFiles(path);

        return entry.isFile() && entry.name.endsWith('.vue') ? [path] : [];
    });
}

describe('data table contract', () => {
    test('all application tables use the shared component', () => {
        const directTables = vueFiles(resourcesRoot)
            .filter((path) => path !== dataTablePath)
            .filter((path) => readFileSync(path, 'utf8').includes('<table'));

        expect(directTables).toEqual([]);
    });

    test('the shared component owns framing, density and responsive variants', () => {
        const component = readFileSync(dataTablePath, 'utf8');

        expect(component).toContain("density?: 'default' | 'compact'");
        expect(component).toContain("variant?: 'standalone' | 'nested'");
        expect(component).toContain('tableClass?: string');
        expect(component).toContain('data-table-frame');
        expect(component).toContain('data-table-scroll');
        expect(component).toContain('cell.dataset.label = label');
        expect(component).toContain("cell.dataset.generatedLabel = 'true'");
    });

    test('table consumers do not reintroduce legacy scrolling or cell padding', () => {
        const legacyConsumers = vueFiles(resourcesRoot)
            .filter((path) => path !== dataTablePath)
            .filter((path) => {
                const source = readFileSync(path, 'utf8');

                return (
                    source.includes('<DataTable') &&
                    (source.includes('[&_td]:') ||
                        /overflow-x-auto[\s\S]{0,120}<DataTable/.test(source))
                );
            });

        expect(legacyConsumers).toEqual([]);
    });
});
