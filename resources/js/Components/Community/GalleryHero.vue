<template>
    <div class="w-full bg-gray-900 overflow-hidden relative" style="aspect-ratio: 16/9;">
        <!-- Empty state -->
        <div v-if="!item" class="w-full h-full bg-linear-to-br from-indigo-500 to-purple-700" />

        <!-- Image item -->
        <img
            v-else-if="item.type === 'image'"
            :src="item.url"
            :alt="alt"
            class="w-full h-full object-contain transition-all duration-300"
        />

        <!-- Video, idle (poster + play button) -->
        <template v-else-if="item.type === 'video' && !playing">
            <img
                v-if="item.poster_url"
                :src="item.poster_url"
                :alt="alt"
                class="w-full h-full object-contain"
            />
            <div v-else class="w-full h-full bg-linear-to-br from-gray-800 to-gray-900" />

            <!-- Transcoding overlay -->
            <div v-if="isTranscoding" class="absolute inset-0 flex flex-col items-center justify-center bg-black/60 text-white text-center px-6">
                <svg class="w-10 h-10 animate-spin mb-3" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <p class="text-sm font-semibold">Preparing video…</p>
                <p v-if="item.transcode_percent" class="text-xs text-gray-300 mt-1">{{ item.transcode_percent }}%</p>
            </div>

            <!-- Failed -->
            <div v-else-if="item.transcode_status === 'failed'" class="absolute inset-0 flex flex-col items-center justify-center bg-black/70 text-white text-center px-6">
                <p class="text-sm font-semibold">Video unavailable</p>
                <p class="text-xs text-gray-300 mt-1">Transcoding failed.</p>
            </div>

            <!-- Play button -->
            <button
                v-else-if="item.video_ready"
                type="button"
                class="absolute inset-0 flex items-center justify-center group"
                @click="startUserInitiated"
                aria-label="Play video"
            >
                <span class="absolute inset-0 bg-black/30 group-hover:bg-black/40 transition-colors" />
                <span class="relative w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-white/90 group-hover:bg-white flex items-center justify-center shadow-lg transition-transform group-hover:scale-105">
                    <svg class="w-8 h-8 sm:w-10 sm:h-10 text-gray-900 ml-1" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z" />
                    </svg>
                </span>
            </button>
        </template>

        <!-- Video, playing -->
        <video
            v-else
            ref="videoEl"
            class="w-full h-full object-contain bg-black"
            controls
            autoplay
            playsinline
            :muted="autoStarted"
            :poster="item.poster_url || undefined"
        />
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useHlsPlayer } from '@/composables/useHlsPlayer.js';

const props = defineProps({
    item: { type: Object, default: null },
    alt:  { type: String, default: '' },
});

const playing = ref(false);
// True when playback started via the autoplay flag (requires muted for browser policies),
// false when the user clicked play (audio allowed).
const autoStarted = ref(false);
const videoEl = ref(null);

const isTranscoding = computed(() =>
    props.item?.type === 'video' &&
    (props.item.transcode_status === 'pending' || props.item.transcode_status === 'processing')
);

const hlsSource = computed(() =>
    playing.value && props.item?.type === 'video' ? props.item.hls_url : null
);

useHlsPlayer(videoEl, hlsSource);

function startUserInitiated() {
    autoStarted.value = false;
    playing.value = true;
}

// Reset / re-apply autoplay when the active item changes.
watch(
    () => [props.item?.id, props.item?.video_ready, props.item?.autoplay],
    () => {
        if (props.item?.type === 'video' && props.item?.video_ready && props.item?.autoplay) {
            autoStarted.value = true;
            playing.value = true;
        } else {
            autoStarted.value = false;
            playing.value = false;
        }
    },
    { immediate: true },
);
</script>
