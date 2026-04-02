<template>
    <AdminLayout title="Trashed Posts">
        <div class="mb-6">
            <h1 class="text-2xl font-black text-gray-900">Trashed Posts</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ posts.total }} deleted posts</p>
        </div>

        <!-- Search -->
        <div class="mb-4">
            <input
                v-model="search"
                type="text"
                placeholder="Search by title or content..."
                class="w-full max-w-md px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                @input="onSearch"
            />
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Post</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Author</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Community</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Deleted</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="p in posts.data" :key="p.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3 max-w-xs">
                            <p class="font-medium text-gray-900 truncate">{{ p.title || '(no title)' }}</p>
                            <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ p.content }}...</p>
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-600">{{ p.author }}</td>
                        <td class="px-5 py-3 text-xs">
                            <Link v-if="p.community_slug" :href="`/communities/${p.community_slug}`" class="text-indigo-600 hover:underline">
                                {{ p.community }}
                            </Link>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-400">{{ p.deleted_at }}</td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <button @click="restore(p.id)" :disabled="acting === p.id"
                                    class="text-xs font-medium text-green-600 hover:text-green-800 disabled:opacity-40 transition-colors">
                                    {{ acting === p.id ? '...' : 'Restore' }}
                                </button>
                                <button @click="forceDelete(p.id)" :disabled="acting === p.id"
                                    class="text-xs font-medium text-red-500 hover:text-red-700 disabled:opacity-40 transition-colors">
                                    Delete permanently
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!posts.data.length">
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-400">No trashed posts found</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="posts.last_page > 1" class="px-5 py-3 border-t border-gray-100 flex justify-center gap-1">
                <Link
                    v-for="link in posts.links"
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
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    posts:   { type: Object, default: () => ({ data: [], total: 0, last_page: 1, links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search ?? '');
const acting = ref(null);

let timer;
function onSearch() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/admin/posts/trashed', { search: search.value || undefined }, { preserveState: 'errors', replace: true });
    }, 350);
}

function restore(id) {
    if (!confirm('Restore this post?')) return;
    acting.value = id;
    router.post(`/admin/posts/${id}/restore`, {}, {
        preserveScroll: true,
        onFinish: () => { acting.value = null; },
    });
}

function forceDelete(id) {
    if (!confirm('Permanently delete this post? This cannot be undone.')) return;
    acting.value = id;
    router.delete(`/admin/posts/${id}/force-delete`, {
        preserveScroll: true,
        onFinish: () => { acting.value = null; },
    });
}
</script>
