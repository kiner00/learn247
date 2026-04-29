<template>
    <AdminLayout title="Creator Plan Affiliate Applications">
        <div class="mb-6">
            <h1 class="text-2xl font-black text-gray-900">Creator Plan Affiliate Applications</h1>
            <p class="text-sm text-gray-500 mt-0.5">Review users requesting to be Creator Plan affiliates</p>
        </div>

        <!-- Status filter tabs -->
        <div class="flex gap-2 mb-6">
            <Link
                v-for="tab in statusTabs"
                :key="tab.value"
                :href="`/admin/creator-plan-affiliates?status=${tab.value}`"
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

        <!-- Applications list -->
        <div v-if="applications.data.length" class="space-y-4">
            <div
                v-for="app in applications.data"
                :key="app.id"
                class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm"
            >
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <img v-if="app.user.avatar" :src="app.user.avatar" class="h-10 w-10 rounded-full object-cover" />
                        <div v-else class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-semibold">
                            {{ app.user.name.charAt(0) }}
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">{{ app.user.name }}</p>
                            <p class="text-xs text-gray-400">{{ app.user.email }} · @{{ app.user.username }} · Submitted {{ app.created_at }}</p>
                        </div>
                    </div>
                    <span
                        class="text-xs font-bold px-2 py-0.5 rounded-full"
                        :class="{
                            'bg-blue-100 text-blue-700': app.status === 'pending',
                            'bg-green-100 text-green-700': app.status === 'approved',
                            'bg-red-100 text-red-700': app.status === 'rejected',
                        }"
                    >{{ app.status }}</span>
                </div>

                <!-- Pitch -->
                <div v-if="app.pitch" class="mb-4 rounded-lg bg-gray-50 border border-gray-100 px-3 py-2.5">
                    <p class="text-xs font-medium text-gray-500 mb-1">Pitch</p>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ app.pitch }}</p>
                </div>
                <p v-else class="mb-4 text-xs italic text-gray-400">No pitch provided.</p>

                <!-- Rejection reason -->
                <div v-if="app.status === 'rejected' && app.rejection_reason" class="mb-4 rounded-lg bg-red-50 border border-red-100 px-3 py-2">
                    <p class="text-xs text-red-700"><span class="font-semibold">Rejected:</span> {{ app.rejection_reason }}</p>
                </div>

                <!-- Actions -->
                <div v-if="app.status === 'pending'" class="flex items-center gap-3">
                    <button
                        @click="approve(app)"
                        :disabled="processing === app.id"
                        class="px-4 py-2 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors disabled:opacity-50"
                    >
                        Approve
                    </button>
                    <button
                        @click="openReject(app)"
                        :disabled="processing === app.id"
                        class="px-4 py-2 text-sm font-semibold text-red-600 border border-red-200 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50"
                    >
                        Reject
                    </button>
                </div>

                <p v-else-if="app.reviewed_at" class="text-xs text-gray-400">Reviewed {{ app.reviewed_at }}</p>
            </div>
        </div>

        <div v-else class="bg-white border border-gray-200 rounded-2xl p-10 text-center">
            <p class="text-sm text-gray-400">No {{ filters.status }} applications</p>
        </div>

        <!-- Pagination -->
        <div v-if="applications.last_page > 1" class="mt-6 flex justify-center gap-1">
            <Link
                v-for="link in applications.links"
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

        <!-- Reject modal -->
        <div v-if="rejectTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="rejectTarget = null">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
                <h3 class="text-base font-bold text-gray-900 mb-1">Reject Application</h3>
                <p class="text-sm text-gray-400 mb-4">Tell {{ rejectTarget.user.name }} why their application was rejected.</p>
                <textarea
                    v-model="rejectReason"
                    rows="3"
                    placeholder="e.g. Account too new, audience not aligned with creator plan..."
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
    applications: { type: Object, default: () => ({ data: [], total: 0, last_page: 1, links: [] }) },
    filters:      { type: Object, default: () => ({ status: 'pending' }) },
    counts:       { type: Object, default: () => ({}) },
});

const statusTabs = [
    { value: 'pending',  label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
];

const processing   = ref(null);
const rejectTarget = ref(null);
const rejectReason = ref('');

function approve(app) {
    processing.value = app.id;
    router.patch(`/admin/creator-plan-affiliates/${app.id}/approve`, {}, {
        preserveScroll: true,
        onFinish: () => { processing.value = null; },
    });
}

function openReject(app) {
    rejectTarget.value = app;
    rejectReason.value = '';
}

function confirmReject() {
    processing.value = rejectTarget.value.id;
    router.patch(`/admin/creator-plan-affiliates/${rejectTarget.value.id}/reject`, {
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
