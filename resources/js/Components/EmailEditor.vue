<template>
    <div class="email-editor border border-gray-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-transparent">
        <!-- Mode toggle -->
        <div class="flex items-center justify-between px-3 py-1.5 bg-gray-100 border-b border-gray-200">
            <span class="text-xs font-medium text-gray-500">{{ htmlMode ? 'HTML Source' : 'Visual Editor' }}</span>
            <button type="button" @click="toggleHtmlMode"
                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                {{ htmlMode ? 'Visual' : 'HTML' }}
            </button>
        </div>

        <!-- Toolbar (visual mode only) -->
        <div v-if="!htmlMode" class="flex items-center gap-0.5 px-2 py-1.5 bg-gray-50 border-b border-gray-200 flex-wrap">
            <!-- Font Family -->
            <select @change="setFontFamily($event.target.value); $event.target.value = ''"
                class="h-7 pl-1.5 pr-6 border border-gray-200 rounded text-xs bg-white text-gray-600 focus:outline-none focus:ring-1 focus:ring-indigo-400 cursor-pointer"
                title="Font family">
                <option value="">Font</option>
                <option v-for="f in fontFamilies" :key="f.value" :value="f.value">{{ f.label }}</option>
            </select>

            <!-- Font Size -->
            <select @change="setFontSize($event.target.value); $event.target.value = ''"
                class="h-7 pl-1.5 pr-6 border border-gray-200 rounded text-xs bg-white text-gray-600 focus:outline-none focus:ring-1 focus:ring-indigo-400 cursor-pointer"
                title="Font size">
                <option value="">Size</option>
                <option v-for="s in fontSizes" :key="s" :value="s">{{ s }}</option>
            </select>

            <div class="w-px h-4 bg-gray-200 mx-1" />

            <!-- Bold -->
            <button type="button" @click="editor.chain().focus().toggleBold().run()"
                :class="editor?.isActive('bold') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs font-bold transition-colors" title="Bold">B</button>
            <!-- Italic -->
            <button type="button" @click="editor.chain().focus().toggleItalic().run()"
                :class="editor?.isActive('italic') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs italic transition-colors" title="Italic">I</button>
            <!-- Underline -->
            <button type="button" @click="editor.chain().focus().toggleUnderline().run()"
                :class="editor?.isActive('underline') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs underline transition-colors" title="Underline">U</button>

            <div class="w-px h-4 bg-gray-200 mx-1" />

            <!-- H2 -->
            <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                :class="editor?.isActive('heading', { level: 2 }) ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs font-semibold transition-colors" title="Heading">H2</button>
            <!-- H3 -->
            <button type="button" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                :class="editor?.isActive('heading', { level: 3 }) ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs font-semibold transition-colors" title="Subheading">H3</button>

            <div class="w-px h-4 bg-gray-200 mx-1" />

            <!-- Bullet list -->
            <button type="button" @click="editor.chain().focus().toggleBulletList().run()"
                :class="editor?.isActive('bulletList') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs transition-colors" title="Bullet list">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
            </button>
            <!-- Ordered list -->
            <button type="button" @click="editor.chain().focus().toggleOrderedList().run()"
                :class="editor?.isActive('orderedList') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs transition-colors" title="Numbered list">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <div class="w-px h-4 bg-gray-200 mx-1" />

            <!-- Link -->
            <button type="button" @click="setLink"
                :class="editor?.isActive('link') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs transition-colors" title="Link">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
            </button>
            <!-- Image -->
            <button type="button" @click="triggerImageUpload" :disabled="uploading"
                :class="uploading ? 'text-indigo-400 bg-indigo-50' : 'text-gray-500 hover:bg-gray-100'"
                class="px-2 py-1 rounded text-xs transition-colors" title="Upload image">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </button>

            <div class="w-px h-4 bg-gray-200 mx-1" />

            <!-- Undo / Redo -->
            <button type="button" @click="editor.chain().focus().undo().run()" :disabled="!editor?.can().undo()"
                class="px-2 py-1 rounded text-xs text-gray-400 hover:bg-gray-100 disabled:opacity-30 transition-colors" title="Undo">&#8617;</button>
            <button type="button" @click="editor.chain().focus().redo().run()" :disabled="!editor?.can().redo()"
                class="px-2 py-1 rounded text-xs text-gray-400 hover:bg-gray-100 disabled:opacity-30 transition-colors" title="Redo">&#8618;</button>
        </div>

        <!-- Variables bar -->
        <div v-if="variables.length && !htmlMode" class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 border-b border-gray-200">
            <span class="text-xs text-gray-400 mr-1">Variables:</span>
            <button v-for="v in variables" :key="v.key" type="button"
                @click="editor.chain().focus().insertContent(v.key).run()"
                class="px-2 py-0.5 text-xs font-mono bg-white border border-gray-200 text-gray-600 rounded hover:bg-indigo-50 hover:text-indigo-700 hover:border-indigo-200 transition-colors">
                {{ v.key }}
            </button>
        </div>

        <!-- Visual editor -->
        <EditorContent v-if="!htmlMode" :editor="editor" class="email-editor-content" />

        <!-- HTML source editor -->
        <textarea v-else v-model="htmlSource" rows="14"
            class="w-full px-3.5 py-2.5 text-sm font-mono focus:outline-none resize-y border-0"
            placeholder="<p>Write your HTML here...</p>" />

        <input ref="imageInput" type="file" accept="image/*" class="hidden" @change="handleImageUpload" />
    </div>
