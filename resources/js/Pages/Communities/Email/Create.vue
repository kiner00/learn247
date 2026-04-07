<script setup>
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';
import EmailEditor from '@/Components/EmailEditor.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const props = defineProps({
    community: Object,
    tags: Array,
});

const { communityPath } = useCommunityUrl(props.community.slug);

const form = useForm({
    name: '',
    subject: '',
    html_body: '',
    reply_to: '',
    filter_tags: [],
    filter_exclude_tags: [],
    filter_registered_days: null,
    filter_membership_type: '',
    scheduled_at: '',
});

// Tag search for include/exclude dropdowns
const includeSearch = ref('');
const excludeSearch = ref('');
const showIncludeDropdown = ref(false);
const showExcludeDropdown = ref(false);

const availableIncludeTags = computed(() => {
    return props.tags.filter(t =>
        !form.filter_tags.includes(t.id) &&
        t.name.toLowerCase().includes(includeSearch.value.toLowerCase())
    );
});

const availableExcludeTags = computed(() => {
    return props.tags.filter(t =>
        !form.filter_exclude_tags.includes(t.id) &&
        t.name.toLowerCase().includes(excludeSearch.value.toLowerCase())
    );
});

const selectedIncludeTags = computed(() => props.tags.filter(t => form.filter_tags.includes(t.id)));
const selectedExcludeTags = computed(() => props.tags.filter(t => form.filter_exclude_tags.includes(t.id)));

function addIncludeTag(tagId) {
    form.filter_tags.push(tagId);
    includeSearch.value = '';
    showIncludeDropdown.value = false;
}

function removeIncludeTag(tagId) {
    form.filter_tags = form.filter_tags.filter(id => id !== tagId);
}

function addExcludeTag(tagId) {
    form.filter_exclude_tags.push(tagId);
    excludeSearch.value = '';
    showExcludeDropdown.value = false;
}

function removeExcludeTag(tagId) {
    form.filter_exclude_tags = form.filter_exclude_tags.filter(id => id !== tagId);
}

function submit() {
    form.post(communityPath('/email-campaigns'), {
        preserveScroll: true,
    });
}

</script>

<template>
    <CommunitySettingsLayout :community="community">
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

                    <!-- Body -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Body</label>
                        <EmailEditor
                            v-model="form.html_body"
                            :upload-url="communityPath('/email-campaigns/upload-image')"
                            placeholder="Hi {{user_name}}, We have exciting news..."
                        />
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

                <!-- Audience / Targeting -->
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

                    <!-- Contacts settings: registered days -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Registered Over <span class="font-normal text-gray-400">(optional)</span></label>
                        <p class="text-xs text-gray-400 mb-2">Send emails only to contacts who registered over X days ago.</p>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600">registered over</span>
                            <input v-model.number="form.filter_registered_days" type="number" min="0" max="9999"
                                placeholder="0"
                                class="w-20 px-3 py-2 border border-gray-300 rounded-lg text-sm text-center focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
                            <span class="text-sm text-gray-600">days ago</span>
                        </div>
                    </div>

                    <!-- Include tags -->
                    <div v-if="tags.length > 0">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Include these tags <span class="font-normal text-gray-400">(optional)</span></label>
                        <p class="text-xs text-gray-400 mb-2">Only send to members who have at least one of these tags.</p>

                        <!-- Selected include tags -->
                        <div v-if="selectedIncludeTags.length" class="flex flex-wrap gap-2 mb-2">
                            <span v-for="tag in selectedIncludeTags" :key="tag.id"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium text-white"
                                :style="{ backgroundColor: tag.color || '#6366f1' }">
                                {{ tag.name }}
                                <button type="button" @click="removeIncludeTag(tag.id)" class="hover:opacity-70">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                                </button>
                            </span>
                        </div>

                        <!-- Dropdown -->
                        <div class="relative">
                            <select @change="addIncludeTag(Number($event.target.value)); $event.target.value = ''"
                                class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white">
                                <option value="">Select</option>
                                <option v-for="tag in availableIncludeTags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Exclude tags -->
                    <div v-if="tags.length > 0">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Exclude these tags <span class="font-normal text-gray-400">(optional)</span></label>
                        <p class="text-xs text-gray-400 mb-2">Members with any of these tags will NOT receive the email.</p>

                        <!-- Selected exclude tags -->
                        <div v-if="selectedExcludeTags.length" class="flex flex-wrap gap-2 mb-2">
                            <span v-for="tag in selectedExcludeTags" :key="tag.id"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium text-white"
                                :style="{ backgroundColor: tag.color || '#ef4444' }">
                                {{ tag.name }}
                                <button type="button" @click="removeExcludeTag(tag.id)" class="hover:opacity-70">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                                </button>
                            </span>
                        </div>

                        <!-- Dropdown -->
                        <div class="relative">
                            <select @change="addExcludeTag(Number($event.target.value)); $event.target.value = ''"
                                class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white">
                                <option value="">Select</option>
                                <option v-for="tag in availableExcludeTags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
                            </select>
                        </div>

                        <!-- Explanation note -->
                        <p class="mt-3 text-xs text-gray-400 bg-gray-50 rounded-lg p-3">
                            All contacts that have a tag in the "Include" list will receive the newsletter unless they also have a tag that is in the "Exclude" list.
                            <br>Note: if a contact has several tags that are in the "Include" list, they'll only receive one email.
                        </p>
                    </div>

                    <div v-if="!tags.length" class="p-3 bg-gray-50 rounded-lg text-xs text-gray-500">
                        No tags created yet. <Link :href="communityPath('/settings/tags')" class="text-indigo-600 hover:underline">Manage tags</Link> to segment your audience.
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
    </CommunitySettingsLayout>
</template>
