<template>
    <div ref="container" class="lesson-display text-sm text-gray-700 leading-relaxed mb-6" />
</template>

<script setup>
import { ref, watch, onMounted, nextTick } from 'vue';
import DOMPurify from 'dompurify';

const props = defineProps({ html: String });

const container = ref(null);

const IFRAME_ALLOWED = /^https:\/\/(www\.)?(youtube\.com|youtube-nocookie\.com|player\.vimeo\.com|fast\.wistia\.(com|net)|scripts\.converteai\.net|embed\.tella\.tv|senja\.io|widget\.senja\.io|embed-v2\.testimonial\.to|embed\.testimonial\.to)\//;

const SCRIPT_HOST_ALLOWED = /^https:\/\/(scripts\.converteai\.net|cdn\.converteai\.net|fast\.wistia\.com|fast\.wistia\.net|player\.vimeo\.com|www\.youtube\.com|widget\.senja\.io|static\.senja\.io|embed\.testimonial\.to|embed-v2\.testimonial\.to|embed\.tella\.tv|cdn\.jsdelivr\.net|unpkg\.com)\//;

const purifyConfig = {
    ADD_TAGS: ['iframe'],
    ADD_ATTR: ['allow', 'allowfullscreen', 'frameborder', 'scrolling'],
    ALLOWED_URI_REGEXP: /^(?:(?:https?|mailto):|[^a-z]|[a-z+.-]+(?:[^a-z+.\-:]|$))/i,
};

DOMPurify.addHook('uponSanitizeElement', (node) => {
    if (node.tagName === 'IFRAME') {
        const src = node.getAttribute('src') || '';
        if (!IFRAME_ALLOWED.test(src)) node.remove();
    }
});

function extractAllowedScripts(html) {
    const scripts = [];
    const tmp = document.createElement('div');
    tmp.innerHTML = html ?? '';
    tmp.querySelectorAll('script').forEach((s) => {
        const src = s.getAttribute('src') || '';
        if (src) {
            if (SCRIPT_HOST_ALLOWED.test(src)) {
                scripts.push({
                    src,
                    type: s.getAttribute('type') || '',
                    async: s.async || s.hasAttribute('async'),
                    defer: s.defer || s.hasAttribute('defer'),
                    dataset: { ...s.dataset },
                });
            }
        } else {
            const text = s.textContent ?? '';
            if (text.trim()) {
                scripts.push({ inline: text, type: s.getAttribute('type') || '' });
            }
        }
        s.remove();
    });
    return { cleanedHtml: tmp.innerHTML, scripts };
}

function renderHtml() {
    if (!container.value) return;
    const raw = props.html ?? '';
    const { cleanedHtml, scripts } = extractAllowedScripts(raw);

    container.value.innerHTML = DOMPurify.sanitize(cleanedHtml, purifyConfig);

    scripts.forEach((meta) => {
        const el = document.createElement('script');
        if (meta.src) {
            el.src = meta.src;
            if (meta.type) el.type = meta.type;
            if (meta.async) el.async = true;
            if (meta.defer) el.defer = true;
            Object.entries(meta.dataset || {}).forEach(([k, v]) => {
                el.dataset[k] = v;
            });
        } else {
            if (meta.type) el.type = meta.type;
            el.textContent = meta.inline;
        }
        container.value.appendChild(el);
    });
}

onMounted(() => nextTick(renderHtml));
watch(() => props.html, () => nextTick(renderHtml));
</script>
