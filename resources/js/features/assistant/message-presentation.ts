import { isToolUIPart } from 'ai';
import {
    assistantActionApprovalParts,
    type AssistantActionApprovalPart,
} from './assistant-chat';
import type { AssistantUIMessage, ToolPart } from './types';

export function actionApprovalParts(
    message: AssistantUIMessage,
): AssistantActionApprovalPart[] {
    return assistantActionApprovalParts(message);
}

export function isActionApprovalPart(
    message: AssistantUIMessage,
    part: AssistantUIMessage['parts'][number],
): boolean {
    if (!isToolUIPart(part)) {
        return false;
    }

    return actionApprovalParts(message).some(
        (candidate) => candidate.toolCallId === part.toolCallId,
    );
}

export function firstActionApprovalPart(
    message: AssistantUIMessage,
): AssistantActionApprovalPart {
    const part = actionApprovalParts(message).at(0);

    if (part === undefined) {
        throw new Error('Expected an action approval part.');
    }

    return part;
}

export function hasPrimaryMessageContent(message: AssistantUIMessage): boolean {
    if (message.role === 'user') {
        return true;
    }

    return message.parts.some(
        (part) =>
            part.type === 'text' ||
            (!isActionApprovalPart(message, part) &&
                (showsToolCard(part) ||
                    showsToolProgress(part) ||
                    showsReadResult(part))),
    );
}

export function toolPart(part: AssistantUIMessage['parts'][number]): ToolPart {
    return part as ToolPart;
}

export function toolPartKey(
    part: AssistantUIMessage['parts'][number],
    index: number,
): string {
    if (!isToolUIPart(part)) {
        return String(index);
    }

    return `${part.toolCallId}:${part.state}`;
}

export function showsToolProgress(
    part: AssistantUIMessage['parts'][number],
): boolean {
    return (
        isToolUIPart(part) &&
        (part.state === 'input-streaming' || part.state === 'input-available')
    );
}

export function showsToolCard(
    part: AssistantUIMessage['parts'][number],
): boolean {
    if (!isToolUIPart(part)) {
        return false;
    }

    if (
        part.state === 'approval-requested' ||
        part.state === 'approval-responded'
    ) {
        return true;
    }

    return (
        !part.type.startsWith('tool-read_') &&
        ['output-available', 'output-denied', 'output-error'].includes(
            part.state,
        )
    );
}

export function showsReadResult(
    part: AssistantUIMessage['parts'][number],
): boolean {
    return (
        isToolUIPart(part) &&
        part.type.startsWith('tool-read_') &&
        part.state === 'output-available'
    );
}
