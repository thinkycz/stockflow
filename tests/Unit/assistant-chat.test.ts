import { describe, expect, it } from 'vitest';
import type { UIMessage } from 'ai';
import {
    assistantActionApprovalParts,
    assistantDecisionBody,
    assistantMessageText,
    assistantResponseError,
    hasPendingAssistantApprovals,
} from '@/lib/assistant-chat';

const pendingMessage: UIMessage = {
    id: 'assistant-1',
    role: 'assistant',
    parts: [
        {
            type: 'tool-write_stock_movements',
            toolCallId: 'call-1',
            state: 'approval-requested',
            input: {
                request: {
                    action: 'create_stock_movement',
                    mode: 'incoming',
                    store_id: 7,
                    values: { items: [{ item_id: 2, quantity: 3 }] },
                },
            },
            approval: { id: 'call-1' },
        },
    ],
};

describe('assistant chat protocol helpers', () => {
    it('combines streamed text parts', () => {
        expect(
            assistantMessageText({
                id: 'message-1',
                role: 'assistant',
                parts: [
                    { type: 'text', text: 'Live ' },
                    { type: 'text', text: 'stock data' },
                ],
            }),
        ).toBe('Live stock data');
    });

    it('detects pending approvals after hydration', () => {
        expect(hasPendingAssistantApprovals([pendingMessage])).toBe(true);
        expect(hasPendingAssistantApprovals([])).toBe(false);
    });

    it('groups every pending business action while leaving choices separate', () => {
        const grouped = structuredClone(pendingMessage);
        const second = structuredClone(pendingMessage.parts[0]);
        const choice = structuredClone(pendingMessage.parts[0]);

        Object.assign(second, {
            type: 'tool-write_workers',
            toolCallId: 'call-2',
            approval: { id: 'approval-2' },
        });
        Object.assign(choice, {
            type: 'tool-ask_user_choice',
            toolCallId: 'choice-1',
            approval: { id: 'choice-1' },
        });
        grouped.parts.push(second, choice);

        expect(
            assistantActionApprovalParts(grouped).map((part) =>
                'toolCallId' in part ? part.toolCallId : null,
            ),
        ).toEqual(['call-1', 'call-2']);
    });

    it('does not expose an HTML server error in the chat interface', async () => {
        const response = new Response(
            '<!DOCTYPE html><html><body>Laravel debug trace</body></html>',
            {
                status: 500,
                headers: { 'Content-Type': 'text/html; charset=UTF-8' },
            },
        );

        const error = await assistantResponseError(
            response,
            'The assistant is unavailable.',
        );

        expect(error.message).toBe('The assistant is unavailable.');
        expect(error.name).toBe('AssistantHttp500');
    });

    it('does not expose a JSON exception message in the chat interface', async () => {
        const response = Response.json(
            { message: 'SQLSTATE connection details and debug trace' },
            { status: 500 },
        );

        const error = await assistantResponseError(
            response,
            'The assistant is unavailable.',
        );

        expect(error.message).toBe('The assistant is unavailable.');
        expect(error.name).toBe('AssistantHttp500');
    });

    it('surfaces a bounded safe client error with its HTTP category', async () => {
        const response = Response.json(
            { error: { message: 'Another assistant turn is already active.' } },
            { status: 409 },
        );

        const error = await assistantResponseError(
            response,
            'The assistant is unavailable.',
        );

        expect(error.message).toBe('Another assistant turn is already active.');
        expect(error.name).toBe('AssistantHttp409');
    });

    it('categorizes hidden server failures without exposing their payload', async () => {
        const response = Response.json(
            { message: 'provider-secret-and-stack-trace' },
            { status: 503 },
        );

        const error = await assistantResponseError(
            response,
            'The assistant is unavailable.',
        );

        expect(error.message).toBe('The assistant is unavailable.');
        expect(error.name).toBe('AssistantHttp503');
    });

    it('builds separate read-only approve and reject decisions', () => {
        const approved = structuredClone(pendingMessage);
        const approvedPart = approved.parts[0];

        if (approvedPart === undefined || approvedPart.type === 'text') {
            throw new Error('Expected tool part.');
        }

        Object.assign(approvedPart, {
            state: 'approval-responded',
            approval: { id: 'call-1', approved: true },
        });

        expect(assistantDecisionBody([approved], new Map())).toEqual({
            'call-1': { action: 'approve' },
        });

        Object.assign(approvedPart, {
            approval: {
                id: 'call-1',
                approved: false,
                reason: 'Use another store.',
            },
        });

        expect(assistantDecisionBody([approved], new Map())).toEqual({
            'call-1': { action: 'reject' },
        });
    });

    it('builds a locked clarification selection without exposing arguments', () => {
        const selected = structuredClone(pendingMessage);
        const selectedPart = selected.parts[0];

        if (selectedPart === undefined || selectedPart.type === 'text') {
            throw new Error('Expected tool part.');
        }

        Object.assign(selectedPart, {
            type: 'tool-ask_user_choice',
            state: 'approval-responded',
            approval: { id: 'call-1', approved: true },
        });

        expect(
            assistantDecisionBody(
                [selected],
                new Map([['call-1', 'store-ostrava']]),
            ),
        ).toEqual({
            'call-1': {
                action: 'select',
                option_id: 'store-ostrava',
            },
        });
    });
});
