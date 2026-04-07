<script setup>
import { Link } from '@inertiajs/vue3';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const props = defineProps({
    community: Object,
});

const { communityPath } = useCommunityUrl(props.community.slug);
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Workflows</h1>
            <p class="text-sm text-gray-500 mt-1">Automate tagging and actions based on member behavior.</p>
        </div>

        <!-- Coming soon -->
        <div class="bg-white border border-gray-200 rounded-2xl p-12 text-center">
            <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-blue-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-900 mb-1">Workflows coming soon</h3>
            <p class="text-sm text-gray-500 mb-2 max-w-md mx-auto">
                Set up automated rules to tag members based on triggers like course enrollment, subscription changes, or time-based conditions.
            </p>
            <p class="text-xs text-gray-400 mb-4">Example: When a member enrolls in a course → automatically apply the "LEAD" tag.</p>

            <!-- Sample workflow preview -->
            <div class="max-w-xs mx-auto mt-6 bg-gray-50 border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                    <span class="text-xs font-semibold text-gray-700">Sample Workflow</span>
                </div>
                <div class="space-y-2">
                    <div class="bg-emerald-100 text-emerald-700 text-xs font-medium px-3 py-2 rounded-lg text-center">
                        Trigger: Member enrolls in course
                    </div>
                    <div class="flex justify-center">
                        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </div>
                    <div class="bg-blue-100 text-blue-700 text-xs font-medium px-3 py-2 rounded-lg text-center">
                        Action: Apply "LEAD" tag
                    </div>
                </div>
            </div>

            <p class="text-xs text-gray-400 mt-6">
                In the meantime, you can
                <Link :href="communityPath('/settings/tags')" class="text-indigo-600 hover:underline">manage tags manually</Link>
                from the Members page.
            </p>
        </div>
    </CommunitySettingsLayout>
</template>
