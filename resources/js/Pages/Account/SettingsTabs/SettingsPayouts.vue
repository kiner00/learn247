<template>
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h2 class="text-base font-bold text-gray-900 mb-1">Payout Settings</h2>
        <p class="text-sm text-gray-400 mb-6">
            Set where you want to receive your earnings as a community owner.
            This applies to all communities you own.
        </p>

        <div v-if="!isKycVerified" class="mb-6 rounded-xl border p-4"
             :class="kycStatus === 'rejected' ? 'border-red-200 bg-red-50' : kycStatus === 'submitted' ? 'border-blue-200 bg-blue-50' : 'border-amber-200 bg-amber-50'">
            <div class="flex items-start gap-3">
                <div class="flex-1">
                    <p class="text-sm font-bold"
                       :class="kycStatus === 'rejected' ? 'text-red-800' : kycStatus === 'submitted' ? 'text-blue-800' : 'text-amber-800'">
                        {{ kycBannerTitle }}
                    </p>
                    <p class="text-xs mt-1"
                       :class="kycStatus === 'rejected' ? 'text-red-700' : kycStatus === 'submitted' ? 'text-blue-700' : 'text-amber-700'">
                        {{ kycBannerMessage }}
                    </p>
                </div>
                <button v-if="kycStatus !== 'submitted'" type="button" @click="$emit('go-to-kyc')"
                        class="shrink-0 px-3 py-1.5 text-xs font-bold rounded-lg transition-colors"
                        :class="kycStatus === 'rejected' ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-amber-600 hover:bg-amber-700 text-white'">
                    {{ kycStatus === 'rejected' ? 'Resubmit' : 'Verify now' }}
                </button>
            </div>
        </div>

        <form @submit.prevent="savePayout" class="max-w-sm space-y-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Payout Method</label>
                <select v-model="payoutForm.payout_method"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="gcash">GCash</option>
                    <option value="maya">Maya</option>
                    <option value="bank">Bank Transfer</option>
                    <option value="paypal">PayPal</option>
                </select>
            </div>
            <div v-if="payoutForm.payout_method === 'bank'">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Bank</label>
                <select v-model="payoutForm.bank_name"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Select Bank --</option>
                    <option v-for="b in PH_BANKS" :key="b.code" :value="b.code">{{ b.name }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">
                    {{ payoutForm.payout_method === 'bank' ? 'Account Number' : 'Account / Mobile Number' }}
                </label>
                <input v-model="payoutForm.payout_details" type="text"
                       :placeholder="payoutForm.payout_method === 'bank' ? 'e.g. 1234567890' : 'e.g. 09xxxxxxxxx'"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <button type="submit" :disabled="payoutForm.processing"
                    class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg transition-colors disabled:opacity-50">
                {{ payoutForm.processing ? 'Saving...' : 'SAVE PAYOUT DETAILS' }}
            </button>
            <p v-if="payoutForm.recentlySuccessful" class="text-xs text-green-600 font-medium text-center">Saved!</p>
        </form>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    payoutMethod:  { type: String, default: 'gcash' },
    payoutDetails: { type: String, default: '' },
    bankName:      { type: String, default: '' },
    kyc:           { type: Object, default: () => ({ status: 'none' }) },
});

defineEmits(['go-to-kyc']);

const kycStatus = computed(() => props.kyc?.status ?? 'none');
const isKycVerified = computed(() => kycStatus.value === 'approved');

const kycBannerTitle = computed(() => {
    switch (kycStatus.value) {
        case 'submitted': return 'Identity verification in review';
        case 'rejected':  return 'Identity verification was rejected';
        default:          return 'Identity verification required';
    }
});

const kycBannerMessage = computed(() => {
    switch (kycStatus.value) {
        case 'submitted': return 'We\'re reviewing your documents. You\'ll be able to request payouts once approved.';
        case 'rejected':  return props.kyc?.rejected_reason || 'Please resubmit your ID and selfie for verification.';
        default:          return 'You must complete KYC verification before you can request payouts.';
    }
});

const PH_BANKS = [
    { code: 'PH_BDO',     name: 'BDO Unibank' },
    { code: 'PH_BPI',     name: 'Bank of the Philippine Islands (BPI)' },
    { code: 'PH_MET',     name: 'Metrobank' },
    { code: 'PH_UBP',     name: 'UnionBank of the Philippines' },
    { code: 'PH_PNB',     name: 'Philippine National Bank (PNB)' },
    { code: 'PH_RCBC',    name: 'RCBC' },
    { code: 'PH_CBC',     name: 'China Banking Corporation' },
    { code: 'PH_CBS',     name: 'China Bank Savings' },
    { code: 'PH_EWB',     name: 'EastWest Bank' },
    { code: 'PH_SEC',     name: 'Security Bank' },
    { code: 'PH_LBP',     name: 'Land Bank of the Philippines' },
    { code: 'PH_DBP',     name: 'Development Bank of the Philippines (DBP)' },
    { code: 'PH_PSB',     name: 'PSBank' },
    { code: 'PH_ROB',     name: 'Robinsons Bank' },
    { code: 'PH_PBC',     name: 'Philippine Bank of Communications (PBCOM)' },
    { code: 'PH_PBB',     name: 'Philippine Business Bank' },
    { code: 'PH_AUB',     name: 'Asia United Bank (AUB)' },
    { code: 'PH_BOC',     name: 'Bank of Commerce' },
    { code: 'PH_MPI',     name: 'Maybank Philippines' },
    { code: 'PH_ONB',     name: 'BDO Network Bank' },
    { code: 'PH_MAYA',    name: 'Maya Bank' },
    { code: 'PH_CIMB',    name: 'CIMB Bank Philippines' },
    { code: 'PH_TONIK',   name: 'Tonik Digital Bank' },
    { code: 'PH_GOTYME',  name: 'GoTyme Bank' },
    { code: 'PH_SEA',     name: 'SeaBank Philippines' },
    { code: 'PH_UNO',     name: 'UNObank' },
    { code: 'PH_UDP',     name: 'Union Digital Bank' },
    { code: 'PH_HSBC',    name: 'HSBC' },
    { code: 'PH_CITI',    name: 'Citibank' },
    { code: 'PH_SCB',     name: 'Standard Chartered Bank' },
];

const payoutForm = useForm({
    payout_method:  props.payoutMethod  ?? 'gcash',
    payout_details: props.payoutDetails ?? '',
    bank_name:      props.bankName      ?? '',
});

function savePayout() {
    payoutForm.patch('/account/settings/payout', { preserveScroll: true });
}
</script>
