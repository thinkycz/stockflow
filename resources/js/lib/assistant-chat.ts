import { isToolUIPart, type UIMessage } from 'ai';

export type AssistantActionApprovalPart = {
    type: string;
    state: string;
    toolCallId: string;
    input?: unknown;
    errorText?: string;
    approval?: {
        id: string;
        approved?: boolean;
        requestReason?: string;
    };
};

export function assistantActionApprovalParts(
    message: UIMessage,
): AssistantActionApprovalPart[] {
    return message.parts.filter(
        (part) =>
            isToolUIPart(part) &&
            part.type.startsWith('tool-write_') &&
            [
                'approval-requested',
                'approval-responded',
                'output-available',
                'output-denied',
                'output-error',
            ].includes(part.state),
    ) as AssistantActionApprovalPart[];
}

export async function assistantResponseError(
    response: Response,
    fallbackMessage: string,
): Promise<Error> {
    await response.body?.cancel();

    return new Error(fallbackMessage);
}

export function assistantMessageText(message: UIMessage): string {
    return message.parts
        .filter(
            (
                part,
            ): part is Extract<
                (typeof message.parts)[number],
                { type: 'text' }
            > => part.type === 'text',
        )
        .map((part) => part.text)
        .join('');
}

export function hasPendingAssistantApprovals(messages: UIMessage[]): boolean {
    return messages.some((message) =>
        message.parts.some(
            (part) => isToolUIPart(part) && part.state === 'approval-requested',
        ),
    );
}

export function assistantDecisionBody(
    messages: UIMessage[],
    choiceSelections: ReadonlyMap<string, string>,
): Record<string, Record<string, unknown>> {
    const last = messages.at(-1);
    const decisions: Record<string, Record<string, unknown>> = {};

    if (last?.role !== 'assistant') {
        return decisions;
    }

    for (const part of last.parts) {
        if (!isToolUIPart(part) || part.state !== 'approval-responded') {
            continue;
        }

        const selectedOption = choiceSelections.get(part.toolCallId);

        if (part.approval.approved) {
            decisions[part.toolCallId] =
                selectedOption === undefined
                    ? { action: 'approve' }
                    : { action: 'select', option_id: selectedOption };
        } else {
            decisions[part.toolCallId] = { action: 'reject' };
        }
    }

    return decisions;
}
