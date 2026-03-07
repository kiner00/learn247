<template>
    <AppLayout title="Admin — Payouts">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-black text-gray-900">Payouts</h1>
                <p class="text-sm text-gray-500 mt-0.5">Manage community owner and affiliate earnings</p>
            </div>
            <Link href="/admin" class="text-sm text-gray-400 hover:text-gray-600">← Dashboard</Link>
        </div>

        <!-- Summary stats -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5">
                <p class="text-xs font-semibold text-amber-600 uppercase tracking-wide mb-1">Owner Pending</p>
                <p class="text-2xl font-black text-amber-700">₱{{ fmt(stats.owners_pending) }}</p>
            </div>
            <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-5">
                <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">Affiliate Pending</p>
                <p class="text-2xl font-black text-indigo-700">₱{{ fmt(stats.affiliates_pending) }}</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-6 bg-gray-100 rounded-xl p-1 w-fit">
            <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
                    class="px-5 py-2 text-sm font-semibold rounded-lg transition-all"
                    :class="activeTab === t.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                {{ t.label }}
            </button>
        </div>

        <!-- ── Community Owners tab ─────────────────────────────────────────── -->
        <div v-if="activeTab === 'owners'">
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-800">Community Owners</h2>
                    <div class="flex items-center gap-2">
                        <button v-if="selectedOwnerIds.size > 0"
                                @click="paySelectedOwners"
                                class="text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-1.5 rounded-lg transition-colors">
                            Pay Selected ({{ selectedOwnerIds.size }})
                        </button>
                        <button @click="batchPayOwners"
                                class="text-sm bg-green-600 hover:bg-green-700 text-white font-semibold px-4 py-1.5 rounded-lg transition-colors">
                            Batch Pay All (Xendit)
                        </button>
                    </div>
                </div>

                <div v-if="owners.length === 0" class="px-5 py-10 text-center text-sm text-gray-400">
                    No pending owner payouts.
                </div>

                <div v-else>
                    <div v-for="owner in owners" :key="owner.user_id"
                         class="border-b border-gray-100 last:border-0">
                        <!-- Owner header -->
                        <div class="px-5 py-3 bg-gray-50 flex items-center justify-between">
                            <div>
                                <span class="font-semibold text-gray-900 text-sm">{{ owner.name }}</span>
                                <span class="text-gray-400 text-xs ml-2">{{ owner.email }}</span>
                                <span v-if="owner.payout_method"
                                      class="ml-2 text-xs font-semibold uppercase bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">
                                    {{ owner.payout_method }} · {{ owner.payout_details }}
                                </span>
                                <span v-else class="ml-2 text-xs text-red-500 italic">No payout method set</span>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500">Total Pending</p>
                                <p class="font-bold text-green-700">₱{{ fmt(owner.total_pending) }}</p>
                            </div>
                        </div>

                        <!-- Communities breakdown -->
                        <table class="w-full text-sm">
                            <thead class="bg-white border-b border-gray-100">
                                <tr>
                                    <th class="px-5 py-2 w-8"></th>
                                    <th class="text-left px-5 py-2 text-xs font-semibold text-gray-500">Community</th>
                                    <th class="text-right px-5 py-2 text-xs font-semibold text-gray-500">Gross</th>
                                    <th class="text-right px-5 py-2 text-xs font-semibold text-gray-500">Platform (3%)</th>
                                    <th class="text-right px-5 py-2 text-xs font-semibold text-gray-500">Commissions</th>
                                    <th class="text-right px-5 py-2 text-xs font-semibold text-gray-500">Earned</th>
                                    <th class="text-right px-5 py-2 text-xs font-semibold text-gray-500">Paid</th>
                                    <th class="text-right px-5 py-2 text-xs font-semibold text-gray-500">Pending</th>
                                    <th class="px-5 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="c in owner.communities" :key="c.community_id" class="hover:bg-gray-50">
                                    <td class="px-5 py-2.5">
                                        <input v-if="c.pending > 0 && owner.can_disburse"
                                               type="checkbox"
                                               :value="c.community_id"
                                               :checked="selectedOwnerIds.has(c.community_id)"
                                               @change="toggleOwner(c.community_id)"
                                               class="w-4 h-4 rounded border-gray-300 text-indigo-600 cursor-pointer" />
                                    </td>
                                    <td class="px-5 py-2.5 font-medium text-gray-900">{{ c.community_name }}</td>
                                    <td class="px-5 py-2.5 text-right text-gray-600">₱{{ fmt(c.gross) }}</td>
                                    <td class="px-5 py-2.5 text-right text-red-500">−₱{{ fmt(c.platform_fee) }}</td>
                                    <td class="px-5 py-2.5 text-right text-orange-500">−₱{{ fmt(c.commissions) }}</td>
                                    <td class="px-5 py-2.5 text-right font-medium text-gray-900">₱{{ fmt(c.earned) }}</td>
                                    <td class="px-5 py-2.5 text-right text-gray-400">₱{{ fmt(c.paid) }}</td>
                                    <td class="px-5 py-2.5 text-right">
                                        <span :class="c.pending > 0 ? 'text-green-700 font-semibold' : 'text-gray-400'">
                                            ₱{{ fmt(c.pending) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-2.5 text-right">
                                        <button v-if="c.pending > 0 && owner.can_disburse"
                                                @click="payOwner(c.community_id)"
                                                class="text-xs text-green-700 hover:text-green-900 font-semibold whitespace-nowrap">
                                            Pay via Xendit
                                        </button>
                                        <span v-else-if="c.pending > 0" class="text-xs text-gray-400 italic">Manual</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Affiliates tab ──────────────────────────────────────────────── -->
        <div v-else-if="activeTab === 'affiliates'">
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-800">Affiliates with Pending Commissions</h2>
                    <div class="flex items-center gap-2">
                        <button v-if="selectedAffiliateIds.size > 0"
                                @click="paySelectedAffiliates"
                                class="text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-1.5 rounded-lg transition-colors">
                            Pay Selected ({{ selectedAffiliateIds.size }})
                        </button>
                        <button @click="batchPayAffiliates"
                                class="text-sm bg-green-600 hover:bg-green-700 text-white font-semibold px-4 py-1.5 rounded-lg transition-colors">
                            Batch Pay All (Xendit)
                        </button>
                    </div>
                </div>

                <div v-if="affiliates.length === 0" class="px-5 py-10 text-center text-sm text-gray-400">
                    No pending affiliate payouts.
                </div>

                <table v-else class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-5 py-3 w-8"></th>
                            <th class="text-left px-5 py-3 font-semibold text-gray-600">Affiliate</th>
                            <th class="text-left px-5 py-3 font-semibold text-gray-600">Community</th>
                            <th class="text-right px-5 py-3 font-semibold text-gray-600">Earned</th>
                            <th class="text-right px-5 py-3 font-semibold text-gray-600">Paid</th>
                            <th class="text-right px-5 py-3 font-semibold text-gray-600">Pending</th>
                            <th class="text-left px-5 py-3 font-semibold text-gray-600">Payout</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="a in affiliates" :key="a.id" class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <input v-if="a.can_disburse"
                                       type="checkbox"
                                       :value="a.id"
                                       :checked="selectedAffiliateIds.has(a.id)"
                                       @change="toggleAffiliate(a.id)"
                                       class="w-4 h-4 rounded border-gray-300 text-indigo-600 cursor-pointer" />
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-900">{{ a.name }}</p>
                                <p class="text-xs text-gray-400">{{ a.email }}</p>
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ a.community_name }}</td>
                            <td class="px-5 py-3 text-right font-medium">₱{{ fmt(a.total_earned) }}</td>
                            <td class="px-5 py-3 text-right text-gray-400">₱{{ fmt(a.total_paid) }}</td>
                            <td class="px-5 py-3 text-right text-green-700 font-semibold">₱{{ fmt(a.pending) }}</td>
                            <td class="px-5 py-3">
                                <span v-if="a.payout_method" class="text-xs font-semibold uppercase text-gray-700">
                                    {{ a.payout_method }} · {{ a.payout_details }}
                                </span>
                                <span v-else class="text-xs text-red-400 italic">Not set</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    owners:     Array,
    affiliates: Array,
    stats:      Object,
})

