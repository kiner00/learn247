<template>
    <AppLayout :title="community.name" :community="community">

        <CommunityTabs :community="community" active-tab="community" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ── Posts feed ──────────────────────────────────────────────── -->
            <div class="lg:col-span-2 space-y-4">

                <!-- Compose box -->
                <div v-if="isMember" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm">
                    <!-- Collapsed -->
                    <div v-if="!composing" class="flex items-center gap-3 px-4 py-3 cursor-text" @click="composing = true">
                        <UserAvatar :name="page.props.auth?.user?.name" :avatar="page.props.auth?.user?.avatar" size="8" />
                        <span class="text-sm text-gray-400 flex-1">Write something...</span>
                    </div>
                    <!-- Expanded -->
                    <form v-else @submit.prevent="createPost" class="p-4 space-y-2">
                        <div class="flex items-start gap-3">
                            <UserAvatar :name="page.props.auth?.user?.name" :avatar="page.props.auth?.user?.avatar" size="8" class="mt-0.5" />
                            <div class="flex-1 space-y-2">
                                <input v-model="postForm.title" type="text" placeholder="Title (optional)" autofocus
                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <!-- Formatting toolbar -->
                                <div class="flex items-center gap-1 px-1">
                                    <button type="button" @click="applyBold"
                                        class="px-2 py-0.5 text-xs font-bold border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300"
                                        title="Bold (**text**)">B</button>
                                    <button type="button" @click="applyItalic"
                                        class="px-2 py-0.5 text-xs italic border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300"
                                        title="Italic (_text_)">I</button>
                                    <button type="button" @click="applyBullet"
                                        class="px-2 py-0.5 text-xs border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300"
                                        title="Bullet list (- item)">• List</button>
                                </div>
                                <textarea id="post-content-editor" v-model="postForm.content" rows="4" placeholder="Share something with the community..." required
                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none font-mono"
                                    :class="postForm.errors.content ? 'border-red-400' : ''" />

                                <!-- Image preview -->
                                <div v-if="postImagePreview" class="relative inline-block">
                                    <img :src="postImagePreview" class="h-32 rounded-xl object-cover border border-gray-200" />
                                    <button type="button" @click="removePostImage"
                                        class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center leading-none">✕</button>
                                </div>

                                <!-- Video URL input -->
                                <div v-if="showVideoInput" class="flex items-center gap-2">
                                    <input v-model="postForm.video_url" type="url" placeholder="Paste YouTube or Vimeo link..."
                                        class="flex-1 px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                    <button type="button" @click="showVideoInput = false; postForm.video_url = ''"
                                        class="text-gray-400 hover:text-red-500 text-sm">✕</button>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <!-- Media buttons -->
                            <div class="flex items-center gap-1 pl-12">
                                <input ref="postImageInput" type="file" accept="image/*" class="hidden" @change="onPostImageChange" />
                                <button type="button" @click="postImageInput.click()"
                                    class="flex items-center gap-1 px-3 py-1.5 text-xs text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg transition-colors"
                                    title="Attach image">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 15l-5-5L5 21"/></svg>
                                    Photo
                                </button>
                                <button type="button" @click="showVideoInput = !showVideoInput"
                                    class="flex items-center gap-1 px-3 py-1.5 text-xs text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg transition-colors"
                                    title="Add video link">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
                                    Video
                                </button>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="composing = false; postForm.reset(); removePostImage(); showVideoInput = false"
                                    class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 rounded-xl">Cancel</button>
                                <button type="submit" :disabled="postForm.processing || !postForm.content.trim()"
                                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-40 transition-colors">
                                    {{ postForm.processing ? 'Posting...' : 'Post' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Post list -->
                <template v-if="community.posts?.length">
                    <div v-for="post in community.posts" :key="post.id"
                        class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-sm hover:border-indigo-200 dark:hover:border-indigo-700 transition-colors cursor-pointer"
                        @click="openPost(post)"
                    >
                        <!-- Author row -->
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-2.5">
                                <UserAvatar :name="post.author?.name" :avatar="post.author?.avatar" size="9" />
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-tight">{{ post.author?.name }}</p>
                                        <span v-if="post.is_pinned" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 leading-none">📌 Pinned</span>
                                    </div>
                                    <p class="text-xs text-gray-400">{{ formatDate(post.created_at) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <button v-if="isAdmin" @click.stop="togglePin(post)"
                                    class="text-xs px-2 py-1 rounded transition-colors"
                                    :class="post.is_pinned ? 'text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20' : 'text-gray-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20'"
                                    :title="post.is_pinned ? 'Unpin post' : 'Pin post'">
                                    📌
                                </button>
                                <button v-if="post.user_id === page.props.auth?.user?.id" @click.stop="startEdit(post)"
                                    class="text-xs text-gray-400 hover:text-indigo-500 transition-colors px-2 py-1 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                                    Edit
                                </button>
                                <button v-if="canDeletePost(post)" @click.stop="deletePost(post)"
                                    class="text-xs text-gray-400 hover:text-red-500 transition-colors px-2 py-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20">
                                    Delete
                                </button>
                            </div>
                        </div>

                        <!-- Inline edit form -->
                        <form v-if="editingPostId === post.id" @submit.prevent="submitEdit(post)" @click.stop class="space-y-2 mb-3">
                            <input v-model="editForm.title" type="text" placeholder="Title (optional)"
                                class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <textarea v-model="editForm.content" rows="4" required
                                class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
                            <div class="flex gap-2 justify-end">
                                <button type="button" @click.stop="cancelEdit()"
                                    class="px-3 py-1.5 text-xs rounded-lg border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="editForm.processing"
                                    class="px-3 py-1.5 text-xs rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                                    Save
                                </button>
                            </div>
                        </form>

                        <!-- Content -->
                        <template v-else>
                            <h3 v-if="post.title" class="font-bold text-gray-900 dark:text-gray-100 mb-1.5">{{ post.title }}</h3>
                            <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed line-clamp-4 prose prose-sm max-w-none" v-html="mdToHtml(post.content)" />
                        </template>

                        <!-- Post image -->
                        <img v-if="post.image" :src="post.image" @click.stop="lightboxImg = post.image"
                            class="mt-3 rounded-xl max-h-72 w-full object-cover cursor-pointer hover:opacity-95 transition-opacity" />

                        <!-- Post video embed -->
                        <div v-if="post.video_url && getVideoEmbed(post.video_url)" class="mt-3 rounded-xl overflow-hidden aspect-video" @click.stop>
                            <iframe :src="getVideoEmbed(post.video_url)" class="w-full h-full" frameborder="0" allowfullscreen />
                        </div>

                        <!-- Reaction bar -->
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center gap-4">
                            <!-- Reactions -->
                            <div class="flex items-center gap-1">
                                <button v-for="r in REACTIONS" :key="r.type"
                                    @click.stop="togglePostReaction(post, r.type)"
                                    class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs transition-colors"
                                    :class="post.user_reaction === r.type
                                        ? 'bg-indigo-50 text-indigo-700 font-semibold dark:bg-indigo-900/30 dark:text-indigo-300'
                                        : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                    :title="r.label">
                                    <span>{{ r.emoji }}</span>
                                    <span v-if="post.reactions?.[r.type]">{{ post.reactions[r.type] }}</span>
                                </button>
                            </div>
                            <button class="flex items-center gap-1.5 text-xs text-gray-500 hover:text-indigo-500 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                {{ post.comments_count ?? 0 }}
                            </button>

                            <!-- Commenter avatars + last comment -->
                            <div v-if="post.commenter_avatars?.length" class="flex items-center gap-2 ml-auto">
                                <div class="flex -space-x-1.5">
                                    <div v-for="(c, i) in post.commenter_avatars" :key="i"
                                        class="w-5 h-5 rounded-full ring-2 ring-white dark:ring-gray-800 shrink-0 overflow-hidden bg-indigo-100 flex items-center justify-center">
                                        <img v-if="c.avatar" :src="c.avatar" :alt="c.name" class="w-full h-full object-cover" />
                                        <span v-else class="text-indigo-600 font-bold text-[8px]">{{ c.name?.charAt(0)?.toUpperCase() }}</span>
                                    </div>
                                </div>
                                <span v-if="post.last_comment_at" class="text-xs text-gray-400">
                                    Last comment {{ formatRelative(post.last_comment_at) }}
                                </span>
                            </div>
                        </div>

                        <!-- Latest 3 comments preview -->
                        <div v-if="post.comments?.length" class="mt-3 space-y-2 border-t border-gray-100 dark:border-gray-700 pt-3" @click.stop>
                            <div v-for="comment in post.comments.slice(0, 3)" :key="comment.id" class="flex gap-2.5 items-start">
                                <div class="w-6 h-6 rounded-full shrink-0 overflow-hidden bg-indigo-100 flex items-center justify-center ring-1 ring-gray-200 dark:ring-gray-700">
                                    <img v-if="comment.author?.avatar" :src="comment.author.avatar" :alt="comment.author.name" class="w-full h-full object-cover" />
                                    <span v-else class="text-indigo-600 font-bold text-[9px]">{{ comment.author?.name?.charAt(0)?.toUpperCase() }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="text-xs font-semibold text-gray-800 dark:text-gray-100 mr-1.5">{{ comment.author?.name }}</span>
                                    <span class="text-xs text-gray-600 dark:text-gray-300 break-words">{{ comment.content }}</span>
                                </div>
                            </div>
                            <button v-if="post.comments_count > 3" @click.stop="openPost(post)"
                                class="text-xs text-indigo-500 hover:text-indigo-700 font-medium transition-colors">
                                View all {{ post.comments_count }} comments
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Empty -->
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

            <!-- ── Sidebar ──────────────────────────────────────────────────── -->
            <div class="space-y-4">

                <!-- Community card -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden shadow-sm">
                    <div class="h-40 overflow-hidden">
                        <img v-if="community.cover_image" :src="community.cover_image" :alt="community.name" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full bg-linear-to-br from-indigo-500 to-purple-700" />
                    </div>

                    <div class="p-4">
                        <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base mb-0.5">{{ community.name }}</h3>
                        <p class="text-xs text-gray-400 mb-2">curzzo.com/communities/{{ community.slug }}</p>
                        <p v-if="community.description" class="text-sm text-gray-500 dark:text-gray-400 mb-4 leading-relaxed">{{ community.description }}</p>

                        <!-- Stats (members only) -->
                        <div v-if="isMember" class="flex items-center justify-around text-center border-y border-gray-100 dark:border-gray-700 py-3 mb-4">
                            <div>
                                <p class="text-base font-black text-gray-900 dark:text-gray-100">{{ formatCount(community.members_count) }}</p>
                                <p class="text-xs text-gray-400">Members</p>
                            </div>
                            <div>
                                <p class="text-base font-black text-gray-900 dark:text-gray-100">0</p>
                                <p class="text-xs text-gray-400">Online</p>
                            </div>
                            <div>
                                <p class="text-base font-black text-gray-900 dark:text-gray-100">{{ adminCount }}</p>
                                <p class="text-xs text-gray-400">{{ adminCount === 1 ? 'Admin' : 'Admins' }}</p>
                            </div>
                        </div>
                        <div v-else class="border-y border-gray-100 dark:border-gray-700 py-3 mb-4 text-center">
                            <p class="text-xs text-gray-400">Join to see member stats</p>
                        </div>

                        <!-- Milestone plaque -->
                        <div v-if="getMilestone(community.members_count)" class="flex justify-center mb-3">
                            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1 rounded-full border"
                                :class="getMilestone(community.members_count).classes">
                                {{ getMilestone(community.members_count).icon }} {{ getMilestone(community.members_count).label }}
                            </span>
                        </div>

                        <!-- Join / member buttons -->
                        <div v-if="!isMember" class="space-y-2">
                            <button v-if="!(community.price > 0)" @click="join" :disabled="joinForm.processing"
                                class="w-full py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors disabled:opacity-50">
                                {{ joinForm.processing ? 'Joining...' : 'Join for free' }}
                            </button>
                            <template v-else>
                                <button @click="checkout" :disabled="checkoutForm.processing"
                                    class="w-full py-2.5 bg-amber-500 text-white text-sm font-bold rounded-xl hover:bg-amber-600 transition-colors disabled:opacity-50">
                                    {{ checkoutForm.processing ? 'Redirecting...' : `Join · ₱${Number(community.price).toLocaleString()}/mo` }}
                                </button>
                                <button v-if="hasFreeCourses" @click="freeSubscribe" :disabled="freeSubscribeForm.processing"
                                    class="w-full py-2.5 bg-green-600 text-white text-sm font-bold rounded-xl hover:bg-green-700 transition-colors disabled:opacity-50">
                                    {{ freeSubscribeForm.processing ? 'Subscribing...' : 'Subscribe for Free' }}
                                </button>
                            </template>
                        </div>

                        <div v-else class="space-y-2">
                            <button @click="showInviteModal = true"
                                class="w-full py-2 text-sm font-bold border border-gray-300 dark:border-gray-600 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors uppercase tracking-wide">
                                Invite People
                            </button>
                            <div v-if="isOwner || isAdmin" class="flex gap-2">
                                <Link v-if="isOwner" :href="`/communities/${community.slug}/settings`"
                                    class="flex-1 text-center py-1.5 text-xs font-medium border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    Settings
                                </Link>
                                <Link v-if="isOwner" :href="`/communities/${community.slug}/landing`"
                                    class="flex-1 text-center py-1.5 text-xs font-medium border border-indigo-200 dark:border-indigo-700 text-indigo-600 dark:text-indigo-400 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors">
                                    ✨ Landing Page
                                </Link>
                                <Link v-if="isAdmin" :href="`/communities/${community.slug}/analytics`"
                                    class="flex-1 text-center py-1.5 text-xs font-medium border border-gray-200 dark:border-gray-600 text-indigo-600 dark:text-indigo-400 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors">
                                    📊 Analytics
                                </Link>
                                <Link :href="`/communities/${community.slug}/members`"
                                    class="flex-1 text-center py-1.5 text-xs font-medium border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    Members
                                </Link>
                            </div>
                        </div>

                        <!-- Affiliate -->
                        <template v-if="isMember && !isOwner && community.affiliate_commission_rate">
                            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    🔗 Affiliate Program
                                    <span class="font-normal text-gray-400 ml-1">{{ community.affiliate_commission_rate }}% commission</span>
                                </p>
                                <div v-if="affiliate">
                                    <!-- About page link -->
                                    <p class="text-xs text-gray-400 mb-1">About page</p>
                                    <div class="flex items-center gap-2 mb-2">
                                        <input :value="affiliateUrl" readonly
                                            class="flex-1 text-xs px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 dark:text-gray-100 font-mono" />
                                        <button @click="copyAffiliateUrl('about')" class="shrink-0 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                            {{ affiliateCopied === 'about' ? '✓' : 'Copy' }}
                                        </button>
                                    </div>
                                    <!-- Landing page link (only if landing page exists) -->
                                    <template v-if="hasLandingPage">
                                        <p class="text-xs text-gray-400 mb-1">Landing page</p>
                                        <div class="flex items-center gap-2 mb-2">
                                            <input :value="affiliateLandingUrl" readonly
                                                class="flex-1 text-xs px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 dark:text-gray-100 font-mono" />
                                            <button @click="copyAffiliateUrl('landing')" class="shrink-0 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                                {{ affiliateCopied === 'landing' ? '✓' : 'Copy' }}
                                            </button>
                                        </div>
                                    </template>
                                    <Link href="/my-affiliates" class="text-xs text-gray-400 hover:text-indigo-600">View my earnings →</Link>
                                </div>
                                <button v-else @click="joinAffiliate" :disabled="affiliateForm.processing"
                                    class="w-full py-2 border border-indigo-200 text-indigo-600 text-xs font-semibold rounded-xl hover:bg-indigo-50 transition-colors disabled:opacity-50">
                                    {{ affiliateForm.processing ? 'Joining...' : 'Become an Affiliate' }}
                                </button>
                                <p v-if="affiliateForm.errors.affiliate" class="mt-1.5 text-xs text-red-500">
                                    {{ affiliateForm.errors.affiliate }}
                                </p>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Recent Comments widget -->
                <div v-if="isMember && recentComments?.length" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden shadow-sm">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100">Recent Comments</h4>
                    </div>
                    <div class="divide-y divide-gray-50 dark:divide-gray-700/50">
                        <div v-for="comment in recentComments" :key="comment.id"
                            class="px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors cursor-pointer"
                            @click="openPost(comment.post)"
                        >
                            <div class="flex items-center gap-2 mb-1">
                                <UserAvatar :name="comment.author?.name" :avatar="comment.author?.avatar" size="5" />
                                <span class="text-xs font-semibold text-gray-800 dark:text-gray-100 truncate">{{ comment.author?.name }}</span>
                            </div>
                            <p v-if="comment.post?.title" class="text-[11px] text-indigo-500 truncate mb-0.5">{{ comment.post.title }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ comment.content }}</p>
                        </div>
                    </div>
                </div>

                <!-- Leaderboard widget -->
                <div v-if="isMember && topMembers?.length" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden shadow-sm">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100">Leaderboard</h4>
                        <Link :href="`/communities/${community.slug}/leaderboard`"
                            class="text-xs text-indigo-500 hover:text-indigo-700 font-medium">
                            See all leaderboards
                        </Link>
                    </div>
                    <div class="divide-y divide-gray-50 dark:divide-gray-700/50">
                        <div v-for="(member, i) in topMembers" :key="member.user_id"
                            class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <!-- Rank medal -->
                            <span class="w-6 text-center shrink-0 text-base">
                                <template v-if="i === 0">🥇</template>
                                <template v-else-if="i === 1">🥈</template>
                                <template v-else-if="i === 2">🥉</template>
                                <span v-else class="text-xs font-bold text-gray-400">{{ i + 1 }}</span>
                            </span>
                            <!-- Avatar -->
                            <UserAvatar :name="member.name" :avatar="member.avatar" size="7" />
                            <!-- Name + points -->
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-gray-800 dark:text-gray-100 truncate">{{ member.name }}</p>
                                <p class="text-xs text-gray-400">{{ member.points.toLocaleString() }} pts</p>
                            </div>
                            <!-- Level badge -->
                            <span class="text-xs font-bold px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 shrink-0">
                                Lv {{ member.level }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Owner onboarding checklist -->
                <div v-if="checklist && !checklistDismissed && checklistDone < checklist.length"
                    class="bg-white dark:bg-gray-800 border border-indigo-200 dark:border-indigo-700 rounded-2xl overflow-hidden shadow-sm">
                    <div class="px-4 py-3 border-b border-indigo-100 dark:border-indigo-800 flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100">Setup checklist</h4>
                            <p class="text-xs text-gray-400">{{ checklistDone }}/{{ checklist.length }} complete</p>
                        </div>
                        <button @click="checklistDismissed = true" class="text-gray-300 hover:text-gray-500 text-lg leading-none">×</button>
                    </div>
                    <!-- Progress bar -->
                    <div class="h-1 bg-gray-100 dark:bg-gray-700">
                        <div class="h-full bg-indigo-500 transition-all duration-500"
                            :style="{ width: `${Math.round(checklistDone / checklist.length * 100)}%` }" />
                    </div>
                    <ul class="divide-y divide-gray-50 dark:divide-gray-700/50">
                        <li v-for="item in checklist" :key="item.key"
                            class="flex items-center gap-3 px-4 py-2.5">
                            <span class="w-4 h-4 shrink-0 flex items-center justify-center rounded-full text-xs"
                                :class="item.done ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400'">
                                <svg v-if="item.done" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                                <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <circle cx="12" cy="12" r="9" stroke-width="2"/>
                                </svg>
                            </span>
                            <span class="text-xs" :class="item.done ? 'text-gray-400 line-through' : 'text-gray-700 dark:text-gray-300'">
                                {{ item.label }}
                            </span>
                        </li>
                    </ul>
                </div>

            </div>
        </div>

        <!-- ─── Auth Prompt Modal ───────────────────────────────────────────── -->
        <Teleport to="body">
            <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showAuthPrompt" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="showAuthPrompt = false">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-gray-900 mb-1">Join {{ community.name }}</h3>
                        <p class="text-sm text-gray-500 mb-5">Create an account or log in to continue.</p>
                        <div class="flex gap-3">
                            <Link :href="`/login?redirect=/communities/${community.slug}`"
                                class="flex-1 py-2.5 border border-gray-300 text-gray-700 text-sm font-semibold rounded-xl hover:bg-gray-50 transition-colors text-center">
                                Log in
                            </Link>
                            <Link :href="`/register?redirect=/communities/${community.slug}`"
                                class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors text-center">
                                Sign up
                            </Link>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ─── Invite Modal ─────────────────────────────────────────────────── -->
        <InviteModal :show="showInviteModal" :community-name="community.name" :community-slug="community.slug" :invite-url="inviteUrl" :is-owner="isOwner" @close="showInviteModal = false" />

        <!-- ─── Post Modal ───────────────────────────────────────────────────── -->
        <Teleport to="body">
            <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="activePost"
                    class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-12 bg-black/50 backdrop-blur-sm overflow-y-auto"
                    @click.self="closeModal">
                    <div class="w-full max-w-2xl bg-white dark:bg-gray-900 rounded-2xl shadow-2xl relative" @click.stop>

                        <!-- Header -->
                        <div class="flex items-start justify-between p-5 border-b border-gray-100 dark:border-gray-800">
                            <div class="flex items-center gap-3">
                                <UserAvatar :name="activePost.author?.name" :avatar="activePost.author?.avatar" size="10" />
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

                        <!-- Body -->
                        <div class="p-5">
                            <h2 v-if="activePost.title" class="text-xl font-black text-gray-900 dark:text-gray-100 mb-3">{{ activePost.title }}</h2>
                            <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed prose prose-sm max-w-none" v-html="mdToHtml(activePost.content)" />
                            <!-- Image -->
                            <img v-if="activePost.image" :src="activePost.image" @click="lightboxImg = activePost.image"
                                class="mt-4 rounded-xl w-full object-cover max-h-96 cursor-pointer hover:opacity-95 transition-opacity" />
                            <!-- Video embed -->
                            <div v-if="activePost.video_url && getVideoEmbed(activePost.video_url)" class="mt-4 rounded-xl overflow-hidden aspect-video">
                                <iframe :src="getVideoEmbed(activePost.video_url)" class="w-full h-full" frameborder="0" allowfullscreen />
                            </div>
                        </div>

                        <!-- Reaction bar -->
                        <div class="px-5 pb-4 flex items-center gap-5 border-b border-gray-100 dark:border-gray-800">
                            <div class="flex items-center gap-1">
                                <button v-for="r in REACTIONS" :key="r.type"
                                    @click="togglePostReaction(activePost, r.type)"
                                    class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-sm font-medium transition-colors"
                                    :class="activePost.user_reaction === r.type
                                        ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                        : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                    :title="r.label">
                                    <span>{{ r.emoji }}</span>
                                    <span>{{ activePost.reactions?.[r.type] || 0 }} {{ r.label }}</span>
                                </button>
                            </div>
                            <span class="flex items-center gap-2 text-sm text-gray-500">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                {{ activePost.comments?.length ?? 0 }} Comments
                            </span>
                            <button v-if="canDeletePost(activePost)" @click="deletePost(activePost); closeModal()"
                                class="ml-auto text-xs text-gray-400 hover:text-red-500 transition-colors">Delete post</button>
                        </div>

                        <!-- Comments -->
                        <div class="p-5 space-y-4 max-h-[50vh] overflow-y-auto">
                            <div v-if="!activePost.comments?.length" class="text-center py-8 text-sm text-gray-400">No comments yet. Be the first!</div>
                            <div v-for="comment in activePost.comments" :key="comment.id">
                                <div class="flex gap-3">
                                    <UserAvatar :name="comment.author?.name" :avatar="comment.author?.avatar" size="8" class="mt-0.5 shrink-0" />
                                    <div class="flex-1 min-w-0">
                                        <div class="bg-gray-50 dark:bg-gray-800 rounded-xl px-4 py-3">
                                            <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-0.5">{{ comment.author?.name }}</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ comment.content }}</p>
                                        </div>
                                        <div class="flex items-center gap-3 mt-1.5 ml-1">
                                            <p class="text-xs text-gray-400">{{ formatRelative(comment.created_at) }}</p>
                                            <div class="flex items-center gap-0.5">
                                                <button v-for="r in REACTIONS" :key="r.type"
                                                    @click="toggleCommentReaction(comment, r.type)"
                                                    class="flex items-center gap-0.5 px-1.5 py-0.5 rounded text-xs transition-colors"
                                                    :class="comment.user_reaction === r.type
                                                        ? 'bg-indigo-50 text-indigo-700 font-medium dark:bg-indigo-900/30'
                                                        : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                                    :title="r.label">
                                                    <span>{{ r.emoji }}</span>
                                                    <span v-if="comment.reactions?.[r.type]">{{ comment.reactions[r.type] }}</span>
                                                </button>
                                            </div>
                                            <button v-if="isMember" @click="setReplyTarget(comment)"
                                                class="text-xs text-gray-400 hover:text-indigo-500 font-medium transition-colors">Reply</button>
                                            <button v-if="canDeleteComment(comment)" @click="deleteComment(comment)"
                                                class="text-xs text-gray-400 hover:text-red-500 transition-colors ml-auto">Delete</button>
                                        </div>
                                        <!-- Replies -->
                                        <div v-if="comment.replies?.length" class="mt-3 space-y-3 ml-2 pl-3 border-l-2 border-gray-100 dark:border-gray-700">
                                            <div v-for="reply in comment.replies" :key="reply.id" class="flex gap-2.5">
                                                <UserAvatar :name="reply.author?.name" :avatar="reply.author?.avatar" size="7" class="mt-0.5 shrink-0" />
                                                <div class="flex-1 min-w-0">
                                                    <div class="bg-gray-50 dark:bg-gray-800 rounded-xl px-3 py-2.5">
                                                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-0.5">{{ reply.author?.name }}</p>
                                                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ reply.content }}</p>
                                                    </div>
                                                    <div class="flex items-center gap-3 mt-1 ml-1">
                                                        <p class="text-xs text-gray-400">{{ formatRelative(reply.created_at) }}</p>
                                                        <div class="flex items-center gap-0.5">
                                                            <button v-for="r in REACTIONS" :key="r.type"
                                                                @click="toggleCommentReaction(reply, r.type)"
                                                                class="flex items-center gap-0.5 px-1.5 py-0.5 rounded text-xs transition-colors"
                                                                :class="reply.user_reaction === r.type
                                                                    ? 'bg-indigo-50 text-indigo-700 font-medium dark:bg-indigo-900/30'
                                                                    : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                                                :title="r.label">
                                                                <span>{{ r.emoji }}</span>
                                                                <span v-if="reply.reactions?.[r.type]">{{ reply.reactions[r.type] }}</span>
                                                            </button>
                                                        </div>
                                                        <button v-if="canDeleteComment(reply)" @click="deleteComment(reply)"
                                                            class="text-xs text-gray-400 hover:text-red-500 transition-colors ml-auto">Delete</button>
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
                            <div v-if="replyTarget" class="flex items-center gap-2 text-xs text-indigo-600 dark:text-indigo-400 mb-2 bg-indigo-50 dark:bg-indigo-900/20 px-3 py-1.5 rounded-lg">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                </svg>
                                Replying to <span class="font-semibold">{{ replyTarget.author?.name }}</span>
                                <button @click="replyTarget = null" class="ml-auto text-gray-400 hover:text-gray-600">✕</button>
                            </div>
                            <div class="flex gap-3 items-end">
                                <UserAvatar :name="page.props.auth?.user?.name" :avatar="page.props.auth?.user?.avatar" size="8" class="shrink-0" />
                                <div class="flex-1 flex gap-2">
                                    <textarea v-model="modalCommentInput" rows="1"
                                        :placeholder="replyTarget ? `Reply to ${replyTarget.author?.name}...` : 'Write a comment...'"
                                        class="flex-1 px-3.5 py-2.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"
                                        @keydown.enter.exact.prevent="submitModalComment" />
                                    <button @click="submitModalComment" :disabled="!modalCommentInput.trim()"
                                        class="px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors disabled:opacity-40 self-end">
                                        Post
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Image lightbox -->
        <Teleport to="body">
            <div v-if="lightboxImg" class="fixed inset-0 z-[60] bg-black/85 flex items-center justify-center p-4" @click="lightboxImg = null">
                <img :src="lightboxImg" class="max-w-full max-h-full rounded-xl shadow-2xl" @click.stop />
            </div>
        </Teleport>

    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import InviteModal from '@/Components/InviteModal.vue';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    community:      Object,
    membership:     Object,
    affiliate:      Object,
    adminCount:     { type: Number, default: 0 },
    topMembers:     { type: Array, default: () => [] },
    checklist:      { type: Array, default: null },
    recentComments:  { type: Array, default: () => [] },
    hasFreeCourses:  { type: Boolean, default: false },
    hasLandingPage:  { type: Boolean, default: false },
});

const page = usePage();

const isMember = computed(() => !!props.membership || isOwner.value);
const isOwner  = computed(() => props.community.owner_id === page.props.auth?.user?.id);
const isAdmin  = computed(() => isOwner.value || props.membership?.role === 'admin');

// ─── Compose ──────────────────────────────────────────────────────────────────
const composing = ref(false);

// ─── Post modal ───────────────────────────────────────────────────────────────
const activePostId      = ref(null);
const modalCommentInput = ref('');
const replyTarget       = ref(null);

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

function setReplyTarget(comment) { replyTarget.value = comment; }

// ─── Join / Checkout ──────────────────────────────────────────────────────────
const showAuthPrompt = ref(false);

function requireAuth() {
    if (!page.props.auth?.user) {
        showAuthPrompt.value = true;
        return false;
    }
    return true;
}

const joinForm = useForm({});
function join() {
    if (!requireAuth()) return;
    joinForm.post(`/communities/${props.community.slug}/join`);
}

const checkoutForm = useForm({});
function checkout() {
    if (!requireAuth()) return;
    checkoutForm.post(`/communities/${props.community.slug}/checkout`);
}

const freeSubscribeForm = useForm({});
function freeSubscribe() {
    if (!requireAuth()) return;
    freeSubscribeForm.post(`/communities/${props.community.slug}/free-subscribe`);
}

// ─── Posts ────────────────────────────────────────────────────────────────────
const editingPostId = ref(null);
const editForm      = useForm({ title: '', content: '' });

function startEdit(post) {
    editingPostId.value = post.id;
    editForm.title   = post.title ?? '';
    editForm.content = post.content ?? '';
}

function cancelEdit() {
    editingPostId.value = null;
    editForm.reset();
}

function submitEdit(post) {
    editForm.patch(`/posts/${post.id}`, {
        preserveScroll: true,
        onSuccess: () => { editingPostId.value = null; },
    });
}

const postForm         = useForm({ title: '', content: '', image: null, video_url: '' });
const postImagePreview = ref(null);
const postImageInput   = ref(null);
const showVideoInput   = ref(false);
const lightboxImg      = ref(null);

function onPostImageChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    postForm.image = file;
    postImagePreview.value = URL.createObjectURL(file);
}

