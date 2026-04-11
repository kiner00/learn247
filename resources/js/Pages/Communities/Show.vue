<template>
    <AppLayout :title="community.name" :community="community">
        <CommunityTabs :community="community" active-tab="community" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- ── Posts feed ──────────────────────────────────────────────── -->
            <div class="lg:col-span-2 space-y-4 order-2 lg:order-1">
                <!-- Compose box -->
                <PostComposer
                    v-if="isMember"
                    :community-slug="community.slug"
                    :auth-user="page.props.auth?.user"
                />

                <!-- Post list -->
                <template v-if="community.posts?.length">
                    <PostCard
                        v-for="post in community.posts"
                        :key="post.id"
                        :post="post"
                        :is-admin="isAdmin"
                        :current-user-id="page.props.auth?.user?.id"
                        :can-delete="canDeletePost(post)"
                        :is-member="isMember"
                        :auth-user="page.props.auth?.user"
                        @open="openPost"
                        @delete="deletePost"
                        @toggle-pin="togglePin"
                        @react="togglePostReaction"
                        @lightbox="lightboxImg = $event"
                    />
                </template>

                <!-- Empty -->
                <div
                    v-else
                    class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-12 text-center shadow-sm"
                >
                    <div
                        class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center mx-auto mb-3"
                    >
                        <svg
                            class="w-6 h-6 text-indigo-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                            />
                        </svg>
                    </div>
                    <p
                        class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                    >
                        No posts yet
                    </p>
                    <p class="text-xs text-gray-500">Be the first to post!</p>
                </div>
            </div>

            <!-- ── Sidebar ──────────────────────────────────────────────────── -->
            <div class="space-y-4 order-1 lg:order-2">
                <!-- Community card -->
                <CommunitySidebarCard
                    :community="community"
                    :admin-count="adminCount"
                    :is-member="isMember"
                >

                        <!-- Milestone plaque -->
                        <div
                            v-if="getMilestone(community.members_count)"
                            class="flex justify-center mb-3"
                        >
                            <span
                                class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1 rounded-full border"
                                :class="
                                    getMilestone(community.members_count)
                                        .classes
                                "
                            >
                                {{ getMilestone(community.members_count).icon }}
                                {{
                                    getMilestone(community.members_count).label
                                }}
                            </span>
                        </div>

                        <!-- Join / member buttons -->
                        <div v-if="!isMember" class="space-y-2">
                            <button
                                v-if="!(community.price > 0)"
                                @click="join"
                                :disabled="joinForm.processing"
                                class="w-full py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors disabled:opacity-50"
                            >
                                {{
                                    joinForm.processing
                                        ? "Joining..."
                                        : "Join for free"
                                }}
                            </button>
                            <template v-else>
                                <button
                                    @click="checkout"
                                    :disabled="checkoutForm.processing"
                                    class="w-full py-2.5 bg-amber-500 text-white text-sm font-bold rounded-xl hover:bg-amber-600 transition-colors disabled:opacity-50"
                                >
                                    {{
                                        checkoutForm.processing
                                            ? "Redirecting..."
                                            : `Join · ₱${Number(community.price).toLocaleString()}/mo`
                                    }}
                                </button>
                                <button
                                    v-if="hasFreeCourses"
                                    @click="freeSubscribe"
                                    :disabled="freeSubscribeForm.processing"
                                    class="w-full py-2.5 bg-green-600 text-white text-sm font-bold rounded-xl hover:bg-green-700 transition-colors disabled:opacity-50"
                                >
                                    {{
                                        freeSubscribeForm.processing
                                            ? "Subscribing..."
                                            : "Subscribe for Free"
                                    }}
                                </button>
                            </template>
                        </div>

                        <div v-else class="space-y-2">
                            <button
                                @click="showInviteModal = true"
                                class="w-full py-2 text-sm font-bold border border-gray-300 dark:border-gray-600 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors uppercase tracking-wide"
                            >
                                Invite People
                            </button>
                            <div v-if="isOwner || isAdmin" class="flex gap-2">
                                <Link
                                    v-if="isOwner"
                                    :href="`/communities/${community.slug}/settings`"
                                    class="flex-1 text-center py-1.5 text-xs font-medium border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                >
                                    Settings
                                </Link>
                                <Link
                                    v-if="isOwner"
                                    :href="`/communities/${community.slug}/landing`"
                                    class="flex-1 text-center py-1.5 text-xs font-medium border border-indigo-200 dark:border-indigo-700 text-indigo-600 dark:text-indigo-400 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors"
                                >
                                    ✨ Landing Page
                                </Link>
                                <Link
                                    v-if="isAdmin"
                                    :href="`/communities/${community.slug}/analytics`"
                                    class="flex-1 text-center py-1.5 text-xs font-medium border border-gray-200 dark:border-gray-600 text-indigo-600 dark:text-indigo-400 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors"
                                >
                                    📊 Analytics
                                </Link>
                                <Link
                                    :href="`/communities/${community.slug}/members`"
                                    class="flex-1 text-center py-1.5 text-xs font-medium border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                >
                                    Members
                                </Link>
                            </div>
                        </div>

                        <!-- Affiliate -->
                        <template
                            v-if="
                                isMember &&
                                !isOwner &&
                                community.affiliate_commission_rate
                            "
                        >
                            <div
                                class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700"
                            >
                                <p
                                    class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    🔗 Affiliate Program
                                    <span class="font-normal text-gray-400 ml-1"
                                        >{{
                                            community.affiliate_commission_rate
                                        }}% commission</span
                                    >
                                </p>
                                <div v-if="affiliate">
                                    <!-- About page link -->
                                    <p class="text-xs text-gray-400 mb-1">
                                        About page
                                    </p>
                                    <div class="flex items-center gap-2 mb-2">
                                        <input
                                            :value="affiliateUrl"
                                            readonly
                                            class="flex-1 text-xs px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 dark:text-gray-100 font-mono"
                                        />
                                        <button
                                            @click="copyAffiliateUrl('about')"
                                            class="shrink-0 text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                        >
                                            {{
                                                affiliateCopied === "about"
                                                    ? "✓"
                                                    : "Copy"
                                            }}
                                        </button>
                                    </div>
                                    <!-- Landing page link (only if landing page exists) -->
                                    <template v-if="hasLandingPage">
                                        <p class="text-xs text-gray-400 mb-1">
                                            Landing page
                                        </p>
                                        <div
                                            class="flex items-center gap-2 mb-2"
                                        >
                                            <input
                                                :value="affiliateLandingUrl"
                                                readonly
                                                class="flex-1 text-xs px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 dark:text-gray-100 font-mono"
                                            />
                                            <button
                                                @click="
                                                    copyAffiliateUrl('landing')
                                                "
                                                class="shrink-0 text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                            >
                                                {{
                                                    affiliateCopied ===
                                                    "landing"
                                                        ? "✓"
                                                        : "Copy"
                                                }}
                                            </button>
                                        </div>
                                    </template>
                                    <Link
                                        href="/my-affiliates"
                                        class="text-xs text-gray-400 hover:text-indigo-600"
                                        >View my earnings →</Link
                                    >
                                </div>
                                <button
                                    v-else
                                    @click="joinAffiliate"
                                    :disabled="affiliateForm.processing"
                                    class="w-full py-2 border border-indigo-200 text-indigo-600 text-xs font-semibold rounded-xl hover:bg-indigo-50 transition-colors disabled:opacity-50"
                                >
                                    {{
                                        affiliateForm.processing
                                            ? "Joining..."
                                            : "Become an Affiliate"
                                    }}
                                </button>
                                <p
                                    v-if="affiliateForm.errors.affiliate"
                                    class="mt-1.5 text-xs text-red-500"
                                >
                                    {{ affiliateForm.errors.affiliate }}
                                </p>
                            </div>
                        </template>
                </CommunitySidebarCard>

                <!-- Recent Comments widget -->
                <div
                    v-if="isMember && recentComments?.length"
                    class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden shadow-sm"
                >
                    <div
                        class="px-4 py-3 border-b border-gray-100 dark:border-gray-700"
                    >
                        <h4
                            class="text-sm font-bold text-gray-900 dark:text-gray-100"
                        >
                            Recent Comments
                        </h4>
                    </div>
                    <div
                        class="divide-y divide-gray-50 dark:divide-gray-700/50"
                    >
                        <div
                            v-for="comment in recentComments"
                            :key="comment.id"
                            class="px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors cursor-pointer"
                            @click="openPost(comment.post)"
                        >
                            <div class="flex items-center gap-2 mb-1">
                                <UserAvatar
                                    :name="comment.author?.name"
                                    :avatar="comment.author?.avatar"
                                    size="5"
                                />
                                <span
                                    class="text-xs font-semibold text-gray-800 dark:text-gray-100 truncate"
                                    >{{ comment.author?.name }}</span
                                >
                            </div>
                            <p
                                v-if="comment.post?.title"
                                class="text-[11px] text-indigo-500 truncate mb-0.5"
                            >
                                {{ comment.post.title }}
                            </p>
                            <p
                                class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2"
                            >
                                {{ comment.content }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Leaderboard widget -->
                <div
                    v-if="isMember && topMembers?.length"
                    class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden shadow-sm"
                >
                    <div
                        class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between"
                    >
                        <h4
                            class="text-sm font-bold text-gray-900 dark:text-gray-100"
                        >
                            Leaderboard
                        </h4>
                        <Link
                            :href="`/communities/${community.slug}/leaderboard`"
                            class="text-xs text-indigo-500 hover:text-indigo-700 font-medium"
                        >
                            See all leaderboards
                        </Link>
                    </div>
                    <div
                        class="divide-y divide-gray-50 dark:divide-gray-700/50"
                    >
                        <div
                            v-for="(member, i) in topMembers"
                            :key="member.user_id"
                            class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
                        >
                            <!-- Rank medal -->
                            <span class="w-6 text-center shrink-0 text-base">
                                <template v-if="i === 0">🥇</template>
                                <template v-else-if="i === 1">🥈</template>
                                <template v-else-if="i === 2">🥉</template>
                                <span
                                    v-else
                                    class="text-xs font-bold text-gray-400"
                                    >{{ i + 1 }}</span
                                >
                            </span>
                            <!-- Avatar -->
                            <UserAvatar
                                :name="member.name"
                                :avatar="member.avatar"
                                size="7"
                            />
                            <!-- Name + points -->
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-xs font-semibold text-gray-800 dark:text-gray-100 truncate"
                                >
                                    {{ member.name }}
                                </p>
                                <p class="text-xs text-gray-400">
                                    {{ member.points.toLocaleString() }} pts
                                </p>
                            </div>
                            <!-- Level badge -->
                            <span
                                class="text-xs font-bold px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 shrink-0"
                            >
                                Lv {{ member.level }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Owner onboarding checklist -->
                <div
                    v-if="
                        checklist &&
                        !checklistDismissed &&
                        checklistDone < checklist.length
                    "
                    class="bg-white dark:bg-gray-800 border border-indigo-200 dark:border-indigo-700 rounded-2xl overflow-hidden shadow-sm"
                >
                    <div
                        class="px-4 py-3 border-b border-indigo-100 dark:border-indigo-800 flex items-center justify-between"
                    >
                        <div>
                            <h4
                                class="text-sm font-bold text-gray-900 dark:text-gray-100"
                            >
                                Setup checklist
                            </h4>
                            <p class="text-xs text-gray-400">
                                {{ checklistDone }}/{{
                                    checklist.length
                                }}
                                complete
                            </p>
                        </div>
                        <button
                            @click="checklistDismissed = true"
                            class="text-gray-300 hover:text-gray-500 text-lg leading-none"
                        >
                            ×
                        </button>
                    </div>
                    <!-- Progress bar -->
                    <div class="h-1 bg-gray-100 dark:bg-gray-700">
                        <div
                            class="h-full bg-indigo-500 transition-all duration-500"
                            :style="{
                                width: `${Math.round((checklistDone / checklist.length) * 100)}%`,
                            }"
                        />
                    </div>
                    <ul class="divide-y divide-gray-50 dark:divide-gray-700/50">
                        <li
                            v-for="item in checklist"
                            :key="item.key"
                            class="flex items-center gap-3 px-4 py-2.5"
                        >
                            <span
                                class="w-4 h-4 shrink-0 flex items-center justify-center rounded-full text-xs"
                                :class="
                                    item.done
                                        ? 'bg-green-100 text-green-600'
                                        : 'bg-gray-100 text-gray-400'
                                "
                            >
                                <svg
                                    v-if="item.done"
                                    class="w-3 h-3"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="3"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    class="w-3 h-3"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="9"
                                        stroke-width="2"
                                    />
                                </svg>
                            </span>
                            <span
                                class="text-xs"
                                :class="
                                    item.done
                                        ? 'text-gray-400 line-through'
                                        : 'text-gray-700 dark:text-gray-300'
                                "
                            >
                                {{ item.label }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ─── Auth Prompt Modal ───────────────────────────────────────────── -->
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
                    v-if="showAuthPrompt"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                    @click.self="showAuthPrompt = false"
                >
                    <div
                        class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center"
                    >
                        <div
                            class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center mx-auto mb-4"
                        >
                            <svg
                                class="w-6 h-6 text-indigo-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-gray-900 mb-1">
                            Join {{ community.name }}
                        </h3>
                        <p class="text-sm text-gray-500 mb-5">
                            Create an account or log in to continue.
                        </p>
                        <div class="flex gap-3">
                            <Link
                                :href="`/login?redirect=/communities/${community.slug}`"
                                class="flex-1 py-2.5 border border-gray-300 text-gray-700 text-sm font-semibold rounded-xl hover:bg-gray-50 transition-colors text-center"
                            >
                                Log in
                            </Link>
                            <Link
                                :href="`/register?redirect=/communities/${community.slug}`"
                                class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors text-center"
                            >
                                Sign up
                            </Link>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ─── Invite Modal ─────────────────────────────────────────────────── -->
        <InviteModal
            :show="showInviteModal"
            :community-name="community.name"
            :community-slug="community.slug"
            :invite-url="inviteUrl"
            :is-owner="isOwner"
            @close="showInviteModal = false"
        />

        <!-- ─── Post Modal ───────────────────────────────────────────────────── -->
        <PostModal
            :post="activePost"
            :is-member="isMember"
            :can-delete-post="canDeletePost(activePost)"
            :auth-user="page.props.auth?.user"
            :current-user-id="page.props.auth?.user?.id"
            :membership-role="membership?.role"
            @close="closeModal"
            @lightbox="lightboxImg = $event"
            @react-post="togglePostReaction"
            @react-comment="toggleCommentReaction"
            @delete-post="deletePost"
            @delete-comment="deleteComment"
        />

        <!-- Image lightbox -->
        <Teleport to="body">
            <div
                v-if="lightboxImg"
                class="fixed inset-0 z-[60] bg-black/85 flex items-center justify-center p-4"
                @click="lightboxImg = null"
            >
                <img
                    :src="lightboxImg"
                    class="max-w-full max-h-full rounded-xl shadow-2xl"
                    @click.stop
                />
            </div>
        </Teleport>
        <ConfirmModal :show="confirmShow" :title="confirmTitle" :message="confirmMessage" :confirm-label="confirmLabel" :destructive="confirmDestructive" @confirm="onConfirm" @cancel="onCancel" />
    </AppLayout>
</template>

<script setup>
import { ref, computed } from "vue";
import { Link, useForm, usePage, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import CommunityTabs from "@/Components/CommunityTabs.vue";
import CommunitySidebarCard from "@/Components/CommunitySidebarCard.vue";
import InviteModal from "@/Components/InviteModal.vue";
import UserAvatar from "@/Components/UserAvatar.vue";
import PostComposer from "@/Components/Community/PostComposer.vue";
import PostCard from "@/Components/Community/PostCard.vue";
import PostModal from "@/Components/Community/PostModal.vue";
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useConfirm } from '@/composables/useConfirm';

const { show: confirmShow, title: confirmTitle, message: confirmMessage, confirmLabel, destructive: confirmDestructive, ask, onConfirm, onCancel } = useConfirm();

const props = defineProps({
    community: Object,
    membership: Object,
    affiliate: Object,
    adminCount: { type: Number, default: 0 },
    topMembers: { type: Array, default: () => [] },
    checklist: { type: Array, default: null },
    recentComments: { type: Array, default: () => [] },
    hasFreeCourses: { type: Boolean, default: false },
    hasLandingPage: { type: Boolean, default: false },
});

const page = usePage();

const isMember = computed(() => !!props.membership || isOwner.value);
const isOwner = computed(
    () => props.community.owner_id === page.props.auth?.user?.id,
);
const isAdmin = computed(
    () => isOwner.value || props.membership?.role === "admin",
);

// ─── Post modal ───────────────────────────────────────────────────────────────
const activePostId = ref(null);
const lightboxImg = ref(null);

const activePost = computed(
    () =>
        props.community.posts?.find((p) => p.id === activePostId.value) ?? null,
);

function openPost(post) {
    activePostId.value = post.id;
    document.body.style.overflow = "hidden";
}

function closeModal() {
    activePostId.value = null;
    document.body.style.overflow = "";
}

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
    freeSubscribeForm.post(
        `/communities/${props.community.slug}/free-subscribe`,
    );
}

// ─── Posts ────────────────────────────────────────────────────────────────────
async function deletePost(post) {
    if (await ask({ title: 'Delete post', message: 'Delete this post?', confirmLabel: 'Delete', destructive: true })) {
        router.delete(`/posts/${post.id}`, { preserveScroll: true });
    }
}

function togglePin(post) {
    router.post(`/posts/${post.id}/pin`, {}, { preserveScroll: true });
}

function canDeletePost(post) {
    if (!post) return false;
    const userId = page.props.auth?.user?.id;
    return (
        userId &&
        (post.user_id === userId ||
            props.membership?.role === "admin" ||
            props.membership?.role === "moderator")
    );
}

// ─── Reactions ────────────────────────────────────────────────────────────────
function togglePostReaction(post, type) {
    if (!page.props.auth?.user) return;
    router.post(
        `/posts/${post.id}/like`,
        { type },
        { preserveScroll: true, preserveState: true },
    );
}

function toggleCommentReaction(comment, type) {
    if (!page.props.auth?.user) return;
    router.post(
        `/comments/${comment.id}/like`,
        { type },
        { preserveScroll: true, preserveState: true },
    );
}

// ─── Comments ─────────────────────────────────────────────────────────────────
function deleteComment(comment) {
    router.delete(`/comments/${comment.id}`, { preserveScroll: true });
}

// ─── Invite ───────────────────────────────────────────────────────────────────
const showInviteModal = ref(false);
const inviteUrl = computed(() =>
    props.affiliate?.code
        ? `${window.location.origin}/ref/${props.affiliate.code}`
        : `${window.location.origin}/communities/${props.community.slug}`,
);

// ─── Affiliate ────────────────────────────────────────────────────────────────
const affiliateForm = useForm({});
const affiliateCopied = ref(null); // 'about' | 'landing' | null
const affiliateUrl = computed(() =>
    props.affiliate
        ? `${window.location.origin}/ref/${props.affiliate.code}`
        : "",
);
const affiliateLandingUrl = computed(() =>
    props.affiliate
        ? `${window.location.origin}/communities/${props.community.slug}/landing?ref=${props.affiliate.code}`
        : "",
);

function joinAffiliate() {
    affiliateForm.post(`/communities/${props.community.slug}/affiliates`);
}

async function copyAffiliateUrl(type = "about") {
    const url =
        type === "landing" ? affiliateLandingUrl.value : affiliateUrl.value;
    await navigator.clipboard.writeText(url);
    affiliateCopied.value = type;
    setTimeout(() => {
        affiliateCopied.value = null;
    }, 2000);
}

// ─── Checklist ────────────────────────────────────────────────────────────────
const checklistDismissed = ref(false);
const checklistDone = computed(
    () => props.checklist?.filter((c) => c.done).length ?? 0,
);

// ─── Helpers ──────────────────────────────────────────────────────────────────
function getMilestone(count) {
    if (count >= 100_000)
        return {
            icon: "🌟",
            label: "100K Plaque",
            classes: "bg-yellow-50 border-yellow-300 text-yellow-700",
        };
    if (count >= 50_000)
        return {
            icon: "🏆",
            label: "Platinum",
            classes: "bg-slate-100 border-slate-400 text-slate-700",
        };
    if (count >= 10_000)
        return {
            icon: "💎",
            label: "Diamond",
            classes: "bg-cyan-50 border-cyan-300 text-cyan-700",
        };
    if (count >= 1_000)
        return {
            icon: "🥇",
            label: "Gold",
            classes: "bg-amber-50 border-amber-300 text-amber-700",
        };
    if (count >= 500)
        return {
            icon: "🥈",
            label: "Silver",
            classes: "bg-gray-100 border-gray-400 text-gray-600",
        };
    if (count >= 100)
        return {
            icon: "🥉",
            label: "Bronze",
            classes: "bg-orange-50 border-orange-300 text-orange-700",
        };
    return null;
}
</script>
