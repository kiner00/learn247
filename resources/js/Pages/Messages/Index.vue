<template>
    <AppLayout title="Messages">
        <div class="max-w-2xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-xl font-black text-gray-900">Messages</h1>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                <!-- Empty state -->
                <div v-if="!conversations.length" class="py-20 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-50 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-gray-700 mb-1">No messages yet</p>
                    <p class="text-xs text-gray-400">Chat with members from any community.</p>
                </div>

                <!-- Conversation list -->
                <Link
                    v-for="conv in conversations"
                    :key="conv.user?.id"
                    :href="`/messages/${conv.user?.username ?? conv.user?.id}`"
                    class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-0"
                >
                    <!-- Avatar -->
                    <div
                        class="w-11 h-11 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
                        :class="avatarColor(conv.user?.name)"
                    >
                        {{ conv.user?.name?.charAt(0)?.toUpperCase() }}
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-0.5">
                            <p class="text-sm font-semibold text-gray-900">{{ conv.user?.name }}</p>
                            <p class="text-xs text-gray-400 shrink-0 ml-2">{{ formatTime(conv.latest_message?.created_at) }}</p>
                        </div>
                        <p class="text-sm text-gray-500 truncate">
                            <span v-if="conv.latest_message?.is_mine" class="text-gray-400">You: </span>
                            <template v-if="conv.latest_message?.content">{{ conv.latest_message.content }}</template>
                            <span v-else-if="conv.latest_message?.has_image" class="inline-flex items-center gap-1 text-gray-500">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Photo
                            </span>
                            <template v-else>No messages yet</template>
                        </p>
                    </div>

                    <!-- Unread badge -->
                    <span
                        v-if="conv.unread_count > 0"
                        class="shrink-0 min-w-5 h-5 px-1 bg-indigo-600 text-white text-xs font-bold rounded-full flex items-center justify-center"
                    >
                        {{ conv.unread_count }}
                    </span>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ conversations: Array });

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

function formatTime(str) {
    if (!str) return '';
    const d = new Date(str);
    const now = new Date();
    const diffDays = Math.floor((now - d) / 86400000);
    if (diffDays === 0) return d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7)  return d.toLocaleDateString('en-PH', { weekday: 'short' });
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}
</script>
