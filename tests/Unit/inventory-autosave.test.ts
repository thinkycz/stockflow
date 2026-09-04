import { describe, expect, it, vi } from 'vitest';
import { useInventoryAutosave } from '../../resources/js/features/inventory/useInventoryAutosave';

const values = (quantity: string) => ({
    quantity,
    classification: null,
    note: '',
});
const result = (revision: number) => ({ revision, row: null });
function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (error: unknown) => void;
    const promise = new Promise<T>((yes, no) => {
        resolve = yes;
        reject = no;
    });
    return { promise, resolve, reject };
}

describe('inventory autosave', () => {
    it('serializes and coalesces same-row edits using the acknowledged revision', async () => {
        const first = deferred<ReturnType<typeof result>>();
        const send = vi
            .fn()
            .mockReturnValueOnce(first.promise)
            .mockResolvedValue(result(2));
        const saves = useInventoryAutosave({}, send);
        saves.save(1, values('1'));
        saves.dirty(1);
        saves.save(1, values('2'));
        saves.dirty(1);
        saves.save(1, values('3'));
        expect(send).toHaveBeenCalledTimes(1);
        first.resolve(result(1));
        await saves.settled();
        expect(send).toHaveBeenLastCalledWith(1, values('3'), 1);
        expect(send).toHaveBeenCalledTimes(2);
        expect(saves.state[1]).toBe('saved');
    });
    it('keeps newest failure unsaved after an older success', async () => {
        const first = deferred<ReturnType<typeof result>>();
        const send = vi
            .fn()
            .mockReturnValueOnce(first.promise)
            .mockRejectedValue(new Error('offline'));
        const saves = useInventoryAutosave({}, send);
        saves.save(1, values('1'));
        saves.dirty(1);
        saves.save(1, values('2'));
        first.resolve(result(1));
        await saves.settled();
        expect(saves.state[1]).toBe('error');
    });
    it('does not acknowledge an edit made during a request before blur', async () => {
        const request = deferred<ReturnType<typeof result>>();
        const saves = useInventoryAutosave({}, () => request.promise);
        saves.save(1, values('1'));
        saves.dirty(1);
        request.resolve(result(1));
        await saves.settled();
        expect(saves.state[1]).toBe('idle');
    });
    it('requires explicit conflict resolution and can conflict again on reapply', async () => {
        const authoritative = {
            revision: 7,
            row: { quantity: 5, classification: null, note: null, revision: 7 },
        };
        const send = vi.fn().mockRejectedValue({
            response: { status: 409, data: authoritative },
        });
        const saves = useInventoryAutosave({}, send);
        saves.save(1, values('9'));
        await saves.settled();
        saves.save(1, values('9'));
        expect(send).toHaveBeenCalledTimes(1);
        expect(saves.conflicts[1]?.row?.quantity).toBe(5);
        expect(saves.resolve(1)?.quantity).toBe(5);
        saves.save(1, values('9'));
        await saves.settled();
        expect(send).toHaveBeenLastCalledWith(1, values('9'), 7);
        expect(saves.state[1]).toBe('conflict');
    });
    it('allows independent rows to complete in reverse order', async () => {
        const first = deferred<ReturnType<typeof result>>();
        const second = deferred<ReturnType<typeof result>>();
        const send = vi
            .fn()
            .mockReturnValueOnce(first.promise)
            .mockReturnValueOnce(second.promise);
        const saves = useInventoryAutosave({}, send);
        saves.save(1, values('1'));
        saves.save(2, values('2'));
        second.resolve(result(1));
        first.reject(new Error('offline'));
        await saves.settled();
        expect(saves.state[1]).toBe('error');
        expect(saves.state[2]).toBe('saved');
    });
});
