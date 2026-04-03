<template>
    <div
        class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-sm hover:border-indigo-200 dark:hover:border-indigo-700 transition-colors cursor-pointer"
        @click="$emit('open', post)"
    >
        <!-- Author row -->
        <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-2.5">
                <UserAvatar
                    :name="post.author?.name"
                    :avatar="post.author?.avatar"
                    size="9"
                />
                <div>
                    <div class="flex items-center gap-1.5">
                        <p
                            class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-tight"
                        >
                            {{ post.author?.name }}
                        </p>
                        <span
                            v-if="post.is_pinned"
                            class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 leading-none"
                            >📌 Pinned</span
                        >
                    </div>
                    <p class="text-xs text-gray-400">
                        {{ formatDate(post.created_at) }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button
                    v-if="isAdmin"
                    @click.stop="$emit('togglePin', post)"
                    class="text-xs px-2 py-1 rounded transition-colors"
                    :class="
                        post.is_pinned
                            ? 'text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20'
                            : 'text-gray-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20'
                    "
                    :title="
                        post.is_pinned
                            ? 'Unpin post'
                            : 'Pin post'
                    "
                >
                    📌
                </button>
                <button
                    v-if="post.user_id === currentUserId"
                    @click.stop="startEdit"
                    class="text-xs text-gray-400 hover:text-indigo-500 transition-colors px-2 py-1 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/20"
                >
                    Edit
                </button>
                <button
                    v-if="canDelete"
                    @click.stop="$emit('delete', post)"
                    class="text-xs text-gray-400 hover:text-red-500 transition-colors px-2 py-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20"
                >
                    Delete
                </button>
            </div>
        </div>

        <!-- Inline edit form -->
        <form
            v-if="editing"
            @submit.prevent="submitEdit"
            @click.stop
            class="space-y-2 mb-3"
        >
            <input
                v-model="editForm.title"
                type="text"
                placeholder="Title (optional)"
                class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <textarea
                v-model="editForm.content"
                rows="4"
                required
                class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
            />
            <div class="flex gap-2 justify-end">
                <button
                    type="button"
                    @click.stop="cancelEdit"
                    class="px-3 py-1.5 text-xs rounded-lg border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="editForm.processing"
                    class="px-3 py-1.5 text-xs rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                >
                    Save
                </button>
            </div>
        </form>

        <!-- Content -->
        <template v-else>
            <h3
                v-if="post.title"
                class="font-bold text-gray-900 dark:text-gray-100 mb-1.5"
            >
                {{ post.title }}
            </h3>
            <div
                class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed line-clamp-4 prose prose-sm max-w-none"
                v-html="mdToHtml(post.content)"
            />
        </template>

        <!-- Post image -->
        <img
            v-if="post.image"
            :src="post.image"
            @click.stop="$emit('lightbox', post.image)"
            class="mt-3 rounded-xl max-h-72 w-full object-cover cursor-pointer hover:opacity-95 transition-opacity"
        />

        <!-- Post video embed -->
        <div
            v-if="
                post.video_url && getVideoEmbed(post.video_url)
            "
            class="mt-3 rounded-xl overflow-hidden aspect-video"
            @click.stop
        >
            <iframe
                :src="getVideoEmbed(post.video_url)"
                class="w-full h-full"
                frameborder="0"
                allowfullscreen
            />
        </div>

        <!-- Reaction bar -->
        <div
            class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center gap-4"
        >
            <!-- Reactions -->
            <div class="flex items-center gap-1">
                <button
                    v-for="r in REACTIONS"
                    :key="r.type"
                    @click.stop="$emit('react', post, r.type)"
                    class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs transition-colors"
                    :class="
                        post.user_reaction === r.type
                            ? 'bg-indigo-50 text-indigo-700 font-semibold dark:bg-indigo-900/30 dark:text-indigo-300'
                            : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700'
                    "
                    :title="r.label"
                >
                    <span>{{ r.emoji }}</span>
                    <span v-if="post.reactions?.[r.type]">{{
                        post.reactions[r.type]
                    }}</span>
                </button>
            </div>
            <button
                class="flex items-center gap-1.5 text-xs text-gray-500 hover:text-indigo-500 transition-colors"
            >
                <svg
                    class="w-4 h-4"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                    />
                </svg>
                {{ post.comments_count ?? 0 }}
            </button>

            <!-- Commenter avatars + last comment -->
            <div
                v-if="post.commenter_avatars?.length"
                class="flex items-center gap-2 ml-auto"
            >
                <div class="flex -space-x-1.5">
                    <div
                        v-for="(c, i) in post.commenter_avatars"
                        :key="i"
                        class="w-5 h-5 rounded-full ring-2 ring-white dark:ring-gray-800 shrink-0 overflow-hidden bg-indigo-100 flex items-center justify-center"
                    >
                        <img
                            v-if="c.avatar"
                            :src="c.avatar"
                            :alt="c.name"
                            class="w-full h-full object-cover"
                        />
                        <span
                            v-else
                            class="text-indigo-600 font-bold text-[8px]"
                            >{{
                                c.name?.charAt(0)?.toUpperCase()
                            }}</span
                        >
                    </div>
                </div>
                <span
                    v-if="post.last_comment_at"
                    class="text-xs text-gray-400"
                >
                    Last comment
                    {{ formatRelative(post.last_comment_at) }}
                </span>
            </div>
        </div>

        <!-- Latest 3 comments preview -->
        <div
            v-if="post.comments?.length"
            class="mt-3 space-y-2 border-t border-gray-100 dark:border-gray-700 pt-3"
            @click.stop
        >
            <div
                v-for="comment in post.comments.slice(0, 3)"
                :key="comment.id"
                class="flex gap-2.5 items-start"
            >
                <div
                    class="w-6 h-6 rounded-full shrink-0 overflow-hidden bg-indigo-100 flex items-center justify-center ring-1 ring-gray-200 dark:ring-gray-700"
                >
                    <img
                        v-if="comment.author?.avatar"
                        :src="comment.author.avatar"
                        :alt="comment.author.name"
                        class="w-full h-full object-cover"
                    />
                    <span
                        v-else
                        class="text-indigo-600 font-bold text-[9px]"
                        >{{
                            comment.author?.name
                                ?.charAt(0)
                                ?.toUpperCase()
                        }}</span
                    >
                </div>
                <div class="flex-1 min-w-0">
                    <span
                        class="text-xs font-semibold text-gray-800 dark:text-gray-100 mr-1.5"
                        >{{ comment.author?.name }}</span
                    >
                    <span
                        class="text-xs text-gray-600 dark:text-gray-300 break-words"
                        >{{ comment.content }}</span
                    >
                </div>
            </div>
            <button
                v-if="post.comments_count > 3"
                @click.stop="$emit('open', post)"
                class="text-xs text-indigo-500 hover:text-indigo-700 font-medium transition-colors"
            >
                View all {{ post.comments_count }} comments
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";
import { useForm } from "@inertiajs/vue3";
import UserAvatar from "@/Components/UserAvatar.vue";
import { REACTIONS, mdToHtml, getVideoEmbed, formatDate, formatRelative } from "./postHelpers";

const props = defineProps({
    post: { type: Object, required: true },
    isAdmin: { type: Boolean, default: false },
    currentUserId: { type: [Number, String], default: null },
    canDelete: { type: Boolean, default: false },
});

defineEmits(["open", "delete", "togglePin", "react", "lightbox"]);

// ─── Inline editing ──────────────────────────────────────────────────────────
const editing = ref(false);
const editForm = useForm({ title: "", content: "" });

function startEdit() {
    editing.value = true;
    editForm.title = props.post.title ?? "";
    editForm.content = props.post.content ?? "";
}

function cancelEdit() {
    editing.value = false;
    editForm.reset();
}

function submitEdit() {
    editForm.patch(`/posts/${props.post.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false;
        },
    });
}
</script>
