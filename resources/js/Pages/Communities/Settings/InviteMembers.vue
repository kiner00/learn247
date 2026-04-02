<script setup>
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';

const props = defineProps({
    community: Object,
});

const inviteTab         = ref('single');
const inviteSent        = ref(false);
const inviteSentMessage = ref('');
const csvFile           = ref(null);

const inviteForm    = useForm({ email: '' });
const csvInviteForm = useForm({ csv: null });

function sendSingleInvite() {
    inviteForm.post(`/communities/${props.community.slug}/invite`, {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset();
            inviteSentMessage.value = usePage().props.flash?.success ?? 'Invite sent!';
            inviteSent.value = true;
            setTimeout(() => (inviteSent.value = false), 4000);
        },
    });
}

function onCsvChange(e) {
    csvFile.value = e.target.files[0] ?? null;
    csvInviteForm.csv = csvFile.value;
}

function sendCsvInvite() {
    csvInviteForm.post(`/communities/${props.community.slug}/invite`, {
        preserveScroll: true,
        onSuccess: () => {
            csvFile.value = null;
            csvInviteForm.reset();
            inviteSentMessage.value = usePage().props.flash?.success ?? 'Invites sent!';
            inviteSent.value = true;
            setTimeout(() => (inviteSent.value = false), 4000);
        },
    });
}
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <div class="bg-white border border-gray-200 rounded-2xl p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-1">✉️ Invite Members</h2>
            <p class="text-sm text-gray-500 mb-5">
                Add existing members by email, or batch upload a CSV file (one email per row).
                They'll receive a personal invite link granting instant access.
            </p>

            <!-- Tab toggle -->
            <div class="flex gap-2 mb-4">
                <button
                    type="button"
                    @click="inviteTab = 'single'"
                    class="px-3.5 py-1.5 text-sm font-medium rounded-lg border transition-colors"
                    :class="inviteTab === 'single' ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-600 border-gray-300 hover:bg-gray-50'"
                >Single email</button>
                <button
                    type="button"
                    @click="inviteTab = 'csv'"
                    class="px-3.5 py-1.5 text-sm font-medium rounded-lg border transition-colors"
                    :class="inviteTab === 'csv' ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-600 border-gray-300 hover:bg-gray-50'"
                >Batch CSV upload</button>
            </div>

            <!-- Single email -->
            <form v-if="inviteTab === 'single'" @submit.prevent="sendSingleInvite" class="flex items-end gap-3">
                <div class="flex-1 max-w-sm">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                    <input
                        v-model="inviteForm.email"
                        type="email"
                        required
                        placeholder="member@example.com"
                        class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="inviteForm.errors.email ? 'border-red-400' : 'border-gray-300'"
                    />
                    <p v-if="inviteForm.errors.email" class="mt-1 text-xs text-red-600">{{ inviteForm.errors.email }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="inviteForm.processing"
                    class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                >
                    {{ inviteForm.processing ? 'Sending...' : 'Send invite' }}
                </button>
            </form>

            <!-- CSV batch -->
            <form v-else @submit.prevent="sendCsvInvite" class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">CSV file <span class="text-gray-400 font-normal">(one email per row)</span></label>
                    <label class="flex items-center gap-2 w-fit cursor-pointer px-3.5 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        {{ csvFile ? csvFile.name : 'Choose CSV file' }}
                        <input type="file" accept=".csv,.txt" class="hidden" @change="onCsvChange" />
                    </label>
                    <p class="mt-1 text-xs text-gray-400">Max 2 MB · .csv or .txt · one email per line</p>
                    <p v-if="csvInviteForm.errors.csv" class="mt-1 text-xs text-red-600">{{ csvInviteForm.errors.csv }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="!csvFile || csvInviteForm.processing"
                    class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ csvInviteForm.processing ? 'Sending invites...' : 'Upload &amp; send invites' }}
                </button>
            </form>

            <p v-if="inviteSent" class="mt-3 text-sm text-green-600">{{ inviteSentMessage }}</p>
        </div>
    </CommunitySettingsLayout>
</template>
