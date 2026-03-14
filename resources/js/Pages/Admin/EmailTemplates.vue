<template>
    <AppLayout title="Email Templates">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-black text-gray-900">Email Templates</h1>
                <p class="text-sm text-gray-500 mt-0.5">Customize the emails sent to your members</p>
            </div>
            <Link href="/admin" class="text-sm text-indigo-600 hover:underline">← Dashboard</Link>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Template</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Subject</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Last Updated</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="t in templates" :key="t.key" class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900">{{ t.name }}</p>
                            <p class="text-xs text-gray-400 mt-0.5 font-mono">{{ t.key }}</p>
                        </td>
                        <td class="px-5 py-3 text-gray-600 max-w-xs truncate">{{ t.subject }}</td>
                        <td class="px-5 py-3 text-xs text-gray-400">{{ formatDate(t.updated_at) }}</td>
                        <td class="px-5 py-3 text-right">
                            <Link
                                :href="`/admin/email-templates/${t.key}/edit`"
                                class="text-xs font-medium text-indigo-600 hover:text-indigo-800"
                            >
                                Edit →
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    templates: Array,
})

function formatDate(iso) {
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>
