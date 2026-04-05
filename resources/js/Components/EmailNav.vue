<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const props = defineProps({
    community: { type: Object, required: true },
    active: { type: String, default: 'campaigns' },
});

const page = usePage();
const { communityPath } = useCommunityUrl(props.community.slug);

const items = [
    { name: 'campaigns',  label: 'Campaigns',  href: communityPath('/email-campaigns') },
    { name: 'sequences',  label: 'Sequences',  href: communityPath('/email-sequences') },
    { name: 'history',    label: 'Send History', href: communityPath('/email-history') },
    { name: 'analytics',  label: 'Analytics',  href: communityPath('/email-analytics') },
    { name: 'settings',   label: 'Settings',   href: communityPath('/settings/email') },
];
</script>

<template>
    <div class="flex items-center gap-1 border-b border-gray-200 mb-6 overflow-x-auto">
        <Link
            v-for="item in items"
            :key="item.name"
            :href="item.href"
            class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors"
            :class="active === item.name
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300'"
        >
            {{ item.label }}
        </Link>
    </div>
</template>
