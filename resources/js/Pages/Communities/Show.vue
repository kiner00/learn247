<template>
    <AppLayout :title="community.name" :community="community">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Posts feed (2/3) -->
            <div class="lg:col-span-2 space-y-4">

                <!-- Community title + meta row -->
                <div class="bg-white border border-gray-200 rounded-2xl px-5 py-4 shadow-sm">
                    <h1 class="text-lg font-black text-gray-900 mb-2">{{ community.name }}</h1>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            {{ community.is_private ? 'Private' : 'Public' }}
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ community.members_count }} {{ community.members_count === 1 ? 'member' : 'members' }}
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ community.price > 0 ? `₱${Number(community.price).toLocaleString()}/month` : 'Free' }}
                        </span>
                        <span v-if="community.owner" class="flex items-center gap-1">
                            By {{ community.owner.name }}
                        </span>
                    </div>
                </div>

                <!-- Create post (members only) -->
                <div v-if="isMember" class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-linear-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-xs font-bold text-white shrink-0">
                            {{ userInitial }}
                        </div>
                        <form class="flex-1" @submit.prevent="createPost">
                            <input
                                v-model="postForm.title"
                                type="text"
                                placeholder="Post title (optional)"
                                class="w-full px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent mb-2"
                            />
                            <textarea
                                v-model="postForm.content"
                                rows="3"
                                placeholder="Share something with the community..."
                                required
                                class="w-full px-3.5 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                                :class="postForm.errors.content ? 'border-red-400' : 'border-gray-200'"
                            />
                            <p v-if="postForm.errors.content" class="mt-1 text-xs text-red-600">{{ postForm.errors.content }}</p>
                            <div class="flex justify-end mt-2">
                                <button
                                    type="submit"
                                    :disabled="postForm.processing || !postForm.content.trim()"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    {{ postForm.processing ? 'Posting...' : 'Post' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Post list -->
                <template v-if="community.posts?.length">
                    <div
                        v-for="post in community.posts"
                        :key="post.id"
                        class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm hover:border-indigo-100 transition-colors"
                    >
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-linear-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-xs font-bold text-white shrink-0">
                                    {{ post.author?.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ post.author?.name }}</p>
                                    <p class="text-xs text-gray-400">{{ formatDate(post.created_at) }}</p>
                                </div>
                            </div>
                            <button
                                v-if="canDeletePost(post)"
                                @click="deletePost(post)"
                                class="text-xs text-gray-400 hover:text-red-500 transition-colors px-2 py-1 rounded hover:bg-red-50"
                            >
                                Delete
                            </button>
                        </div>

                        <h3 v-if="post.title" class="font-bold text-gray-900 mb-1.5">{{ post.title }}</h3>
                        <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ post.content }}</p>

                        <!-- Comments -->
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <button
                                @click="toggleComments(post.id)"
                                class="flex items-center gap-1.5 text-xs text-gray-500 hover:text-indigo-600 transition-colors"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                {{ openComments.has(post.id) ? 'Hide' : 'Show' }}
                                {{ post.comments?.length ?? 0 }} comment{{ (post.comments?.length ?? 0) !== 1 ? 's' : '' }}
                            </button>

                            <div v-if="openComments.has(post.id)" class="mt-3 space-y-2.5">
                                <div v-for="comment in post.comments" :key="comment.id" class="flex gap-2.5">
                                    <div class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-xs font-semibold text-gray-600 shrink-0 mt-0.5">
                                        {{ comment.user?.name?.charAt(0)?.toUpperCase() }}
                                    </div>
                                    <div class="flex-1 bg-gray-50 rounded-xl px-3 py-2">
                                        <p class="text-xs font-semibold text-gray-700">{{ comment.user?.name }}</p>
                                        <p class="text-sm text-gray-600 mt-0.5">{{ comment.content }}</p>
                                    </div>
                                    <button
                                        v-if="canDeleteComment(comment)"
                                        @click="deleteComment(comment)"
                                        class="text-gray-300 hover:text-red-500 transition-colors self-start mt-1.5 px-1"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                <div v-if="isMember" class="flex gap-2.5">
                                    <div class="w-6 h-6 rounded-full bg-linear-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-xs font-bold text-white shrink-0 mt-0.5">
                                        {{ userInitial }}
                                    </div>
                                    <form class="flex-1 flex gap-2" @submit.prevent="createComment(post.id)">
                                        <input
                                            v-model="commentInputs[post.id]"
                                            type="text"
                                            placeholder="Write a comment..."
                                            class="flex-1 px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent"
                                        />
                                        <button
                                            type="submit"
                                            :disabled="!commentInputs[post.id]?.trim()"
                                            class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-40"
                                        >
                                            Send
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Empty posts -->
                <div v-else class="bg-white border border-gray-200 rounded-2xl p-12 text-center shadow-sm">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-gray-700 mb-1">No posts yet</p>
                    <p class="text-xs text-gray-500">Be the first to post!</p>
                </div>
            </div>

            <!-- Sidebar — Skool-style info card -->
            <div class="space-y-4">
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                    <!-- Cover image / gradient -->
                    <div class="h-32 overflow-hidden">
                        <img
                            v-if="community.cover_image"
                            :src="community.cover_image"
                            :alt="community.name"
                            class="w-full h-full object-cover"
                        />
                        <div
                            v-else
                            class="w-full h-full bg-linear-to-br from-indigo-500 to-purple-700"
                        />
                    </div>

                    <div class="p-4">
                        <!-- Name -->
                        <h3 class="font-bold text-gray-900 text-base mb-1">{{ community.name }}</h3>

                        <!-- Description -->
                        <p v-if="community.description" class="text-sm text-gray-500 mb-4 leading-relaxed">
                            {{ community.description }}
                        </p>

                        <!-- Stats row -->
                        <div class="flex items-center divide-x divide-gray-100 mb-4">
                            <div class="flex-1 text-center pr-3">
                                <p class="text-lg font-black text-gray-900">{{ formatCount(community.members_count) }}</p>
                                <p class="text-xs text-gray-500">Members</p>
                            </div>
                            <div class="flex-1 text-center px-3">
                                <p class="text-lg font-black text-gray-900">
                                    {{ community.price > 0 ? `₱${Number(community.price).toLocaleString()}` : '—' }}
                                </p>
                                <p class="text-xs text-gray-500">{{ community.price > 0 ? '/month' : 'Free' }}</p>
                            </div>
                            <div class="flex-1 text-center pl-3">
                                <p class="text-lg font-black text-gray-900">{{ community.is_private ? '🔒' : '🌐' }}</p>
                                <p class="text-xs text-gray-500">{{ community.is_private ? 'Private' : 'Public' }}</p>
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <div v-if="!isMember" class="space-y-2">
                            <button
                                v-if="!community.price"
                                @click="join"
                                :disabled="joinForm.processing"
                                class="w-full py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors disabled:opacity-50"
                            >
                                {{ joinForm.processing ? 'Joining...' : 'Join for free' }}
                            </button>
                            <button
                                v-else
                                @click="checkout"
                                :disabled="checkoutForm.processing"
                                class="w-full py-2.5 bg-amber-500 text-white text-sm font-bold rounded-xl hover:bg-amber-600 transition-colors disabled:opacity-50"
                            >
                                {{ checkoutForm.processing ? 'Redirecting...' : `Join · ₱${Number(community.price).toLocaleString()}/mo` }}
                            </button>
                        </div>

                        <!-- Member actions -->
                        <div v-else class="flex gap-2">
                            <Link
                                v-if="isOwner"
                                :href="`/communities/${community.slug}/settings`"
                                class="flex-1 text-center py-2 text-xs font-medium border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Settings
                            </Link>
                            <Link
                                v-if="isAdmin"
                                :href="`/communities/${community.slug}/analytics`"
                                class="flex-1 text-center py-2 text-xs font-medium border border-gray-200 text-indigo-600 rounded-lg hover:bg-indigo-50 transition-colors"
                            >
                                📊 Analytics
                            </Link>
                            <Link
                                :href="`/communities/${community.slug}/members`"
                                class="flex-1 text-center py-2 text-xs font-medium border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Members
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    community: Object,
    membership: Object,
});

