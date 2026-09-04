import { workflowSource } from './helpers/workflow-source';
import { resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

const source = workflowSource(
    resolve(process.cwd(), 'resources/js/pages/attendance/Index.vue'),
    'utf8',
);
const reportSource = workflowSource(
    resolve(process.cwd(), 'resources/js/pages/attendance/Report.vue'),
    'utf8',
);
const checklistSource = workflowSource(
    resolve(process.cwd(), 'resources/js/pages/checklists/Index.vue'),
    'utf8',
);

describe('attendance table contract', () => {
    test('keeps the DataTable standalone below a separate timer Card', () => {
        expect(source).toContain('<DataTable');
        expect(source).toContain('<Card');
        expect(source).toContain('data-testid="attendance-timer-panel"');
        expect(source).toContain("performTimerAction('arrival')");
        expect(source).toContain("performTimerAction('break_start')");
        expect(source).toContain("performTimerAction('break_end')");
        expect(source).toContain("performTimerAction('departure')");
        expect(source.indexOf('</Card>')).toBeLessThan(
            source.indexOf('<DataTable'),
        );
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

    test('inactive historical reports are read only and keep exact store context', () => {
        expect(reportSource).toContain('store.is_active &&');
        expect(reportSource).toContain('v-if="store.is_active"');
        expect(reportSource).toContain('store_id: props.store?.id ?? null');
    });

    test('uses active workers for new corrections while retaining historical filter workers', () => {
        expect(reportSource).toContain('active_workers: Worker[]');
        expect(reportSource).toContain(
            'const correctionWorkerOptions = computed',
        );
        expect(reportSource).toContain(
            'const workers = [...props.active_workers]',
        );
        expect(reportSource).toContain(':options="correctionWorkerOptions"');
    });

    test('keeps inactive checklist history in exact store context and read only', () => {
        expect(checklistSource).toContain('is_active: boolean');
        expect(checklistSource).toContain('store_id: props.active_store?.id');
        expect(checklistSource).toContain('v-if="active_store.is_active"');
        expect(checklistSource).toContain(
            'history_detail && active_store?.is_active',
        );
    });
});
