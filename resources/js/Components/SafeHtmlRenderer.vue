<template>
    <div ref="container" class="lesson-display text-sm text-gray-700 leading-relaxed mb-6" />
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';

const props = defineProps({ html: String });

const container = ref(null);
const injectedScripts = [];

function renderHtml() {
    if (!container.value) return;

    // Clear previous scripts
    injectedScripts.forEach(s => s.remove());
    injectedScripts.length = 0;

    container.value.innerHTML = props.html ?? '';

    // Re-execute any <script> tags (v-html silently drops them)
    container.value.querySelectorAll('script').forEach(oldScript => {
        const newScript = document.createElement('script');
        [...oldScript.attributes].forEach(attr => newScript.setAttribute(attr.name, attr.value));
        newScript.textContent = oldScript.textContent;
        oldScript.replaceWith(newScript);
        injectedScripts.push(newScript);
    });
}

onMounted(() => nextTick(renderHtml));
watch(() => props.html, () => nextTick(renderHtml));

onBeforeUnmount(() => {
    injectedScripts.forEach(s => s.remove());
    injectedScripts.length = 0;
});
</script>
