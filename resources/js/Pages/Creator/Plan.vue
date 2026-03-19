<template>
    <AppLayout title="Creator Pro Plan">
        <div class="max-w-4xl mx-auto">

            <!-- Header -->
            <div class="text-center mb-10">
                <h1 class="text-3xl font-black text-gray-900">Upgrade to Creator Pro</h1>
                <p class="text-gray-500 mt-2 text-sm">Unlock powerful tools to grow your community faster</p>
            </div>

            <!-- Pricing cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">

                <!-- Free plan -->
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm flex flex-col">
                    <div class="px-6 py-6 border-b border-gray-100">
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-2">Free</p>
                        <p class="text-4xl font-black text-gray-900">₱0</p>
                        <p class="text-xs text-gray-400 mt-1">No monthly fee</p>
                    </div>
                    <ul class="px-6 py-5 space-y-3 flex-1">
                        <li v-for="f in freeFeatures" :key="f.label" class="flex items-start gap-2.5 text-sm text-gray-600">
                            <span class="mt-0.5 text-green-500 shrink-0">✓</span>
                            <span><span class="font-medium text-gray-700">{{ f.label }}:</span> {{ f.value }}</span>
                        </li>
                    </ul>
                    <div class="px-6 py-5 border-t border-gray-100">
                        <div class="w-full text-center py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-400 bg-gray-50 cursor-default">
                            Current Plan
                        </div>
                    </div>
                </div>

                <!-- Pro plan -->
                <div class="bg-indigo-600 rounded-2xl overflow-hidden shadow-lg flex flex-col relative">
                    <!-- Badge -->
                    <div class="absolute top-4 right-4 bg-amber-400 text-amber-900 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">
                        Best Value
                    </div>

                    <div class="px-6 py-6 border-b border-indigo-500">
                        <p class="text-xs font-semibold uppercase tracking-widest text-indigo-200 mb-2">Pro</p>

                        <!-- Price display: strikethrough + discounted -->
                        <div class="flex items-baseline gap-3">
                            <p class="text-2xl font-bold text-indigo-300 line-through">
                                ₱{{ regularFormatted }}
                            </p>
                            <p class="text-4xl font-black text-white">
                                ₱{{ discountedFormatted }}
                            </p>
                        </div>
                        <p class="text-xs text-indigo-200 mt-1">per month · billed monthly</p>
                    </div>

                    <ul class="px-6 py-5 space-y-3 flex-1">
                        <li v-for="f in proFeatures" :key="f.label" class="flex items-start gap-2.5 text-sm text-indigo-100">
                            <span class="mt-0.5 text-amber-300 shrink-0">★</span>
                            <span>
                                <span class="font-semibold text-white">{{ f.label }}</span>
                                <span v-if="f.sub" class="block text-xs text-indigo-200 mt-0.5">{{ f.sub }}</span>
                            </span>
                        </li>
                        <!-- Free features included -->
                        <li class="pt-2 border-t border-indigo-500">
                            <p class="text-xs text-indigo-300 font-semibold mb-2">Everything in Free, plus:</p>
                        </li>
                        <li v-for="f in freeFeatures" :key="f.label" class="flex items-start gap-2.5 text-sm text-indigo-200">
                            <span class="mt-0.5 text-indigo-300 shrink-0">✓</span>
                            <span><span class="font-medium text-indigo-100">{{ f.label }}:</span> {{ f.value }}</span>
                        </li>
                    </ul>

                    <div class="px-6 py-5 border-t border-indigo-500">
                        <button
                            class="w-full py-3 rounded-xl bg-white text-indigo-700 font-bold text-sm hover:bg-indigo-50 transition-colors shadow"
                            @click="subscribe"
                        >
                            Get Creator Pro →
                        </button>
                        <p class="text-xs text-indigo-300 text-center mt-2">Cancel anytime</p>
                    </div>
                </div>

            </div>

            <!-- Feature comparison table -->
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">Full Feature Comparison</h2>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide w-1/2">Feature</th>
                            <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Free</th>
                            <th class="text-center px-6 py-3 text-xs font-semibold text-indigo-600 uppercase tracking-wide">Pro</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in comparisonRows" :key="row.feature" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-3.5">
                                <p class="font-medium text-gray-800">{{ row.feature }}</p>
                                <p v-if="row.sub" class="text-xs text-gray-400 mt-0.5">{{ row.sub }}</p>
                            </td>
                            <td class="px-6 py-3.5 text-center">
                                <span v-if="row.free === true" class="text-green-500 font-bold text-base">✓</span>
                                <span v-else-if="row.free === false" class="text-gray-300 text-lg">—</span>
                                <span v-else class="text-xs text-gray-500 font-medium">{{ row.free }}</span>
                            </td>
                            <td class="px-6 py-3.5 text-center">
                                <span v-if="row.pro === true" class="text-indigo-600 font-bold text-base">✓</span>
                                <span v-else-if="row.pro === false" class="text-gray-300 text-lg">—</span>
                                <span v-else class="text-xs font-semibold text-indigo-600">{{ row.pro }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    regularPrice:    { type: Number, default: 3000 },
    discountedPrice: { type: Number, default: 1999 },
});

