<template>
    <div class="bg-white border border-gray-200 rounded-2xl p-4 mb-6 shadow-sm flex items-center gap-4">
        <div class="flex-1">
            <div class="flex items-center justify-between text-xs text-gray-500 mb-1.5">
                <span class="font-medium text-gray-700">{{ courseTitle }}</span>
                <span>{{ completedCount }}/{{ totalLessons }} lessons</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div
                    class="h-full bg-indigo-500 rounded-full transition-all"
                    :style="{ width: `${progress}%` }"
                />
            </div>
        </div>
        <span class="text-sm font-black text-indigo-600 shrink-0">{{ progress }}%</span>
    </div>
</template>

<script setup>
defineProps({
    courseTitle:     { type: String, required: true },
    completedCount: { type: Number, required: true },
    totalLessons:   { type: Number, required: true },
    progress:       { type: Number, required: true },
});
</script>