const activeTab = ref('owners')
const tabs = [
    { key: 'owners',     label: 'Community Owners' },
    { key: 'affiliates', label: 'Affiliates' },
]

const selectedOwnerIds    = reactive(new Set())
const selectedAffiliateIds = reactive(new Set())

function toggleOwner(id) {
    selectedOwnerIds.has(id) ? selectedOwnerIds.delete(id) : selectedOwnerIds.add(id)
}

function toggleAffiliate(id) {
    selectedAffiliateIds.has(id) ? selectedAffiliateIds.delete(id) : selectedAffiliateIds.add(id)
}

function fmt(val) {
    return Number(val ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function payOwner(communityId) {
    router.post(`/admin/payouts/owner/${communityId}`)
}

function paySelectedOwners() {
    router.post('/admin/payouts/owners/selected', { community_ids: [...selectedOwnerIds] }, {
        onSuccess: () => selectedOwnerIds.clear(),
    })
}

function batchPayOwners() {
    router.post('/admin/payouts/owners/batch')
}

function paySelectedAffiliates() {
    router.post('/admin/payouts/affiliates/selected', { affiliate_ids: [...selectedAffiliateIds] }, {
        onSuccess: () => selectedAffiliateIds.clear(),
    })
}

function batchPayAffiliates() {
    router.post('/admin/payouts/affiliates/batch')
}
</script>
