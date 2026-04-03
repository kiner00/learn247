<template>
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h2 class="text-base font-bold text-gray-900 mb-6">Notifications</h2>

        <div class="space-y-0 divide-y divide-gray-100">
            <div v-for="n in notificationToggles" :key="n.key" class="flex items-center justify-between py-3.5">
                <p class="text-sm text-gray-700">{{ n.label }}</p>
                <button
                    @click="toggleNotif(n)"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                    :class="notifForm[n.key] ? 'bg-green-500' : 'bg-gray-200'"
                >
                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                        :class="notifForm[n.key] ? 'translate-x-6' : 'translate-x-1'" />
                </button>
            </div>
        </div>

        <!-- Per-community -->
        <div class="mt-4 space-y-2">
            <div v-for="m in communityMembers" :key="m.community_id"
                class="border border-gray-100 rounded-xl overflow-hidden">
                <button
                    @click="toggleCommunityNotif(m.community_id)"
                    class="w-full flex items-center gap-3 p-4 hover:bg-gray-50 transition-colors"
                >
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-600 shrink-0 overflow-hidden">
                        <img v-if="m.avatar" :src="m.avatar" :alt="m.name" class="w-full h-full object-cover" />
                        <span v-else>{{ m.name?.charAt(0)?.toUpperCase() }}</span>
                    </div>
                    <span class="flex-1 text-sm font-medium text-gray-800 text-left">{{ m.name }}</span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="openCommunityNotif === m.community_id ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div v-if="openCommunityNotif === m.community_id" class="px-4 pb-4 space-y-3 border-t border-gray-100 pt-3">
                    <div v-for="item in communityNotifItems" :key="item.key" class="flex items-center justify-between">
                        <p class="text-sm text-gray-600">{{ item.label }}</p>
                        <button @click="toggleCommunityNotifItem(m, item.key)"
                            class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
                            :class="m.notif_prefs[item.key] ? 'bg-green-500' : 'bg-gray-200'">
                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
                                :class="m.notif_prefs[item.key] ? 'translate-x-5' : 'translate-x-0.5'" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    notifPrefs:       { type: Object, required: true },
    communityMembers: { type: Array, required: true },
});

const notificationToggles = [
    { key: 'follower',  label: 'New follower' },
    { key: 'likes',     label: 'Likes' },
    { key: 'kaching',   label: 'Ka-ching' },
    { key: 'affiliate', label: 'Affiliate referral' },
];

const notifForm = useForm({ ...props.notifPrefs });

const communityMembers = reactive(props.communityMembers.map(m => ({ ...m })));

function toggleNotif(n) {
    notifForm[n.key] = !notifForm[n.key];
    notifForm.patch('/account/settings/notifications', { preserveScroll: true });
}

const openCommunityNotif = ref(null);
const communityNotifItems = [
    { key: 'new_posts', label: 'New posts' },
    { key: 'comments',  label: 'Comments' },
    { key: 'mentions',  label: 'Mentions' },
];
function toggleCommunityNotif(id) {
    openCommunityNotif.value = openCommunityNotif.value === id ? null : id;
}
function toggleCommunityNotifItem(m, key) {
    m.notif_prefs[key] = !m.notif_prefs[key];
    router.patch(`/account/settings/notifications/${m.community_id}`, m.notif_prefs, { preserveScroll: true });
}
</script>
