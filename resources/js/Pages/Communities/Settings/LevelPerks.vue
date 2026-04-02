<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';

const props = defineProps({
    community:  Object,
    levelPerks: { type: Object, default: () => ({}) },
});

const perksSaving = ref(false);
const perksSaved  = ref(false);

const levelPerksForm = ref(
    Object.fromEntries(Array.from({ length: 9 }, (_, i) => [i + 1, props.levelPerks[i + 1] ?? '']))
);

function saveLevelPerks() {
    perksSaving.value = true;
    router.patch(`/communities/${props.community.slug}/level-perks`, { perks: levelPerksForm.value }, {
        preserveScroll: true,
        onSuccess: () => { perksSaved.value = true; setTimeout(() => (perksSaved.value = false), 3000); },
        onFinish: () => { perksSaving.value = false; },
    });
}
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <div class="bg-white border border-gray-200 rounded-2xl p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Level Perks</h2>
            <p class="text-sm text-gray-500 mb-4">
                Set an unlock reward for each level. Members see this on the leaderboard as motivation.
                Leave blank to show no perk for that level.
            </p>
            <form @submit.prevent="saveLevelPerks" class="space-y-2">
                <div v-for="lvl in 9" :key="lvl" class="flex items-center gap-3">
                    <span class="w-16 text-xs font-semibold text-gray-500 shrink-0">Level {{ lvl }}</span>
                    <input
                        v-model="levelPerksForm[lvl]"
                        type="text"
                        :placeholder="lvl === 4 ? 'e.g. Chat with members' : 'e.g. Access to bonus content'"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" :disabled="perksSaving"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                        Save perks
                    </button>
                    <p v-if="perksSaved" class="text-sm text-green-600">Saved!</p>
                </div>
            </form>
        </div>
    </CommunitySettingsLayout>
</template>
