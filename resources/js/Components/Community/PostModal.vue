<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="post"
                class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-12 bg-black/50 backdrop-blur-sm overflow-y-auto"
                @click.self="$emit('close')"
            >
                <div
                    class="w-full max-w-2xl bg-white dark:bg-gray-900 rounded-2xl shadow-2xl relative"
                    @click.stop
                >
                    <!-- Header -->
                    <div
                        class="flex items-start justify-between p-5 border-b border-gray-100 dark:border-gray-800"
                    >
                        <div class="flex items-center gap-3">
                            <UserAvatar
                                :name="post.author?.name"
                                :avatar="post.author?.avatar"
                                size="10"
                            />
                            <div>
                                <p
                                    class="text-sm font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    {{ post.author?.name }}
                                </p>
                                <p class="text-xs text-gray-400">
                                    {{ formatDate(post.created_at) }}
                                </p>
                            </div>
                        </div>
                        <button
                            @click="$emit('close')"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-5">
                        <h2
                            v-if="post.title"
                            class="text-xl font-black text-gray-900 dark:text-gray-100 mb-3"
                        >
                            {{ post.title }}
                        </h2>
                        <div
                            class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed prose prose-sm max-w-none"
                            v-html="mdToHtml(post.content)"
                        />
                        <!-- Image -->
                        <img
                            v-if="post.image"
                            :src="post.image"
                            @click="$emit('lightbox', post.image)"
                            class="mt-4 rounded-xl w-full object-cover max-h-96 cursor-pointer hover:opacity-95 transition-opacity"
                        />
                        <!-- Video embed -->
                        <div
                            v-if="
                                post.video_url &&
                                getVideoEmbed(post.video_url)
                            "
                            class="mt-4 rounded-xl overflow-hidden aspect-video"
                        >
                            <iframe
                                :src="getVideoEmbed(post.video_url)"
                                class="w-full h-full"
                                frameborder="0"
                                allowfullscreen
                            />
                        </div>
                    </div>

                    <!-- Reaction bar -->
                    <div
                        class="px-5 pb-4 flex items-center gap-5 border-b border-gray-100 dark:border-gray-800"
                    >
                        <div class="flex items-center gap-1">
                            <button
                                v-for="r in REACTIONS"
                                :key="r.type"
                                @click="$emit('reactPost', post, r.type)"
                                class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-sm font-medium transition-colors"
                                :class="
                                    post.user_reaction === r.type
                                        ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                        : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700'
                                "
                                :title="r.label"
                            >
                                <span>{{ r.emoji }}</span>
                                <span
                                    >{{
                                        post.reactions?.[r.type] || 0
                                    }}
                                    {{ r.label }}</span
                                >
                            </button>
                        </div>
                        <span
                            class="flex items-center gap-2 text-sm text-gray-500"
                        >
                            <svg
                                class="w-5 h-5"
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
                            {{ post.comments?.length ?? 0 }} Comments
                        </span>
                        <button
                            v-if="canDeletePost"
                            @click="
                                $emit('deletePost', post);
                                $emit('close');
                            "
                            class="ml-auto text-xs text-gray-400 hover:text-red-500 transition-colors"
                        >
                            Delete post
                        </button>
                    </div>

                    <!-- Comments -->
                    <div class="p-5 space-y-4 max-h-[50vh] overflow-y-auto">
                        <div
                            v-if="!post.comments?.length"
                            class="text-center py-8 text-sm text-gray-400"
                        >
                            No comments yet. Be the first!
                        </div>
                        <div
                            v-for="comment in post.comments"
                            :key="comment.id"
                        >
                            <div class="flex gap-3">
                                <UserAvatar
                                    :name="comment.author?.name"
                                    :avatar="comment.author?.avatar"
                                    size="8"
                                    class="mt-0.5 shrink-0"
                                />
                                <div class="flex-1 min-w-0">
                                    <div
                                        class="bg-gray-50 dark:bg-gray-800 rounded-xl px-4 py-3"
                                    >
                                        <p
                                            class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-0.5"
                                        >
                                            {{ comment.author?.name }}
                                        </p>
                                        <p
                                            class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed"
                                        >
                                            {{ comment.content }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex items-center gap-3 mt-1.5 ml-1"
                                    >
                                        <p class="text-xs text-gray-400">
                                            {{
                                                formatRelative(
                                                    comment.created_at,
                                                )
                                            }}
                                        </p>
                                        <div
                                            class="flex items-center gap-0.5"
                                        >
                                            <button
                                                v-for="r in REACTIONS"
                                                :key="r.type"
                                                @click="$emit('reactComment', comment, r.type)"
                                                class="flex items-center gap-0.5 px-1.5 py-0.5 rounded text-xs transition-colors"
                                                :class="
                                                    comment.user_reaction ===
                                                    r.type
                                                        ? 'bg-indigo-50 text-indigo-700 font-medium dark:bg-indigo-900/30'
                                                        : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'
                                                "
                                                :title="r.label"
                                            >
                                                <span>{{ r.emoji }}</span>
                                                <span
                                                    v-if="
                                                        comment.reactions?.[
                                                            r.type
                                                        ]
                                                    "
                                                    >{{
                                                        comment.reactions[
                                                            r.type
                                                        ]
                                                    }}</span
                                                >
                                            </button>
                                        </div>
                                        <button
                                            v-if="isMember"
                                            @click="replyTarget = comment"
                                            class="text-xs text-gray-400 hover:text-indigo-500 font-medium transition-colors"
                                        >
                                            Reply
                                        </button>
                                        <button
                                            v-if="canDeleteComment(comment)"
                                            @click="$emit('deleteComment', comment)"
                                            class="text-xs text-gray-400 hover:text-red-500 transition-colors ml-auto"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                    <!-- Replies -->
                                    <div
                                        v-if="comment.replies?.length"
                                        class="mt-3 space-y-3 ml-2 pl-3 border-l-2 border-gray-100 dark:border-gray-700"
                                    >
                                        <div
                                            v-for="reply in comment.replies"
                                            :key="reply.id"
                                            class="flex gap-2.5"
                                        >
                                            <UserAvatar
                                                :name="reply.author?.name"
                                                :avatar="
                                                    reply.author?.avatar
                                                "
                                                size="7"
                                                class="mt-0.5 shrink-0"
                                            />
                                            <div class="flex-1 min-w-0">
                                                <div
                                                    class="bg-gray-50 dark:bg-gray-800 rounded-xl px-3 py-2.5"
                                                >
                                                    <p
                                                        class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-0.5"
                                                    >
                                                        {{
                                                            reply.author
                                                                ?.name
                                                        }}
                                                    </p>
                                                    <p
                                                        class="text-sm text-gray-600 dark:text-gray-300"
                                                    >
                                                        {{ reply.content }}
                                                    </p>
                                                </div>
                                                <div
                                                    class="flex items-center gap-3 mt-1 ml-1"
                                                >
                                                    <p
                                                        class="text-xs text-gray-400"
                                                    >
                                                        {{
                                                            formatRelative(
                                                                reply.created_at,
                                                            )
                                                        }}
                                                    </p>
                                                    <div
                                                        class="flex items-center gap-0.5"
                                                    >
                                                        <button
                                                            v-for="r in REACTIONS"
                                                            :key="r.type"
                                                            @click="$emit('reactComment', reply, r.type)"
                                                            class="flex items-center gap-0.5 px-1.5 py-0.5 rounded text-xs transition-colors"
                                                            :class="
                                                                reply.user_reaction ===
                                                                r.type
                                                                    ? 'bg-indigo-50 text-indigo-700 font-medium dark:bg-indigo-900/30'
                                                                    : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'
                                                            "
                                                            :title="r.label"
                                                        >
                                                            <span>{{
                                                                r.emoji
                                                            }}</span>
                                                            <span
                                                                v-if="
                                                                    reply
                                                                        .reactions?.[
                                                                        r
                                                                            .type
                                                                    ]
                                                                "
                                                                >{{
                                                                    reply
                                                                        .reactions[
                                                                        r
                                                                            .type
                                                                    ]
                                                                }}</span
                                                            >
                                                        </button>
                                                    </div>
                                                    <button
                                                        v-if="
                                                            canDeleteComment(
                                                                reply,
                                                            )
                                                        "
                                                        @click="$emit('deleteComment', reply)"
                                                        class="text-xs text-gray-400 hover:text-red-500 transition-colors ml-auto"
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Comment input -->
                    <div
                        v-if="isMember"
                        class="p-4 border-t border-gray-100 dark:border-gray-800"
                    >
                        <div
                            v-if="replyTarget"
                            class="flex items-center gap-2 text-xs text-indigo-600 dark:text-indigo-400 mb-2 bg-indigo-50 dark:bg-indigo-900/20 px-3 py-1.5 rounded-lg"
                        >
                            <svg
                                class="w-3.5 h-3.5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"
                                />
                            </svg>
                            Replying to
                            <span class="font-semibold">{{
                                replyTarget.author?.name
                            }}</span>
                            <button
                                @click="replyTarget = null"
                                class="ml-auto text-gray-400 hover:text-gray-600"
                            >
                                ✕
                            </button>
                        </div>
                        <div class="flex gap-3 items-end">
                            <UserAvatar
                                :name="authUser?.name"
                                :avatar="authUser?.avatar"
                                size="8"
                                class="shrink-0"
                            />
                            <div class="flex-1 flex gap-2">
                                <textarea
                                    v-model="commentInput"
                                    rows="1"
                                    :placeholder="
                                        replyTarget
                                            ? `Reply to ${replyTarget.author?.name}...`
                                            : 'Write a comment...'
                                    "
                                    class="flex-1 px-3.5 py-2.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"
                                    @keydown.enter.exact.prevent="
                                        submitComment
                                    "
                                />
                                <button
                                    @click="submitComment"
                                    :disabled="!commentInput.trim()"
                                    class="px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors disabled:opacity-40 self-end"
                                >
                                    Post
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import UserAvatar from "@/Components/UserAvatar.vue";
import { REACTIONS, mdToHtml, getVideoEmbed, formatDate, formatRelative } from "./postHelpers";

const props = defineProps({
    post: { type: Object, default: null },
    isMember: { type: Boolean, default: false },
    canDeletePost: { type: Boolean, default: false },
    authUser: { type: Object, default: null },
    currentUserId: { type: [Number, String], default: null },
    membershipRole: { type: String, default: null },
});

const emit = defineEmits([
    "close",
    "lightbox",
    "reactPost",
    "reactComment",
    "deletePost",
    "deleteComment",
]);

const commentInput = ref("");
const replyTarget = ref(null);

// Reset state when post changes
watch(
    () => props.post?.id,
    () => {
        commentInput.value = "";
        replyTarget.value = null;
    },
);

function canDeleteComment(comment) {
    const userId = props.currentUserId;
    return (
        userId &&
        (comment.user_id === userId ||
            props.membershipRole === "admin" ||
            props.membershipRole === "moderator")
    );
}

function submitComment() {
    const content = commentInput.value.trim();
    if (!content || !props.post) return;
    router.post(
        `/posts/${props.post.id}/comments`,
        {
            content,
            parent_id: replyTarget.value?.id ?? null,
        },
        {
            onSuccess: () => {
                commentInput.value = "";
                replyTarget.value = null;
            },
            preserveScroll: true,
        },
    );
}
</script>
