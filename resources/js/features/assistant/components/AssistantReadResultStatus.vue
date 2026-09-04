<script setup lang="ts">
import { CheckCircle2 } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ output: unknown }>();
const { t } = useI18n();

type ReadEnvelope = {
    operation?: string;
    returned_count?: number;
    has_more?: boolean;
};

const envelope = computed<ReadEnvelope | null>(() => {
    if (typeof props.output === 'object' && props.output !== null) {
        return props.output as ReadEnvelope;
    }

    if (typeof props.output !== 'string') {
        return null;
    }

    try {
        return JSON.parse(props.output) as ReadEnvelope;
    } catch {
        return null;
    }
});

const label = computed(() => {
    if (envelope.value?.operation === 'summary') {
        return t('assistant.read_result.summary');
    }

    const count = envelope.value?.returned_count ?? 0;

    return envelope.value?.has_more === true
        ? t('assistant.read_result.partial', { count })
        : t('assistant.read_result.complete', { count });
});
</script>

<template>
    <div
        data-testid="assistant-read-result"
        class="flex items-center gap-2 text-xs text-on-surface-variant"
    >
        <CheckCircle2 :size="14" class="shrink-0 text-primary" />
        <span>{{ label }}</span>
    </div>
</template>