function removePostImage() {
    postForm.image = null;
    postImagePreview.value = null;
    if (postImageInput.value) postImageInput.value.value = '';
}

function getVideoEmbed(url) {
    try {
        const u = new URL(url);
        if (u.hostname.includes('youtube.com') || u.hostname.includes('youtu.be')) {
            const id = u.searchParams.get('v') || u.pathname.split('/').pop();
            return id ? `https://www.youtube.com/embed/${id}` : null;
        }
        if (u.hostname.includes('vimeo.com')) {
            const id = u.pathname.split('/').pop();
            return id ? `https://player.vimeo.com/video/${id}` : null;
        }
    } catch {}
    return null;
}

function createPost() {
    postForm.post(`/communities/${props.community.slug}/posts`, {
        onSuccess: () => {
            postForm.reset();
            removePostImage();
            showVideoInput.value = false;
            composing.value = false;
        },
        preserveScroll: true,
        forceFormData: true,
    });
}

function deletePost(post) {
    if (confirm('Delete this post?')) {
        router.delete(`/posts/${post.id}`, { preserveScroll: true });
    }
}

function togglePin(post) {
    router.post(`/posts/${post.id}/pin`, {}, { preserveScroll: true });
}

function canDeletePost(post) {
    const userId = page.props.auth?.user?.id;
    return userId && (post.user_id === userId || props.membership?.role === 'admin' || props.membership?.role === 'moderator');
}

