<template>
    <div class="flex items-center gap-1 border-b border-gray-200 mb-6 -mx-0 overflow-x-auto">
        <Link
            v-for="tab in tabs"
            :key="tab.name"
            :href="tab.href"
            class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors"
            :class="activeTab === tab.name
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300'"
        >
            {{ tab.label }}
        </Link>
    </div>

    <!-- KYC verification banner for unverified community owners -->
    <div
        v-if="isOwner && !page.props.auth?.user?.kyc_verified"
        class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"
    >
        <div class="flex items-center gap-2 text-sm text-amber-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.981-1.742 2.981H4.42c-1.53 0-2.493-1.646-1.743-2.981l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <span>Your community is not yet visible on the directory. Please complete your profile to get verified.</span>
        </div>
        <Link
            href="/account/settings?tab=profile"
            class="shrink-0 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700 transition-colors"
        >
            Complete Profile
        </Link>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const props = defineProps({
    community: Object,
    activeTab: String,
});

const slug = computed(() => props.community.slug);
const isOwner = computed(() => props.community.owner_id === page.props.auth?.user?.id);

const tabs = computed(() => [
    { name: 'community',   label: 'Community',    href: `/communities/${slug.value}` },
    { name: 'classroom',       label: 'Classroom',       href: `/communities/${slug.value}/classroom` },
    { name: 'certifications',  label: 'Certifications',  href: `/communities/${slug.value}/certifications` },
    { name: 'calendar',        label: 'Calendar',        href: `/communities/${slug.value}/calendar` },
    { name: 'members',     label: 'Members',      href: `/communities/${slug.value}/members` },
    { name: 'chat',        label: 'Chat',         href: `/communities/${slug.value}/chat` },
    { name: 'leaderboard', label: 'Leaderboards', href: `/communities/${slug.value}/leaderboard` },
    { name: 'about',       label: 'About',        href: `/communities/${slug.value}/about` },
]);
</script>
