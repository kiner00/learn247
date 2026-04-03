<template>
    <div class="bg-white border border-gray-200 rounded-2xl p-6 space-y-6">

        <!-- Notifications toggle -->
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-bold text-gray-900 mb-1">Notifications</p>
                <p class="text-sm text-gray-500">Notify me with sound and blinking tab header when somebody messages me.</p>
            </div>
            <button @click="toggleChat('notifications')"
                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors mt-0.5"
                :class="chatForm.notifications ? 'bg-green-500' : 'bg-gray-200'">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                    :class="chatForm.notifications ? 'translate-x-6' : 'translate-x-1'" />
            </button>
        </div>

        <!-- Email notifications toggle -->
        <div class="flex items-start justify-between gap-4 border-t border-gray-100 pt-6">
            <div>
                <p class="text-sm font-bold text-gray-900 mb-1">Email notifications</p>
                <p class="text-sm text-gray-500">If you're offline and somebody messages you, we'll let you know via email. We won't email you if you're online.</p>
            </div>
            <button @click="toggleChat('email_notifications')"
                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors mt-0.5"
                :class="chatForm.email_notifications ? 'bg-green-500' : 'bg-gray-200'">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                    :class="chatForm.email_notifications ? 'translate-x-6' : 'translate-x-1'" />
            </button>
        </div>

        <!-- Who can message me -->
        <div class="border-t border-gray-100 pt-6">
            <p class="text-sm font-bold text-gray-900 mb-1">Who can message me?</p>
            <p class="text-sm text-gray-500 mb-4">Only members in the group you're in can message you. You choose what group users can message you from by turning your chat on/off below.</p>
            <div class="space-y-2">
                <div v-for="m in communityMembers" :key="m.community_id"
                    class="flex items-center justify-between p-3 border border-gray-100 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-600 shrink-0 overflow-hidden">
                            <img v-if="m.avatar" :src="m.avatar" :alt="m.name" class="w-full h-full object-cover" />
                            <span v-else>{{ m.name?.charAt(0)?.toUpperCase() }}</span>
                        </div>
                        <span class="text-sm font-medium text-gray-800">{{ m.name }}</span>
                    </div>
                    <button @click="toggleCommunityChat(m)"
                        class="flex items-center gap-1.5 px-3 py-1.5 border rounded-lg text-xs font-medium transition-colors"
                        :class="m.chat_enabled ? 'border-green-300 text-green-700 bg-green-50' : 'border-gray-200 text-gray-500 hover:bg-gray-50'">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        {{ m.chat_enabled ? 'ON' : 'OFF' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Blocked users -->
        <div class="border-t border-gray-100 pt-6">
            <p class="text-sm font-bold text-gray-900 mb-1">Blocked users</p>
            <p class="text-sm text-gray-500">You have no blocked users.</p>
        </div>
    </div>
</template>

<script setup>
import { reactive } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    chatPrefs:        { type: Object, required: true },
    communityMembers: { type: Array, required: true },
});

const chatForm = useForm({ ...props.chatPrefs });
const communityMembers = reactive(props.communityMembers.map(m => ({ ...m })));

function toggleChat(key) {
    chatForm[key] = !chatForm[key];
    chatForm.patch('/account/settings/chat', { preserveScroll: true });
}

function toggleCommunityChat(m) {
    m.chat_enabled = !m.chat_enabled;
    router.patch(`/account/settings/chat/${m.community_id}`, { chat_enabled: m.chat_enabled }, { preserveScroll: true });
}
</script>
