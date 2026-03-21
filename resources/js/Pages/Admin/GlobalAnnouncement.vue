<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const form = useForm({
    audience: 'all',
    subject:  '',
    message:  '',
});

const audiences = [
    { value: 'all',        label: 'All Users',   desc: 'Every registered user on the platform',     icon: '🌐' },
    { value: 'members',    label: 'Members',      desc: 'Users with at least one community membership', icon: '👥' },
    { value: 'creators',   label: 'Creators',     desc: 'Users with an active Creator plan (Basic or Pro)', icon: '🎨' },
    { value: 'affiliates', label: 'Affiliates',   desc: 'Users with an active affiliate link',       icon: '🔗' },
];

function send() {
    form.post('/admin/announcements', { preserveScroll: true, onSuccess: () => form.reset('subject', 'message') });
}
</script>

<template>
    <AppLayout title="Global Announcement">
        <div class="max-w-2xl mx-auto">

            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <Link href="/admin" class="text-gray-400 hover:text-gray-600 transition-colors">
                    ← Admin
                </Link>
                <span class="text-gray-300">/</span>
                <h1 class="text-xl font-black text-gray-900">Global Announcement</h1>
            </div>

            <!-- Success notice -->
            <div v-if="flash.success" class="mb-6 bg-green-50 border border-green-200 rounded-2xl px-5 py-4 flex items-center gap-3">
                <span class="text-xl">🎉</span>
                <p class="text-sm font-semibold text-green-800">{{ flash.success }}</p>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">

                <!-- Audience picker -->
                <div class="px-6 py-5 border-b border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Send To</p>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="a in audiences"
                            :key="a.value"
                            type="button"
                            @click="form.audience = a.value"
                            class="flex items-start gap-3 p-3 rounded-xl border text-left transition-all"
                            :class="form.audience === a.value
                                ? 'border-indigo-400 bg-indigo-50 shadow-sm'
                                : 'border-gray-200 hover:border-gray-300'"
                        >
                            <span class="text-lg mt-0.5">{{ a.icon }}</span>
                            <div>
                                <p class="text-xs font-bold" :class="form.audience === a.value ? 'text-indigo-700' : 'text-gray-800'">{{ a.label }}</p>
                                <p class="text-[11px] text-gray-400 mt-0.5 leading-tight">{{ a.desc }}</p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Compose -->
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Subject</label>
                        <input
                            v-model="form.subject"
                            type="text"
                            placeholder="e.g. New features are live!"
                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.subject" class="text-xs text-red-500 mt-1">{{ form.errors.subject }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Message</label>
                        <textarea
                            v-model="form.message"
                            rows="7"
                            placeholder="Write your announcement here..."
                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                        />
                        <p v-if="form.errors.message" class="text-xs text-red-500 mt-1">{{ form.errors.message }}</p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                    <p class="text-xs text-gray-400">Emails will be queued and sent in the background.</p>
                    <button
                        @click="send"
                        :disabled="form.processing || !form.subject || !form.message"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                        {{ form.processing ? 'Sending...' : '📢 Send Announcement' }}
                    </button>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
