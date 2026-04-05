<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const props = defineProps({
    community: Object,
    sequence: Object,
    enrollmentStats: Object,
});

const { communityPath } = useCommunityUrl(props.community.slug);

const triggerLabels = {
    'member.joined': 'Member Joined',
    'subscription.paid': 'Subscription Paid',
    'course.enrolled': 'Course Enrolled',
    'cart.abandoned': 'Cart Abandoned',
    'tag.added': 'Tag Added',
};

const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    active: 'bg-green-100 text-green-700',
    paused: 'bg-yellow-100 text-yellow-700',
};

function activate() {
    router.post(communityPath(`/email-sequences/${props.sequence.id}/activate`), {}, { preserveScroll: true });
}

function pause() {
    router.post(communityPath(`/email-sequences/${props.sequence.id}/pause`), {}, { preserveScroll: true });
}

function deleteSequence() {
    if (!confirm('Are you sure you want to delete this sequence? All enrollments will be cancelled.')) return;
    router.delete(communityPath(`/email-sequences/${props.sequence.id}`));
}

function delayLabel(hours) {
    if (hours === 0) return 'Immediately';
    if (hours < 24) return `${hours}h`;
    const days = Math.floor(hours / 24);
    const rem = hours % 24;
    let label = `${days}d`;
    if (rem > 0) label += ` ${rem}h`;
    return label;
}
</script>

<template>
    <AppLayout :title="`${community.name} · ${sequence.campaign?.name}`">
        <div class="max-w-4xl mx-auto px-4 py-8">
            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                <Link :href="communityPath()" class="hover:text-indigo-600">{{ community.name }}</Link>
                <span>/</span>
                <Link :href="communityPath('/email-sequences')" class="hover:text-indigo-600">Sequences</Link>
                <span>/</span>
                <span>{{ sequence.campaign?.name }}</span>
            </div>

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ sequence.campaign?.name }}</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Trigger: <strong>{{ triggerLabels[sequence.trigger_event] || sequence.trigger_event }}</strong>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="px-2.5 py-1 text-xs font-bold rounded-full"
                        :class="statusColors[sequence.status] || 'bg-gray-100 text-gray-700'">
                        {{ sequence.status }}
                    </span>
                    <button v-if="sequence.status === 'draft' || sequence.status === 'paused'"
                        @click="activate"
                        class="px-5 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                        Activate
                    </button>
                    <button v-if="sequence.status === 'active'"
                        @click="pause"
                        class="px-4 py-2.5 border border-yellow-400 text-yellow-700 text-sm font-medium rounded-lg hover:bg-yellow-50 transition-colors">
                        Pause
                    </button>
                    <button @click="deleteSequence"
                        class="px-4 py-2.5 border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors">
                        Delete
                    </button>
                </div>
            </div>

            <!-- Enrollment stats -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-blue-600">{{ enrollmentStats.active }}</p>
                    <p class="text-xs text-gray-500">Active</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-green-600">{{ enrollmentStats.completed }}</p>
                    <p class="text-xs text-gray-500">Completed</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-gray-400">{{ enrollmentStats.cancelled }}</p>
                    <p class="text-xs text-gray-500">Cancelled</p>
                </div>
            </div>

            <!-- Steps timeline -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Steps</h2>

                <div v-if="sequence.steps?.length" class="space-y-4">
                    <div v-for="step in sequence.steps" :key="step.id"
                        class="flex gap-4 border-l-2 border-indigo-200 pl-4 pb-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">
                                    Step {{ step.position }}
                                </span>
                                <span class="text-xs text-gray-400">
                                    Delay: {{ delayLabel(step.delay_hours) }}
                                </span>
                            </div>
                            <p class="text-sm font-medium text-gray-800">{{ step.subject }}</p>
                            <div class="mt-2 text-xs text-gray-500 border border-gray-100 rounded p-3 bg-gray-50 max-h-32 overflow-y-auto">
                                <div v-html="step.html_body"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400">No steps configured.</p>
            </div>
        </div>
    </AppLayout>
</template>
