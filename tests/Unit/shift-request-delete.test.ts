import { beforeEach, describe, expect, test, vi } from 'vitest';
import { computed, effectScope, reactive, ref } from 'vue';
import { useShiftEditor } from '@/features/shifts/useShiftEditor';
import type { CalendarRequest } from '@/features/shifts/scheduling-types';

const mocks = vi.hoisted(() => ({
    confirm: vi.fn(),
    delete: vi.fn(),
    route: vi.fn(() => '/shift-requests/7'),
}));
vi.mock('@/composables/useDialog', () => ({
    useDialog: () => ({ confirm: mocks.confirm }),
}));
vi.mock('@/composables/useRoute', () => ({ useRoute: () => mocks.route }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@inertiajs/vue3', () => ({
    router: { delete: mocks.delete },
    useForm: (data: object) =>
        reactive({
            ...data,
            processing: false,
            errors: {},
            reset: vi.fn(),
            clearErrors: vi.fn(),
        }),
}));

function fixture() {
    const scope = effectScope();
    const props = reactive({ store: { id: 1 }, workers: [] });
    const month = ref(10);
    const year = ref(2026);
    const request: CalendarRequest = {
        id: 7,
        worker_id: 1,
        worker_name: 'Test Worker',
        worker_color: '#123456',
        date: '2026-10-15',
        start_time: '09:00',
        end_time: '17:00',
    };
    const requests = ref([request]);
    const editor = scope.run(() =>
        useShiftEditor(
            props,
            month,
            year,
            computed(() => [
                {
                    date: request.date,
                    day: 15,
                    isCurrentMonth: true,
                    shifts: [],
                    requests: requests.value,
                },
            ]),
        ),
    )!;
    editor.openDayModal(request.date);
    return { scope, props, month, request, requests, editor };
}

describe('administrator request cancellation', () => {
    beforeEach(() => vi.resetAllMocks());

    test('cancelled confirmation keeps the request and its edit form', async () => {
        const f = fixture();
        f.editor.editRequest(f.request);
        mocks.confirm.mockResolvedValue(false);
        await f.editor.deleteRequest(f.request);
        expect(mocks.delete).not.toHaveBeenCalled();
        expect(f.editor.editingRequestId.value).toBe(f.request.id);
        expect(f.editor.deletingRequestId.value).toBeNull();
        f.scope.stop();
    });

    test.each(['store', 'month', 'modal', 'request'] as const)(
        'does not delete after the %s changes while confirmation is open',
        async (context) => {
            const f = fixture();
            let resolve!: (confirmed: boolean) => void;
            mocks.confirm.mockReturnValue(
                new Promise<boolean>((done) => (resolve = done)),
            );
            const pending = f.editor.deleteRequest(f.request);
            await f.editor.deleteRequest(f.request);
            expect(mocks.confirm).toHaveBeenCalledOnce();
            if (context === 'store') f.props.store.id = 2;
            if (context === 'month') f.month.value = 11;
            if (context === 'modal') f.editor.closeModal();
            if (context === 'request') f.requests.value = [];
            resolve(true);
            await pending;
            expect(mocks.delete).not.toHaveBeenCalled();
            expect(f.editor.deletingRequestId.value).toBeNull();
            f.scope.stop();
        },
    );

    test('confirmed deletion uses the original store and clears the edit form only on success', async () => {
        const f = fixture();
        f.editor.editRequest(f.request);
        mocks.confirm.mockResolvedValue(true);
        await f.editor.deleteRequest(f.request);
        expect(mocks.route).toHaveBeenCalledWith('shift-requests.destroy', {
            shiftRequest: 7,
            store_id: 1,
            month: 10,
            year: 2026,
        });
        expect(mocks.delete).toHaveBeenCalledOnce();
        expect(f.editor.editingRequestId.value).toBe(7);
        const options = mocks.delete.mock.calls[0][1];
        options.onSuccess();
        options.onFinish();
        expect(f.editor.editingRequestId.value).toBeNull();
        expect(f.editor.deletingRequestId.value).toBeNull();
        f.scope.stop();
    });
});
