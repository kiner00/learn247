<template>
    <AppLayout title="Profile">
        <div class="flex gap-6 items-start">

            <!-- ── Left: main content ─────────────────────────────────────── -->
            <div class="flex-1 min-w-0 space-y-5">

                <!-- Activity heatmap -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <h2 class="text-sm font-bold text-gray-900 mb-4">Activity</h2>
                    <div class="overflow-x-auto">
                        <ActivityHeatmap :activity-map="activityMap" />
                    </div>
                </div>

                <!-- Badges -->
                <div v-if="badges?.length" class="bg-white border border-gray-200 rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-gray-900">
                            Badges
                            <span class="text-gray-400 font-normal ml-1">
                                {{ badges.filter(b => b.earned).length }}/{{ badges.length }}
                            </span>
                        </h2>
                    </div>

                    <!-- Member badges -->
                    <div v-if="memberBadges.length">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Member</p>
                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 mb-5">
                            <div
                                v-for="badge in memberBadges"
                                :key="badge.key"
                                class="relative group flex flex-col items-center text-center p-3 rounded-xl border transition-all cursor-default"
                                :class="badge.earned
                                    ? 'bg-white border-indigo-100 shadow-sm'
                                    : 'bg-gray-50 border-gray-100 opacity-40 grayscale'"
                            >
                                <span class="text-2xl mb-1 leading-none">{{ badge.icon }}</span>
                                <p class="text-[11px] font-semibold text-gray-800 leading-tight">{{ badge.name }}</p>
                                <p v-if="badge.earned" class="text-[10px] text-green-600 mt-0.5">✓ Earned</p>
                                <div class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-44 bg-gray-900 text-white text-[11px] rounded-lg px-3 py-2 opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-10 text-left shadow-lg">
                                    <p class="font-semibold mb-0.5">{{ badge.name }}</p>
                                    <p v-if="badge.earned" class="text-green-400">✓ Earned {{ badge.earned_at }}</p>
                                    <p v-else class="text-gray-300 leading-snug">{{ badge.how_to_earn }}</p>
                                    <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Creator badges -->
                    <div v-if="creatorBadges.length">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Creator</p>
                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                            <div
                                v-for="badge in creatorBadges"
                                :key="badge.key"
                                class="relative group flex flex-col items-center text-center p-3 rounded-xl border transition-all cursor-default"
                                :class="badge.earned
                                    ? 'bg-white border-amber-100 shadow-sm'
                                    : 'bg-gray-50 border-gray-100 opacity-40 grayscale'"
                            >
                                <span class="text-2xl mb-1 leading-none">{{ badge.icon }}</span>
                                <p class="text-[11px] font-semibold text-gray-800 leading-tight">{{ badge.name }}</p>
                                <p v-if="badge.earned" class="text-[10px] text-green-600 mt-0.5">✓ Earned</p>
                                <div class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-44 bg-gray-900 text-white text-[11px] rounded-lg px-3 py-2 opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-10 text-left shadow-lg">
                                    <p class="font-semibold mb-0.5">{{ badge.name }}</p>
                                    <p v-if="badge.earned" class="text-green-400">✓ Earned {{ badge.earned_at }}</p>
                                    <p v-else class="text-gray-300 leading-snug">{{ badge.how_to_earn }}</p>
                                    <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Memberships -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <h2 class="text-sm font-bold text-gray-900 mb-4">Memberships</h2>
                    <div v-if="memberships.length" class="space-y-2">
                        <Link
                            v-for="m in memberships"
                            :key="m.community_id"
                            :href="`/communities/${m.slug}`"
                            class="flex items-center gap-3 p-3 border border-gray-100 rounded-xl hover:border-gray-200 hover:bg-gray-50 transition-colors"
                        >
                            <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-sm font-bold text-gray-600 shrink-0 overflow-hidden">
                                <img v-if="m.avatar" :src="m.avatar" :alt="m.name" class="w-full h-full object-cover" />
                                <span v-else>{{ m.name?.charAt(0)?.toUpperCase() }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ m.name }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ m.members_count }} member{{ m.members_count !== 1 ? 's' : '' }}
                                    · {{ m.price > 0 ? `₱${m.price}/month` : 'Free' }}
                                </p>
                            </div>
                        </Link>
                    </div>
                    <p v-else class="text-sm text-gray-400">No memberships yet.</p>
                </div>

                <!-- Contributions -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-gray-900">
                            {{ contributionsCount }} contribution{{ contributionsCount !== 1 ? 's' : '' }}
                            <span v-if="selectedCommunity"> to {{ selectedCommunity }}</span>
                        </h2>
                        <select
                            v-if="memberships.length > 1"
                            class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                            @change="filterCommunity($event.target.value)"
                        >
                            <option v-for="m in memberships" :key="m.community_id" :value="m.slug">
                                {{ m.name }}
                            </option>
                        </select>
                    </div>
                    <p v-if="contributionsCount === 0" class="text-sm text-gray-400">
                        {{ profileUser.name }} hasn't contributed to {{ selectedCommunity }} yet.
                    </p>
                    <p v-else class="text-sm text-gray-500">
                        Posts and comments in {{ selectedCommunity }}.
                    </p>
                </div>
            </div>

            <!-- ── Right sidebar ──────────────────────────────────────────── -->
            <div class="w-72 shrink-0">
                <div class="bg-white border border-gray-200 rounded-2xl p-6 text-center">

                    <!-- Avatar + level badge -->
                    <div class="relative inline-block mb-3">
                        <img
                            v-if="profileUser.avatar"
                            :src="profileUser.avatar"
                            :alt="profileUser.name"
                            class="w-24 h-24 rounded-full object-cover mx-auto ring-4 ring-white shadow"
                        />
                        <div
                            v-else
                            class="w-24 h-24 rounded-full flex items-center justify-center text-3xl font-black text-white mx-auto"
                            :style="{ background: levelColor(myLevel) }"
                        >
                            {{ profileUser.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <span
                            class="absolute bottom-0 right-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-black text-white ring-2 ring-white"
                            :style="{ background: levelColor(myLevel) }"
                        >
                            {{ myLevel }}
                        </span>
                    </div>

                    <!-- Level + points -->
                    <p class="text-sm font-semibold mb-0.5" :style="{ color: levelColor(myLevel) }">
                        Level {{ myLevel }}
                    </p>
                    <p class="text-xs text-gray-400 mb-4">
                        <span v-if="pointsToNextLevel !== null">
                            <span class="font-semibold text-gray-700">{{ pointsToNextLevel }}</span> points to level up
                        </span>
                        <span v-else class="text-amber-500 font-semibold">Max level!</span>
                    </p>

                    <!-- Name + username + bio -->
                    <p class="text-lg font-bold text-gray-900 leading-tight">{{ profileUser.name }}</p>
                    <p class="text-sm text-gray-400 mb-2">@{{ profileUser.username ?? `user${profileUser.id}` }}</p>
                    <p v-if="profileUser.bio" class="text-sm text-gray-600 mb-4">{{ profileUser.bio }}</p>

                    <div class="border-t border-gray-100 pt-4 mb-4 space-y-2 text-left">
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Joined {{ formatDate(profileUser.created_at) }}
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="flex justify-around text-center border-t border-gray-100 pt-4 mb-4">
                        <div>
                            <p class="text-sm font-bold text-gray-900">{{ totalPoints }}</p>
                            <p class="text-xs text-gray-400">Points</p>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-900">0</p>
                            <p class="text-xs text-gray-400">Followers</p>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-900">0</p>
                            <p class="text-xs text-gray-400">Following</p>
                        </div>
                    </div>

                    <!-- Edit profile button -->
                    <Link
                        v-if="isOwn"
                        href="/account/settings"
                        class="block w-full py-2 text-xs font-semibold border border-gray-300 rounded-xl text-gray-600 hover:bg-gray-50 transition-colors"
                    >
                        Edit Profile
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ActivityHeatmap from '@/Components/ActivityHeatmap.vue';

const props = defineProps({
    profileUser:        Object,
    isOwn:              Boolean,
    totalPoints:        Number,
    myLevel:            Number,
    pointsToNextLevel:  { type: Number, default: null },
    memberships:        Array,
    activityMap:        Object,
    contributionsCount: Number,
    selectedCommunity:  String,
    badges:             { type: Array, default: () => [] },
});

const memberBadges  = computed(() => props.badges.filter(b => b.type === 'member'));
const creatorBadges = computed(() => props.badges.filter(b => b.type === 'creator'));

const LEVEL_COLORS = [
    '#6b7280','#10b981','#3b82f6','#8b5cf6','#ec4899',
    '#f59e0b','#ef4444','#14b8a6','#f97316','#6366f1','#0ea5e9','#eab308',
];

function levelColor(level) {
    return LEVEL_COLORS[(level - 1) % LEVEL_COLORS.length];
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

function filterCommunity(slug) {
    router.get(`/profile/${props.profileUser.username}`, { community: slug }, { preserveScroll: true });
}
</script>
