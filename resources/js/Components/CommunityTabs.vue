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
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    community: Object,
    activeTab: String,
});

const slug = computed(() => props.community.slug);

const tabs = computed(() => [
    { name: 'community',   label: 'Community',   icon: '💬', href: `/communities/${slug.value}` },
    { name: 'classroom',   label: 'Classroom',   icon: '🎓', href: `/communities/${slug.value}/classroom` },
    { name: 'members',     label: 'Members',     icon: '👥', href: `/communities/${slug.value}/members` },
    { name: 'leaderboard', label: 'Leaderboards', icon: '🏆', href: `/communities/${slug.value}/leaderboard` },
    { name: 'about',       label: 'About',       icon: 'ℹ️', href: `/communities/${slug.value}/about` },
]);
</script>
