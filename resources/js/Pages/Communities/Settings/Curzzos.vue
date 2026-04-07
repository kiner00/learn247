<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const props = defineProps({
    community: Object,
    isPro:     { type: Boolean, default: false },
    curzzos:   { type: Array, default: () => [] },
});

const page = usePage();
const creatorPlan = computed(() => page.props.auth.user?.creator_plan ?? 'free');
const { communityPath } = useCommunityUrl(props.community.slug);

const TONES = [
    { value: '', label: 'Default' },
    { value: 'friendly', label: 'Friendly' },
    { value: 'professional', label: 'Professional' },
    { value: 'casual', label: 'Casual' },
    { value: 'formal', label: 'Formal' },
];

const STYLES = [
    { value: '', label: 'Default' },
    { value: 'concise', label: 'Concise' },
    { value: 'detailed', label: 'Detailed' },
    { value: 'conversational', label: 'Conversational' },
];

// Modal state
const showModal    = ref(false);
const editingBot   = ref(null);
const saving       = ref(false);
const formError    = ref('');
const avatarInput  = ref(null);
const avatarPreview = ref(null);

const form = reactive({
    name: '',
    description: '',
    instructions: '',
    personality: { tone: '', expertise: '', response_style: '' },
    avatar: null,
    remove_avatar: false,
    is_active: true,
    price: '',
    currency: 'PHP',
    billing_type: 'one_time',
    affiliate_commission_rate: '',
});

function resetForm() {
    form.name = '';
    form.description = '';
    form.instructions = '';
    form.personality = { tone: '', expertise: '', response_style: '' };
    form.avatar = null;
    form.remove_avatar = false;
    form.is_active = true;
    form.price = '';
    form.currency = 'PHP';
    form.billing_type = 'one_time';
    form.affiliate_commission_rate = '';
    avatarPreview.value = null;
    formError.value = '';
}

function openCreate() {
    editingBot.value = null;
    resetForm();
    showModal.value = true;
}

function openEdit(bot) {
    editingBot.value = bot;
    form.name = bot.name;
    form.description = bot.description ?? '';
    form.instructions = bot.instructions ?? '';
    form.personality = {
        tone: bot.personality?.tone ?? '',
        expertise: bot.personality?.expertise ?? '',
        response_style: bot.personality?.response_style ?? '',
    };
    form.avatar = null;
    form.remove_avatar = false;
    form.is_active = bot.is_active;
    form.price = bot.price ?? '';
    form.currency = bot.currency ?? 'PHP';
    form.billing_type = bot.billing_type ?? 'one_time';
    form.affiliate_commission_rate = bot.affiliate_commission_rate ?? '';
    avatarPreview.value = bot.avatar;
    formError.value = '';
    showModal.value = true;
}

function onAvatarChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    form.avatar = file;
    form.remove_avatar = false;
    avatarPreview.value = URL.createObjectURL(file);
}

function removeAvatar() {
    form.avatar = null;
    form.remove_avatar = true;
    avatarPreview.value = null;
    if (avatarInput.value) avatarInput.value.value = '';
}

function saveBot() {
    saving.value = true;
    formError.value = '';

    const formData = new FormData();
    formData.append('name', form.name);
    formData.append('description', form.description);
    formData.append('instructions', form.instructions);
    if (form.personality.tone) formData.append('personality[tone]', form.personality.tone);
    if (form.personality.expertise) formData.append('personality[expertise]', form.personality.expertise);
    if (form.personality.response_style) formData.append('personality[response_style]', form.personality.response_style);
    if (form.avatar) formData.append('avatar', form.avatar);
    if (form.price !== '' && form.price !== null) formData.append('price', form.price);
    formData.append('currency', form.currency);
    formData.append('billing_type', form.billing_type);
    if (form.affiliate_commission_rate !== '' && form.affiliate_commission_rate !== null) formData.append('affiliate_commission_rate', form.affiliate_commission_rate);

    if (editingBot.value) {
        formData.append('_method', 'PATCH');
        formData.append('is_active', form.is_active ? '1' : '0');
        if (form.remove_avatar) formData.append('remove_avatar', '1');

        router.post(communityPath(`/curzzos/${editingBot.value.id}`), formData, {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; },
            onError: (errors) => { formError.value = Object.values(errors)[0] ?? 'Something went wrong.'; },
            onFinish: () => { saving.value = false; },
        });
    } else {
        router.post(communityPath('/curzzos'), formData, {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; },
            onError: (errors) => { formError.value = Object.values(errors)[0] ?? 'Something went wrong.'; },
            onFinish: () => { saving.value = false; },
        });
    }
}

