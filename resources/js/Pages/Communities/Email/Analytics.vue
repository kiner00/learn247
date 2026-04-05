<script setup>
import { ref, onMounted, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const props = defineProps({
    community: Object,
    dailyStats: Array,
    totals: Object,
    days: Number,
});

const { communityPath } = useCommunityUrl(props.community.slug);
const chartCanvas = ref(null);
let chartInstance = null;

function pct(value, total) {
    if (!total) return '0%';
    return (Math.round((value / total) * 1000) / 10) + '%';
}

function changeDays(d) {
    router.get(communityPath('/email-analytics'), { days: d }, { preserveState: true, preserveScroll: true });
}

onMounted(() => {
    if (!chartCanvas.value || !props.dailyStats.length) return;

    chartInstance = new Chart(chartCanvas.value, {
        type: 'line',
        data: {
            labels: props.dailyStats.map(s => s.date),
            datasets: [
                {
                    label: 'Sent',
                    data: props.dailyStats.map(s => s.sent),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.3,
                },
                {
                    label: 'Opened',
                    data: props.dailyStats.map(s => s.opened),
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    fill: true,
                    tension: 0.3,
                },
                {
                    label: 'Clicked',
                    data: props.dailyStats.map(s => s.clicked),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.3,
                },
                {
                    label: 'Bounced',
                    data: props.dailyStats.map(s => s.bounced),
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    fill: true,
                    tension: 0.3,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16 } },
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { grid: { display: false } },
            },
        },
    });
});
</script>

<template>
    <AppLayout :title="`${community.name} · Email Analytics`">
        <div class="max-w-5xl mx-auto px-4 py-8">
            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                <Link :href="communityPath()" class="hover:text-indigo-600">{{ community.name }}</Link>
                <span>/</span>
                <Link :href="communityPath('/email-campaigns')" class="hover:text-indigo-600">Email</Link>
                <span>/</span>
                <span>Analytics</span>
            </div>

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Email Analytics</h1>
                <div class="flex gap-2">
                    <button v-for="d in [7, 14, 30, 60, 90]" :key="d" @click="changeDays(d)"
                        class="px-3 py-1.5 text-xs font-medium rounded-full border transition-colors"
                        :class="days === d ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:border-indigo-300'">
                        {{ d }}d
                    </button>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-8">
                <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
                    <p class="text-xl font-bold text-gray-900">{{ totals.sent.toLocaleString() }}</p>
                    <p class="text-xs text-gray-500">Sent</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
                    <p class="text-xl font-bold text-green-600">{{ totals.delivered.toLocaleString() }}</p>
                    <p class="text-xs text-gray-500">Delivered {{ pct(totals.delivered, totals.sent) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
                    <p class="text-xl font-bold text-blue-600">{{ totals.opened.toLocaleString() }}</p>
                    <p class="text-xs text-gray-500">Opened {{ pct(totals.opened, totals.sent) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
                    <p class="text-xl font-bold text-indigo-600">{{ totals.clicked.toLocaleString() }}</p>
                    <p class="text-xs text-gray-500">Clicked {{ pct(totals.clicked, totals.sent) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
                    <p class="text-xl font-bold text-red-600">{{ totals.bounced.toLocaleString() }}</p>
                    <p class="text-xs text-gray-500">Bounced {{ pct(totals.bounced, totals.sent) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
                    <p class="text-xl font-bold text-orange-600">{{ totals.failed.toLocaleString() }}</p>
                    <p class="text-xs text-gray-500">Failed</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
                    <p class="text-xl font-bold text-gray-500">{{ totals.unsubscribed.toLocaleString() }}</p>
                    <p class="text-xs text-gray-500">Unsubscribed</p>
                </div>
            </div>

            <!-- Chart -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-8">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Daily Email Activity</h2>
                <div v-if="dailyStats.length" class="h-[300px]">
                    <canvas ref="chartCanvas"></canvas>
                </div>
                <div v-else class="h-[200px] flex items-center justify-center text-sm text-gray-400">
                    No data for the selected period. Send some emails first!
                </div>
            </div>

            <!-- Navigation -->
            <div class="flex gap-4">
                <Link :href="communityPath('/email-campaigns')"
                    class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    ← Broadcast Campaigns
                </Link>
                <Link :href="communityPath('/email-sequences')"
                    class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    Email Sequences
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
