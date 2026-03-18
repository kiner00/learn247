<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
                @click.self="$emit('close')"
            >
                <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
                    <div class="flex items-center justify-between mb-1">
                        <h2 class="text-lg font-bold text-gray-900">Invite people</h2>
                        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">Invite your friends to {{ communityName }}</p>

                    <!-- Share link -->
                    <div class="flex items-center gap-2 mb-5">
                        <input
                            :value="inviteUrl"
                            readonly
                            class="flex-1 text-sm px-3 py-2 border border-gray-200 rounded-xl bg-gray-50 font-mono truncate focus:outline-none"
                        />
                        <button
                            @click="copy"
                            class="shrink-0 px-4 py-2 bg-yellow-400 hover:bg-yellow-500 text-gray-900 text-sm font-bold rounded-xl transition-colors"
                        >
                            {{ copied ? 'Copied!' : 'Copy' }}
                        </button>
                    </div>

                    <!-- Owner-only: invite by email -->
                    <template v-if="isOwner">
                        <div class="border-t border-gray-100 pt-4">
                            <p class="text-sm font-semibold text-gray-700 mb-3">Or send a personal invite</p>

                            <!-- Tab toggle -->
                            <div class="flex gap-2 mb-3">
                                <button type="button" @click="inviteTab = 'single'"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors"
                                    :class="inviteTab === 'single' ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-600 border-gray-300 hover:bg-gray-50'"
                                >Single email</button>
                                <button type="button" @click="inviteTab = 'csv'"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors"
                                    :class="inviteTab === 'csv' ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-600 border-gray-300 hover:bg-gray-50'"
                                >Batch CSV</button>
                                <button type="button" @click="openStatusTab"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors"
                                    :class="inviteTab === 'status' ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-600 border-gray-300 hover:bg-gray-50'"
                                >Sent invites</button>
                            </div>

                            <!-- Single email -->
                            <form v-if="inviteTab === 'single'" @submit.prevent="sendSingleInvite" class="flex gap-2">
                                <input
                                    v-model="emailInput"
                                    type="email"
                                    required
                                    placeholder="member@example.com"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                                <button type="submit" :disabled="sending"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                                >{{ sending ? '...' : 'Send' }}</button>
                            </form>

                            <!-- CSV batch -->
                            <form v-else-if="inviteTab === 'csv'" @submit.prevent="sendCsvInvite" class="space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <label class="flex items-center gap-2 w-fit cursor-pointer px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                        </svg>
                                        {{ csvFile ? csvFile.name : 'Choose CSV file' }}
                                        <input type="file" accept=".csv,.txt" class="hidden" @change="onCsvChange" />
                                    </label>
                                    <button type="button" @click="downloadTemplate"
                                        class="flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium transition-colors shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        Download template
                                    </button>
                                </div>
                                <p class="text-xs text-gray-400">One email per row · max 2 MB</p>
                                <button type="submit" :disabled="!csvFile || sending"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >{{ sending ? 'Sending...' : 'Upload & send invites' }}</button>
                            </form>

                            <p v-if="inviteResult" class="mt-2 text-sm" :class="inviteError ? 'text-red-600' : 'text-green-600'">{{ inviteResult }}</p>

                            <!-- Sent invites status list -->
                            <div v-if="inviteTab === 'status'">
                                <div v-if="statusLoading" class="py-6 text-center text-sm text-gray-400">Loading...</div>
                                <div v-else-if="statusList.length === 0" class="py-6 text-center text-sm text-gray-400">No invites sent yet.</div>
                                <div v-else class="divide-y divide-gray-100 max-h-64 overflow-y-auto -mx-1 px-1">
                                    <div
                                        v-for="invite in statusList"
                                        :key="invite.email"
                                        class="flex items-center justify-between py-2.5 gap-3"
                                    >
                                        <span class="text-sm text-gray-700 truncate flex-1">{{ invite.email }}</span>
                                        <span
                                            class="shrink-0 text-xs font-medium px-2 py-0.5 rounded-full"
                                            :class="{
                                                'bg-green-100 text-green-700': invite.status === 'accepted',
                                                'bg-yellow-100 text-yellow-700': invite.status === 'pending',
                                                'bg-red-100 text-red-700': invite.status === 'expired',
                                            }"
                                        >
                                            {{ invite.status }}
                                        </span>
                                        <button
                                            v-if="invite.status !== 'accepted'"
                                            type="button"
                                            class="shrink-0 text-xs text-indigo-600 hover:underline"
                                            @click="resendInvite(invite.email)"
                                        >Resend</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    show:          Boolean,
    communityName: String,
    communitySlug: String,
    inviteUrl:     String,
    isOwner:       Boolean,
});

defineEmits(['close']);

const copied        = ref(false);
const inviteTab     = ref('single');
const emailInput    = ref('');
const csvFile       = ref(null);
const sending       = ref(false);
const inviteResult  = ref('');
const inviteError   = ref(false);
const statusList    = ref([]);
const statusLoading = ref(false);

function copy() {
    navigator.clipboard.writeText(props.inviteUrl).then(() => {
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    });
}

function openStatusTab() {
    inviteTab.value = 'status';
    statusLoading.value = true;
    axios.get(`/communities/${props.communitySlug}/invites`)
        .then(({ data }) => { statusList.value = data; })
        .finally(() => { statusLoading.value = false; });
}

function resendInvite(email) {
    const formData = new FormData();
    formData.append('email', email);
    axios.post(`/communities/${props.communitySlug}/invite`, formData)
        .then(() => openStatusTab())
        .catch(() => {});
}

function sendSingleInvite() {
    if (!emailInput.value) return;
    sending.value = true;
    inviteResult.value = '';

    const formData = new FormData();
    formData.append('email', emailInput.value);

    axios.post(`/communities/${props.communitySlug}/invite`, formData)
        .then(({ data }) => {
            inviteError.value  = false;
            inviteResult.value = data.message ?? 'Invite sent!';
            emailInput.value   = '';
        })
        .catch(err => {
            inviteError.value  = true;
            inviteResult.value = err.response?.data?.message ?? 'Something went wrong.';
        })
        .finally(() => { sending.value = false; });
}

function downloadTemplate() {
    const content = 'email\njohn@example.com\njane@example.com\n';
    const blob = new Blob([content], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'invite-template.csv';
    a.click();
    URL.revokeObjectURL(url);
}

function onCsvChange(e) {
    csvFile.value = e.target.files[0] ?? null;
}

function sendCsvInvite() {
    if (!csvFile.value) return;
    sending.value = true;
    inviteResult.value = '';

    const formData = new FormData();
    formData.append('csv', csvFile.value);

    axios.post(`/communities/${props.communitySlug}/invite`, formData)
        .then(({ data }) => {
            inviteError.value  = false;
            inviteResult.value = data.message ?? 'Invites sent!';
            csvFile.value      = null;
        })
        .catch(err => {
            inviteError.value  = true;
            inviteResult.value = err.response?.data?.message ?? 'Something went wrong.';
        })
        .finally(() => { sending.value = false; });
}
</script>
