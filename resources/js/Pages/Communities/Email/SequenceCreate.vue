<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const props = defineProps({
    community: Object,
    tags: Array,
    courses: Array,
    triggers: Array,
});

const { communityPath } = useCommunityUrl(props.community.slug);

const triggerLabels = {
    'member.joined': 'Member Joined',
    'subscription.paid': 'Subscription Paid',
    'course.enrolled': 'Course Enrolled',
    'cart.abandoned': 'Cart Abandoned',
    'tag.added': 'Tag Added',
};

const triggerDescriptions = {
    'member.joined': 'Triggered when a new member joins your community (free or paid).',
    'subscription.paid': 'Triggered when a member completes a paid subscription.',
    'course.enrolled': 'Triggered when a member enrolls in a specific course.',
    'cart.abandoned': 'Triggered when someone starts checkout but doesn\'t complete payment.',
    'tag.added': 'Triggered when a specific tag is added to a member.',
};

const form = useForm({
    name: '',
    trigger_event: '',
    trigger_filter: {},
    steps: [
        { subject: '', html_body: '', delay_hours: 0 },
    ],
});

function addStep() {
    form.steps.push({ subject: '', html_body: '', delay_hours: 24 });
}

function removeStep(index) {
    if (form.steps.length > 1) {
        form.steps.splice(index, 1);
    }
}

function submit() {
    form.post(communityPath('/email-sequences'), { preserveScroll: true });
}

function delayLabel(hours) {
    if (hours === 0) return 'Immediately';
    if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} after previous`;
    const days = Math.floor(hours / 24);
    const rem = hours % 24;
    let label = `${days} day${days > 1 ? 's' : ''}`;
    if (rem > 0) label += ` ${rem}h`;
    return label + ' after previous';
}
</script>

<template>
    <AppLayout :title="`${community.name} · Create Sequence`">
        <div class="max-w-3xl mx-auto px-4 py-8">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                <Link :href="communityPath()" class="hover:text-indigo-600">{{ community.name }}</Link>
                <span>/</span>
                <Link :href="communityPath('/email-sequences')" class="hover:text-indigo-600">Sequences</Link>
                <span>/</span>
                <span>Create</span>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-6">Create Email Sequence</h1>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Campaign Name + Trigger -->
                <div class="bg-white border border-gray-200 rounded-2xl p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Sequence Name</label>
                        <input v-model="form.name" type="text" required maxlength="255"
                            placeholder="e.g. Welcome series, Onboarding drip"
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Trigger Event</label>
                        <select v-model="form.trigger_event" required
                            class="w-full max-w-md px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white">
                            <option value="">— Select trigger —</option>
                            <option v-for="t in triggers" :key="t" :value="t">{{ triggerLabels[t] || t }}</option>
                        </select>
                        <p v-if="form.trigger_event" class="mt-1.5 text-xs text-gray-400">
                            {{ triggerDescriptions[form.trigger_event] }}
                        </p>
                    </div>

                    <!-- Trigger filter: membership type for member.joined -->
                    <div v-if="form.trigger_event === 'member.joined'">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Filter by Membership Type</label>
                        <select v-model="form.trigger_filter.membership_type"
                            class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm bg-white">
                            <option value="">All members</option>
                            <option value="free">Free members only</option>
                            <option value="paid">Paid members only</option>
                        </select>
                    </div>

                    <!-- Trigger filter: course for course.enrolled -->
                    <div v-if="form.trigger_event === 'course.enrolled' && courses.length > 0">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Specific Course</label>
                        <select v-model="form.trigger_filter.course_id"
                            class="w-full max-w-md px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm bg-white">
                            <option value="">Any course</option>
                            <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
                        </select>
                    </div>

                    <!-- Trigger filter: tag for tag.added -->
                    <div v-if="form.trigger_event === 'tag.added' && tags.length > 0">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Specific Tag</label>
                        <select v-model="form.trigger_filter.tag_id"
                            class="w-full max-w-md px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm bg-white">
                            <option value="">Any tag</option>
                            <option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- Steps -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-semibold text-gray-900">Steps</h2>
                        <button type="button" @click="addStep"
                            class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                            + Add Step
                        </button>
                    </div>

                    <div v-for="(step, i) in form.steps" :key="i"
                        class="bg-white border border-gray-200 rounded-2xl p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700">Step {{ i + 1 }}</h3>
                            <button v-if="form.steps.length > 1" type="button" @click="removeStep(i)"
                                class="text-xs text-red-500 hover:text-red-700">Remove</button>
                        </div>

                        <!-- Delay -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Delay <span class="font-normal text-gray-400">(hours after {{ i === 0 ? 'trigger' : 'previous step' }})</span>
                            </label>
                            <div class="flex items-center gap-3">
                                <input v-model.number="step.delay_hours" type="number" min="0" max="8760"
                                    class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <span class="text-xs text-gray-400">{{ delayLabel(step.delay_hours) }}</span>
                            </div>
                        </div>

                        <!-- Subject -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Subject</label>
                            <input v-model="step.subject" type="text" required maxlength="255"
                                :placeholder="`e.g. Step ${i + 1}: ...`"
                                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
                        </div>

                        <!-- Body -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Body (HTML)</label>
                            <textarea v-model="step.html_body" rows="8" required
                                placeholder="<p>Hi {{user_name}},</p>"
                                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-y" />
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                        {{ form.processing ? 'Creating...' : 'Create Sequence' }}
                    </button>
                    <Link :href="communityPath('/email-sequences')"
                        class="px-5 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">
                        Cancel
                    </Link>
                </div>
                <p v-if="form.errors.resend" class="text-sm text-red-600">{{ form.errors.resend }}</p>
            </form>
        </div>
    </AppLayout>
</template>
