<template>
    <!-- Sales landing page (locked course) -->
    <div class="mb-8">

        <!-- Hero: cover + title + CTA -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm mb-4">
            <!-- Hero: preview video or cover image -->
            <div class="relative w-full bg-gray-900" :style="course.preview_video ? 'aspect-ratio:16/9' : 'aspect-ratio:16/7'">
                <!-- Preview video (auto-plays muted, click to unmute) -->
                <template v-if="course.preview_video">
                    <video
                        ref="heroVideoRef"
                        :src="course.preview_video"
                        :poster="course.cover_image || undefined"
                        class="w-full h-full object-cover"
                        muted
                        autoplay
                        loop
                        playsinline
                        @click="toggleMute"
                    />
                    <!-- Mute/unmute indicator -->
                    <button
                        @click="toggleMute"
                        class="absolute bottom-3 left-3 w-8 h-8 bg-black/50 hover:bg-black/70 text-white rounded-full flex items-center justify-center transition-colors z-10"
                        :title="isMuted ? 'Unmute' : 'Mute'"
                    >
                        <svg v-if="isMuted" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                        </svg>
                    </button>
                </template>
                <!-- Cover image fallback -->
                <template v-else>
                    <img
                        v-if="course.cover_image"
                        :src="course.cover_image"
                        :alt="course.title"
                        class="w-full h-full object-cover opacity-90"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-600 to-purple-700">
                        <svg class="w-16 h-16 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                </template>
                <!-- Price badge overlay -->
                <div class="absolute top-3 right-3 z-10">
                    <span class="px-3 py-1.5 bg-indigo-600 text-white text-sm font-black rounded-xl shadow-lg">
                        {{ course.access_type === 'paid_once' ? `₱${Number(course.price).toLocaleString()}` : course.access_type === 'paid_monthly' ? `₱${Number(course.price).toLocaleString()}/mo` : 'Members Only' }}
                    </span>
                </div>
            </div>

            <!-- Title + description + CTA -->
            <div class="p-6">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-5">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-indigo-500 uppercase tracking-wide mb-1">{{ community.name }}</p>
                        <h1 class="text-2xl font-black text-gray-900 leading-tight mb-3">{{ course.title }}</h1>
                        <p v-if="course.description" class="text-sm text-gray-600 leading-relaxed">{{ course.description }}</p>
                        <div class="flex items-center gap-4 mt-4 text-xs text-gray-400">
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                {{ totalLessons }} lessons
                            </span>
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                {{ course.modules?.length ?? 0 }} modules
                            </span>
                            <span v-if="course.access_type === 'paid_once'" class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Lifetime access
                            </span>
                        </div>
                    </div>

                    <!-- CTA block -->
                    <div class="shrink-0 flex flex-col items-center gap-2 md:items-end">
                        <template v-if="course.access_type === 'paid_once' || course.access_type === 'paid_monthly'">
                            <div v-if="!authUserId">
                                <button @click="showAuthModal = true"
                                    class="px-8 py-3 bg-indigo-600 text-white text-base font-black rounded-2xl hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-200">
                                    Sign up to enroll
                                </button>
                            </div>
                            <div v-else-if="enrollment?.status === 'pending'" class="flex items-center gap-2">
                                <span class="text-xs text-amber-600 font-medium">Payment pending...</span>
                                <button @click="$emit('enroll')" :disabled="enrollProcessing"
                                    class="px-5 py-2.5 border border-amber-400 text-amber-700 text-sm font-semibold rounded-xl hover:bg-amber-50 transition-colors">
                                    Retry payment
                                </button>
                            </div>
                            <div v-else class="text-center md:text-right">
                                <button @click="$emit('enroll')" :disabled="enrollProcessing"
                                    class="px-8 py-3 bg-indigo-600 text-white text-base font-black rounded-2xl hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-md shadow-indigo-200 whitespace-nowrap">
                                    {{ enrollProcessing ? 'Redirecting...' : `Get Access · ₱${Number(course.price).toLocaleString()}${course.access_type === 'paid_monthly' ? '/mo' : ''}` }}
                                </button>
                                <p class="text-[10px] text-gray-400 mt-1.5">Processed securely under <strong>learn247</strong></p>
                            </div>
                        </template>
                        <template v-else>
                            <button v-if="!authUserId" @click="showAuthModal = true"
                                class="px-8 py-3 bg-indigo-600 text-white text-base font-black rounded-2xl hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-200">
                                Sign up to access
                            </button>
                            <Link v-else :href="`/communities/${community.slug}/about`"
                                class="px-8 py-3 bg-indigo-600 text-white text-base font-black rounded-2xl hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-200">
                                Join Community to Unlock
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- What's inside -->
        <div v-if="course.modules?.length" class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-bold text-gray-900">What's inside</h2>
            </div>
            <div class="divide-y divide-gray-50">
                <div v-for="mod in course.modules" :key="mod.id" class="px-5 py-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ mod.title }}</p>
                    <div class="space-y-1">
                        <div v-for="lesson in mod.lessons" :key="lesson.id"
                            class="flex items-center gap-2 text-sm text-gray-600">
                            <svg class="w-3.5 h-3.5 text-indigo-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>{{ lesson.title }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <AuthModal :show="showAuthModal" @close="showAuthModal = false" />
    </div>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import AuthModal from '@/Components/AuthModal.vue';

defineProps({
    community:       { type: Object, required: true },
    course:          { type: Object, required: true },
    enrollment:      { type: Object, default: null },
    authUserId:      { type: [Number, String], default: null },
    totalLessons:    { type: Number, required: true },
    enrollProcessing:{ type: Boolean, default: false },
});

defineEmits(['enroll']);

const showAuthModal = ref(false);
const heroVideoRef = ref(null);
const isMuted = ref(true);

function toggleMute() {
    if (!heroVideoRef.value) return;
    heroVideoRef.value.muted = !heroVideoRef.value.muted;
    isMuted.value = heroVideoRef.value.muted;
}

onBeforeUnmount(() => {
    if (heroVideoRef.value) {
        heroVideoRef.value.pause();
        heroVideoRef.value.currentTime = 0;
        heroVideoRef.value.muted = true;
    }
});
</script>
