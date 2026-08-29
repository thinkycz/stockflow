<script setup lang="ts">
import { BotMessageSquare } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ toolType: string }>();
const { t } = useI18n();
const label = computed(() =>
    props.toolType.startsWith('tool-read_')
        ? t('assistant.progress.reading')
        : t('assistant.progress.preparing'),
);
</script>

<template>
    <div
        class="my-2 flex items-center gap-2 text-xs text-on-surface-variant"
        role="status"
    >
        <BotMessageSquare :size="14" class="animate-pulse text-primary" />
        {{ label }}
    </div>
</template>
