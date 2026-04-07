<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import CurzzoChat from '@/Components/CurzzoChat.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const props = defineProps({
    community:  Object,
    curzzos:    { type: Array, default: () => [] },
    limitInfo:  { type: Object, default: () => ({}) },
    topupPacks: { type: Array, default: () => [] },
});

const { communityPath } = useCommunityUrl(props.community.slug);
const selectedCurzzo = ref(null);
const checkingOut = ref(false);

function selectBot(bot) {
    if (bot.has_access) {
        selectedCurzzo.value = bot;
    } else {
        startCheckout(bot);
    }
}

function startCheckout(bot) {
    if (checkingOut.value) return;
    checkingOut.value = true;
    router.post(communityPath(`/curzzos/${bot.id}/checkout`), {}, {
        onFinish: () => { checkingOut.value = false; },
    });
}

function goBack() {
    selectedCurzzo.value = null;
}

function formatPrice(bot) {
    if (!bot.price || bot.price <= 0) return 'Free';
    const symbol = bot.currency === 'USD' ? '$' : '₱';
    return `${symbol}${Number(bot.price).toLocaleString()}${bot.billing_type === 'monthly' ? '/mo' : ''}`;
}
</script>

<template>
    <AppLayout :title="`${community.name} · Curzzos`" :community="community">
        <CommunityTabs :community="community" active-tab="curzzos" />

        <div class="flex gap-0 rounded-2xl overflow-hidden h-[calc(100vh-280px)] md:h-[calc(100vh-220px)]">
            <!-- Bot list sidebar -->
            <div
                class="bg-white border border-gray-200 overflow-y-auto shrink-0"
                :class="selectedCurzzo ? 'hidden md:block w-72' : 'w-full md:w-72'"
            >
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">AI Bots</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Chat with a specialized bot</p>
                </div>

                <div v-if="!curzzos.length" class="px-4 py-12 text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-indigo-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5"/>
                        </svg>
                    </div>
                    <p class="text-sm text-gray-500">No bots available yet.</p>
                </div>

                <button
                    v-for="bot in curzzos"
                    :key="bot.id"
                    @click="selectBot(bot)"
                    class="w-full flex items-center gap-3 px-4 py-3.5 border-b border-gray-50 hover:bg-gray-50 transition-colors text-left"
                    :class="selectedCurzzo?.id === bot.id ? 'bg-indigo-50' : ''"
                >
                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center overflow-hidden shrink-0">
                        <img v-if="bot.avatar" :src="bot.avatar" :alt="bot.name" class="w-full h-full object-cover" />
                        <span v-else class="text-sm font-bold text-indigo-600">{{ bot.name.charAt(0).toUpperCase() }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ bot.name }}</p>
                            <span v-if="bot.price > 0 && !bot.has_access"
                                class="shrink-0 px-1.5 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-700 rounded-full">
                                {{ formatPrice(bot) }}
                            </span>
                            <span v-else-if="bot.price > 0 && bot.has_access"
                                class="shrink-0 px-1.5 py-0.5 text-[10px] font-bold bg-green-100 text-green-700 rounded-full">
                                Unlocked
                            </span>
                        </div>
                        <p v-if="bot.description" class="text-xs text-gray-400 truncate">{{ bot.description }}</p>
                    </div>
                    <!-- Lock icon for paid bots -->
                    <svg v-if="bot.price > 0 && !bot.has_access" class="w-4 h-4 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <!-- Chat panel -->
            <div
                class="flex-1 bg-white border-t border-b border-r border-gray-200"
                :class="selectedCurzzo ? 'block' : 'hidden md:block'"
            >
                <CurzzoChat
                    v-if="selectedCurzzo"
                    :key="selectedCurzzo.id"
                    :community="community"
                    :curzzo="selectedCurzzo"
                    :limit-info="limitInfo"
                    :topup-packs="topupPacks"
                    @back="goBack"
                />
                <!-- Empty state -->
                <div v-else class="flex items-center justify-center h-full">
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-indigo-100 flex items-center justify-center">
                            <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-500">Select a Curzzo to start chatting</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
