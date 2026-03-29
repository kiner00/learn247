<template>
    <AppLayout :title="`Chat with ${partner.name}`">
        <div class="max-w-2xl mx-auto flex flex-col" style="height: calc(100vh - 120px);">

            <!-- Header -->
            <div class="flex items-center gap-3 mb-4">
                <Link href="/messages" class="text-gray-400 hover:text-gray-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </Link>
                <div
                    class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
                    :class="avatarColor(partner.name)"
                >
                    {{ partner.name?.charAt(0)?.toUpperCase() }}
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-900 leading-tight">{{ partner.name }}</p>
                    <p class="text-xs text-gray-400">@{{ partner.username ?? `user${partner.id}` }}</p>
                </div>
            </div>

            <!-- Chat box -->
            <div class="bg-white border border-gray-200 rounded-2xl flex flex-col overflow-hidden flex-1 shadow-sm">

                <!-- Messages -->
                <div ref="messagesEl" class="flex-1 overflow-y-auto px-4 py-4 space-y-2">
                    <div v-if="!messages.length" class="flex flex-col items-center justify-center h-full text-center py-10">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500">Say hello to <strong>{{ partner.name }}</strong>!</p>
                    </div>

                    <div
                        v-for="(msg, i) in messages"
                        :key="msg.id"
                        class="flex"
                        :class="msg.is_mine ? 'justify-end' : 'justify-start'"
                    >
                        <!-- Partner avatar (show only for first in group) -->
                        <div v-if="!msg.is_mine" class="flex items-end gap-2">
                            <div
                                v-if="i === messages.length - 1 || messages[i + 1]?.is_mine !== msg.is_mine"
                                class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 mb-0.5"
                                :class="avatarColor(partner.name)"
                            >
                                {{ partner.name?.charAt(0)?.toUpperCase() }}
                            </div>
                            <div v-else class="w-7 shrink-0"></div>

                            <div class="max-w-xs">
                                <div class="px-3.5 py-2 bg-gray-100 rounded-2xl rounded-bl-sm text-sm text-gray-800 leading-relaxed">
                                    {{ msg.content }}
                                </div>
                                <p class="text-[10px] text-gray-400 mt-0.5 ml-1">{{ formatTime(msg.created_at) }}</p>
                            </div>
                        </div>

                        <!-- My message -->
                        <div v-else class="flex items-end gap-1 group">
                            <button
                                @click="deleteMessage(msg)"
                                class="opacity-0 group-hover:opacity-100 text-gray-300 hover:text-red-500 transition-all mb-4"
                                title="Delete message"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                            <div class="max-w-xs">
                                <div class="px-3.5 py-2 bg-indigo-600 rounded-2xl rounded-br-sm text-sm text-white leading-relaxed">
                                    {{ msg.content }}
                                </div>
                                <p class="text-[10px] text-gray-400 mt-0.5 text-right mr-1">{{ formatTime(msg.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input -->
                <div class="px-4 py-3 border-t border-gray-100 shrink-0">
                    <form @submit.prevent="send" class="flex items-end gap-2">
                        <div class="flex-1">
                            <textarea
                                v-model="content"
                                ref="inputEl"
                                rows="1"
                                :placeholder="`Message ${partner.name}…`"
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none bg-gray-50"
                                style="max-height: 120px;"
                                @keydown.enter.exact.prevent="send"
                                @keydown.enter.shift.exact="content += '\n'"
                                @input="autoResize"
                            ></textarea>
                        </div>
                        <button
                            type="submit"
                            :disabled="!content.trim() || sending"
                            class="shrink-0 w-9 h-9 flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white rounded-xl transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    partner:  Object,
    messages: Array,
});

const messages   = ref([...props.messages]);
const content    = ref('');
const sending    = ref(false);
const messagesEl = ref(null);
const inputEl    = ref(null);

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

const avatarColors = [
    'bg-indigo-100 text-indigo-600', 'bg-violet-100 text-violet-600',
    'bg-pink-100 text-pink-600', 'bg-emerald-100 text-emerald-600',
    'bg-amber-100 text-amber-600', 'bg-sky-100 text-sky-600',
];

function avatarColor(name) {
    if (!name) return avatarColors[0];
    return avatarColors[name.charCodeAt(0) % avatarColors.length];
}

function formatTime(str) {
    if (!str) return '';
    return new Date(str).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
}

function autoResize(e) {
    e.target.style.height = 'auto';
    e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
}

function scrollToBottom(smooth = false) {
    nextTick(() => {
        if (messagesEl.value) {
            messagesEl.value.scrollTo({ top: messagesEl.value.scrollHeight, behavior: smooth ? 'smooth' : 'instant' });
        }
    });
}

async function deleteMessage(msg) {
    if (!confirm('Delete this message?')) return;
    try {
        await axios.delete(`/direct-messages/${msg.id}`, {
            headers: { 'X-CSRF-TOKEN': csrfToken },
        });
        messages.value = messages.value.filter(m => m.id !== msg.id);
    } catch { /* ignore */ }
}

async function send() {
    const text = content.value.trim();
    if (!text || sending.value) return;

    sending.value = true;
    content.value = '';
    if (inputEl.value) inputEl.value.style.height = 'auto';

    try {
        const res = await axios.post(`/messages/${props.partner.username ?? props.partner.id}`, { content: text }, {
            headers: { 'X-CSRF-TOKEN': csrfToken },
        });
        messages.value.push(res.data.message);
        scrollToBottom(true);
    } catch {
        content.value = text;
    } finally {
        sending.value = false;
    }
}

// ── Real-time (Echo / Reverb) ─────────────────────────────────────────────────
const authUser = usePage().props.auth?.user;
let echoChannel = null;

function onIncomingDm(e) {
    // Only show messages from the current conversation partner
    if (e.sender_id !== props.partner.id) return;
    if (messages.value.some(m => m.id === e.message.id)) return;

    const atBottom = messagesEl.value
        ? messagesEl.value.scrollHeight - messagesEl.value.scrollTop - messagesEl.value.clientHeight < 100
        : true;
    messages.value.push(e.message);
    if (atBottom) scrollToBottom(true);
}

onMounted(() => {
    scrollToBottom();

    if (authUser && window.Echo) {
        echoChannel = window.Echo.private(`dm.${authUser.id}`)
            .listen('DirectMessageSent', onIncomingDm);
    }
});

onBeforeUnmount(() => {
    if (echoChannel) {
        echoChannel.stopListening('DirectMessageSent');
        window.Echo.leave(`dm.${authUser.id}`);
    }
});
</script>