const page = usePage();

const isMember    = computed(() => !!props.membership);
const isOwner     = computed(() => props.community.owner_id === page.props.auth?.user?.id);
const isAdmin     = computed(() => isOwner.value || props.membership?.role === 'admin');
const userInitial = computed(() => page.props.auth?.user?.name?.charAt(0)?.toUpperCase() ?? '?');

// ─── Join ──────────────────────────────────────────────────────────────────────

const joinForm = useForm({});
function join() {
    joinForm.post(`/communities/${props.community.slug}/join`);
}

// ─── Checkout ─────────────────────────────────────────────────────────────────

const checkoutForm = useForm({});
function checkout() {
    checkoutForm.post(`/communities/${props.community.slug}/checkout`);
}

// ─── Posts ────────────────────────────────────────────────────────────────────

const postForm = useForm({ title: '', content: '' });

function createPost() {
    postForm.post(`/communities/${props.community.slug}/posts`, {
        onSuccess: () => postForm.reset(),
    });
}

function deletePost(post) {
    if (confirm('Delete this post?')) {
        router.delete(`/posts/${post.id}`);
    }
}

function canDeletePost(post) {
    const userId = page.props.auth?.user?.id;
    return userId && (
        post.user_id === userId ||
        props.membership?.role === 'admin' ||
        props.membership?.role === 'moderator'
    );
}

// ─── Comments ─────────────────────────────────────────────────────────────────

const openComments  = ref(new Set());
const commentInputs = reactive({});

function toggleComments(postId) {
    const next = new Set(openComments.value);
    next.has(postId) ? next.delete(postId) : next.add(postId);
    openComments.value = next;
}

function createComment(postId) {
    const content = commentInputs[postId]?.trim();
    if (!content) return;
    router.post(`/posts/${postId}/comments`, { content }, {
        onSuccess: () => { commentInputs[postId] = ''; },
        preserveScroll: true,
    });
}

function deleteComment(comment) {
    router.delete(`/comments/${comment.id}`, { preserveScroll: true });
}

function canDeleteComment(comment) {
    const userId = page.props.auth?.user?.id;
    return userId && (
        comment.user_id === userId ||
        props.membership?.role === 'admin' ||
        props.membership?.role === 'moderator'
    );
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}

function formatCount(n) {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(n);
}
</script>
