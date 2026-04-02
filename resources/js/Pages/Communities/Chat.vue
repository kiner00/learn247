<template>
    <AppLayout :title="`${community.name} · Chat`" :community="community">
        <CommunityTabs :community="community" active-tab="chat" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ── Chat column ─────────────────────────────────────────────── -->
            <div class="lg:col-span-2 flex flex-col" style="height: calc(100vh - 220px);">
                <div class="bg-white border border-gray-200 rounded-2xl flex flex-col overflow-hidden h-full shadow-sm">

                    <!-- Header -->
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center gap-2 shrink-0">
                        <div class="w-2 h-2 rounded-full bg-green-400"></div>
                        <span class="text-sm font-semibold text-gray-900"># general</span>
                        <div v-if="telegramConnected" class="ml-auto flex items-center gap-1.5 px-2.5 py-1 bg-sky-50 border border-sky-200 rounded-full">
                            <svg class="w-3 h-3 text-sky-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z"/></svg>
                            <span class="text-xs font-medium text-sky-600">Telegram connected</span>
                        </div>
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
                            :class="{ 'mt-4': i > 0 && msgGroupKey(messages[i - 1]) !== msgGroupKey(msg) }"
                        >
                            <!-- Avatar (only for first message in a group) -->
                            <div class="shrink-0 w-8 mt-0.5">
                                <template v-if="i === 0 || msgGroupKey(messages[i - 1]) !== msgGroupKey(msg)">
                                    <!-- Telegram avatar -->
                                    <div
                                        v-if="msg.telegram_author"
                                        class="w-8 h-8 rounded-full bg-sky-100 flex items-center justify-center"
                                    >
                                        <svg class="w-4 h-4 text-sky-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z"/></svg>
                                    </div>
                                    <!-- Normal avatar -->
                                    <div
                                        v-else
                                        class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold"
                                        :class="avatarColor(msg.user?.name)"
                                    >
                                        {{ msg.user?.name?.charAt(0)?.toUpperCase() }}
                                    </div>
                                </template>
                            </div>

                            <div class="flex-1 min-w-0">
                                <!-- Name + time (only first in group) -->
                                <div
                                    v-if="i === 0 || msgGroupKey(messages[i - 1]) !== msgGroupKey(msg)"
                                    class="flex items-baseline gap-2 mb-0.5"
                                >
                                    <span v-if="msg.telegram_author" class="flex items-center gap-1 text-sm font-semibold text-sky-600">
                                        {{ msg.telegram_author }}
                                        <svg class="w-3 h-3 opacity-70" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z"/></svg>
                                    </span>
                                    <span v-else class="text-sm font-semibold text-gray-900">{{ msg.user?.name }}</span>
                                    <span class="text-xs text-gray-400">{{ formatTime(msg.created_at) }}</span>
                                </div>

                                <div class="flex items-start gap-2">
                                    <div class="flex-1 min-w-0">
                                        <img
                                            v-if="msg.media_type === 'image' && msg.media_url"
                                            :src="msg.media_url"
                                            class="max-w-xs rounded-lg mb-1 cursor-pointer"
                                            @click="() => window.open(msg.media_url, '_blank')"
                                        />
                                        <video
                                            v-else-if="msg.media_type === 'video' && msg.media_url"
                                            :src="msg.media_url"
                                            controls
                                            class="max-w-xs rounded-lg mb-1"
                                        />
                                        <p v-if="msg.content" class="text-sm text-gray-700 leading-relaxed wrap-break-word">{{ msg.content }}</p>
                                    </div>
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
                        <!-- Media preview -->
                        <div v-if="mediaPreviewUrl" class="mb-2 relative inline-block">
                            <img
                                v-if="mediaFile?.type?.startsWith('image/')"
                                :src="mediaPreviewUrl"
                                class="h-24 rounded-lg object-cover border border-gray-200"
                            />
                            <video
                                v-else
                                :src="mediaPreviewUrl"
                                class="h-24 rounded-lg border border-gray-200"
                            />
                            <button
                                type="button"
                                @click="clearMedia"
                                class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-gray-800 text-white rounded-full flex items-center justify-center text-xs leading-none"
                            >×</button>
                        </div>

                        <form @submit.prevent="send" class="flex items-end gap-2">
                            <!-- Attach button -->
                            <input ref="fileInputEl" type="file" accept="image/*,video/*" class="hidden" @change="onFileSelected" />
                            <button
                                type="button"
                                @click="fileInputEl.click()"
                                class="shrink-0 w-9 h-9 flex items-center justify-center text-gray-400 hover:text-indigo-600 transition-colors"
                                title="Attach image or video"
                            >
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                </svg>
                            </button>

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
                                :disabled="(!content.trim() && !mediaFile) || sending"
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
            <div class="space-y-4">
                <CommunitySidebarCard :community="community">
                    <button
                        v-if="$page.props.auth?.user"
                        @click="showInviteModal = true"
                        class="w-full py-2 text-sm font-semibold border border-gray-300 dark:border-gray-600 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        Invite People
                    </button>
                </CommunitySidebarCard>

                <AiChatSidebar
                    :community-slug="community.slug"
                    :community-name="community.name"
                    :creator-name="community.owner?.name ?? community.name"
                    :creator-avatar="community.owner?.avatar"
                />
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
import CommunitySidebarCard from '@/Components/CommunitySidebarCard.vue';
import InviteModal from '@/Components/InviteModal.vue';
import AiChatSidebar from '@/Components/AiChatSidebar.vue';

