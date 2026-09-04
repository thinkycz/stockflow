<script setup lang="ts">
import {
    Check,
    CheckCircle2,
    CircleX,
    ListChecks,
    LoaderCircle,
    X,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import { assistantApprovalPreview } from '@/features/assistant/assistant-approval';
import {
    hasUncertainAssistantOutcome,
    type AssistantActionApprovalPart,
} from '@/features/assistant/assistant-chat';

const props = defineProps<{ part: AssistantActionApprovalPart }>();
const emit = defineEmits<{
    decide: [
        payload:
            | { id: string; action: 'approve' | 'reject' }
            | { id: string; action: 'select'; optionId: string },
    ];
}>();
const { t, te } = useI18n();
const preview = computed(() => assistantApprovalPreview(props.part, te));

const approvalId = computed(
    () => props.part.approval?.id ?? props.part.toolCallId,
);
const isPending = computed(() => props.part.state === 'approval-requested');
const isUncertain = computed(() => hasUncertainAssistantOutcome(props.part));
const isChoice = computed(() => preview.value?.kind === 'choice');

function decide(action: 'approve' | 'reject'): void {
    emit('decide', { id: approvalId.value, action });
}

function select(optionId: string): void {
    emit('decide', {
        id: approvalId.value,
        action: 'select',
        optionId,
    });
}
</script>

<template>
    <section
        class="my-3 overflow-hidden rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-sm"
        :data-tool-call-id="part.toolCallId"
    >
        <template v-if="isPending && preview?.kind === 'choice'">
            <div class="space-y-3 p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary"
                    >
                        <ListChecks :size="17" />
                    </span>
                    <div class="min-w-0">
                        <p
                            class="text-xs font-semibold text-on-surface-variant"
                        >
                            {{ t('assistant.choice.title') }}
                        </p>
                        <p class="mt-1 text-sm font-semibold text-on-surface">
                            {{ preview.question }}
                        </p>
                    </div>
                </div>

                <div
                    class="grid gap-2"
                    role="group"
                    :aria-label="preview.question"
                >
                    <Button
                        v-for="(option, index) in preview.options"
                        :key="option.id"
                        type="button"
                        variant="secondary"
                        class="h-auto justify-start whitespace-normal px-3 py-3 text-left"
                        @click="select(option.id)"
                    >
                        <span
                            class="grid size-7 shrink-0 place-items-center rounded-lg bg-primary/10 text-xs font-bold text-primary"
                            >{{ String.fromCharCode(65 + index) }}</span
                        >
                        <span class="min-w-0">
                            <span class="block font-semibold text-on-surface">{{
                                option.label
                            }}</span>
                            <span
                                v-if="option.description"
                                class="mt-0.5 block text-xs font-normal text-on-surface-variant"
                                >{{ option.description }}</span
                            >
                        </span>
                    </Button>
                </div>
            </div>
        </template>

        <template
            v-else-if="isPending && preview?.kind === 'action_confirmation'"
        >
            <div class="space-y-4 p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary"
                    >
                        <ListChecks :size="17" />
                    </span>
                    <div class="min-w-0">
                        <p
                            class="text-xs font-semibold text-on-surface-variant"
                        >
                            {{ t('assistant.approval.title') }}
                        </p>
                        <p class="mt-1 text-sm font-semibold text-on-surface">
                            {{
                                t(
                                    preview.summary_key,
                                    preview.summary_params ?? {},
                                )
                            }}
                        </p>
                    </div>
                </div>

                <ul
                    v-if="preview.business_rows?.length"
                    class="divide-y divide-outline-glass rounded-xl bg-surface-container-low px-3"
                >
                    <li
                        v-for="(row, index) in preview.business_rows"
                        :key="`${row.label}-${index}`"
                        class="flex items-start justify-between gap-4 py-2.5 text-xs"
                    >
                        <span class="min-w-0 break-words text-on-surface">{{
                            row.label
                        }}</span>
                        <span
                            v-if="row.value"
                            class="shrink-0 font-semibold text-on-surface-variant"
                            >{{ row.value }}</span
                        >
                    </li>
                </ul>

                <div
                    class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <Button
                        type="button"
                        variant="secondary"
                        @click="decide('reject')"
                    >
                        <X :size="14" />
                        {{ t('assistant.approval.cancel') }}
                    </Button>
                    <Button type="button" @click="decide('approve')">
                        <Check :size="14" />
                        {{ t('assistant.approval.perform') }}
                    </Button>
                </div>
            </div>
        </template>

        <div v-else-if="isPending" class="space-y-3 p-4 sm:p-5" role="status">
            <p class="text-sm font-semibold text-on-surface">
                {{ t('assistant.approval.unavailable') }}
            </p>
            <div class="flex justify-end">
                <Button
                    type="button"
                    variant="secondary"
                    @click="decide('reject')"
                >
                    <X :size="14" />
                    {{ t('assistant.approval.cancel') }}
                </Button>
            </div>
        </div>

        <div
            v-else
            class="flex items-center gap-2.5 px-4 py-3 text-sm"
            role="status"
        >
            <CheckCircle2
                v-if="part.state === 'output-available'"
                :size="17"
                class="text-emerald-600"
            />
            <CircleX
                v-else-if="
                    part.state === 'output-denied' ||
                    part.state === 'output-error'
                "
                :size="17"
                :class="
                    part.state === 'output-denied'
                        ? 'text-on-surface-variant'
                        : isUncertain
                          ? 'text-amber-600'
                          : 'text-red-600'
                "
            />
            <LoaderCircle v-else :size="17" class="animate-spin text-primary" />
            <span class="font-semibold text-on-surface">
                {{
                    isUncertain
                        ? t('assistant.approval.uncertain')
                        : part.state === 'output-available'
                          ? t('assistant.approval.done')
                          : part.state === 'output-denied'
                            ? t('assistant.approval.cancelled')
                            : part.state === 'output-error'
                              ? t('assistant.approval.failed')
                              : isChoice
                                ? t('assistant.choice.selected')
                                : t('assistant.progress.running')
                }}
            </span>
        </div>
    </section>
</template>
