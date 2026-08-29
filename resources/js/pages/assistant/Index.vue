<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    BotMessageSquare,
    History,
    Plus,
    RefreshCw,
    Send,
    Square,
    Trash2,
    X,
} from '@lucide/vue';
import { useChat } from '@ai-sdk/vue';
import {
    DefaultChatTransport,
    isToolUIPart,
    lastAssistantMessageIsCompleteWithApprovalResponses,
    type UIMessage,
} from 'ai';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AssistantApprovalCard from '@/components/assistant/AssistantApprovalCard.vue';
import AssistantApprovalGroup from '@/components/assistant/AssistantApprovalGroup.vue';
import AssistantMarkdown from '@/components/assistant/AssistantMarkdown.vue';
import AssistantReadResultStatus from '@/components/assistant/AssistantReadResultStatus.vue';
import AssistantToolProgress from '@/components/assistant/AssistantToolProgress.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Textarea from '@/components/ui/Textarea.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useRoute } from '@/composables/useRoute';
import { useDialog } from '@/composables/useDialog';
import {
    assistantActionApprovalParts,
    assistantDecisionBody,
    assistantMessageText,
    assistantResponseError,
    hasPendingAssistantApprovals,
    type AssistantActionApprovalPart,
} from '@/lib/assistant-chat';

type ConversationSummary = {
    id: string;
    title: string;
    updated_at: string | null;
};

type ConversationPayload = {
    id: string;
    title: string;
    messages: AssistantUIMessage[];
    active_turn: AssistantTurnPayload | null;
};

type AssistantTurnPayload = {
    id: string;
    status: string;
    kind: string;
    message: string | null;
    queued_at: string;
};

type AssistantMessageMetadata = {
    created_at?: string;
};

type AssistantUIMessage = UIMessage<AssistantMessageMetadata>;

type ApprovalDecision = {
    id: string;
} & ({ action: 'approve' | 'reject' } | { action: 'select'; optionId: string });

type ToolPart = {
    type: string;
    state: string;
    toolCallId: string;
    output?: unknown;
};

const props = defineProps<{
    conversation: ConversationPayload | null;
    conversations: ConversationSummary[];
}>();

const { locale, t } = useI18n();
const route = useRoute();
const dialog = useDialog();
const draft = ref<string | null>('');
const conversationId = ref<string | null>(props.conversation?.id ?? null);
const conversationItems = ref<ConversationSummary[]>([...props.conversations]);
const initialConversationId = props.conversation?.id ?? null;
const pendingConversationTitle = ref<string | null>(null);
const choiceSelections = new Map<string, string>();
const liveMessageTimestamps = new Map<string, string>();
const messageViewport = ref<HTMLElement | null>(null);
const conversationNavOpen = ref(false);
const followsLatestMessage = ref(true);
const activeTurnId = ref<string | null>(
    props.conversation?.active_turn?.id ?? null,
);
const hasServerDurableTurn = ref(false);
const reconnecting = ref(false);
const reconnectAttempts = ref(0);

function initialMessages(): AssistantUIMessage[] {
    const persisted = props.conversation?.messages ?? [];
    const turn = props.conversation?.active_turn;

    if (turn?.kind !== 'message' || turn.message === null) {
        return persisted;
    }

    return [
        ...persisted,
        {
            id: `turn-${turn.id}`,
            role: 'user',
            metadata: { created_at: turn.queued_at },
            parts: [{ type: 'text', text: turn.message }],
        },
    ];
}

function csrfHeader(): Record<string, string> {
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='));

    return cookie === undefined
        ? {}
        : { 'X-XSRF-TOKEN': decodeURIComponent(cookie.split('=')[1] ?? '') };
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

        return response;
    },
    prepareSendMessagesRequest: ({ messages, trigger }) => {
        const turnId = crypto.randomUUID();
        activeTurnId.value = turnId;
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
                message: last === undefined ? '' : assistantMessageText(last),
                turn_id: turnId,
            },
        };
    },
    prepareReconnectToStreamRequest: () => ({
        api:
            activeTurnId.value === null
                ? route('assistant.chat')
                : route('assistant.turns.stream', activeTurnId.value),
    }),
});

