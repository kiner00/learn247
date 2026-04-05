<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const props = defineProps({
    community: Object,
    campaign: Object,
    broadcast: Object,
    stats: Object,
});

const { communityPath } = useCommunityUrl(props.community.slug);

const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    sending: 'bg-blue-100 text-blue-700',
    sent: 'bg-green-100 text-green-700',
    scheduled: 'bg-purple-100 text-purple-700',
    paused: 'bg-yellow-100 text-yellow-700',
    cancelled: 'bg-red-100 text-red-700',
};

function sendNow() {
    if (!confirm('Send this campaign to your members now?')) return;
    router.post(communityPath(`/email-campaigns/${props.campaign.id}/send`), {}, {
        preserveScroll: true,
    });
}

function deleteCampaign() {
    if (!confirm('Are you sure you want to delete this campaign?')) return;
    router.delete(communityPath(`/email-campaigns/${props.campaign.id}`));
}

function formatDate(date) {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function pct(value, total) {
    if (!total) return '0%';
    return Math.round((value / total) * 100) + '%';
}
</script>

<template>
    <AppLayout :title="`${community.name} · ${campaign.name}`">
        <div class="max-w-4xl mx-auto px-4 py-8">
            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                <Link :href="communityPath()" class="hover:text-indigo-600 transition-colors">{{ community.name }}</Link>
                <span>/</span>
                <Link :href="communityPath('/email-campaigns')" class="hover:text-indigo-600 transition-colors">Email Campaigns</Link>
                <span>/</span>
                <span>{{ campaign.name }}</span>
            </div>

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ campaign.name }}</h1>
                    <p v-if="broadcast" class="text-sm text-gray-500 mt-1">Subject: {{ broadcast.subject }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="px-2.5 py-1 text-xs font-bold rounded-full"
                        :class="statusColors[campaign.status] || 'bg-gray-100 text-gray-700'">
                        {{ campaign.status }}
                    </span>
                    <button v-if="campaign.status === 'draft' && broadcast"
                        @click="sendNow"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                        Send Now
                    </button>
                    <button v-if="campaign.status !== 'sending'"
                        @click="deleteCampaign"
                        class="px-4 py-2.5 border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors">
                        Delete
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div v-if="stats" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ stats.total_sent }}</p>
                    <p class="text-xs text-gray-500">Sent</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-green-600">{{ stats.delivered }}</p>
                    <p class="text-xs text-gray-500">Delivered {{ pct(stats.delivered, stats.total_sent) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-blue-600">{{ stats.opened }}</p>
                    <p class="text-xs text-gray-500">Opened {{ pct(stats.opened, stats.total_sent) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-red-600">{{ stats.bounced }}</p>
                    <p class="text-xs text-gray-500">Bounced {{ pct(stats.bounced, stats.total_sent) }}</p>
                </div>
            </div>

            <!-- Broadcast details -->
            <div v-if="broadcast" class="bg-white border border-gray-200 rounded-2xl p-6 space-y-4">
                <h2 class="text-base font-semibold text-gray-900">Broadcast Details</h2>

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Status:</span>
                        <span class="ml-2 font-medium">{{ broadcast.status }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Sent at:</span>
                        <span class="ml-2 font-medium">{{ formatDate(broadcast.sent_at) }}</span>
                    </div>
                    <div v-if="broadcast.scheduled_at">
                        <span class="text-gray-500">Scheduled for:</span>
                        <span class="ml-2 font-medium">{{ formatDate(broadcast.scheduled_at) }}</span>
                    </div>
                    <div v-if="broadcast.filter_membership_type">
                        <span class="text-gray-500">Audience:</span>
                        <span class="ml-2 font-medium capitalize">{{ broadcast.filter_membership_type }} members</span>
                    </div>
                </div>

                <!-- Email preview -->
                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Email Preview</h3>
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <div v-html="broadcast.html_body" class="prose prose-sm max-w-none"></div>
                    </div>
                </div>
            </div>

            <!-- No broadcast -->
            <div v-else class="bg-white border border-gray-200 rounded-2xl p-12 text-center">
                <p class="text-sm text-gray-500">No broadcast created for this campaign yet.</p>
            </div>
        </div>
    </AppLayout>
</template>
