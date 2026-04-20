<template>
    <AdminLayout title="AI Usage">
        <div class="mb-6">
            <h1 class="text-2xl font-black text-gray-900">AI Usage</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ totals.calls.toLocaleString() }} calls ·
                {{ totals.tokens.toLocaleString() }} tokens ·
                <span class="font-semibold text-gray-700">${{ totals.cost.toFixed(4) }}</span>
                in last {{ filters.days }} days
            </p>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Days</label>
                <select v-model.number="form.days" @change="apply" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option :value="1">1 day</option>
                    <option :value="7">7 days</option>
                    <option :value="30">30 days</option>
                    <option :value="90">90 days</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Kind</label>
                <select v-model="form.kind" @change="apply" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All</option>
                    <option value="agent">Agent</option>
                    <option value="image">Image</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Model</label>
                <input v-model="form.model" @change="apply" placeholder="gemini-2.5-flash" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Community ID</label>
                <input v-model.number="form.community_id" @change="apply" type="number" placeholder="e.g. 42" class="w-32 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">User ID</label>
                <input v-model.number="form.user_id" @change="apply" type="number" placeholder="e.g. 12" class="w-32 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <button @click="reset" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-800">Clear</button>
        </div>

        <!-- Table -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">When</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kind</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Model</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Community</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">User</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Tokens (in/out)</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-2.5 text-xs text-gray-500 whitespace-nowrap">{{ formatDate(log.created_at) }}</td>
                        <td class="px-5 py-2.5">
                            <span class="text-xs px-1.5 py-0.5 rounded-full" :class="log.kind === 'image' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'">
                                {{ log.kind }}
                            </span>
                        </td>
                        <td class="px-5 py-2.5 font-mono text-xs text-gray-700">{{ log.model ?? '—' }}</td>
                        <td class="px-5 py-2.5 text-xs">
                            <Link v-if="log.community" :href="`/c/${log.community.slug}`" class="text-indigo-600 hover:underline">
                                {{ log.community.name }}
                            </Link>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-5 py-2.5 text-xs">
                            <div v-if="log.user" class="flex flex-col">
                                <span class="text-gray-900">{{ log.user.name }}</span>
                                <span class="text-gray-400">{{ log.user.email }}</span>
                            </div>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-5 py-2.5 text-right text-xs text-gray-600 tabular-nums whitespace-nowrap">
                            {{ log.prompt_tokens.toLocaleString() }} / {{ log.completion_tokens.toLocaleString() }}
                        </td>
                        <td class="px-5 py-2.5 text-right text-xs text-gray-900 font-medium tabular-nums">${{ log.cost_usd.toFixed(4) }}</td>
                    </tr>
                    <tr v-if="!logs.data.length">
                        <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-400">No AI calls match these filters</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="logs.last_page > 1" class="px-5 py-3 border-t border-gray-100 flex justify-center gap-1">
                <Link
                    v-for="link in logs.links"
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
    </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    logs:    { type: Object, default: () => ({ data: [], links: [], last_page: 1 }) },
    filters: { type: Object, default: () => ({}) },
    totals:  { type: Object, default: () => ({ calls: 0, tokens: 0, cost: 0 }) },
});

const form = reactive({
    days: props.filters.days ?? 7,
    kind: props.filters.kind ?? '',
    model: props.filters.model ?? '',
    community_id: props.filters.community_id ?? null,
    user_id: props.filters.user_id ?? null,
});

function apply() {
    const params = {};
    for (const [k, v] of Object.entries(form)) {
        if (v !== '' && v !== null && v !== undefined) params[k] = v;
    }
    router.get('/admin/ai-usage', params, { preserveState: true, replace: true });
}

function reset() {
    form.days = 7;
    form.kind = '';
    form.model = '';
    form.community_id = null;
    form.user_id = null;
    apply();
}

function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>
