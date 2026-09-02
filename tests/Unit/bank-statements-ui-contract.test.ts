import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

const indexPage = readFileSync(
    resolve(process.cwd(), 'resources/js/pages/bank-statements/Index.vue'),
    'utf8',
);
const detailPage = readFileSync(
    resolve(process.cwd(), 'resources/js/pages/bank-statements/Show.vue'),
    'utf8',
);
const statementsPage = readFileSync(
    resolve(process.cwd(), 'resources/js/pages/statements/Index.vue'),
    'utf8',
);

describe('bank statement ui contract', () => {
    test('upload warns about external AI and only accepts PDF', () => {
        expect(indexPage).toContain('external_ai_notice');
        expect(indexPage).toContain('accept="application/pdf,.pdf"');
        expect(indexPage).toContain('forceFormData: true');
    });

    test('detail polls partial props until a terminal state', () => {
        expect(detailPage).toContain(
            "only: ['statement', 'transactions', 'reconciliation']",
        );
        expect(detailPage).toContain('if (terminal) stopPolling()');
        expect(detailPage).toContain('onUnmounted(stopPolling)');
    });

    test('review supports filtering and adding or removing movements', () => {
        expect(detailPage).toContain('const visibleRows = computed');
        expect(detailPage).toContain('function addRow(): void');
        expect(detailPage).toContain('function removeRow(index: number): void');
        expect(detailPage).toContain('v-if="props.statement.editable"');
    });

    test('monthly statements page exposes the compact bank control panel', () => {
        expect(statementsPage).toContain('props.bank_reconciliation');
        expect(statementsPage).toContain('statements.bank_control.summary');
        expect(statementsPage).toContain("route('bank-statements.show'");
    });
});
