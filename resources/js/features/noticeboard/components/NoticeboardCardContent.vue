<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { NoticeboardCard } from '@/types/noticeboard';

withDefaults(
    defineProps<{
        card: Pick<NoticeboardCard, 'body_html' | 'image_url' | 'label'>;
        compact?: boolean;
    }>(),
    { compact: false },
);
const { t } = useI18n();
</script>

<template>
    <img
        v-if="card.image_url"
        :src="card.image_url"
        :alt="t(`noticeboard.labels.${card.label}`)"
        class="mt-4 w-full rounded-xl"
        :class="compact ? 'h-32 object-cover' : 'max-h-64 object-contain'"
    />
    <div
        class="noticeboard-rich-text mt-4 text-sm leading-relaxed text-slate-700"
        :class="compact ? 'max-h-48 overflow-y-auto pr-1' : ''"
        v-html="card.body_html"
    />
</template>

<style>
.noticeboard-rich-text p + p,
.noticeboard-rich-text ul + p,
.noticeboard-rich-text ol + p {
    margin-top: 0.65rem;
}
.noticeboard-rich-text ul {
    list-style: disc;
    padding-left: 1.25rem;
}
.noticeboard-rich-text ol {
    list-style: decimal;
    padding-left: 1.25rem;
}
.noticeboard-rich-text a {
    color: var(--color-primary);
    text-decoration: underline;
}
.noticeboard-rich-text [data-text-size='small'] {
    font-size: 0.75rem;
}
.noticeboard-rich-text [data-text-size='large'] {
    font-size: 1.25rem;
}
</style>
