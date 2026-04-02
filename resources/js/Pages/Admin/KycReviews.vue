<template>
    <AdminLayout title="KYC Reviews">
        <div class="mb-6">
            <h1 class="text-2xl font-black text-gray-900">KYC Reviews</h1>
            <p class="text-sm text-gray-500 mt-0.5">Review identity verification submissions</p>
        </div>

        <!-- Status filter tabs -->
        <div class="flex gap-2 mb-6">
            <Link
                v-for="tab in statusTabs"
                :key="tab.value"
                :href="`/admin/kyc-reviews?status=${tab.value}`"
                class="px-4 py-2 text-sm font-medium rounded-lg border transition-colors"
                :class="filters.status === tab.value
                    ? 'bg-indigo-600 text-white border-indigo-600'
                    : 'border-gray-200 text-gray-600 hover:border-indigo-300'"
            >
                {{ tab.label }}
                <span
                    v-if="counts[tab.value]"
                    class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full"
                    :class="filters.status === tab.value ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-500'"
                >{{ counts[tab.value] }}</span>
            </Link>
        </div>

        <!-- Reviews list -->
        <div v-if="users.data.length" class="space-y-4">
            <div
                v-for="u in users.data"
                :key="u.id"
                class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm"
            >
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="font-semibold text-gray-900">{{ u.name }}</p>
                        <p class="text-xs text-gray-400">{{ u.email }} · @{{ u.username }} · Submitted {{ u.submitted_at }}</p>
                    </div>
                    <span
                        class="text-xs font-bold px-2 py-0.5 rounded-full"
                        :class="{
                            'bg-blue-100 text-blue-700': u.kyc_status === 'submitted',
                            'bg-green-100 text-green-700': u.kyc_status === 'approved',
                            'bg-red-100 text-red-700': u.kyc_status === 'rejected',
                        }"
                    >{{ u.kyc_status }}</span>
                </div>

                <!-- Document previews -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-1.5">Government ID</p>
                        <div
                            class="cursor-pointer rounded-xl border border-gray-200 bg-gray-50 overflow-hidden hover:border-indigo-300 transition-colors"
                            @click="openLightbox(u.kyc_id_document)"
                        >
                            <img :src="u.kyc_id_document" class="w-full h-48 object-contain" />
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-1.5">Selfie with ID</p>
                        <div
                            class="cursor-pointer rounded-xl border border-gray-200 bg-gray-50 overflow-hidden hover:border-indigo-300 transition-colors"
                            @click="openLightbox(u.kyc_selfie)"
                        >
                            <img :src="u.kyc_selfie" class="w-full h-48 object-contain" />
                        </div>
                    </div>
                </div>

                <!-- Rejection reason (if rejected) -->
                <div v-if="u.kyc_status === 'rejected' && u.rejected_reason" class="mb-4 rounded-lg bg-red-50 border border-red-100 px-3 py-2">
                    <p class="text-xs text-red-700"><span class="font-semibold">Rejected:</span> {{ u.rejected_reason }}</p>
                </div>

                <!-- Actions -->
                <div v-if="u.kyc_status === 'submitted'" class="flex items-center gap-3">
                    <button
                        @click="approve(u)"
                        :disabled="processing === u.id"
                        class="px-4 py-2 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors disabled:opacity-50"
                    >
                        Approve
                    </button>
                    <button
                        @click="openReject(u)"
                        :disabled="processing === u.id"
                        class="px-4 py-2 text-sm font-semibold text-red-600 border border-red-200 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50"
                    >
                        Reject
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="bg-white border border-gray-200 rounded-2xl p-10 text-center">
            <p class="text-sm text-gray-400">No {{ filters.status }} KYC submissions</p>
        </div>

        <!-- Pagination -->
        <div v-if="users.last_page > 1" class="mt-6 flex justify-center gap-1">
            <Link
                v-for="link in users.links"
                :key="link.label"
                :href="link.url ?? ''"
                v-html="link.label"
                class="px-2.5 py-1 text-xs rounded-lg border transition-colors"
                :class="link.active
                    ? 'bg-indigo-600 text-white border-indigo-600'
                    : link.url
                        ? 'border-gray-200 text-gray-600 hover:border-indigo-300'
                        : 'border-gray-100 text-gray-300 cursor-default'"
            />
        </div>

        <!-- Lightbox modal -->
        <div v-if="lightboxSrc" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 cursor-pointer" @click="lightboxSrc = null">
            <button @click="lightboxSrc = null" class="absolute top-4 right-4 text-white/80 hover:text-white transition-colors">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <img :src="lightboxSrc" class="max-w-[90vw] max-h-[90vh] object-contain rounded-lg shadow-2xl" @click.stop />
        </div>

        <!-- Reject modal -->
        <div v-if="rejectTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="rejectTarget = null">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
                <h3 class="text-base font-bold text-gray-900 mb-1">Reject KYC</h3>
                <p class="text-sm text-gray-400 mb-4">Provide a reason for rejecting {{ rejectTarget.name }}'s verification.</p>
                <textarea
                    v-model="rejectReason"
                    rows="3"
                    placeholder="e.g. ID photo is blurry, face not visible in selfie..."
                    class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"
                />
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="rejectTarget = null" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                    <button
                        @click="confirmReject"
                        :disabled="!rejectReason.trim() || processing === rejectTarget?.id"
                        class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors disabled:opacity-50"
                    >
                        Reject
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    users:   { type: Object, default: () => ({ data: [], total: 0, last_page: 1, links: [] }) },
    filters: { type: Object, default: () => ({ status: 'submitted' }) },
    counts:  { type: Object, default: () => ({}) },
});

const statusTabs = [
    { value: 'submitted', label: 'Pending' },
    { value: 'approved',  label: 'Approved' },
    { value: 'rejected',  label: 'Rejected' },
];

const processing   = ref(null);
const rejectTarget = ref(null);
const rejectReason = ref('');
const lightboxSrc  = ref(null);

function openLightbox(src) {
    lightboxSrc.value = src;
}

function approve(user) {
    processing.value = user.id;
    router.patch(`/admin/kyc-reviews/${user.id}/approve`, {}, {
        preserveScroll: true,
        onFinish: () => { processing.value = null; },
    });
}

function openReject(user) {
    rejectTarget.value = user;
    rejectReason.value = '';
}

function confirmReject() {
    processing.value = rejectTarget.value.id;
    router.patch(`/admin/kyc-reviews/${rejectTarget.value.id}/reject`, {
        reason: rejectReason.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = null;
            rejectTarget.value = null;
        },
    });
}
</script>
