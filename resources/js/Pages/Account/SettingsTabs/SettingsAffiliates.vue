<template>
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h2 class="text-base font-bold text-gray-900 mb-1">Affiliates</h2>
        <p class="text-sm text-gray-400 mb-6">Earn commission for life when you invite somebody to create or join a community.</p>

        <!-- Stats row -->
        <div class="flex gap-3 mb-2">
            <div class="flex-1 border border-gray-200 rounded-xl p-4">
                <p class="text-xl font-bold text-gray-900">₱0</p>
                <p class="text-xs text-gray-400 mt-0.5">Last 30 days</p>
            </div>
            <div class="flex-1 border border-gray-200 rounded-xl p-4">
                <p class="text-xl font-bold text-gray-900">₱0</p>
                <p class="text-xs text-gray-400 mt-0.5">Lifetime</p>
            </div>
            <div class="flex-1 border border-gray-200 rounded-xl p-4">
                <p class="text-xl font-bold text-green-600">₱0</p>
                <p class="text-xs text-gray-400 mt-0.5">Account balance</p>
            </div>
            <div class="flex items-center">
                <button disabled class="px-5 py-3 bg-gray-100 text-gray-400 text-sm font-bold rounded-xl cursor-not-allowed tracking-wide">
                    PAYOUT
                </button>
            </div>
        </div>
        <p class="text-xs text-gray-400 mb-6">₱0 available soon</p>

        <!-- Referrals empty state -->
        <div class="border border-gray-100 rounded-xl p-12 text-center mt-6">
            <div class="text-4xl mb-3">💰</div>
            <p class="text-sm text-gray-400">Your referrals will show here</p>
        </div>
    </div>
</template>

<script setup>
defineProps({
    affiliateLink: { type: String, default: '' },
});
</script>
