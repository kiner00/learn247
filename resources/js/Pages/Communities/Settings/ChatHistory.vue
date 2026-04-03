<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';

const props = defineProps({
    community:    Object,
    chatUsers:    { type: Array, default: () => [] },
    selectedUser: { type: Object, default: null },
    chatMessages: { type: Array, default: () => [] },
});

const base = computed(() => `/communities/${props.community.slug}/settings/chat-history`);

// ── Reply state ──────────────────────────────────────────────────────────────
const replyInput  = ref('');
const sending     = ref(false);
const localMessages = ref([...props.chatMessages]);
const chatViewEl  = ref(null);

watch(() => props.chatMessages, (val) => {
    localMessages.value = [...val];
    scrollChatToBottom();
});

watch(() => props.selectedUser, () => {
    replyInput.value = '';
});

function scrollChatToBottom() {
    nextTick(() => {
        if (chatViewEl.value) {
            chatViewEl.value.scrollTo({ top: chatViewEl.value.scrollHeight, behavior: 'smooth' });
        }
    });
}

async function sendReply() {
    const text = replyInput.value.trim();
    if (!text || sending.value || !props.selectedUser) return;

    sending.value = true;
    replyInput.value = '';

    try {
        const res = await axios.post(`/communities/${props.community.slug}/chatbot/reply`, {
            user_id: props.selectedUser.id,
            message: text,
        });

        localMessages.value.push(res.data.message);
        scrollChatToBottom();
    } catch {
        replyInput.value = text;
    } finally {
        sending.value = false;
    }
}

// ── Polling for new member messages ──────────────────────────────────────────
let pollTimer = null;

function startPolling() {
    stopPolling();
    if (!props.selectedUser) return;

    pollTimer = setInterval(async () => {
        if (!props.selectedUser) return;
        const lastId = localMessages.value.length
            ? Math.max(...localMessages.value.map(m => m.id || 0))
            : 0;

        try {
            const { data } = await axios.get(`/communities/${props.community.slug}/chatbot/poll`, {
                params: { after: lastId, user_id: props.selectedUser.id },
            });

            if (data.messages?.length) {
                for (const msg of data.messages) {
                    if (!localMessages.value.some(m => m.id === msg.id)) {
                        localMessages.value.push(msg);
                    }
                }
                scrollChatToBottom();
            }
        } catch { /* ignore */ }
    }, 5000);
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

watch(() => props.selectedUser, (u) => {
    if (u) startPolling();
    else stopPolling();
}, { immediate: true });

onBeforeUnmount(() => stopPolling());

// ── Formatting ───────────────────────────────────────────────────────────────
function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const now = new Date();
    const diffDays = Math.floor((now - d) / 86400000);
    if (diffDays === 0) return d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return d.toLocaleDateString('en-PH', { weekday: 'short' });
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}

function formatFullTime(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">Chat History</h2>
                <p class="text-sm text-gray-500 mt-0.5">View and reply to member conversations.</p>
            </div>

            <div class="flex" style="height: 520px;">
                <!-- User list -->
                <div class="w-64 shrink-0 border-r border-gray-100 overflow-y-auto">
                    <div v-if="!chatUsers.length" class="p-6 text-center">
                        <p class="text-sm text-gray-400">No conversations yet.</p>
                    </div>

                    <Link
                        v-for="u in chatUsers"
                        :key="u.id"
                        :href="`${base}/${u.id}`"
                        class="flex items-center gap-3 px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition-colors"
                        :class="selectedUser?.id === u.id ? 'bg-indigo-50' : ''"
                        preserve-scroll
                    >
                        <img
                            v-if="u.avatar"
                            :src="u.avatar"
                            :alt="u.name"
                            class="w-9 h-9 rounded-full object-cover shrink-0"
                        />
                        <div
                            v-else
                            class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-600 shrink-0"
                        >
                            {{ u.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ u.name }}</p>
                            <p class="text-xs text-gray-400">{{ Math.floor(u.message_count / 2) }} messages</p>
                        </div>
                        <span class="text-xs text-gray-400 shrink-0">{{ formatTime(u.last_chat_at) }}</span>
                    </Link>
                </div>

                <!-- Chat view -->
                <div class="flex-1 flex flex-col min-w-0">
                    <!-- No user selected -->
                    <div v-if="!selectedUser" class="flex-1 flex items-center justify-center text-center px-6">
                        <div>
                            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                            <p class="text-sm text-gray-400">Select a member to view their conversation</p>
                        </div>
                    </div>

                    <!-- Selected user chat -->
                    <template v-else>
                        <!-- Chat header -->
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2.5 shrink-0">
                            <img
                                v-if="selectedUser.avatar"
                                :src="selectedUser.avatar"
                                :alt="selectedUser.name"
                                class="w-8 h-8 rounded-full object-cover"
                            />
                            <div
                                v-else
                                class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-600"
                            >
                                {{ selectedUser.name?.charAt(0)?.toUpperCase() }}
                            </div>
                            <p class="text-sm font-semibold text-gray-900">{{ selectedUser.name }}</p>
                        </div>

                        <!-- Messages -->
                        <div ref="chatViewEl" class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                            <div v-for="msg in localMessages" :key="msg.id" class="flex gap-2.5" :class="msg.role === 'user' ? 'justify-end' : ''">
                                <!-- Creator avatar (your replies) -->
                                <div v-if="msg.role === 'creator'" class="shrink-0 mt-0.5">
                                    <img
                                        v-if="community.owner?.avatar"
                                        :src="community.owner.avatar"
                                        :alt="community.owner.name"
                                        class="w-7 h-7 rounded-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600"
                                    >
                                        {{ community.owner?.name?.charAt(0)?.toUpperCase() }}
                                    </div>
                                </div>

                                <div class="max-w-[75%]">
                                    <div
                                        class="px-3 py-2 rounded-2xl text-sm leading-relaxed"
                                        :class="msg.role === 'user'
                                            ? 'bg-indigo-600 text-white rounded-br-md'
                                            : 'bg-gray-100 text-gray-700 rounded-bl-md'"
                                    >
                                        <p class="whitespace-pre-wrap break-words">{{ msg.content }}</p>
                                    </div>
                                    <p class="text-xs text-gray-300 mt-0.5" :class="msg.role === 'user' ? 'text-right' : ''">
                                        {{ formatFullTime(msg.created_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Reply input -->
                        <div class="px-3 py-3 border-t border-gray-100 shrink-0">
                            <form @submit.prevent="sendReply" class="flex items-end gap-2">
                                <textarea
                                    v-model="replyInput"
                                    rows="1"
                                    :placeholder="`Reply to ${selectedUser.name}...`"
                                    class="flex-1 px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none bg-gray-50"
                                    style="max-height: 80px; overflow-y: auto;"
                                    @keydown.enter.exact.prevent="sendReply"
                                ></textarea>
                                <button
                                    type="submit"
                                    :disabled="!replyInput.trim() || sending"
                                    class="shrink-0 w-8 h-8 flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white rounded-xl transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </CommunitySettingsLayout>
</template>
