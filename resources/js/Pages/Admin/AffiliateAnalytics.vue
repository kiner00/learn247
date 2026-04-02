<template>
    <AdminLayout title="Affiliate Analytics">
        <div class="mb-6">
            <h1 class="text-2xl font-black text-gray-900">Affiliate Analytics</h1>
            <p class="text-sm text-gray-500 mt-0.5">Conversions, commissions & payouts per affiliate</p>
        </div>

        <!-- Summary Totals -->
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 mb-1">Total Conversions</p>
                <p class="text-xl font-black text-gray-900">{{ totals.conversions.toLocaleString() }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 mb-1">Total Sales Referred</p>
                <p class="text-xl font-black text-gray-900">₱{{ fmt(totals.total_sales) }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 mb-1">Total Commission Earned</p>
                <p class="text-xl font-black text-purple-600">₱{{ fmt(totals.total_commission) }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 mb-1">Commission Paid Out</p>
                <p class="text-xl font-black text-green-600">₱{{ fmt(totals.commission_paid) }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 mb-1">Pending Commission</p>
                <p class="text-xl font-black text-amber-500">₱{{ fmt(totals.commission_pending) }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 mb-1">In-Flight Requests</p>
                <p class="text-xl font-black text-blue-500">₱{{ fmt(totals.in_flight) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Pending/approved payout requests</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm mb-6">
            <div class="px-5 py-4 flex flex-wrap items-center gap-3">
                <input
                    v-model="searchInput"
                    @input="applyFilters"
                    type="text"
                    placeholder="Search affiliate, community or code..."
                    class="flex-1 min-w-48 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                />
                <select
                    v-model="statusFilter"
                    @change="applyFilters"
                    class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                >
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select
                    v-model="sortBy"
                    class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                >
                    <option value="total_commission">Sort: Total Commission</option>
                    <option value="conversions">Sort: Conversions</option>
                    <option value="total_sales">Sort: Total Sales</option>
                    <option value="available_now">Sort: Available Now</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Affiliate / Community</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Code</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Conversions</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Sales</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Commission</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Paid Out</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Pending</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">In-Flight</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Available Now</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Payout Via</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in sortedAffiliates" :key="row.affiliate_id" class="hover:bg-gray-50 transition-colors">
                            <!-- Affiliate / Community -->
                            <td class="px-5 py-3">
                                <p class="font-semibold text-gray-900 text-sm">{{ row.user_name }}</p>
                                <p class="text-xs text-gray-400">{{ row.user_email }}</p>
                                <Link v-if="row.community_slug" :href="`/communities/${row.community_slug}`" class="text-xs text-indigo-600 hover:underline">
                                    {{ row.community_name }}
                                </Link>
                                <span v-else class="text-xs text-gray-400">{{ row.community_name }}</span>
                            </td>

                            <!-- Code -->
                            <td class="px-5 py-3">
                                <span class="font-mono text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded-lg">{{ row.affiliate_code }}</span>
                            </td>

                            <!-- Status -->
                            <td class="px-5 py-3">
                                <span class="text-xs font-bold px-2.5 py-1 rounded-full" :class="{
                                    'bg-green-100 text-green-700':  row.affiliate_status === 'active',
                                    'bg-gray-100 text-gray-500':    row.affiliate_status === 'inactive',
                                }">
                                    {{ row.affiliate_status.toUpperCase() }}
                                </span>
                            </td>

                            <!-- Conversions -->
                            <td class="px-5 py-3 text-right font-semibold text-gray-800">{{ row.conversions }}</td>

                            <!-- Total Sales -->
                            <td class="px-5 py-3 text-right text-gray-700 font-medium">₱{{ fmt(row.total_sales) }}</td>

                            <!-- Total Commission -->
                            <td class="px-5 py-3 text-right text-purple-600 font-bold">₱{{ fmt(row.total_commission) }}</td>

                            <!-- Paid Out -->
                            <td class="px-5 py-3 text-right text-green-600 font-medium">₱{{ fmt(row.commission_paid) }}</td>

                            <!-- Pending -->
                            <td class="px-5 py-3 text-right text-amber-500 font-medium">₱{{ fmt(row.commission_pending) }}</td>

                            <!-- In-Flight -->
                            <td class="px-5 py-3 text-right text-blue-500 font-medium text-xs">₱{{ fmt(row.in_flight) }}</td>

                            <!-- Available Now -->
                            <td class="px-5 py-3 text-right">
                                <span class="font-bold" :class="row.available_now > 0 ? 'text-green-600' : 'text-gray-400'">
                                    ₱{{ fmt(row.available_now) }}
                                </span>
                            </td>

                            <!-- Payout Via -->
                            <td class="px-5 py-3">
                                <span v-if="row.payout_method" class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                    :class="{
                                        'bg-blue-100 text-blue-700':   row.payout_method === 'gcash',
                                        'bg-green-100 text-green-700': row.payout_method === 'maya',
                                    }">
                                    {{ row.payout_method.toUpperCase() }}
                                </span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                        </tr>

                        <tr v-if="!sortedAffiliates.length">
                            <td colspan="11" class="px-5 py-8 text-center text-xs text-gray-400">No affiliates found</td>
                        </tr>
                    </tbody>

                    <!-- Totals footer -->
                    <tfoot v-if="sortedAffiliates.length > 1">
                        <tr class="bg-gray-50 border-t-2 border-gray-200 font-bold text-sm">
                            <td class="px-5 py-3 text-xs font-bold text-gray-500 uppercase" colspan="3">Totals ({{ sortedAffiliates.length }})</td>
                            <td class="px-5 py-3 text-right text-gray-800">{{ filteredTotals.conversions }}</td>
                            <td class="px-5 py-3 text-right text-gray-800">₱{{ fmt(filteredTotals.total_sales) }}</td>
                            <td class="px-5 py-3 text-right text-purple-600">₱{{ fmt(filteredTotals.total_commission) }}</td>
                            <td class="px-5 py-3 text-right text-green-600">₱{{ fmt(filteredTotals.commission_paid) }}</td>
                            <td class="px-5 py-3 text-right text-amber-500">₱{{ fmt(filteredTotals.commission_pending) }}</td>
                            <td class="px-5 py-3 text-right text-blue-500">₱{{ fmt(filteredTotals.in_flight) }}</td>
                            <td class="px-5 py-3 text-right text-green-600">₱{{ fmt(filteredTotals.available_now) }}</td>
                            <td></td>
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
    affiliates: { type: Array, default: () => [] },
    totals:     { type: Object, default: () => ({}) },
    filters:    { type: Object, default: () => ({}) },
});

const searchInput  = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');
const sortBy       = ref('total_commission');

let debounceTimer = null;
function applyFilters() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/admin/affiliate-analytics', {
            search: searchInput.value || undefined,
            status: statusFilter.value || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}

const sortedAffiliates = computed(() => {
    return [...props.affiliates].sort((a, b) => b[sortBy.value] - a[sortBy.value]);
});

const filteredTotals = computed(() => {
    const rows = sortedAffiliates.value;
    return {
        conversions:        rows.reduce((s, r) => s + r.conversions, 0),
        total_sales:        rows.reduce((s, r) => s + r.total_sales, 0),
        total_commission:   rows.reduce((s, r) => s + r.total_commission, 0),
        commission_paid:    rows.reduce((s, r) => s + r.commission_paid, 0),
        commission_pending: rows.reduce((s, r) => s + r.commission_pending, 0),
        in_flight:          rows.reduce((s, r) => s + r.in_flight, 0),
        available_now:      rows.reduce((s, r) => s + r.available_now, 0),
    };
});

function fmt(val) {
    return Number(val ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
