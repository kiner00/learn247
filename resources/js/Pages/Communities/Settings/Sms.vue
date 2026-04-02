<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';

const props = defineProps({
    community: Object,
});

const smsSaved       = ref(false);
const smsTesting     = ref(false);
const smsTestPhone   = ref('');
const smsTestSuccess = ref('');
const smsTestError   = ref('');

const smsForm = useForm({
    sms_provider:    props.community.sms_provider    ?? '',
    sms_api_key:     props.community.sms_api_key     ?? '',
    sms_sender_name: props.community.sms_sender_name ?? '',
    sms_device_url:  props.community.sms_device_url  ?? '',
});

function saveSmsConfig() {
    smsForm.post(`/communities/${props.community.slug}/sms-config`, {
        preserveScroll: true,
        onSuccess: () => {
            smsSaved.value = true;
            setTimeout(() => (smsSaved.value = false), 3000);
        },
    });
}

function sendTestSms() {
    smsTesting.value     = true;
    smsTestSuccess.value = '';
    smsTestError.value   = '';
    router.post(`/communities/${props.community.slug}/sms-test`, { phone: smsTestPhone.value }, {
        preserveScroll: true,
        onSuccess: (page) => {
            smsTestSuccess.value = page.props.flash?.success ?? 'Test SMS sent!';
            setTimeout(() => (smsTestSuccess.value = ''), 5000);
        },
        onError: (errors) => {
            smsTestError.value = errors.sms_test ?? 'Test failed.';
            setTimeout(() => (smsTestError.value = ''), 6000);
        },
        onFinish: () => { smsTesting.value = false; },
    });
}
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <div class="bg-white border border-gray-200 rounded-2xl p-6">
            <div class="flex items-center gap-2 mb-1">
                <h2 class="text-base font-semibold text-gray-900">SMS Blast</h2>
                <span class="px-2.5 py-1 text-xs font-bold bg-indigo-100 text-indigo-700 rounded-full">⭐ Pro</span>
            </div>
            <p class="text-sm text-gray-500 mb-5">
                Connect your SMS provider to send text blasts to your members. Members must have a phone number on their profile.
            </p>
            <form @submit.prevent="saveSmsConfig" class="space-y-5">
                <!-- Provider -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">SMS Provider</label>
                    <select
                        v-model="smsForm.sms_provider"
                        class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white"
                    >
                        <option value="">— Select provider —</option>
                        <option value="semaphore">Semaphore (PH)</option>
                        <option value="philsms">PhilSMS (PH)</option>
                        <option value="xtreme_sms">Xtreme SMS (Android Gateway)</option>
                    </select>
                </div>

                <template v-if="smsForm.sms_provider">
                    <!-- API Key -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">API Key</label>
                        <input
                            v-model="smsForm.sms_api_key"
                            type="text"
                            placeholder="Your API key"
                            class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />
                        <p class="mt-1 text-xs text-gray-400">
                            <template v-if="smsForm.sms_provider === 'semaphore'">semaphore.co → Account → API Key</template>
                            <template v-else-if="smsForm.sms_provider === 'philsms'">app.philsms.com → Profile → API Token</template>
                            <template v-else>Your Xtreme SMS API key from the dashboard</template>
                        </p>
                    </div>

                    <!-- Sender name -->
                    <div v-if="smsForm.sms_provider !== 'xtreme_sms'">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Sender Name <span class="text-gray-400 font-normal">(max 11 chars)</span>
                        </label>
                        <input
                            v-model="smsForm.sms_sender_name"
                            type="text"
                            maxlength="11"
                            placeholder="e.g. MyBrand"
                            class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />
                        <p class="mt-1 text-xs text-gray-400">
                            <template v-if="smsForm.sms_provider === 'semaphore'">Approved sender name on your Semaphore account.</template>
                            <template v-else>Approved Sender ID on your PhilSMS account (optional).</template>
                        </p>
                    </div>

                    <!-- Device URL (Xtreme SMS only) -->
                    <div v-if="smsForm.sms_provider === 'xtreme_sms'">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Gateway URL</label>
                        <input
                            v-model="smsForm.sms_device_url"
                            type="url"
                            placeholder="https://your-xtreme-sms-server.com"
                            class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />
                        <p class="mt-1 text-xs text-gray-400">Your Xtreme SMS server URL (e.g. https://sms.xtremesuccess.ph)</p>
                    </div>
                </template>

                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <button
                        type="submit"
                        :disabled="smsForm.processing"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                        Save SMS settings
                    </button>
                    <template v-if="community.sms_provider && community.sms_api_key">
                        <input
                            v-model="smsTestPhone"
                            type="tel"
                            placeholder="e.g. 09171234567"
                            class="px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 w-40"
                        />
                        <button
                            type="button"
                            :disabled="smsTesting || !smsTestPhone.trim()"
                            @click="sendTestSms"
                            class="px-4 py-2.5 border border-emerald-400 text-emerald-700 text-sm font-medium rounded-lg hover:bg-emerald-50 transition-colors disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ smsTesting ? 'Sending…' : 'Test' }}
                        </button>
                    </template>
                    <p v-if="smsSaved" class="text-sm text-green-600">Saved!</p>
                    <p v-if="smsTestSuccess" class="text-sm text-green-600">{{ smsTestSuccess }}</p>
                    <p v-if="smsTestError" class="text-sm text-red-600">{{ smsTestError }}</p>
                </div>

                <!-- Provider info strip -->
                <div v-if="smsForm.sms_provider" class="p-3 bg-gray-50 rounded-lg text-xs text-gray-500 space-y-1">
                    <template v-if="smsForm.sms_provider === 'semaphore'">
                        <p><strong>Semaphore</strong> — Philippine SMS gateway. Charges per message sent. Sign up at <span class="font-mono">semaphore.co</span>.</p>
                    </template>
                    <template v-else-if="smsForm.sms_provider === 'philsms'">
                        <p><strong>PhilSMS</strong> — Philippine SMS gateway. Pay-as-you-go credits. Sign up at <span class="font-mono">philsms.com</span>.</p>
                    </template>
                    <template v-else>
                        <p><strong>Xtreme SMS</strong> — Uses an Android phone as an SMS gateway. Requires the Xtreme SMS app installed on your device.</p>
                    </template>
                </div>
            </form>
        </div>
    </CommunitySettingsLayout>
</template>
