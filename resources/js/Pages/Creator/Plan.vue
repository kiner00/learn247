<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const props = defineProps({
    basicPrice:  { type: Number, default: 499 },
    proPrice:    { type: Number, default: 1999 },
    currentPlan: { type: String, default: 'free' },
});

const notice = ref(null);
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('success') === '1') notice.value = 'success';
    if (params.get('failed')  === '1') notice.value = 'failed';
});

const basicForm = useForm({ plan: 'basic' });
const proForm   = useForm({ plan: 'pro' });

function subscribe(form) {
    form.post('/creator/plan/checkout');
}

const fmt = (n) => Number(n).toLocaleString();

const comparisonRows = [
    { feature: 'Platform Fee',         free: '9.8%',       basic: '4.9%',       pro: '2.9%' },
    { feature: 'Payout Fee',           free: '₱15 flat',   basic: '₱15 flat',   pro: '₱15 flat' },
    { feature: 'Communities',          free: '1',          basic: '3',          pro: 'Unlimited' },
    { feature: 'Courses',              free: 'Up to 3',    basic: 'Up to 5',    pro: 'Unlimited' },
    { feature: 'Pixel / GA Integration', free: false,      basic: true,         pro: true },
    { feature: 'Email Announcement Blast', free: false,    basic: '5,000/mo',   pro: 'More (TBD)' },
    { feature: 'Analytics',            free: 'Basic',      basic: 'Advanced',   pro: 'Advanced' },
    { feature: 'Custom Branding',      free: false,        basic: false,        pro: true },
    { feature: 'Priority Payouts',     free: false,        basic: false,        pro: true },
    { feature: 'AI Landing Page Builder', free: false,     basic: false,        pro: true },
    { feature: 'Custom Domain',        free: false,        basic: false,        pro: '(Soon)' },
    { feature: 'Workflow Builder',     free: false,        basic: false,        pro: '(Soon)' },
    { feature: 'Video Hosting',        free: false,        basic: false,        pro: '(Soon)' },
];

const planLabel = { free: 'Free', basic: 'Basic', pro: 'Pro' };
</script>

