<template>
    <AppLayout title="Support">
        <div class="max-w-3xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-xl font-black text-gray-900">Support Tickets</h1>
                <button
                    @click="showForm = !showForm"
                    class="px-4 py-2 bg-amber-500 text-white text-sm font-semibold rounded-xl hover:bg-amber-600 transition-colors"
                >
                    {{ showForm ? 'Cancel' : 'New Ticket' }}
                </button>
            </div>

            <!-- Create ticket form -->
            <div v-if="showForm" class="bg-white border border-gray-200 rounded-2xl p-6 mb-6 shadow-sm">
                <h2 class="text-base font-bold text-gray-900 mb-4">Submit a ticket</h2>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select
                                v-model="form.type"
                                class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500"
                            >
                                <option value="bug">Bug</option>
                                <option value="suggestion">Suggestion / Enhancement</option>
                                <option value="question">Question</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                            <select
                                v-model="form.priority"
                                class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500"
                            >
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <input
                            v-model="form.subject"
                            type="text"
                            placeholder="Brief description of the issue"
                            class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500"
                        />
                        <p v-if="form.errors.subject" class="text-xs text-red-500 mt-1">{{ form.errors.subject }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="4"
                            placeholder="Describe the issue in detail..."
                            class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500"
                        />
                        <p v-if="form.errors.description" class="text-xs text-red-500 mt-1">{{ form.errors.description }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Attachments (max 5 images)</label>
                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            @change="handleFiles"
                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100"
                        />
                        <p v-if="form.errors.attachments" class="text-xs text-red-500 mt-1">{{ form.errors.attachments }}</p>

                        <!-- Preview thumbnails -->
                        <div v-if="previews.length" class="flex gap-2 mt-3 flex-wrap">
                            <div v-for="(src, i) in previews" :key="i" class="relative">
                                <img :src="src" class="w-16 h-16 object-cover rounded-lg border border-gray-200" />
                                <button
                                    type="button"
                                    @click="removeFile(i)"
                                    class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-xs"
                                >
                                    x
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 bg-amber-500 text-white text-sm font-semibold rounded-xl hover:bg-amber-600 transition-colors disabled:opacity-50"
                        >
                            {{ form.processing ? 'Submitting...' : 'Submit Ticket' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tickets list -->
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                <div v-if="!tickets.data.length" class="py-20 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-gray-700 mb-1">No tickets yet</p>
                    <p class="text-xs text-gray-400">Submit a ticket to report bugs or suggest improvements.</p>
                </div>

                <Link
                    v-for="ticket in tickets.data"
                    :key="ticket.id"
                    :href="`/support/${ticket.id}`"
                    class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-0"
                >
                    <!-- Type icon -->
                    <div
                        class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold shrink-0"
                        :class="typeStyles[ticket.type]?.bg ?? 'bg-gray-100 text-gray-500'"
                    >
                        {{ typeStyles[ticket.type]?.icon ?? '?' }}
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ ticket.subject }}</p>
                            <span
                                class="shrink-0 px-2 py-0.5 text-xs font-medium rounded-full"
                                :class="statusStyles[ticket.status]"
                            >
                                {{ ticket.status.replace('_', ' ') }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3 text-xs text-gray-400">
                            <span>{{ ticket.type }}</span>
                            <span>{{ ticket.replies_count }} {{ ticket.replies_count === 1 ? 'reply' : 'replies' }}</span>
                            <span>{{ formatDate(ticket.created_at) }}</span>
                        </div>
                    </div>

                    <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
            </div>

            <!-- Pagination -->
            <div v-if="tickets.last_page > 1" class="flex justify-center gap-2 mt-6">
                <Link
                    v-for="link in tickets.links"
                    :key="link.label"
                    :href="link.url"
                    v-html="link.label"
                    class="px-3 py-1.5 text-sm rounded-lg border"
                    :class="link.active ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                    :preserve-scroll="true"
                />
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ tickets: Object });

const showForm = ref(false);
const previews = ref([]);

const form = useForm({
    subject: '',
    description: '',
    type: 'bug',
    priority: 'medium',
    attachments: [],
});

function handleFiles(e) {
    const newFiles = Array.from(e.target.files);
    const combined = [...form.attachments, ...newFiles].slice(0, 5);
    form.attachments = combined;
    previews.value = [];
    combined.forEach(file => {
        const reader = new FileReader();
        reader.onload = (ev) => previews.value.push(ev.target.result);
        reader.readAsDataURL(file);
    });
    e.target.value = '';
}

function removeFile(index) {
    const files = [...form.attachments];
    files.splice(index, 1);
    form.attachments = files;
    previews.value.splice(index, 1);
}

function submit() {
    form.post('/support', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            previews.value = [];
            showForm.value = false;
        },
    });
}

const typeStyles = {
    bug:        { bg: 'bg-red-100 text-red-600',    icon: '!' },
    suggestion: { bg: 'bg-blue-100 text-blue-600',   icon: '+' },
    question:   { bg: 'bg-violet-100 text-violet-600', icon: '?' },
    other:      { bg: 'bg-gray-100 text-gray-600',   icon: '*' },
};

const statusStyles = {
    open:        'bg-green-100 text-green-700',
    in_progress: 'bg-amber-100 text-amber-700',
    resolved:    'bg-blue-100 text-blue-700',
    closed:      'bg-gray-100 text-gray-500',
};

function formatDate(str) {
    if (!str) return '';
    const d = new Date(str);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>
