<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
                @click.self="$emit('close')"
            >
                <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
                    <div class="flex items-center justify-between mb-1">
                        <h2 class="text-lg font-bold text-gray-900">Invite people</h2>
                        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">Invite your friends to {{ communityName }}</p>
                    <div class="flex items-center gap-2">
                        <input
                            :value="inviteUrl"
                            readonly
                            class="flex-1 text-sm px-3 py-2 border border-gray-200 rounded-xl bg-gray-50 font-mono truncate focus:outline-none"
                        />
                        <button
                            @click="copy"
                            class="shrink-0 px-4 py-2 bg-yellow-400 hover:bg-yellow-500 text-gray-900 text-sm font-bold rounded-xl transition-colors"
                        >
                            {{ copied ? 'Copied!' : 'Copy' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    show:          Boolean,
    communityName: String,
    inviteUrl:     String,
});

defineEmits(['close']);

const copied = ref(false);

function copy() {
    navigator.clipboard.writeText(props.inviteUrl).then(() => {
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    });
}
</script>