function deleteBot(bot) {
    if (!confirm(`Delete "${bot.name}"? All conversation history will be lost.`)) return;
    router.delete(communityPath(`/curzzos/${bot.id}`), { preserveScroll: true });
}

function toggleActive(bot) {
    router.patch(communityPath(`/curzzos/${bot.id}`), { is_active: !bot.is_active }, { preserveScroll: true });
}
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <div class="flex items-center justify-between mb-1">
            <h2 class="text-2xl font-bold text-gray-900">Curzzos</h2>
            <span class="text-xs font-bold bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full">⭐ Pro</span>
        </div>
        <p class="text-sm text-gray-500 mb-6">Create custom AI bots that your members can chat with. Each Curzzo has its own personality, expertise, and instructions.</p>

        <!-- Locked for non-Pro -->
        <div v-if="creatorPlan !== 'pro'" class="rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-6 text-center">
            <p class="text-sm font-semibold text-indigo-800 mb-1">Creator Pro feature</p>
            <p class="text-xs text-indigo-600 mb-3">Upgrade to create custom AI bots for your community.</p>
            <Link href="/creator/plan" class="inline-block px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                Upgrade to Creator Pro →
            </Link>
        </div>

        <!-- PRO content -->
        <template v-else>
            <!-- Bot cards -->
            <div v-if="curzzos.length" class="space-y-3">
                <div v-for="bot in curzzos" :key="bot.id"
                    class="bg-white border border-gray-200 rounded-2xl p-5 flex items-start gap-4">
                    <!-- Avatar -->
                    <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center shrink-0 overflow-hidden">
                        <img v-if="bot.avatar" :src="bot.avatar" :alt="bot.name" class="w-full h-full object-cover" />
                        <span v-else class="text-lg font-bold text-indigo-600">{{ bot.name.charAt(0).toUpperCase() }}</span>
                    </div>

                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <h3 class="font-semibold text-gray-900 text-sm">{{ bot.name }}</h3>
                            <span v-if="bot.is_active"
                                class="px-1.5 py-0.5 text-[10px] font-bold bg-green-100 text-green-700 rounded-full">Active</span>
                            <span v-else
                                class="px-1.5 py-0.5 text-[10px] font-bold bg-gray-100 text-gray-500 rounded-full">Inactive</span>
                        </div>
                        <p v-if="bot.description" class="text-xs text-gray-500 line-clamp-2">{{ bot.description }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <p v-if="bot.personality?.expertise" class="text-xs text-indigo-500">{{ bot.personality.expertise }}</p>
                            <span v-if="bot.price > 0" class="text-xs font-semibold text-amber-600">
                                {{ bot.currency ?? 'PHP' }} {{ Number(bot.price).toLocaleString() }}{{ bot.billing_type === 'monthly' ? '/mo' : '' }}
                            </span>
                            <span v-else class="text-xs font-semibold text-green-600">Free</span>
                            <span v-if="bot.affiliate_commission_rate" class="text-[10px] text-gray-400">{{ bot.affiliate_commission_rate }}% affiliate</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-3 shrink-0">
                        <button @click="toggleActive(bot)"
                            class="text-xs font-medium"
                            :class="bot.is_active ? 'text-amber-600 hover:text-amber-700' : 'text-green-600 hover:text-green-700'">
                            {{ bot.is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                        <button @click="openEdit(bot)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                        <button @click="deleteBot(bot)" class="text-xs text-red-500 hover:text-red-700 font-medium">Delete</button>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="bg-white border border-gray-200 rounded-2xl p-12 text-center">
                <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-indigo-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 mb-1">No Curzzos yet</h3>
                <p class="text-sm text-gray-500 mb-4">Create your first AI bot to help engage your community members.</p>
                <button @click="openCreate"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    Create your first Curzzo
                </button>
            </div>

            <!-- Create button (when bots exist) -->
            <div v-if="curzzos.length" class="mt-4 flex items-center justify-between">
                <button @click="openCreate"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    + New Curzzo
                </button>
                <p class="text-xs text-gray-400">{{ curzzos.length }} / 5 bots</p>
            </div>
        </template>

        <!-- Create / Edit Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                    <h3 class="font-semibold text-gray-900 text-base mb-4">{{ editingBot ? 'Edit Curzzo' : 'Create Curzzo' }}</h3>
                    <form @submit.prevent="saveBot" class="space-y-4">
                        <!-- Avatar -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Avatar</label>
                            <div class="flex items-center gap-3">
                                <div class="w-14 h-14 rounded-full bg-indigo-100 flex items-center justify-center overflow-hidden shrink-0">
                                    <img v-if="avatarPreview" :src="avatarPreview" class="w-full h-full object-cover" />
                                    <span v-else class="text-xl font-bold text-indigo-600">{{ (form.name || '?').charAt(0).toUpperCase() }}</span>
                                </div>
                                <div class="flex gap-2">
                                    <label class="px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                        Upload
                                        <input ref="avatarInput" type="file" accept="image/*" class="hidden" @change="onAvatarChange" />
                                    </label>
                                    <button v-if="avatarPreview" type="button" @click="removeAvatar"
                                        class="px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-lg text-red-500 hover:bg-red-50 transition-colors">
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input v-model="form.name" type="text" maxlength="100" required
                                placeholder="e.g. Script Writer, Sales Coach, Quiz Master"
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <input v-model="form.description" type="text" maxlength="500"
                                placeholder="Brief description of what this bot does"
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>

                        <!-- Instructions -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instructions</label>
                            <textarea v-model="form.instructions" rows="6" required maxlength="5000"
                                placeholder="Define the bot's behavior, knowledge, and rules. e.g. You are a script writing expert who helps members create viral short-form video scripts. Always provide 3 hook options and a clear call-to-action."
                                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none" />
                            <p class="mt-1 text-xs text-gray-400">This defines the bot's personality and behavior. Be specific about what it should do and how it should respond.</p>
                        </div>

                        <!-- Personality -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tone</label>
                                <select v-model="form.personality.tone"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="t in TONES" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Response Style</label>
                                <select v-model="form.personality.response_style"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="s in STYLES" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Expertise</label>
                            <input v-model="form.personality.expertise" type="text" maxlength="200"
                                placeholder="e.g. Script writing, Sales strategies, Fitness coaching"
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>

                        <!-- Pricing -->
                        <div class="border-t border-gray-100 pt-4 mt-2">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Pricing</h4>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                                    <input v-model="form.price" type="number" step="0.01" min="0"
                                        placeholder="0 = Free"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                                    <select v-model="form.currency"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option value="PHP">PHP</option>
                                        <option value="USD">USD</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Billing</label>
                                    <select v-model="form.billing_type"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option value="one_time">One-time</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Affiliate Commission (%)</label>
                                <input v-model="form.affiliate_commission_rate" type="number" min="0" max="100"
                                    placeholder="e.g. 30"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <p class="mt-1 text-xs text-gray-400">Set to 0 or leave empty for no affiliate program on this bot.</p>
                            </div>
                        </div>

                        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

                        <div class="flex gap-3 pt-1">
                            <button type="button" @click="showModal = false"
                                class="flex-1 px-4 py-2.5 border border-gray-300 text-sm font-medium rounded-xl text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" :disabled="saving || !form.name.trim() || !form.instructions.trim()"
                                class="flex-1 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors disabled:opacity-50">
                                {{ saving ? 'Saving...' : editingBot ? 'Update' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </CommunitySettingsLayout>
</template>
