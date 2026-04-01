<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const props = defineProps({
    status: Number,
    title: String,
});

const messages = {
    500: "We're working on getting this fixed. Please try again.",
    503: "We're doing some quick maintenance. We'll be back shortly.",
    404: "The page you're looking for doesn't exist or has been moved.",
    403: "You don't have permission to access this page.",
    419: "Your session has expired. Please refresh the page.",
};

const message = messages[props.status] || 'An unexpected error occurred.';
const showToast = ref(true);

function dismiss() {
    showToast.value = false;
}

function goHome() {
    router.visit('/');
}

function goBack() {
    window.history.back();
}

onMounted(() => {
    setTimeout(() => { showToast.value = false; }, 8000);
});
</script>

<template>
    <AppLayout>
        <!-- Toast notification in upper right -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-x-4"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 translate-x-4"
            >
                <div
                    v-if="showToast"
                    class="fixed top-16 right-4 z-50 pointer-events-auto flex items-start gap-3 px-5 py-4 bg-white dark:bg-gray-800 border border-red-200 dark:border-red-700 rounded-xl shadow-lg max-w-sm"
                >
                    <div class="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-3.5 h-3.5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ status }} — {{ title }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ message }}</p>
                        <div class="flex gap-2 mt-2">
                            <button @click="goHome" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Go home</button>
                            <span class="text-gray-300">|</span>
                            <button @click="goBack" class="text-xs font-medium text-gray-500 hover:text-gray-700">Go back</button>
                        </div>
                    </div>
                    <button @click="dismiss" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </Transition>
        </Teleport>

        <!-- Empty content area so layout renders normally -->
        <div class="min-h-[60vh] flex items-center justify-center">
            <div class="text-center">
                <p class="text-6xl font-black text-gray-200">{{ status }}</p>
                <p class="text-gray-500 mt-2">{{ title }}</p>
                <div class="flex gap-3 justify-center mt-4">
                    <button @click="goHome" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition">Go to homepage</button>
                    <button @click="goBack" class="px-4 py-2 bg-white hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 transition">Go back</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
