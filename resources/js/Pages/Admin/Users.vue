<template>
    <AppLayout title="User Management">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-black text-gray-900">User Management</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ users.total.toLocaleString() }} users total</p>
            </div>
            <Link href="/admin" class="text-sm text-indigo-600 hover:underline">← Dashboard</Link>
        </div>

        <!-- Search -->
        <div class="mb-4">
            <input
                v-model="search"
                type="text"
                placeholder="Search by name, email, or username..."
                class="w-full max-w-md px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                @input="onSearch"
            />
        </div>

        <!-- Table -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">User</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Username</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Communities</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Joined</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">KYC</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="u in users.data" :key="u.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3">
                            <div>
                                <p class="font-medium text-gray-900">{{ u.name }}</p>
                                <p class="text-xs text-gray-400">{{ u.email }}</p>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500">
                            <Link v-if="u.username" :href="`/profile/${u.username}`" class="text-indigo-600 hover:underline">
                                @{{ u.username }}
                            </Link>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-600 font-medium">{{ u.memberships }}</td>
                        <td class="px-5 py-3 text-xs text-gray-400">{{ u.created_at }}</td>
                        <td class="px-5 py-3">
                            <button
                                @click="toggleKyc(u)"
                                :disabled="togglingKyc === u.id"
                                class="text-xs font-bold px-2 py-0.5 rounded-full cursor-pointer transition-colors"
                                :class="u.kyc_verified
                                    ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                    : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                            >
                                {{ togglingKyc === u.id ? '...' : (u.kyc_verified ? 'Verified' : 'Unverified') }}
                            </button>
                        </td>
                        <td class="px-5 py-3">
                            <span v-if="u.is_super_admin" class="text-xs font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-700">Super Admin</span>
                            <span v-else-if="u.is_active" class="text-xs font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Active</span>
                            <span v-else class="text-xs font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700">Disabled</span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <button v-if="!u.is_super_admin"
                                @click="toggleStatus(u)"
                                :disabled="toggling === u.id"
                                class="text-xs font-medium transition-colors disabled:opacity-40"
                                :class="u.is_active ? 'text-red-500 hover:text-red-700' : 'text-green-600 hover:text-green-800'">
                                {{ toggling === u.id ? '...' : (u.is_active ? 'Disable' : 'Enable') }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!users.data.length">
                        <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-400">No users found</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="users.last_page > 1" class="px-5 py-3 border-t border-gray-100 flex justify-center gap-1">
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
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    users:   { type: Object, default: () => ({ data: [], total: 0, last_page: 1, links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const search  = ref(props.filters.search ?? '');
const toggling = ref(null);
const togglingKyc = ref(null);

let timer;
function onSearch() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/admin/users', { search: search.value || undefined }, { preserveState: 'errors', replace: true });
    }, 350);
}

function toggleStatus(user) {
    toggling.value = user.id;
    router.patch(`/admin/users/${user.id}/toggle-status`, {}, {
        preserveScroll: true,
        onFinish: () => { toggling.value = null; },
    });
}

function toggleKyc(user) {
    togglingKyc.value = user.id;
    router.patch(`/admin/users/${user.id}/toggle-kyc`, {}, {
        preserveScroll: true,
        onFinish: () => { togglingKyc.value = null; },
    });
}
</script>
