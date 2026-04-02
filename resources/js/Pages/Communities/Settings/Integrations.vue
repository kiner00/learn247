<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';

const props = defineProps({
    community:          Object,
    canUseIntegrations: { type: Boolean, default: false },
    isPro:              { type: Boolean, default: false },
});

// ─── Integrations ────────────────────────────────────────────────────────────
const integrationsSaved = ref(false);

const integrationsForm = useForm({
    name:                props.community.name,
    facebook_pixel_id:   props.community.facebook_pixel_id   ?? '',
    tiktok_pixel_id:     props.community.tiktok_pixel_id     ?? '',
    google_analytics_id: props.community.google_analytics_id ?? '',
});

function saveIntegrations() {
    integrationsForm.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            onSuccess: () => {
                integrationsSaved.value = true;
                setTimeout(() => (integrationsSaved.value = false), 3000);
            },
        });
}

// ─── Telegram ────────────────────────────────────────────────────────────────
const telegramSaved = ref(false);
const telegramForm  = useForm({
    name:               props.community.name,
    telegram_bot_token: '',
    telegram_chat_id:   props.community.telegram_chat_id ?? '',
    telegram_clear:     false,
});

function saveTelegram() {
    telegramForm.telegram_clear = false;
    telegramForm.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            onSuccess: () => {
                telegramSaved.value = true;
                telegramForm.telegram_bot_token = '';
                setTimeout(() => (telegramSaved.value = false), 4000);
            },
        });
}

