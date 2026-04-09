<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import CurzzoChat from '@/Components/CurzzoChat.vue';
import NewCurzzoForm from '@/Components/NewCurzzoForm.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const props = defineProps({
    community:  Object,
    curzzos:    { type: Array, default: () => [] },
    limitInfo:  { type: Object, default: () => ({}) },
    topupPacks: { type: Array, default: () => [] },
    isOwner:    { type: Boolean, default: false },
    modelTiers: { type: Array, default: () => [] },
});

const showNewForm = ref(false);

const { communityPath } = useCommunityUrl(props.community.slug);
const chatMode = ref(false);
const selectedCurzzo = ref(null);
const checkingOut = ref(false);

function selectBot(bot) {
    if (!bot.has_access) {
        startCheckout(bot);
        return;
    }
    selectedCurzzo.value = bot;
    chatMode.value = true;
}

function switchBot(bot) {
    if (!bot.has_access) {
        startCheckout(bot);
        return;
    }
    selectedCurzzo.value = bot;
}

function startCheckout(bot) {
    if (checkingOut.value) return;
    checkingOut.value = true;
    router.post(communityPath(`/curzzos/${bot.id}/checkout`), {}, {
        onFinish: () => { checkingOut.value = false; },
    });
}

function goBackToGrid() {
    selectedCurzzo.value = null;
    chatMode.value = false;
}

function goBack() {
    selectedCurzzo.value = null;
}

function formatPrice(bot) {
    if (!bot.price || bot.price <= 0) return 'Free';
    const symbol = bot.currency === 'USD' ? '$' : '₱';
    return `${symbol}${Number(bot.price).toLocaleString()}${bot.access_type === 'paid_monthly' ? '/mo' : ''}`;
}

function accessBadgeClass(bot) {
    const t = bot.access_type ?? 'free';
    if (t === 'free') return 'bg-green-100 text-green-700';
    if (t === 'inclusive') return 'bg-indigo-100 text-indigo-700';
    if (t === 'member_once') return 'bg-purple-100 text-purple-700';
    return 'bg-amber-100 text-amber-700';
}

function accessBadgeText(bot) {
    const t = bot.access_type ?? 'free';
    if (t === 'free') return 'FREE';
    if (t === 'inclusive') return 'INCLUDED';
    if (t === 'member_once') return 'ONE-TIME';
    return formatPrice(bot);
}
</script>

