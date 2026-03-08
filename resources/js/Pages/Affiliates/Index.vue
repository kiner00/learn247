<template>
    <AppLayout title="My Affiliates">
        <div class="max-w-5xl mx-auto px-4 py-8">
            <div class="flex items-start justify-between mb-1">
                <h1 class="text-2xl font-bold text-gray-900">My Affiliate Links</h1>
                <Link
                    href="/my-affiliates/analytics"
                    class="flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors"
                >
                    <span>📊</span> Analytics
                </Link>
            </div>
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
                            <th class="text-right px-5 py-3 font-semibold text-gray-600">Payout</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template v-for="a in affiliates" :key="a.id">
                        <tr :class="['hover:bg-gray-50', !a.is_active ? 'opacity-60' : '']">
                            <td class="px-5 py-4 font-medium text-gray-900">
                                <div class="flex items-center gap-2">
                                    <Link :href="`/communities/${a.community.slug}`"
                                          class="hover:text-indigo-600">
                                        {{ a.community.name }}
                                    </Link>
                                    <span v-if="!a.is_active"
                                          class="text-xs font-bold uppercase bg-red-100 text-red-600 px-2 py-0.5 rounded-full">
                                        Suspended
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div v-if="a.is_active" class="flex items-center gap-2">
                                    <span class="font-mono text-xs text-gray-500 truncate max-w-48">
                                        {{ a.referral_url }}
                                    </span>
                                    <button @click="copy(a.referral_url)"
                                            class="shrink-0 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                        {{ copied === a.referral_url ? '✓ Copied' : 'Copy' }}
                                    </button>
                                </div>
                                <span v-else class="text-xs text-red-500 italic">
                                    Renew subscription to reactivate
                                </span>
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
                            <td class="px-5 py-4 text-right">
                                <button @click="showPayoutModal = true"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                    {{ payoutMethod ? '✓ ' + payoutMethod.toUpperCase() : 'Set payout' }}
                                </button>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payout modal -->
        <Teleport to="body">
            <div v-if="showPayoutModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="showPayoutModal = false">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
                    <h3 class="text-base font-bold text-gray-900 mb-1">Payout Details</h3>
                    <p class="text-xs text-gray-400 mb-4">This applies to all your affiliate earnings.</p>
                    <form @submit.prevent="savePayout" class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Method</label>
                            <select v-model="payoutForm.payout_method"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="gcash">GCash</option>
                                <option value="maya">Maya</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="paypal">PayPal</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                {{ payoutForm.payout_method === 'bank' ? 'Account Number / Name' : 'Account / Number' }}
                            </label>
                            <input v-model="payoutForm.payout_details" type="text"
                                   placeholder="e.g. 09xxxxxxxxx or account number"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div class="flex gap-3 pt-1">
                            <button type="button" @click="showPayoutModal = false"
                                    class="flex-1 py-2 border border-gray-200 text-gray-600 text-sm rounded-xl hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="flex-1 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700">
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    affiliates: Array,
    payoutMethod: String,
    payoutDetails: String,
})

const copied = ref(null)
const showPayoutModal = ref(false)
const payoutForm = reactive({
    payout_method:  props.payoutMethod  ?? 'gcash',
    payout_details: props.payoutDetails ?? '',
})

async function copy(url) {
    await navigator.clipboard.writeText(url)
    copied.value = url
    setTimeout(() => { copied.value = null }, 2000)
}

function savePayout() {
    router.patch('/account/settings/payout', payoutForm, {
        onSuccess: () => { showPayoutModal.value = false },
        preserveScroll: true,
    })
}
</script>