function disconnectTelegram() {
    if (!confirm('Disconnect Telegram? Messages will no longer sync.')) return;
    telegramForm.telegram_clear     = true;
    telegramForm.telegram_bot_token = '';
    telegramForm.telegram_chat_id   = '';
    telegramForm.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            onSuccess: () => {
                telegramForm.telegram_clear = false;
            },
        });
}
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <!-- Integrations -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
            <div class="flex items-center gap-2 mb-1">
                <h2 class="text-base font-semibold text-gray-900">Integrations</h2>
                <span class="px-2.5 py-1 text-xs font-bold bg-blue-100 text-blue-700 rounded-full">Basic+</span>
            </div>
            <p class="text-sm text-gray-500 mb-5">
                Connect third-party tools to track conversions and optimize your ads.
            </p>

            <!-- Locked state for Free plan -->
            <div v-if="!canUseIntegrations" class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-6 text-center">
                <p class="text-2xl mb-2">🔒</p>
                <p class="text-sm font-semibold text-gray-700">Available on Basic &amp; Pro</p>
                <p class="text-xs text-gray-500 mt-1 mb-4">Upgrade to connect Facebook Pixel, TikTok Pixel, and Google Analytics.</p>
                <a href="/creator/plan" class="inline-block px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 transition-colors">Upgrade Plan →</a>
            </div>

            <form v-else @submit.prevent="saveIntegrations" class="space-y-5">
                <!-- Facebook Pixel -->
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1.5">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.234 2.686.234v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
                        Facebook Pixel ID
                    </label>
                    <input
                        v-model="integrationsForm.facebook_pixel_id"
                        type="text"
                        placeholder="e.g. 1234567890123456"
                        maxlength="30"
                        class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        :class="integrationsForm.errors.facebook_pixel_id ? 'border-red-400' : ''"
                    />
                    <p class="mt-1 text-xs text-gray-400">Events Manager → your Pixel → Settings.</p>
                    <p v-if="integrationsForm.errors.facebook_pixel_id" class="mt-1 text-xs text-red-600">{{ integrationsForm.errors.facebook_pixel_id }}</p>
                </div>

                <!-- TikTok Pixel -->
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1.5">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.79 1.53V6.75a4.85 4.85 0 01-1.02-.06z"/></svg>
                        TikTok Pixel ID
                    </label>
                    <input
                        v-model="integrationsForm.tiktok_pixel_id"
                        type="text"
                        placeholder="e.g. C9ABCDEF12345678"
                        maxlength="30"
                        class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        :class="integrationsForm.errors.tiktok_pixel_id ? 'border-red-400' : ''"
                    />
                    <p class="mt-1 text-xs text-gray-400">TikTok Ads Manager → Assets → Events → Web Events.</p>
                    <p v-if="integrationsForm.errors.tiktok_pixel_id" class="mt-1 text-xs text-red-600">{{ integrationsForm.errors.tiktok_pixel_id }}</p>
                </div>

                <!-- Google Analytics -->
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1.5">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24"><path d="M12 22.5a2 2 0 002-2V3.5a2 2 0 00-4 0v17a2 2 0 002 2z" fill="#F9AB00"/><path d="M19.5 22.5a2 2 0 002-2v-7a2 2 0 00-4 0v7a2 2 0 002 2z" fill="#E37400"/><path d="M4.5 22.5a2.5 2.5 0 002.5-2.5v-1a2.5 2.5 0 00-5 0v1a2.5 2.5 0 002.5 2.5z" fill="#E37400"/></svg>
                        Google Analytics 4 ID
                    </label>
                    <input
                        v-model="integrationsForm.google_analytics_id"
                        type="text"
                        placeholder="e.g. G-XXXXXXXXXX"
                        maxlength="20"
                        class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        :class="integrationsForm.errors.google_analytics_id ? 'border-red-400' : ''"
                    />
                    <p class="mt-1 text-xs text-gray-400">GA4 → Admin → Data Streams → your stream → Measurement ID.</p>
                    <p v-if="integrationsForm.errors.google_analytics_id" class="mt-1 text-xs text-red-600">{{ integrationsForm.errors.google_analytics_id }}</p>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <button
                        type="submit"
                        :disabled="integrationsForm.processing"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                        Save integrations
                    </button>
                    <p v-if="integrationsSaved" class="text-sm text-green-600">Saved!</p>
                </div>

                <!-- Events legend -->
                <div v-if="integrationsForm.facebook_pixel_id || integrationsForm.tiktok_pixel_id || integrationsForm.google_analytics_id"
                     class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-xs font-semibold text-gray-600 mb-2">Events fired automatically across all active platforms:</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-gray-500">
                        <div>
                            <p class="font-semibold text-gray-600 mb-1">📄 Page Visit</p>
                            <p class="text-gray-400">FB: PageView<br>TT: page()<br>GA: page_view</p>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-600 mb-1">👁️ Landing Page</p>
                            <p class="text-gray-400">FB: ViewContent<br>TT: ViewContent<br>GA: view_item</p>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-600 mb-1">✋ Join Form</p>
                            <p class="text-gray-400">FB: Lead<br>TT: PlaceAnOrder<br>GA: begin_checkout</p>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-600 mb-1">💰 Payment</p>
                            <p class="text-gray-400">FB: Purchase<br>TT: CompletePayment<br>GA: purchase</p>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Telegram -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-5 h-5 text-sky-500 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z"/></svg>
                <h2 class="text-base font-semibold text-gray-900">Telegram Group Chat</h2>
                <span class="px-2.5 py-1 text-xs font-bold bg-purple-100 text-purple-700 rounded-full">⭐ Pro</span>
            </div>
            <p class="text-sm text-gray-500 mb-5">
                Sync your community chat with a Telegram group. Messages posted in the app are forwarded to Telegram, and messages from Telegram appear in the app chat.
            </p>

            <!-- Locked for non-Pro -->
            <div v-if="!isPro" class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-6 flex items-center gap-4">
                <span class="text-2xl">🔒</span>
                <div>
                    <p class="text-sm font-semibold text-gray-700">Available on Pro</p>
                    <p class="text-xs text-gray-500 mt-0.5 mb-3">Upgrade to connect your community chat to a Telegram group.</p>
                    <a href="/creator/plan" class="inline-block px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 transition-colors">Upgrade to Pro →</a>
                </div>
            </div>

            <template v-else>
                <!-- Setup guide -->
                <div class="mb-5 p-4 bg-sky-50 border border-sky-200 rounded-xl space-y-2 text-sm text-sky-800">
                    <p class="font-semibold">How to set up:</p>
                    <ol class="list-decimal list-inside space-y-1 text-sky-700">
                        <li>Open Telegram and message <span class="font-mono font-bold">@BotFather</span> → send <span class="font-mono">/newbot</span> → follow the steps to get your <strong>Bot Token</strong>.</li>
                        <li>Add your bot to the Telegram group and make it an <strong>Admin</strong> (so it can read and send messages).</li>
                        <li>Get your group's <strong>Chat ID</strong>: add <span class="font-mono font-bold">@userinfobot</span> to the group → it will reply with the chat ID (a negative number like <span class="font-mono">-1001234567890</span>).</li>
                        <li>Paste the Token and Chat ID below and click <strong>Save Telegram settings</strong>. The webhook is registered automatically.</li>
                    </ol>
                </div>

                <form @submit.prevent="saveTelegram" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Bot Token</label>
                        <input
                            v-model="telegramForm.telegram_bot_token"
                            type="password"
                            placeholder="123456789:AABBccDDeeFFggHHiiJJ..."
                            autocomplete="off"
                            class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"
                            :class="telegramForm.errors.telegram_bot_token ? 'border-red-400' : ''"
                        />
                        <p class="mt-1 text-xs text-gray-400">From @BotFather. Keep this private.</p>
                        <p v-if="telegramForm.errors.telegram_bot_token" class="mt-1 text-xs text-red-600">{{ telegramForm.errors.telegram_bot_token }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Group Chat ID</label>
                        <input
                            v-model="telegramForm.telegram_chat_id"
                            type="text"
                            placeholder="-1001234567890"
                            class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"
                            :class="telegramForm.errors.telegram_chat_id ? 'border-red-400' : ''"
                        />
                        <p class="mt-1 text-xs text-gray-400">Usually a negative number. Get it from @userinfobot.</p>
                        <p v-if="telegramForm.errors.telegram_chat_id" class="mt-1 text-xs text-red-600">{{ telegramForm.errors.telegram_chat_id }}</p>
                    </div>

                    <!-- Connected status -->
                    <div v-if="community.telegram_chat_id" class="flex items-center gap-2 text-sm text-green-700">
                        <svg class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Telegram group connected
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <button
                            type="submit"
                            :disabled="telegramForm.processing"
                            class="px-5 py-2.5 bg-sky-600 text-white text-sm font-medium rounded-lg hover:bg-sky-700 transition-colors disabled:opacity-50"
                        >
                            {{ telegramForm.processing ? 'Saving…' : 'Save Telegram settings' }}
                        </button>
                        <button
                            v-if="community.telegram_bot_token"
                            type="button"
                            @click="disconnectTelegram"
                            :disabled="telegramForm.processing"
                            class="px-4 py-2.5 border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors disabled:opacity-50"
                        >
                            Disconnect
                        </button>
                        <p v-if="telegramSaved" class="text-sm text-green-600">Saved! Webhook registered.</p>
                    </div>
                </form>
            </template>
        </div>
    </CommunitySettingsLayout>
</template>
