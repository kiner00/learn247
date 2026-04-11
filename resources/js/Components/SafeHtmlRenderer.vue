<template>
    <div ref="container" class="lesson-display text-sm text-gray-700 leading-relaxed mb-6" />
</template>

<script setup>
import { ref, watch, onMounted, nextTick } from 'vue';
import DOMPurify from 'dompurify';

const props = defineProps({ html: String });

const container = ref(null);

// Allow iframes for YouTube/Vimeo embeds only
const purifyConfig = {
    ADD_TAGS: ['iframe'],
    ADD_ATTR: ['allow', 'allowfullscreen', 'frameborder', 'scrolling'],
    ALLOWED_URI_REGEXP: /^(?:(?:https?|mailto):|[^a-z]|[a-z+.-]+(?:[^a-z+.\-:]|$))/i,
};

DOMPurify.addHook('uponSanitizeElement', (node) => {
    if (node.tagName === 'IFRAME') {
        const src = node.getAttribute('src') || '';
        const allowed = /^https:\/\/(www\.)?(youtube\.com|youtube-nocookie\.com|player\.vimeo\.com|fast\.wistia\.(com|net))\//;
        if (!allowed.test(src)) {
            node.remove();
        }
    }
});

function renderHtml() {
    if (!container.value) return;
    container.value.innerHTML = DOMPurify.sanitize(props.html ?? '', purifyConfig);
}

onMounted(() => nextTick(renderHtml));
watch(() => props.html, () => nextTick(renderHtml));
</script>
