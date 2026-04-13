<script setup>
import { ref, nextTick, onMounted, computed } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const props = defineProps({
    community: Object,
    curzzo: Object,
    limitInfo: { type: Object, default: () => ({}) },
    topupPacks: { type: Array, default: () => [] },
});

const emit = defineEmits(['back']);

const { communityPath } = useCommunityUrl(props.community.slug);

const messages = ref([]);
const newMessage = ref('');
const conversationId = ref(null);
const loading = ref(false);
const loadingHistory = ref(true);
const chatContainer = ref(null);
const inputEl = ref(null);

function autoResize() {
    nextTick(() => {
        if (!inputEl.value) return;
        inputEl.value.style.height = 'auto';
        inputEl.value.style.height = Math.min(inputEl.value.scrollHeight, 120) + 'px';
    });
}
const limitReached = ref(false);
const limitReason = ref('');
const checkingOut = ref(false);

// Usage tracking (updated from server responses)
const dailyLimit = ref(props.limitInfo.daily_limit ?? 0);
const dailyUsed = ref(props.limitInfo.daily_used ?? 0);
const topupRemaining = ref(props.limitInfo.topup_remaining ?? 0);

const isUnlimited = computed(() => dailyLimit.value === -1);
const usageText = computed(() => {
    if (isUnlimited.value) return 'Unlimited';
    return `${dailyUsed.value}/${dailyLimit.value}`;
});
const usagePercent = computed(() => {
    if (isUnlimited.value || dailyLimit.value <= 0) return 0;
    return Math.min(100, (dailyUsed.value / dailyLimit.value) * 100);
});

function scrollToBottom() {
    nextTick(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    });
}

async function loadHistory() {
    loadingHistory.value = true;
    try {
        const { data } = await axios.get(communityPath(`/curzzos/${props.curzzo.id}/history`));
        messages.value = data.messages ?? [];
        scrollToBottom();
    } catch (e) {
        messages.value = [{ role: 'assistant', text: 'Failed to load chat history. Please try again.' }];
    } finally {
        loadingHistory.value = false;
    }
}

async function send() {
    const text = newMessage.value.trim();
    if (!text || loading.value || limitReached.value) return;

    newMessage.value = '';
    autoResize();
    messages.value.push({ role: 'user', text });
    scrollToBottom();

    loading.value = true;
    try {
        const { data } = await axios.post(communityPath(`/curzzos/${props.curzzo.id}/chat`), {
            message: text,
            conversation_id: conversationId.value,
        });
        conversationId.value = data.conversation_id;
        messages.value.push({ role: 'assistant', text: data.message });

        // Update usage from response
        if (data.daily_limit !== undefined) dailyLimit.value = data.daily_limit;
        if (data.daily_used !== undefined) dailyUsed.value = data.daily_used;
        if (data.topup_remaining !== undefined) topupRemaining.value = data.topup_remaining;

        scrollToBottom();
    } catch (e) {
        if (e.response?.status === 429 && e.response?.data?.limit_reached) {
            limitReached.value = true;
            limitReason.value = e.response.data.error;
            if (e.response.data.daily_limit !== undefined) dailyLimit.value = e.response.data.daily_limit;
            if (e.response.data.daily_used !== undefined) dailyUsed.value = e.response.data.daily_used;
            if (e.response.data.topup_remaining !== undefined) topupRemaining.value = e.response.data.topup_remaining;
            // Remove the optimistic user message
            messages.value.pop();
        } else {
            messages.value.push({ role: 'assistant', text: 'Something went wrong. Please try again.' });
        }
        scrollToBottom();
    } finally {
        loading.value = false;
    }
}

function buyTopup(packIndex) {
    if (checkingOut.value) return;
    checkingOut.value = true;
    router.post(communityPath('/curzzos/topup/checkout'), { pack_index: packIndex }, {
        onFinish: () => { checkingOut.value = false; },
    });
}

function formatPackPrice(pack) {
    const symbol = (props.community.currency === 'USD') ? '$' : '₱';
    return `${symbol}${Number(pack.price).toLocaleString()}`;
}

