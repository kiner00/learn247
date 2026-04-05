<script setup>
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const props = defineProps({
    community: Object,
    tags: Array,
});

const { communityPath } = useCommunityUrl(props.community.slug);
const showPreview = ref(false);

const form = useForm({
    name: '',
    subject: '',
    html_body: '',
    reply_to: '',
    filter_tags: [],
    filter_membership_type: '',
    scheduled_at: '',
});

function submit() {
    form.post(communityPath('/email-campaigns'), {
        preserveScroll: true,
    });
}

function toggleTag(tagId) {
    const idx = form.filter_tags.indexOf(tagId);
    if (idx > -1) {
        form.filter_tags.splice(idx, 1);
    } else {
        form.filter_tags.push(tagId);
    }
}

const variablesList = [
    { key: '{{user_name}}', label: 'Member name' },
    { key: '{{user_email}}', label: 'Member email' },
    { key: '{{community_name}}', label: 'Community name' },
];

function insertVariable(variable) {
    form.html_body += variable;
}
</script>

<template>
    <AppLayout :title="`${community.name} · Create Campaign`">
        <div class="max-w-3xl mx-auto px-4 py-8">
            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                <Link :href="communityPath()" class="hover:text-indigo-600 transition-colors">{{ community.name }}</Link>
                <span>/</span>
                <Link :href="communityPath('/email-campaigns')" class="hover:text-indigo-600 transition-colors">Email Campaigns</Link>
                <span>/</span>
                <span>Create</span>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-6">Create Campaign</h1>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Campaign Name -->
                <div class="bg-white border border-gray-200 rounded-2xl p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Campaign Name</label>
                        <input v-model="form.name" type="text" required maxlength="255"
                            placeholder="e.g. Welcome series, Weekly newsletter"
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Subject Line</label>
                        <input v-model="form.subject" type="text" required maxlength="255"
                            placeholder="e.g. New content just dropped!"
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
                        <p v-if="form.errors.subject" class="mt-1 text-xs text-red-600">{{ form.errors.subject }}</p>
                    </div>

                    <!-- Variables -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Insert Variable</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="v in variablesList" :key="v.key" type="button"
                                @click="insertVariable(v.key)"
                                class="px-3 py-1.5 text-xs font-mono bg-gray-100 text-gray-700 rounded-lg hover:bg-indigo-100 hover:text-indigo-700 transition-colors">
                                {{ v.key }}
                            </button>
                        </div>
                    </div>

                    <!-- Body -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="text-sm font-medium text-gray-700">Email Body (HTML)</label>
                            <button type="button" @click="showPreview = !showPreview"
                                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                {{ showPreview ? 'Edit' : 'Preview' }}
                            </button>
                        </div>
                        <textarea v-if="!showPreview" v-model="form.html_body" rows="12" required
                            placeholder="<p>Hi {{user_name}},</p><p>We have exciting news...</p>"
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-y" />
                        <div v-else class="border border-gray-300 rounded-lg p-4 min-h-[200px] bg-white">
                            <div v-html="form.html_body" class="prose prose-sm max-w-none"></div>
                        </div>
                        <p v-if="form.errors.html_body" class="mt-1 text-xs text-red-600">{{ form.errors.html_body }}</p>
                    </div>

                    <!-- Reply To -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Reply-To Email <span class="font-normal text-gray-400">(optional)</span></label>
                        <input v-model="form.reply_to" type="email" maxlength="255"
                            placeholder="replies@yourdomain.com"
                            class="w-full max-w-md px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
                    </div>
                </div>

                <!-- Targeting -->
                <div class="bg-white border border-gray-200 rounded-2xl p-6 space-y-5">
                    <h2 class="text-base font-semibold text-gray-900">Audience</h2>

                    <!-- Membership type filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Membership Type</label>
                        <select v-model="form.filter_membership_type"
                            class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white">
                            <option value="">All members</option>
                            <option value="free">Free members only</option>
                            <option value="paid">Paid members only</option>
                        </select>
                    </div>

                    <!-- Tag filter -->
                    <div v-if="tags.length > 0">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Filter by Tags <span class="font-normal text-gray-400">(optional)</span></label>
                        <p class="text-xs text-gray-400 mb-2">Only send to members who have at least one of these tags.</p>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="tag in tags" :key="tag.id" type="button"
                                @click="toggleTag(tag.id)"
                                class="px-3 py-1.5 text-xs font-medium rounded-full border transition-colors"
                                :class="form.filter_tags.includes(tag.id)
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : 'bg-white text-gray-600 border-gray-300 hover:border-indigo-300'"
                                :style="form.filter_tags.includes(tag.id) && tag.color ? { backgroundColor: tag.color, borderColor: tag.color } : {}">
                                {{ tag.name }}
                            </button>
                        </div>
                    </div>
                    <div v-else class="p-3 bg-gray-50 rounded-lg text-xs text-gray-500">
                        No tags created yet. <Link :href="communityPath('/settings/email')" class="text-indigo-600 hover:underline">Manage tags</Link> to segment your audience.
                    </div>
                </div>

                <!-- Schedule -->
                <div class="bg-white border border-gray-200 rounded-2xl p-6 space-y-5">
                    <h2 class="text-base font-semibold text-gray-900">Schedule <span class="font-normal text-gray-400 text-sm">(optional)</span></h2>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Send at</label>
                        <input v-model="form.scheduled_at" type="datetime-local"
                            class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
                        <p class="mt-1 text-xs text-gray-400">Leave empty to send manually from the campaign page.</p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                        {{ form.processing ? 'Creating...' : 'Create Campaign' }}
                    </button>
                    <Link :href="communityPath('/email-campaigns')"
                        class="px-5 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">
                        Cancel
                    </Link>
                </div>

                <p v-if="form.errors.resend" class="text-sm text-red-600">{{ form.errors.resend }}</p>
            </form>
        </div>
    </AppLayout>
</template>
