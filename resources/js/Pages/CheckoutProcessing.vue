<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div class="text-center max-w-sm w-full">
            <!-- Animated spinner -->
            <div class="mb-6 flex justify-center">
                <div class="w-16 h-16 rounded-full border-4 border-indigo-100 border-t-indigo-600 animate-spin"></div>
            </div>

            <h1 class="text-xl font-bold text-gray-900 mb-2">
                {{ confirmed ? 'You\'re in! 🎉' : 'Confirming your payment…' }}
            </h1>
            <p class="text-gray-500 text-sm">
                {{ confirmed
                    ? `Redirecting you to ${props.communityName}…`
                    : 'This usually takes just a few seconds.' }}
            </p>

            <!-- Subtle progress dots while waiting -->
            <div v-if="!confirmed" class="mt-6 flex justify-center gap-1.5">
                <span
                    v-for="i in 3"
                    :key="i"
                    class="w-2 h-2 rounded-full bg-indigo-300 animate-bounce"
                    :style="{ animationDelay: `${(i - 1) * 0.15}s` }"
                ></span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    communitySlug: String,
    communityName: String,
});

const confirmed = ref(false);
let timer = null;
let attempts = 0;
const MAX_ATTEMPTS = 30; // 30 × 2s = 60s max wait

async function poll() {
    attempts++;

    try {
        const res = await fetch(`/checkout-status/${props.communitySlug}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();

        if (data.active) {
            confirmed.value = true;
            clearInterval(timer);
            // Small delay so user can read the "You're in!" message
            setTimeout(() => {
                router.visit(`/communities/${props.communitySlug}`);
            }, 1200);
            return;
        }
    } catch {
        // Network blip — keep polling
    }

    if (attempts >= MAX_ATTEMPTS) {
        clearInterval(timer);
        // Fallback: just go to the community anyway
        router.visit(`/communities/${props.communitySlug}`);
    }
}

onMounted(() => {
    // First check immediately, then every 2 seconds
    poll();
    timer = setInterval(poll, 2000);
});

onUnmounted(() => clearInterval(timer));
</script>