onMounted(loadHistory);
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- Header -->
        <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-200 bg-white shrink-0">
            <button @click="emit('back')" class="text-gray-400 hover:text-gray-600 md:hidden">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center overflow-hidden shrink-0">
                <img v-if="curzzo.avatar" :src="curzzo.avatar" :alt="curzzo.name" class="w-full h-full object-cover" />
                <span v-else class="text-sm font-bold text-indigo-600">{{ curzzo.name.charAt(0).toUpperCase() }}</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900">{{ curzzo.name }}</p>
                <p v-if="curzzo.description" class="text-xs text-gray-400 truncate">{{ curzzo.description }}</p>
            </div>
            <!-- Usage counter -->
            <div v-if="!isUnlimited" class="text-right shrink-0">
                <p class="text-[10px] font-semibold" :class="usagePercent >= 90 ? 'text-red-500' : usagePercent >= 70 ? 'text-amber-500' : 'text-gray-400'">
                    {{ usageText }} today
                </p>
                <div class="w-16 h-1 bg-gray-200 rounded-full mt-0.5">
                    <div class="h-full rounded-full transition-all"
                        :class="usagePercent >= 90 ? 'bg-red-500' : usagePercent >= 70 ? 'bg-amber-500' : 'bg-indigo-500'"
                        :style="{ width: usagePercent + '%' }"></div>
                </div>
                <p v-if="topupRemaining > 0 && topupRemaining !== -1" class="text-[9px] text-indigo-400 mt-0.5">+{{ topupRemaining }} bonus</p>
            </div>
        </div>

        <!-- Messages -->
        <div ref="chatContainer" class="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-gray-50">
            <div v-if="loadingHistory" class="text-center py-8">
                <div class="w-6 h-6 border-2 border-indigo-300 border-t-indigo-600 rounded-full animate-spin mx-auto"></div>
            </div>

            <div v-else-if="!messages.length" class="text-center py-12">
                <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-indigo-100 flex items-center justify-center">
                    <span class="text-2xl font-bold text-indigo-600">{{ curzzo.name.charAt(0).toUpperCase() }}</span>
                </div>
                <p class="text-sm font-medium text-gray-900 mb-1">{{ curzzo.name }}</p>
                <p class="text-xs text-gray-400">Start a conversation</p>
            </div>

            <template v-else>
                <div v-for="(msg, i) in messages" :key="i"
                    class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[80%] min-w-0 px-4 py-2.5 rounded-2xl text-sm leading-relaxed"
                        :class="msg.role === 'user'
                            ? 'bg-indigo-600 text-white rounded-br-md'
                            : 'bg-white text-gray-800 border border-gray-200 rounded-bl-md'">
                        <p class="whitespace-pre-wrap break-words">{{ msg.text }}</p>
                    </div>
                </div>
            </template>

            <!-- Typing indicator -->
            <div v-if="loading" class="flex justify-start">
                <div class="bg-white border border-gray-200 rounded-2xl rounded-bl-md px-4 py-3">
                    <div class="flex gap-1">
                        <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                        <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                        <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Limit reached: Top-up prompt -->
        <div v-if="limitReached" class="px-4 py-4 bg-amber-50 border-t border-amber-200 shrink-0">
            <p class="text-sm font-semibold text-amber-800 mb-1">{{ limitReason }}</p>
            <p class="text-xs text-amber-600 mb-3">Get more messages to continue chatting.</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="(pack, i) in topupPacks"
                    :key="i"
                    @click="buyTopup(i)"
                    :disabled="checkingOut"
                    class="px-3 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl transition-colors disabled:opacity-50"
                >
                    {{ pack.label || (pack.messages > 0 ? pack.messages + ' msgs' : 'Day Pass') }}
                    · {{ formatPackPrice(pack) }}
                </button>
            </div>
        </div>

        <!-- Input (hidden when limit reached) -->
        <div v-else class="px-4 py-3 bg-white border-t border-gray-200 shrink-0">
            <form @submit.prevent="send" class="flex items-end gap-2">
                <textarea
                    v-model="newMessage"
                    ref="inputEl"
                    rows="1"
                    maxlength="1000"
                    :placeholder="`Message ${curzzo.name}...`"
                    :disabled="loading"
                    class="flex-1 px-4 py-2.5 border border-gray-300 rounded-3xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent disabled:opacity-50 resize-none leading-5"
                    style="max-height: 120px;"
                    @keydown.enter.exact.prevent="send"
                    @input="autoResize"
                ></textarea>
                <button type="submit" :disabled="!newMessage.trim() || loading"
                    class="w-10 h-10 flex items-center justify-center bg-indigo-600 text-white rounded-full hover:bg-indigo-700 transition-colors disabled:opacity-50 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </button>
            </form>
            <p class="text-[10px] text-gray-300 text-center mt-1.5">Enter to send · Shift+Enter for new line</p>
        </div>
    </div>
</template>
