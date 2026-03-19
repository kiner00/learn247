<template>
    <AppLayout title="Creator Dashboard">
        <div class="mb-6">
            <h1 class="text-2xl font-black text-gray-900">Creator Dashboard</h1>
            <p class="text-sm text-gray-500 mt-0.5">Your earnings and payout requests</p>
        </div>

        <!-- No payout method warning -->
        <div v-if="!payoutMethod" class="mb-6 bg-amber-50 border border-amber-200 rounded-2xl px-5 py-4 flex items-start gap-3">
            <span class="text-xl mt-0.5">⚠️</span>
            <div>
                <p class="text-sm font-semibold text-amber-800">No payout method set</p>
                <p class="text-xs text-amber-700 mt-0.5">
                    You need to set your GCash or Maya number before you can request a payout.
                    <Link href="/account/settings" class="underline font-semibold">Go to Account Settings →</Link>
                </p>
            </div>
        </div>

        <!-- Advanced Analytics (Pro) -->
        <div v-if="isPro && analytics" class="mb-6 space-y-4">
            <!-- KPI row -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                    <p class="text-xs font-medium text-gray-500 mb-1">MRR</p>
                    <p class="text-2xl font-black text-gray-900">₱{{ fmt(analytics.mrr) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Monthly recurring revenue</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                    <p class="text-xs font-medium text-gray-500 mb-1">Retention Rate</p>
                    <p class="text-2xl font-black" :class="analytics.retentionRate >= 80 ? 'text-green-600' : analytics.retentionRate >= 60 ? 'text-amber-500' : 'text-red-500'">
                        {{ analytics.retentionRate }}%
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">Last 30 days</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                    <p class="text-xs font-medium text-gray-500 mb-1">New Members (this month)</p>
                    <p class="text-2xl font-black text-indigo-600">{{ analytics.newMembers.at(-1) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">vs {{ analytics.newMembers.at(-2) }} last month</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                    <p class="text-xs font-medium text-gray-500 mb-1">Churn (this month)</p>
                    <p class="text-2xl font-black text-red-500">{{ analytics.churn.at(-1) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Expired / cancelled</p>
                </div>
            </div>

            <!-- Charts row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                    <p class="text-sm font-bold text-gray-900 mb-4">Revenue Trend (6 months)</p>
                    <canvas ref="revenueChart" height="200"></canvas>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                    <p class="text-sm font-bold text-gray-900 mb-4">Members vs Churn (6 months)</p>
                    <canvas ref="memberChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Analytics locked (non-Pro) -->
        <div v-else class="mb-6 bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-gray-900">Advanced Analytics</p>
                    <p class="text-xs text-gray-400 mt-0.5">Revenue trends, retention rate, churn insights</p>
                </div>
                <span class="text-xs font-bold bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full">⭐ Pro</span>
            </div>
            <div class="px-5 py-10 text-center">
                <p class="text-3xl mb-3">📊</p>
                <p class="text-sm font-semibold text-gray-700 mb-1">Unlock Advanced Analytics</p>
                <p class="text-xs text-gray-400 mb-4">See MRR, retention rate, churn, and 6-month revenue & member growth charts.</p>
                <Link href="/creator/plan" class="inline-block px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                    Upgrade to Creator Pro →
                </Link>
            </div>
        </div>

        <!-- Communities -->
        <div v-if="communities.length === 0" class="bg-white border border-gray-200 rounded-2xl px-5 py-10 text-center text-sm text-gray-400">
            You have no paid communities yet.
        </div>

        <div v-for="c in communities" :key="c.community_id" class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm mb-6">
            <!-- Community header -->
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <Link :href="`/communities/${c.community_slug}`" class="font-bold text-gray-900 hover:text-indigo-600">
                        {{ c.community_name }}
                    </Link>
                    <span class="ml-2 text-xs text-gray-400">{{ c.members_count }} members</span>
                </div>
                <span v-if="payoutMethod" class="text-xs font-semibold uppercase bg-gray-100 text-gray-600 px-2.5 py-1 rounded-full">
                    {{ payoutMethod }} · {{ payoutDetails }}
                </span>
            </div>

            <!-- Earnings grid -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-px bg-gray-100">
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Gross Revenue</p>
                    <p class="text-lg font-black text-gray-900">₱{{ fmt(c.gross) }}</p>
                </div>
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Platform Fee (15%)</p>
                    <p class="text-lg font-black text-red-500">−₱{{ fmt(c.platform_fee) }}</p>
                </div>
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Commissions</p>
                    <p class="text-lg font-black text-orange-500">−₱{{ fmt(c.commissions) }}</p>
                </div>
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Total Earned</p>
                    <p class="text-lg font-black text-gray-900">₱{{ fmt(c.earned) }}</p>
                </div>
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Paid Out</p>
                    <p class="text-lg font-black text-green-600">₱{{ fmt(c.paid) }}</p>
                </div>
            </div>

            <!-- Eligibility row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-px bg-gray-100">
                <div class="bg-green-50 px-5 py-4">
                    <p class="text-xs font-semibold text-green-700 uppercase tracking-wide mb-1">Available to Request</p>
                    <p class="text-2xl font-black text-green-700">₱{{ fmt(c.eligible_now) }}</p>
                    <p class="text-xs text-green-600 mt-0.5">Payments older than 15 days, after fees</p>
                </div>
                <div class="bg-gray-50 px-5 py-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Locked (< 15 days)</p>
                    <p class="text-2xl font-black text-gray-400">₱{{ fmt(c.locked_amount) }}</p>
                    <p v-if="c.next_eligible_date" class="text-xs text-gray-400 mt-0.5">
                        Next unlock: {{ c.next_eligible_date }}
                    </p>
                </div>
            </div>

            <!-- Pending request notice OR request form -->
            <div class="px-5 py-4 border-t border-gray-100">
                <!-- Already has pending request -->
                <div v-if="c.pending_request" class="flex items-center justify-between bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold text-amber-800">Payout request pending</p>
                        <p class="text-xs text-amber-600 mt-0.5">₱{{ fmt(c.pending_request.amount) }} — waiting for admin approval</p>
                    </div>
                    <span class="text-xs font-bold uppercase text-amber-600 bg-amber-100 px-3 py-1 rounded-full">Pending</span>
                </div>

                <!-- Request form -->
                <div v-else-if="c.eligible_now > 0 && payoutMethod">
                    <p class="text-xs font-semibold text-gray-700 mb-3">Request a payout</p>
                    <form @submit.prevent="submitRequest(c)" class="flex items-center gap-3">
                        <div class="relative flex-1 max-w-xs">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold">₱</span>
                            <input
                                v-model="requestAmounts[c.community_id]"
                                type="number"
                                step="0.01"
                                :min="1"
                                :max="c.eligible_now"
                                placeholder="0.00"
                                class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                        <button
                            type="button"
                            @click="requestAmounts[c.community_id] = c.eligible_now"
                            class="text-xs text-indigo-600 hover:underline font-semibold"
                        >
                            Max
                        </button>
                        <button
                            type="submit"
                            :disabled="submitting[c.community_id]"
                            class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors"
                        >
                            {{ submitting[c.community_id] ? 'Submitting...' : 'Request Payout' }}
                        </button>
                    </form>
                </div>

                <p v-else-if="!payoutMethod" class="text-xs text-gray-400 italic">Set a payout method to request payouts.</p>
                <p v-else class="text-xs text-gray-400 italic">No eligible earnings yet.</p>
            </div>

            <!-- Abandoned checkouts -->
            <div v-if="c.abandoned_payments.length > 0" class="border-t border-gray-100">
                <div class="px-5 py-3 bg-red-50 border-b border-red-100 flex items-center justify-between gap-3 flex-wrap">
                    <p class="text-xs font-semibold text-red-700 uppercase tracking-wide">Abandoned Checkouts</p>
                    <span class="text-xs text-red-500">Started checkout but did not pay</span>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-125">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500">Name</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500">Email</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500">Phone</th>
                            <th class="text-center px-5 py-2.5 text-xs font-semibold text-gray-500">Status</th>
                            <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-500">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="(a, i) in c.abandoned_payments" :key="i" class="hover:bg-gray-50">
                            <td class="px-5 py-2.5 font-medium text-gray-900">{{ a.name }}</td>
                            <td class="px-5 py-2.5 text-gray-600">{{ a.email }}</td>
                            <td class="px-5 py-2.5 text-gray-600">{{ a.phone ?? '—' }}</td>
                            <td class="px-5 py-2.5 text-center">
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full capitalize bg-red-100 text-red-700">
                                    {{ a.status }}
                                </span>
                            </td>
                            <td class="px-5 py-2.5 text-right text-gray-400 text-xs">{{ a.date }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Recent member payments -->
            <div v-if="c.recent_payments.length > 0" class="border-t border-gray-100">
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-100">
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Recent Member Payments</p>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-100">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500">Member</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500">Phone</th>
                            <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-500">Amount</th>
                            <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-500">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="(p, i) in c.recent_payments" :key="i" class="hover:bg-gray-50">
                            <td class="px-5 py-2.5">
                                <p class="font-medium text-gray-900">{{ p.member_name }}</p>
                                <p class="text-xs text-gray-400">{{ p.member_email }}</p>
                            </td>
                            <td class="px-5 py-2.5 text-sm text-gray-600">{{ p.member_phone ?? '—' }}</td>
                            <td class="px-5 py-2.5 text-right font-semibold text-gray-800">₱{{ fmt(p.amount) }}</td>
                            <td class="px-5 py-2.5 text-right text-gray-400 text-xs">{{ p.paid_at }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- Payout Request History -->
        <div v-if="requestHistory.length > 0" class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-bold text-gray-900">Payout Request History</h2>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-125">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Community</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500">Amount</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Status</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Note</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500">Requested</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="r in requestHistory" :key="r.id" class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-medium text-gray-900">{{ r.community_name }}</td>
                        <td class="px-5 py-3 text-right font-semibold">₱{{ fmt(r.amount) }}</td>
                        <td class="px-5 py-3">
                            <span class="text-xs font-bold uppercase px-2.5 py-1 rounded-full"
                                  :class="{
                                      'bg-amber-100 text-amber-700': r.status === 'pending',
                                      'bg-green-100 text-green-700': r.status === 'approved',
                                      'bg-red-100 text-red-700':    r.status === 'rejected',
                                  }">
                                {{ r.status }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ r.rejection_reason ?? '—' }}</td>
                        <td class="px-5 py-3 text-right text-xs text-gray-400">{{ r.requested_at }}</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Chart, registerables } from 'chart.js'
Chart.register(...registerables)

const props = defineProps({
    communities:    Array,
    requestHistory: Array,
    payoutMethod:   String,
    payoutDetails:  String,
    analytics:      { type: Object, default: null },
    isPro:          { type: Boolean, default: false },
})

const revenueChart = ref(null)
const memberChart  = ref(null)

onMounted(() => {
    if (!props.isPro || !props.analytics) return

    const { labels, revenue, newMembers, churn } = props.analytics

    new Chart(revenueChart.value, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Revenue (₱)',
                data: revenue,
                backgroundColor: 'rgba(99, 102, 241, 0.7)',
                borderRadius: 6,
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: (v) => `₱${Number(v).toLocaleString()}` },
                },
            },
        },
    })

    new Chart(memberChart.value, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'New Members',
                    data: newMembers,
                    backgroundColor: 'rgba(34, 197, 94, 0.7)',
                    borderRadius: 6,
                },
                {
                    label: 'Churn',
                    data: churn,
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    borderRadius: 6,
                },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
        },
    })
})

const requestAmounts = reactive({})
const submitting     = reactive({})

function fmt(val) {
    return Number(val ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function submitRequest(community) {
    const amount = requestAmounts[community.community_id]
    if (!amount || amount <= 0) return

    submitting[community.community_id] = true

    router.post(`/creator/payout-request/${community.community_id}`, { amount }, {
        onFinish: () => { submitting[community.community_id] = false },
    })
}
</script>
