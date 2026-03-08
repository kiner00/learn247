<template>
    <AppLayout title="Affiliate Analytics">
        <div class="max-w-5xl">

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                        <Link href="/my-affiliates" class="hover:text-indigo-600 transition-colors">Affiliates</Link>
                        <span>/</span>
                        <span>Analytics</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900">Affiliate Analytics</h1>
                </div>

                <!-- Filters -->
                <div class="flex items-center gap-2">
                    <!-- Community filter -->
                    <select
                        :value="communityId ?? ''"
                        @change="applyFilter('community', $event.target.value || null)"
                        class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">All communities</option>
                        <option v-for="c in communities" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>

                    <!-- Period filter -->
                    <div class="flex rounded-lg border border-gray-200 overflow-hidden">
                        <button
                            v-for="p in PERIODS"
                            :key="p.value"
                            @click="applyFilter('period', p.value)"
                            class="px-3 py-2 text-sm font-medium transition-colors"
                            :class="period === p.value
                                ? 'bg-indigo-600 text-white'
                                : 'bg-white text-gray-600 hover:bg-gray-50'"
                        >
                            {{ p.label }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
                <div class="bg-white border border-gray-200 rounded-2xl p-4 text-center">
                    <p class="text-xs text-gray-400 mb-1">Total Earned</p>
                    <p class="text-lg font-bold text-gray-900">₱{{ fmt(summary.total_earned) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-4 text-center">
                    <p class="text-xs text-gray-400 mb-1">Paid Out</p>
                    <p class="text-lg font-bold text-green-600">₱{{ fmt(summary.total_paid) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-4 text-center">
                    <p class="text-xs text-gray-400 mb-1">Pending</p>
                    <p class="text-lg font-bold text-amber-500">₱{{ fmt(summary.total_pending) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-4 text-center">
                    <p class="text-xs text-gray-400 mb-1">Conversions</p>
                    <p class="text-lg font-bold text-gray-900">{{ summary.total_conversions }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-4 text-center">
                    <p class="text-xs text-gray-400 mb-1">Avg / Referral</p>
                    <p class="text-lg font-bold text-indigo-600">₱{{ fmt(summary.avg_per_referral) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-4 text-center">
                    <p class="text-xs text-gray-400 mb-1">Best Month</p>
                    <p class="text-sm font-bold text-gray-900">{{ summary.best_month ?? '—' }}</p>
                    <p v-if="summary.best_month_total" class="text-xs text-gray-500">₱{{ fmt(summary.best_month_total) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

                <!-- Earnings chart -->
                <div class="lg:col-span-2 bg-white border border-gray-200 rounded-2xl p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">
                        Earnings — {{ PERIODS.find(p => p.value === period)?.label }}
                    </h2>
                    <BarChart :data="chartData" />
                </div>

                <!-- By community breakdown -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">By Community (All Time)</h2>
                    <div v-if="byComm.length" class="space-y-3">
                        <div v-for="row in byComm" :key="row.community">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-700 font-medium truncate max-w-[60%]">{{ row.community }}</span>
                                <span class="text-gray-500">₱{{ fmt(row.total) }}</span>
                            </div>
                            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-indigo-400 rounded-full"
                                    :style="{ width: `${(row.total / byCommMax) * 100}%` }"
                                />
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">No data yet.</p>
                </div>
            </div>

            <!-- Conversion history table -->
            <div class="bg-white border border-gray-200 rounded-2xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-gray-900">
                        Conversion History
                        <span class="text-gray-400 font-normal ml-1">(last 100)</span>
                    </h2>
                    <div class="flex gap-2">
                        <button
                            v-for="s in STATUS_FILTERS"
                            :key="s.value"
                            @click="statusFilter = s.value"
                            class="px-2.5 py-1 text-xs rounded-lg border transition-colors"
                            :class="statusFilter === s.value
                                ? 'bg-indigo-50 border-indigo-200 text-indigo-700 font-medium'
                                : 'border-gray-200 text-gray-500 hover:bg-gray-50'"
                        >
                            {{ s.label }}
                        </button>
                    </div>
                </div>

                <div v-if="filteredConversions.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-gray-100">
                                <th class="pb-2 text-xs font-semibold text-gray-400">Date</th>
                                <th class="pb-2 text-xs font-semibold text-gray-400">Community</th>
                                <th class="pb-2 text-xs font-semibold text-gray-400 text-right">Sale</th>
                                <th class="pb-2 text-xs font-semibold text-gray-400 text-right">Commission</th>
                                <th class="pb-2 text-xs font-semibold text-gray-400">Status</th>
                                <th class="pb-2 text-xs font-semibold text-gray-400">Paid At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="c in filteredConversions" :key="c.id" class="hover:bg-gray-50">
                                <td class="py-2.5 text-gray-600 whitespace-nowrap">{{ c.date }}</td>
                                <td class="py-2.5 text-gray-800 font-medium max-w-[150px] truncate">{{ c.community }}</td>
                                <td class="py-2.5 text-gray-600 text-right whitespace-nowrap">₱{{ fmt(c.sale_amount) }}</td>
                                <td class="py-2.5 text-indigo-700 font-semibold text-right whitespace-nowrap">₱{{ fmt(c.commission_amount) }}</td>
                                <td class="py-2.5">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium"
                                        :class="{
                                            'bg-green-100 text-green-700': c.status === 'paid',
                                            'bg-amber-100 text-amber-700': c.status === 'pending',
                                            'bg-red-100 text-red-600':    c.status === 'failed',
                                        }"
                                    >
                                        {{ c.status }}
                                    </span>
                                </td>
                                <td class="py-2.5 text-gray-400 whitespace-nowrap">{{ c.paid_at ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-gray-400 py-4 text-center">No conversions found.</p>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BarChart from './BarChart.vue';

const props = defineProps({
    period:      String,
    communityId: { type: Number, default: null },
    summary:     Object,
    chartData:   Array,
    conversions: Array,
    communities: Array,
    byComm:      Array,
});

const PERIODS = [
    { value: 'week',  label: 'Week'  },
    { value: 'month', label: 'Month' },
    { value: 'year',  label: 'Year'  },
    { value: 'all',   label: 'All'   },
];

const STATUS_FILTERS = [
    { value: 'all',     label: 'All'     },
    { value: 'pending', label: 'Pending' },
    { value: 'paid',    label: 'Paid'    },
];

const statusFilter = ref('all');

const filteredConversions = computed(() =>
    statusFilter.value === 'all'
        ? props.conversions
        : props.conversions.filter(c => c.status === statusFilter.value)
);

const byCommMax = computed(() =>
    props.byComm.length ? Math.max(...props.byComm.map(r => r.total)) : 1
);

function fmt(n) {
    return Number(n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function applyFilter(key, value) {
    const params = {
        period:    props.period,
        community: props.communityId,
    };
    params[key] = value;
    if (!params.community) delete params.community;
    router.get('/my-affiliates/analytics', params, { preserveScroll: true });
}
</script>
