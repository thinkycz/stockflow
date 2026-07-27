<script setup lang="ts">
import { Mark, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    Bold,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    RemoveFormatting,
    Underline,
} from '@lucide/vue';
import { onBeforeUnmount, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        invalid?: boolean;
    }>(),
    {
        invalid: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const { t } = useI18n();

const TextSize = Mark.create({
    name: 'textSize',
    addAttributes() {
        return {
            size: {
                default: 'normal',
                parseHTML: (element: HTMLElement) =>
                    element.getAttribute('data-text-size') ?? 'normal',
                renderHTML: (attributes: Record<string, unknown>) => ({
                    'data-text-size': attributes.size,
                }),
            },
        };
    },
    parseHTML() {
        return [{ tag: 'span[data-text-size]' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['span', mergeAttributes(HTMLAttributes), 0];
    },
});

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            blockquote: false,
            code: false,
            codeBlock: false,
            heading: false,
            horizontalRule: false,
            strike: false,
            link: {
                openOnClick: false,
                defaultProtocol: 'https',
                HTMLAttributes: {
                    target: '_blank',
                    rel: 'noopener noreferrer nofollow',
                },
            },
        }),
        TextSize,
    ],
    editorProps: {
        attributes: {
            class: 'noticeboard-editor min-h-40 px-4 py-3 text-sm leading-relaxed outline-none',
        },
    },
    onUpdate: ({ editor: activeEditor }) => {
        emit('update:modelValue', activeEditor.getHTML());
    },
});

watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && editor.value.getHTML() !== value) {
            editor.value.commands.setContent(value, { emitUpdate: false });
        }
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

function setTextSize(size: 'small' | 'normal' | 'large'): void {
    editor.value?.chain().focus().setMark('textSize', { size }).run();
}

function setLink(): void {
    const current = editor.value?.getAttributes('link').href as
        string | undefined;
    const href = window.prompt(t('noticeboard.editor.link_prompt'), current);

    if (href === null || !editor.value) return;
    if (href.trim() === '') {
        editor.value.chain().focus().unsetLink().run();
        return;
    }

    editor.value.chain().focus().setLink({ href: href.trim() }).run();
}

function toolbarClass(active = false): string {
    return cn(
        'inline-flex size-8 items-center justify-center rounded-lg text-on-surface-variant transition hover:bg-surface-container-high hover:text-on-surface',
        active ? 'bg-primary/10 text-primary' : '',
    );
}
</script>

<template>
    <div
        :class="
            cn(
                'overflow-hidden rounded-xl border bg-white',
                props.invalid
                    ? 'border-error-red'
                    : 'border-outline-glass focus-within:border-primary',
            )
        "
    >
        <div
            class="flex flex-wrap items-center gap-1 border-b border-outline-glass bg-surface-container-low px-2 py-1.5"
        >
            <select
                class="h-8 rounded-lg border border-outline-glass bg-white px-2 text-xs"
                :aria-label="t('noticeboard.editor.text_size')"
                @change="
                    setTextSize(
                        ($event.target as HTMLSelectElement).value as
                            'small' | 'normal' | 'large',
                    )
                "
            >
                <option value="small">
                    {{ t('noticeboard.editor.size_small') }}
                </option>
                <option value="normal" selected>
                    {{ t('noticeboard.editor.size_normal') }}
                </option>
                <option value="large">
                    {{ t('noticeboard.editor.size_large') }}
                </option>
            </select>
            <button
                type="button"
                :class="toolbarClass(editor?.isActive('bold'))"
                :aria-label="t('noticeboard.editor.bold')"
                @click="editor?.chain().focus().toggleBold().run()"
            >
                <Bold :size="16" />
            </button>
            <button
                type="button"
                :class="toolbarClass(editor?.isActive('italic'))"
                :aria-label="t('noticeboard.editor.italic')"
                @click="editor?.chain().focus().toggleItalic().run()"
            >
                <Italic :size="16" />
            </button>
            <button
                type="button"
                :class="toolbarClass(editor?.isActive('underline'))"
                :aria-label="t('noticeboard.editor.underline')"
                @click="editor?.chain().focus().toggleUnderline().run()"
            >
                <Underline :size="16" />
            </button>
            <button
                type="button"
                :class="toolbarClass(editor?.isActive('bulletList'))"
                :aria-label="t('noticeboard.editor.bullet_list')"
                @click="editor?.chain().focus().toggleBulletList().run()"
            >
                <List :size="16" />
            </button>
            <button
                type="button"
                :class="toolbarClass(editor?.isActive('orderedList'))"
                :aria-label="t('noticeboard.editor.ordered_list')"
                @click="editor?.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered :size="16" />
            </button>
            <button
                type="button"
                :class="toolbarClass(editor?.isActive('link'))"
                :aria-label="t('noticeboard.editor.link')"
                @click="setLink"
            >
                <LinkIcon :size="16" />
            </button>
            <button
                type="button"
                :class="toolbarClass()"
                :aria-label="t('noticeboard.editor.clear')"
                @click="
                    editor?.chain().focus().unsetAllMarks().clearNodes().run()
                "
            >
                <RemoveFormatting :size="16" />
            </button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>

<style>
.noticeboard-editor ul {
    list-style: disc;
    padding-left: 1.4rem;
}

.noticeboard-editor ol {
    list-style: decimal;
    padding-left: 1.4rem;
}

.noticeboard-editor [data-text-size='small'] {
    font-size: 0.75rem;
}

.noticeboard-editor [data-text-size='large'] {
    font-size: 1.25rem;
}
</style>
