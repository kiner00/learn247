<template>
    <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-gray-900 text-base">
                💬 Discussion
                <span class="text-sm font-normal text-gray-400 ml-1">({{ comments.length }})</span>
            </h3>
        </div>

        <div class="p-6 space-y-4">
            <!-- Comment form -->
            <form @submit.prevent="$emit('post-comment')" class="flex gap-3">
                <div class="flex-1">
                    <textarea
                        v-model="commentForm.content"
                        rows="2"
                        placeholder="Ask a question or leave a comment..."
                        class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                    />
                </div>
                <button type="submit" :disabled="commentForm.processing || !commentForm.content.trim()"
                    class="self-end px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                    Post
                </button>
            </form>

            <!-- Comments list -->
            <div v-if="comments.length" class="space-y-3">
                <div v-for="comment in comments" :key="comment.id"
                    class="flex gap-3">
                    <!-- Avatar -->
                    <div class="shrink-0">
                        <img v-if="comment.author?.avatar" :src="comment.author.avatar"
                            class="w-8 h-8 rounded-full object-cover" />
                        <div v-else class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">
                            {{ comment.author?.name?.charAt(0) }}
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="bg-gray-50 rounded-xl px-4 py-3">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-bold text-gray-800">{{ comment.author?.name }}</span>
                                <button
                                    v-if="comment.author?.id === authUserId || isOwner"
                                    @click="$emit('delete-comment', comment.id)"
                                    class="text-xs text-red-400 hover:text-red-600"
                                >✕</button>
                            </div>
                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ comment.content }}</p>
                        </div>
                        <!-- Replies -->
                        <div v-if="comment.replies?.length" class="ml-4 mt-2 space-y-2">
                            <div v-for="reply in comment.replies" :key="reply.id" class="flex gap-2">
                                <img v-if="reply.author?.avatar" :src="reply.author.avatar"
                                    class="w-6 h-6 rounded-full object-cover shrink-0" />
                                <div v-else class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-500 shrink-0">
                                    {{ reply.author?.name?.charAt(0) }}
                                </div>
                                <div class="bg-gray-50 rounded-xl px-3 py-2 flex-1">
                                    <p class="text-xs font-bold text-gray-800 mb-0.5">{{ reply.author?.name }}</p>
                                    <p class="text-xs text-gray-700">{{ reply.content }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <p v-else class="text-sm text-gray-400 text-center py-4">No comments yet. Be the first to ask a question!</p>
        </div>
    </div>
</template>

<script setup>
defineProps({
    comments:    { type: Array, required: true },
    commentForm: { type: Object, required: true },
    authUserId:  { type: [Number, String], default: null },
    isOwner:     { type: Boolean, default: false },
});

defineEmits(['post-comment', 'delete-comment']);
</script>
