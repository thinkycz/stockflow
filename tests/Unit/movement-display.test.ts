import { describe, expect, test } from 'vitest';
import { movementDisplayLabelKey } from '@/lib/movement-display';

describe('movement display labels', () => {
    test('labels only warehouse-to-retail transfers as outgoing', () => {
        const warehouse = { is_warehouse: true };
        const retail = { is_warehouse: false };

        expect(movementDisplayLabelKey('transfer', warehouse, retail)).toBe(
            'outgoing',
        );
        expect(movementDisplayLabelKey('transfer', retail, warehouse)).toBe(
            'transfer',
        );
        expect(movementDisplayLabelKey('transfer', retail, retail)).toBe(
            'transfer',
        );
        expect(movementDisplayLabelKey('transfer', warehouse, warehouse)).toBe(
            'transfer',
        );
        expect(movementDisplayLabelKey('transfer', null, retail)).toBe(
            'transfer',
        );
    });

    test('keeps non-transfer movement labels canonical', () => {
        expect(movementDisplayLabelKey('incoming')).toBe('incoming');
        expect(movementDisplayLabelKey('inventory_reconciliation')).toBe(
            'inventory_reconciliation',
        );
        expect(movementDisplayLabelKey('reversal')).toBe('reversal');
    });
});
