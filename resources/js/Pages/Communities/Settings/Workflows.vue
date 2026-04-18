<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    community: Object,
    workflows: { type: Array, default: () => [] },
    tags:      { type: Array, default: () => [] },
    courses:   { type: Array, default: () => [] },
});

const { communityPath } = useCommunityUrl(props.community.slug);
const { show: confirmShow, title: confirmTitle, message: confirmMessage, confirmLabel, destructive: confirmDestructive, ask, onConfirm, onCancel } = useConfirm();

const TRIGGERS = [
    { value: 'member_joined',     label: 'Member joins community' },
    { value: 'subscription_paid', label: 'Member pays subscription' },
    { value: 'course_enrolled',   label: 'Member enrolls in course' },
];

const triggerLabel = (value) => TRIGGERS.find(t => t.value === value)?.label ?? value;

// Create / Edit modal
const showModal  = ref(false);
const editing    = ref(null);
const saving     = ref(false);
const formError  = ref('');
const form = reactive({
    name: '',
    trigger_event: 'member_joined',
    tag_id: null,
    course_id: null,
    membership_type: '',
    action_type: 'apply_tag',
    is_active: true,
});

function resetForm() {
    form.name = '';
    form.trigger_event = 'member_joined';
    form.tag_id = props.tags[0]?.id ?? null;
    form.course_id = null;
    form.membership_type = '';
    form.action_type = 'apply_tag';
    form.is_active = true;
    formError.value = '';
}

function openCreate() {
    if (!props.tags.length) {
        formError.value = '';
        return;
    }
    editing.value = null;
    resetForm();
    showModal.value = true;
}

function openEdit(wf) {
    editing.value = wf;
    form.name = wf.name;
    form.trigger_event = wf.trigger_event;
    form.tag_id = wf.action_config?.tag_id ?? null;
    form.course_id = wf.trigger_filter?.course_id ?? null;
    form.membership_type = wf.trigger_filter?.membership_type ?? '';
    form.action_type = wf.action_type;
    form.is_active = wf.is_active;
    formError.value = '';
    showModal.value = true;
}

function save() {
    saving.value = true;
    formError.value = '';

    const payload = {
        name: form.name,
        trigger_event: form.trigger_event,
        action_type: form.action_type,
        tag_id: form.tag_id,
        course_id: form.trigger_event === 'course_enrolled' ? form.course_id : null,
        membership_type: form.trigger_event === 'member_joined' ? (form.membership_type || null) : null,
        is_active: form.is_active,
    };

    const url = editing.value
        ? communityPath(`/workflows/${editing.value.id}`)
        : communityPath('/workflows');
    const method = editing.value ? 'patch' : 'post';

    router[method](url, payload, {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; },
        onError: (errors) => {
            formError.value = Object.values(errors)[0] ?? 'Something went wrong.';
        },
        onFinish: () => { saving.value = false; },
    });
}

function toggle(wf) {
    router.post(communityPath(`/workflows/${wf.id}/toggle`), {}, { preserveScroll: true });
}

async function remove(wf) {
    if (!await ask({ title: 'Delete Workflow', message: `Delete "${wf.name}"? This will stop it from running.`, confirmLabel: 'Delete', destructive: true })) return;
    router.delete(communityPath(`/workflows/${wf.id}`), { preserveScroll: true });
}

const tagById = computed(() => Object.fromEntries(props.tags.map(t => [t.id, t])));
const courseById = computed(() => Object.fromEntries(props.courses.map(c => [c.id, c])));

function triggerSummary(wf) {
    const base = triggerLabel(wf.trigger_event);
    const f = wf.trigger_filter ?? {};
    if (wf.trigger_event === 'course_enrolled' && f.course_id) {
        const title = courseById.value[f.course_id]?.title ?? `course #${f.course_id}`;
        return `${base}: ${title}`;
    }
    if (wf.trigger_event === 'member_joined' && f.membership_type) {
        return `${base} (${f.membership_type === 'paid' ? 'paid' : 'free'})`;
    }
    return base;
}

