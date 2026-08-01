import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

const resourcesRoot = resolve(process.cwd(), 'resources/js');
const monthPickerPath = resolve(resourcesRoot, 'components/ui/MonthPicker.vue');
const monthPages = [
    'pages/attendance/Report.vue',
    'pages/income-expenses/Index.vue',
    'pages/payroll/Index.vue',
    'pages/reports/Index.vue',
    'pages/statements/Index.vue',
];

describe('month picker contract', () => {
    test('the shared component owns month input presentation', () => {
        const component = readFileSync(monthPickerPath, 'utf8');

        expect(component).toContain('type="month"');
        expect(component).toContain("cn('w-44', props.class)");
        expect(component).toContain('change: [value: string]');
    });

    test.each(monthPages)('%s uses the shared month picker', (page) => {
        const source = readFileSync(resolve(resourcesRoot, page), 'utf8');

        expect(source).toContain('@/components/ui/MonthPicker.vue');
        expect(source).toContain('<MonthPicker');
        expect(source).not.toContain('type="month"');
    });
});
