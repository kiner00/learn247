<template>
    <AppLayout title="My Affiliates">
        <div class="max-w-5xl mx-auto px-4 py-8">
            <h1 class="text-2xl font-bold text-gray-900 mb-1">My Affiliate Links</h1>
            <p class="text-gray-500 mb-8 text-sm">
                Share your referral links. When someone subscribes through your link, you earn your community's commission.
            </p>

            <!-- Empty state -->
            <div v-if="affiliates.length === 0"
                 class="text-center py-16 bg-white rounded-xl border border-gray-200">
                <div class="text-4xl mb-3">🔗</div>
                <p class="font-medium text-gray-700">No affiliate links yet</p>
                <p class="text-sm text-gray-500 mt-1">
                    Visit a paid community and click "Become an Affiliate" to get started.
                </p>
            </div>

            <!-- Affiliates table -->
            <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-5 py-3 font-semibold text-gray-600">Community</th>
                            <th class="text-left px-5 py-3 font-semibold text-gray-600">Referral Link</th>
                            <th class="text-right px-5 py-3 font-semibold text-gray-600">Earned</th>
                            <th class="text-right px-5 py-3 font-semibold text-gray-600">Paid Out</th>
                            <th class="text-right px-5 py-3 font-semibold text-gray-600">Pending</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="a in affiliates" :key="a.id" class="hover:bg-gray-50">
                            <td class="px-5 py-4 font-medium text-gray-900">
                                <Link :href="`/communities/${a.community.slug}`"
                                      class="hover:text-indigo-600">
                                    {{ a.community.name }}
                                </Link>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs text-gray-500 truncate max-w-48">
                                        {{ a.referral_url }}
                                    </span>
                                    <button @click="copy(a.referral_url)"
                                            class="shrink-0 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                        {{ copied === a.id ? '✓ Copied' : 'Copy' }}
                                    </button>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-right text-gray-900 font-medium">
                                ₱{{ Number(a.total_earned).toFixed(2) }}
                            </td>
                            <td class="px-5 py-4 text-right text-gray-500">
                                ₱{{ Number(a.total_paid).toFixed(2) }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <span :class="a.pending_amount > 0
                                    ? 'text-green-700 font-semibold'
                                    : 'text-gray-400'">
                                    ₱{{ Number(a.pending_amount).toFixed(2) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
    affiliates: Array,
})

const copied = ref(null)

async function copy(url) {
    await navigator.clipboard.writeText(url)
    copied.value = url
    setTimeout(() => { copied.value = null }, 2000)
}
</script>
