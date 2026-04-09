<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { useCommunityUrl } from '@/composables/useCommunityUrl';
import { useDropzone } from '@/composables/useDropzone';

const props = defineProps({
    community:  { type: Object, required: true },
    modelTiers: { type: Array, default: () => [] },
    bot:        { type: Object, default: null },
});

const emit = defineEmits(['cancel', 'created', 'updated']);

const isEditMode = computed(() => !!props.bot);

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

const isPaidType = (type) => type === 'paid_once' || type === 'paid_monthly';
const selectPaidIfNeeded = (f) => { if (!isPaidType(f.access_type)) f.access_type = 'paid_once'; };

const saving = ref(false);
const coverPreview = ref(null);
const coverInput = ref(null);
const videoPreview = ref(null);
const videoInput = ref(null);
const videoUploading = ref(false);
const videoUploadProgress = ref(0);
const videoUploadError = ref('');
const curzzoCoverDropRef = ref(null);

const form = ref(makeEmptyForm());

function makeEmptyForm() {
    return {
        name: '',
        description: '',
        instructions: '',
        access_type: 'free',
        price: '',
        affiliate_commission_rate: '',
        cover_image: null,
        preview_video: null,
        remove_cover_image: false,
        remove_preview_video: false,
        model_tier: 'basic',
        personality_tone: '',
        personality_response_style: '',
        personality_expertise: '',
    };
}

function fillFromBot(bot) {
    if (!bot) return;
    form.value = {
        name: bot.name ?? '',
        description: bot.description ?? '',
        instructions: bot.instructions ?? '',
        access_type: bot.access_type ?? 'free',
        price: bot.price ?? '',
        affiliate_commission_rate: bot.affiliate_commission_rate ?? '',
        cover_image: null,
        preview_video: null,
        remove_cover_image: false,
        remove_preview_video: false,
        model_tier: bot.model_tier ?? 'basic',
        personality_tone: bot.personality?.tone ?? '',
        personality_response_style: bot.personality?.response_style ?? '',
        personality_expertise: bot.personality?.expertise ?? '',
    };
    coverPreview.value = bot.cover_image ?? null;
    videoPreview.value = bot.preview_video ?? null;
}

onMounted(() => {
    if (props.bot) fillFromBot(props.bot);
});

watch(() => props.bot, (b) => { if (b) fillFromBot(b); });

function resetForm() {
    form.value = makeEmptyForm();
    coverPreview.value = null;
    videoPreview.value = null;
    videoUploadError.value = '';
    if (coverInput.value) coverInput.value.value = '';
    if (videoInput.value) videoInput.value.value = '';
}

function cancel() {
    resetForm();
    emit('cancel');
}

function onCoverChange(e) {
    const file = e instanceof File ? e : e.target.files?.[0];
    if (!file) return;
    form.value.cover_image = file;
    coverPreview.value = URL.createObjectURL(file);
}

const { isDragging: curzzoCoverDragging } = useDropzone(curzzoCoverDropRef, files => onCoverChange(files[0]), { accept: 'image/*' });

function removeCover() {
    form.value.cover_image = null;
    coverPreview.value = null;
    if (coverInput.value) coverInput.value.value = '';
    if (isEditMode.value) form.value.remove_cover_image = true;
}

async function onVideoChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    videoUploading.value = true;
    videoUploadProgress.value = 0;
    videoUploadError.value = '';

    try {
        const { data } = await axios.post(communityPath('/curzzos/preview-videos'), {
            filename: file.name,
            content_type: file.type,
            size: file.size,
        });

        const { default: rawAxios } = await import('axios');
        const s3Client = rawAxios.create({ withCredentials: false });
        await s3Client.put(data.upload_url, file, {
            headers: { 'Content-Type': file.type },
            onUploadProgress: (p) => { videoUploadProgress.value = Math.round((p.loaded / p.total) * 100); },
        });

        form.value.preview_video = data.key;
        videoPreview.value = URL.createObjectURL(file);
    } catch (err) {
        videoUploadError.value = err.response?.data?.error || err.response?.data?.message || 'Upload failed. Please try again.';
    } finally {
        videoUploading.value = false;
        e.target.value = '';
    }
}

