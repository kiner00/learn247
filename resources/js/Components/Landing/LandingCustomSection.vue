<template>
    <section class="py-20 relative"
        :style="{ backgroundColor: data.bg_color || '#ffffff' }">
        <div class="max-w-3xl mx-auto px-6">
            <h2 v-if="data.title"
                v-html="sanitizeHtml(data.title)"
                :contenteditable="inlineMode ? 'true' : 'false'"
                @focus="inlineMode && $emit('elFocus', $event, `custom_sections.${sectionId}.title`)"
                @blur="inlineMode && $emit('elBlur', $event, `custom_sections.${sectionId}.title`)"
                @keydown.enter.prevent
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
                        :src="data.video_url" controls class="w-full h-full object-cover" />
                    <iframe v-else :src="normalizeVideoUrl(data.video_url)" class="w-full h-full" frameborder="0" allowfullscreen allow="autoplay; encrypted-media" />
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import SafeHtmlRenderer from '@/Components/SafeHtmlRenderer.vue';
import { sanitizeHtml } from '@/utils/sanitize';

defineProps({
    sectionId: { type: String, required: true },
    data: { type: Object, required: true },
    inlineMode: { type: Boolean, default: false },
    editableClass: { type: String, default: '' },
    normalizeVideoUrl: { type: Function, required: true },
});

defineEmits(['elFocus', 'elBlur']);
</script>