</template>

<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import Underline from '@tiptap/extension-underline';
import { TextStyle, FontSize as FontSizeExt, FontFamily as FontFamilyExt } from '@tiptap/extension-text-style';
import axios from 'axios';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Write your email content...' },
    uploadUrl: { type: String, default: '' },
    variables: {
        type: Array,
        default: () => [
            { key: '{{user_name}}', label: 'Member name' },
            { key: '{{user_email}}', label: 'Member email' },
            { key: '{{community_name}}', label: 'Community name' },
        ],
    },
});

const emit = defineEmits(['update:modelValue']);

const imageInput = ref(null);
const uploading = ref(false);
const htmlMode = ref(false);
const htmlSource = ref('');

const fontFamilies = [
    { label: 'Sans-serif', value: 'Arial, Helvetica, sans-serif' },
    { label: 'Serif', value: 'Georgia, Times New Roman, serif' },
    { label: 'Monospace', value: 'Courier New, monospace' },
    { label: 'System', value: '-apple-system, BlinkMacSystemFont, sans-serif' },
    { label: 'Verdana', value: 'Verdana, Geneva, sans-serif' },
    { label: 'Trebuchet', value: 'Trebuchet MS, sans-serif' },
];

const fontSizes = ['12px', '14px', '16px', '18px', '20px', '24px', '28px', '32px'];

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Link.configure({ openOnClick: false }),
        Image.configure({ inline: false, allowBase64: false }),
        Placeholder.configure({ placeholder: props.placeholder }),
        Underline,
        TextStyle,
        FontSizeExt,
        FontFamilyExt,
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none px-4 py-3 focus:outline-none',
            style: 'min-height: 200px',
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

function setFontSize(size) {
    if (!size) return;
    editor.value.chain().focus().setFontSize(size).run();
}

function setFontFamily(family) {
    if (!family) return;
    editor.value.chain().focus().setFontFamily(family).run();
}

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
    if (!file) return;

    if (props.uploadUrl) {
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
    } else {
        // Fallback: convert to base64 for preview (not recommended for production)
        const reader = new FileReader();
        reader.onload = (e) => {
            editor.value.chain().focus().setImage({ src: e.target.result }).run();
        };
        reader.readAsDataURL(file);
        imageInput.value.value = '';
    }
}

function toggleHtmlMode() {
    if (htmlMode.value) {
        // Switching from HTML source back to visual
        editor.value.commands.setContent(htmlSource.value, false);
        emit('update:modelValue', htmlSource.value);
        htmlMode.value = false;
    } else {
        // Switching to HTML source
        htmlSource.value = editor.value.getHTML();
        htmlMode.value = true;
    }
}

// Sync HTML source changes back to modelValue
watch(htmlSource, (val) => {
    if (htmlMode.value) {
        emit('update:modelValue', val);
    }
});

onBeforeUnmount(() => editor.value?.destroy());
</script>

<style>
.email-editor-content .tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: #9ca3af;
    pointer-events: none;
    height: 0;
}
.email-editor-content .tiptap h2 { font-size: 1.25rem; font-weight: 700; margin: 0.75rem 0 0.25rem; }
.email-editor-content .tiptap h3 { font-size: 1.05rem; font-weight: 600; margin: 0.6rem 0 0.2rem; }
.email-editor-content .tiptap ul { list-style-type: disc; padding-left: 1.25rem; margin: 0.4rem 0; }
.email-editor-content .tiptap ol { list-style-type: decimal; padding-left: 1.25rem; margin: 0.4rem 0; }
.email-editor-content .tiptap li { margin: 0.15rem 0; }
.email-editor-content .tiptap a { color: #4f46e5; text-decoration: underline; cursor: pointer; }
.email-editor-content .tiptap strong { font-weight: 700; }
.email-editor-content .tiptap em { font-style: italic; }
.email-editor-content .tiptap u { text-decoration: underline; }
.email-editor-content .tiptap p { margin: 0.25rem 0; line-height: 1.6; }
.email-editor-content .tiptap img { max-width: 100%; height: auto; border-radius: 8px; margin: 0.5rem 0; }
</style>
