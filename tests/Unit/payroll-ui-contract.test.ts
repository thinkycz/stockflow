import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

const resourcesRoot = resolve(process.cwd(), 'resources/js');

describe('payroll ui contract', () => {
    test('the overview stays summary-only with one detail action', () => {
        const source = readFileSync(
            resolve(resourcesRoot, 'pages/payroll/Index.vue'),
            'utf8',
        );

        expect(source).not.toContain('<details>');
        expect(source).not.toContain('openAdjustment');
        expect(source).not.toContain('openWageOverride');
        expect(source).toContain("route('payroll.show'");
        expect(source).toContain("t('common.detail')");
        expect(source).toContain("route('payroll.workers.store'");
        expect(source).toContain('<Combobox');
    });

    test('the detail owns payroll editing and both print variants', () => {
        const source = readFileSync(
            resolve(resourcesRoot, 'pages/payroll/Show.vue'),
            'utf8',
        );

        expect(source).toContain('openAdjustment');
        expect(source).toContain('openWageOverride');
        expect(source).toContain('<PayrollPrintMenu');
        expect(source).toContain('payslip.shifts');
        expect(source).toContain('payslip.attendance');
        expect(source).toContain("route('payroll.workers.destroy'");
        expect(source).toContain('payslip.can_remove');
        expect(source).not.toContain('<Card :padded="false"');
        expect(source).not.toContain('variant="nested"');
    });

    test('the print menu exposes one accessible trigger and two menu items', () => {
        const source = readFileSync(
            resolve(resourcesRoot, 'components/payroll/PayrollPrintMenu.vue'),
            'utf8',
        );

        expect(source).toContain('aria-haspopup="menu"');
        expect(source).toContain(':aria-expanded="open"');
        expect(source).toContain('role="menu"');
        expect(source.match(/role="menuitem"/g)).toHaveLength(2);
    });
});
