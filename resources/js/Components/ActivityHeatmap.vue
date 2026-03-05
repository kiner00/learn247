<template>
    <div>
        <!-- Month labels -->
        <div class="flex ml-8 mb-1 text-xs text-gray-400 gap-0">
            <span
                v-for="(month, i) in monthLabels"
                :key="i"
                :style="{ width: month.weeks * cellSize + 'px' }"
                class="shrink-0"
            >{{ month.label }}</span>
        </div>

        <div class="flex gap-0.5">
            <!-- Day labels -->
            <div class="flex flex-col gap-0.5 mr-1.5" :style="{ marginTop: cellSize * 0 + 'px' }">
                <div v-for="day in ['', 'Mon', '', 'Wed', '', 'Fri', '', 'Sun']" :key="day"
                     class="text-xs text-gray-400 leading-none flex items-center"
                     :style="{ height: cellSize + 'px', fontSize: '10px' }">
                    {{ day }}
                </div>
            </div>

            <!-- Week columns -->
            <div class="flex gap-0.5">
                <div
                    v-for="(week, wi) in weeks"
                    :key="wi"
                    class="flex flex-col gap-0.5"
                >
                    <div
                        v-for="(day, di) in week"
                        :key="di"
                        class="rounded-sm cursor-default transition-colors"
                        :style="{ width: cellSize + 'px', height: cellSize + 'px' }"
                        :class="colorClass(day.count)"
                        :title="day.date ? `${day.date}: ${day.count} contribution${day.count !== 1 ? 's' : ''}` : ''"
                    />
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="flex items-center justify-between mt-2">
            <span class="text-xs text-gray-400">What is this?</span>
            <div class="flex items-center gap-1 text-xs text-gray-400">
                <span>Less</span>
                <div class="w-2.5 h-2.5 rounded-sm bg-gray-100"></div>
                <div class="w-2.5 h-2.5 rounded-sm bg-green-200"></div>
                <div class="w-2.5 h-2.5 rounded-sm bg-green-400"></div>
                <div class="w-2.5 h-2.5 rounded-sm bg-green-600"></div>
                <span>More</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    activityMap: { type: Object, default: () => ({}) },
});

const cellSize = 12; // px

// Build 52 weeks × 7 days grid ending today
const weeks = computed(() => {
    const today    = new Date();
    const end      = new Date(today);
    // shift to end of week (Saturday)
    end.setDate(end.getDate() + (6 - end.getDay()));

    const start = new Date(end);
    start.setDate(start.getDate() - 52 * 7 + 1);

    const allWeeks = [];
    let cur = new Date(start);

    while (cur <= end) {
        const week = [];
        for (let d = 0; d < 7; d++) {
            const dateStr = cur.toISOString().slice(0, 10);
            const isFuture = cur > today;
            week.push({
                date:  isFuture ? '' : dateStr,
                count: isFuture ? -1 : (props.activityMap[dateStr] ?? 0),
            });
            cur.setDate(cur.getDate() + 1);
        }
        allWeeks.push(week);
    }
    return allWeeks;
});

// Month labels above columns
const monthLabels = computed(() => {
    const labels = [];
    let lastMonth = null;
    let currentGroup = null;

    weeks.value.forEach((week) => {
        const firstDay = week.find(d => d.date);
        if (!firstDay) return;
        const month = new Date(firstDay.date).toLocaleString('en', { month: 'short' });
        if (month !== lastMonth) {
            if (currentGroup) labels.push(currentGroup);
            currentGroup = { label: month, weeks: 1 };
            lastMonth = month;
        } else {
            currentGroup.weeks++;
        }
    });
    if (currentGroup) labels.push(currentGroup);
    return labels;
});

function colorClass(count) {
    if (count < 0) return 'bg-gray-50';   // future
    if (count === 0) return 'bg-gray-100';
    if (count === 1) return 'bg-green-200';
    if (count <= 3)  return 'bg-green-400';
    return 'bg-green-600';
}
</script>
