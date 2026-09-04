import {
    actionApprovalParts,
    isActionApprovalPart,
    firstActionApprovalPart,
    hasPrimaryMessageContent,
    toolPart,
    toolPartKey,
    showsToolProgress,
    showsToolCard,
    showsReadResult,
} from './message-presentation';
import { router } from '@inertiajs/vue3';
import { useChat } from '@ai-sdk/vue';
import {
    DefaultChatTransport,
    isToolUIPart,
    lastAssistantMessageIsCompleteWithApprovalResponses,
} from 'ai';
import { computed, nextTick, onMounted, ref, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from '@/composables/useRoute';
import { useDialog } from '@/composables/useDialog';
import {
    canRetryAssistantTurn,
    hasUncertainAssistantOutcome,
    assistantDecisionBody,
    assistantMessageText,
    assistantResponseError,
    hasPendingAssistantApprovals,
    type AssistantActionApprovalPart,
} from '@/features/assistant/assistant-chat';

import type {
    ConversationSummary,
    ConversationPayload,
    AssistantUIMessage,
    ApprovalDecision,
} from './types';
export type ConversationProps = {
    conversation: ConversationPayload | null;
    conversations: ConversationSummary[];
};

export function useConversation(props: ConversationProps) {
    const { locale, t } = useI18n();

    const route = useRoute();

    const dialog = useDialog();

    const draft = ref<string | null>('');

    const conversationId = ref<string | null>(props.conversation?.id ?? null);

    const conversationItems = ref<ConversationSummary[]>([
        ...props.conversations,
    ]);

    const initialConversationId = props.conversation?.id ?? null;

    const pendingConversationTitle = ref<string | null>(null);

    const choiceSelections = new Map<string, string>();

    const liveMessageTimestamps = new Map<string, string>();

    const messageViewport = useTemplateRef<HTMLElement>('messageViewport');

    const conversationNavOpen = ref(false);

    const followsLatestMessage = ref(true);

    const activeTurnId = ref<string | null>(
        props.conversation?.active_turn?.id ?? null,
    );

    const hasServerDurableTurn = ref(false);

    const hydratedFailure = ref<string | null>(
        props.conversation?.active_turn?.failure?.message ?? null,
    );

    const hydratedFailureCode = ref<string | null>(
        props.conversation?.active_turn?.failure?.code ?? null,
    );

    const reconnecting = ref(false);

    const reconnectAttempts = ref(0);

    const lastConsumedTurnEventId = ref(0);

    function initialMessages(): AssistantUIMessage[] {
        const persisted = props.conversation?.messages ?? [];
        const turn = props.conversation?.active_turn;
        const hydrated = [...persisted];

        if (turn?.kind === 'message' && turn.message !== null) {
            hydrated.push({
                id: `turn-${turn.id}`,
                role: 'user',
                metadata: { created_at: turn.queued_at },
                parts: [{ type: 'text', text: turn.message }],
            });
        }

        if (
            turn?.partial_response !== null &&
            turn?.partial_response !== undefined
        ) {
            hydrated.push({
                id: turn.partial_response.id,
                role: 'assistant',
                metadata: { created_at: turn.partial_response.created_at },
                parts: [{ type: 'text', text: turn.partial_response.text }],
            });
        }

        return hydrated;
    }

    function csrfHeader(): Record<string, string> {
        const cookie = document.cookie
            .split('; ')
            .find((entry) => entry.startsWith('XSRF-TOKEN='));

        return cookie === undefined
            ? {}
            : {
                  'X-XSRF-TOKEN': decodeURIComponent(
                      cookie.split('=')[1] ?? '',
                  ),
              };
    }

    function decodedConversationTitle(value: string | null): string | null {
        if (value === null) {
            return null;
        }

        try {
            return decodeURIComponent(value);
        } catch {
            return value;
        }
    }

    function syncConversationSummary(
        id: string,
        encodedTitle: string | null,
    ): void {
        const existing = conversationItems.value.find((item) => item.id === id);
        const title =
            decodedConversationTitle(encodedTitle) ??
            existing?.title ??
            pendingConversationTitle.value;

        if (title === null) {
            return;
        }

        conversationItems.value = [
            {
                id,
                title,
                updated_at: new Date().toISOString(),
            },
            ...conversationItems.value.filter((item) => item.id !== id),
        ];
    }

    function trackAssistantEventIds(response: Response): Response {
        if (
            response.body === null ||
            response.headers.get('x-assistant-turn-id') === null
        ) {
            return response;
        }

        const decoder = new TextDecoder();
        let pending = '';
        const body = response.body.pipeThrough(
            new TransformStream<Uint8Array, Uint8Array>({
                transform(chunk, controller) {
                    pending += decoder.decode(chunk, { stream: true });
                    const lines = pending.split('\n');
                    pending = lines.pop() ?? '';

                    for (const line of lines) {
                        if (!line.startsWith('id:')) {
                            continue;
                        }

                        const sequence = Number.parseInt(
                            line.slice(3).trim(),
                            10,
                        );
                        if (Number.isSafeInteger(sequence) && sequence > 0) {
                            lastConsumedTurnEventId.value = Math.max(
                                lastConsumedTurnEventId.value,
                                sequence,
                            );
                        }
                    }

                    controller.enqueue(chunk);
                },
            }),
        );

        return new Response(body, {
            headers: response.headers,
            status: response.status,
            statusText: response.statusText,
        });
    }

    const transport = new DefaultChatTransport<AssistantUIMessage>({
        api: route('assistant.chat'),
        credentials: 'same-origin',
        headers: csrfHeader,
        fetch: async (input, init) => {
            const response = await globalThis.fetch(input, init);
            const returnedConversationId =
                response.headers.get('x-conversation-id');
            const returnedTurnId = response.headers.get('x-assistant-turn-id');

            if (returnedTurnId !== null) {
                activeTurnId.value = returnedTurnId;
                hasServerDurableTurn.value = true;
            }

            if (returnedConversationId !== null) {
                conversationId.value = returnedConversationId;
                syncConversationSummary(
                    returnedConversationId,
                    response.headers.get('x-conversation-title'),
                );
            }

            if (!response.ok) {
                throw await assistantResponseError(
                    response,
                    t('assistant.unavailable'),
                );
            }

            return trackAssistantEventIds(response);
        },
        prepareSendMessagesRequest: ({ messages, trigger }) => {
            const turnId = crypto.randomUUID();
            activeTurnId.value = turnId;
            lastConsumedTurnEventId.value = 0;
            const decisions = assistantDecisionBody(messages, choiceSelections);

            if (Object.keys(decisions).length > 0) {
                return {
                    body: {
                        conversation_id: conversationId.value,
                        decisions,
                        turn_id: turnId,
                    },
                };
            }

            const last =
                trigger === 'regenerate-message'
                    ? [...messages]
                          .reverse()
                          .find((message) => message.role === 'user')
                    : messages.at(-1);

            return {
                body: {
                    conversation_id: conversationId.value,
                    message:
                        last === undefined ? '' : assistantMessageText(last),
                    turn_id: turnId,
                },
            };
        },
        prepareReconnectToStreamRequest: () => {
            const headers: Record<string, string> = csrfHeader();

            if (lastConsumedTurnEventId.value > 0) {
                headers['Last-Event-ID'] = String(
                    lastConsumedTurnEventId.value,
                );
            }

            return {
                api:
                    activeTurnId.value === null
                        ? route('assistant.chat')
                        : route('assistant.turns.stream', activeTurnId.value),
                headers,
            };
        },
    });

    const {
        messages,
        status,
        error,
        sendMessage,
        addToolApprovalResponse,
        stop: stopLocal,
        resumeStream,
        clearError,
    } = useChat<AssistantUIMessage>({
        id: props.conversation?.id,
        messages: initialMessages(),
        transport,
        sendAutomaticallyWhen:
            lastAssistantMessageIsCompleteWithApprovalResponses,
        onFinish: async () => {
            const shouldReconcile = hasServerDurableTurn.value;
            activeTurnId.value = null;
            hasServerDurableTurn.value = false;
            reconnecting.value = false;
            reconnectAttempts.value = 0;
            lastConsumedTurnEventId.value = 0;
            await scrollToLatest();

            if (
                initialConversationId === null &&
                conversationId.value !== null &&
                window.location.pathname === '/assistant'
            ) {
                window.history.replaceState(
                    {},
                    '',
                    route('assistant.conversations.show', conversationId.value),
                );
            }

            if (shouldReconcile) {
                router.reload({
                    only: ['conversation', 'conversations'],
                    onSuccess: () => {
                        messages.value = initialMessages();
                    },
                });
            }
        },
        onError: () => {
            if (activeTurnId.value === null || reconnectAttempts.value >= 3) {
                reconnecting.value = false;

                return;
            }

            reconnecting.value = true;
            const delay = 500 * 2 ** reconnectAttempts.value;
            reconnectAttempts.value += 1;
            window.setTimeout(() => {
                void resumeStream().finally(() => {
                    reconnecting.value = false;
                });
            }, delay);
        },
    });

    async function stop(): Promise<void> {
        const turnId = activeTurnId.value;

        if (turnId !== null) {
            await globalThis.fetch(route('assistant.turns.cancel', turnId), {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeader(),
            });
        }

        await stopLocal();
        reconnecting.value = false;
    }

    const isBusy = computed(
        () =>
            status.value === 'submitted' ||
            status.value === 'streaming' ||
            reconnecting.value,
    );

    const hasPendingApprovals = computed(() =>
        hasPendingAssistantApprovals(messages.value),
    );

    const activityLabel = computed(() => {
        if (reconnecting.value) {
            return t('assistant.progress.reconnecting');
        }

        const latest = messages.value.at(-1);
        const activeTool = [...(latest?.parts.filter(isToolUIPart) ?? [])]
            .reverse()
            .find((part) =>
                ['input-streaming', 'input-available'].includes(part.state),
            );

        if (activeTool?.type.startsWith('tool-read_')) {
            return t('assistant.progress.reading');
        }

        if (activeTool !== undefined) {
            return t('assistant.progress.preparing');
        }

        return t('assistant.progress.thinking');
    });

    async function submit(): Promise<void> {
        const message = draft.value?.trim() ?? '';

        if (message === '' || isBusy.value || hasPendingApprovals.value) {
            return;
        }

        draft.value = '';
        pendingConversationTitle.value = message;
        clearError();
        hydratedFailure.value = null;
        hydratedFailureCode.value = null;
        followsLatestMessage.value = true;
        await sendMessage({
            text: message,
            metadata: { created_at: new Date().toISOString() },
        });
    }

    const displayedError = computed(() => {
        if (uncertainOutcome.value) return t('assistant.approval.uncertain');
        if (hydratedFailureCode.value === 'POST_ACTION_GENERATION_FAILED') {
            return t('assistant.failures.post_action');
        }
        if (hydratedFailureCode.value === 'TURN_FAILED') {
            return t('assistant.failures.interrupted');
        }

        switch (error.value?.name) {
            case 'AssistantHttp409':
                return t('assistant.failures.busy');
            case 'AssistantHttp422':
                return t('assistant.failures.invalid');
            case 'AssistantHttp429':
                return t('assistant.failures.rate_limited');
            default:
                return error.value?.message ?? hydratedFailure.value;
        }
    });

    const latestAssistantMessage = computed(() =>
        [...messages.value]
            .reverse()
            .find((message) => message.role === 'assistant'),
    );
    const uncertainOutcome = computed(
        () =>
            [
                hydratedFailureCode.value,
                error.value?.message,
                props.conversation?.active_turn?.id === activeTurnId.value
                    ? props.conversation?.active_turn?.failure?.code
                    : null,
            ].some(
                (value) =>
                    value
                        ?.toLowerCase()
                        .includes('action_outcome_uncertain') === true,
            ) ||
            (latestAssistantMessage.value !== undefined &&
                actionApprovalParts(latestAssistantMessage.value).some(
                    hasUncertainAssistantOutcome,
                )),
    );
    const canRetryTurn = computed(() => {
        const serverTurn = props.conversation?.active_turn;
        return (
            !uncertainOutcome.value &&
            canRetryAssistantTurn(
                serverTurn?.id === activeTurnId.value
                    ? serverTurn?.can_retry
                    : undefined,
                hasServerDurableTurn.value,
                hydratedFailureCode.value ?? error.value?.message,
                latestAssistantMessage.value,
            )
        );
    });

    async function retryFailedTurn(): Promise<void> {
        const failedTurnId = activeTurnId.value;
        if (failedTurnId === null || !canRetryTurn.value) {
            return;
        }

        try {
            const response = await globalThis.fetch(
                route('assistant.turns.retry', failedTurnId),
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        ...csrfHeader(),
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ turn_id: crypto.randomUUID() }),
                },
            );

            if (!response.ok) {
                const failure = await assistantResponseError(
                    response,
                    t('assistant.unavailable'),
                );
                hydratedFailure.value = failure.message;
                hydratedFailureCode.value = null;

                return;
            }

            const payload = (await response.json()) as { turn_id: string };
            activeTurnId.value = payload.turn_id;
            hasServerDurableTurn.value = true;
            lastConsumedTurnEventId.value = 0;
            hydratedFailure.value = null;
            hydratedFailureCode.value = null;
            clearError();
            reconnecting.value = true;
            await resumeStream().finally(() => {
                reconnecting.value = false;
            });
        } catch (failure) {
            hydratedFailure.value =
                failure instanceof Error
                    ? failure.message
                    : t('assistant.unavailable');
            hydratedFailureCode.value = null;
            reconnecting.value = false;
        }
    }

    async function decide(payload: ApprovalDecision): Promise<void> {
        if (payload.action === 'select') {
            choiceSelections.set(payload.id, payload.optionId);
        }

        await addToolApprovalResponse({
            id: payload.id,
            approved: payload.action !== 'reject',
        });
    }

    async function decideActionGroup(
        parts: AssistantActionApprovalPart[],
        action: 'approve' | 'reject',
    ): Promise<void> {
        for (const part of parts) {
            if (part.state !== 'approval-requested') {
                continue;
            }

            await addToolApprovalResponse({
                id: part.approval?.id ?? part.toolCallId,
                approved: action === 'approve',
            });
        }
    }

    function showsActionApprovalMessage(message: AssistantUIMessage): boolean {
        const parts = actionApprovalParts(message);

        if (parts.length === 0) {
            return false;
        }

        return (
            message !== messages.value.at(-1) ||
            !isBusy.value ||
            parts.every((part) => part.state !== 'approval-requested')
        );
    }

    const messageDateTimeFormatter = computed(
        () =>
            new Intl.DateTimeFormat(locale.value, {
                dateStyle: 'medium',
                timeStyle: 'short',
            }),
    );

    function messageTimestamp(message: AssistantUIMessage): string {
        const persisted = message.metadata?.created_at;

        if (persisted !== undefined) {
            return persisted;
        }

        const current = liveMessageTimestamps.get(message.id);

        if (current !== undefined) {
            return current;
        }

        const timestamp = new Date().toISOString();
        liveMessageTimestamps.set(message.id, timestamp);

        return timestamp;
    }

    function formattedMessageTimestamp(message: AssistantUIMessage): string {
        return messageDateTimeFormatter.value.format(
            new Date(messageTimestamp(message)),
        );
    }

    function updateScrollAffinity(): void {
        const viewport = messageViewport.value;

        if (viewport === null) {
            return;
        }

        followsLatestMessage.value =
            viewport.scrollHeight - viewport.scrollTop - viewport.clientHeight <
            96;
    }

    async function scrollToLatest(): Promise<void> {
        if (!followsLatestMessage.value) {
            return;
        }

        await nextTick();
        messageViewport.value?.scrollTo({
            top: messageViewport.value.scrollHeight,
            behavior: status.value === 'streaming' ? 'auto' : 'smooth',
        });
    }

    onMounted(async () => {
        await nextTick();

        const viewport = messageViewport.value;

        if (viewport !== null) {
            viewport.scrollTop = viewport.scrollHeight;
        }

        if (
            activeTurnId.value !== null &&
            ['queued', 'running', 'cancel_requested'].includes(
                props.conversation?.active_turn?.status ?? '',
            )
        ) {
            await resumeStream();
        }
    });

    watch(messages, scrollToLatest, { deep: true, flush: 'post' });

    watch(status, scrollToLatest, { flush: 'post' });

    watch(
        () => props.conversations,
        (value) => {
            conversationItems.value = [...value];
        },
    );

    async function destroyConversation(
        conversation: ConversationSummary,
    ): Promise<void> {
        if (
            !(await dialog.confirm({
                title: `${t('assistant.delete')}: ${conversation.title}`,
                message: t('assistant.delete_confirmation'),
                confirmLabel: t('common.delete'),
                variant: 'danger',
            }))
        ) {
            return;
        }

        router.delete(
            route('assistant.conversations.destroy', conversation.id),
        );
    }
    return {
        t,
        route,
        draft,
        conversationId,
        conversationItems,
        messageViewport,
        conversationNavOpen,
        messages,
        status,
        error,
        stop,
        isBusy,
        hasPendingApprovals,
        activityLabel,
        submit,
        displayedError,
        canRetryTurn,
        retryFailedTurn,
        decide,
        decideActionGroup,
        actionApprovalParts,
        isActionApprovalPart,
        firstActionApprovalPart,
        hasPrimaryMessageContent,
        showsActionApprovalMessage,
        toolPart,
        toolPartKey,
        showsToolProgress,
        showsToolCard,
        showsReadResult,
        messageTimestamp,
        formattedMessageTimestamp,
        updateScrollAffinity,
        destroyConversation,
    };
}
