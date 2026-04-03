<template>
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h2 class="text-base font-bold text-gray-900 mb-6">Theme</h2>
        <div class="max-w-sm space-y-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1.5">Theme</label>
                <div class="relative">
                    <select v-model="themeForm.theme"
                        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white appearance-none pr-8">
                        <option value="light">Light (default)</option>
                        <option value="dark">Dark</option>
                    </select>
                    <svg class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>
            <button @click="saveTheme" :disabled="themeForm.processing"
                class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg tracking-wide transition-colors disabled:opacity-50">
                SAVE
            </button>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    theme: { type: String, default: 'light' },
});

const themeForm = useForm({ theme: props.theme ?? 'light' });

function saveTheme() {
    themeForm.patch('/account/settings/theme', { preserveScroll: true });
}
</script>
