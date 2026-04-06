<script setup>
import { Link } from '@inertiajs/vue3';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const props = defineProps({
    community: Object,
    sequences: Array,
    hasResendKey: Boolean,
});

const { communityPath } = useCommunityUrl(props.community.slug);

const triggerLabels = {
    'member.joined': 'Member Joined',
    'free.subscribed': 'Free Subscriber',
    'subscription.paid': 'Subscription Paid',
    'subscription.cancelled': 'Subscription Cancelled',
    'course.enrolled': 'Course Enrolled',
    'course.completed': 'Course Completed',
    'cart.abandoned': 'Cart Abandoned',
    'tag.added': 'Tag Added',
    'member.inactive': 'Member Inactive',
    'certification.earned': 'Certification Earned',
    'member.first_post': 'First Post',
};

const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    active: 'bg-green-100 text-green-700',
    paused: 'bg-yellow-100 text-yellow-700',
};

function formatDate(date) {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

<template>
    <CommunitySettingsLayout :community="community">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Email Sequences</h1>
                    <p class="text-sm text-gray-500 mt-1">Automated drip campaigns triggered by member actions.</p>
                </div>
                <Link v-if="hasResendKey"
                    :href="communityPath('/email-sequences/create')"
                    class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    + Create Sequence
                </Link>
            </div>

            <!-- No Resend key -->
            <div v-if="!hasResendKey" class="bg-yellow-50 border border-yellow-200 rounded-2xl p-6 text-center mb-6">
                <p class="text-sm font-semibold text-yellow-800 mb-1">Email not configured</p>
                <p class="text-xs text-yellow-700 mb-3">Connect your Resend API key first.</p>
                <Link :href="communityPath('/settings/email')"
                    class="inline-block px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                    Configure Email
                </Link>
            </div>

            <!-- Empty state -->
            <div v-else-if="sequences.length === 0" class="bg-white border border-gray-200 rounded-2xl p-12 text-center">
                <div class="text-4xl mb-3">🔄</div>
                <p class="text-sm font-semibold text-gray-700 mb-1">No sequences yet</p>
                <p class="text-xs text-gray-500 mb-4">Create an automated email sequence to engage members on autopilot.</p>
                <Link :href="communityPath('/email-sequences/create')"
                    class="inline-block px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    + Create Sequence
                </Link>
            </div>

            <!-- Sequences table -->
            <div v-else class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 border-b bg-gray-50">
                            <th class="px-5 py-3 font-medium">Campaign</th>
                            <th class="px-5 py-3 font-medium">Trigger</th>
                            <th class="px-5 py-3 font-medium">Steps</th>
                            <th class="px-5 py-3 font-medium">Enrolled</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="seq in sequences" :key="seq.id" class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3.5">
                                <Link :href="communityPath(`/email-sequences/${seq.id}`)"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                    {{ seq.campaign_name }}
                                </Link>
                            </td>
                            <td class="px-5 py-3.5 text-sm text-gray-600">
                                {{ triggerLabels[seq.trigger_event] || seq.trigger_event }}
                            </td>
                            <td class="px-5 py-3.5 text-sm text-gray-600">{{ seq.steps_count }}</td>
                            <td class="px-5 py-3.5 text-sm text-gray-600">{{ seq.enrollments_count }}</td>
                            <td class="px-5 py-3.5">
                                <span class="px-2.5 py-1 text-xs font-bold rounded-full"
                                    :class="statusColors[seq.status] || 'bg-gray-100 text-gray-700'">
                                    {{ seq.status }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-sm text-gray-500">{{ formatDate(seq.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

    </CommunitySettingsLayout>
</template>
