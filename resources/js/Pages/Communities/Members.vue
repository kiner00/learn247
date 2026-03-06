<template>
    <AppLayout :title="`${community.name} · Members`" :community="community">
        <CommunityTabs :community="community" active-tab="members" />

        <div class="flex gap-6 items-start">

            <!-- ── Main column ─────────────────────────────────────────── -->
            <div class="flex-1 min-w-0">

                <!-- Filter tabs + Invite -->
                <div class="flex items-center justify-between mb-5">
                    <div class="flex gap-2">
                        <Link
                            :href="`/communities/${community.slug}/members`"
                            class="px-4 py-1.5 text-sm rounded-full font-medium border transition-colors"
                            :class="!currentFilter
                                ? 'bg-gray-900 text-white border-gray-900'
                                : 'bg-white text-gray-600 border-gray-200 hover:border-gray-400'"
                        >
                            Members <span class="ml-1 opacity-70">{{ totalCount }}</span>
                        </Link>
                        <Link
                            :href="`/communities/${community.slug}/members?filter=admin`"
                            class="px-4 py-1.5 text-sm rounded-full font-medium border transition-colors"
                            :class="currentFilter === 'admin'
                                ? 'bg-gray-900 text-white border-gray-900'
                                : 'bg-white text-gray-600 border-gray-200 hover:border-gray-400'"
                        >
                            Admins <span class="ml-1 opacity-70">{{ adminCount }}</span>
                        </Link>
                        <span class="px-4 py-1.5 text-sm rounded-full font-medium border border-gray-200 bg-white text-gray-400 cursor-default">
                            Online <span class="ml-1">0</span>
                        </span>
                    </div>

                    <button
                        v-if="isAdmin"
                        @click="showInviteModal = true"
                        class="px-4 py-1.5 text-sm font-semibold rounded-full bg-yellow-400 hover:bg-yellow-500 text-gray-900 transition-colors"
                    >
                        Invite
                    </button>
                </div>

                <!-- Member list -->
                <div class="divide-y divide-gray-100 bg-white border border-gray-200 rounded-2xl overflow-hidden">
                    <div
                        v-for="member in members.data"
                        :key="member.id"
                        class="flex items-start gap-4 px-5 py-4 hover:bg-gray-50 transition-colors"
                    >
                        <!-- Avatar + level badge -->
                        <div class="relative shrink-0">
                            <div
                                class="w-12 h-12 rounded-full flex items-center justify-center text-lg font-bold"
                                :class="avatarColor(member.user?.name)"
                            >
                                {{ member.user?.name?.charAt(0)?.toUpperCase() }}
                            </div>
                            <span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-indigo-600 text-white text-[10px] font-bold flex items-center justify-center ring-2 ring-white">
                                {{ computeLevel(member.points ?? 0) }}
                            </span>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-900 text-sm leading-tight">{{ member.user?.name }}</p>
                            <p class="text-xs text-gray-400 mb-1">@{{ member.user?.username ?? `user${member.user?.id}` }}</p>
                            <p v-if="member.user?.bio" class="text-sm text-gray-600 mb-2">{{ member.user.bio }}</p>

                            <div class="flex items-center gap-3 text-xs text-gray-400 flex-wrap">
                                <span>Joined {{ formatDate(member.joined_at) }}</span>
                                <span class="text-gray-200">·</span>
                                <span class="font-medium text-indigo-500">{{ member.points ?? 0 }} pts</span>
                                <span class="text-gray-200">·</span>
                                <span
                                    class="px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="{
                                        'bg-indigo-100 text-indigo-700': member.role === 'admin',
                                        'bg-purple-100 text-purple-700': member.role === 'moderator',
                                        'bg-gray-100 text-gray-500':     member.role === 'member',
                                    }"
                                >
                                    {{ member.role }}
                                </span>
                            </div>
                        </div>

                        <!-- Right actions -->
                        <div class="flex items-center gap-2 shrink-0 pt-1">
                            <!-- Chat placeholder -->
                            <button class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-full text-gray-500 hover:border-gray-400 hover:text-gray-700 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                Chat
                            </button>

                            <!-- Admin role change + remove -->
                            <template v-if="isAdmin && member.user?.id !== community.owner_id">
                                <select
                                    :value="member.role"
                                    @change="changeRole(member, $event.target.value)"
                                    class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                                >
                                    <option value="member">Member</option>
                                    <option value="moderator">Moderator</option>
                                    <option value="admin">Admin</option>
                                </select>
                                <button
                                    @click="removeMember(member)"
                                    class="text-xs text-gray-400 hover:text-red-500 transition-colors"
                                >
                                    Remove
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-if="!members.data.length" class="text-center py-16">
                        <p class="text-sm text-gray-500">No members found.</p>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="members.last_page > 1" class="mt-5 flex justify-center gap-2">
                    <Link
                        v-for="link in members.links"
                        :key="link.label"
                        :href="link.url ?? ''"
                        v-html="link.label"
                        class="px-3 py-1.5 text-sm rounded-lg border transition-colors"
                        :class="link.active
                            ? 'bg-indigo-600 text-white border-indigo-600'
                            : link.url
                                ? 'border-gray-200 text-gray-600 hover:border-indigo-300'
                                : 'border-gray-100 text-gray-300 cursor-default'"
                    />
                </div>
            </div>

            <!-- ── Right sidebar ────────────────────────────────────────── -->
            <div class="w-72 shrink-0 space-y-4">
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                    <!-- Cover image -->
                    <div class="h-32 bg-gray-900 flex items-center justify-center overflow-hidden">
                        <img
                            v-if="community.cover_image"
                            :src="community.cover_image"
                            :alt="community.name"
                            class="w-full h-full object-cover"
                        />
                        <span v-else class="text-3xl font-black text-white opacity-20">
                            {{ community.name.charAt(0).toUpperCase() }}
                        </span>
                    </div>

                    <div class="p-4">
                        <h2 class="font-bold text-gray-900 text-sm">{{ community.name }}</h2>
                        <p class="text-xs text-gray-400 mt-0.5 mb-3">curzzo.com/communities/{{ community.slug }}</p>
                        <p v-if="community.description" class="text-sm text-gray-600 mb-4 line-clamp-3">{{ community.description }}</p>

                        <!-- Stats -->
                        <div class="flex justify-around text-center border-t border-gray-100 pt-3 mb-4">
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ totalCount }}</p>
                                <p class="text-xs text-gray-400">Members</p>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-900">0</p>
                                <p class="text-xs text-gray-400">Online</p>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ adminCount }}</p>
                                <p class="text-xs text-gray-400">Admin</p>
                            </div>
                        </div>

                        <!-- Invite button -->
                        <button
                            v-if="isAdmin"
                            @click="showInviteModal = true"
                            class="w-full py-2 text-sm font-semibold border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Invite People
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <InviteModal
            :show="showInviteModal"
            :community-name="community.name"
            :invite-url="inviteUrl"
            @close="showInviteModal = false"
        />
    </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import InviteModal from '@/Components/InviteModal.vue';

