<template>
    <div
        class="relative flex flex-col items-center text-center p-5 rounded-2xl border transition-all duration-200"
        :class="badge.earned
            ? 'bg-white border-indigo-200 shadow-sm'
            : 'bg-gray-50 border-gray-200 opacity-50 grayscale'"
    >
        <!-- Earned indicator -->
        <div
            v-if="badge.earned"
            class="absolute top-2 right-2 w-5 h-5 rounded-full bg-green-500 flex items-center justify-center"
            title="Earned!"
        >
            <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <!-- Badge icon -->
        <div class="text-4xl mb-3 leading-none select-none">{{ badge.icon }}</div>

        <!-- Name -->
        <p class="text-sm font-semibold text-gray-900 leading-tight mb-1">{{ badge.name }}</p>

        <!-- Description -->
        <p class="text-xs text-gray-500 leading-snug mb-3">{{ badge.description }}</p>

        <!-- How to earn (always visible) -->
        <div class="w-full pt-3 border-t border-gray-100">
            <p class="text-[11px] text-gray-400 leading-snug italic">
                <span class="font-medium not-italic text-gray-500">How to earn:</span>
                {{ badge.how_to_earn }}
            </p>
        </div>
    </div>
</template>

<script setup>
defineProps({
    badge: Object,
});
</script>