const fmt = (n) => Number(n).toLocaleString();
const regularFormatted    = fmt(props.regularPrice);
const discountedFormatted = fmt(props.discountedPrice);

const freeFeatures = [
    { label: 'Community',      value: '1 community' },
    { label: 'Courses',        value: 'Up to 3 courses' },
    { label: 'Analytics',      value: 'Basic stats' },
    { label: 'Communication',  value: 'Member posts & chat' },
    { label: 'Branding',       value: '"Powered by Curzzo" badge' },
    { label: 'Payouts',        value: 'Standard speed' },
];

const proFeatures = [
    { label: 'Communities',              sub: 'Create & manage unlimited communities' },
    { label: 'Unlimited Courses',        sub: 'No cap on course creation' },
    { label: 'Advanced Analytics',       sub: 'Retention & churn insights' },
    { label: 'Email Announcement Blast', sub: 'Broadcast emails to all members' },
    { label: 'Custom Branding',          sub: 'Clean look — remove "Powered by Curzzo" badge' },
    { label: 'Priority Payout Processing', sub: 'Faster payout approvals' },
    { label: 'AI Landing Page UI Builder', sub: 'Build beautiful landing pages with AI' },
    { label: 'Custom Domain',            sub: 'Use your own domain name' },
    { label: 'Custom Email',             sub: 'Send emails from your own address' },
    { label: 'Email Inbox Management',   sub: 'Manage your email inbox in one place' },
    { label: 'Workflow Builder',         sub: 'Tags, automation — like Systeme.io' },
    { label: 'Video Hosting',            sub: 'Upload and host your videos on-site' },
    { label: 'Featured Placement',       sub: 'Place your community in featured courses' },
];

const comparisonRows = [
    { feature: 'Communities',               free: '1',          pro: 'Unlimited' },
    { feature: 'Courses',                   free: 'Up to 3',    pro: 'Unlimited' },
    { feature: 'Analytics',                 free: 'Basic stats', pro: 'Advanced (Retention & Churn)' },
    { feature: 'Communication',             free: 'Member posts & chat', pro: 'Email Announcement Blast' },
    { feature: 'Branding',                  free: '"Powered by Curzzo" badge', pro: 'Custom Branding (Clean look)' },
    { feature: 'Payouts',                   free: 'Standard speed', pro: 'Priority Processing' },
    { feature: 'AI Landing Page UI Builder', free: false,        pro: true },
    { feature: 'Custom Domain',             free: false,        pro: true },
    { feature: 'Custom Email',              free: false,        pro: true },
    { feature: 'Email Inbox Management',    free: false,        pro: true },
    { feature: 'Workflow Builder',          sub: 'Tags, automation', free: false, pro: true },
    { feature: 'Video Hosting',             sub: 'Upload videos on-site', free: false, pro: true },
    { feature: 'Featured Placement',        sub: 'Community in featured courses', free: false, pro: true },
];

function subscribe() {
    // TODO: wire up Xendit checkout in next step
    alert('Checkout coming soon!');
}
</script>