function actionSummary(wf) {
    if (wf.action_type === 'apply_tag') {
        const t = tagById.value[wf.action_config?.tag_id];
        return t ? `Apply "${t.name}" tag` : 'Apply tag';
    }
    return wf.action_type;
}
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Workflows</h1>
                <p class="text-sm text-gray-500 mt-1">Automate tagging based on member behavior.</p>
            </div>
            <button v-if="tags.length" @click="openCreate"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                + New Workflow
            </button>
        </div>

        <!-- No tags yet -->
        <div v-if="!tags.length" class="bg-white border border-gray-200 rounded-2xl p-12 text-center">
            <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-amber-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0l-7.1 12.25A2 2 0 005 19z"/>
                </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-900 mb-1">Create a tag first</h3>
            <p class="text-sm text-gray-500 mb-4 max-w-md mx-auto">
                Workflows apply tags to members based on triggers. Create at least one tag before building a workflow.
            </p>
            <Link :href="communityPath('/settings/tags')"
                class="inline-block px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                Go to Tags
            </Link>
        </div>

        <!-- Workflows list -->
        <div v-else-if="workflows.length" class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 font-medium text-gray-500">Name</th>
                        <th class="text-left px-5 py-3 font-medium text-gray-500">Trigger</th>
                        <th class="text-left px-5 py-3 font-medium text-gray-500">Action</th>
                        <th class="text-right px-5 py-3 font-medium text-gray-500">Runs</th>
                        <th class="text-center px-5 py-3 font-medium text-gray-500">Status</th>
                        <th class="text-right px-5 py-3 font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="wf in workflows" :key="wf.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5 font-medium text-gray-900">{{ wf.name }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ triggerSummary(wf) }}</td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-2">
                                <span v-if="tagById[wf.action_config?.tag_id]"
                                    class="w-2 h-2 rounded-full"
                                    :style="{ backgroundColor: tagById[wf.action_config.tag_id].color || '#6366f1' }"></span>
                                <span class="text-gray-600">{{ actionSummary(wf) }}</span>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-right text-gray-500 tabular-nums">{{ wf.run_count }}</td>
                        <td class="px-5 py-3.5 text-center">
                            <button type="button" @click="toggle(wf)"
                                class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
                                :class="wf.is_active ? 'bg-emerald-500' : 'bg-gray-300'">
                                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform"
                                    :class="wf.is_active ? 'translate-x-5' : 'translate-x-1'"></span>
                            </button>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <button @click="openEdit(wf)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                                <button @click="remove(wf)" class="text-xs text-red-500 hover:text-red-700 font-medium">Delete</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Empty state -->
        <div v-else class="bg-white border border-gray-200 rounded-2xl p-12 text-center">
            <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-blue-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-900 mb-1">No workflows yet</h3>
            <p class="text-sm text-gray-500 mb-4 max-w-md mx-auto">
                Set up automated rules to tag members based on triggers like joining, subscribing, or enrolling in a course.
            </p>
            <button @click="openCreate"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                Create your first workflow
            </button>
        </div>

        <!-- Create / Edit Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                    <h3 class="font-semibold text-gray-900 text-base mb-4">{{ editing ? 'Edit Workflow' : 'Create Workflow' }}</h3>
                    <form @submit.prevent="save" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input v-model="form.name" type="text" maxlength="150" required
                                placeholder="e.g. Tag new paid members as LEAD"
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">When</label>
                            <select v-model="form.trigger_event"
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option v-for="t in TRIGGERS" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>

                        <div v-if="form.trigger_event === 'course_enrolled'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Course (optional)</label>
                            <select v-model="form.course_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option :value="null">Any course</option>
                                <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
                            </select>
                        </div>

                        <div v-if="form.trigger_event === 'member_joined'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Membership type (optional)</label>
                            <select v-model="form.membership_type"
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">Any</option>
                                <option value="free">Free members only</option>
                                <option value="paid">Paid members only</option>
                            </select>
                        </div>

                        <div class="pt-2 border-t border-gray-100">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Then apply tag</label>
                            <select v-model="form.tag_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option :value="null" disabled>Select a tag</option>
                                <option v-for="t in tags" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>

                        <label class="flex items-center gap-2 pt-1">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Active</span>
                        </label>

                        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
                        <div class="flex gap-3 pt-1">
                            <button type="button" @click="showModal = false"
                                class="flex-1 px-4 py-2.5 border border-gray-300 text-sm font-medium rounded-xl text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" :disabled="saving || !form.name.trim() || !form.tag_id"
                                class="flex-1 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors disabled:opacity-50">
                                {{ saving ? 'Saving…' : editing ? 'Update' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
        <ConfirmModal :show="confirmShow" :title="confirmTitle" :message="confirmMessage" :confirm-label="confirmLabel" :destructive="confirmDestructive" @confirm="onConfirm" @cancel="onCancel" />
    </CommunitySettingsLayout>
</template>
