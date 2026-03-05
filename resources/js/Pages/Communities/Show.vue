<template>
    <AppLayout :title="community.name" :community="community">

        <CommunityTabs :community="community" active-tab="community" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Posts feed (2/3) -->
            <div class="lg:col-span-2 space-y-4">

                <!-- Community title + meta row -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl px-5 py-4 shadow-sm">
                    <h1 class="text-lg font-black text-gray-900 dark:text-gray-100 mb-2">{{ community.name }}</h1>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
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
                <div v-if="isMember" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-linear-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-xs font-bold text-white shrink-0">
                            {{ userInitial }}
                        </div>
                        <form class="flex-1" @submit.prevent="createPost">
                            <input
                                v-model="postForm.title"
                                type="text"
                                placeholder="Post title (optional)"
                                class="w-full px-3.5 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent mb-2"
                            />
                            <textarea
                                v-model="postForm.content"
                                rows="3"
                                placeholder="Share something with the community..."
                                required
                                class="w-full px-3.5 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none dark:bg-gray-700 dark:text-gray-100"
                                :class="postForm.errors.content ? 'border-red-400' : 'border-gray-200 dark:border-gray-600'"
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
                        class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-sm hover:border-indigo-200 dark:hover:border-indigo-700 transition-colors cursor-pointer"
                        @click="openPost(post)"
                    >
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-full bg-linear-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-xs font-bold text-white shrink-0">
                                    {{ post.author?.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ post.author?.name }}</p>
                                    <p class="text-xs text-gray-400">{{ formatDate(post.created_at) }}</p>
                                </div>
                            </div>
                            <button
                                v-if="canDeletePost(post)"
                                @click.stop="deletePost(post)"
                                class="text-xs text-gray-400 hover:text-red-500 transition-colors px-2 py-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20"
                            >
                                Delete
                            </button>
                        </div>

                        <h3 v-if="post.title" class="font-bold text-gray-900 dark:text-gray-100 mb-1.5">{{ post.title }}</h3>
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed line-clamp-4">{{ post.content }}</p>

                        <!-- Reaction bar -->
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center gap-4">
                            <button
                                @click.stop="togglePostLike(post)"
                                class="flex items-center gap-1.5 text-xs transition-colors"
                                :class="post.user_has_liked ? 'text-indigo-600 font-semibold' : 'text-gray-500 hover:text-indigo-500'"
                            >
                                <svg class="w-4 h-4" :fill="post.user_has_liked ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                                {{ post.likes_count ?? post.likes?.length ?? 0 }}
                            </button>
                            <button
                                class="flex items-center gap-1.5 text-xs text-gray-500 hover:text-indigo-500 transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                {{ post.comments_count ?? post.comments?.length ?? 0 }} comments
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Empty posts -->
                <div v-else class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-12 text-center shadow-sm">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">No posts yet</p>
                    <p class="text-xs text-gray-500">Be the first to post!</p>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden shadow-sm">
                    <div class="h-32 overflow-hidden">
                        <img
                            v-if="community.cover_image"
                            :src="community.cover_image"
                            :alt="community.name"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="w-full h-full bg-linear-to-br from-indigo-500 to-purple-700" />
                    </div>

                    <div class="p-4">
                        <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base mb-1">{{ community.name }}</h3>
                        <p v-if="community.description" class="text-sm text-gray-500 dark:text-gray-400 mb-4 leading-relaxed">
                            {{ community.description }}
                        </p>

                        <div class="flex items-center divide-x divide-gray-100 dark:divide-gray-700 mb-4">
                            <div class="flex-1 text-center pr-3">
                                <p class="text-lg font-black text-gray-900 dark:text-gray-100">{{ formatCount(community.members_count) }}</p>
                                <p class="text-xs text-gray-500">Members</p>
                            </div>
                            <div class="flex-1 text-center px-3">
                                <p class="text-lg font-black text-gray-900 dark:text-gray-100">
                                    {{ community.price > 0 ? `₱${Number(community.price).toLocaleString()}` : '—' }}
                                </p>
                                <p class="text-xs text-gray-500">{{ community.price > 0 ? '/month' : 'Free' }}</p>
                            </div>
                            <div class="flex-1 text-center pl-3">
                                <p class="text-lg font-black text-gray-900 dark:text-gray-100">{{ community.is_private ? '🔒' : '🌐' }}</p>
                                <p class="text-xs text-gray-500">{{ community.is_private ? 'Private' : 'Public' }}</p>
                            </div>
                        </div>

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

                        <div v-else class="flex gap-2">
                            <Link
                                v-if="isOwner"
                                :href="`/communities/${community.slug}/settings`"
                                class="flex-1 text-center py-2 text-xs font-medium border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            >
                                Settings
                            </Link>
                            <Link
                                v-if="isAdmin"
                                :href="`/communities/${community.slug}/analytics`"
                                class="flex-1 text-center py-2 text-xs font-medium border border-gray-200 dark:border-gray-600 text-indigo-600 dark:text-indigo-400 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors"
                            >
                                📊 Analytics
                            </Link>
                            <Link
                                :href="`/communities/${community.slug}/members`"
                                class="flex-1 text-center py-2 text-xs font-medium border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            >
                                Members
                            </Link>
                        </div>

                        <template v-if="isMember && community.affiliate_commission_rate">
                            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    🔗 Affiliate Program
                                    <span class="font-normal text-gray-400 ml-1">{{ community.affiliate_commission_rate }}% commission</span>
                                </p>
                                <div v-if="affiliate">
                                    <div class="flex items-center gap-2 mb-1">
                                        <input
                                            :value="affiliateUrl"
                                            readonly
                                            class="flex-1 text-xs px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 dark:text-gray-100 font-mono"
                                        />
                                        <button @click="copyAffiliateUrl" class="shrink-0 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                            {{ affiliateCopied ? '✓' : 'Copy' }}
                                        </button>
                                    </div>
                                    <Link href="/my-affiliates" class="text-xs text-gray-400 hover:text-indigo-600">
                                        View my earnings →
                                    </Link>
                                </div>
                                <button
                                    v-else
                                    @click="joinAffiliate"
                                    :disabled="affiliateForm.processing"
                                    class="w-full py-2 border border-indigo-200 text-indigo-600 text-xs font-semibold rounded-xl hover:bg-indigo-50 transition-colors disabled:opacity-50"
                                >
                                    {{ affiliateForm.processing ? 'Joining...' : 'Become an Affiliate' }}
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Post Modal ──────────────────────────────────────────────────── -->
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
                    v-if="activePost"
                    class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-12 bg-black/50 backdrop-blur-sm overflow-y-auto"
                    @click.self="closeModal"
                >
                    <div
                        class="w-full max-w-2xl bg-white dark:bg-gray-900 rounded-2xl shadow-2xl relative"
                        @click.stop
                    >
                        <!-- Modal header -->
                        <div class="flex items-start justify-between p-5 border-b border-gray-100 dark:border-gray-800">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-linear-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-sm font-bold text-white shrink-0">
                                    {{ activePost.author?.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ activePost.author?.name }}</p>
                                    <p class="text-xs text-gray-400">{{ formatDate(activePost.created_at) }}</p>
                                </div>
                            </div>
                            <button @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Post body -->
                        <div class="p-5">
                            <h2 v-if="activePost.title" class="text-xl font-black text-gray-900 dark:text-gray-100 mb-3">{{ activePost.title }}</h2>
                            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ activePost.content }}</p>
                        </div>

                        <!-- Post reaction bar -->
                        <div class="px-5 pb-4 flex items-center gap-5 border-b border-gray-100 dark:border-gray-800">
                            <button
                                @click="togglePostLike(activePost)"
                                class="flex items-center gap-2 text-sm font-medium transition-colors"
                                :class="activePost.user_has_liked ? 'text-indigo-600' : 'text-gray-500 hover:text-indigo-500'"
                            >
                                <svg class="w-5 h-5" :fill="activePost.user_has_liked ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                                {{ activePost.likes_count ?? activePost.likes?.length ?? 0 }} {{ activePost.likes_count === 1 ? 'Like' : 'Likes' }}
                            </button>
                            <span class="flex items-center gap-2 text-sm text-gray-500">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                {{ activePost.comments?.length ?? 0 }} Comments
                            </span>
                            <button
                                v-if="canDeletePost(activePost)"
                                @click="deletePost(activePost); closeModal()"
                                class="ml-auto text-xs text-gray-400 hover:text-red-500 transition-colors"
                            >
                                Delete post
                            </button>
                        </div>

                        <!-- Comments -->
                        <div class="p-5 space-y-4 max-h-[50vh] overflow-y-auto">
                            <div v-if="!activePost.comments?.length" class="text-center py-8 text-sm text-gray-400">
                                No comments yet. Be the first!
                            </div>

                            <div v-for="comment in activePost.comments" :key="comment.id">
                                <!-- Top-level comment -->
                                <div class="flex gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-semibold text-gray-600 dark:text-gray-300 shrink-0 mt-0.5">
                                        {{ comment.author?.name?.charAt(0)?.toUpperCase() }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="bg-gray-50 dark:bg-gray-800 rounded-xl px-4 py-3">
                                            <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-0.5">{{ comment.author?.name }}</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ comment.content }}</p>
                                        </div>
                                        <!-- Comment actions -->
                                        <div class="flex items-center gap-3 mt-1.5 ml-1">
                                            <p class="text-xs text-gray-400">{{ formatRelative(comment.created_at) }}</p>
                                            <button
                                                @click="toggleCommentLike(comment)"
                                                class="text-xs font-medium transition-colors"
                                                :class="comment.user_has_liked ? 'text-indigo-600' : 'text-gray-400 hover:text-indigo-500'"
                                            >
                                                {{ comment.user_has_liked ? '♥' : '♡' }} {{ comment.likes_count || '' }}
                                            </button>
                                            <button
                                                v-if="isMember"
                                                @click="setReplyTarget(comment)"
                                                class="text-xs text-gray-400 hover:text-indigo-500 font-medium transition-colors"
                                            >
                                                Reply
                                            </button>
                                            <button
                                                v-if="canDeleteComment(comment)"
                                                @click="deleteComment(comment)"
                                                class="text-xs text-gray-400 hover:text-red-500 transition-colors ml-auto"
                                            >
                                                Delete
                                            </button>
                                        </div>

                                        <!-- Replies -->
                                        <div v-if="comment.replies?.length" class="mt-3 space-y-3 ml-2 pl-3 border-l-2 border-gray-100 dark:border-gray-700">
                                            <div v-for="reply in comment.replies" :key="reply.id" class="flex gap-2.5">
                                                <div class="w-7 h-7 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-semibold text-gray-600 dark:text-gray-300 shrink-0 mt-0.5">
                                                    {{ reply.author?.name?.charAt(0)?.toUpperCase() }}
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="bg-gray-50 dark:bg-gray-800 rounded-xl px-3 py-2.5">
                                                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-0.5">{{ reply.author?.name }}</p>
                                                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ reply.content }}</p>
                                                    </div>
                                                    <div class="flex items-center gap-3 mt-1 ml-1">
                                                        <p class="text-xs text-gray-400">{{ formatRelative(reply.created_at) }}</p>
                                                        <button
                                                            @click="toggleCommentLike(reply)"
                                                            class="text-xs font-medium transition-colors"
                                                            :class="reply.user_has_liked ? 'text-indigo-600' : 'text-gray-400 hover:text-indigo-500'"
                                                        >
                                                            {{ reply.user_has_liked ? '♥' : '♡' }} {{ reply.likes_count || '' }}
                                                        </button>
                                                        <button
                                                            v-if="canDeleteComment(reply)"
                                                            @click="deleteComment(reply)"
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
                        <div v-if="isMember" class="p-4 border-t border-gray-100 dark:border-gray-800">
                            <!-- Reply indicator -->
                            <div v-if="replyTarget" class="flex items-center gap-2 text-xs text-indigo-600 dark:text-indigo-400 mb-2 bg-indigo-50 dark:bg-indigo-900/20 px-3 py-1.5 rounded-lg">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                </svg>
                                Replying to <span class="font-semibold">{{ replyTarget.author?.name }}</span>
                                <button @click="replyTarget = null" class="ml-auto text-gray-400 hover:text-gray-600">✕</button>
                            </div>

                            <div class="flex gap-3 items-end">
                                <div class="w-8 h-8 rounded-full bg-linear-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-xs font-bold text-white shrink-0">
                                    {{ userInitial }}
                                </div>
                                <div class="flex-1 flex gap-2">
                                    <textarea
                                        v-model="modalCommentInput"
                                        rows="1"
                                        :placeholder="replyTarget ? `Reply to ${replyTarget.author?.name}...` : 'Write a comment...'"
                                        class="flex-1 px-3.5 py-2.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent resize-none"
                                        @keydown.enter.exact.prevent="submitModalComment"
                                    />
                                    <button
                                        @click="submitModalComment"
                                        :disabled="!modalCommentInput.trim()"
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

    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';

