<template>
    <AdminLayout title="Admin Dashboard">
        <div class="mb-6">
            <h1 class="text-2xl font-black text-gray-900">Dashboard</h1>
            <p class="text-sm text-gray-500 mt-0.5">Platform overview</p>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4 mb-8">
            <div
                v-for="stat in statCards"
                :key="stat.label"
                class="bg-white border border-gray-200 rounded-2xl p-3.5 sm:p-5 shadow-sm"
            >
                <div class="flex items-center gap-2 sm:gap-3 mb-2 sm:mb-3">
                    <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl flex items-center justify-center shrink-0" :class="stat.iconBg">
                        <span class="text-base sm:text-lg">{{ stat.icon }}</span>
                    </div>
                    <p class="text-[11px] sm:text-xs font-medium text-gray-500 leading-tight">{{ stat.label }}</p>
                </div>
                <p class="text-xl sm:text-2xl font-black text-gray-900" :title="stat.fullValue">{{ stat.value }}</p>
                <p v-if="stat.sub" class="text-[10px] sm:text-xs text-gray-400 mt-0.5">{{ stat.sub }}</p>
            </div>
        </div>

        <!-- Revenue Breakdown -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm mb-8">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-bold text-gray-900">Platform Revenue Breakdown</h2>
                <p class="text-xs text-gray-400 mt-0.5">Based on all collected payments</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-px bg-gray-100">
                <div class="bg-white px-4 sm:px-5 py-3 sm:py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Gross Revenue</p>
                    <p class="text-lg sm:text-xl font-black text-gray-900">₱{{ fmt(revenue.gross) }}</p>
                    <p class="text-[10px] sm:text-xs text-gray-400 mt-0.5">Total collected across all communities</p>
                </div>
                <div class="bg-white px-4 sm:px-5 py-3 sm:py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Platform Fees</p>
                    <p class="text-lg sm:text-xl font-black text-indigo-600">₱{{ fmt(revenue.platform_fee) }}</p>
                    <p class="text-[10px] sm:text-xs text-gray-400 mt-0.5">Platform income</p>
                </div>
                <div class="bg-white px-4 sm:px-5 py-3 sm:py-4">
                    <p class="text-xs font-medium text-gray-500 mb-1">Creator Net Income</p>
                    <p class="text-lg sm:text-xl font-black text-green-600">₱{{ fmt(revenue.creator_net) }}</p>
                    <p class="text-[10px] sm:text-xs text-gray-400 mt-0.5">After fees & affiliate commissions</p>
                </div>
            </div>

            <div class="px-5 py-3 border-t border-gray-100 bg-indigo-50">
                <p class="text-xs font-semibold text-indigo-700">Affiliate Commissions</p>
            </div>
            <div class="grid grid-cols-3 gap-px bg-gray-100">
                <div class="bg-white px-3 sm:px-5 py-3 sm:py-4">
                    <p class="text-[10px] sm:text-xs font-medium text-gray-500 mb-1">Total Earned</p>
                    <p class="text-sm sm:text-lg font-black text-indigo-600">₱{{ fmt(revenue.affiliate_commission_total) }}</p>
                </div>
                <div class="bg-white px-3 sm:px-5 py-3 sm:py-4">
                    <p class="text-[10px] sm:text-xs font-medium text-gray-500 mb-1">Paid Out</p>
                    <p class="text-sm sm:text-lg font-black text-green-600">₱{{ fmt(revenue.affiliate_commission_paid) }}</p>
                </div>
                <div class="bg-white px-3 sm:px-5 py-3 sm:py-4">
                    <p class="text-[10px] sm:text-xs font-medium text-gray-500 mb-1">Pending</p>
                    <p class="text-sm sm:text-lg font-black text-amber-500">₱{{ fmt(revenue.affiliate_commission_pending) }}</p>
                </div>
            </div>
        </div>
        <!-- Creator Plan Pricing -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm mb-8">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-bold text-gray-900">Creator Plan Pricing</h2>
                <p class="text-xs text-gray-400 mt-0.5">Set the monthly and annual prices for Basic and Pro plans shown on the creator upgrade page</p>
            </div>
            <form @submit.prevent="savePlanPricing" class="px-4 sm:px-5 py-4 flex flex-wrap items-end gap-3 sm:gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Basic Plan (₱/mo)</label>
                    <input
                        v-model.number="planForm.basic_price"
                        type="number" min="0" step="1"
                        class="w-36 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Basic Plan (₱/year)</label>
                    <input
                        v-model.number="planForm.basic_annual_price"
                        type="number" min="0" step="1"
                        class="w-36 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Pro Plan (₱/mo)</label>
                    <input
                        v-model.number="planForm.pro_price"
                        type="number" min="0" step="1"
                        class="w-36 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Pro Plan (₱/year)</label>
                    <input
                        v-model.number="planForm.pro_annual_price"
                        type="number" min="0" step="1"
                        class="w-36 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="planForm.processing"
                    class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                >
                    Save Pricing
                </button>
                <span v-if="planForm.recentlySuccessful" class="text-xs text-green-600 font-medium">Saved!</span>
            </form>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Recent Communities -->
            <div class="lg:col-span-2 bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                <div class="px-4 sm:px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-gray-900">Recent Communities</h2>
                    <Link href="/communities" class="text-xs text-indigo-600 hover:underline">View all</Link>
                </div>
                <!-- Mobile: card layout -->
                <div class="sm:hidden divide-y divide-gray-100">
                    <div v-for="c in recentCommunities" :key="c.id" class="px-4 py-3 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <div>
                                <Link :href="`/communities/${c.slug}`" class="font-medium text-gray-900 hover:text-indigo-600 text-sm">
                                    {{ c.name }}
                                </Link>
                                <p class="text-xs text-gray-400">by {{ c.owner?.name ?? '—' }}</p>
                            </div>
                            <button
                                @click="toggleFeatured(c.slug)"
                                class="text-xs font-semibold px-2 py-0.5 rounded-full border transition-colors shrink-0 ml-2"
                                :class="c.is_featured
                                    ? 'bg-indigo-50 text-indigo-700 border-indigo-200'
                                    : 'bg-gray-50 text-gray-500 border-gray-200'"
                            >
                                {{ c.is_featured ? '⭐' : 'Feature' }}
                            </button>
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <span v-if="c.category" class="font-medium px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">{{ c.category }}</span>
                            <span class="text-gray-500">{{ c.members_count }} members</span>
                            <span :class="c.price > 0 ? 'text-amber-600 font-medium' : 'text-green-600'">
                                {{ c.price > 0 ? `₱${Number(c.price).toLocaleString()}` : 'Free' }}
                            </span>
                        </div>
                    </div>
                </div>
                <!-- Desktop: table layout -->
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50">
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Community</th>
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Category</th>
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Members</th>
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Price</th>
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Created</th>
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Feature</th>
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
                                <td class="px-5 py-3">
                                    <button
                                        @click="toggleFeatured(c.slug)"
                                        class="text-xs font-semibold px-2 py-0.5 rounded-full border transition-colors"
                                        :class="c.is_featured
                                            ? 'bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-red-50 hover:text-red-600 hover:border-red-200'
                                            : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200'"
                                    >
                                        {{ c.is_featured ? '⭐ Unfeature' : 'Feature' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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
                            <div class="w-8 h-8 rounded-full shrink-0 overflow-hidden bg-linear-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-xs font-bold text-white">
                                <img v-if="u.avatar" :src="u.avatar" :alt="u.name" class="w-full h-full object-cover" />
                                <span v-else>{{ u.name.charAt(0).toUpperCase() }}</span>
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

        <!-- Recent Payments -->
        <div class="mt-6 bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <div class="px-4 sm:px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-bold text-gray-900">Recent Payments</h2>
                <p class="text-[10px] sm:text-xs text-gray-400 mt-0.5">Last 20 webhook-processed payments</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">User</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Community</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Xendit ID</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Paid At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="p in recentPayments" :key="p.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3">
                                <p class="text-sm font-medium text-gray-900">{{ p.user_name ?? '—' }}</p>
                                <p class="text-xs text-gray-400">{{ p.user_email ?? '—' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <Link v-if="p.community_slug" :href="`/communities/${p.community_slug}`" class="text-xs text-indigo-600 hover:underline">
                                    {{ p.community_name }}
                                </Link>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="px-5 py-3 text-sm font-semibold text-gray-800">
                                ₱{{ p.amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                            </td>
                            <td class="px-5 py-3">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                    :class="{
                                        'bg-green-100 text-green-700': p.status === 'paid',
                                        'bg-red-100 text-red-700': p.status === 'failed',
                                        'bg-gray-100 text-gray-600': p.status === 'expired',
                                        'bg-yellow-100 text-yellow-700': p.status === 'pending',
                                    }">
                                    {{ p.status.toUpperCase() }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <span class="text-xs font-mono text-gray-500 break-all">{{ p.xendit_event_id ?? '—' }}</span>
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-400 whitespace-nowrap">{{ p.paid_at ?? p.created_at }}</td>
                        </tr>
                        <tr v-if="!recentPayments?.length">
                            <td colspan="6" class="px-5 py-6 text-center text-xs text-gray-400">No payments yet</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pending Password Setup -->
        <div v-if="pendingOnboarding?.data?.length" class="mt-6 bg-white border border-orange-200 rounded-2xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-orange-100 flex items-center gap-3">
                <span class="text-base">⚠️</span>
                <div>
                    <h2 class="text-sm font-bold text-gray-900">Pending Password Setup <span class="ml-1 text-orange-600">({{ pendingOnboarding.total }})</span></h2>
                    <p class="text-xs text-gray-400">Users who paid via affiliate link but haven't logged in yet</p>
                </div>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50">
                    <tr class="text-xs font-semibold text-gray-400 uppercase tracking-wide">
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Email</th>
                        <th class="px-5 py-3">Community</th>
                        <th class="px-5 py-3">Joined</th>
                        <th class="px-5 py-3">Waiting</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="u in pendingOnboarding.data" :key="u.id" class="hover:bg-orange-50 transition-colors">
                        <td class="px-5 py-3 text-sm font-medium text-gray-800">{{ u.name }}</td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ u.email }}</td>
                        <td class="px-5 py-3 text-xs">
                            <Link v-if="u.community_slug" :href="`/communities/${u.community_slug}`" class="text-indigo-600 hover:underline">{{ u.community }}</Link>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ u.joined_at }}</td>
                        <td class="px-5 py-3">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                :class="u.days_since >= 5 ? 'bg-red-100 text-red-700' : u.days_since >= 3 ? 'bg-orange-100 text-orange-700' : 'bg-yellow-100 text-yellow-700'">
                                {{ u.days_since }}d
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <button @click="resend(u.id)"
                                :disabled="resending === u.id"
                                class="text-xs font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-40 transition-colors">
                                {{ resending === u.id ? 'Sending...' : 'Resend Email' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
            <!-- Pagination -->
            <div v-if="pendingOnboarding.last_page > 1" class="px-5 py-3 border-t border-gray-100 flex justify-center gap-1">
                <Link
                    v-for="link in pendingOnboarding.links"
                    :key="link.label"
                    :href="link.url ?? ''"
                    v-html="link.label"
                    class="px-2.5 py-1 text-xs rounded-lg border transition-colors"
                    :class="link.active
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : link.url
                            ? 'border-gray-200 text-gray-600 hover:border-indigo-300'
                            : 'border-gray-100 text-gray-300 cursor-default'"
                />
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    stats:               Object,
    revenue:             Object,
    byCategory:          Array,
    recentCommunities:   Array,
    recentUsers:         Array,
    xenditBalance:       Number,
    pendingOnboarding:   { type: Object, default: () => ({ data: [], total: 0, last_page: 1, links: [] }) },
    creatorPlanPricing:  { type: Object, default: () => ({ basic_price: 499, pro_price: 1999 }) },
    recentPayments:      { type: Array, default: () => [] },
});

function toggleFeatured(communityId) {
    router.post(`/admin/communities/${communityId}/toggle-featured`, {}, { preserveScroll: true });
}

const resending = ref(null);
function resend(userId) {
    resending.value = userId;
    router.post(`/admin/onboarding/${userId}/resend`, {}, {
        preserveScroll: true,
        onFinish: () => { resending.value = null; },
    });
}

const statCards = computed(() => [
    {
        label:     'Total Users',
        value:     compact(props.stats.total_users),
        fullValue: props.stats.total_users.toLocaleString(),
        icon:      '👤',
        iconBg:    'bg-blue-50',
    },
    {
        label:     'Communities',
        value:     compact(props.stats.total_communities),
        fullValue: props.stats.total_communities.toLocaleString(),
        icon:      '🏘️',
        iconBg:    'bg-indigo-50',
    },
    {
        label:     'Memberships',
        value:     compact(props.stats.total_members),
        fullValue: props.stats.total_members.toLocaleString(),
        icon:      '🤝',
        iconBg:    'bg-purple-50',
    },
    {
        label:     'Paid Subs',
        value:     compact(props.stats.active_subscriptions),
        fullValue: props.stats.active_subscriptions.toLocaleString(),
        icon:      '💳',
        iconBg:    'bg-amber-50',
    },
    {
        label:     'Monthly Revenue',
        value:     compact(props.stats.monthly_revenue, '₱'),
        fullValue: `₱${fmt(props.stats.monthly_revenue)}`,
        icon:      '💰',
        iconBg:    'bg-green-50',
        sub:       'from active subscriptions',
    },
    {
        label:     'Xendit Balance',
        value:     compact(props.xenditBalance ?? 0, '₱'),
        fullValue: `₱${fmt(props.xenditBalance ?? 0)}`,
        icon:      '🏦',
        iconBg:    'bg-teal-50',
        sub:       'available cash balance',
    },
]);

const page = usePage();
const planForm = useForm({
    basic_price:        props.creatorPlanPricing.basic_price,
    pro_price:          props.creatorPlanPricing.pro_price,
    basic_annual_price: props.creatorPlanPricing.basic_annual_price,
    pro_annual_price:   props.creatorPlanPricing.pro_annual_price,
});

function savePlanPricing() {
    planForm.patch('/admin/creator-plan-pricing', { preserveScroll: true });
}

const maxCategoryTotal = computed(() =>
    Math.max(1, ...props.byCategory.map((r) => r.total))
);

function fmt(val) {
    return Number(val ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function compact(val, prefix = '') {
    const n = Number(val ?? 0);
    if (n >= 1_000_000_000) return `${prefix}${(n / 1_000_000_000).toFixed(2)}B`;
    if (n >= 1_000_000)     return `${prefix}${(n / 1_000_000).toFixed(2)}M`;
    if (n >= 10_000)        return `${prefix}${(n / 1_000).toFixed(1)}K`;
    return `${prefix}${n.toLocaleString()}`;
}
</script>
