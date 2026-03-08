<template>
    <div class="relative">
        <div v-if="!data?.length" class="flex items-center justify-center h-40 text-sm text-gray-400">
            No data for this period.
        </div>
        <div v-else>
            <!-- Bars -->
            <div class="flex items-end gap-1 h-40 mb-1">
                <div
                    v-for="(bar, i) in data"
                    :key="i"
                    class="flex-1 flex flex-col items-center justify-end group relative"
                >
                    <!-- Tooltip -->
                    <div
                        class="absolute bottom-full mb-1.5 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-[10px] px-2 py-1 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10"
                    >
                        {{ bar.label }}: ₱{{ fmt(bar.total) }}
                    </div>
                    <!-- Bar -->
                    <div
                        class="w-full rounded-t-sm transition-all duration-300"
                        :class="bar.total > 0 ? 'bg-indigo-500' : 'bg-gray-100'"
                        :style="{ height: barHeight(bar.total) }"
                    />
                </div>
            </div>

            <!-- X-axis labels — show only every nth to avoid crowding -->
            <div class="flex gap-1">
                <div
                    v-for="(bar, i) in data"
                    :key="i"
                    class="flex-1 text-center text-[9px] text-gray-400 truncate"
                >
                    {{ showLabel(i) ? bar.label : '' }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    data: { type: Array, default: () => [] },
});

const maxVal = computed(() => Math.max(...(props.data?.map(d => d.total) ?? [0]), 1));

function barHeight(total) {
    const pct = total / maxVal.value;
    const px  = Math.max(pct * 140, total > 0 ? 4 : 0);
    return `${px}px`;
}

function showLabel(i) {
    const n = props.data?.length ?? 0;
    if (n <= 12) return true;
    if (n <= 31) return i % 5 === 0 || i === n - 1;
    return i % Math.ceil(n / 8) === 0 || i === n - 1;
}

function fmt(n) {
    return Number(n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}
</script>
