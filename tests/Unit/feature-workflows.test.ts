import { beforeEach, describe, expect, test, vi } from 'vitest';
import { effectScope, reactive, ref } from 'vue';
import { useVoucherList } from '@/features/gift-vouchers/useVoucherList';
import { useShiftPresets } from '@/features/shifts/useShiftPresets';
import { useShiftRequestApproval } from '@/features/shifts/useShiftRequestApproval';
import { useShiftQuickAdd } from '@/features/shifts/useShiftQuickAdd';
import { useClientToast } from '@/composables/useClientToast';
import type { GiftVoucherRow } from '@/features/gift-vouchers/types';
import type { MonthlyShiftSummary } from '@/features/shifts/types';
import type { Shift, Worker } from '@/features/shifts/scheduling-types';

const mocks = vi.hoisted(() => ({
    post: vi.fn(),
    get: vi.fn(),
    remove: vi.fn(),
    put: vi.fn(),
    prompt: vi.fn(),
    confirm: vi.fn(),
    axiosPost: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { post: mocks.post, get: mocks.get, delete: mocks.remove },
    useForm: <T extends object>(defaults: T) =>
        reactive({
            ...defaults,
            errors: {},
            processing: false,
            reset() {
                Object.assign(this, defaults);
            },
            clearErrors() {
                this.errors = {};
            },
            post: mocks.post,
            put: mocks.put,
        }),
}));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@/composables/useRoute', () => ({
    useRoute: () => (name: string) => name,
}));
vi.mock('@/composables/useBoundLocale', () => ({ useBoundLocale: () => {} }));
vi.mock('@/composables/useDialog', () => ({
    useDialog: () => ({ prompt: mocks.prompt, confirm: mocks.confirm }),
}));

beforeEach(() => {
    vi.clearAllMocks();
    mocks.confirm.mockResolvedValue(true);
});

describe('voucher lifecycle feedback', () => {
    test.each(['voidVoucher', 'reverseVoucher'] as const)(
        '%s surfaces server validation and does not navigate on cancellation',
        async (action) => {
            const state = useVoucherList({
                batches: [],
                filters: { status: null, search: null },
            });
            const voucher = { id: 42 } as GiftVoucherRow;
            mocks.prompt.mockResolvedValueOnce(null);
            await state[action](voucher);
            expect(mocks.post).not.toHaveBeenCalled();

            mocks.prompt.mockResolvedValueOnce('Duplicate entry');
            await state[action](voucher);
            const [, data, options] = mocks.post.mock.calls[0];
            expect(data).toEqual({ reason: 'Duplicate entry' });
            expect(options.headers['X-StockFlow-Action']).toBe('true');
            options.onError({ voucher: ['The voucher was already changed.'] });
            expect(useClientToast().clientToast.value).toMatchObject({
                type: 'error',
                message: 'The voucher was already changed.',
            });
        },
    );
});

describe('shift workflow state boundaries', () => {
    test('preset editing clears selection after saving and tells quick-add before deletion', async () => {
        const deleting = vi.fn();
        const presets = useShiftPresets(ref(9), ref(2026), deleting);
        presets.editPreset({
            id: 7,
            name: 'Morning',
            start_time: '08:00',
            end_time: '13:00',
        });
        presets.submitPreset();
        expect(mocks.put.mock.calls[0][0]).toBe('shift-presets.update');
        mocks.put.mock.calls[0][1].onSuccess();
        expect(presets.editingPresetId.value).toBeNull();
        expect(presets.presetForm.start_time).toBe('09:00');
        await presets.deletePreset({
            id: 7,
            name: 'Morning',
            start_time: '08:00',
            end_time: '13:00',
        });
        expect(deleting).toHaveBeenCalledWith(7);
        expect(mocks.remove.mock.calls[0][0]).toBe('shift-presets.destroy');
    });

    test('request approval retries overlap only after confirmation and clears processing', async () => {
        const state = useShiftRequestApproval(ref(9), ref(2026));
        const request = {
            id: 9,
            worker_id: 3,
            date: '2026-09-04',
            start_time: '08:00',
            end_time: '12:00',
            worker_name: 'A B',
            worker_color: '#123456',
        };
        state.approveRequest(request);
        expect(state.approvingRequestId.value).toBe(9);
        const [, data, options] = mocks.post.mock.calls[0];
        expect(data.allow_overlap).toBe(false);
        options.onError({ overlap: 'Overlapping shift' });
        await Promise.resolve();
        expect(mocks.post).toHaveBeenCalledTimes(2);
        expect(mocks.post.mock.calls[1][1].allow_overlap).toBe(true);
        mocks.post.mock.calls[1][2].onFinish();
        expect(state.approvingRequestId.value).toBeNull();
    });

    test('quick-add coalesces duplicate clicks and updates salary once from server contribution', async () => {
        vi.stubGlobal('window', { axios: { post: mocks.axiosPost } });
        const worker: Worker = {
            id: 3,
            first_name: 'A',
            last_name: 'B',
            color: '#123456',
            archived: false,
            attendance_rating_enabled: false,
        };
        const shifts = ref<Shift[]>([]);
        const summary = ref<MonthlyShiftSummary[]>([]);
        const scope = effectScope();
        const state = scope.run(() =>
            useShiftQuickAdd(
                { store: { id: 1 }, workers: [worker], is_admin: true },
                shifts,
                summary,
                vi.fn(),
            ),
        )!;
        state.selectedWorkerId.value = '3';
        state.selectedPresetId.value = '7';
        state.startQuickAdd();
        let complete!: (result: unknown) => void;
        mocks.axiosPost.mockImplementation(
            () =>
                new Promise((resolve) => {
                    complete = resolve;
                }),
        );
        const day = { date: '2026-09-04', isCurrentMonth: true };
        state.handleDayClick(day);
        state.handleDayClick(day);
        expect(mocks.axiosPost).toHaveBeenCalledTimes(1);
        complete({
            data: {
                status: 'created',
                shift: {
                    id: 4,
                    worker_id: 3,
                    date: day.date,
                    start_time: '08:00',
                    end_time: '12:00',
                },
                contribution: { minutes: 240, salary: 500 },
            },
        });
        await Promise.resolve();
        expect(shifts.value).toHaveLength(1);
        expect(summary.value[0]).toMatchObject({
            worker_id: 3,
            hours: 4,
            salary: 500,
        });
        expect(state.pendingDates.value.size).toBe(0);
        scope.stop();
        vi.unstubAllGlobals();
    });
});