const props = defineProps({
    community:  Object,
    members:    Object,
    totalCount: Number,
    adminCount: Number,
    affiliate:  Object,
});

const page = usePage();

const currentFilter = computed(() => {
    const url = new URL(window.location.href);
    return url.searchParams.get('filter') ?? null;
});

const currentUserId = computed(() => page.props.auth?.user?.id);

const showInviteModal = ref(false);

const inviteUrl = computed(() =>
    props.affiliate?.code
        ? `${window.location.origin}/ref/${props.affiliate.code}`
        : `${window.location.origin}/communities/${props.community.slug}`
);

const isOwner = computed(() => currentUserId.value === props.community.owner_id);

const isAdmin = computed(() => {
    const me = props.members.data.find((m) => m.user?.id === currentUserId.value);
    return me?.role === 'admin' || isOwner.value;
});

const avatarColors = [
    'bg-indigo-100 text-indigo-600',
    'bg-violet-100 text-violet-600',
    'bg-pink-100 text-pink-600',
    'bg-emerald-100 text-emerald-600',
    'bg-amber-100 text-amber-600',
    'bg-sky-100 text-sky-600',
];

function avatarColor(name) {
    if (!name) return avatarColors[0];
    return avatarColors[name.charCodeAt(0) % avatarColors.length];
}

function changeRole(member, role) {
    router.patch(
        `/communities/${props.community.slug}/members/${member.user.id}/role`,
        { role },
        { preserveScroll: true },
    );
}

function removeMember(member) {
    if (!confirm(`Remove ${member.user?.name} from the community?`)) return;
    router.delete(
        `/communities/${props.community.slug}/members/${member.user.id}`,
        { preserveScroll: true },
    );
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}

const LEVEL_THRESHOLDS = [0, 50, 150, 350, 700, 1250, 2000, 3000, 4500, 6500, 9000, 12500];

function computeLevel(points) {
    for (let i = LEVEL_THRESHOLDS.length - 1; i >= 0; i--) {
        if (points >= LEVEL_THRESHOLDS[i]) return i + 1;
    }
    return 1;
}
</script>
