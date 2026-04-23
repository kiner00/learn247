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

function extractAllowedContent(html) {
    const scripts = [];
    const iframes = [];
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

    tmp.querySelectorAll('iframe').forEach((frame) => {
        const src = frame.getAttribute('src') || '';
        const onload = frame.getAttribute('onload') || '';
        const hasAllowedLoader = SCRIPT_HOST_ALLOWED.test(src) || SCRIPT_HOST_ALLOWED.test(onload.match(/https:\/\/[^\s'"]+/)?.[0] || '');

        if (IFRAME_ALLOWED.test(src) && !onload) return;
        if (!hasAllowedLoader) return;

        const attrs = {};
        for (const attr of frame.attributes) attrs[attr.name] = attr.value;
        const placeholder = document.createElement('div');
        placeholder.setAttribute('data-ifr-placeholder', String(iframes.length));
        iframes.push(attrs);
        frame.replaceWith(placeholder);
    });

    return { cleanedHtml: tmp.innerHTML, scripts, iframes };
}

function renderHtml() {
    if (!container.value) return;
    const raw = props.html ?? '';
    const { cleanedHtml, scripts, iframes } = extractAllowedContent(raw);

    container.value.innerHTML = DOMPurify.sanitize(cleanedHtml, {
        ...purifyConfig,
        ADD_ATTR: [...purifyConfig.ADD_ATTR, 'data-ifr-placeholder'],
    });

    container.value.querySelectorAll('[data-ifr-placeholder]').forEach((ph) => {
        const idx = Number(ph.getAttribute('data-ifr-placeholder'));
        const attrs = iframes[idx];
        if (!attrs) return;
        const frame = document.createElement('iframe');
        Object.entries(attrs).forEach(([name, value]) => {
            if (name === 'onload') {
                frame.onload = new Function(value);
            } else {
                frame.setAttribute(name, value);
            }
        });
        ph.replaceWith(frame);
    });

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

<style>
/* Tailwind Preflight zeroes out p/h/ul/ol margins, so TipTap-saved content
   collapses to a wall of text. Mirror LessonEditor's in-editor typography
   so the saved view matches the edit view. */
.lesson-display p { margin: 0.25rem 0; line-height: 1.6; }
.lesson-display p + p { margin-top: 0.75rem; }
.lesson-display h2 { font-size: 1.1rem; font-weight: 700; margin: 0.75rem 0 0.25rem; }
.lesson-display h3 { font-size: 0.95rem; font-weight: 600; margin: 0.6rem 0 0.2rem; }
.lesson-display ul { list-style-type: disc; padding-left: 1.25rem; margin: 0.4rem 0; }
.lesson-display ol { list-style-type: decimal; padding-left: 1.25rem; margin: 0.4rem 0; }
.lesson-display li { margin: 0.15rem 0; }
.lesson-display strong { font-weight: 700; }
.lesson-display em { font-style: italic; }
.lesson-display a { color: #4f46e5; text-decoration: underline; }
.lesson-display code { background: #f3f4f6; padding: 0.1em 0.35em; border-radius: 4px; font-size: 0.85em; }
.lesson-display pre { background: #1e1e2e; color: #cdd6f4; padding: 1rem; border-radius: 8px; overflow-x: auto; margin: 0.5rem 0; }
.lesson-display pre code { background: none; padding: 0; color: inherit; font-size: 0.85rem; }
.lesson-display img { max-width: 100%; height: auto; border-radius: 8px; margin: 0.5rem 0; }
.lesson-display blockquote { border-left: 3px solid #e5e7eb; padding-left: 0.75rem; margin: 0.5rem 0; color: #6b7280; }
</style>
