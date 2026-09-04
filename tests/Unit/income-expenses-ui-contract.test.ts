import { workflowSource } from './helpers/workflow-source';
import { resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

const page = workflowSource(
    resolve(process.cwd(), 'resources/js/pages/income-expenses/Index.vue'),
    'utf8',
);

describe('income and expenses ui contract', () => {
    test('stock movement rows describe their source and destination', () => {
        expect(page).toContain("row.source_type === 'stock_movement'");
        expect(page).toContain('row.details.source_store_name');
        expect(page).toContain('row.details.destination_store_name');
        expect(page).toContain("t('stock_movements.types.incoming')");
    });

    test('each financial section totals calculated and used amounts', () => {
        expect(page).toContain('const financialSections = computed');
        expect(page).toContain(
            ':data-testid="`financial-totals-${section.key}`"',
        );
        expect(page).toContain('section.calculatedTotal');
        expect(page).toContain('section.effectiveTotal');
    });

    test('inactive historical reports are read only and keep exact store context', () => {
        expect(page).toContain('active_store?.is_active &&');
        expect(page).toContain('store_id: props.active_store?.id ?? null');
        expect(page).toContain('store_id: active_store?.id ?? null');
    });
});
