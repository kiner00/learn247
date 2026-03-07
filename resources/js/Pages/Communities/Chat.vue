<template>
    <AppLayout :title="`${community.name} · Chat`" :community="community">
        <CommunityTabs :community="community" active-tab="chat" />

        <div class="flex gap-6 items-start">

            <!-- ── Chat column ─────────────────────────────────────────────── -->
            <div class="flex-1 min-w-0 flex flex-col" style="height: calc(100vh - 220px);">
                <div class="bg-white border border-gray-200 rounded-2xl flex flex-col overflow-hidden h-full shadow-sm">

                    <!-- Header -->
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center gap-2 shrink-0">
                        <div class="w-2 h-2 rounded-full bg-green-400"></div>
                        <span class="text-sm font-semibold text-gray-900"># general</span>
                    </div>

                    <!-- Messages -->
                    <div ref="messagesEl" class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                        <!-- Date separator for first load -->
                        <div v-if="messages.length" class="flex items-center gap-3 py-1">
                            <div class="flex-1 h-px bg-gray-100"></div>
                            <span class="text-xs text-gray-400">Today</span>
                            <div class="flex-1 h-px bg-gray-100"></div>
                        </div>

                        <!-- Empty state -->
                        <div v-if="!messages.length" class="flex flex-col items-center justify-center h-full text-center py-16">
                            <div class="w-14 h-14 rounded-2xl bg-indigo-50 flex items-center justify-center mx-auto mb-4">
                                <svg class="w-7 h-7 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-700 mb-1">No messages yet</p>
                            <p class="text-xs text-gray-400">Be the first to say something!</p>
                        </div>

                        <!-- Message list -->
                        <div
                            v-for="(msg, i) in messages"
                            :key="msg.id"
                            class="flex gap-3 group"
                            :class="{ 'mt-4': i > 0 && messages[i - 1]?.user?.id !== msg.user?.id }"
                        >
                            <!-- Avatar (only for first message in a group) -->
                            <div class="shrink-0 w-8 mt-0.5">
                                <div
                                    v-if="i === 0 || messages[i - 1]?.user?.id !== msg.user?.id"
                                    class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold"
                                    :class="avatarColor(msg.user?.name)"
                                >
                                    {{ msg.user?.name?.charAt(0)?.toUpperCase() }}
                                </div>
                            </div>

                            <div class="flex-1 min-w-0">
                                <!-- Name + time (only first in group) -->
                                <div
                                    v-if="i === 0 || messages[i - 1]?.user?.id !== msg.user?.id"
                                    class="flex items-baseline gap-2 mb-0.5"
                                >
                                    <span class="text-sm font-semibold text-gray-900">{{ msg.user?.name }}</span>
                                    <span class="text-xs text-gray-400">{{ formatTime(msg.created_at) }}</span>
                                </div>

                                <div class="flex items-start gap-2">
                                    <p class="flex-1 text-sm text-gray-700 leading-relaxed break-words">{{ msg.content }}</p>
                                    <button
                                        v-if="canDelete(msg)"
                                        @click="deleteMessage(msg)"
                                        class="opacity-0 group-hover:opacity-100 shrink-0 text-gray-300 hover:text-red-500 transition-all mt-0.5"
                                        title="Delete message"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Typing / loading indicator -->
                        <div v-if="sending" class="flex gap-3 items-center px-1">
                            <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                                <span class="text-xs text-gray-400">...</span>
                            </div>
                            <span class="text-xs text-gray-400">Sending…</span>
                        </div>
                    </div>

                    <!-- Input bar -->
                    <div class="px-4 py-3 border-t border-gray-100 shrink-0">
                        <form @submit.prevent="send" class="flex items-end gap-2">
                            <div class="flex-1 relative">
                                <textarea
                                    v-model="content"
                                    ref="inputEl"
                                    rows="1"
                                    :placeholder="`Message #general`"
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none bg-gray-50"
                                    style="max-height: 120px; overflow-y: auto;"
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
                        <p class="text-xs text-gray-400 mt-1.5 ml-1">Enter to send · Shift+Enter for new line</p>
                    </div>
                </div>
            </div>

            <!-- ── Right sidebar ────────────────────────────────────────────── -->
            <div class="w-72 shrink-0">
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                    <div class="h-32 bg-gray-900 flex items-center justify-center overflow-hidden">
                        <img
                            v-if="community.cover_image"
                            :src="community.cover_image"
                            :alt="community.name"
                            class="w-full h-full object-cover"
                        />
                        <span v-else class="text-3xl font-black text-white opacity-20">
                            {{ community.name.charAt(0).toUpperCase() }}
                        </span>
                    </div>

                    <div class="p-4">
                        <h2 class="font-bold text-gray-900 text-sm">{{ community.name }}</h2>
                        <p class="text-xs text-gray-400 mt-0.5 mb-3">curzzo.com/communities/{{ community.slug }}</p>

                        <div class="flex justify-around text-center border-t border-gray-100 pt-3 mb-4">
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ community.members_count ?? 0 }}</p>
                                <p class="text-xs text-gray-400">Members</p>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-900">0</p>
                                <p class="text-xs text-gray-400">Online</p>
                            </div>
                        </div>

                        <button
                            v-if="$page.props.auth?.user"
                            @click="showInviteModal = true"
                            class="w-full py-2 text-sm font-semibold border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Invite People
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <InviteModal
            :show="showInviteModal"
            :community-name="community.name"
            :invite-url="inviteUrl"
            @close="showInviteModal = false"
        />
    </AppLayout>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import InviteModal from '@/Components/InviteModal.vue';

