<template>
    <AppLayout :title="`${community.name} · Leaderboards`" :community="community">
        <CommunityTabs :community="community" active-tab="leaderboard" />

        <!-- ── Hero card: current user + level grid ──────────────────────── -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-4 flex flex-col sm:flex-row gap-8">

            <!-- Left: current user -->
            <div class="flex flex-col items-center justify-center text-center min-w-40">
                <div class="relative mb-3">
                    <div class="w-24 h-24 rounded-full flex items-center justify-center text-3xl font-black text-white"
                         :style="levelGradient(myLevel)">
                        {{ myName?.charAt(0)?.toUpperCase() ?? '?' }}
                    </div>
                    <span class="absolute bottom-0 right-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-black text-white ring-2 ring-white"
                          :style="levelGradient(myLevel)">
                        {{ myLevel }}
                    </span>
                </div>
                <p class="font-bold text-gray-900 text-sm">{{ myName }}</p>
                <p class="text-sm font-semibold mt-0.5" :style="{ color: levelColor(myLevel) }">Level {{ myLevel }}</p>
                <p class="text-xs text-gray-400 mt-1">
                    <span v-if="pointsToNextLevel !== null">
                        <span class="font-semibold text-gray-700">{{ pointsToNextLevel }}</span> points to level up
                    </span>
                    <span v-else class="font-semibold text-amber-500">Max level!</span>
                </p>
            </div>

            <!-- Divider -->
            <div class="hidden sm:block w-px bg-gray-100 self-stretch"></div>

            <!-- Right: level grid -->
            <div class="flex-1 grid grid-cols-2 gap-x-8 gap-y-2.5 content-center">
                <div
                    v-for="lvl in levelDistribution"
                    :key="lvl.level"
                    class="flex items-center gap-3"
                >
                    <!-- Badge or lock -->
                    <div
                        class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-xs font-bold"
                        :class="lvl.level <= myLevel
                            ? 'text-white'
                            : 'bg-gray-100 text-gray-400'"
                        :style="lvl.level <= myLevel ? levelGradient(lvl.level) : ''"
                    >
                        <svg v-if="lvl.level > myLevel" class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/>
                        </svg>
                        <span v-else>{{ lvl.level }}</span>
                    </div>
                    <!-- Label -->
                    <div>
                        <p class="text-xs font-semibold text-gray-700">Level {{ lvl.level }}</p>
                        <p class="text-xs text-gray-400">{{ lvl.percent }}% of members</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Last updated -->
        <p class="text-xs text-gray-400 mb-5">Last updated: {{ updatedAt }}</p>

        <!-- ── Three leaderboard columns ──────────────────────────────────── -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <!-- 7-day -->
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Leaderboard (7-day)</h3>
                </div>
                <LeaderboardList :entries="leaderboard7" empty-text="No activity this week" />
            </div>

            <!-- 30-day -->
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Leaderboard (30-day)</h3>
                </div>
                <LeaderboardList :entries="leaderboard30" empty-text="No activity this month" />
            </div>

            <!-- All-time -->
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Leaderboard (all-time)</h3>
                </div>
                <LeaderboardList :entries="leaderboard" empty-text="No activity yet" />
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import LeaderboardList from '@/Components/LeaderboardList.vue';

defineProps({
    community:          Object,
    myName:             String,
    myPoints:           Number,
    myLevel:            Number,
    pointsToNextLevel:  Number,
    levelDistribution:  Array,
    leaderboard:        Array,
    leaderboard30:      Array,
    leaderboard7:       Array,
    updatedAt:          String,
});

const LEVEL_COLORS = [
    '#6b7280', // L1  gray
    '#10b981', // L2  emerald
    '#3b82f6', // L3  blue
    '#8b5cf6', // L4  violet
    '#ec4899', // L5  pink
    '#f59e0b', // L6  amber
    '#ef4444', // L7  red
    '#14b8a6', // L8  teal
    '#f97316', // L9  orange
    '#6366f1', // L10 indigo
    '#0ea5e9', // L11 sky
    '#eab308', // L12 yellow
];

function levelColor(level) {
    return LEVEL_COLORS[(level - 1) % LEVEL_COLORS.length];
}

function levelGradient(level) {
    const c = levelColor(level);
    return { background: c };
}
</script>
