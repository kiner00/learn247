<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';

const props = defineProps({
    community: Object,
});

const page = usePage();
const creatorPlan = computed(() => page.props.auth.user?.creator_plan ?? 'free');

const announceSent = ref(false);
const announceForm = useForm({ subject: '', message: '' });

function sendAnnouncement() {
    announceForm.post(`/communities/${props.community.slug}/announce`, {
        preserveScroll: true,
        onSuccess: () => {
            announceForm.reset();
            announceSent.value = true;
            setTimeout(() => (announceSent.value = false), 4000);
        },
    });
}
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <div class="bg-white border border-gray-200 rounded-2xl p-6">
            <div class="flex items-center justify-between mb-1">
                <h2 class="text-base font-semibold text-gray-900">📢 Send Announcement</h2>
                <span class="text-xs font-bold bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full">⭐ Pro</span>
            </div>
            <p class="text-sm text-gray-500 mb-5">Email all members of this community at once.</p>

            <!-- Locked for Free -->
            <div v-if="creatorPlan === 'free'" class="rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-6 text-center">
                <p class="text-sm font-semibold text-indigo-800 mb-1">Basic &amp; Pro feature</p>
                <p class="text-xs text-indigo-600 mb-3">Upgrade to send broadcast emails to all your members.</p>
                <Link href="/creator/plan" class="inline-block px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                    Upgrade Plan →
                </Link>
            </div>

            <!-- Form for Basic/Pro -->
            <form v-else @submit.prevent="sendAnnouncement" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Subject</label>
                    <input v-model="announceForm.subject" type="text" required maxlength="200"
                        placeholder="e.g. New content dropped!"
                        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="announceForm.errors.subject ? 'border-red-400' : ''" />
                    <p v-if="announceForm.errors.subject" class="mt-1 text-xs text-red-600">{{ announceForm.errors.subject }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Message</label>
                    <textarea v-model="announceForm.message" rows="5" required maxlength="5000"
                        placeholder="Write your announcement here..."
                        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                        :class="announceForm.errors.message ? 'border-red-400' : ''" />
                    <p v-if="announceForm.errors.message" class="mt-1 text-xs text-red-600">{{ announceForm.errors.message }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" :disabled="announceForm.processing"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                        {{ announceForm.processing ? 'Sending...' : 'Send to all members' }}
                    </button>
                    <p v-if="announceSent" class="text-sm text-green-600">Announcement sent!</p>
                </div>
            </form>
        </div>
    </CommunitySettingsLayout>
</template>
