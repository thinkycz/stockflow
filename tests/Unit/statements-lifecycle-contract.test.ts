import { workflowSource } from './helpers/workflow-source';
import { resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

describe('inactive statement lifecycle contract', () => {
    test('the statement editor carries exact store context and hides mutations when read only', () => {
        const source = workflowSource(
            resolve(process.cwd(), 'resources/js/pages/statements/Index.vue'),
            'utf8',
        );

        expect(source).toContain(
            '<StoreContextIndicator :store="props.store" />',
        );
        expect(source).toContain('props.editable &&');
        expect(source).toContain('v-if="props.editable"');
        expect(source).toContain(':disabled="!props.editable"');
        expect(source).toContain('store_id: props.filters.store_id');
    });

    test('version restore is unavailable for an inactive historical store', () => {
        const source = workflowSource(
            resolve(process.cwd(), 'resources/js/pages/statements/Version.vue'),
            'utf8',
        );

        expect(source).toContain('v-if="props.statement.store_active"');
    });
});