// ─── Reactions ────────────────────────────────────────────────────────────────
const REACTIONS = [
    { type: 'like',      emoji: '👍',  label: 'Like'              },
    { type: 'handshake', emoji: '🤝',  label: 'Helpful'           },
    { type: 'trophy',    emoji: '🏆',  label: 'Solution Accepted' },
];

function togglePostReaction(post, type) {
    if (!page.props.auth?.user) return;
    router.post(`/posts/${post.id}/like`, { type }, { preserveScroll: true, preserveState: true });
}

function toggleCommentReaction(comment, type) {
    if (!page.props.auth?.user) return;
    router.post(`/comments/${comment.id}/like`, { type }, { preserveScroll: true, preserveState: true });
}

// ─── Comments ─────────────────────────────────────────────────────────────────
function submitModalComment() {
    const content = modalCommentInput.value.trim();
    if (!content || !activePost.value) return;
    router.post(`/posts/${activePost.value.id}/comments`, {
        content,
        parent_id: replyTarget.value?.id ?? null,
    }, {
        onSuccess: () => { modalCommentInput.value = ''; replyTarget.value = null; },
        preserveScroll: true,
    });
}

function deleteComment(comment) {
    router.delete(`/comments/${comment.id}`, { preserveScroll: true });
}

function canDeleteComment(comment) {
    const userId = page.props.auth?.user?.id;
    return userId && (comment.user_id === userId || props.membership?.role === 'admin' || props.membership?.role === 'moderator');
}

