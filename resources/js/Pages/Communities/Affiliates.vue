<template>
    <AppLayout :title="`Affiliates — ${community.name}`">
        <div class="max-w-5xl mx-auto px-4 py-8">
            <!-- Header -->
            <div class="flex items-center gap-3 mb-8">
                <Link :href="`/communities/${community.slug}/settings`"
                      class="text-gray-400 hover:text-gray-600 text-sm">← Settings</Link>
                <span class="text-gray-300">/</span>
                <h1 class="text-2xl font-bold text-gray-900">Affiliate Program</h1>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4 mb-8">
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Affiliates</p>
                    <p class="text-2xl font-bold text-gray-900">{{ stats.total_affiliates }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Total Commissions</p>
                    <p class="text-2xl font-bold text-gray-900">₱{{ Number(stats.total_commissions).toFixed(2) }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Paid Out</p>
                    <p class="text-2xl font-bold text-gray-900">₱{{ Number(stats.total_paid_out).toFixed(2) }}</p>
                </div>
            </div>

            <!-- Affiliates list -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-8">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h2 class="font-semibold text-gray-800">Affiliates</h2>
                </div>
                <div v-if="affiliates.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                    No affiliates yet. Members will see a "Become an Affiliate" button on the community page.
                </div>
                <table v-else class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-5 py-3 font-semibold text-gray-600">Name</th>
                            <th class="text-left px-5 py-3 font-semibold text-gray-600">Code</th>
                            <th class="text-right px-5 py-3 font-semibold text-gray-600">Earned</th>
                            <th class="text-right px-5 py-3 font-semibold text-gray-600">Paid</th>
                            <th class="text-right px-5 py-3 font-semibold text-gray-600">Pending</th>
                            <th class="text-left px-5 py-3 font-semibold text-gray-600">Payout Info</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="a in affiliates" :key="a.id" class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-medium text-gray-900">
                                {{ a.user.name }}
                                <span class="text-gray-400 font-normal ml-1 text-xs">{{ a.user.email }}</span>
                            </td>
                            <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ a.code }}</td>
                            <td class="px-5 py-3 text-right font-medium">₱{{ Number(a.total_earned).toFixed(2) }}</td>
                            <td class="px-5 py-3 text-right text-gray-500">₱{{ Number(a.total_paid).toFixed(2) }}</td>
                            <td class="px-5 py-3 text-right">
                                <span :class="a.pending_amount > 0 ? 'text-green-700 font-semibold' : 'text-gray-400'">
                                    ₱{{ Number(a.pending_amount).toFixed(2) }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <span v-if="a.payout_method" class="text-xs text-gray-700">
                                    <span class="font-semibold uppercase">{{ a.payout_method }}</span>
                                    · {{ a.payout_details }}
                                </span>
                                <span v-else class="text-xs text-gray-400 italic">Not set</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Conversions -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h2 class="font-semibold text-gray-800">Conversions</h2>
                </div>
                <div v-if="conversions.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                    No conversions yet.
                </div>
                <table v-else class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-5 py-3 font-semibold text-gray-600">Date</th>
                            <th class="text-left px-5 py-3 font-semibold text-gray-600">Referred</th>
                            <th class="text-left px-5 py-3 font-semibold text-gray-600">Affiliate</th>
                            <th class="text-right px-5 py-3 font-semibold text-gray-600">Sale</th>
                            <th class="text-right px-5 py-3 font-semibold text-gray-600">Platform (3%)</th>
                            <th class="text-right px-5 py-3 font-semibold text-gray-600">Commission</th>
                            <th class="text-right px-5 py-3 font-semibold text-gray-600">You Get</th>
                            <th class="text-center px-5 py-3 font-semibold text-gray-600">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="c in conversions" :key="c.id" class="hover:bg-gray-50">
                            <td class="px-5 py-3 text-gray-500 whitespace-nowrap">{{ c.date }}</td>
                            <td class="px-5 py-3 text-gray-900">{{ c.referred_user }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ c.affiliate_name }}</td>
                            <td class="px-5 py-3 text-right font-medium">₱{{ Number(c.sale_amount).toFixed(2) }}</td>
                            <td class="px-5 py-3 text-right text-red-500">−₱{{ Number(c.platform_fee).toFixed(2) }}</td>
                            <td class="px-5 py-3 text-right text-orange-600">−₱{{ Number(c.commission_amount).toFixed(2) }}</td>
                            <td class="px-5 py-3 text-right text-green-700 font-semibold">₱{{ Number(c.creator_amount).toFixed(2) }}</td>
                            <td class="px-5 py-3 text-center">
                                <span :class="c.status === 'paid'
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-yellow-100 text-yellow-700'"
                                      class="text-xs font-medium px-2 py-0.5 rounded-full capitalize">
                                    {{ c.status }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <template v-if="c.status === 'pending'">
                                    <button v-if="c.can_disburse"
                                            @click="disburse(c.id)"
                                            class="text-xs text-green-700 hover:text-green-900 font-semibold whitespace-nowrap mr-2">
                                        Pay via Xendit
                                    </button>
                                    <button @click="markPaid(c.id)"
                                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium whitespace-nowrap">
                                        Mark Paid
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    community:   Object,
    affiliates:  Array,
    conversions: Array,
    stats:       Object,
})

function markPaid(conversionId) {
    router.patch(`/affiliate-conversions/${conversionId}/paid`)
}

function disburse(conversionId) {
    router.post(`/affiliate-conversions/${conversionId}/disburse`)
}
</script>
