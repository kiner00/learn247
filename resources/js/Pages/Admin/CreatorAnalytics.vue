<template>
    <AdminLayout title="Creator Analytics">
        <div class="mb-6">
            <h1 class="text-2xl font-black text-gray-900">Creator Analytics</h1>
            <p class="text-sm text-gray-500 mt-0.5">Revenue, profitability & payouts per creator</p>
        </div>

        <!-- Summary Totals -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 mb-1">Total Gross</p>
                <p class="text-xl font-black text-gray-900">₱{{ fmt(totals.gross) }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 mb-1">Platform Fees Collected</p>
                <p class="text-xl font-black text-indigo-600">₱{{ fmt(totals.platform_fee) }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 mb-1">Xendit Fees Absorbed</p>
                <p class="text-xl font-black text-red-500">₱{{ fmt(totals.processing_fee) }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 mb-1">Net Platform Profit</p>
                <p class="text-xl font-black" :class="totals.net_platform_profit >= 0 ? 'text-green-600' : 'text-red-600'">
                    ₱{{ fmt(totals.net_platform_profit) }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 mb-1">Affiliate Commissions</p>
                <p class="text-xl font-black text-purple-600">₱{{ fmt(totals.affiliate_commission) }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 mb-1">Creator Total Earned</p>
                <p class="text-xl font-black text-gray-900">₱{{ fmt(totals.creator_earned) }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 mb-1">Creator Paid Out</p>
                <p class="text-xl font-black text-green-600">₱{{ fmt(totals.creator_paid) }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 mb-1">Creator Pending</p>
                <p class="text-xl font-black text-amber-500">₱{{ fmt(totals.creator_pending) }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm mb-6">
            <div class="px-5 py-4 flex flex-wrap items-center gap-3">
                <input
                    v-model="searchInput"
                    @input="applyFilters"
                    type="text"
                    placeholder="Search creator or community..."
                    class="flex-1 min-w-48 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                />
                <select
                    v-model="planFilter"
                    @change="applyFilters"
                    class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                >
                    <option value="">All Plans</option>
                    <option value="free">Free (9.8%)</option>
                    <option value="basic">Basic (4.9%)</option>
                    <option value="pro">Pro (2.9%)</option>
                </select>
                <select
                    v-model="sortBy"
                    class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                >
                    <option value="gross">Sort: Gross Revenue</option>
                    <option value="net_platform_profit">Sort: Net Profit</option>
                    <option value="subscribers">Sort: Subscribers</option>
                    <option value="creator_pending">Sort: Creator Pending</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Creator / Community</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Plan</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Subs</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Gross</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Xendit Fee</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Platform Fee</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Net Profit</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Affiliate</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Creator Earned</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Paid Out</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Pending</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in sortedCreators" :key="row.community_id" class="hover:bg-gray-50 transition-colors">
                            <!-- Creator / Community -->
                            <td class="px-5 py-3">
                                <Link :href="`/communities/${row.community_slug}`" class="font-semibold text-gray-900 hover:text-indigo-600 transition-colors text-sm">
                                    {{ row.community_name }}
                                </Link>
                                <p class="text-xs text-gray-400">{{ row.creator_name }} · {{ row.creator_email }}</p>
                                <p v-if="row.community_price > 0" class="text-xs text-amber-600 font-medium">₱{{ row.community_price.toLocaleString() }}/mo</p>
                                <p v-else class="text-xs text-green-600 font-medium">Free community</p>
                            </td>

                            <!-- Plan -->
                            <td class="px-5 py-3">
                                <span class="text-xs font-bold px-2.5 py-1 rounded-full" :class="{
                                    'bg-purple-100 text-purple-700': row.creator_plan === 'pro',
                                    'bg-blue-100 text-blue-700':   row.creator_plan === 'basic',
                                    'bg-gray-100 text-gray-600':   row.creator_plan === 'free',
                                }">
                                    {{ row.creator_plan.toUpperCase() }}
                                </span>
                            </td>

                            <!-- Subscribers -->
                            <td class="px-5 py-3 text-right font-semibold text-gray-800">{{ row.subscribers }}</td>

                            <!-- Gross -->
                            <td class="px-5 py-3 text-right font-semibold text-gray-800">₱{{ fmt(row.gross) }}</td>

                            <!-- Xendit Fee (absorbed) -->
                            <td class="px-5 py-3 text-right text-red-500 font-medium text-xs">₱{{ fmt(row.processing_fee) }}</td>

                            <!-- Platform Fee -->
                            <td class="px-5 py-3 text-right text-indigo-600 font-semibold">₱{{ fmt(row.platform_fee) }}</td>

                            <!-- Net Platform Profit -->
                            <td class="px-5 py-3 text-right">
                                <span class="font-bold text-sm" :class="row.is_profitable ? 'text-green-600' : 'text-red-600'">
                                    {{ row.is_profitable ? '' : '−' }}₱{{ fmt(Math.abs(row.net_platform_profit)) }}
                                </span>
                                <span v-if="!row.is_profitable" class="ml-1 text-xs text-red-400">⚠</span>
                            </td>

                            <!-- Affiliate Commission -->
                            <td class="px-5 py-3 text-right text-purple-600 font-medium text-xs">₱{{ fmt(row.affiliate_commission) }}</td>

                            <!-- Creator Earned -->
                            <td class="px-5 py-3 text-right text-gray-800 font-semibold">₱{{ fmt(row.creator_earned) }}</td>

                            <!-- Creator Paid Out -->
                            <td class="px-5 py-3 text-right text-green-600 font-medium">₱{{ fmt(row.creator_paid) }}</td>

                            <!-- Creator Pending -->
                            <td class="px-5 py-3 text-right">
                                <span class="font-bold" :class="row.creator_pending > 0 ? 'text-amber-500' : 'text-gray-400'">
                                    ₱{{ fmt(row.creator_pending) }}
                                </span>
                            </td>
                        </tr>

                        <tr v-if="!sortedCreators.length">
                            <td colspan="11" class="px-5 py-8 text-center text-xs text-gray-400">No creators found</td>
                        </tr>
                    </tbody>

                    <!-- Totals footer -->
                    <tfoot v-if="sortedCreators.length > 1">
                        <tr class="bg-gray-50 border-t-2 border-gray-200 font-bold text-sm">
                            <td class="px-5 py-3 text-xs font-bold text-gray-500 uppercase">Totals ({{ sortedCreators.length }})</td>
                            <td></td>
                            <td class="px-5 py-3 text-right text-gray-800">{{ filteredTotals.subscribers }}</td>
                            <td class="px-5 py-3 text-right text-gray-800">₱{{ fmt(filteredTotals.gross) }}</td>
                            <td class="px-5 py-3 text-right text-red-500">₱{{ fmt(filteredTotals.processing_fee) }}</td>
                            <td class="px-5 py-3 text-right text-indigo-600">₱{{ fmt(filteredTotals.platform_fee) }}</td>
                            <td class="px-5 py-3 text-right" :class="filteredTotals.net_platform_profit >= 0 ? 'text-green-600' : 'text-red-600'">
                                ₱{{ fmt(filteredTotals.net_platform_profit) }}
                            </td>
                            <td class="px-5 py-3 text-right text-purple-600">₱{{ fmt(filteredTotals.affiliate_commission) }}</td>
                            <td class="px-5 py-3 text-right text-gray-800">₱{{ fmt(filteredTotals.creator_earned) }}</td>
                            <td class="px-5 py-3 text-right text-green-600">₱{{ fmt(filteredTotals.creator_paid) }}</td>
                            <td class="px-5 py-3 text-right text-amber-500">₱{{ fmt(filteredTotals.creator_pending) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    creators: { type: Array, default: () => [] },
    totals:   { type: Object, default: () => ({}) },
    filters:  { type: Object, default: () => ({}) },
});

const searchInput = ref(props.filters.search ?? '');
const planFilter  = ref(props.filters.plan ?? '');
const sortBy      = ref('gross');

let debounceTimer = null;
function applyFilters() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/admin/creator-analytics', {
            search: searchInput.value || undefined,
            plan:   planFilter.value  || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}

const sortedCreators = computed(() => {
    return [...props.creators].sort((a, b) => b[sortBy.value] - a[sortBy.value]);
});

const filteredTotals = computed(() => {
    const rows = sortedCreators.value;
    return {
        subscribers:          rows.reduce((s, r) => s + r.subscribers, 0),
        gross:                rows.reduce((s, r) => s + r.gross, 0),
        processing_fee:       rows.reduce((s, r) => s + r.processing_fee, 0),
        platform_fee:         rows.reduce((s, r) => s + r.platform_fee, 0),
        net_platform_profit:  rows.reduce((s, r) => s + r.net_platform_profit, 0),
        affiliate_commission: rows.reduce((s, r) => s + r.affiliate_commission, 0),
        creator_earned:       rows.reduce((s, r) => s + r.creator_earned, 0),
        creator_paid:         rows.reduce((s, r) => s + r.creator_paid, 0),
        creator_pending:      rows.reduce((s, r) => s + r.creator_pending, 0),
    };
});

function fmt(val) {
    return Number(val ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