// ─── Invite ───────────────────────────────────────────────────────────────────
const showInviteModal = ref(false);
const inviteUrl = computed(() =>
    props.affiliate?.code
        ? `${window.location.origin}/ref/${props.affiliate.code}`
        : `${window.location.origin}/communities/${props.community.slug}`
);

// ─── Affiliate ────────────────────────────────────────────────────────────────
const affiliateForm        = useForm({});
const affiliateCopied      = ref(null); // 'about' | 'landing' | null
const affiliateUrl         = computed(() =>
    props.affiliate ? `${window.location.origin}/ref/${props.affiliate.code}` : ''
);
const affiliateLandingUrl  = computed(() =>
    props.affiliate ? `${window.location.origin}/communities/${props.community.slug}/landing?ref=${props.affiliate.code}` : ''
);

function joinAffiliate() { affiliateForm.post(`/communities/${props.community.slug}/affiliates`); }

async function copyAffiliateUrl(type = 'about') {
    const url = type === 'landing' ? affiliateLandingUrl.value : affiliateUrl.value;
    await navigator.clipboard.writeText(url);
    affiliateCopied.value = type;
    setTimeout(() => { affiliateCopied.value = null; }, 2000);
}

// ─── Checklist ────────────────────────────────────────────────────────────────
const checklistDismissed = ref(false);
const checklistDone = computed(() =>
    props.checklist?.filter(c => c.done).length ?? 0
);