const props = defineProps({
    community: Object,
    membership: Object,
    affiliate:  Object,
});

const page = usePage();

const isMember    = computed(() => !!props.membership || isOwner.value);
const isOwner     = computed(() => props.community.owner_id === page.props.auth?.user?.id);
const isAdmin     = computed(() => isOwner.value || props.membership?.role === 'admin');
const userInitial = computed(() => page.props.auth?.user?.name?.charAt(0)?.toUpperCase() ?? '?');

// ─── Post modal ────────────────────────────────────────────────────────────────

const activePostId      = ref(null);
const modalCommentInput = ref('');
const replyTarget       = ref(null);

// Always derived from live props so likes/comments refresh instantly
const activePost = computed(() =>
    props.community.posts?.find(p => p.id === activePostId.value) ?? null
);

function openPost(post) {
    activePostId.value      = post.id;
    modalCommentInput.value = '';
    replyTarget.value       = null;
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    activePostId.value = null;
    document.body.style.overflow = '';
}

function setReplyTarget(comment) {
    replyTarget.value = comment;
}

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
        preserveScroll: true,
    });
}

function deletePost(post) {
    if (confirm('Delete this post?')) {
        router.delete(`/posts/${post.id}`, { preserveScroll: true });
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

// ─── Likes ────────────────────────────────────────────────────────────────────

function togglePostLike(post) {
    if (!page.props.auth?.user) return;
    router.post(`/posts/${post.id}/like`, {}, {
        preserveScroll: true,
        preserveState:  true,
    });
}

function toggleCommentLike(comment) {
    if (!page.props.auth?.user) return;
    router.post(`/comments/${comment.id}/like`, {}, {
        preserveScroll: true,
        preserveState:  true,
    });
}

// ─── Comments ─────────────────────────────────────────────────────────────────

function submitModalComment() {
    const content = modalCommentInput.value.trim();
    if (!content || !activePost.value) return;

    router.post(`/posts/${activePost.value.id}/comments`, {
        content,
        parent_id: replyTarget.value?.id ?? null,
    }, {
        onSuccess: () => {
            modalCommentInput.value = '';
            replyTarget.value       = null;
        },
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

// ─── Affiliate ────────────────────────────────────────────────────────────────

const affiliateForm   = useForm({});
const affiliateCopied = ref(false);
const affiliateUrl    = computed(() =>
    props.affiliate ? `${window.location.origin}/ref/${props.affiliate.code}` : ''
);

function joinAffiliate() {
    affiliateForm.post(`/communities/${props.community.slug}/affiliates`);
}

async function copyAffiliateUrl() {
    await navigator.clipboard.writeText(affiliateUrl.value);
    affiliateCopied.value = true;
    setTimeout(() => { affiliateCopied.value = false; }, 2000);
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}

function formatRelative(dateStr) {
    if (!dateStr) return '';
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1)   return 'just now';
    if (mins < 60)  return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24)   return `${hrs}h ago`;
    const days = Math.floor(hrs / 24);
    if (days < 7)   return `${days}d ago`;
    return formatDate(dateStr);
}

function formatCount(n) {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(n);
}
</script>