const props = defineProps({
    community:         Object,
    messages:          Array,
    affiliate:         Object,
    telegramConnected: { type: Boolean, default: false },
});

const page    = usePage();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

// ── State ──────────────────────────────────────────────────────────────────────
const messages      = ref([...props.messages]);
const content         = ref('');
const sending         = ref(false);
const messagesEl      = ref(null);
const inputEl         = ref(null);
const fileInputEl     = ref(null);
const mediaFile       = ref(null);
const mediaPreviewUrl = ref(null);
const showInviteModal = ref(false);

function onFileSelected(e) {
    const file = e.target.files[0];
    if (!file) return;
    mediaFile.value = file;
    mediaPreviewUrl.value = URL.createObjectURL(file);
}

function clearMedia() {
    mediaFile.value = null;
    mediaPreviewUrl.value = null;
    if (fileInputEl.value) fileInputEl.value.value = '';
}

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

// Group consecutive messages from the same sender (telegram or app user)
function msgGroupKey(msg) {
    return msg.telegram_author ? `tg:${msg.telegram_author}` : `u:${msg.user?.id}`;
}

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
    if ((!text && !mediaFile.value) || sending.value) return;

    sending.value = true;
    const savedText  = text;
    const savedFile  = mediaFile.value;
    content.value = '';
    clearMedia();
    if (inputEl.value) inputEl.value.style.height = 'auto';

    try {
        const formData = new FormData();
        if (savedText) formData.append('content', savedText);
        if (savedFile) formData.append('media', savedFile);

        const res = await axios.post(`/communities/${props.community.slug}/chat`, formData, {
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'multipart/form-data' },
        });
        messages.value.push(res.data.message);
        scrollToBottom(true);
    } catch {
        content.value = savedText; // restore on error
    } finally {
        sending.value = false;
    }
}

// ── Real-time (Echo / Reverb) ─────────────────────────────────────────────────
let echoChannel = null;

function onIncomingMessage(e) {
    const msg = e.message;
    if (messages.value.some(m => m.id === msg.id)) return;

    const atBottom = messagesEl.value
        ? messagesEl.value.scrollHeight - messagesEl.value.scrollTop - messagesEl.value.clientHeight < 100
        : true;
    messages.value.push(msg);
    if (atBottom) scrollToBottom(true);
}

function onDeletedMessage(e) {
    messages.value = messages.value.filter(m => m.id !== e.message_id);
}

// ── Lifecycle ──────────────────────────────────────────────────────────────────
onMounted(() => {
    scrollToBottom();

    if (window.Echo) {
        echoChannel = window.Echo.join(`community.${props.community.id}.chat`)
            .listen('ChatMessageSent', onIncomingMessage)
            .listen('ChatMessageDeleted', onDeletedMessage);
    }
});

onBeforeUnmount(() => {
    if (echoChannel) {
        echoChannel.stopListening('ChatMessageSent');
        echoChannel.stopListening('ChatMessageDeleted');
        window.Echo.leave(`community.${props.community.id}.chat`);
    }
});
</script>
