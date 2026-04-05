<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmailNav from '@/Components/EmailNav.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const props = defineProps({
    community: Object,
    sends: Object,
    counts: Object,
    filter: String,
    search: String,
});

const { communityPath } = useCommunityUrl(props.community.slug);
const searchInput = ref(props.search ?? '');

const statusColors = {
    queued: 'bg-gray-100 text-gray-600',
    sent: 'bg-blue-100 text-blue-700',
    delivered: 'bg-green-100 text-green-700',
    opened: 'bg-emerald-100 text-emerald-700',
    clicked: 'bg-indigo-100 text-indigo-700',
    bounced: 'bg-red-100 text-red-700',
    complained: 'bg-orange-100 text-orange-700',
    failed: 'bg-red-100 text-red-700',
};

const filterTabs = [
    { value: '', label: 'All', countKey: 'total' },
    { value: 'delivered', label: 'Delivered', countKey: 'delivered' },
    { value: 'sent', label: 'Sent', countKey: 'sent' },
    { value: 'bounced', label: 'Bounced', countKey: 'bounced' },
    { value: 'failed', label: 'Failed', countKey: 'failed' },
];

function applyFilter(status) {
    router.get(communityPath('/email-history'), { status, search: searchInput.value }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function applySearch() {
    router.get(communityPath('/email-history'), { status: props.filter, search: searchInput.value }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function formatDate(date) {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function displayStatus(send) {
    if (send.clicked_at) return 'clicked';
    if (send.opened_at) return 'opened';
    return send.status;
}
</script>

<template>
    <AppLayout :title="`${community.name} · Email History`">
        <div class="max-w-5xl mx-auto px-4 py-8">
            <EmailNav :community="community" active="history" />

            <h1 class="text-2xl font-bold text-gray-900 mb-6">Send History</h1>

            <!-- Summary row -->
            <div class="grid grid-cols-3 sm:grid-cols-7 gap-3 mb-6">
                <div class="bg-white border border-gray-200 rounded-xl p-3 text-center">
                    <p class="text-lg font-bold text-gray-900">{{ counts.total.toLocaleString() }}</p>
                    <p class="text-[11px] text-gray-500">Total</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-3 text-center">
                    <p class="text-lg font-bold text-blue-600">{{ counts.sent.toLocaleString() }}</p>
                    <p class="text-[11px] text-gray-500">Sent</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-3 text-center">
                    <p class="text-lg font-bold text-green-600">{{ counts.delivered.toLocaleString() }}</p>
                    <p class="text-[11px] text-gray-500">Delivered</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-3 text-center">
                    <p class="text-lg font-bold text-emerald-600">{{ counts.opened.toLocaleString() }}</p>
                    <p class="text-[11px] text-gray-500">Opened</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-3 text-center">
                    <p class="text-lg font-bold text-indigo-600">{{ counts.clicked.toLocaleString() }}</p>
                    <p class="text-[11px] text-gray-500">Clicked</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-3 text-center">
                    <p class="text-lg font-bold text-red-600">{{ counts.bounced.toLocaleString() }}</p>
                    <p class="text-[11px] text-gray-500">Bounced</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-3 text-center">
                    <p class="text-lg font-bold text-red-500">{{ counts.failed.toLocaleString() }}</p>
                    <p class="text-[11px] text-gray-500">Failed</p>
                </div>
            </div>

            <!-- Filters + Search -->
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <div class="flex gap-1.5">
                    <button v-for="tab in filterTabs" :key="tab.value"
                        @click="applyFilter(tab.value)"
                        class="px-3 py-1.5 text-xs font-medium rounded-full border transition-colors"
                        :class="filter === tab.value
                            ? 'bg-indigo-600 text-white border-indigo-600'
                            : 'bg-white text-gray-600 border-gray-300 hover:border-indigo-300'">
                        {{ tab.label }}
                        <span class="ml-1 opacity-70">{{ counts[tab.countKey] }}</span>
                    </button>
                </div>
                <form @submit.prevent="applySearch" class="flex-1 max-w-xs">
                    <input v-model="searchInput" type="text" placeholder="Search by name or email..."
                        class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
                </form>
            </div>

            <!-- Table -->
            <div v-if="sends.data?.length" class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 border-b bg-gray-50">
                            <th class="px-4 py-3 font-medium">Recipient</th>
                            <th class="px-4 py-3 font-medium">Campaign / Subject</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Opened</th>
                            <th class="px-4 py-3 font-medium">Clicked</th>
                            <th class="px-4 py-3 font-medium">Sent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="send in sends.data" :key="send.id"
                            class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <img v-if="send.member_avatar" :src="send.member_avatar"
                                        class="w-7 h-7 rounded-full object-cover" />
                                    <div v-else class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">
                                        {{ (send.member_name || '?')[0].toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-800 truncate">{{ send.member_name }}</p>
                                        <p class="text-xs text-gray-400 truncate">{{ send.member_email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm text-gray-700 truncate max-w-[200px]">{{ send.subject }}</p>
                                <p v-if="send.campaign_name" class="text-xs text-gray-400 truncate">{{ send.campaign_name }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 text-xs font-bold rounded-full"
                                    :class="statusColors[displayStatus(send)] || 'bg-gray-100 text-gray-600'">
                                    {{ displayStatus(send) }}
                                </span>
                                <p v-if="send.failed_reason" class="text-xs text-red-500 mt-1 max-w-[150px] truncate" :title="send.failed_reason">
                                    {{ send.failed_reason }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(send.opened_at) }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(send.clicked_at) }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(send.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="sends.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t bg-gray-50">
                    <p class="text-xs text-gray-500">
                        Showing {{ sends.from }}–{{ sends.to }} of {{ sends.total }}
                    </p>
                    <div class="flex gap-1">
                        <Link v-for="link in sends.links" :key="link.label"
                            :href="link.url || '#'"
                            class="px-3 py-1 text-xs rounded border transition-colors"
                            :class="link.active
                                ? 'bg-indigo-600 text-white border-indigo-600'
                                : link.url
                                    ? 'bg-white text-gray-600 border-gray-300 hover:border-indigo-300'
                                    : 'bg-gray-100 text-gray-400 border-gray-200 cursor-default'"
                            v-html="link.label" />
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="bg-white border border-gray-200 rounded-2xl p-12 text-center">
                <div class="text-4xl mb-3">📬</div>
                <p class="text-sm font-semibold text-gray-700 mb-1">No emails sent yet</p>
                <p class="text-xs text-gray-500">Send a broadcast or activate a sequence to see history here.</p>
            </div>

        </div>
    </AppLayout>
</template>
