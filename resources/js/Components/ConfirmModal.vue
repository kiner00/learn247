<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="cancel">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">{{ title }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mb-6">{{ message }}</p>
                    <div class="flex justify-end gap-3">
                        <button
                            @click="cancel"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                        >
                            {{ cancelLabel }}
                        </button>
                        <button
                            ref="confirmBtn"
                            @click="confirm"
                            :disabled="processing"
                            class="px-4 py-2 text-sm font-semibold rounded-xl transition-colors disabled:opacity-50"
                            :class="destructive
                                ? 'bg-red-600 text-white hover:bg-red-700'
                                : 'bg-amber-500 text-white hover:bg-amber-600'"
                        >
                            {{ processing ? 'Processing...' : confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel: { type: String, default: 'Cancel' },
    destructive: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);

const confirmBtn = ref(null);

watch(() => props.show, (val) => {
    if (val) nextTick(() => confirmBtn.value?.focus());
});

function confirm() {
    emit('confirm');
}

function cancel() {
    emit('cancel');
}
</script>
