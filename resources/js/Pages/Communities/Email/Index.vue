<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmailNav from '@/Components/EmailNav.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const props = defineProps({
    community: Object,
    campaigns: Array,
    hasResendKey: Boolean,
});

const page = usePage();
const { communityPath } = useCommunityUrl(props.community.slug);

const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    sending: 'bg-blue-100 text-blue-700',
    sent: 'bg-green-100 text-green-700',
    paused: 'bg-yellow-100 text-yellow-700',
    cancelled: 'bg-red-100 text-red-700',
};

function formatDate(date) {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}
</script>

<template>
    <AppLayout :title="`${community.name} · Email Campaigns`">
        <div class="max-w-4xl mx-auto px-4 py-8">
            <EmailNav :community="community" active="campaigns" />

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Email Campaigns</h1>
                </div>
                <Link
                    v-if="hasResendKey"
                    :href="communityPath('/email-campaigns/create')"
                    class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                >
                    + Create Campaign
                </Link>
            </div>

            <!-- No Resend key warning -->
            <div v-if="!hasResendKey" class="bg-yellow-50 border border-yellow-200 rounded-2xl p-6 text-center mb-6">
                <p class="text-sm font-semibold text-yellow-800 mb-1">Email not configured</p>
                <p class="text-xs text-yellow-700 mb-3">Connect your Resend API key in Email Settings to start sending campaigns.</p>
                <Link :href="communityPath('/settings/email')"
                    class="inline-block px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                    Configure Email Settings
                </Link>
            </div>

            <!-- Empty state -->
            <div v-else-if="campaigns.length === 0" class="bg-white border border-gray-200 rounded-2xl p-12 text-center">
                <div class="text-4xl mb-3">📧</div>
                <p class="text-sm font-semibold text-gray-700 mb-1">No campaigns yet</p>
                <p class="text-xs text-gray-500 mb-4">Create your first email campaign to reach your community members.</p>
                <Link :href="communityPath('/email-campaigns/create')"
                    class="inline-block px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    + Create Campaign
                </Link>
            </div>

            <!-- Campaigns table -->
            <div v-else class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 border-b bg-gray-50">
                            <th class="px-5 py-3 font-medium">Name</th>
                            <th class="px-5 py-3 font-medium">Emails Sent</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="campaign in campaigns" :key="campaign.id" class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3.5">
                                <Link :href="communityPath(`/email-campaigns/${campaign.id}`)"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                    {{ campaign.name }}
                                </Link>
                            </td>
                            <td class="px-5 py-3.5 text-sm text-gray-600">
                                {{ campaign.total_sent ?? 0 }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="px-2.5 py-1 text-xs font-bold rounded-full"
                                    :class="statusColors[campaign.status] || 'bg-gray-100 text-gray-700'">
                                    {{ campaign.status }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-sm text-gray-500">
                                {{ formatDate(campaign.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AppLayout>
</template>