// ─── Markdown renderer ────────────────────────────────────────────────────────
function mdToHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/_(.*?)_/g, '<em>$1</em>')
        .replace(/^- (.+)$/gm, '<li>$1</li>')
        .replace(/(<li>.*<\/li>)/s, '<ul class="list-disc pl-4 space-y-0.5">$1</ul>')
        .replace(/\n/g, '<br>');
}

// ─── Rich text formatting helpers ─────────────────────────────────────────────
function wrapSelection(textarea, before, after) {
    const start = textarea.selectionStart;
    const end   = textarea.selectionEnd;
    const sel   = textarea.value.substring(start, end);
    const replacement = before + (sel || 'text') + after;
    const newVal = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    return { newVal, cursor: start + replacement.length };
}

function applyBold() {
    const el = document.getElementById('post-content-editor');
    if (!el) return;
    const { newVal, cursor } = wrapSelection(el, '**', '**');
    postForm.content = newVal;
    el.focus();
    el.setSelectionRange(cursor, cursor);
}

function applyItalic() {
    const el = document.getElementById('post-content-editor');
    if (!el) return;
    const { newVal, cursor } = wrapSelection(el, '_', '_');
    postForm.content = newVal;
    el.focus();
    el.setSelectionRange(cursor, cursor);
}

