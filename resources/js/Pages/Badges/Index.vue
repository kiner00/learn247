<template>
    <AppLayout title="Badges">
        <div class="max-w-4xl">
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900">Badges</h1>
                <p class="text-sm text-gray-500 mt-1">Earn badges by hitting milestones on the platform. Locked badges show you what's possible.</p>
            </div>

            <!-- Member Badges -->
            <section class="mb-10">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-lg">🎖️</span>
                    <h2 class="text-base font-semibold text-gray-900">Member Badges</h2>
                    <span class="text-xs text-gray-400 font-normal">
                        {{ memberBadges.filter(b => b.earned).length }}/{{ memberBadges.length }} earned
                    </span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    <BadgeCard v-for="badge in memberBadges" :key="badge.key" :badge="badge" />
                </div>
            </section>

            <!-- Creator Badges -->
            <section>
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-lg">🏆</span>
                    <h2 class="text-base font-semibold text-gray-900">Community Creator Badges</h2>
                    <span class="text-xs text-gray-400 font-normal">
                        {{ creatorBadges.filter(b => b.earned).length }}/{{ creatorBadges.length }} earned
                    </span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    <BadgeCard v-for="badge in creatorBadges" :key="badge.key" :badge="badge" />
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import BadgeCard from './BadgeCard.vue';

defineProps({
    memberBadges:  Array,
    creatorBadges: Array,
});
</script>