function removeVideo() {
    form.value.preview_video = null;
    videoPreview.value = null;
    videoUploadError.value = '';
    if (videoInput.value) videoInput.value.value = '';
    if (isEditMode.value) form.value.remove_preview_video = true;
}

function submitCurzzo() {
    saving.value = true;

    const formData = new FormData();
    formData.append('name', form.value.name);
    formData.append('description', form.value.description);
    formData.append('instructions', form.value.instructions);
    formData.append('access_type', form.value.access_type);
    formData.append('model_tier', form.value.model_tier);

    if (form.value.cover_image) formData.append('cover_image', form.value.cover_image);
    if (form.value.preview_video) formData.append('preview_video', form.value.preview_video);
    if (form.value.remove_cover_image) formData.append('remove_cover_image', '1');
    if (form.value.remove_preview_video) formData.append('remove_preview_video', '1');

    if (isPaidType(form.value.access_type)) {
        if (form.value.price) formData.append('price', form.value.price);
        formData.append('currency', 'PHP');
        formData.append('billing_type', form.value.access_type === 'paid_monthly' ? 'monthly' : 'one_time');
        if (form.value.affiliate_commission_rate) formData.append('affiliate_commission_rate', form.value.affiliate_commission_rate);
    }

    if (form.value.personality_tone) formData.append('personality[tone]', form.value.personality_tone);
    if (form.value.personality_response_style) formData.append('personality[response_style]', form.value.personality_response_style);
    if (form.value.personality_expertise) formData.append('personality[expertise]', form.value.personality_expertise);

    if (isEditMode.value) {
        formData.append('_method', 'PATCH');
        router.post(communityPath(`/curzzos/${props.bot.id}`), formData, {
            preserveScroll: true,
            onSuccess: () => {
                emit('updated');
            },
            onError: (errors) => {
                console.error('Update curzzo errors:', errors);
            },
            onFinish: () => { saving.value = false; },
        });
        return;
    }

    router.post(communityPath('/curzzos'), formData, {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
            emit('created');
        },
        onError: (errors) => {
            console.error('Create curzzo errors:', errors);
        },
        onFinish: () => { saving.value = false; },
    });
}
</script>

