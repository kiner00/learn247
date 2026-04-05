<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';

const props = defineProps({
    community: Object,
    hasApiKey: Boolean,
    emailProvider: String,
    fromEmail: String,
    fromName: String,
    replyTo: String,
    domainId: String,
    domainStatus: String,
    providers: Array,
});

const saved = ref(false);
const testingEmail = ref(false);
const testEmail = ref('');
const testSuccess = ref('');
const testError = ref('');
const domainError = ref('');
const domainSuccess = ref('');
const domainRecords = ref([]);
const loadingDomain = ref(false);
const addingDomain = ref(false);
const verifyingDomain = ref(false);
const newDomain = ref('');

const form = useForm({
    email_provider: props.emailProvider ?? '',
    resend_api_key: '',
    resend_from_email: props.fromEmail ?? '',
    resend_from_name: props.fromName ?? '',
    resend_reply_to: props.replyTo ?? '',
});

const selectedProvider = computed(() =>
    props.providers?.find(p => p.id === form.email_provider)
);

const apiKeyLabel = computed(() => {
    if (form.email_provider === 'ses') return 'AWS Credentials';
    return 'API Key';
});

const apiKeyPlaceholder = computed(() => {
    if (props.hasApiKey) return '••••••••••••••••';
    if (form.email_provider === 'ses') return 'ACCESS_KEY_ID:SECRET_ACCESS_KEY:us-east-1';
    if (form.email_provider === 'sendgrid') return 'SG.xxxxxxxxxx...';
    if (form.email_provider === 'postmark') return 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
    if (form.email_provider === 'mailgun') return 'key-xxxxxxxxxx...';
    return 're_xxxxxxxxxx...';
});

function saveConfig() {
    form.post(`/communities/${props.community.slug}/resend-config`, {
        preserveScroll: true,
        onSuccess: () => {
            saved.value = true;
            setTimeout(() => (saved.value = false), 3000);
        },
    });
}

function sendTestEmail() {
    testingEmail.value = true;
    testSuccess.value = '';
    testError.value = '';
    router.post(`/communities/${props.community.slug}/resend-test`, { test_email: testEmail.value }, {
        preserveScroll: true,
        onSuccess: (page) => {
            testSuccess.value = page.props.flash?.success ?? 'Test email sent!';
            setTimeout(() => (testSuccess.value = ''), 5000);
        },
        onError: (errors) => {
            testError.value = errors.resend_test ?? 'Test failed.';
            setTimeout(() => (testError.value = ''), 6000);
        },
        onFinish: () => { testingEmail.value = false; },
    });
}

function addDomain() {
    if (!newDomain.value.trim()) return;
    addingDomain.value = true;
    domainError.value = '';
    domainSuccess.value = '';
    router.post(`/communities/${props.community.slug}/resend-add-domain`, { domain: newDomain.value }, {
        preserveScroll: true,
        onSuccess: (page) => {
            domainSuccess.value = page.props.flash?.success ?? 'Domain added!';
            setTimeout(() => (domainSuccess.value = ''), 5000);
            fetchDomainRecords();
        },
        onError: (errors) => {
            domainError.value = errors.resend_domain ?? 'Failed to add domain.';
            setTimeout(() => (domainError.value = ''), 6000);
        },
        onFinish: () => { addingDomain.value = false; },
    });
}

function verifyDomain() {
    verifyingDomain.value = true;
    domainError.value = '';
    domainSuccess.value = '';
    router.post(`/communities/${props.community.slug}/resend-verify-domain`, {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            domainSuccess.value = page.props.flash?.success ?? 'Verification triggered.';
            setTimeout(() => (domainSuccess.value = ''), 5000);
            fetchDomainRecords();
        },
        onError: (errors) => {
            domainError.value = errors.resend_domain ?? 'Verification failed.';
            setTimeout(() => (domainError.value = ''), 6000);
        },
        onFinish: () => { verifyingDomain.value = false; },
    });
}

async function fetchDomainRecords() {
    if (!props.domainId && !props.hasApiKey) return;
    loadingDomain.value = true;
    try {
        const res = await fetch(`/communities/${props.community.slug}/resend-domain-info`);
        if (res.ok) {
            const data = await res.json();
            domainRecords.value = data.records || [];
        }
    } catch {
        // silent
    } finally {
        loadingDomain.value = false;
    }
}

