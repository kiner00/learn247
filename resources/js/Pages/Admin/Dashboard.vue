<template>
    <AppLayout title="Admin Dashboard">
        <div class="mb-6">
            <h1 class="text-2xl font-black text-gray-900">Admin Dashboard</h1>
            <p class="text-sm text-gray-500 mt-0.5">Platform overview</p>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            <div
                v-for="stat in statCards"
                :key="stat.label"
                class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm"
            >
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" :class="stat.iconBg">
                        <span class="text-lg">{{ stat.icon }}</span>
                    </div>
                    <p class="text-xs font-medium text-gray-500">{{ stat.label }}</p>
                </div>
                <p class="text-2xl font-black text-gray-900">{{ stat.value }}</p>
                <p v-if="stat.sub" class="text-xs text-gray-400 mt-0.5">{{ stat.sub }}</p>
            </div>
        </div>

        <!-- Revenue Breakdown -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm mb-8">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-bold text-gray-900">Platform Revenue Breakdown</h2>
                <p class="text-xs text-gray-400 mt-0.5">Based on all collected payments</p>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-3 gap-px bg-gray-100">
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Gross Revenue</p>
                    <p class="text-xl font-black text-gray-900">₱{{ fmt(revenue.gross) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Total collected across all communities</p>
                </div>
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Platform Fees (3%)</p>
                    <p class="text-xl font-black text-indigo-600">₱{{ fmt(revenue.platform_fee) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Platform income</p>
                </div>
                <div class="bg-white px-5 py-4 col-span-2 lg:col-span-1">
                    <p class="text-xs font-medium text-gray-500 mb-1">Creator Net Income</p>
                    <p class="text-xl font-black text-green-600">₱{{ fmt(revenue.creator_net) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">After fees & affiliate commissions</p>
                </div>
            </div>

            <div class="px-5 py-3 border-t border-gray-100 bg-indigo-50">
                <p class="text-xs font-semibold text-indigo-700">Affiliate Commissions</p>
            </div>
            <div class="grid grid-cols-3 gap-px bg-gray-100">
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Total Earned</p>
                    <p class="text-lg font-black text-indigo-600">₱{{ fmt(revenue.affiliate_commission_total) }}</p>
                </div>
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Paid Out</p>
                    <p class="text-lg font-black text-green-600">₱{{ fmt(revenue.affiliate_commission_paid) }}</p>
                </div>
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Pending Payout</p>
                    <p class="text-lg font-black text-amber-500">₱{{ fmt(revenue.affiliate_commission_pending) }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Recent Communities -->
            <div class="lg:col-span-2 bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-gray-900">Recent Communities</h2>
                    <Link href="/communities" class="text-xs text-indigo-600 hover:underline">View all</Link>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Community</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Category</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Members</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Price</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="c in recentCommunities" :key="c.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3">
                                <div>
                                    <Link :href="`/communities/${c.slug}`" class="font-medium text-gray-900 hover:text-indigo-600 transition-colors text-sm">
                                        {{ c.name }}
                                    </Link>
                                    <p class="text-xs text-gray-400">by {{ c.owner?.name ?? '—' }}</p>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span v-if="c.category" class="text-xs font-medium px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                                    {{ c.category }}
                                </span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="px-5 py-3 text-gray-600 text-xs font-medium">{{ c.members_count }}</td>
                            <td class="px-5 py-3">
                                <span class="text-xs font-medium" :class="c.price > 0 ? 'text-amber-600' : 'text-green-600'">
                                    {{ c.price > 0 ? `₱${Number(c.price).toLocaleString()}` : 'Free' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-400 text-xs">{{ c.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Right column -->
            <div class="space-y-4">

                <!-- Recent Users -->
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-bold text-gray-900">Recent Users</h2>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <div v-for="u in recentUsers" :key="u.id" class="px-5 py-3 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-linear-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-xs font-bold text-white shrink-0">
                                {{ u.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ u.name }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ u.email }}</p>
                            </div>
                            <span class="text-xs text-gray-400 shrink-0">{{ u.created_at }}</span>
                        </div>
                    </div>
                </div>

                <!-- Communities by Category -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                    <h2 class="text-sm font-bold text-gray-900 mb-4">By Category</h2>
                    <div class="space-y-2.5">
                        <div v-for="row in byCategory" :key="row.category" class="flex items-center gap-2">
                            <span class="text-xs text-gray-600 w-28 truncate shrink-0">{{ row.category }}</span>
                            <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-indigo-500 rounded-full"
                                    :style="{ width: `${Math.round((row.total / maxCategoryTotal) * 100)}%` }"
                                />
                            </div>
                            <span class="text-xs font-semibold text-gray-700 w-5 text-right shrink-0">{{ row.total }}</span>
                        </div>
                        <p v-if="!byCategory.length" class="text-xs text-gray-400 text-center py-2">No data yet</p>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stats:              Object,
    revenue:            Object,
    byCategory:         Array,
    recentCommunities:  Array,
    recentUsers:        Array,
});

const statCards = computed(() => [
    {
        label:  'Total Users',
        value:  props.stats.total_users.toLocaleString(),
        icon:   '👤',
        iconBg: 'bg-blue-50',
    },
    {
        label:  'Communities',
        value:  props.stats.total_communities.toLocaleString(),
        icon:   '🏘️',
        iconBg: 'bg-indigo-50',
    },
    {
        label:  'Memberships',
        value:  props.stats.total_members.toLocaleString(),
        icon:   '🤝',
        iconBg: 'bg-purple-50',
    },
    {
        label:  'Paid Subs',
        value:  props.stats.active_subscriptions.toLocaleString(),
        icon:   '💳',
        iconBg: 'bg-amber-50',
    },
    {
        label:  'Monthly Revenue',
        value:  `₱${Number(props.stats.monthly_revenue).toLocaleString()}`,
        icon:   '💰',
        iconBg: 'bg-green-50',
        sub:    'from active subscriptions',
    },
]);

const maxCategoryTotal = computed(() =>
    Math.max(1, ...props.byCategory.map((r) => r.total))
);

function fmt(val) {
    return Number(val ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
