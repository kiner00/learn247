<template>
    <AppLayout :title="`${community.name} · Analytics`" :community="community">
        <div class="max-w-4xl">
            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                <Link :href="`/communities/${community.slug}`" class="hover:text-indigo-600 transition-colors">
                    {{ community.name }}
                </Link>
                <span>/</span>
                <span>Analytics</span>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-8">Community Analytics</h1>

            <!-- Stat cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div
                    v-for="card in statCards"
                    :key="card.label"
                    class="bg-white border border-gray-200 rounded-2xl p-5"
                >
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" :class="card.iconBg">
                            <span class="text-base">{{ card.icon }}</span>
                        </div>
                        <p class="text-xs font-medium text-gray-500">{{ card.label }}</p>
                    </div>
                    <p class="text-2xl font-black text-gray-900">{{ card.value }}</p>
                    <p v-if="card.sub" class="text-xs text-gray-400 mt-0.5">{{ card.sub }}</p>
                </div>
            </div>

            <!-- Request Payout -->
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden mb-6">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">Payout</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Request your earnings. Processed within 15 days.</p>
                    </div>
                    <!-- Pending request badge -->
                    <div v-if="payout.pending_request" class="flex flex-col items-end gap-1">
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-amber-100 text-amber-700">
                            ⏳ Pending · {{ curr }}{{ fmt(payout.pending_request.amount) }}
                        </span>
                        <p class="text-xs text-gray-400">Requested {{ payout.pending_request.created_at }}</p>
                    </div>
                    <!-- Request button -->
                    <div v-else class="flex flex-col items-end gap-1">
                        <div v-if="payout.eligible_now > 0">
                            <form @submit.prevent="submitPayoutRequest">
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden">
                                        <span class="px-3 py-2 text-sm text-gray-500 bg-gray-50 border-r border-gray-200">{{ curr }}</span>
                                        <input v-model="payoutAmount" type="number" step="0.01" :min="1" :max="payout.eligible_now"
                                            class="w-28 px-3 py-2 text-sm focus:outline-none" />
                                    </div>
                                    <button type="submit" :disabled="payoutForm.processing"
                                        class="px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-xl hover:bg-green-700 disabled:opacity-50 transition-colors">
                                        Request Payout
                                    </button>
                                </div>
                            </form>
                            <p class="text-xs text-gray-400 mt-1 text-right">Available: {{ curr }}{{ fmt(payout.eligible_now) }}</p>
                        </div>
                        <div v-else class="text-right">
                            <p class="text-xs text-gray-500 font-medium">No eligible earnings yet</p>
                            <p v-if="payout.next_eligible_date" class="text-xs text-gray-400 mt-0.5">
                                Next eligible: {{ payout.next_eligible_date }}
                                <span class="text-gray-300">({{ curr }}{{ fmt(payout.locked_amount) }} locked)</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payout History -->
            <div v-if="payout_history?.length" class="bg-white border border-gray-200 rounded-2xl overflow-hidden mb-6">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">Payout History</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Past disbursements for this community</p>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Reference</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                            <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="p in payout_history" :key="p.reference" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3 text-xs text-gray-500">{{ p.paid_at ?? '—' }}</td>
                            <td class="px-5 py-3 text-xs text-gray-400 font-mono">{{ p.reference ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                    :class="{
                                        'bg-green-100 text-green-700': p.status === 'succeeded',
                                        'bg-amber-100 text-amber-700': p.status === 'accepted',
                                        'bg-red-100 text-red-500': p.status === 'failed',
                                        'bg-gray-100 text-gray-500': !['succeeded','accepted','failed'].includes(p.status),
                                    }">
                                    {{ p.status }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right text-sm font-bold text-gray-800">{{ curr }}{{ fmt(p.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Revenue Breakdown -->
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden mb-6">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">Revenue Breakdown</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Based on actual payments collected</p>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-3 gap-px bg-gray-100">
                    <!-- Gross Revenue -->
                    <div class="bg-white px-5 py-4">
                        <p class="text-xs font-medium text-gray-500 mb-1">Gross Revenue</p>
                        <p class="text-xl font-black text-gray-900">{{ curr }}{{ fmt(revenue.gross) }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Total collected</p>
                    </div>

                    <!-- Platform Fee -->
                    <div class="bg-white px-5 py-4">
                        <p class="text-xs font-medium text-gray-500 mb-1">Platform Fee ({{ (revenue.platform_fee_rate * 100).toFixed(1) }}%)</p>
                        <p class="text-xl font-black text-red-500">{{ curr }}{{ fmt(revenue.platform_fee) }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Deducted from gross</p>
                    </div>

                    <!-- Creator Net -->
                    <div class="bg-white px-5 py-4 col-span-2 lg:col-span-1">
                        <p class="text-xs font-medium text-gray-500 mb-1">Your Net Income</p>
                        <p class="text-xl font-black text-green-600">{{ curr }}{{ fmt(revenue.creator_net) }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">After all deductions</p>
                    </div>
                </div>

                <!-- Affiliate split (only if there are affiliate-attributed sales) -->
                <template v-if="revenue.has_affiliate_data">
                    <div class="px-5 py-3 border-t border-gray-100 bg-indigo-50">
                        <p class="text-xs font-semibold text-indigo-700">Affiliate Commission Breakdown</p>
                    </div>
                    <div class="grid grid-cols-3 gap-px bg-gray-100">
                        <div class="bg-white px-5 py-4">
                            <p class="text-xs font-medium text-gray-500 mb-1">Total Earned by Affiliates</p>
                            <p class="text-lg font-black text-indigo-600">{{ curr }}{{ fmt(revenue.affiliate_commission_earned) }}</p>
                        </div>
                        <div class="bg-white px-5 py-4">
                            <p class="text-xs font-medium text-gray-500 mb-1">Paid Out</p>
                            <p class="text-lg font-black text-green-600">{{ curr }}{{ fmt(revenue.affiliate_commission_paid) }}</p>
                        </div>
                        <div class="bg-white px-5 py-4">
                            <p class="text-xs font-medium text-gray-500 mb-1">Pending Payout</p>
                            <p class="text-lg font-black text-amber-500">{{ curr }}{{ fmt(revenue.affiliate_commission_pending) }}</p>
                            <p v-if="revenue.affiliate_commission_pending > 0" class="text-xs text-amber-500 mt-0.5">Needs to be paid</p>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Classroom analytics -->
            <div v-if="course_stats?.length" class="bg-white border border-gray-200 rounded-2xl overflow-hidden mb-6">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">Classroom Analytics</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Completion and quiz performance per course</p>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Course</th>
                            <th class="text-center px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Lessons</th>
                            <th class="text-center px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Completions</th>
                            <th class="text-center px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Finished</th>
                            <th class="text-center px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Quiz Pass Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="c in course_stats" :key="c.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3 font-medium text-gray-900">{{ c.title }}</td>
                            <td class="px-4 py-3 text-center text-gray-500">{{ c.total_lessons }}</td>
                            <td class="px-4 py-3 text-center text-gray-500">{{ c.total_completions }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full">
                                    {{ c.completed_members }} members
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="c.quiz_pass_rate !== null" class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                    :class="c.quiz_pass_rate >= 70 ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">
                                    {{ c.quiz_pass_rate }}%
                                </span>
                                <span v-else class="text-xs text-gray-300">No quizzes</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Subscribers table -->
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">Subscribers</h2>
                    <p class="text-xs text-gray-400 mt-0.5">All paid subscription records for this community</p>
                </div>

                <div v-if="subscribers.length === 0" class="px-5 py-10 text-center text-sm text-gray-400">
                    No subscribers yet. Once members pay, they'll appear here.
                </div>

                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Member</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Expires</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Subscribed</th>
                            <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="s in subscribers" :key="s.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3">
                                <div>
                                    <p class="font-medium text-gray-900 text-sm">{{ s.user?.name ?? '—' }}</p>
                                    <p class="text-xs text-gray-400">{{ s.user?.email ?? '' }}</p>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span
                                    class="text-xs font-medium px-2 py-0.5 rounded-full"
                                    :class="statusClass(s.status)"
                                >
                                    {{ s.status }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-500">{{ s.expires_at ?? '—' }}</td>
                            <td class="px-5 py-3 text-xs text-gray-400">{{ s.created_at }}</td>
                            <td class="px-5 py-3 text-right text-xs font-semibold text-gray-700">
                                <span v-if="s.amount_paid !== null">{{ curr }}{{ Number(s.amount_paid).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                                <span v-else class="text-gray-400 font-normal">Free</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
</div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    community:    Object,
    stats:        Object,
    revenue:      Object,
    payout:         Object,
    payout_history: Array,
    subscribers:    Array,
    course_stats:   Array,
});

const curr = props.community.currency === 'USD' ? '$' : '₱';

const payoutAmount = ref(props.payout?.eligible_now ?? 0);
const payoutForm   = useForm({ amount: null });

function submitPayoutRequest() {
    payoutForm.amount = payoutAmount.value;
    payoutForm.post(`/creator/payout-request/${props.community.id}`, {
        preserveScroll: true,
        onError: (errors) => alert(Object.values(errors)[0]),
    });
}

function fmt(val) {
    return Number(val ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const statCards = computed(() => [
    {
        label:  'Monthly Revenue',
        value:  `${curr}${Number(props.stats.monthly_revenue).toLocaleString()}`,
        icon:   '💰',
        iconBg: 'bg-green-50',
        sub:    'from active subscriptions',
    },
    {
        label:  'Active Subscribers',
        value:  props.stats.active_subscriptions.toLocaleString(),
        icon:   '💳',
        iconBg: 'bg-amber-50',
        sub:    'paid & active',
    },
    {
        label:  'Total Members',
        value:  props.stats.total_members.toLocaleString(),
        icon:   '👥',
        iconBg: 'bg-indigo-50',
        sub:    'free + paid',
    },
    {
        label:  'Free Members',
        value:  props.stats.free_members.toLocaleString(),
        icon:   '🎁',
        iconBg: 'bg-blue-50',
        sub:    'no active subscription',
    },
]);

function statusClass(status) {
    return {
        active:    'bg-green-100 text-green-700',
        pending:   'bg-yellow-100 text-yellow-700',
        expired:   'bg-gray-100 text-gray-500',
        cancelled: 'bg-red-100 text-red-500',
    }[status] ?? 'bg-gray-100 text-gray-500';
}
</script>
