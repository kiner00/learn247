<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';

const props = defineProps({
    community:  Object,
    isPro:      { type: Boolean, default: false },
    baseDomain: { type: String, default: 'curzzo.com' },
    serverIp:   { type: String, default: '' },
});

const domainSaved = ref(false);

const domainForm = useForm({
    name:          props.community.name,
    subdomain:     props.community.subdomain    ?? '',
    custom_domain: props.community.custom_domain ?? '',
});

function saveDomain() {
    domainForm.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            onSuccess: () => {
                domainSaved.value = true;
                setTimeout(() => (domainSaved.value = false), 3000);
            },
        });
}
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <div class="bg-white border border-gray-200 rounded-2xl p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Domain</h2>
            <p class="text-sm text-gray-500 mb-5">
                Give your community its own address on the web.
            </p>

            <form @submit.prevent="saveDomain" class="space-y-6">
                <!-- Subdomain -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Subdomain
                        <span class="ml-1 px-2 py-0.5 text-xs font-bold bg-green-100 text-green-700 rounded-full">Free</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-2">
                        Your community gets its own address under <strong>{{ baseDomain }}</strong>. No DNS setup required.
                    </p>
                    <div class="flex items-stretch max-w-sm">
                        <input
                            v-model="domainForm.subdomain"
                            type="text"
                            placeholder="yourname"
                            maxlength="63"
                            class="flex-1 min-w-0 px-3.5 py-2.5 border rounded-l-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            :class="domainForm.errors.subdomain ? 'border-red-400' : 'border-gray-300'"
                        />
                        <span class="inline-flex items-center px-3 border border-l-0 border-gray-300 rounded-r-lg bg-gray-50 text-sm text-gray-500 whitespace-nowrap">.{{ baseDomain }}</span>
                    </div>
                    <p v-if="domainForm.errors.subdomain" class="mt-1 text-xs text-red-600">{{ domainForm.errors.subdomain }}</p>
                    <p v-else-if="domainForm.subdomain" class="mt-1 text-xs text-indigo-600 font-medium">
                        Preview: {{ domainForm.subdomain }}.{{ baseDomain }}
                    </p>
                    <p v-else class="mt-1 text-xs text-gray-400">Lowercase letters, numbers, and hyphens only. No spaces.</p>
                </div>

                <!-- Custom Domain -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Custom Domain
                        <span class="ml-1 px-2 py-0.5 text-xs font-bold bg-purple-100 text-purple-700 rounded-full">Pro</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-3">
                        Use your own domain like <span class="font-mono">myclassroom.com</span>. You'll need to update your DNS records.
                    </p>

                    <!-- Locked for non-Pro -->
                    <div v-if="!isPro" class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-5 flex items-center gap-4">
                        <span class="text-2xl">🔒</span>
                        <div>
                            <p class="text-sm font-semibold text-gray-700">Available on Pro</p>
                            <p class="text-xs text-gray-500 mt-0.5 mb-3">Upgrade to connect your own domain to this community.</p>
                            <a href="/creator/plan" class="inline-block px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 transition-colors">Upgrade to Pro →</a>
                        </div>
                    </div>

                    <!-- Pro: input + DNS guide -->
                    <template v-else>
                        <input
                            v-model="domainForm.custom_domain"
                            type="text"
                            placeholder="myclassroom.com"
                            maxlength="253"
                            class="w-full max-w-sm px-3.5 py-2.5 border rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            :class="domainForm.errors.custom_domain ? 'border-red-400' : 'border-gray-300'"
                        />
                        <p v-if="domainForm.errors.custom_domain" class="mt-1 text-xs text-red-600">{{ domainForm.errors.custom_domain }}</p>
                        <p v-else class="mt-1 text-xs text-gray-400">Enter the domain you own, without http:// or a trailing slash.</p>

                        <!-- DNS instructions -->
                        <div v-if="domainForm.custom_domain" class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-xl space-y-3">
                            <p class="text-sm font-semibold text-blue-800">DNS Setup Instructions</p>
                            <p class="text-xs text-blue-700">
                                Log in to your domain registrar (GoDaddy, Namecheap, Cloudflare, etc.) and add one of the following records:
                            </p>
                            <div class="space-y-2 text-xs">
                                <div class="bg-white border border-blue-100 rounded-lg p-3">
                                    <p class="font-semibold text-blue-700 mb-1">Option A — A Record <span class="font-normal text-blue-500">(recommended)</span></p>
                                    <table class="w-full font-mono text-gray-700">
                                        <tr class="text-gray-400 text-[11px]"><th class="text-left pr-4">Type</th><th class="text-left pr-4">Host / Name</th><th class="text-left">Value</th></tr>
                                        <tr><td class="pr-4">A</td><td class="pr-4">@ or {{ domainForm.custom_domain }}</td><td class="text-indigo-700">{{ serverIp || 'your-server-ip' }}</td></tr>
                                    </table>
                                </div>
                                <div class="bg-white border border-blue-100 rounded-lg p-3">
                                    <p class="font-semibold text-blue-700 mb-1">Option B — CNAME Record</p>
                                    <table class="w-full font-mono text-gray-700">
                                        <tr class="text-gray-400 text-[11px]"><th class="text-left pr-4">Type</th><th class="text-left pr-4">Host / Name</th><th class="text-left">Value</th></tr>
                                        <tr><td class="pr-4">CNAME</td><td class="pr-4">@ or www</td><td class="text-indigo-700">{{ baseDomain }}</td></tr>
                                    </table>
                                </div>
                            </div>
                            <p class="text-xs text-blue-600">DNS changes can take up to 24–48 hours to propagate worldwide. Once active, your community will be accessible at your domain.</p>
                        </div>
                    </template>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <button
                        type="submit"
                        :disabled="domainForm.processing"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                        {{ domainForm.processing ? 'Saving...' : 'Save domain settings' }}
                    </button>
                    <p v-if="domainSaved" class="text-sm text-green-600">Saved!</p>
                </div>
            </form>
        </div>
    </CommunitySettingsLayout>
</template>
