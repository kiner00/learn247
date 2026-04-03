<template>
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 translate-x-4"
        enter-to-class="opacity-100 translate-x-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100 translate-x-0"
        leave-to-class="opacity-0 translate-x-4"
    >
        <div
            v-if="modelValue"
            class="fixed top-14 right-0 bottom-0 w-80 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 shadow-xl z-40 flex flex-col"
        >
            <!-- Header -->
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-full shrink-0 shadow-sm overflow-hidden bg-gray-100">
                        <img :src="curzzoIcon" alt="Curzzo" class="w-full h-full object-cover" />
                    </div>
                    <div>
                        <p class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-tight">AI Assistant</p>
                        <p class="text-[10px] text-indigo-400 leading-tight">AI Learning Assistant</p>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        @click="newChat"
                        class="text-xs text-gray-400 hover:text-indigo-600 px-2 py-1 rounded-lg hover:bg-indigo-50 transition-colors"
                        title="New conversation"
                    >New chat</button>
                    <button
                        @click="$emit('update:modelValue', false)"
                        class="w-6 h-6 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Messages -->
            <div ref="scrollRef" class="flex-1 overflow-y-auto p-4 space-y-3">
                <div v-if="!messages.length && !loading" class="flex flex-col items-center justify-center h-full text-center text-gray-400 gap-2">
                    <div class="w-14 h-14 rounded-full shadow-md overflow-hidden bg-gray-100">
                        <img :src="curzzoIcon" alt="Curzzo" class="w-full h-full object-cover" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Hi, I'm your AI Assistant!</p>
                        <p class="text-xs mt-0.5 text-gray-400">Ask me about your lessons, quizzes &amp; progress.</p>
                    </div>
                </div>

                <template v-for="(msg, i) in messages" :key="i">
                    <!-- User message -->
                    <div v-if="msg.role === 'user'" class="flex justify-end items-end gap-2">
                        <div class="max-w-[75%] px-3 py-2 bg-indigo-600 text-white text-sm rounded-2xl rounded-tr-sm">
                            {{ msg.content }}
                        </div>
                        <div class="w-6 h-6 rounded-full shrink-0 mb-0.5 overflow-hidden bg-indigo-200 flex items-center justify-center text-[10px] font-bold text-indigo-700">
                            <img v-if="userAvatar" :src="userAvatar" class="w-full h-full object-cover" />
                            <span v-else>{{ initials }}</span>
                        </div>
                    </div>
                    <!-- Curzzo message -->
                    <div v-else class="flex justify-start items-end gap-2">
                        <div class="w-6 h-6 rounded-full shrink-0 mb-0.5 overflow-hidden bg-gray-100">
                            <img :src="curzzoIcon" alt="Curzzo" class="w-full h-full object-cover" />
                        </div>
                        <div v-if="msg.type === 'image'" class="max-w-[85%]">
                            <img :src="msg.content" alt="Generated image" class="rounded-2xl rounded-tl-sm w-full" />
                            <a :href="msg.content" download="curzzo-image.png" class="block mt-1 text-[11px] text-indigo-500 hover:underline text-center">Download</a>
                        </div>
                        <div v-else class="max-w-[75%] px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm rounded-2xl rounded-tl-sm whitespace-pre-wrap">
                            {{ msg.content }}
                        </div>
                    </div>
                </template>

                <!-- Loading dots -->
                <div v-if="loading" class="flex justify-start items-end gap-2">
                    <div class="w-6 h-6 rounded-full shrink-0 mb-0.5 overflow-hidden bg-gray-100">
                        <img :src="curzzoIcon" alt="Curzzo" class="w-full h-full object-cover" />
                    </div>
                    <div class="px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-2xl rounded-tl-sm">
                        <template v-if="generatingImage">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Generating image, this may take up to a minute...</span>
                        </template>
                        <template v-else>
                            <span class="flex gap-1">
                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:150ms"></span>
                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:300ms"></span>
                            </span>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Input -->
            <div class="p-3 border-t border-gray-100 dark:border-gray-700 shrink-0">
                <div class="flex gap-2 items-end">
                    <textarea
                        v-model="input"
                        @keydown.enter.exact.prevent="sendMessage"
                        placeholder="Ask about your progress..."
                        rows="1"
                        class="flex-1 px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                        style="max-height:100px; overflow-y:auto;"
                        :disabled="loading"
                    ></textarea>
                    <button
                        @click="sendMessage"
                        :disabled="loading || !input.trim()"
                        class="shrink-0 w-8 h-8 flex items-center justify-center bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 disabled:opacity-40 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </button>
                </div>
                <p class="text-[10px] text-gray-300 mt-1.5 text-center">Enter to send · Shift+Enter for newline</p>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    initials:   { type: String, default: '' },
    userAvatar: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const curzzoIcon = '/brand/ICON/CURZZO LOGO WHIT BG ROUND.png';
const page = usePage();

const messages       = ref([]);
const input          = ref('');
const loading        = ref(false);
const generatingImage = ref(false);
const conversationId = ref(null);
const scrollRef      = ref(null);

function newChat() {
    messages.value       = [];
    conversationId.value = null;
}

async function fetchGreeting() {
    emit('update:modelValue', true);
    loading.value = true;
    await nextTick();
    if (scrollRef.value) scrollRef.value.scrollTop = scrollRef.value.scrollHeight;

    try {
        const axios = (await import('axios')).default;
        const res   = await axios.post('/ai/greet');
        conversationId.value = res.data.conversation_id;
        messages.value.push({ role: 'assistant', content: res.data.message });
    } catch (e) {
        messages.value.push({ role: 'assistant', content: 'Hey! How can I help you today?' });
    } finally {
        loading.value = false;
        await nextTick();
        if (scrollRef.value) scrollRef.value.scrollTop = scrollRef.value.scrollHeight;
    }
}

watch(() => page.props.flash?.show_ai_greeting, (val) => {
    if (val && (page.props.auth?.communities ?? []).length > 0) {
        fetchGreeting();
    }
}, { immediate: true });

function isImageRequestMsg(text) {
    const lower = text.toLowerCase();
    return /\b(generate|create|make|draw|design|produce)\b.{0,30}\b(image|photo|picture|banner|thumbnail|cover|poster|visual|graphic|illustration)\b/.test(lower)
        || /\b(image|photo|picture|banner|thumbnail|cover|poster|visual|graphic|illustration)\b.{0,30}\b(generate|create|make|draw|design)\b/.test(lower);
}

async function sendMessage() {
    const text = input.value.trim();
    if (!text || loading.value) return;

    messages.value.push({ role: 'user', content: text });
    input.value  = '';
    loading.value = true;
    generatingImage.value = isImageRequestMsg(text);

    await nextTick();
    if (scrollRef.value) scrollRef.value.scrollTop = scrollRef.value.scrollHeight;

    try {
        const axios = (await import('axios')).default;
        const res   = await axios.post('/ai/chat', {
            message:         text,
            conversation_id: conversationId.value,
        }, { timeout: 120000 });

        if (res.data.conversation_id) conversationId.value = res.data.conversation_id;
        messages.value.push({ role: 'assistant', type: res.data.type ?? 'text', content: res.data.message });
    } catch (e) {
        const msg = e?.response?.data?.message
            ?? e?.response?.data?.error
            ?? 'Something went wrong. Please try again.';
        messages.value.push({ role: 'assistant', type: 'text', content: msg });
    } finally {
        loading.value = false;
        generatingImage.value = false;
        await nextTick();
        if (scrollRef.value) scrollRef.value.scrollTop = scrollRef.value.scrollHeight;
    }
}
</script>
