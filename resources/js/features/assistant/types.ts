import type { UIMessage } from 'ai';

export type ConversationSummary = {
    id: string;
    title: string;
    updated_at: string | null;
};

export type ConversationPayload = {
    id: string;
    title: string;
    messages: AssistantUIMessage[];
    active_turn: AssistantTurnPayload | null;
    slack?: {
        url: string;
        channel_id: string;
        active_store_id: number | null;
        active_store_name: string | null;
        mapping_status: string;
        history_ready: boolean;
        history_error: string | null;
        decisions: { origin: string; author_id: string; action: string }[];
    } | null;
};

export type AssistantTurnPayload = {
    id: string;
    status: string;
    kind: string;
    message: string | null;
    queued_at: string;
    recovery_mode: string;
    can_retry: boolean;
    completed_actions: Record<string, unknown>[];
    failure: { code: string; message: string } | null;
    partial_response: {
        id: string;
        text: string;
        created_at: string;
    } | null;
};

export type AssistantMessageMetadata = {
    created_at?: string;
};

export type AssistantUIMessage = UIMessage<AssistantMessageMetadata>;

export type ApprovalDecision = {
    id: string;
} & ({ action: 'approve' | 'reject' } | { action: 'select'; optionId: string });

export type ToolPart = {
    type: string;
    state: string;
    toolCallId: string;
    output?: unknown;
};
