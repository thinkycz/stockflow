<script setup lang="ts">
import { computed } from 'vue';
import { renderAssistantMarkdown } from '@/lib/assistant-markdown';

const props = defineProps<{ source: string }>();
const html = computed(() => renderAssistantMarkdown(props.source));
</script>

<template>
    <div class="assistant-markdown break-words" v-html="html" />
</template>

<style scoped>
.assistant-markdown :deep(> * + *) {
    margin-top: 0.75rem;
}

.assistant-markdown :deep(h1),
.assistant-markdown :deep(h2),
.assistant-markdown :deep(h3),
.assistant-markdown :deep(h4) {
    color: var(--color-on-surface);
    font-weight: 700;
    line-height: 1.35;
}

.assistant-markdown :deep(h1) {
    font-size: 1.25rem;
}

.assistant-markdown :deep(h2) {
    font-size: 1.125rem;
}

.assistant-markdown :deep(h3),
.assistant-markdown :deep(h4) {
    font-size: 1rem;
}

.assistant-markdown :deep(ul),
.assistant-markdown :deep(ol) {
    padding-left: 1.35rem;
}

.assistant-markdown :deep(ul) {
    list-style: disc;
}

.assistant-markdown :deep(ol) {
    list-style: decimal;
}

.assistant-markdown :deep(li + li) {
    margin-top: 0.25rem;
}

.assistant-markdown :deep(blockquote) {
    border-left: 3px solid
        color-mix(in srgb, var(--color-primary) 35%, transparent);
    color: var(--color-on-surface-variant);
    padding-left: 0.875rem;
}

.assistant-markdown :deep(a) {
    color: var(--color-primary);
    font-weight: 600;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.assistant-markdown :deep(code) {
    border-radius: 0.35rem;
    background: var(--color-surface-container);
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.82em;
    padding: 0.12rem 0.3rem;
}

.assistant-markdown :deep(pre) {
    max-width: 100%;
    overflow-x: auto;
    border: 1px solid var(--color-outline-glass);
    border-radius: 0.75rem;
    background: var(--color-surface-container-low);
    padding: 0.875rem;
}

.assistant-markdown :deep(pre code) {
    background: transparent;
    padding: 0;
}

.assistant-markdown :deep(table) {
    display: block;
    max-width: 100%;
    overflow-x: auto;
    border-collapse: collapse;
}

.assistant-markdown :deep(th),
.assistant-markdown :deep(td) {
    border: 1px solid var(--color-outline-glass);
    padding: 0.4rem 0.65rem;
    text-align: left;
}

.assistant-markdown :deep(th) {
    background: var(--color-surface-container-low);
    font-weight: 700;
}
</style>
