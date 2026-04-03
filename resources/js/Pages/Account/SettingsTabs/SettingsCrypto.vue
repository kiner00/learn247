<template>
    <div class="bg-white border border-gray-200 rounded-2xl p-6 space-y-6">
        <div>
            <h2 class="text-base font-bold text-gray-900 mb-1">Crypto</h2>
            <p class="text-sm text-gray-400">Your CRZ token balance and wallet address for future airdrops.</p>
        </div>

        <!-- CRZ balance card -->
        <div class="bg-linear-to-br from-amber-50 to-yellow-100 border border-amber-200 rounded-2xl p-5 flex items-center gap-4">
            <div class="text-4xl">🪙</div>
            <div>
                <p class="text-xs font-semibold text-amber-700 uppercase tracking-wider mb-0.5">CRZ Token Balance</p>
                <p class="text-3xl font-bold text-amber-900">{{ crzBalance ?? 0 }}</p>
                <p class="text-xs text-amber-600 mt-0.5">Tokens earned from badges &amp; milestones</p>
            </div>
        </div>

        <!-- Wallet address -->
        <form @submit.prevent="saveCrypto" class="max-w-sm space-y-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Crypto Wallet Address</label>
                <input
                    v-model="cryptoForm.crypto_wallet"
                    type="text"
                    placeholder="e.g. 0x1234... or your wallet address"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 font-mono"
                />
                <p class="text-xs text-gray-400 mt-1">This is where your CRZ tokens will be sent during airdrops.</p>
                <p v-if="cryptoForm.errors.crypto_wallet" class="text-xs text-red-500 mt-1">{{ cryptoForm.errors.crypto_wallet }}</p>
            </div>
            <button type="submit" :disabled="cryptoForm.processing"
                    class="w-full py-2.5 bg-amber-400 hover:bg-amber-500 text-white text-sm font-bold rounded-lg transition-colors disabled:opacity-50">
                {{ cryptoForm.processing ? 'Saving...' : 'SAVE WALLET ADDRESS' }}
            </button>
            <p v-if="cryptoForm.recentlySuccessful" class="text-xs text-green-600 font-medium text-center">Saved!</p>
        </form>

        <!-- How to earn info -->
        <div class="border border-gray-100 rounded-xl p-4 space-y-3">
            <p class="text-xs font-bold text-gray-700 uppercase tracking-wider">How to earn CRZ tokens</p>
            <div class="flex items-start gap-3">
                <span class="text-xl">🐦</span>
                <div>
                    <p class="text-sm font-semibold text-gray-800">Early Bird Badge — 1 CRZ</p>
                    <p class="text-xs text-gray-500">Be among the first 100,000 members to achieve 1 affiliate sale.</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <span class="text-xl">🏗️</span>
                <div>
                    <p class="text-sm font-semibold text-gray-800">Early Builder Badge — 10 CRZ</p>
                    <p class="text-xs text-gray-500">Be among the first 1,000 community creators to reach 10 paying members.</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    cryptoWallet: { type: String, default: '' },
    crzBalance:   { type: Number, default: 0 },
});

const cryptoForm = useForm({ crypto_wallet: props.cryptoWallet ?? '' });

function saveCrypto() {
    cryptoForm.patch('/account/settings/crypto', { preserveScroll: true });
}
</script>
