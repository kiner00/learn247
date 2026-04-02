<template>
    <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm flex flex-col h-full">
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2.5 shrink-0">
            <img
                v-if="creatorAvatar"
                :src="creatorAvatar"
                :alt="creatorName"
                class="w-9 h-9 rounded-full object-cover"
            />
            <div
                v-else
                class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-600"
            >
                {{ creatorName?.charAt(0)?.toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 truncate">{{ creatorName }}</p>
                <div class="flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                    <p class="text-xs text-gray-400">Online</p>
                </div>
            </div>
            <button
                v-if="messages.length"
                @click="clearChat"
                class="text-gray-300 hover:text-gray-500 transition-colors"
                title="New conversation"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
                </svg>
            </button>
        </div>

        <!-- Messages -->
        <div ref="chatEl" class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
            <!-- Welcome state -->
            <div v-if="!messages.length && !loading && !initialLoading" class="flex flex-col items-center justify-center h-full text-center px-2">
                <img
                    v-if="creatorAvatar"
                    :src="creatorAvatar"
                    :alt="creatorName"
                    class="w-14 h-14 rounded-full object-cover mb-3"
                />
                <div
                    v-else
                    class="w-14 h-14 rounded-full bg-indigo-100 flex items-center justify-center text-lg font-bold text-indigo-600 mb-3"
                >
                    {{ creatorName?.charAt(0)?.toUpperCase() }}
                </div>
                <p class="text-sm font-medium text-gray-700 mb-1">Chat with {{ creatorName }}</p>
                <p class="text-xs text-gray-400 leading-relaxed">Ask me anything about {{ communityName }}!</p>
            </div>

            <!-- Chat messages -->
            <div v-for="(msg, i) in messages" :key="msg.id ?? `local-${i}`" class="flex gap-2.5" :class="msg.role === 'user' ? 'justify-end' : ''">
                <!-- Creator avatar -->
                <div v-if="msg.role === 'creator'" class="shrink-0 mt-0.5">
                    <img
                        v-if="creatorAvatar"
                        :src="creatorAvatar"
                        :alt="creatorName"
                        class="w-7 h-7 rounded-full object-cover"
                    />
                    <div
                        v-else
                        class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600"
                    >
                        {{ creatorName?.charAt(0)?.toUpperCase() }}
                    </div>
                </div>

                <div
                    class="max-w-[85%] px-3 py-2 rounded-2xl text-sm leading-relaxed"
                    :class="msg.role === 'user'
                        ? 'bg-indigo-600 text-white rounded-br-md'
                        : 'bg-gray-100 text-gray-700 rounded-bl-md'"
                >
                    <p class="whitespace-pre-wrap break-words">{{ msg.text }}</p>
                </div>
            </div>

            <!-- Typing indicator -->
            <div v-if="loading" class="flex gap-2.5">
                <div class="shrink-0 mt-0.5">
                    <img
                        v-if="creatorAvatar"
                        :src="creatorAvatar"
                        :alt="creatorName"
                        class="w-7 h-7 rounded-full object-cover"
                    />
                    <div
                        v-else
                        class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600"
                    >
                        {{ creatorName?.charAt(0)?.toUpperCase() }}
                    </div>
                </div>
                <div class="bg-gray-100 px-3 py-2 rounded-2xl rounded-bl-md">
                    <div class="flex gap-1 items-center">
                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Input -->
        <div class="px-3 py-3 border-t border-gray-100 shrink-0">
            <form @submit.prevent="sendMessage" class="flex items-end gap-2">
                <textarea
                    v-model="input"
                    ref="inputEl"
                    rows="1"
                    :placeholder="`Message ${creatorName}...`"
                    class="flex-1 px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none bg-gray-50"
                    style="max-height: 80px; overflow-y: auto;"
                    @keydown.enter.exact.prevent="sendMessage"
                    @input="autoResize"
                ></textarea>
                <button
                    type="submit"
                    :disabled="!input.trim() || loading"
                    class="shrink-0 w-8 h-8 flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white rounded-xl transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, nextTick, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';

const props = defineProps({
    communitySlug: { type: String, required: true },
    communityName: { type: String, required: true },
    creatorName:   { type: String, required: true },
    creatorAvatar: { type: String, default: null },
});

const messages       = ref([]);
const input          = ref('');
const loading        = ref(false);
const initialLoading = ref(true);
const conversationId = ref(null);
const chatEl         = ref(null);
const inputEl        = ref(null);
let   pollTimer      = null;
let   lastMessageId  = 0;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

function autoResize(e) {
    e.target.style.height = 'auto';
    e.target.style.height = Math.min(e.target.scrollHeight, 80) + 'px';
}

function scrollToBottom(smooth = true) {
    nextTick(() => {
        if (chatEl.value) {
            chatEl.value.scrollTo({ top: chatEl.value.scrollHeight, behavior: smooth ? 'smooth' : 'instant' });
        }
    });
}

function clearChat() {
    messages.value = [];
    conversationId.value = null;
    lastMessageId = 0;
}

// ── Load previous messages ───────────────────────────────────────────────────
async function loadHistory() {
    try {
        const { data } = await axios.get(`/communities/${props.communitySlug}/chatbot/history`);
        if (data.messages?.length) {
            messages.value = data.messages;
            lastMessageId = Math.max(...data.messages.map(m => m.id || 0));
            scrollToBottom(false);
        }
    } catch { /* ignore */ }
    initialLoading.value = false;
}

// ── Poll for new creator replies ─────────────────────────────────────────────
async function pollNewMessages() {
    if (!lastMessageId) return;
    try {
        const { data } = await axios.get(`/communities/${props.communitySlug}/chatbot/poll`, {
            params: { after: lastMessageId },
        });
        if (data.messages?.length) {
            for (const msg of data.messages) {
                if (!messages.value.some(m => m.id === msg.id)) {
                    messages.value.push(msg);
                    if (msg.id > lastMessageId) lastMessageId = msg.id;
                }
            }
            scrollToBottom();
        }
    } catch { /* ignore */ }
}

// ── Send message ─────────────────────────────────────────────────────────────
async function sendMessage() {
    const text = input.value.trim();
    if (!text || loading.value) return;

    messages.value.push({ role: 'user', text });
    input.value = '';
    if (inputEl.value) inputEl.value.style.height = 'auto';
    loading.value = true;
    scrollToBottom();

    try {
        const res = await axios.post(`/communities/${props.communitySlug}/chatbot`, {
            message: text,
            conversation_id: conversationId.value,
        }, {
            headers: { 'X-CSRF-TOKEN': csrfToken },
        });

        conversationId.value = res.data.conversation_id;
        messages.value.push({ role: 'creator', text: res.data.message });

        // Reload to get proper IDs for polling
        const { data } = await axios.get(`/communities/${props.communitySlug}/chatbot/history`);
        if (data.messages?.length) {
            messages.value = data.messages;
            lastMessageId = Math.max(...data.messages.map(m => m.id || 0));
        }
    } catch (err) {
        messages.value.push({
            role: 'creator',
            text: err.response?.status === 429
                ? 'Hey, slow down a bit! Send me another message in a moment.'
                : 'Sorry, something went wrong. Try again in a bit!',
        });
    } finally {
        loading.value = false;
        scrollToBottom();
    }
}

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
    loadHistory();
    pollTimer = setInterval(pollNewMessages, 5000);
});

onBeforeUnmount(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>
