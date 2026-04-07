<template>
    <AdminLayout title="Support Tickets">
        <div class="mb-6">
            <h1 class="text-2xl font-black text-gray-900">Support Tickets</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ tickets.total }} tickets total</p>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">ID</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">User</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Subject</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Type</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Replies</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="ticket in tickets.data" :key="ticket.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3 text-xs text-gray-400 font-mono">#{{ ticket.id }}</td>
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900 text-sm">{{ ticket.user?.name }}</p>
                            <p class="text-xs text-gray-400">{{ ticket.user?.email }}</p>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-700 max-w-xs truncate">{{ ticket.subject }}</td>
                        <td class="px-5 py-3">
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full" :class="typeStyles[ticket.type]">
                                {{ ticket.type }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <select
                                :value="ticket.status"
                                @change="updateStatus(ticket.id, $event.target.value)"
                                class="text-xs border border-gray-200 rounded-lg px-2 py-1 focus:ring-amber-500 focus:border-amber-500"
                            >
                                <option value="open">Open</option>
                                <option value="in_progress">In Progress</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500 font-medium">{{ ticket.replies_count }}</td>
                        <td class="px-5 py-3 text-xs text-gray-400">{{ formatDate(ticket.created_at) }}</td>
                        <td class="px-5 py-3">
                            <Link
                                :href="`/admin/tickets/${ticket.id}`"
                                class="text-xs text-amber-600 hover:text-amber-800 font-medium"
                            >
                                View
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!tickets.data.length">
                        <td colspan="8" class="px-5 py-12 text-center text-sm text-gray-400">No tickets found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="tickets.last_page > 1" class="flex justify-center gap-2 mt-6">
            <Link
                v-for="link in tickets.links"
                :key="link.label"
                :href="link.url"
                v-html="link.label"
                class="px-3 py-1.5 text-sm rounded-lg border"
                :class="link.active ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
            />
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ tickets: Object });

const typeStyles = {
    bug:        'bg-red-100 text-red-700',
    suggestion: 'bg-blue-100 text-blue-700',
    question:   'bg-violet-100 text-violet-700',
    other:      'bg-gray-100 text-gray-600',
};

function updateStatus(ticketId, status) {
    router.patch(`/admin/tickets/${ticketId}/status`, { status }, { preserveScroll: true });
}

function formatDate(str) {
    if (!str) return '';
    return new Date(str).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>
