<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';

const props = defineProps({
    community: Object,
});

const page = usePage();
const creatorPlan = computed(() => page.props.auth.user?.creator_plan ?? 'free');
const platformFeeRate = computed(() => {
    if (creatorPlan.value === 'pro')   return 0.029;
    if (creatorPlan.value === 'basic') return 0.049;
    return 0.098;
});

const affiliateSaved = ref(false);

const affiliateForm = useForm({
    name:                      props.community.name,
    affiliate_commission_rate: props.community.affiliate_commission_rate ?? '',
});

function saveAffiliate() {
    affiliateForm.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            onSuccess: () => {
                affiliateSaved.value = true;
                setTimeout(() => (affiliateSaved.value = false), 3000);
            },
        });
}
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <div class="bg-white border border-gray-200 rounded-2xl p-6">
            <div class="flex items-center justify-between mb-1">
                <h2 class="text-base font-semibold text-gray-900">Affiliate Program</h2>
                <Link :href="`/communities/${community.slug}/affiliates`"
                      class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    View affiliates →
                </Link>
            </div>
            <p class="text-sm text-gray-500 mb-5">
                Members can become affiliates and earn a commission for every new subscriber they refer.
                The platform takes {{ (platformFeeRate * 100).toFixed(1) }}% off the top.
            </p>
            <form @submit.prevent="saveAffiliate">
                <div class="flex items-end gap-4">
                    <div class="flex-1 max-w-xs">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Affiliate Commission Rate (%)
                        </label>
                        <input
                            v-model="affiliateForm.affiliate_commission_rate"
                            type="number"
                            min="0"
                            max="85"
                            step="1"
                            placeholder="e.g. 50"
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            :class="affiliateForm.errors.affiliate_commission_rate ? 'border-red-400' : ''"
                        />
                        <p class="mt-1 text-xs text-gray-400">
                            0 = disable affiliate program. Max 85 (platform takes {{ (platformFeeRate * 100).toFixed(1) }}%).
                        </p>
                        <p v-if="affiliateForm.errors.affiliate_commission_rate" class="mt-1 text-xs text-red-600">
                            {{ affiliateForm.errors.affiliate_commission_rate }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        :disabled="affiliateForm.processing"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                        Save
                    </button>
                    <p v-if="affiliateSaved" class="text-sm text-green-600 self-center">Saved!</p>
                </div>
                <div v-if="community.affiliate_commission_rate" class="mt-3 p-3 bg-gray-50 rounded-lg text-xs text-gray-500">
                    Example split on ₱{{ community.price }} sale:
                    <strong class="text-red-500">Platform ₱{{ (community.price * platformFeeRate).toFixed(2) }}</strong>
                    · <strong class="text-orange-600">Affiliate ₱{{ (community.price * community.affiliate_commission_rate / 100).toFixed(2) }}</strong>
                    · <strong class="text-green-700">You ₱{{ (community.price - community.price * platformFeeRate - community.price * community.affiliate_commission_rate / 100).toFixed(2) }}</strong>
                </div>
            </form>
        </div>
    </CommunitySettingsLayout>
</template>
