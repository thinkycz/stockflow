<script setup lang="ts">
import {
    Check,
    CheckCircle2,
    CircleX,
    ListChecks,
    LoaderCircle,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import { assistantApprovalPreview } from '@/lib/assistant-approval';
import type { AssistantActionApprovalPart } from '@/lib/assistant-chat';

const props = defineProps<{ parts: AssistantActionApprovalPart[] }>();
const emit = defineEmits<{
    decide: [action: 'approve' | 'reject'];
}>();
const { t, te } = useI18n();
const deciding = ref(false);

const previews = computed(() =>
    props.parts.map((part) => {
        const preview = assistantApprovalPreview(part, te);

        return preview?.kind === 'action_confirmation' ? preview : null;
    }),
);
const isPending = computed(() =>
    props.parts.some((part) => part.state === 'approval-requested'),
);
const hasFailed = computed(() =>
    props.parts.some((part) => part.state === 'output-error'),
);
const isCancelled = computed(() =>
    props.parts.every((part) => part.state === 'output-denied'),
);
const isComplete = computed(() =>
    props.parts.every((part) => part.state === 'output-available'),
);

function decide(action: 'approve' | 'reject'): void {
    deciding.value = true;
    emit('decide', action);
}
</script>

<template>
    <section
        data-testid="assistant-approval-group"
        class="my-3 overflow-hidden rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-sm"
    >
        <template v-if="isPending">
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
                            {{ t('assistant.approval.group_title') }}
                        </p>
                        <p class="mt-1 text-sm font-semibold text-on-surface">
                            {{
                                t('assistant.approval.group_count', {
                                    count: parts.length,
                                })
                            }}
                        </p>
                    </div>
                </div>

                <ol
                    class="max-h-80 divide-y divide-outline-glass overflow-y-auto rounded-xl bg-surface-container-low px-3"
                >
                    <li
                        v-for="(preview, index) in previews"
                        :key="parts[index]?.toolCallId ?? index"
                        class="py-3 text-sm"
                    >
                        <div class="flex items-start gap-2.5">
                            <span
                                class="grid size-6 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary"
                            >
                                {{ index + 1 }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p
                                    v-if="preview !== null"
                                    class="font-medium text-on-surface"
                                >
                                    {{
                                        t(
                                            preview.summary_key,
                                            preview.summary_params ?? {},
                                        )
                                    }}
                                </p>
                                <p v-else class="font-medium text-on-surface">
                                    {{ t('assistant.approval.unavailable') }}
                                </p>
                                <ul
                                    v-if="preview?.business_rows?.length"
                                    class="mt-2 space-y-1 text-xs text-on-surface-variant"
                                >
                                    <li
                                        v-for="(
                                            row, rowIndex
                                        ) in preview.business_rows"
                                        :key="`${row.label}-${rowIndex}`"
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <span>{{ row.label }}</span>
                                        <span
                                            v-if="row.value"
                                            class="shrink-0 font-semibold"
                                            >{{ row.value }}</span
                                        >
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </li>
                </ol>

                <div
                    class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="deciding"
                        @click="decide('reject')"
                    >
                        <X :size="14" />
                        {{ t('assistant.approval.cancel') }}
                    </Button>
                    <Button
                        type="button"
                        :disabled="deciding"
                        @click="decide('approve')"
                    >
                        <Check :size="14" />
                        {{ t('assistant.approval.perform') }}
                    </Button>
                </div>
            </div>
        </template>

        <div
            v-else
            class="flex items-center gap-2.5 px-4 py-3 text-sm"
            role="status"
        >
            <CircleX
                v-if="hasFailed || isCancelled"
                :size="17"
                :class="hasFailed ? 'text-red-600' : 'text-on-surface-variant'"
            />
            <CheckCircle2
                v-else-if="isComplete"
                :size="17"
                class="text-emerald-600"
            />
            <LoaderCircle v-else :size="17" class="animate-spin text-primary" />
            <span class="font-semibold text-on-surface">
                {{
                    hasFailed
                        ? t('assistant.approval.failed')
                        : isCancelled
                          ? t('assistant.approval.cancelled')
                          : isComplete
                            ? t('assistant.approval.done')
                            : t('assistant.progress.running')
                }}
            </span>
        </div>
    </section>
</template>
