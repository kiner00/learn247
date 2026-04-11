<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    community: Object,
    tags: Array,
});

const { communityPath } = useCommunityUrl(props.community.slug);
const { show: confirmShow, title: confirmTitle, message: confirmMessage, confirmLabel, destructive: confirmDestructive, ask, onConfirm, onCancel } = useConfirm();

const TAG_COLORS = ['#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f59e0b', '#10b981', '#06b6d4', '#3b82f6', '#64748b'];

// Create / Edit modal
const showModal   = ref(false);
const editingTag  = ref(null);
const form        = reactive({ name: '', color: '#6366f1' });
const saving      = ref(false);
const formError   = ref('');

function openCreate() {
    editingTag.value = null;
    form.name  = '';
    form.color = '#6366f1';
    formError.value = '';
    showModal.value = true;
}

function openEdit(tag) {
    editingTag.value = tag;
    form.name  = tag.name;
    form.color = tag.color || '#6366f1';
    formError.value = '';
    showModal.value = true;
}

function saveTag() {
    saving.value    = true;
    formError.value = '';

    const url    = editingTag.value
        ? communityPath(`/tags/${editingTag.value.id}`)
        : communityPath('/tags');
    const method = editingTag.value ? 'patch' : 'post';

    router[method](url, { name: form.name, color: form.color, type: 'manual' }, {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; },
        onError: (errors) => { formError.value = errors.name ?? 'Something went wrong.'; },
        onFinish: () => { saving.value = false; },
    });
}

async function deleteTag(tag) {
    if (!await ask({ title: 'Delete Tag', message: `Delete tag "${tag.name}"? It will be removed from all members.`, confirmLabel: 'Delete', destructive: true })) return;
    router.delete(communityPath(`/tags/${tag.id}`), { preserveScroll: true });
}
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tags</h1>
                <p class="text-sm text-gray-500 mt-1">Create tags to organize and segment your community members for email campaigns.</p>
            </div>
            <button @click="openCreate"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                + New Tag
            </button>
        </div>

        <!-- Tags table -->
        <div v-if="tags.length" class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 font-medium text-gray-500">Tag</th>
                        <th class="text-left px-5 py-3 font-medium text-gray-500">Type</th>
                        <th class="text-right px-5 py-3 font-medium text-gray-500">Members</th>
                        <th class="text-right px-5 py-3 font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="tag in tags" :key="tag.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <span class="w-3 h-3 rounded-full shrink-0" :style="{ backgroundColor: tag.color || '#6366f1' }"></span>
                                <span class="font-medium text-gray-900">{{ tag.name }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                :class="tag.type === 'automatic' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'">
                                {{ tag.type === 'automatic' ? 'Automatic' : 'Manual' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-right text-gray-600">
                            {{ tag.members_count }}
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <button @click="openEdit(tag)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                                <button @click="deleteTag(tag)" class="text-xs text-red-500 hover:text-red-700 font-medium">Delete</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Empty state -->
        <div v-else class="bg-white border border-gray-200 rounded-2xl p-12 text-center">
            <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-purple-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-900 mb-1">No tags yet</h3>
            <p class="text-sm text-gray-500 mb-4">Tags help you segment members for targeted email campaigns.</p>
            <button @click="openCreate"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                Create your first tag
            </button>
        </div>

        <!-- Create / Edit Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
                    <h3 class="font-semibold text-gray-900 text-base mb-4">{{ editingTag ? 'Edit Tag' : 'Create Tag' }}</h3>
                    <form @submit.prevent="saveTag" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input v-model="form.name" type="text" maxlength="100" required
                                placeholder="e.g. LEAD, VIP, Buyer"
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                            <div class="flex gap-2 flex-wrap">
                                <button v-for="c in TAG_COLORS" :key="c" type="button" @click="form.color = c"
                                    class="w-7 h-7 rounded-full border-2 transition-all"
                                    :class="form.color === c ? 'border-gray-900 scale-110' : 'border-transparent'"
                                    :style="{ backgroundColor: c }" />
                            </div>
                        </div>
                        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
                        <div class="flex gap-3 pt-1">
                            <button type="button" @click="showModal = false"
                                class="flex-1 px-4 py-2.5 border border-gray-300 text-sm font-medium rounded-xl text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" :disabled="saving || !form.name.trim()"
                                class="flex-1 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors disabled:opacity-50">
                                {{ saving ? 'Saving…' : editingTag ? 'Update' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
        <ConfirmModal :show="confirmShow" :title="confirmTitle" :message="confirmMessage" :confirm-label="confirmLabel" :destructive="confirmDestructive" @confirm="onConfirm" @cancel="onCancel" />
    </CommunitySettingsLayout>
</template>
