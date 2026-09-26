import { beforeEach, describe, expect, test, vi } from 'vitest';
import { effectScope, nextTick, reactive, ref } from 'vue';
import { useShiftBulkDelete } from '@/features/shifts/useShiftBulkDelete';
import type { Shift } from '@/features/shifts/scheduling-types';

const mocks = vi.hoisted(() => ({ confirm: vi.fn(), post: vi.fn() }));
vi.mock('@/composables/useDialog', () => ({
    useDialog: () => ({ confirm: mocks.confirm }),
}));
vi.mock('@/composables/useRoute', () => ({
    useRoute: () => () => '/shifts/bulk-delete',
}));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@inertiajs/vue3', () => ({
    useForm: (data: object) =>
        reactive({
            ...data,
            processing: false,
            errors: {},
            clearErrors: vi.fn(),
            post: mocks.post,
        }),
}));

function fixture(count = 3) {
    const scope = effectScope();
    const props = reactive({
        store: { id: 1, name: 'Store A' },
        workers: [],
        is_admin: true,
    });
    const month = ref(10);
    const year = ref(2026);
    const shifts = ref<Shift[]>(
        Array.from({ length: count }, (_, i) => ({
            id: i + 1,
            worker_id: 1,
            date: '2026-10-15',
            start_time: '09:00',
            end_time: '10:00',
        })),
    );
    const stop = vi.fn();
    const bulk = scope.run(() =>
        useShiftBulkDelete(
            props,
            shifts,
            month,
            year,
            ref('October 2026'),
            stop,
        ),
    )!;
    return { scope, props, month, shifts, stop, bulk };
}

describe('bulk shift selection', () => {
    beforeEach(() => vi.clearAllMocks());

    test('selects the complete month beyond 1000 records and preserves selection on cancelled confirmation', async () => {
        const f = fixture(1002);
        f.bulk.show();
        expect(f.stop).toHaveBeenCalledOnce();
        expect(f.bulk.selectedIds.value).toEqual([]);
        f.bulk.selectAll();
        expect(f.bulk.selectedIds.value).toHaveLength(1002);
        f.bulk.toggle(2, false);
        mocks.confirm.mockResolvedValue(false);
        await f.bulk.submit();
        expect(f.bulk.selectedIds.value).toHaveLength(1001);
        expect(f.bulk.selectedSet.value.has(2)).toBe(false);
        expect(mocks.post).not.toHaveBeenCalled();
        f.bulk.clearSelection();
        expect(f.bulk.selectedIds.value).toEqual([]);
        f.scope.stop();
    });

    test.each(['month', 'store'] as const)(
        'invalidates pending confirmation after %s changes',
        async (context) => {
            const f = fixture();
            f.bulk.show();
            f.bulk.selectAll();
            let resolve!: (value: boolean) => void;
            mocks.confirm.mockReturnValue(
                new Promise<boolean>((done) => {
                    resolve = done;
                }),
            );
            const pending = f.bulk.submit();
            await f.bulk.submit();
            expect(mocks.confirm).toHaveBeenCalledOnce();
            if (context === 'month') f.month.value = 11;
            else f.props.store = { id: 2, name: 'Store B' };
            resolve(true);
            await pending;
            expect(f.bulk.open.value).toBe(false);
            expect(f.bulk.selectedIds.value).toEqual([]);
            expect(mocks.post).not.toHaveBeenCalled();
            f.scope.stop();
        },
    );

    test('new shifts do not silently enter the confirmed selection and submitting is blocked while busy', async () => {
        const f = fixture();
        f.bulk.show();
        f.bulk.selectAll();
        f.shifts.value.push({ ...f.shifts.value[0], id: 4 });
        await nextTick();
        mocks.confirm.mockResolvedValue(true);
        await f.bulk.submit();
        expect(f.bulk.form.shift_ids).toEqual([1, 2, 3]);
        expect(f.bulk.form.store_id).toBe(1);
        f.bulk.form.processing = true;
        await f.bulk.submit();
        f.bulk.close();
        expect(f.bulk.open.value).toBe(true);
        expect(mocks.post).toHaveBeenCalledOnce();
        f.scope.stop();
    });
});
