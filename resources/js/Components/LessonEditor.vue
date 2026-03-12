<template>
    <div class="border border-gray-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-transparent">
        <!-- Toolbar -->
        <div class="flex items-center gap-0.5 px-2 py-1.5 bg-gray-50 border-b border-gray-200 flex-wrap">
            <button
                type="button"
                @click="editor.chain().focus().toggleBold().run()"
                :class="editor?.isActive('bold') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs font-bold transition-colors"
                title="Bold"
            >B</button>
            <button
                type="button"
                @click="editor.chain().focus().toggleItalic().run()"
                :class="editor?.isActive('italic') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs italic transition-colors"
                title="Italic"
            >I</button>
            <div class="w-px h-4 bg-gray-200 mx-1" />
            <button
                type="button"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                :class="editor?.isActive('heading', { level: 2 }) ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs font-semibold transition-colors"
                title="Heading"
            >H2</button>
            <button
                type="button"
                @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                :class="editor?.isActive('heading', { level: 3 }) ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs font-semibold transition-colors"
                title="Subheading"
            >H3</button>
            <div class="w-px h-4 bg-gray-200 mx-1" />
            <button
                type="button"
                @click="editor.chain().focus().toggleBulletList().run()"
                :class="editor?.isActive('bulletList') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs transition-colors"
                title="Bullet list"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleOrderedList().run()"
                :class="editor?.isActive('orderedList') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs transition-colors"
                title="Numbered list"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <div class="w-px h-4 bg-gray-200 mx-1" />
            <button
                type="button"
                @click="editor.chain().focus().toggleCodeBlock().run()"
                :class="editor?.isActive('codeBlock') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs font-mono transition-colors"
                title="Code block"
            >&lt;/&gt;</button>
            <button
                type="button"
                @click="setLink"
                :class="editor?.isActive('link') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs transition-colors"
                title="Link"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
            </button>
            <div class="w-px h-4 bg-gray-200 mx-1" />
            <button
                type="button"
                @click="editor.chain().focus().undo().run()"
                :disabled="!editor?.can().undo()"
                class="px-2 py-1 rounded text-xs text-gray-400 hover:bg-gray-100 disabled:opacity-30 transition-colors"
                title="Undo"
            >↩</button>
            <button
                type="button"
                @click="editor.chain().focus().redo().run()"
                :disabled="!editor?.can().redo()"
                class="px-2 py-1 rounded text-xs text-gray-400 hover:bg-gray-100 disabled:opacity-30 transition-colors"
                title="Redo"
            >↪</button>
        </div>

        <!-- Editor content -->
        <EditorContent :editor="editor" class="lesson-editor-content" />
    </div>
</template>

<script setup>
import { watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Write lesson description...' },
    minHeight: { type: String, default: '120px' },
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Link.configure({ openOnClick: false }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none px-4 py-3 focus:outline-none',
            style: `min-height: ${props.minHeight}`,
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

watch(() => props.modelValue, (val) => {
    if (editor.value && val !== editor.value.getHTML()) {
        editor.value.commands.setContent(val, false);
    }
});

function setLink() {
    const prev = editor.value.getAttributes('link').href ?? '';
    const url = window.prompt('URL', prev);
    if (url === null) return;
    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }
}

onBeforeUnmount(() => editor.value?.destroy());
</script>

<style>
.lesson-editor-content .tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: #9ca3af;
    pointer-events: none;
    height: 0;
}
.lesson-editor-content .tiptap h2 { font-size: 1.1rem; font-weight: 700; margin: 0.75rem 0 0.25rem; }
.lesson-editor-content .tiptap h3 { font-size: 0.95rem; font-weight: 600; margin: 0.6rem 0 0.2rem; }
.lesson-editor-content .tiptap ul { list-style-type: disc; padding-left: 1.25rem; margin: 0.4rem 0; }
.lesson-editor-content .tiptap ol { list-style-type: decimal; padding-left: 1.25rem; margin: 0.4rem 0; }
.lesson-editor-content .tiptap li { margin: 0.15rem 0; }
.lesson-editor-content .tiptap code { background: #f3f4f6; padding: 0.1em 0.35em; border-radius: 4px; font-size: 0.85em; }
.lesson-editor-content .tiptap pre { background: #1e1e2e; color: #cdd6f4; padding: 1rem; border-radius: 8px; overflow-x: auto; margin: 0.5rem 0; }
.lesson-editor-content .tiptap pre code { background: none; padding: 0; color: inherit; font-size: 0.85rem; }
.lesson-editor-content .tiptap a { color: #4f46e5; text-decoration: underline; cursor: pointer; }
.lesson-editor-content .tiptap strong { font-weight: 700; }
.lesson-editor-content .tiptap em { font-style: italic; }
.lesson-editor-content .tiptap p { margin: 0.25rem 0; line-height: 1.6; }
</style>
