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
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5">
                <p class="text-xs font-semibold text-amber-600 uppercase tracking-wide mb-1">Owner Pending</p>
                <p class="text-2xl font-black text-amber-700">₱{{ fmt(stats.owners_pending) }}</p>
            </div>
            <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-5">
                <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">Affiliate Pending</p>
                <p class="text-2xl font-black text-indigo-700">₱{{ fmt(stats.affiliates_pending) }}</p>
            </div>
            <div class="bg-purple-50 border border-purple-200 rounded-2xl p-5">
                <p class="text-xs font-semibold text-purple-600 uppercase tracking-wide mb-1">Platform Income (varies by plan)</p>
                <p class="text-2xl font-black text-purple-700">₱{{ fmt(stats.platform_fee_collected) }}</p>
                <p class="text-xs text-purple-400 mt-1">From all collected payments</p>
            </div>
            <div class="bg-teal-50 border border-teal-200 rounded-2xl p-5">
                <p class="text-xs font-semibold text-teal-600 uppercase tracking-wide mb-1">Xendit Cash Balance</p>
                <p class="text-2xl font-black text-teal-700">₱{{ fmt(xenditBalance ?? 0) }}</p>
                <p class="text-xs text-teal-500 mt-1">Available for payouts</p>
            </div>
        </div>

        <!-- Balance vs Payout comparison -->
        <div class="bg-white border rounded-2xl overflow-hidden shadow-sm mb-6"
             :class="netPosition >= 0 ? 'border-green-200' : 'border-red-200'">
            <div class="px-5 py-3 border-b flex items-center justify-between"
                 :class="netPosition >= 0 ? 'border-green-100 bg-green-50' : 'border-red-100 bg-red-50'">
                <div>
                    <p class="text-sm font-bold" :class="netPosition >= 0 ? 'text-green-800' : 'text-red-800'">
                        {{ netPosition >= 0 ? '✅ Sufficient balance to cover all payouts' : '⚠️ Insufficient balance — top up Xendit before paying out' }}
                    </p>
                </div>
                <p class="text-lg font-black" :class="netPosition >= 0 ? 'text-green-700' : 'text-red-600'">
                    {{ netPosition >= 0 ? '+' : '' }}₱{{ fmt(netPosition) }}
                </p>
            </div>
            <div class="grid grid-cols-3 divide-x divide-gray-100">
                <div class="px-5 py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Xendit Cash Balance</p>
                    <p class="text-xl font-black text-teal-700">₱{{ fmt(xenditBalance ?? 0) }}</p>
                </div>
                <div class="px-5 py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Total Pending Payouts</p>
                    <p class="text-xl font-black text-amber-700">₱{{ fmt(totalPending) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Owners ₱{{ fmt(stats.owners_pending) }} + Affiliates ₱{{ fmt(stats.affiliates_pending) }}</p>
                </div>
                <div class="px-5 py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Net Position</p>
                    <p class="text-xl font-black" :class="netPosition >= 0 ? 'text-green-600' : 'text-red-600'">
                        {{ netPosition >= 0 ? '+' : '' }}₱{{ fmt(netPosition) }}
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ netPosition >= 0 ? 'Surplus after full payout' : 'Shortfall to cover' }}</p>
                </div>
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

        <!-- ── Payout Requests tab ────────────────────────────────────────────── -->
        <div v-if="activeTab === 'requests'">
            <div class="bg-white rounded-2xl border border-gray-200 overflow-x-auto">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                    <h2 class="font-semibold text-gray-800">Payout Requests</h2>
                    <div class="flex gap-0.5 bg-gray-100 rounded-lg p-0.5">
                        <button v-for="s in requestStatusTabs" :key="s.key" @click="requestStatus = s.key"
                                class="px-3 py-1 text-xs font-semibold rounded-md transition-all"
                                :class="requestStatus === s.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                            {{ s.label }}
                        </button>
                    </div>
                </div>

                <div v-if="filteredRequests.length === 0" class="px-5 py-10 text-center text-sm text-gray-400">
                    No {{ requestStatus }} payout requests.
                </div>

                <table v-else class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">User</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Type</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Community</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Payout To</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500">Requested</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500">Xendit Balance</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500">Eligible at Request</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Status</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Xendit Reference</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500">Requested</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500">Processed</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template v-for="r in filteredRequests" :key="r.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-gray-900">{{ r.user_name }}</p>
                                    <p class="text-xs text-gray-400">{{ r.user_email }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-xs font-semibold uppercase px-2 py-0.5 rounded-full"
                                          :class="r.type === 'owner' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'">
                                        {{ r.type }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-gray-700">{{ r.community_name ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <span v-if="r.payout_method" class="text-xs font-semibold uppercase text-gray-700">
                                        {{ r.payout_method }} · {{ r.payout_details }}
                                    </span>
                                    <span v-else class="text-xs text-red-400 italic">Not set</span>
                                </td>
                                <td class="px-5 py-3 text-right font-semibold text-gray-900">₱{{ fmt(r.amount) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <p class="text-xs text-gray-500">Before: <span class="font-semibold text-gray-800">₱{{ fmt(xenditBalance) }}</span></p>
                                    <p class="text-xs mt-0.5"
                                       :class="(xenditBalance - r.amount) >= 0 ? 'text-green-600' : 'text-red-600'">
                                        After: <span class="font-semibold">₱{{ fmt(xenditBalance - r.amount) }}</span>
                                    </p>
                                </td>
                                <td class="px-5 py-3 text-right text-gray-400">₱{{ fmt(r.eligible_amount) }}</td>
                                <td class="px-5 py-3">
                                    <span class="text-xs font-bold uppercase px-2 py-0.5 rounded-full"
                                          :class="{
                                              'bg-amber-100 text-amber-700': r.status === 'pending',
                                              'bg-blue-100 text-blue-700':   r.status === 'approved',
                                              'bg-green-100 text-green-700': r.status === 'paid',
                                              'bg-red-100 text-red-700':     r.status === 'rejected',
                                          }">
                                        {{ r.status }}
                                    </span>
                                    <p v-if="r.rejection_reason" class="text-xs text-red-500 mt-0.5 max-w-40 truncate">{{ r.rejection_reason }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <span v-if="r.xendit_reference" class="text-xs font-mono text-gray-500 break-all">{{ r.xendit_reference }}</span>
                                    <span v-else class="text-xs text-gray-300">—</span>
                                </td>
                                <td class="px-5 py-3 text-right text-xs text-gray-400 whitespace-nowrap">{{ r.requested_at }}</td>
                                <td class="px-5 py-3 text-right text-xs text-gray-400 whitespace-nowrap">{{ r.processed_at ?? '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <div v-if="r.status === 'pending'" class="flex items-center justify-end gap-2">
                                        <button @click="approveRequest(r.id)"
                                                class="text-xs bg-green-600 hover:bg-green-700 text-white font-semibold px-3 py-1.5 rounded-lg transition-colors">
                                            Approve
                                        </button>
                                        <button @click="rejectingId = rejectingId === r.id ? null : r.id"
                                                class="text-xs bg-red-50 hover:bg-red-100 text-red-600 font-semibold px-3 py-1.5 rounded-lg transition-colors border border-red-200">
                                            Reject
                                        </button>
                                    </div>
                                    <div v-else-if="r.status === 'approved'" class="flex items-center justify-end">
                                        <button @click="markRequestPaid(r.id)"
                                                class="text-xs bg-green-600 hover:bg-green-700 text-white font-semibold px-3 py-1.5 rounded-lg transition-colors">
                                            Mark Paid
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Inline reject form -->
                            <tr v-if="rejectingId === r.id" class="bg-red-50">
                                <td colspan="11" class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <input v-model="rejectReason" type="text" placeholder="Reason (optional)"
                                               class="flex-1 max-w-sm border border-red-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400" />
                                        <button @click="confirmReject(r.id)"
                                                class="text-xs bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-1.5 rounded-lg transition-colors">
                                            Confirm Reject
                                        </button>
                                        <button @click="rejectingId = null; rejectReason = ''"
                                                class="text-xs text-gray-500 hover:text-gray-700">Cancel</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Community Owners tab ─────────────────────────────────────────── -->
        <div v-else-if="activeTab === 'owners'">
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <h2 class="font-semibold text-gray-800">Community Owners</h2>
                        <div class="flex gap-0.5 bg-gray-100 rounded-lg p-0.5">
                            <button v-for="s in statusTabs" :key="s.key" @click="ownerStatus = s.key"
                                    class="px-3 py-1 text-xs font-semibold rounded-md transition-all"
                                    :class="ownerStatus === s.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                {{ s.label }}
                            </button>
                        </div>
                    </div>
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

                <div v-if="filteredOwners.length === 0" class="px-5 py-10 text-center text-sm text-gray-400">
                    No {{ ownerStatus === 'all' ? '' : ownerStatus }} owner payouts.
                </div>

                <div v-else>
                    <div v-for="owner in filteredOwners" :key="owner.user_id"
                         class="border-b border-gray-100 last:border-0">
                        <!-- Owner header -->
                        <div class="px-5 py-3 bg-gray-50 flex items-center justify-between">
                            <div>
                                <span class="font-semibold text-gray-900 text-sm">{{ owner.name }}</span>
                                <span v-if="owner.is_pro" class="ml-1.5 text-xs font-bold bg-indigo-600 text-white px-2 py-0.5 rounded-full">⭐ Pro</span>
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
                                    <th class="text-right px-5 py-2 text-xs font-semibold text-gray-500">Platform Fee</th>
                                    <th class="text-right px-5 py-2 text-xs font-semibold text-gray-500">Commissions</th>
                                    <th class="text-right px-5 py-2 text-xs font-semibold text-gray-500">Earned</th>
                                    <th class="text-right px-5 py-2 text-xs font-semibold text-gray-500">Available to Payout</th>
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
                                    <td class="px-5 py-2.5 text-right">
                                        <span :class="c.available_payout > 0 ? 'text-blue-700 font-semibold' : 'text-gray-400'">
                                            ₱{{ fmt(c.available_payout) }}
                                        </span>
                                    </td>
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
                    <div class="flex items-center gap-3">
                        <h2 class="font-semibold text-gray-800">Affiliates</h2>
                        <div class="flex gap-0.5 bg-gray-100 rounded-lg p-0.5">
                            <button v-for="s in statusTabs" :key="s.key" @click="affiliateStatus = s.key"
                                    class="px-3 py-1 text-xs font-semibold rounded-md transition-all"
                                    :class="affiliateStatus === s.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                {{ s.label }}
                            </button>
                        </div>
                    </div>
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

                <div v-if="filteredAffiliates.length === 0" class="px-5 py-10 text-center text-sm text-gray-400">
                    No {{ affiliateStatus === 'all' ? '' : affiliateStatus }} affiliate payouts.
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
                        <tr v-for="a in filteredAffiliates" :key="a.id" class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <input v-if="a.can_disburse && a.pending > 0"
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
import { ref, reactive, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    owners:         Array,
    affiliates:     Array,
    payoutRequests: Array,
    stats:          Object,
    xenditBalance:  Number,
})

const activeTab = ref('requests')
const tabs = computed(() => [
    { key: 'requests',   label: `Payout Requests${props.stats.payout_requests_pending > 0 ? ` (${props.stats.payout_requests_pending})` : ''}` },
    { key: 'owners',     label: 'Community Owners' },
    { key: 'affiliates', label: 'Affiliates' },
])

const requestStatus = ref('pending')
const requestStatusTabs = [
    { key: 'pending',  label: 'Pending' },
    { key: 'approved', label: 'Approved' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'all',      label: 'All' },
]
const filteredRequests = computed(() => {
    if (requestStatus.value === 'all') return props.payoutRequests
    return props.payoutRequests.filter(r => r.status === requestStatus.value)
})

const rejectingId  = ref(null)
const rejectReason = ref('')

function approveRequest(id) {
    router.post(`/admin/payout-requests/${id}/approve`)
}

function markRequestPaid(id) {
    router.post(`/admin/payout-requests/${id}/mark-paid`)
}

function confirmReject(id) {
    router.post(`/admin/payout-requests/${id}/reject`, { reason: rejectReason.value }, {
        onSuccess: () => { rejectingId.value = null; rejectReason.value = '' },
    })
}

const ownerStatus     = ref('pending')
const affiliateStatus = ref('pending')
const statusTabs = [
    { key: 'all',     label: 'All' },
    { key: 'pending', label: 'Pending' },
    { key: 'paid',    label: 'Paid' },
]

const totalPending  = computed(() => (props.stats.owners_pending ?? 0) + (props.stats.affiliates_pending ?? 0))
const netPosition   = computed(() => (props.xenditBalance ?? 0) - totalPending.value)

const filteredOwners = computed(() => {
    if (ownerStatus.value === 'pending') return props.owners.filter(o => o.total_pending > 0)
    if (ownerStatus.value === 'paid')    return props.owners.filter(o => o.total_pending <= 0)
    return props.owners
})

const filteredAffiliates = computed(() => {
    if (affiliateStatus.value === 'pending') return props.affiliates.filter(a => a.pending > 0)
    if (affiliateStatus.value === 'paid')    return props.affiliates.filter(a => a.pending <= 0)
    return props.affiliates
})

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
