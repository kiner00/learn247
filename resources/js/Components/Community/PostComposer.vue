<template>
    <div
        class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm"
    >
        <!-- Collapsed -->
        <div
            v-if="!composing"
            class="flex items-center gap-3 px-4 py-3 cursor-text"
            @click="composing = true"
        >
            <UserAvatar
                :name="authUser?.name"
                :avatar="authUser?.avatar"
                size="8"
            />
            <span class="text-sm text-gray-400 flex-1"
                >Write something...</span
            >
        </div>
        <!-- Expanded -->
        <form
            v-else
            @submit.prevent="handleSubmit"
            class="p-4 space-y-2"
        >
            <div class="flex items-start gap-3">
                <UserAvatar
                    :name="authUser?.name"
                    :avatar="authUser?.avatar"
                    size="8"
                    class="mt-0.5"
                />
                <div class="flex-1 space-y-2">
                    <input
                        v-model="form.title"
                        type="text"
                        placeholder="Title (optional)"
                        autofocus
                        class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <!-- Formatting toolbar -->
                    <div class="flex items-center gap-1 px-1">
                        <button
                            type="button"
                            @click="applyBold"
                            class="px-2 py-0.5 text-xs font-bold border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300"
                            title="Bold (**text**)"
                        >
                            B
                        </button>
                        <button
                            type="button"
                            @click="applyItalic"
                            class="px-2 py-0.5 text-xs italic border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300"
                            title="Italic (_text_)"
                        >
                            I
                        </button>
                        <button
                            type="button"
                            @click="applyBullet"
                            class="px-2 py-0.5 text-xs border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300"
                            title="Bullet list (- item)"
                        >
                            • List
                        </button>
                    </div>
                    <textarea
                        id="post-content-editor"
                        v-model="form.content"
                        rows="4"
                        placeholder="Share something with the community..."
                        required
                        class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none font-mono"
                        :class="
                            form.errors.content
                                ? 'border-red-400'
                                : ''
                        "
                    />

                    <!-- Image preview -->
                    <div
                        v-if="imagePreview"
                        class="relative inline-block"
                    >
                        <img
                            :src="imagePreview"
                            class="h-32 rounded-xl object-cover border border-gray-200"
                        />
                        <button
                            type="button"
                            @click="removeImage"
                            class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center leading-none"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Video URL input -->
                    <div
                        v-if="showVideoInput"
                        class="flex items-center gap-2"
                    >
                        <input
                            v-model="form.video_url"
                            type="url"
                            placeholder="Paste YouTube or Vimeo link..."
                            class="flex-1 px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <button
                            type="button"
                            @click="
                                showVideoInput = false;
                                form.video_url = '';
                            "
                            class="text-gray-400 hover:text-red-500 text-sm"
                        >
                            ✕
                        </button>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <!-- Media buttons -->
                <div class="flex items-center gap-1 pl-12">
                    <input
                        ref="imageInput"
                        type="file"
                        accept="image/*"
                        class="hidden"
                        @change="onImageChange"
                    />
                    <button
                        type="button"
                        @click="imageInput.click()"
                        class="flex items-center gap-1 px-3 py-1.5 text-xs text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg transition-colors"
                        title="Attach image"
                    >
                        <svg
                            class="w-4 h-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <rect
                                x="3"
                                y="3"
                                width="18"
                                height="18"
                                rx="2"
                            />
                            <circle cx="8.5" cy="8.5" r="1.5" />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M21 15l-5-5L5 21"
                            />
                        </svg>
                        Photo
                    </button>
                    <button
                        type="button"
                        @click="showVideoInput = !showVideoInput"
                        class="flex items-center gap-1 px-3 py-1.5 text-xs text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg transition-colors"
                        title="Add video link"
                    >
                        <svg
                            class="w-4 h-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"
                            />
                        </svg>
                        Video
                    </button>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="handleCancel"
                        class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 rounded-xl"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="
                            form.processing ||
                            !form.content.trim()
                        "
                        class="px-5 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-40 transition-colors"
                    >
                        {{
                            form.processing
                                ? "Posting..."
                                : "Post"
                        }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref } from "vue";
import { useForm } from "@inertiajs/vue3";
import UserAvatar from "@/Components/UserAvatar.vue";

const props = defineProps({
    communitySlug: { type: String, required: true },
    authUser: { type: Object, default: null },
});

const emit = defineEmits(["posted"]);

const composing = ref(false);
const imagePreview = ref(null);
const imageInput = ref(null);
const showVideoInput = ref(false);

const form = useForm({
    title: "",
    content: "",
    image: null,
    video_url: "",
});

function onImageChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    form.image = file;
    imagePreview.value = URL.createObjectURL(file);
}

function removeImage() {
    form.image = null;
    imagePreview.value = null;
    if (imageInput.value) imageInput.value.value = "";
}

function handleSubmit() {
    form.post(`/communities/${props.communitySlug}/posts`, {
        onSuccess: () => {
            form.reset();
            removeImage();
            showVideoInput.value = false;
            composing.value = false;
            emit("posted");
        },
        preserveScroll: true,
        forceFormData: true,
    });
}

function handleCancel() {
    composing.value = false;
    form.reset();
    removeImage();
    showVideoInput.value = false;
}

// ─── Rich text formatting helpers ─────────────────────────────────────────────
function wrapSelection(textarea, before, after) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const sel = textarea.value.substring(start, end);
    const replacement = before + (sel || "text") + after;
    const newVal =
        textarea.value.substring(0, start) +
        replacement +
        textarea.value.substring(end);
    return { newVal, cursor: start + replacement.length };
}

function applyBold() {
    const el = document.getElementById("post-content-editor");
    if (!el) return;
    const { newVal, cursor } = wrapSelection(el, "**", "**");
    form.content = newVal;
    el.focus();
    el.setSelectionRange(cursor, cursor);
}

function applyItalic() {
    const el = document.getElementById("post-content-editor");
    if (!el) return;
    const { newVal, cursor } = wrapSelection(el, "_", "_");
    form.content = newVal;
    el.focus();
    el.setSelectionRange(cursor, cursor);
}

function applyBullet() {
    const el = document.getElementById("post-content-editor");
    if (!el) return;
    const start = el.selectionStart;
    const lineStart = form.content.lastIndexOf("\n", start - 1) + 1;
    const newVal =
        form.content.substring(0, lineStart) +
        "- " +
        form.content.substring(lineStart);
    form.content = newVal;
    el.focus();
    el.setSelectionRange(start + 2, start + 2);
}
</script>
