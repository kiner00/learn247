<script setup>
import { computed, toRef } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { usePricingLabel } from '@/composables/usePricingLabel.js';

const props = defineProps({
    community: { type: Object, required: true },
});

const priceNote = usePricingLabel(toRef(props, 'community'));

const firstCharge = computed(() => {
    const promo = props.community.first_month_price;
    const regular = Number(props.community.price) || 0;
    const amount = promo !== null && promo !== undefined && promo !== ''
        ? Number(promo)
        : regular;
    const currency = props.community.currency || 'PHP';
    return `${currency} ${amount.toLocaleString()}`;
});

const checkoutForm = useForm({});

function startCheckout() {
    checkoutForm.post(`/communities/${props.community.slug}/checkout`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="rounded-2xl border border-amber-300 bg-amber-50 p-5 shadow-sm">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 shrink-0 rounded-full bg-amber-200 flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-bold text-amber-900">Your free trial has ended</h3>
                <p class="mt-1 text-sm text-amber-800">
                    Continue your membership to keep posting, chatting, and accessing classroom content.
                </p>
                <p class="mt-2 text-sm font-medium text-amber-900">{{ priceNote }}</p>
            </div>
        </div>

        <div class="mt-4 flex items-center gap-3">
            <button
                type="button"
                :disabled="checkoutForm.processing"
                @click="startCheckout"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-semibold hover:bg-amber-600 disabled:opacity-60 transition-colors"
            >
                {{ checkoutForm.processing ? 'Redirecting…' : `Continue for ${firstCharge}` }}
            </button>
            <Link :href="`/communities/${community.slug}/about`" class="text-sm text-amber-800 hover:text-amber-900 underline">
                Learn more
            </Link>
        </div>
    </div>
</template>
