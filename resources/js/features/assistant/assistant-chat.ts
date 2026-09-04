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
    let message = fallbackMessage;

    if (response.status < 500) {
        try {
            const body = (await response.clone().json()) as {
                message?: unknown;
                error?: { message?: unknown };
            };
            const candidate =
                typeof body.error?.message === 'string'
                    ? body.error.message
                    : body.message;

            if (typeof candidate === 'string' && candidate.trim() !== '') {
                message = candidate;
            }
        } catch {
            // Non-JSON failures intentionally use the localized safe fallback.
        }
    }

    await response.body?.cancel();

    const error = new Error(message);
    error.name = `AssistantHttp${response.status}`;

    return error;
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

/** External effects may have succeeded even though confirmation was interrupted. */
export function hasUncertainAssistantOutcome(
    part: AssistantActionApprovalPart,
): boolean {
    return (
        part.state === 'output-error' &&
        part.errorText?.includes('action_outcome_uncertain') === true
    );
}

export function canRetryAssistantTurn(
    serverDecision: boolean | undefined,
    durable: boolean,
    failure: string | null | undefined,
    message?: UIMessage,
): boolean {
    if (
        failure?.toLowerCase().includes('action_outcome_uncertain') === true ||
        (message !== undefined &&
            assistantActionApprovalParts(message).some(
                hasUncertainAssistantOutcome,
            ))
    ) {
        return false;
    }

    return serverDecision ?? durable;
}
