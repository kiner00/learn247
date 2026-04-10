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
                    <!-- Subscription status for paid communities -->
                    <div v-if="m.price > 0 && m.subscription_id && !m.is_owner" class="mt-1 flex items-center gap-2 flex-wrap">
                        <span v-if="m.expires_at" class="text-xs text-gray-400">
                            {{ m.is_auto_renewing ? 'Renews' : 'Expires' }} {{ formatDate(m.expires_at) }}
                        </span>
                        <span v-if="m.is_auto_renewing" class="inline-flex items-center text-[11px] font-medium px-1.5 py-0.5 rounded-full bg-green-50 text-green-700">
                            Auto-Renew ON
                        </span>
                        <span v-else-if="m.recurring_status === 'INACTIVE'" class="inline-flex items-center text-[11px] font-medium px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500">
                            Auto-Renew Cancelled
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <!-- Auto-renew buttons for paid, non-owner members -->
                    <button
                        v-if="m.price > 0 && m.subscription_id && !m.is_owner && !m.is_recurring"
                        @click="enableAutoRenew(m)"
                        :disabled="loading === m.subscription_id"
                        class="text-[11px] text-indigo-600 hover:text-indigo-800 transition-colors px-2 py-1 border border-indigo-200 rounded-lg disabled:opacity-50"
                    >
                        {{ loading === m.subscription_id ? 'Loading...' : 'Auto-Renew' }}
                    </button>
                    <Link
                        v-if="m.price > 0 && m.subscription_id && !m.is_owner && m.is_auto_renewing"
                        :href="`/subscriptions/${m.subscription_id}/cancel-recurring`"
                        method="post"
                        as="button"
                        class="text-[11px] text-red-500 hover:text-red-700 transition-colors px-2 py-1 border border-red-200 rounded-lg"
                    >
                        Cancel
                    </Link>
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
        </div>
        <p v-else class="text-sm text-gray-400">You haven't joined any communities yet.</p>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';

defineProps({
    memberships: { type: Array, required: true },
});

const loading = ref(null);

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

async function enableAutoRenew(m) {
    loading.value = m.subscription_id;
    try {
        const { data } = await axios.post(`/subscriptions/${m.subscription_id}/enable-auto-renew`);
        if (data.linking_url) {
            window.location.href = data.linking_url;
        }
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to enable auto-renew. Please try again.');
    } finally {
        loading.value = null;
    }
}
</script>
