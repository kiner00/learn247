<template>
    <AdminLayout title="Coupons">
        <div class="mb-6">
            <h1 class="text-2xl font-black text-gray-900">Coupon Management</h1>
            <p class="text-sm text-gray-500 mt-0.5">Create and manage creator plan coupons</p>
        </div>

        <!-- Create coupon form -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm mb-8">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-bold text-gray-900">Create New Coupon</h2>
            </div>
            <form @submit.prevent="createCoupon" class="px-5 py-4 flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Coupon Code</label>
                    <input
                        v-model="form.code"
                        type="text"
                        placeholder="e.g. LAUNCH2026"
                        class="w-44 border border-gray-200 rounded-xl px-3 py-2 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    />
                    <p v-if="form.errors.code" class="text-xs text-red-500 mt-1">{{ form.errors.code }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Plan</label>
                    <select
                        v-model="form.plan"
                        class="w-32 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    >
                        <option value="basic">Basic</option>
                        <option value="pro">Pro</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Duration (months)</label>
                    <input
                        v-model.number="form.duration_months"
                        type="number" min="1" max="36"
                        class="w-28 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Max Redemptions</label>
                    <input
                        v-model.number="form.max_redemptions"
                        type="number" min="1"
                        class="w-28 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Expires At <span class="text-gray-400">(optional)</span></label>
                    <input
                        v-model="form.expires_at"
                        type="date"
                        class="w-40 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                >
                    Create Coupon
                </button>
            </form>
        </div>

        <!-- Coupons table -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Code</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Plan</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Duration</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Redeemed</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Expires</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Created</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="c in coupons" :key="c.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3 font-mono font-bold text-gray-900">{{ c.code }}</td>
                        <td class="px-5 py-3">
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full uppercase"
                                :class="c.plan === 'pro' ? 'bg-indigo-100 text-indigo-700' : 'bg-blue-100 text-blue-700'">
                                {{ c.plan }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ c.duration_months }} mo</td>
                        <td class="px-5 py-3">
                            <span class="text-sm font-semibold" :class="c.times_redeemed >= c.max_redemptions ? 'text-red-500' : 'text-gray-700'">
                                {{ c.times_redeemed }} / {{ c.max_redemptions }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ c.expires_at ?? 'Never' }}</td>
                        <td class="px-5 py-3">
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full"
                                :class="c.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                                {{ c.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-400">{{ c.created_at }}</td>
                        <td class="px-5 py-3 text-right space-x-2">
                            <button
                                @click="toggleCoupon(c)"
                                class="text-xs font-medium transition-colors"
                                :class="c.is_active ? 'text-orange-500 hover:text-orange-700' : 'text-green-600 hover:text-green-800'">
                                {{ c.is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                            <button
                                v-if="c.times_redeemed === 0"
                                @click="deleteCoupon(c)"
                                class="text-xs font-medium text-red-500 hover:text-red-700 transition-colors">
                                Delete
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!coupons.length">
                        <td colspan="8" class="px-5 py-10 text-center text-sm text-gray-400">No coupons yet</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    coupons: { type: Array, default: () => [] },
});

const form = useForm({
    code: '',
    plan: 'pro',
    duration_months: 1,
    max_redemptions: 1,
    expires_at: '',
});

function createCoupon() {
    form.transform((data) => ({
        ...data,
        expires_at: data.expires_at || null,
    })).post('/admin/coupons', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function toggleCoupon(coupon) {
    router.post(`/admin/coupons/${coupon.id}/toggle`, {}, { preserveScroll: true });
}

function deleteCoupon(coupon) {
    if (!confirm(`Delete coupon ${coupon.code}?`)) return;
    router.delete(`/admin/coupons/${coupon.id}`, { preserveScroll: true });
}
</script>
