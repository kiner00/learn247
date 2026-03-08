<template>
    <div v-if="entries.length" class="divide-y divide-gray-50">
        <div
            v-for="(entry, index) in entries"
            :key="entry.user_id"
            class="flex items-center gap-3 px-5 py-3"
        >
            <!-- Rank -->
            <div class="w-6 text-center shrink-0">
                <span v-if="index === 0" class="text-base">🥇</span>
                <span v-else-if="index === 1" class="text-base">🥈</span>
                <span v-else-if="index === 2" class="text-base">🥉</span>
                <span v-else class="text-xs font-bold text-gray-400">{{ index + 1 }}</span>
            </div>

            <!-- Avatar -->
            <div class="w-8 h-8 rounded-full shrink-0 overflow-hidden flex items-center justify-center text-xs font-bold text-white"
                :class="entry.avatar ? '' : avatarBg(index)">
                <img v-if="entry.avatar" :src="entry.avatar" :alt="entry.name" class="w-full h-full object-cover" />
                <span v-else>{{ entry.name.charAt(0).toUpperCase() }}</span>
            </div>

            <!-- Name -->
            <p class="flex-1 text-sm font-medium text-gray-800 truncate">{{ entry.name }}</p>

            <!-- Points -->
            <span class="text-sm font-bold text-indigo-500 shrink-0">+{{ entry.points }}</span>
        </div>
    </div>

    <div v-else class="px-5 py-10 text-center">
        <p class="text-xs text-gray-400">{{ emptyText }}</p>
    </div>
</template>

<script setup>
defineProps({
    entries:   { type: Array,  default: () => [] },
    emptyText: { type: String, default: 'No activity yet' },
});

const BG = ['bg-amber-400', 'bg-gray-400', 'bg-orange-400', 'bg-indigo-400'];
function avatarBg(index) {
    return BG[index] ?? 'bg-indigo-300';
}
</script>
