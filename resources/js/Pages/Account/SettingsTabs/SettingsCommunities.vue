<template>
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h2 class="text-base font-bold text-gray-900 mb-1">Communities</h2>
        <p class="text-sm text-gray-400 mb-5">Your community memberships.</p>

        <div v-if="memberships.length" class="space-y-2">
            <div
                v-for="m in memberships"
                :key="m.community_id"
                class="flex items-center gap-3 p-3 border border-gray-100 rounded-xl"
            >
                <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-sm font-bold text-gray-600 shrink-0 overflow-hidden">
                    <img v-if="m.avatar" :src="m.avatar" :alt="m.name" class="w-full h-full object-cover" />
                    <span v-else>{{ m.name?.charAt(0)?.toUpperCase() }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 truncate">{{ m.name }}</p>
                    <p class="text-xs text-gray-400">
                        {{ m.is_owner ? 'Owner' : m.role }}
                        · {{ m.price > 0 ? `₱${m.price}/month` : 'Free' }}
                    </p>
                </div>
                <Link
                    v-if="m.is_owner"
                    :href="`/communities/${m.slug}/settings`"
                    class="text-xs text-gray-400 hover:text-indigo-600 transition-colors px-2 py-1 border border-gray-200 rounded-lg"
                >
                    Settings
                </Link>
                <Link
                    v-else
                    :href="`/communities/${m.slug}`"
                    class="text-xs text-gray-400 hover:text-indigo-600 transition-colors px-2 py-1 border border-gray-200 rounded-lg"
                >
                    View
                </Link>
            </div>
        </div>
        <p v-else class="text-sm text-gray-400">You haven't joined any communities yet.</p>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    memberships: { type: Array, required: true },
});
</script>
