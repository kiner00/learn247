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
import { usePixel } from '@/composables/usePixel';
import { useTiktokPixel } from '@/composables/useTiktokPixel';
import { useGoogleAnalytics } from '@/composables/useGoogleAnalytics';

const props = defineProps({
    communitySlug:           String,
    communityName:           String,
    pixelId:                 { type: String, default: null },
    tiktokPixelId:           { type: String, default: null },
    googleAnalyticsId:       { type: String, default: null },
    affiliateFbPixelId:      { type: String, default: null },
    affiliateTiktokPixelId:  { type: String, default: null },
    affiliateGaId:           { type: String, default: null },
    amount:                  { type: Number, default: 0 },
    currency:                { type: String, default: 'PHP' },
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
            credentials: 'same-origin',
        });

        if (res.redirected || !res.ok) {
            clearInterval(timer);
            window.location.href = `/communities/${props.communitySlug}`;
            return;
        }

        const data = await res.json();

        if (data.active) {
            confirmed.value = true;
            clearInterval(timer);

            // Fire Purchase across all active trackers before redirecting
            const purchaseParams = {
                value:          props.amount,
                currency:       props.currency,
                content_name:   props.communityName,
                content_type:   'product',
                transaction_id: Date.now().toString(),  // GA4 requires this
            };
            const trackers = [
                props.pixelId           ? usePixel(props.pixelId)                     : null,
                props.tiktokPixelId     ? useTiktokPixel(props.tiktokPixelId)         : null,
                props.googleAnalyticsId ? useGoogleAnalytics(props.googleAnalyticsId) : null,
                // Only add affiliate pixels if they differ from the community's own pixels
                props.affiliateFbPixelId     && props.affiliateFbPixelId     !== props.pixelId           ? usePixel(props.affiliateFbPixelId)               : null,
                props.affiliateTiktokPixelId && props.affiliateTiktokPixelId !== props.tiktokPixelId     ? useTiktokPixel(props.affiliateTiktokPixelId)      : null,
                props.affiliateGaId          && props.affiliateGaId          !== props.googleAnalyticsId ? useGoogleAnalytics(props.affiliateGaId)           : null,
            ].filter(Boolean);
            trackers.forEach(t => { t.init(); t.purchase(purchaseParams); });

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
        window.location.href = `/communities/${props.communitySlug}`;
    }
}

onMounted(() => {
    // First check immediately, then every 2 seconds
    poll();
    timer = setInterval(poll, 2000);
});

onUnmounted(() => clearInterval(timer));
</script>
