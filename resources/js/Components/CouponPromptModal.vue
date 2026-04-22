<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: { type: Boolean, default: false },
    plan: { type: String, default: null },
    cycle: { type: String, default: null },
    originalPrice: { type: Number, default: 0 },
});

const emit = defineEmits(['proceed', 'cancel']);

const code = ref('');
const error = ref(null);
const loading = ref(false);
const applied = ref(null); // { discount_percent, discounted_price, savings, code }

watch(() => props.show, (v) => {
    if (!v) {
        code.value = '';
        error.value = null;
        applied.value = null;
        loading.value = false;
    }
});

const finalPrice = computed(() => applied.value?.discounted_price ?? props.originalPrice);

const cycleLabel = computed(() => props.cycle === 'annual' ? 'yearly' : 'monthly');
const fmt = (n) => Number(n).toLocaleString('en-PH', { maximumFractionDigits: 2 });

async function apply() {
    if (!code.value.trim()) {
        error.value = 'Enter a coupon code or skip.';
        return;
    }
    loading.value = true;
    error.value = null;
    try {
        const { data } = await axios.post('/creator/plan/validate-coupon', {
            code: code.value.trim().toUpperCase(),
            plan: props.plan,
            cycle: props.cycle,
        });
        applied.value = {
            code: data.code,
            discount_percent: data.discount_percent,
            discounted_price: data.discounted_price,
            savings: data.savings,
        };
    } catch (e) {
        error.value = e.response?.data?.errors?.code?.[0]
            || e.response?.data?.message
            || 'Could not apply this coupon.';
        applied.value = null;
    } finally {
        loading.value = false;
    }
}

function removeApplied() {
    applied.value = null;
    code.value = '';
    error.value = null;
}

function proceed() {
    emit('proceed', { couponCode: applied.value?.code ?? null });
}
</script>

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
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="emit('cancel')">
                <div class="bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900">Have a promo code?</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Apply a coupon or skip to continue with regular pricing.</p>
                    </div>

                    <div class="px-6 py-5 space-y-4">
                        <!-- Applied state -->
                        <div v-if="applied" class="bg-green-50 border border-green-200 rounded-xl px-4 py-3">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-green-700 uppercase tracking-wide">Coupon Applied</p>
                                    <p class="text-sm font-bold text-green-900 mt-0.5">{{ applied.code }}</p>
                                    <p class="text-xs text-green-700 mt-1">{{ applied.discount_percent }}% off · You save ₱{{ fmt(applied.savings) }}</p>
                                </div>
                                <button @click="removeApplied" class="text-xs text-green-700 hover:text-green-900 font-medium underline underline-offset-2">
                                    Remove
                                </button>
                            </div>
                        </div>

                        <!-- Input state -->
                        <div v-else>
                            <div class="flex items-stretch gap-2">
                                <input
                                    v-model="code"
                                    type="text"
                                    placeholder="ENTER COUPON CODE"
                                    @keydown.enter.prevent="apply"
                                    class="flex-1 border border-gray-200 rounded-xl px-3 py-2.5 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-indigo-300"
                                />
                                <button
                                    type="button"
                                    @click="apply"
                                    :disabled="loading"
                                    class="px-4 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                                >
                                    {{ loading ? '...' : 'Apply' }}
                                </button>
                            </div>
                            <p v-if="error" class="text-xs text-red-500 mt-2">{{ error }}</p>
                        </div>

                        <!-- Price summary -->
                        <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                            <div class="flex items-baseline justify-between">
                                <span class="text-xs text-gray-500 uppercase tracking-wide">You'll pay ({{ cycleLabel }})</span>
                                <div class="flex items-baseline gap-2">
                                    <span v-if="applied" class="text-sm text-gray-400 line-through">₱{{ fmt(originalPrice) }}</span>
                                    <span class="text-lg font-black text-gray-900">₱{{ fmt(finalPrice) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="emit('cancel')"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="proceed"
                            class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition-colors"
                        >
                            {{ applied ? 'Continue to Checkout' : 'Skip & Continue' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
