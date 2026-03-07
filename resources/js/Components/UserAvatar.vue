<template>
    <div :style="{ width: px, height: px }" class="shrink-0 rounded-full overflow-hidden flex items-center justify-center font-bold text-white" :class="sizeClass">
        <img v-if="avatar" :src="avatar" :alt="name" class="w-full h-full object-cover" />
        <span v-else :style="{ background: bg, fontSize: fontSize }">{{ initial }}</span>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    name:   { type: String, default: '' },
    avatar: { type: String, default: null },
    size:   { type: [Number, String], default: 8 },
});

const COLORS = [
    '#6b7280','#10b981','#3b82f6','#8b5cf6','#ec4899',
    '#f59e0b','#ef4444','#14b8a6','#f97316','#6366f1',
];

const initial = computed(() => props.name?.charAt(0)?.toUpperCase() || '?');

const bg = computed(() => {
    const idx = (props.name?.charCodeAt(0) ?? 0) % COLORS.length;
    return COLORS[idx];
});

const sz = computed(() => Number(props.size));
const px = computed(() => `${sz.value * 4}px`);
const fontSize = computed(() => `${Math.max(sz.value * 1.5, 10)}px`);
const sizeClass = computed(() => '');
</script>