<template>
    <AppLayout title="Creator Plans">
        <div class="max-w-5xl mx-auto">

            <!-- Header -->
            <div class="text-center mb-10">
                <h1 class="text-3xl font-black text-gray-900">Choose Your Creator Plan</h1>
                <p class="text-gray-500 mt-2 text-sm">Scale your community business — start free, upgrade anytime</p>
            </div>

            <!-- Notices -->
            <div v-if="notice === 'success'" class="mb-6 bg-green-50 border border-green-200 rounded-2xl px-5 py-4 flex items-center gap-3">
                <span class="text-xl">🎉</span>
                <div>
                    <p class="text-sm font-bold text-green-800">Payment received! Your plan is now active.</p>
                    <p class="text-xs text-green-600 mt-0.5">All features for your plan are now unlocked.</p>
                </div>
            </div>
            <div v-if="notice === 'failed'" class="mb-6 bg-red-50 border border-red-200 rounded-2xl px-5 py-4 flex items-center gap-3">
                <span class="text-xl">❌</span>
                <p class="text-sm font-medium text-red-700">Payment was not completed. Please try again or contact support.</p>
            </div>

            <!-- Current plan banner -->
            <div v-if="currentPlan !== 'free'" class="mb-6 bg-indigo-50 border border-indigo-200 rounded-2xl px-5 py-4 flex items-center gap-3">
                <span class="text-xl">⭐</span>
                <p class="text-sm font-bold text-indigo-800">You're on the <span class="capitalize">{{ currentPlan }}</span> plan. All your features are active.</p>
            </div>

            <!-- Pricing cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-10">

                <!-- Free -->
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm flex flex-col">
                    <div class="px-6 py-6 border-b border-gray-100">
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-2">Free</p>
                        <p class="text-4xl font-black text-gray-900">₱0</p>
                        <p class="text-xs text-gray-400 mt-1">No monthly fee</p>
                    </div>
                    <ul class="px-6 py-5 space-y-2.5 flex-1 text-sm text-gray-600">
                        <li class="flex items-start gap-2"><span class="text-green-500 mt-0.5 shrink-0">✓</span> 1 community</li>
                        <li class="flex items-start gap-2"><span class="text-green-500 mt-0.5 shrink-0">✓</span> Up to 3 courses</li>
                        <li class="flex items-start gap-2"><span class="text-green-500 mt-0.5 shrink-0">✓</span> Member posts &amp; chat</li>
                        <li class="flex items-start gap-2"><span class="text-green-500 mt-0.5 shrink-0">✓</span> 9.8% per transaction</li>
                        <li class="flex items-start gap-2"><span class="text-green-500 mt-0.5 shrink-0">✓</span> ₱15 flat payout fee</li>
                    </ul>
                    <div class="px-6 py-5 border-t border-gray-100">
                        <div class="w-full text-center py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-400 bg-gray-50">
                            {{ currentPlan === 'free' ? 'Current Plan' : 'Free Plan' }}
                        </div>
                    </div>
                </div>

                <!-- Basic -->
                <div class="bg-white border-2 border-blue-400 rounded-2xl overflow-hidden shadow-md flex flex-col relative">
                    <div class="absolute top-4 right-4 bg-blue-100 text-blue-700 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">
                        Popular
                    </div>
                    <div class="px-6 py-6 border-b border-blue-100">
                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-500 mb-2">Basic</p>
                        <p class="text-4xl font-black text-gray-900">₱{{ fmt(basicPrice) }}</p>
                        <p class="text-xs text-gray-400 mt-1">per month · billed monthly</p>
                    </div>
                    <ul class="px-6 py-5 space-y-2.5 flex-1 text-sm text-gray-600">
                        <li class="flex items-start gap-2"><span class="text-blue-500 mt-0.5 shrink-0">✓</span> 3 communities</li>
                        <li class="flex items-start gap-2"><span class="text-blue-500 mt-0.5 shrink-0">✓</span> Up to 5 courses</li>
                        <li class="flex items-start gap-2"><span class="text-blue-500 mt-0.5 shrink-0">✓</span> Pixel / GA integrations</li>
                        <li class="flex items-start gap-2"><span class="text-blue-500 mt-0.5 shrink-0">✓</span> Email blast (5,000/mo)</li>
                        <li class="flex items-start gap-2"><span class="text-blue-500 mt-0.5 shrink-0">✓</span> 4.9% per transaction</li>
                        <li class="flex items-start gap-2"><span class="text-blue-500 mt-0.5 shrink-0">✓</span> ₱15 flat payout fee</li>
                    </ul>
                    <div class="px-6 py-5 border-t border-blue-100">
                        <button
                            v-if="currentPlan !== 'basic'"
                            class="w-full py-3 rounded-xl bg-blue-500 text-white font-bold text-sm hover:bg-blue-600 transition-colors shadow disabled:opacity-50"
                            :disabled="basicForm.processing"
                            @click="subscribe(basicForm)"
                        >
                            {{ basicForm.processing ? 'Redirecting...' : 'Get Basic →' }}
                        </button>
                        <div v-else class="w-full py-3 rounded-xl bg-blue-100 text-blue-700 font-bold text-sm text-center">
                            ⭐ Current Plan
                        </div>
                    </div>
                </div>

                <!-- Pro -->
                <div class="bg-indigo-600 rounded-2xl overflow-hidden shadow-lg flex flex-col relative">
                    <div class="absolute top-4 right-4 bg-amber-400 text-amber-900 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">
                        Best Value
                    </div>
                    <div class="px-6 py-6 border-b border-indigo-500">
                        <p class="text-xs font-semibold uppercase tracking-widest text-indigo-200 mb-2">Pro</p>
                        <p class="text-4xl font-black text-white">₱{{ fmt(proPrice) }}</p>
                        <p class="text-xs text-indigo-200 mt-1">per month · billed monthly</p>
                    </div>
                    <ul class="px-6 py-5 space-y-2.5 flex-1 text-sm text-indigo-100">
                        <li class="flex items-start gap-2"><span class="text-amber-300 mt-0.5 shrink-0">★</span> Unlimited communities</li>
                        <li class="flex items-start gap-2"><span class="text-amber-300 mt-0.5 shrink-0">★</span> Unlimited courses</li>
                        <li class="flex items-start gap-2"><span class="text-amber-300 mt-0.5 shrink-0">★</span> Pixel / GA integrations</li>
                        <li class="flex items-start gap-2"><span class="text-amber-300 mt-0.5 shrink-0">★</span> Email blast (more TBD)</li>
                        <li class="flex items-start gap-2"><span class="text-amber-300 mt-0.5 shrink-0">★</span> 2.9% per transaction</li>
                        <li class="flex items-start gap-2"><span class="text-amber-300 mt-0.5 shrink-0">★</span> ₱15 flat payout fee</li>
                        <li class="flex items-start gap-2"><span class="text-amber-300 mt-0.5 shrink-0">★</span> Custom branding</li>
                        <li class="flex items-start gap-2"><span class="text-amber-300 mt-0.5 shrink-0">★</span> Priority payouts</li>
                        <li class="flex items-start gap-2"><span class="text-amber-300 mt-0.5 shrink-0">★</span> AI Landing Page Builder</li>
                    </ul>
                    <div class="px-6 py-5 border-t border-indigo-500">
                        <div class="w-full py-3 rounded-xl bg-indigo-500 text-indigo-300 font-bold text-sm text-center cursor-not-allowed">
                            Coming Soon
                        </div>
                        <p class="text-xs text-indigo-400 text-center mt-2">Available after MVP launch</p>
                    </div>
                </div>

            </div>

            <!-- Comparison table -->
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">Full Feature Comparison</h2>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide w-2/5">Feature</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Free</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-blue-600 uppercase tracking-wide">Basic</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-indigo-600 uppercase tracking-wide">Pro</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in comparisonRows" :key="row.feature" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-3.5 font-medium text-gray-800">{{ row.feature }}</td>
                            <td class="px-4 py-3.5 text-center">
                                <span v-if="row.free === true"  class="text-green-500 font-bold text-base">✓</span>
                                <span v-else-if="row.free === false" class="text-gray-300 text-lg">—</span>
                                <span v-else class="text-xs text-gray-500 font-medium">{{ row.free }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span v-if="row.basic === true"  class="text-blue-500 font-bold text-base">✓</span>
                                <span v-else-if="row.basic === false" class="text-gray-300 text-lg">—</span>
                                <span v-else class="text-xs text-blue-600 font-semibold">{{ row.basic }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span v-if="row.pro === true"  class="text-indigo-600 font-bold text-base">✓</span>
                                <span v-else-if="row.pro === false" class="text-gray-300 text-lg">—</span>
                                <span v-else class="text-xs text-indigo-600 font-semibold">{{ row.pro }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AppLayout>
</template>