<template>
    <div class="bg-white border border-indigo-200 rounded-2xl p-5 shadow-sm mb-6">
        <h2 class="text-sm font-bold text-gray-900 mb-3">{{ isEditMode ? 'Edit Curzzo' : 'New Curzzo' }}</h2>
        <form @submit.prevent="submitCurzzo">
            <input
                v-model="form.name"
                type="text"
                placeholder="Curzzo title"
                required
                maxlength="100"
                class="w-full px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-2"
            />
            <textarea
                v-model="form.description"
                rows="2"
                placeholder="Description (optional)"
                maxlength="500"
                class="w-full px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none mb-3"
            />

            <!-- Access type -->
            <div class="mb-3">
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Access</label>
                <div class="flex gap-2">
                    <label :class="['flex-1 cursor-pointer rounded-lg border-2 p-2.5 text-center transition-all',
                        form.access_type === 'free' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                        <input type="radio" value="free" v-model="form.access_type" class="sr-only" />
                        <div class="text-base mb-0.5">🌐</div>
                        <div class="text-xs font-semibold text-gray-800">Free</div>
                        <div class="text-[10px] text-gray-400 leading-tight mt-0.5">Anyone can access</div>
                    </label>
                    <label :class="['flex-1 cursor-pointer rounded-lg border-2 p-2.5 text-center transition-all',
                        form.access_type === 'inclusive' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                        <input type="radio" value="inclusive" v-model="form.access_type" class="sr-only" />
                        <div class="text-base mb-0.5">⭐</div>
                        <div class="text-xs font-semibold text-gray-800">Included</div>
                        <div class="text-[10px] text-gray-400 leading-tight mt-0.5">Members only</div>
                    </label>
                    <label :class="['flex-1 cursor-pointer rounded-lg border-2 p-2.5 text-center transition-all',
                        form.access_type === 'member_once' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                        <input type="radio" value="member_once" v-model="form.access_type" class="sr-only" />
                        <div class="text-base mb-0.5">🎟️</div>
                        <div class="text-xs font-semibold text-gray-800">One-Time</div>
                        <div class="text-[10px] text-gray-400 leading-tight mt-0.5">Past members included</div>
                    </label>
                    <label :class="['flex-1 cursor-pointer rounded-lg border-2 p-2.5 text-center transition-all',
                        isPaidType(form.access_type) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']"
                        @click="selectPaidIfNeeded(form)">
                        <div class="text-base mb-0.5">💳</div>
                        <div class="text-xs font-semibold text-gray-800">Paid</div>
                        <div class="text-[10px] text-gray-400 leading-tight mt-0.5">Separate payment</div>
                    </label>
                </div>
                <!-- Paid sub-choice -->
                <div v-if="isPaidType(form.access_type)" class="mt-2 flex gap-2 pl-0.5">
                    <label :class="['flex-1 cursor-pointer rounded-lg border px-3 py-2 flex items-center gap-2 transition-all',
                        form.access_type === 'paid_once' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                        <input type="radio" value="paid_once" v-model="form.access_type" class="accent-indigo-600" />
                        <div>
                            <div class="text-xs font-semibold text-gray-800">One-time</div>
                            <div class="text-[10px] text-gray-400">Pay once, access forever</div>
                        </div>
                    </label>
                    <label :class="['flex-1 cursor-pointer rounded-lg border px-3 py-2 flex items-center gap-2 transition-all',
                        form.access_type === 'paid_monthly' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                        <input type="radio" value="paid_monthly" v-model="form.access_type" class="accent-indigo-600" />
                        <div>
                            <div class="text-xs font-semibold text-gray-800">Monthly</div>
                            <div class="text-[10px] text-gray-400">Recurring monthly payment</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Price (paid types) -->
            <div v-if="isPaidType(form.access_type)" class="mb-3">
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Price (PHP)
                    <span class="text-gray-400 font-normal">{{ form.access_type === 'paid_monthly' ? '/ month' : '· one-time' }}</span>
                </label>
                <input v-model="form.price" type="number" min="1" step="0.01" required placeholder="e.g. 1500"
                    class="w-48 px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <!-- Affiliate commission (paid types) -->
            <div v-if="isPaidType(form.access_type)" class="mb-3">
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Affiliate commission
                    <span class="text-gray-400 font-normal">· % of sale price paid to referring affiliate</span>
                </label>
                <div class="flex items-center gap-2">
                    <input v-model="form.affiliate_commission_rate" type="number" min="0" max="100" step="1" placeholder="e.g. 30"
                        class="w-24 px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <span class="text-sm text-gray-500">%</span>
                    <span v-if="form.affiliate_commission_rate && form.price" class="text-xs text-gray-400">
                        = ₱{{ (form.price * form.affiliate_commission_rate / 100).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }} per sale
                    </span>
                </div>
            </div>

            <!-- Cover image -->
            <div class="mb-3">
                <label class="block text-xs font-medium text-gray-600 mb-1">Cover image <span class="text-gray-400 font-normal">(optional)</span></label>
                <div
                    ref="curzzoCoverDropRef"
                    class="rounded-lg transition-colors"
                    :class="curzzoCoverDragging ? 'ring-2 ring-indigo-300 bg-indigo-50 ring-dashed' : ''"
                >
                    <div v-if="coverPreview" class="relative mb-2 h-28 rounded-lg overflow-hidden border border-gray-200">
                        <img :src="coverPreview" class="w-full h-full object-cover" />
                        <button type="button" @click="removeCover"
                            class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-black/50 text-white flex items-center justify-center text-xs hover:bg-black/70">x</button>
                    </div>
                    <label class="flex items-center gap-2 w-fit cursor-pointer px-3 py-1.5 border border-gray-300 rounded-lg text-xs text-gray-600 hover:bg-gray-50 transition-colors">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        {{ curzzoCoverDragging ? 'Drop here' : (coverPreview ? 'Change image' : 'Upload or drag & drop cover') }}
                        <input ref="coverInput" type="file" accept="image/*" class="hidden" @change="onCoverChange" />
                    </label>
                </div>
                <p class="text-xs text-gray-400 mt-1">Recommended: 1280 x 720 px</p>
            </div>

            <!-- Preview video -->
            <div class="mb-3">
                <label class="block text-xs font-medium text-gray-600 mb-1">Preview video <span class="text-gray-400 font-normal">(optional)</span></label>
                <div v-if="videoPreview" class="relative mb-2 aspect-video rounded-lg overflow-hidden border border-gray-200 bg-black">
                    <video :src="videoPreview" class="w-full h-full object-cover" muted playsinline />
                    <button type="button" @click="removeVideo"
                        class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-black/50 text-white flex items-center justify-center text-xs hover:bg-black/70">x</button>
                </div>
                <div v-if="videoUploading" class="mb-2">
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-indigo-500 rounded-full transition-all" :style="{ width: `${videoUploadProgress}%` }" />
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Uploading... {{ videoUploadProgress }}%</p>
                </div>
                <p v-if="videoUploadError" class="text-xs text-red-500 mb-1">{{ videoUploadError }}</p>
                <label v-if="!videoUploading" class="flex items-center gap-2 w-fit cursor-pointer px-3 py-1.5 border border-gray-300 rounded-lg text-xs text-gray-600 hover:bg-gray-50 transition-colors">
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    {{ videoPreview ? 'Change video' : 'Upload preview' }}
                    <input ref="videoInput" type="file" accept="video/mp4,video/quicktime,video/webm" class="hidden" @change="onVideoChange" />
                </label>
                <p class="text-xs text-gray-400 mt-1">MP4 recommended, 1280 x 720 px, max 500 MB. Plays on hover.</p>
            </div>

            <!-- Curzzo Instructions -->
            <div class="mb-3">
                <label class="block text-xs font-medium text-gray-600 mb-1">Curzzo Instructions</label>
                <textarea v-model="form.instructions" rows="6" required maxlength="5000"
                    placeholder="Define the bot's behavior, knowledge, and rules. e.g. You are a script writing expert who helps members create viral short-form video scripts. Always provide 3 hook options and a clear call-to-action."
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
                <p class="text-xs text-gray-400 mt-1">This defines the bot's personality and behavior. Be specific about what it should do and how it should respond.</p>
            </div>

            <!-- Model Tier -->
            <div v-if="modelTiers.length" class="mb-3">
                <label class="block text-xs font-medium text-gray-600 mb-2">AI Model</label>
                <div class="grid grid-cols-2 gap-3">
                    <button v-for="tier in modelTiers" :key="tier.value"
                        type="button" @click="form.model_tier = tier.value"
                        :class="[
                            'relative rounded-xl border-2 p-3 text-left transition-all',
                            form.model_tier === tier.value
                                ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600'
                                : 'border-gray-200 hover:border-gray-300'
                        ]">
                        <div class="text-sm font-semibold text-gray-900">{{ tier.label }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ tier.description }}</div>
                    </button>
                </div>
            </div>

            <!-- Personality -->
            <div class="mb-3">
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Personality</label>
                <div class="grid grid-cols-2 gap-3 mb-2">
                    <div>
                        <label class="block text-[11px] text-gray-500 mb-0.5">Tone</label>
                        <select v-model="form.personality_tone"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option v-for="t in TONES" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] text-gray-500 mb-0.5">Response Style</label>
                        <select v-model="form.personality_response_style"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option v-for="s in STYLES" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] text-gray-500 mb-0.5">Expertise</label>
                    <input v-model="form.personality_expertise" type="text" maxlength="200"
                        placeholder="e.g. Script writing, Sales strategies, Fitness coaching"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
            </div>

            <div class="flex gap-2 justify-end">
                <button type="button" @click="cancel" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancel</button>
                <button type="submit" :disabled="saving || !form.name.trim() || !form.instructions.trim()"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    {{ saving ? (isEditMode ? 'Saving...' : 'Creating...') : (isEditMode ? 'Save Changes' : 'Create Curzzo') }}
                </button>
            </div>
        </form>
    </div>
</template>
