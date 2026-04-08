<template>
    <div class="border border-gray-200 rounded-xl overflow-y-auto max-h-[70vh] focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-transparent">
        <!-- Toolbar -->
        <div class="flex items-center gap-0.5 px-2 py-1.5 bg-gray-50 border-b border-gray-200 flex-wrap sticky top-0 z-10">
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
            <button
                v-if="uploadUrl"
                type="button"
                @click="triggerImageUpload"
                :disabled="uploading"
                class="px-2 py-1 rounded text-xs transition-colors"
                :class="uploading ? 'text-indigo-400 bg-indigo-50' : 'text-gray-500 hover:bg-gray-100'"
                title="Upload image"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
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

        <input
            ref="imageInput"
            type="file"
            accept="image/*"
            class="hidden"
            @change="handleImageUpload"
        />
    </div>
</template>

<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import axios from 'axios';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Write lesson description...' },
    minHeight: { type: String, default: '120px' },
    uploadUrl: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const imageInput = ref(null);
const uploading = ref(false);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Link.configure({ openOnClick: false }),
        Image.configure({ inline: false, allowBase64: false }),
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

function triggerImageUpload() {
    imageInput.value?.click();
}

async function handleImageUpload(event) {
    const file = event.target.files?.[0];
    if (!file || !props.uploadUrl) return;

    uploading.value = true;
    try {
        const formData = new FormData();
        formData.append('image', file);

        const { data } = await axios.post(props.uploadUrl, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        if (data.url) {
            editor.value.chain().focus().setImage({ src: data.url }).run();
        }
    } catch (error) {
        console.error('Image upload failed:', error);
    } finally {
        uploading.value = false;
        imageInput.value.value = '';
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
.lesson-editor-content .tiptap img { max-width: 100%; height: auto; border-radius: 8px; margin: 0.5rem 0; }
</style>
