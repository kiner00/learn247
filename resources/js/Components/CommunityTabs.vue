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

    <!-- KYC banner for unverified community owners -->
    <div
        v-if="isOwner && !page.props.auth?.user?.kyc_verified"
        class="mb-4 flex items-center justify-between gap-3 rounded-xl border px-4 py-3"
        :class="bannerStyle"
    >
        <div class="flex items-center gap-2 text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" :class="bannerIconClass" viewBox="0 0 20 20" fill="currentColor">
                <path v-if="kycStatus === 'submitted'" fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                <path v-else-if="kycStatus === 'rejected'" fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                <path v-else fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.981-1.742 2.981H4.42c-1.53 0-2.493-1.646-1.743-2.981l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <span>{{ bannerMessage }}</span>
        </div>
        <Link
            v-if="kycStatus !== 'submitted'"
            href="/account/settings?tab=kyc"
            class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold text-white transition-colors"
            :class="kycStatus === 'rejected' ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-600 hover:bg-amber-700'"
        >
            {{ kycStatus === 'rejected' ? 'Re-submit' : 'Verify Now' }}
        </Link>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const page = usePage();

const props = defineProps({
    community: Object,
    activeTab: String,
});

const slug = computed(() => props.community.slug);
const { communityPath } = useCommunityUrl(slug.value);
const isOwner = computed(() => !!page.props.auth?.user?.id && props.community.owner_id === page.props.auth.user.id);
const kycStatus = computed(() => page.props.auth?.user?.kyc_status ?? 'none');

const bannerMessage = computed(() => {
    switch (kycStatus.value) {
        case 'submitted':
            return 'Your verification is under review. Your community will be listed once approved.';
        case 'rejected':
            return 'Your verification was rejected. Please re-submit your documents.';
        default:
            return 'Your community is not yet visible on the directory. Verify your identity to get listed.';
    }
});

const bannerStyle = computed(() => {
    switch (kycStatus.value) {
        case 'submitted': return 'border-blue-200 bg-blue-50 text-blue-800';
        case 'rejected':  return 'border-red-200 bg-red-50 text-red-800';
        default:          return 'border-amber-200 bg-amber-50 text-amber-800';
    }
});

const bannerIconClass = computed(() => {
    switch (kycStatus.value) {
        case 'submitted': return 'text-blue-500';
        case 'rejected':  return 'text-red-500';
        default:          return 'text-amber-500';
    }
});

const baseTabs = [
    { name: 'community',      label: 'Community',      href: communityPath() },
    { name: 'classroom',      label: 'Classroom',      href: communityPath('/classroom') },
    { name: 'certifications', label: 'Certifications', href: communityPath('/certifications') },
    { name: 'calendar',       label: 'Calendar',       href: communityPath('/calendar') },
    { name: 'members',        label: 'Members',        href: communityPath('/members') },
    { name: 'chat',           label: 'Chat',           href: communityPath('/chat') },
    { name: 'leaderboard',    label: 'Leaderboards',   href: communityPath('/leaderboard') },
    { name: 'about',          label: 'About',          href: communityPath('/about') },
];

const tabs = computed(() => {
    return [...baseTabs];
});
</script>