const props = defineProps({
    community: Object,
    messages:  Array,
    affiliate: Object,
});

const page    = usePage();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

// ── State ──────────────────────────────────────────────────────────────────────
const messages      = ref([...props.messages]);
const content       = ref('');
const sending       = ref(false);
const messagesEl    = ref(null);
const inputEl       = ref(null);
const showInviteModal = ref(false);

const lastMessageId = computed(() => messages.value.at(-1)?.id ?? 0);

const inviteUrl = computed(() =>
    props.affiliate?.code
        ? `${window.location.origin}/ref/${props.affiliate.code}`
        : `${window.location.origin}/communities/${props.community.slug}`
);

// ── Helpers ────────────────────────────────────────────────────────────────────
const avatarColors = [
    'bg-indigo-100 text-indigo-600',
    'bg-violet-100 text-violet-600',
    'bg-pink-100 text-pink-600',
    'bg-emerald-100 text-emerald-600',
    'bg-amber-100 text-amber-600',
    'bg-sky-100 text-sky-600',
];

function avatarColor(name) {
    if (!name) return avatarColors[0];
    return avatarColors[name.charCodeAt(0) % avatarColors.length];
}

function formatTime(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
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

// ── Delete message ─────────────────────────────────────────────────────────────
function canDelete(msg) {
    const authUser = page.props.auth?.user;
    if (!authUser) return false;
    return msg.user?.id === authUser.id || authUser.is_super_admin;
}

async function deleteMessage(msg) {
    if (!confirm('Delete this message?')) return;
    try {
        await axios.delete(`/communities/${props.community.slug}/chat/${msg.id}`, {
            headers: { 'X-CSRF-TOKEN': csrfToken },
        });
        messages.value = messages.value.filter(m => m.id !== msg.id);
    } catch { /* ignore */ }
}

// ── Send message ───────────────────────────────────────────────────────────────
async function send() {
    const text = content.value.trim();
    if (!text || sending.value) return;

    sending.value = true;
    content.value = '';
    if (inputEl.value) {
        inputEl.value.style.height = 'auto';
    }

    try {
        const res = await axios.post(`/communities/${props.community.slug}/chat`, { content: text }, {
            headers: { 'X-CSRF-TOKEN': csrfToken },
        });
        messages.value.push(res.data.message);
        scrollToBottom(true);
    } catch {
        content.value = text; // restore on error
    } finally {
        sending.value = false;
    }
}

// ── Polling ────────────────────────────────────────────────────────────────────
let pollTimer = null;

async function poll() {
    try {
        const res = await axios.get(`/communities/${props.community.slug}/chat/poll`, {
            params: { after: lastMessageId.value },
        });
        if (res.data.messages.length) {
            const atBottom = messagesEl.value
                ? messagesEl.value.scrollHeight - messagesEl.value.scrollTop - messagesEl.value.clientHeight < 100
                : true;
            messages.value.push(...res.data.messages);
            if (atBottom) scrollToBottom(true);
        }
    } catch { /* ignore poll errors */ }
}

// ── Lifecycle ──────────────────────────────────────────────────────────────────
onMounted(() => {
    scrollToBottom();
    pollTimer = setInterval(poll, 3000);
});

onBeforeUnmount(() => clearInterval(pollTimer));
</script>
