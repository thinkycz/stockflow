<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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
import AssistantApprovalCard from '@/features/assistant/components/AssistantApprovalCard.vue';
import AssistantApprovalGroup from '@/features/assistant/components/AssistantApprovalGroup.vue';
import AssistantMarkdown from '@/features/assistant/components/AssistantMarkdown.vue';
import AssistantReadResultStatus from '@/features/assistant/components/AssistantReadResultStatus.vue';
import AssistantToolProgress from '@/features/assistant/components/AssistantToolProgress.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Textarea from '@/components/ui/Textarea.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    useConversation,
    type ConversationProps,
} from '@/features/assistant/useConversation';

const props = defineProps<ConversationProps>();
const {
    t,
    route,
    draft,
    conversationId,
    conversationItems,
    conversationNavOpen,
    messages,
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
} = useConversation(props);
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
                    <Alert v-if="displayedError" variant="error" class="mb-3">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <span>{{ displayedError }}</span>
                            <Button
                                v-if="canRetryTurn"
                                variant="ghost"
                                size="compact"
                                @click="retryFailedTurn()"
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

                    <form
                        class="flex items-stretch gap-2"
                        @submit.prevent="submit"
                    >
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
                            class="h-auto w-[3.125rem] shrink-0"
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
                </div>
            </section>
        </div>
    </AppLayout>
</template>