onMounted(() => {
    if (props.domainId) fetchDomainRecords();
});
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <div class="space-y-6">
            <!-- Provider + API Key Section -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6">
                <div class="flex items-center gap-2 mb-1">
                    <h2 class="text-base font-semibold text-gray-900">Email Settings</h2>
                    <span class="px-2.5 py-1 text-xs font-bold bg-indigo-100 text-indigo-700 rounded-full">Pro</span>
                </div>
                <p class="text-sm text-gray-500 mb-5">
                    Connect your email provider to send campaigns to your community members.
                </p>

                <form @submit.prevent="saveConfig" class="space-y-5">
                    <!-- Provider selector -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Provider</label>
                        <select
                            v-model="form.email_provider"
                            class="w-full max-w-md px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white"
                        >
                            <option value="">— Select provider —</option>
                            <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.label }}</option>
                        </select>
                    </div>

                    <template v-if="form.email_provider">
                        <!-- Provider info strip -->
                        <div v-if="selectedProvider" class="p-3 bg-gray-50 rounded-lg text-xs text-gray-500">
                            <p><strong>{{ selectedProvider.label }}</strong> — {{ selectedProvider.help }}</p>
                        </div>

                        <!-- API Key -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ apiKeyLabel }}</label>
                            <input
                                v-model="form.resend_api_key"
                                type="password"
                                :placeholder="apiKeyPlaceholder"
                                class="w-full max-w-md px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            />
                            <p v-if="form.errors.resend_api_key" class="mt-1 text-xs text-red-600">{{ form.errors.resend_api_key }}</p>
                        </div>

                        <!-- From Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">From Email</label>
                            <input
                                v-model="form.resend_from_email"
                                type="email"
                                placeholder="hello@yourdomain.com"
                                class="w-full max-w-md px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            />
                            <p class="mt-1 text-xs text-gray-400">The email address your members will see. Must be from a verified domain.</p>
                        </div>

                        <!-- From Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">From Name</label>
                            <input
                                v-model="form.resend_from_name"
                                type="text"
                                :placeholder="community.name"
                                class="w-full max-w-md px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            />
                        </div>

                        <!-- Reply-To -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Reply-To Email</label>
                            <input
                                v-model="form.resend_reply_to"
                                type="email"
                                placeholder="you@gmail.com"
                                class="w-full max-w-md px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            />
                            <p class="mt-1 text-xs text-gray-400">When members reply to your emails, replies go to this address. Set this to your personal or support email.</p>
                        </div>
                    </template>

                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <button
                            type="submit"
                            :disabled="form.processing || !form.email_provider"
                            class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                        >
                            Save email settings
                        </button>
                        <p v-if="saved" class="text-sm text-green-600">Saved!</p>
                    </div>
                </form>
            </div>

            <!-- Domain Verification Section -->
            <div v-if="hasApiKey" class="bg-white border border-gray-200 rounded-2xl p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-1">Domain Verification</h2>
                <p class="text-sm text-gray-500 mb-5">
                    Verify your domain to improve deliverability and send from your custom email address.
                </p>

                <div v-if="!domainId" class="flex items-end gap-3 mb-4">
                    <div class="flex-1 max-w-sm">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Domain</label>
                        <input
                            v-model="newDomain"
                            type="text"
                            placeholder="yourdomain.com"
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />
                    </div>
                    <button
                        @click="addDomain"
                        :disabled="addingDomain || !newDomain.trim()"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                        {{ addingDomain ? 'Adding...' : 'Add Domain' }}
                    </button>
                </div>

                <div v-if="domainId" class="mb-4">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-sm font-medium text-gray-700">Status:</span>
                        <span
                            class="px-2.5 py-1 text-xs font-bold rounded-full"
                            :class="domainStatus === 'verified'
                                ? 'bg-green-100 text-green-700'
                                : 'bg-yellow-100 text-yellow-700'"
                        >
                            {{ domainStatus ?? 'pending' }}
                        </span>
                        <button
                            v-if="domainStatus !== 'verified'"
                            @click="verifyDomain"
                            :disabled="verifyingDomain"
                            class="px-4 py-1.5 border border-indigo-300 text-indigo-700 text-xs font-medium rounded-lg hover:bg-indigo-50 transition-colors disabled:opacity-50"
                        >
                            {{ verifyingDomain ? 'Verifying...' : 'Verify' }}
                        </button>
                    </div>

                    <div v-if="domainRecords.length" class="overflow-x-auto">
                        <p class="text-sm text-gray-500 mb-3">Add these DNS records to your domain registrar:</p>
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500 border-b">
                                    <th class="pb-2 pr-4 font-medium">Type</th>
                                    <th class="pb-2 pr-4 font-medium">Name</th>
                                    <th class="pb-2 pr-4 font-medium">Value</th>
                                    <th class="pb-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="record in domainRecords" :key="record.name" class="border-b border-gray-100">
                                    <td class="py-2 pr-4 font-mono text-gray-700">{{ record.type }}</td>
                                    <td class="py-2 pr-4 font-mono text-gray-700 break-all max-w-[200px]">{{ record.name }}</td>
                                    <td class="py-2 pr-4 font-mono text-gray-600 break-all max-w-[300px]">{{ record.value }}</td>
                                    <td class="py-2">
                                        <span
                                            class="px-2 py-0.5 text-xs rounded-full"
                                            :class="record.status === 'verified'
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-yellow-100 text-yellow-700'"
                                        >
                                            {{ record.status ?? 'pending' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else-if="loadingDomain" class="text-sm text-gray-400">Loading DNS records...</p>
                </div>

                <p v-if="domainSuccess" class="text-sm text-green-600">{{ domainSuccess }}</p>
                <p v-if="domainError" class="text-sm text-red-600">{{ domainError }}</p>
            </div>

            <!-- Test Email Section -->
            <div v-if="hasApiKey" class="bg-white border border-gray-200 rounded-2xl p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-1">Send Test Email</h2>
                <p class="text-sm text-gray-500 mb-4">
                    Send a test email to verify your configuration is working.
                </p>
                <div class="flex items-end gap-3">
                    <div class="flex-1 max-w-sm">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                        <input
                            v-model="testEmail"
                            type="email"
                            placeholder="you@example.com"
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent"
                        />
                    </div>
                    <button
                        @click="sendTestEmail"
                        :disabled="testingEmail || !testEmail.trim()"
                        class="px-4 py-2.5 border border-emerald-400 text-emerald-700 text-sm font-medium rounded-lg hover:bg-emerald-50 transition-colors disabled:opacity-50 flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        {{ testingEmail ? 'Sending...' : 'Send Test' }}
                    </button>
                </div>
                <p v-if="testSuccess" class="mt-2 text-sm text-green-600">{{ testSuccess }}</p>
                <p v-if="testError" class="mt-2 text-sm text-red-600">{{ testError }}</p>
            </div>
        </div>
    </CommunitySettingsLayout>
</template>
