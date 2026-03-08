<template>
    <AppLayout :title="`${community.name} · Leaderboards`" :community="community">
        <CommunityTabs :community="community" active-tab="leaderboard" />

        <!-- ── Hero card: current user + level grid ──────────────────────── -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-4 flex flex-col sm:flex-row gap-8">

            <!-- Left: current user -->
            <div class="flex flex-col items-center justify-center text-center min-w-44">
                <div class="relative mb-3">
                    <img v-if="myAvatar" :src="myAvatar" :alt="myName"
                        class="w-24 h-24 rounded-full object-cover ring-4 ring-white shadow" />
                    <div v-else
                        class="w-24 h-24 rounded-full flex items-center justify-center text-3xl font-black text-white"
                        :style="{ background: levelColor(myLevel) }">
                        {{ myName?.charAt(0)?.toUpperCase() ?? '?' }}
                    </div>
                    <span class="absolute bottom-0 right-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-black text-white ring-2 ring-white"
                          :style="{ background: levelColor(myLevel) }">
                        {{ myLevel }}
                    </span>
                </div>
                <p class="font-bold text-gray-900 text-sm">{{ myName }}</p>
                <p class="text-sm font-semibold mt-0.5" :style="{ color: levelColor(myLevel) }">Level {{ myLevel }}</p>
                <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                    <span v-if="pointsToNextLevel !== null">
                        <span class="font-semibold text-gray-700">{{ pointsToNextLevel }}</span> points to level up
                    </span>
                    <span v-else class="font-semibold text-amber-500">Max level!</span>
                    <button @click="showInfo = true" class="text-gray-300 hover:text-gray-500 transition-colors ml-1" title="How points work">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </p>
            </div>

            <!-- Divider -->
            <div class="hidden sm:block w-px bg-gray-100 self-stretch"></div>

            <!-- Right: level grid -->
            <div class="flex-1 grid grid-cols-2 gap-x-8 gap-y-3 content-center">
                <div
                    v-for="lvl in levelDistribution"
                    :key="lvl.level"
                    class="flex items-center gap-3"
                >
                    <!-- Badge or lock -->
                    <div
                        class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-xs font-bold"
                        :class="lvl.level <= myLevel ? 'text-white' : 'bg-gray-100 text-gray-400'"
                        :style="lvl.level <= myLevel ? { background: levelColor(lvl.level) } : ''"
                    >
                        <svg v-if="lvl.level > myLevel" class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/>
                        </svg>
                        <span v-else>{{ lvl.level }}</span>
                    </div>
                    <!-- Label -->
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-gray-700">Level {{ lvl.level }}</p>
                        <p v-if="lvl.perk" class="text-xs text-indigo-600 font-medium truncate">
                            Unlock "{{ lvl.perk }}"
                        </p>
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

        <!-- ── Points info modal ───────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showInfo" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="showInfo = false">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
                    <h3 class="text-base font-bold text-gray-900 mb-4">Points &amp; Levels</h3>

                    <p class="text-sm font-semibold text-gray-700 mb-1">Points</p>
                    <p class="text-sm text-gray-500 mb-4">
                        You earn points when other members react to your posts or comments.
                        Each reaction = 1 point. This encourages quality content and engagement.
                    </p>

                    <p class="text-sm font-semibold text-gray-700 mb-2">Levels</p>
                    <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-gray-500 mb-4">
                        <div v-for="lvl in levelDistribution" :key="lvl.level" class="flex items-center gap-1.5">
                            <span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold text-white shrink-0"
                                :style="{ background: levelColor(lvl.level) }">{{ lvl.level }}</span>
                            {{ lvl.threshold.toLocaleString() }} points
                        </div>
                    </div>

                    <button @click="showInfo = false"
                        class="w-full py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors">
                        Got it
                    </button>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import LeaderboardList from '@/Components/LeaderboardList.vue';

defineProps({
    community:          Object,
    myName:             String,
    myAvatar:           { type: String, default: null },
    myPoints:           Number,
    myLevel:            Number,
    pointsToNextLevel:  { type: Number, default: null },
    levelDistribution:  Array,
    leaderboard:        Array,
    leaderboard30:      Array,
    leaderboard7:       Array,
    updatedAt:          String,
});

const showInfo = ref(false);

const LEVEL_COLORS = [
    '#6b7280', // L1 gray
    '#10b981', // L2 emerald
    '#3b82f6', // L3 blue
    '#8b5cf6', // L4 violet
    '#ec4899', // L5 pink
    '#f59e0b', // L6 amber
    '#ef4444', // L7 red
    '#14b8a6', // L8 teal
    '#f97316', // L9 orange
];

function levelColor(level) {
    return LEVEL_COLORS[(level - 1) % LEVEL_COLORS.length];
}
</script>
