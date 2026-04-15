<template>
    <!-- Carousel variant -->
    <section v-if="data.kind === 'carousel'" class="py-20 relative bg-cover bg-center"
        :style="{
            backgroundColor: data.bg_color || '#ffffff',
            backgroundImage: data.bg_image ? `url(${data.bg_image})` : 'none',
        }">
        <div class="max-w-4xl mx-auto px-6">
            <h2 v-if="data.title"
                v-html="sanitizeHtml(data.title)"
                :contenteditable="inlineMode ? 'true' : 'false'"
                @focus="inlineMode && $emit('elFocus', $event, `custom_sections.${sectionId}.title`)"
                @blur="inlineMode && $emit('elBlur', $event, `custom_sections.${sectionId}.title`)"
                @keydown.enter.prevent="insertBr"
                :class="['text-3xl sm:text-4xl font-black text-center mb-3', inlineMode ? editableClass : '']"
                :style="{ color: data.text_color || '#111827' }"
            />
            <p v-if="data.subtitle"
                v-html="sanitizeHtml(data.subtitle)"
                :contenteditable="inlineMode ? 'true' : 'false'"
                @focus="inlineMode && $emit('elFocus', $event, `custom_sections.${sectionId}.subtitle`)"
                @blur="inlineMode && $emit('elBlur', $event, `custom_sections.${sectionId}.subtitle`)"
                @keydown.enter.prevent="insertBr"
                :class="['text-base sm:text-lg text-center mb-10', inlineMode ? editableClass : '']"
                :style="{ color: data.subtitle_color || '#4b5563' }"
            />

            <div v-if="slides.length" class="relative">
                <div class="flex items-center gap-3">
                    <button type="button" @click="prev" aria-label="Previous slide"
                        class="shrink-0 w-10 h-10 rounded-full bg-white/90 hover:bg-white shadow-md border border-gray-200 flex items-center justify-center text-gray-700 transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <div class="flex-1 overflow-hidden">
                        <div class="flex transition-transform duration-500 ease-out"
                            :style="{ transform: `translateX(-${activeIdx * 100}%)` }">
                            <div v-for="(slide, i) in slides" :key="i"
                                class="shrink-0 w-full flex justify-center px-2">
                                <img v-if="slide.image_url" :src="slide.image_url" :alt="slide.alt || ''"
                                    class="rounded-2xl shadow-lg max-h-[420px] w-auto object-contain" />
                                <div v-else class="w-full aspect-video rounded-2xl bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center text-gray-400 text-sm">
                                    Slide {{ i + 1 }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" @click="next" aria-label="Next slide"
                        class="shrink-0 w-10 h-10 rounded-full bg-white/90 hover:bg-white shadow-md border border-gray-200 flex items-center justify-center text-gray-700 transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
                <div v-if="slides.length > 1" class="flex items-center justify-center gap-2 mt-5">
                    <button v-for="(_, i) in slides" :key="i" type="button" @click="activeIdx = i"
                        :aria-label="`Go to slide ${i + 1}`"
                        :class="['w-2.5 h-2.5 rounded-full transition', i === activeIdx ? 'bg-gray-900' : 'bg-gray-300 hover:bg-gray-400']" />
                </div>
            </div>

            <div v-if="data.cta_label" class="mt-10 text-center">
                <a :href="data.cta_url || '#'"
                    class="inline-flex items-center gap-2 px-8 py-3 rounded-2xl font-black uppercase tracking-wide transition hover:opacity-90"
                    :style="{ backgroundColor: data.btn_bg || '#fbbf24', color: data.btn_text || '#111827' }"
                    v-html="sanitizeHtml(data.cta_label)"
                />
            </div>
        </div>
    </section>

    <!-- Default custom section -->
    <section v-else class="py-20 relative"
        :style="{ backgroundColor: data.bg_color || '#ffffff' }">
        <div class="max-w-3xl mx-auto px-6">
            <h2 v-if="data.title"
                v-html="sanitizeHtml(data.title)"
                :contenteditable="inlineMode ? 'true' : 'false'"
                @focus="inlineMode && $emit('elFocus', $event, `custom_sections.${sectionId}.title`)"
                @blur="inlineMode && $emit('elBlur', $event, `custom_sections.${sectionId}.title`)"
                @keydown.enter.prevent="insertBr"
                :class="['text-3xl sm:text-4xl font-black text-center mb-8', inlineMode ? editableClass : '']"
                :style="{ color: data.text_color || '#111827' }"
            />
            <div v-if="data.text"
                v-html="sanitizeHtml(data.text)"
                :contenteditable="inlineMode ? 'true' : 'false'"
                @focus="inlineMode && $emit('elFocus', $event, `custom_sections.${sectionId}.text`)"
                @blur="inlineMode && $emit('elBlur', $event, `custom_sections.${sectionId}.text`)"
                :class="['text-gray-600 leading-relaxed text-center mb-8', inlineMode ? editableClass : '']"
            />
            <div v-if="data.image_url" class="mb-8">
                <img :src="data.image_url" class="w-full rounded-2xl shadow-lg" />
            </div>
            <div v-if="data.embed_html" class="mb-8">
                <SafeHtmlRenderer :html="data.embed_html" />
            </div>
            <div v-else-if="data.video_url" class="mb-8">
                <div class="aspect-video rounded-2xl overflow-hidden shadow-2xl">
                    <video v-if="data.video_url.includes('.mp4') || data.video_url.includes('.webm') || data.video_url.includes('.mov')"
                        :src="resolveMediaUrl(data.video_url)" controls class="w-full h-full object-cover" />
                    <iframe v-else :src="normalizeVideoUrl(data.video_url)" class="w-full h-full" frameborder="0" allowfullscreen allow="autoplay; encrypted-media" />
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import SafeHtmlRenderer from '@/Components/SafeHtmlRenderer.vue';
import { sanitizeHtml } from '@/utils/sanitize';
import { resolveMediaUrl } from '@/utils/media';

const props = defineProps({
    sectionId: { type: String, required: true },
    data: { type: Object, required: true },
    inlineMode: { type: Boolean, default: false },
    editableClass: { type: String, default: '' },
    normalizeVideoUrl: { type: Function, required: true },
});

defineEmits(['elFocus', 'elBlur']);

const slides = computed(() => Array.isArray(props.data.slides) ? props.data.slides : []);
const activeIdx = ref(0);

watch(slides, (list) => {
    if (activeIdx.value >= list.length) activeIdx.value = Math.max(0, list.length - 1);
});

function prev() {
    if (!slides.value.length) return;
    activeIdx.value = (activeIdx.value - 1 + slides.value.length) % slides.value.length;
}
function next() {
    if (!slides.value.length) return;
    activeIdx.value = (activeIdx.value + 1) % slides.value.length;
}

function insertBr() {
    document.execCommand('insertLineBreak');
}
</script>
