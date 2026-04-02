<script setup>
import { router } from '@inertiajs/vue3';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';

const props = defineProps({
    community: Object,
});

function deleteCommunity() {
    if (!confirm('Are you sure you want to delete this community? If there are active subscribers, deletion will be scheduled and the community will be removed once all subscriptions expire.')) return;
    router.delete(`/communities/${props.community.slug}`);
}

function cancelDeletion() {
    if (!confirm('Cancel the scheduled deletion? The community will become active again.')) return;
    router.post(`/communities/${props.community.slug}/cancel-deletion`, {}, { preserveScroll: true });
}
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <div class="bg-white border border-red-200 rounded-2xl p-6">
            <h2 class="text-base font-semibold text-red-600 mb-1">Danger zone</h2>

            <!-- Pending deletion notice -->
            <div v-if="community.deletion_requested_at" class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                <p class="text-sm font-semibold text-amber-800 mb-1">Deletion scheduled</p>
                <p class="text-sm text-amber-700">
                    This community is pending deletion. No new members can join and subscriptions will not renew.
                    It will be automatically deleted once all active subscribers expire.
                </p>
                <button
                    @click="cancelDeletion"
                    class="mt-3 px-4 py-2 border border-amber-400 text-amber-800 text-sm font-medium rounded-lg hover:bg-amber-100 transition-colors"
                >
                    Cancel scheduled deletion
                </button>
            </div>

            <template v-else>
                <p class="text-sm text-gray-500 mb-4">
                    If the community has active subscribers, deletion will be scheduled — no new joins, no renewals.
                    The community will be automatically deleted once all subscriptions expire.
                </p>
                <button
                    @click="deleteCommunity"
                    class="px-4 py-2 border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors"
                >
                    Delete community
                </button>
            </template>
        </div>
    </CommunitySettingsLayout>
</template>
