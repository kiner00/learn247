<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    community:    { type: Object,  required: true },
    membersCount: { type: Number,  default: null },
    adminCount:   { type: Number,  default: 0 },
    isMember:     { type: Boolean, default: true },
});

const dc = usePage().props.domain_community;
const displayUrl = computed(() =>
    dc && dc.slug === props.community.slug
        ? props.community.custom_domain || `${props.community.subdomain ? props.community.subdomain + '.' : ''}curzzo.com`
        : `curzzo.com/communities/${props.community.slug}`
);
</script>

<template>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden shadow-sm">
        <!-- Cover image -->
        <div class="h-50 overflow-hidden">
            <img
                v-if="community.cover_image"
                :src="community.cover_image"
                :alt="community.name"
                class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full bg-linear-to-br from-indigo-500 to-purple-700" />
        </div>

        <div class="p-4">
            <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base mb-0.5">{{ community.name }}</h3>
            <p class="text-xs text-gray-400 mb-2">{{ displayUrl }}</p>
            <p v-if="community.description" class="text-sm text-gray-500 dark:text-gray-400 mb-4 leading-relaxed line-clamp-3">
                {{ community.description }}
            </p>

            <!-- Stats -->
            <div v-if="isMember" class="flex items-center justify-around text-center border-y border-gray-100 dark:border-gray-700 py-3 mb-4">
                <div>
                    <p class="text-base font-black text-gray-900 dark:text-gray-100">{{ membersCount ?? community.members_count ?? 0 }}</p>
                    <p class="text-xs text-gray-400">Members</p>
                </div>
                <div>
                    <p class="text-base font-black text-gray-900 dark:text-gray-100">0</p>
                    <p class="text-xs text-gray-400">Online</p>
                </div>
                <div>
                    <p class="text-base font-black text-gray-900 dark:text-gray-100">{{ adminCount }}</p>
                    <p class="text-xs text-gray-400">{{ adminCount === 1 ? 'Admin' : 'Admins' }}</p>
                </div>
            </div>
            <div v-else class="border-y border-gray-100 dark:border-gray-700 py-3 mb-4 text-center">
                <p class="text-xs text-gray-400">Join to see member stats</p>
            </div>

            <!-- Page-specific actions -->
            <slot />
        </div>
    </div>
</template>