<template>
    <AppLayout :title="`${community.name} · Curzzos`" :community="community">
        <CommunityTabs :community="community" active-tab="curzzos" />

        <!-- ── View 1: Card Grid ─────────────────────────────────────── -->
        <template v-if="!chatMode">
            <div class="mb-4 flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Curzzos</h2>
                    <p class="text-sm text-gray-400 mt-0.5">Chat with specialized AI bots</p>
                </div>
                <button
                    v-if="isOwner && !showNewForm"
                    @click="showNewForm = true"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    New Curzzo
                </button>
            </div>

            <NewCurzzoForm
                v-if="showNewForm"
                :community="community"
                :model-tiers="modelTiers"
                @cancel="showNewForm = false"
                @created="showNewForm = false"
            />

            <!-- Empty state -->
            <div v-if="!curzzos.length" class="bg-white border border-gray-200 rounded-2xl p-16 text-center shadow-sm">
                <div class="w-14 h-14 rounded-2xl bg-indigo-50 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-700 mb-1">No bots available yet</p>
                <p class="text-xs text-gray-400">The creator hasn't added any AI bots yet.</p>
            </div>

            <!-- Bot card grid -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <div v-for="bot in curzzos" :key="bot.id" class="relative group h-full">
                    <button
                        @click="selectBot(bot)"
                        class="w-full text-left flex flex-col h-full bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all border border-gray-100"
                    >
                        <!-- Cover image -->
                        <div class="relative aspect-video bg-gray-900 overflow-hidden shrink-0">
                            <img v-if="bot.cover_image" :src="bot.cover_image" :alt="bot.name"
                                :class="['w-full h-full object-cover transition-transform duration-300',
                                    bot.has_access ? 'group-hover:scale-105' : 'blur-[1.5px]']" />
                            <div v-else
                                :class="['w-full h-full bg-gradient-to-br from-indigo-600 to-purple-700 flex items-center justify-center',
                                    !bot.has_access && 'opacity-80']">
                                <span class="text-4xl font-black text-white/20 select-none">{{ bot.name.charAt(0).toUpperCase() }}</span>
                            </div>
                            <!-- Avatar overlay -->
                            <div v-if="bot.avatar" class="absolute bottom-2.5 left-2.5 w-8 h-8 rounded-full border-2 border-white overflow-hidden shadow-sm z-[2]">
                                <img :src="bot.avatar" :alt="bot.name" class="w-full h-full object-cover" />
                            </div>
                            <!-- Lock overlay -->
                            <div v-if="!bot.has_access"
                                class="absolute inset-0 flex flex-col items-center justify-center bg-black/20">
                                <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center mb-2">
                                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                </div>
                                <span v-if="bot.access_type === 'paid_once' || bot.access_type === 'paid_monthly'"
                                    class="text-white text-xs font-bold bg-indigo-600 px-3 py-1 rounded-full">{{ formatPrice(bot) }}</span>
                                <span v-else-if="bot.access_type === 'member_once'"
                                    class="text-white text-xs font-semibold bg-purple-600/80 px-3 py-1 rounded-full">For past members</span>
                                <span v-else-if="bot.access_type === 'inclusive'"
                                    class="text-white text-xs font-semibold bg-black/40 px-3 py-1 rounded-full">Members only</span>
                                <span v-else
                                    class="text-white text-xs font-semibold bg-green-600/80 px-3 py-1 rounded-full">Sign up to access</span>
                            </div>
                        </div>
                        <!-- Content -->
                        <div class="p-4 flex flex-col flex-1">
                            <div class="flex items-start justify-between gap-2 mb-1">
                                <h3 class="font-bold text-gray-900 group-hover:text-indigo-700 transition-colors line-clamp-1">{{ bot.name }}</h3>
                                <span :class="['shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full', accessBadgeClass(bot)]">
                                    {{ accessBadgeText(bot) }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 line-clamp-2 leading-relaxed flex-1">{{ bot.description ?? '' }}</p>
                        </div>
                    </button>
                </div>
            </div>
        </template>

        <!-- ── View 2: Sidebar + Chat ────────────────────────────────── -->
        <template v-else>
            <div class="flex gap-0 rounded-2xl overflow-hidden h-[calc(100vh-280px)] md:h-[calc(100vh-220px)]">
                <!-- Bot list sidebar -->
                <div
                    class="bg-white border border-gray-200 overflow-y-auto shrink-0"
                    :class="selectedCurzzo ? 'hidden md:block w-72' : 'w-full md:w-72'"
                >
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">AI Bots</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Chat with a specialized bot</p>
                        </div>
                        <button @click="goBackToGrid" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium" title="Back to grid">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                            </svg>
                        </button>
                    </div>

                    <button
                        v-for="bot in curzzos"
                        :key="bot.id"
                        @click="switchBot(bot)"
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
                                <span v-if="!bot.has_access"
                                    class="shrink-0 px-1.5 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-700 rounded-full">
                                    {{ formatPrice(bot) }}
                                </span>
                                <span v-else-if="bot.access_type && bot.access_type !== 'free' && bot.has_access"
                                    class="shrink-0 px-1.5 py-0.5 text-[10px] font-bold bg-green-100 text-green-700 rounded-full">
                                    Unlocked
                                </span>
                            </div>
                            <p v-if="bot.description" class="text-xs text-gray-400 truncate">{{ bot.description }}</p>
                        </div>
                        <svg v-if="!bot.has_access" class="w-4 h-4 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
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
        </template>
    </AppLayout>
</template>