const {
    messages,
    status,
    error,
    sendMessage,
    addToolApprovalResponse,
    regenerate,
    stop: stopLocal,
    resumeStream,
    clearError,
} = useChat<AssistantUIMessage>({
    id: props.conversation?.id,
    messages: initialMessages(),
    transport,
    sendAutomaticallyWhen: lastAssistantMessageIsCompleteWithApprovalResponses,
    onFinish: async () => {
        const shouldReconcile = hasServerDurableTurn.value;
        activeTurnId.value = null;
        hasServerDurableTurn.value = false;
        reconnecting.value = false;
        reconnectAttempts.value = 0;
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
    followsLatestMessage.value = true;
    await sendMessage({
        text: message,
        metadata: { created_at: new Date().toISOString() },
    });
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

function actionApprovalParts(
    message: AssistantUIMessage,
): AssistantActionApprovalPart[] {
    return assistantActionApprovalParts(message);
}

function isActionApprovalPart(
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

function firstActionApprovalPart(
    message: AssistantUIMessage,
): AssistantActionApprovalPart {
    const part = actionApprovalParts(message).at(0);

    if (part === undefined) {
        throw new Error('Expected an action approval part.');
    }

    return part;
}

function hasPrimaryMessageContent(message: AssistantUIMessage): boolean {
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

function toolPart(part: AssistantUIMessage['parts'][number]): ToolPart {
    return part as ToolPart;
}

function toolPartKey(
    part: AssistantUIMessage['parts'][number],
    index: number,
): string {
    if (!isToolUIPart(part)) {
        return String(index);
    }

    return `${part.toolCallId}:${part.state}`;
}

function showsToolProgress(part: AssistantUIMessage['parts'][number]): boolean {
    return (
        isToolUIPart(part) &&
        (part.state === 'input-streaming' || part.state === 'input-available')
    );
}

function showsToolCard(part: AssistantUIMessage['parts'][number]): boolean {
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

function showsReadResult(part: AssistantUIMessage['parts'][number]): boolean {
    return (
        isToolUIPart(part) &&
        part.type.startsWith('tool-read_') &&
        part.state === 'output-available'
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
        viewport.scrollHeight - viewport.scrollTop - viewport.clientHeight < 96;
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

    router.delete(route('assistant.conversations.destroy', conversation.id));
}
</script>

<template>
    <AppLayout :title="t('assistant.title')" full-bleed>
        <div
            data-testid="assistant-workspace"
            class="relative flex min-h-0 flex-1 overflow-hidden bg-surface-bg"
        >
            <Button
                v-if="conversationNavOpen"
                type="button"
                variant="ghost"
                class="fixed inset-x-0 bottom-0 top-16 z-30 rounded-none bg-black/30 p-0 hover:bg-black/30 md:hidden"
                :aria-label="t('nav.close')"
                @click="conversationNavOpen = false"
            />

            <aside
                data-testid="assistant-conversation-sidebar"
                :class="[
                    'fixed bottom-0 left-0 top-16 z-40 w-[min(20rem,calc(100vw-3rem))] shrink-0 flex-col border-r border-outline-glass bg-surface-container-lowest md:static md:z-auto md:flex md:w-72',
                    conversationNavOpen ? 'flex' : 'hidden',
                ]"
                :aria-label="t('assistant.history')"
            >
                <div
                    class="flex min-h-16 shrink-0 items-center justify-between gap-3 border-b border-outline-glass px-4"
                >
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span
                            class="grid size-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary"
                        >
                            <BotMessageSquare :size="17" />
                        </span>
                        <h2 class="truncate text-sm font-bold text-on-surface">
                            {{ t('assistant.history') }}
                        </h2>
                    </div>
                    <div class="flex items-center gap-1">
                        <Link
                            :href="route('assistant.index')"
                            class="rounded-lg p-2 text-primary transition hover:bg-surface-container"
                            :aria-label="t('assistant.new_chat')"
                            @click="conversationNavOpen = false"
                        >
                            <Plus :size="16" />
                        </Link>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="md:hidden"
                            :aria-label="t('nav.close')"
                            @click="conversationNavOpen = false"
                        >
                            <X :size="16" />
                        </Button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-3">
                    <p
                        v-if="conversationItems.length === 0"
                        class="px-2 py-3 text-xs text-on-surface-variant"
                    >
                        {{ t('assistant.no_conversations') }}
                    </p>
                    <ul v-else class="space-y-1">
                        <li
                            v-for="item in conversationItems"
                            :key="item.id"
                            class="group flex items-center gap-1"
                        >
                            <Link
                                :href="
                                    route(
                                        'assistant.conversations.show',
                                        item.id,
                                    )
                                "
                                :class="[
                                    'min-w-0 flex-1 truncate rounded-lg px-3 py-2.5 text-xs font-medium transition',
                                    item.id === conversationId
                                        ? 'bg-primary/10 text-primary'
                                        : 'text-on-surface-variant hover:bg-surface-container-low',
                                ]"
                                @click="conversationNavOpen = false"
                            >
                                {{ item.title }}
                            </Link>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="text-on-surface-variant opacity-100 hover:bg-red-50 hover:text-red-700 md:opacity-0 md:group-hover:opacity-100 md:focus:opacity-100"
                                :aria-label="t('assistant.delete')"
                                @click="destroyConversation(item)"
                            >
                                <Trash2 :size="13" />
                            </Button>
                        </li>
                    </ul>
                </div>
            </aside>

            <section
                data-testid="assistant-main-content"
                class="flex min-w-0 flex-1 flex-col bg-surface-bg"
            >
                <header
                    class="flex min-h-16 shrink-0 items-center justify-between gap-3 border-b border-outline-glass bg-surface-container-lowest px-4 sm:px-6"
                >
                    <div class="min-w-0">
                        <h1
                            class="truncate text-base font-bold text-on-surface"
                        >
                            {{ t('assistant.title') }}
                        </h1>
                        <p
                            class="hidden truncate text-xs text-on-surface-variant sm:block"
                        >
                            {{ t('assistant.subtitle') }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="md:hidden"
                            :aria-label="t('assistant.history')"
                            @click="conversationNavOpen = true"
                        >
                            <History :size="16" />
                        </Button>
                    </div>
                </header>

                <div
                    ref="messageViewport"
                    class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4 sm:p-6"
                    aria-live="polite"
                    @scroll.passive="updateScrollAffinity"
                >
                    <EmptyState
                        v-if="messages.length === 0"
                        :title="t('assistant.empty_title')"
                        :description="t('assistant.empty_description')"
                        icon="inbox"
                        class="my-auto"
                    >
                        <template #action>
                            <div class="flex flex-wrap justify-center gap-2">
                                <Button
                                    v-for="prompt in [
                                        t('assistant.prompts.low_stock'),
                                        t('assistant.prompts.today'),
                                        t('assistant.prompts.staffing'),
                                    ]"
                                    :key="prompt"
                                    variant="secondary"
                                    size="compact"
                                    class="rounded-full border border-outline-glass bg-white px-3 py-1.5 text-xs font-medium text-on-surface-variant hover:border-primary/30 hover:text-primary"
                                    @click="draft = prompt"
                                >
                                    {{ prompt }}
                                </Button>
                            </div>
                        </template>
                    </EmptyState>

                    <template v-for="message in messages" :key="message.id">
                        <article
                            v-if="hasPrimaryMessageContent(message)"
                            data-testid="assistant-message"
                            :class="[
                                'group/message max-w-[92%] rounded-2xl px-4 py-3 text-sm leading-6 sm:max-w-[82%]',
                                message.role === 'user'
                                    ? 'ml-auto bg-primary text-white'
                                    : 'mr-auto border border-outline-glass bg-surface-container-lowest text-on-surface',
                            ]"
                        >
                            <template
                                v-for="(part, index) in message.parts"
                                :key="toolPartKey(part, index)"
                            >
                                <p
                                    v-if="
                                        part.type === 'text' &&
                                        message.role === 'user'
                                    "
                                    class="whitespace-pre-wrap break-words"
                                >
                                    {{ part.text }}
                                </p>
                                <AssistantMarkdown
                                    v-else-if="part.type === 'text'"
                                    :source="part.text"
                                />
                                <AssistantApprovalCard
                                    v-else-if="
                                        showsToolCard(part) &&
                                        !isActionApprovalPart(message, part)
                                    "
                                    :part="toolPart(part)"
                                    @decide="decide"
                                />
                                <AssistantReadResultStatus
                                    v-else-if="showsReadResult(part)"
                                    :output="toolPart(part).output"
                                />
                                <AssistantToolProgress
                                    v-else-if="showsToolProgress(part)"
                                    :tool-type="toolPart(part).type"
                                />
                            </template>
                            <time
                                data-testid="assistant-message-timestamp"
                                :datetime="messageTimestamp(message)"
                                :title="formattedMessageTimestamp(message)"
                                :class="[
                                    'mt-1 block text-[10px] leading-4 opacity-60 transition-opacity sm:opacity-0 sm:group-hover/message:opacity-100 sm:group-focus-within/message:opacity-100',
                                    message.role === 'user'
                                        ? 'text-right text-white/75'
                                        : 'text-on-surface-variant',
                                ]"
                            >
                                {{ formattedMessageTimestamp(message) }}
                            </time>
                        </article>

                        <article
                            v-if="showsActionApprovalMessage(message)"
                            data-testid="assistant-message"
                            class="group/message mr-auto w-full max-w-[92%] text-sm leading-6 sm:max-w-[82%]"
                        >
                            <AssistantApprovalGroup
                                v-if="actionApprovalParts(message).length > 1"
                                :parts="actionApprovalParts(message)"
                                @decide="
                                    decideActionGroup(
                                        actionApprovalParts(message),
                                        $event,
                                    )
                                "
                            />
                            <AssistantApprovalCard
                                v-else
                                :part="firstActionApprovalPart(message)"
                                @decide="decide"
                            />
                            <time
                                data-testid="assistant-message-timestamp"
                                :datetime="messageTimestamp(message)"
                                :title="formattedMessageTimestamp(message)"
                                class="mt-1 block px-1 text-[10px] leading-4 text-on-surface-variant opacity-60 transition-opacity sm:opacity-0 sm:group-hover/message:opacity-100 sm:group-focus-within/message:opacity-100"
                            >
                                {{ formattedMessageTimestamp(message) }}
                            </time>
                        </article>
                    </template>
                </div>

                <div
                    class="shrink-0 border-t border-outline-glass bg-surface-container-lowest p-3 sm:p-4"
                >
                    <Alert v-if="error" variant="error" class="mb-3">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <span>{{ error.message }}</span>
                            <Button
                                variant="ghost"
                                size="compact"
                                @click="regenerate()"
                            >
                                <RefreshCw :size="13" />
                                {{ t('assistant.retry') }}
                            </Button>
                        </div>
                    </Alert>

                    <div
                        v-if="isBusy"
                        class="mb-3 flex items-center justify-between gap-3 rounded-xl bg-surface-container-low px-3 py-2 text-xs text-on-surface-variant"
                        role="status"
                        aria-live="polite"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <BotMessageSquare
                                :size="14"
                                class="shrink-0 animate-pulse text-primary"
                            />
                            <span class="truncate">{{ activityLabel }}</span>
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="compact"
                            class="shrink-0"
                            :aria-label="t('assistant.stop')"
                            @click="stop()"
                        >
                            <Square :size="13" />
                            {{ t('assistant.stop_short') }}
                        </Button>
                    </div>

                    <form class="flex items-end gap-2" @submit.prevent="submit">
                        <Textarea
                            v-model="draft"
                            :placeholder="t('assistant.placeholder')"
                            :maxlength="10000"
                            :rows="2"
                            class="min-h-[3rem] flex-1 resize-none"
                            @keydown.enter.exact.prevent="submit"
                        />
                        <Button
                            type="submit"
                            size="icon"
                            :disabled="
                                (draft?.trim() ?? '') === '' ||
                                hasPendingApprovals ||
                                isBusy
                            "
                            :aria-label="t('assistant.send')"
                        >
                            <Send :size="15" />
                        </Button>
                    </form>
                    <p class="mt-2 text-[11px] text-on-surface-variant">
                        {{ t('assistant.disclaimer') }}
                    </p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