function applyBullet() {
    const el = document.getElementById('post-content-editor');
    if (!el) return;
    const start = el.selectionStart;
    const lineStart = postForm.content.lastIndexOf('\n', start - 1) + 1;
    const newVal = postForm.content.substring(0, lineStart) + '- ' + postForm.content.substring(lineStart);
    postForm.content = newVal;
    el.focus();
    el.setSelectionRange(start + 2, start + 2);
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
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

function getMilestone(count) {
    if (count >= 100_000)   return { icon: '🌟', label: '100K Plaque', classes: 'bg-yellow-50 border-yellow-300 text-yellow-700' };
    if (count >= 50_000)    return { icon: '🏆', label: 'Platinum',    classes: 'bg-slate-100 border-slate-400 text-slate-700' };
    if (count >= 10_000)    return { icon: '💎', label: 'Diamond',     classes: 'bg-cyan-50 border-cyan-300 text-cyan-700' };
    if (count >= 1_000)     return { icon: '🥇', label: 'Gold',        classes: 'bg-amber-50 border-amber-300 text-amber-700' };
    if (count >= 500)       return { icon: '🥈', label: 'Silver',      classes: 'bg-gray-100 border-gray-400 text-gray-600' };
    if (count >= 100)       return { icon: '🥉', label: 'Bronze',      classes: 'bg-orange-50 border-orange-300 text-orange-700' };
    return null;
}
</script>
