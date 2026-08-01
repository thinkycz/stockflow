import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

const source = readFileSync(
    resolve(process.cwd(), 'resources/js/pages/attendance/Index.vue'),
    'utf8',
);

describe('attendance table contract', () => {
    test('uses one standalone DataTable without a Card wrapper', () => {
        expect(source).toContain('<DataTable');
        expect(source).not.toContain('<Card');
        expect(source).toContain('attendance_rows');
    });

    test('keeps every mobile table cell labelled and actions last', () => {
        const cells = source.match(/<td\b[\s\S]*?>/g) ?? [];

        expect(cells).toHaveLength(6);
        for (const cell of cells) expect(cell).toContain('data-label');
        expect(cells.at(-1)).toContain('data-mobile-layout="stack"');
    });

    test('provides every attendance transition from a worker row', () => {
        for (const action of [
            "perform(row, 'arrival')",
            "perform(row, 'break_start')",
            "perform(row, 'break_end')",
            "perform(row, 'departure')",
        ]) {
            expect(source).toContain(action);
        }
        expect(source).toContain('row.quality.average_score');
    });
});
