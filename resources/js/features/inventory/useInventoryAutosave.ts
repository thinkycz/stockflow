import { reactive } from 'vue';

export type DraftValues = {
    quantity: string;
    classification: string | null;
    note: string;
};
export type SavedDraftRow = {
    quantity: number;
    classification: string | null;
    note: string | null;
    revision: number;
};
type State = 'idle' | 'saving' | 'saved' | 'error' | 'conflict';
type Response = { revision: number; row: SavedDraftRow | null };

/** Serialize each row independently and keep conflicts until explicitly resolved. */
export function useInventoryAutosave(
    initialRevisions: Record<number, number>,
    send: (
        id: number,
        values: DraftValues,
        revision: number,
    ) => Promise<Response>,
) {
    const state = reactive<Record<number, State>>({});
    const conflicts = reactive<Record<number, Response>>({});
    const revisions = { ...initialRevisions };
    const generations: Record<number, number> = {};
    const queued = new Map<number, DraftValues>();
    const running = new Map<number, Promise<void>>();

    function dirty(id: number): void {
        generations[id] = (generations[id] ?? 0) + 1;
        if (state[id] !== 'conflict') state[id] = 'idle';
    }

    async function drain(id: number): Promise<void> {
        while (queued.has(id) && state[id] !== 'conflict') {
            const values = queued.get(id)!;
            queued.delete(id);
            const generation = generations[id] ?? 0;
            state[id] = 'saving';
            try {
                const result = await send(id, values, revisions[id] ?? 0);
                revisions[id] = result.revision;
                state[id] =
                    generation === (generations[id] ?? 0) ? 'saved' : 'idle';
            } catch (error) {
                const response = (
                    error as { response?: { status: number; data: Response } }
                ).response;
                if (response?.status === 409) {
                    conflicts[id] = response.data;
                    state[id] = 'conflict';
                } else {
                    state[id] = 'error';
                }
                queued.delete(id);
                return;
            }
        }
    }

    function save(id: number, values: DraftValues): void {
        if (state[id] === 'conflict') return;
        queued.set(id, { ...values });
        if (!running.has(id)) {
            const task = drain(id).finally(() => running.delete(id));
            running.set(id, task);
        }
    }

    function resolve(id: number): SavedDraftRow | null {
        const conflict = conflicts[id];
        if (!conflict) return null;
        revisions[id] = conflict.revision;
        delete conflicts[id];
        state[id] = 'idle';
        return conflict.row;
    }

    async function settled(): Promise<void> {
        while (running.size) await Promise.all([...running.values()]);
    }

    return { state, conflicts, dirty, save, resolve, settled };
}
