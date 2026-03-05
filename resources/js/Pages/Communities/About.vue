<template>
    <AppLayout :title="`${community.name} · About`" :community="community">
        <CommunityTabs :community="community" active-tab="about" />

        <div class="max-w-2xl">
            <!-- Cover image -->
            <div class="rounded-2xl overflow-hidden mb-6 h-48 shadow-sm">
                <img
                    v-if="community.cover_image"
                    :src="community.cover_image"
                    :alt="community.name"
                    class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full bg-gradient-to-br from-indigo-500 to-purple-700" />
            </div>

            <!-- Main card -->
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm mb-4">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div>
                            <h1 class="text-xl font-black text-gray-900">{{ community.name }}</h1>
                            <span v-if="community.category" class="inline-block mt-1 text-xs font-medium px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                                {{ community.category }}
                            </span>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-lg font-black text-gray-900">
                                {{ community.price > 0 ? `₱${Number(community.price).toLocaleString()}` : 'Free' }}
                            </p>
                            <p v-if="community.price > 0" class="text-xs text-gray-400">/month</p>
                        </div>
                    </div>

                    <p v-if="community.description" class="text-sm text-gray-600 leading-relaxed mb-6">
                        {{ community.description }}
                    </p>
                    <p v-else class="text-sm text-gray-400 italic mb-6">No description provided.</p>

                    <!-- Stats row -->
                    <div class="grid grid-cols-3 gap-4 pt-4 border-t border-gray-100">
                        <div class="text-center">
                            <p class="text-xl font-black text-gray-900">{{ formatCount(community.members_count) }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">Members</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xl font-black text-gray-900">{{ community.is_private ? '🔒' : '🌐' }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ community.is_private ? 'Private' : 'Public' }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-sm font-black text-gray-900">{{ formatDate(community.created_at) }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">Founded</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Owner card -->
            <div v-if="community.owner" class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm flex items-center gap-4">
                <div class="w-11 h-11 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-base font-bold text-white shrink-0">
                    {{ community.owner.name.charAt(0).toUpperCase() }}
                </div>
                <div>
                    <p class="text-xs text-gray-400">Community Owner</p>
                    <p class="text-sm font-semibold text-gray-900">{{ community.owner.name }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';

defineProps({
    community: Object,
});

function formatDate(str) {
    if (!str) return '—';
    return new Date(str).toLocaleDateString('en-PH', { month: 'short', year: 'numeric' });
}

function formatCount(n) {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(n ?? 0);
}
</script>
