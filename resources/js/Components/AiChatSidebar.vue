<template>
    <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm flex flex-col" style="height: 480px;">
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2.5 shrink-0">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714a2.25 2.25 0 00.659 1.591L19 14.5M14.25 3.104c.251.023.501.05.75.082M19 14.5l-2.47 2.47a2.25 2.25 0 01-1.591.659H9.061a2.25 2.25 0 01-1.591-.659L5 14.5m14 0V17a2.25 2.25 0 01-2.25 2.25H7.25A2.25 2.25 0 015 17v-2.5" />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 truncate">Chat with AI</p>
                <p class="text-xs text-gray-400 truncate">Ask about {{ communityName }}</p>
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
            <!-- Welcome message -->
            <div v-if="!messages.length && !loading" class="flex flex-col items-center justify-center h-full text-center px-2">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-700 mb-1">AI Assistant</p>
                <p class="text-xs text-gray-400 leading-relaxed">Ask me anything about this community, its courses, lessons, or posts.</p>
            </div>

            <!-- Chat messages -->
            <div v-for="(msg, i) in messages" :key="i" class="flex gap-2.5" :class="msg.role === 'user' ? 'justify-end' : ''">
                <!-- AI avatar -->
                <div v-if="msg.role === 'ai'" class="shrink-0 w-7 h-7 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center mt-0.5">
                    <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                    </svg>
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

            <!-- Loading indicator -->
            <div v-if="loading" class="flex gap-2.5">
                <div class="shrink-0 w-7 h-7 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center mt-0.5">
                    <svg class="w-3.5 h-3.5 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                </div>
                <div class="bg-gray-100 px-3 py-2 rounded-2xl rounded-bl-md">
                    <div class="flex gap-1">
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
                    placeholder="Ask something..."
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
import { ref, nextTick } from 'vue';
import axios from 'axios';

const props = defineProps({
    communitySlug: { type: String, required: true },
    communityName: { type: String, required: true },
});

const messages       = ref([]);
const input          = ref('');
const loading        = ref(false);
const conversationId = ref(null);
const chatEl         = ref(null);
const inputEl        = ref(null);

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

function autoResize(e) {
    e.target.style.height = 'auto';
    e.target.style.height = Math.min(e.target.scrollHeight, 80) + 'px';
}

function scrollToBottom() {
    nextTick(() => {
        if (chatEl.value) {
            chatEl.value.scrollTo({ top: chatEl.value.scrollHeight, behavior: 'smooth' });
        }
    });
}

function clearChat() {
    messages.value = [];
    conversationId.value = null;
}

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
        messages.value.push({ role: 'ai', text: res.data.message });
    } catch (err) {
        messages.value.push({
            role: 'ai',
            text: err.response?.status === 429
                ? 'Too many messages. Please wait a moment and try again.'
                : 'Sorry, something went wrong. Please try again.',
        });
    } finally {
        loading.value = false;
        scrollToBottom();
    }
}
</script>
