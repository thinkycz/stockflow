import { describe, expect, test } from 'vitest';
import {
    hasUncertainAssistantOutcome,
    canRetryAssistantTurn,
} from '@/features/assistant/assistant-chat';
import {
    hasPrimaryMessageContent,
    showsReadResult,
    showsToolCard,
    showsToolProgress,
} from '@/features/assistant/message-presentation';
import type { AssistantUIMessage } from '@/features/assistant/types';

describe('assistant message presentation', () => {
    test('uncertain external outcomes are distinct from confirmed failures or successes', () => {
        expect(
            hasUncertainAssistantOutcome({
                type: 'tool-write_x',
                state: 'output-error',
                toolCallId: '1',
                errorText: 'RuntimeException: action_outcome_uncertain',
            }),
        ).toBe(true);
        expect(
            hasUncertainAssistantOutcome({
                type: 'tool-write_x',
                state: 'output-error',
                toolCallId: '1',
                errorText: 'Validation failed',
            }),
        ).toBe(false);
        expect(
            hasUncertainAssistantOutcome({
                type: 'tool-write_x',
                state: 'output-available',
                toolCallId: '1',
                errorText: 'action_outcome_uncertain',
            }),
        ).toBe(false);
    });

    test('read results replace progress without becoming write confirmation cards', () => {
        const pending = {
            type: 'tool-read_stock',
            toolCallId: '1',
            state: 'input-available',
            input: {},
        } as AssistantUIMessage['parts'][number];
        const completed = {
            ...pending,
            state: 'output-available',
            output: {},
        } as AssistantUIMessage['parts'][number];
        expect(showsToolProgress(pending)).toBe(true);
        expect(showsToolProgress(completed)).toBe(false);
        expect(showsReadResult(completed)).toBe(true);
        expect(showsToolCard(completed)).toBe(false);
        expect(
            hasPrimaryMessageContent({
                id: '1',
                role: 'assistant',
                parts: [completed],
            }),
        ).toBe(true);
    });
});

describe('assistant retry eligibility', () => {
    test('explicit server rejection wins over durable stream fallback', () => {
        expect(canRetryAssistantTurn(false, true, null)).toBe(false);
        expect(canRetryAssistantTurn(true, false, null)).toBe(true);
        expect(canRetryAssistantTurn(undefined, true, null)).toBe(true);
    });
    test('hydrated or streamed uncertainty blocks replay before server reconciliation', () => {
        expect(
            canRetryAssistantTurn(true, true, 'action_outcome_uncertain'),
        ).toBe(false);
        expect(
            canRetryAssistantTurn(
                undefined,
                true,
                'RuntimeException: action_outcome_uncertain',
            ),
        ).toBe(false);
        const message = {
            id: 'a',
            role: 'assistant',
            parts: [
                {
                    type: 'tool-write_notify',
                    state: 'output-error',
                    toolCallId: '1',
                    input: {},
                    errorText: 'action_outcome_uncertain',
                },
            ],
        } as AssistantUIMessage;
        expect(canRetryAssistantTurn(undefined, true, null, message)).toBe(
            false,
        );
    });
});
