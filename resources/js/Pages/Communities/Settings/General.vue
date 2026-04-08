<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';
import { IMAGE_DIMENSIONS } from '@/constants';
import { useDropzone } from '@/composables/useDropzone';

const CATEGORIES = ['Tech', 'Business', 'Design', 'Health', 'Education', 'Finance', 'Other'];

const props = defineProps({
    community:   Object,
    pricingGate: Object,
    isPro:       { type: Boolean, default: false },
});

// ─── General form ────────────────────────────────────────────────────────────
const saved = ref(false);

const form = useForm({
    name:         props.community.name,
    description:  props.community.description ?? '',
    category:     props.community.category ?? '',
    price:        props.community.price ?? 0,
    currency:     props.community.currency ?? 'PHP',
    billing_type: props.community.billing_type ?? 'monthly',
    is_private:   props.community.is_private ?? false,
});

function save() {
    form.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            onSuccess: () => {
                saved.value = true;
                setTimeout(() => (saved.value = false), 3000);
            },
        });
}

// ─── Images form ─────────────────────────────────────────────────────────────
const imagesSaved   = ref(false);
const coverPreview  = ref(null);
const coverInput    = ref(null);
const coverRemoved  = ref(false);
const avatarPreview = ref(null);
const avatarInput   = ref(null);
const avatarRemoved = ref(false);

const imageForm = useForm({
    name:               props.community.name,
    cover_image:        null,
    avatar:             null,
    remove_cover_image: false,
    remove_avatar:      false,
});

function onCoverChange(e) {
    const file = e instanceof File ? e : e.target.files[0];
    if (!file) return;
    if (file.size > 15 * 1024 * 1024) {
        imageForm.errors.cover_image = 'The banner must not be larger than 15 MB.';
        if (coverInput.value) coverInput.value.value = '';
        return;
    }
    imageForm.errors.cover_image = null;
    imageForm.cover_image = file;
    imageForm.remove_cover_image = false;
    coverPreview.value = URL.createObjectURL(file);
    coverRemoved.value = false;
}

function removeCover() {
    imageForm.cover_image = null;
    imageForm.remove_cover_image = true;
    coverPreview.value = null;
    coverRemoved.value = true;
    if (coverInput.value) coverInput.value.value = '';
}

function onAvatarChange(e) {
    const file = e instanceof File ? e : e.target.files[0];
    if (!file) return;
    if (file.size > 15 * 1024 * 1024) {
        imageForm.errors.avatar = 'The avatar must not be larger than 15 MB.';
        if (avatarInput.value) avatarInput.value.value = '';
        return;
    }
    imageForm.errors.avatar = null;
    imageForm.avatar = file;
    imageForm.remove_avatar = false;
    avatarPreview.value = URL.createObjectURL(file);
    avatarRemoved.value = false;
}

function removeAvatar() {
    imageForm.avatar = null;
    imageForm.remove_avatar = true;
    avatarPreview.value = null;
    avatarRemoved.value = true;
    if (avatarInput.value) avatarInput.value.value = '';
}

function saveImages() {
    imageForm.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            onSuccess: () => {
                coverPreview.value = null;
                coverRemoved.value = false;
                avatarPreview.value = null;
                avatarRemoved.value = false;
                imagesSaved.value = true;
                setTimeout(() => (imagesSaved.value = false), 3000);
            },
        });
}

// ─── Gallery ─────────────────────────────────────────────────────────────────
const galleryFile      = ref(null);
const galleryUploading = ref(false);
const galleryForm      = useForm({ image: null });

function onGalleryFileChange(e) {
    const file = e instanceof File ? e : (e.target.files[0] ?? null);
    if (file && file.size > 15 * 1024 * 1024) {
        galleryForm.errors.image = 'The image must not be larger than 15 MB.';
        galleryFile.value = null;
        galleryForm.image = null;
        return;
    }
    galleryForm.errors.image = null;
    galleryFile.value = file;
    galleryForm.image = file;
}

function uploadGalleryImage() {
    if (!galleryFile.value) return;
    galleryUploading.value = true;
    galleryForm.post(`/communities/${props.community.slug}/gallery`, {
        preserveScroll: true,
        onSuccess: () => {
            galleryFile.value = null;
            galleryForm.reset();
        },
        onFinish: () => { galleryUploading.value = false; },
    });
}

function removeGalleryImage(index) {
    router.delete(`/communities/${props.community.slug}/gallery/${index}`, { preserveScroll: true });
}

const generalCoverDropRef = ref(null);
const generalAvatarDropRef = ref(null);
const galleryDropRef = ref(null);
const { isDragging: generalCoverDragging } = useDropzone(generalCoverDropRef, files => onCoverChange(files[0]), { accept: 'image/*' });
const { isDragging: generalAvatarDragging } = useDropzone(generalAvatarDropRef, files => onAvatarChange(files[0]), { accept: 'image/*' });
const { isDragging: galleryDragging } = useDropzone(galleryDropRef, files => onGalleryFileChange(files[0]), { accept: 'image/*' });

// ─── AI Gallery Generation (one at a time) ──────────────────────────────────
const aiGalleryGenerating = ref(false);
const aiGalleryError      = ref(null);
const aiGalleryProgress   = ref(0);
const aiGalleryTotal      = ref(0);
let aiGalleryPollTimer    = null;
let lastSeenProgress      = -1;

const galleryLabels = [
    'Welcome Image', 'The "Why"', 'The Classroom', 'The Calendar',
    'Certification Preview', 'Social Proof', 'The Mobile View', 'Final CTA',
];

(async function checkAiGalleryStatus() {
    try {
        const { data } = await axios.get(`/communities/${props.community.slug}/gallery/ai-status`);
        if (data.status === 'generating') {
            aiGalleryGenerating.value = true;
            aiGalleryProgress.value   = data.progress ?? 0;
            aiGalleryTotal.value      = data.total ?? 1;
            pollAiGalleryStatus();
        }
    } catch {}
})();

async function startAiGalleryGeneration() {
    if (!props.isPro || aiGalleryGenerating.value) return;

    const count = props.community.gallery_images?.length ?? 0;
    if (count >= 8) return;

    aiGalleryGenerating.value = true;
    aiGalleryError.value      = null;
    aiGalleryProgress.value   = 0;
    aiGalleryTotal.value      = 8 - count;
    lastSeenProgress          = -1;

    try {
        await axios.post(`/communities/${props.community.slug}/gallery/ai-generate`);
        pollAiGalleryStatus();
    } catch (e) {
        aiGalleryGenerating.value = false;
        aiGalleryError.value = e.response?.data?.error || 'Failed to start generation.';
    }
}

function pollAiGalleryStatus() {
    aiGalleryPollTimer = setInterval(async () => {
        try {
            const { data } = await axios.get(`/communities/${props.community.slug}/gallery/ai-status`);

            if (data.status === 'generating') {
                aiGalleryProgress.value = data.progress ?? 0;
                aiGalleryTotal.value    = data.total ?? 1;
                // Reload page data when a new image was generated so it appears in the gallery
                if (data.progress > lastSeenProgress && data.progress > 0) {
                    lastSeenProgress = data.progress;
                    router.reload({ only: ['community'], preserveScroll: true });
                }
            } else if (data.status === 'completed') {
                clearInterval(aiGalleryPollTimer);
                aiGalleryGenerating.value = false;
                router.reload({ only: ['community'], preserveScroll: true });
            } else if (data.status === 'failed') {
                clearInterval(aiGalleryPollTimer);
                aiGalleryGenerating.value = false;
                aiGalleryError.value = data.error || 'Generation failed.';
                // Still reload to show any images that were generated before the failure
                if (data.progress > 0) {
                    router.reload({ only: ['community'], preserveScroll: true });
                }
            }
        } catch {
            clearInterval(aiGalleryPollTimer);
            aiGalleryGenerating.value = false;
            aiGalleryError.value = 'Failed to check status.';
        }
    }, 4000);
}

// ─── Gallery Drag & Drop Reorder ─────────────────────────────────────────────
const dragIndex    = ref(null);
const dropIndex    = ref(null);

function onDragStart(index) {
    dragIndex.value = index;
}

function onDragOver(e, index) {
    e.preventDefault();
    dropIndex.value = index;
}

function onDragLeave() {
    dropIndex.value = null;
}

async function onDrop(index) {
    dropIndex.value = null;
    if (dragIndex.value === null || dragIndex.value === index) return;

    const gallery = [...props.community.gallery_images];
    const [moved] = gallery.splice(dragIndex.value, 1);
    gallery.splice(index, 0, moved);

    // Build order array: map new positions to original indices
    const original = props.community.gallery_images;
    const order = gallery.map(url => original.indexOf(url));

    try {
        await axios.put(`/communities/${props.community.slug}/gallery/reorder`, { order });
        router.reload({ only: ['community'], preserveScroll: true });
    } catch (e) {
        aiGalleryError.value = 'Failed to reorder images.';
    }

    dragIndex.value = null;
}

function onDragEnd() {
    dragIndex.value = null;
    dropIndex.value = null;
}

// ─── Brand Source of Truth ───────────────────────────────────────────────────
const brandSaved    = ref(false);
const brandExpanded = ref(false);

const bc = props.community.brand_context ?? {};

const brandForm = useForm({
    brand_context: {
        brand_personality:        bc.brand_personality ?? '',
        target_audience:          bc.target_audience ?? '',
        tone_of_voice:            bc.tone_of_voice ?? '',
        value_proposition:        bc.value_proposition ?? '',
        primary_keywords:         bc.primary_keywords ?? '',
        big_problem:              bc.big_problem ?? '',
        color_primary:            bc.color_primary ?? '',
        color_secondary:          bc.color_secondary ?? '',
        color_accent:             bc.color_accent ?? '',
        visual_style:             bc.visual_style ?? '',
        logo_rules:               bc.logo_rules ?? '',
        cta_goal:                 bc.cta_goal ?? '',
        offer_details:            bc.offer_details ?? '',
        social_share_description: bc.social_share_description ?? '',
    },
});

function saveBrand() {
    brandForm.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            preserveScroll: true,
            onSuccess: () => {
                brandSaved.value = true;
                setTimeout(() => (brandSaved.value = false), 3000);
            },
        });
}
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <!-- General settings -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-900 mb-5">General</h2>
            <form @submit.prevent="save">
                <div v-if="form.hasErrors" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3">
                    <p class="text-sm font-medium text-red-800">Please fix the following errors:</p>
                    <ul class="mt-1 list-disc list-inside text-xs text-red-700">
                        <li v-for="(msg, field) in form.errors" :key="field">{{ msg }}</li>
                    </ul>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Community name <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            :class="form.errors.name ? 'border-red-400' : 'border-gray-300'"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Category</label>
                        <select
                            v-model="form.category"
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                        >
                            <option value="">No category</option>
                            <option v-for="cat in CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>

                    <!-- Pricing requirements checklist -->
                    <div v-if="pricingGate && !pricingGate.can_enable_pricing" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-sm font-semibold text-amber-800 mb-3">Complete these requirements to enable paid pricing:</p>
                        <ul class="space-y-2 text-sm">
                            <li class="flex items-center gap-2" :class="pricingGate.module_count >= 5 ? 'text-green-700' : 'text-gray-600'">
                                <span>{{ pricingGate.module_count >= 5 ? '✅' : '☐' }}</span>
                                <span>At least 5 modules <span class="text-gray-400">({{ pricingGate.module_count }}/5)</span></span>
                            </li>
                            <li class="flex items-center gap-2" :class="pricingGate.has_banner ? 'text-green-700' : 'text-gray-600'">
                                <span>{{ pricingGate.has_banner ? '✅' : '☐' }}</span>
                                <span>Banner image uploaded</span>
                            </li>
                            <li class="flex items-center gap-2" :class="pricingGate.has_description ? 'text-green-700' : 'text-gray-600'">
                                <span>{{ pricingGate.has_description ? '✅' : '☐' }}</span>
                                <span>Community description filled</span>
                            </li>
                            <li class="flex items-center gap-2" :class="pricingGate.profile_complete ? 'text-green-700' : 'text-gray-600'">
                                <span>{{ pricingGate.profile_complete ? '✅' : '☐' }}</span>
                                <span>Your profile complete (name, bio, avatar)</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Billing type -->
                    <div v-if="Number(form.price) > 0" class="mb-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Billing type</label>
                        <div class="flex gap-3">
                            <label :class="['flex-1 cursor-pointer rounded-xl border-2 p-3 transition-all',
                                form.billing_type === 'monthly' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                                <input type="radio" value="monthly" v-model="form.billing_type" class="sr-only" />
                                <div class="text-sm font-bold text-gray-800">🔄 Monthly</div>
                                <div class="text-xs text-gray-400 mt-0.5">Members pay every month to stay active</div>
                            </label>
                            <label :class="['flex-1 cursor-pointer rounded-xl border-2 p-3 transition-all',
                                form.billing_type === 'one_time' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                                <input type="radio" value="one_time" v-model="form.billing_type" class="sr-only" />
                                <div class="text-sm font-bold text-gray-800">💳 One-time</div>
                                <div class="text-xs text-gray-400 mt-0.5">Members pay once for lifetime access</div>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Price
                                <span class="text-gray-400 font-normal">
                                    ({{ form.billing_type === 'one_time' ? 'one-time' : 'per month' }})
                                </span>
                            </label>
                            <input
                                v-model="form.price"
                                type="number"
                                min="0"
                                step="1"
                                :disabled="pricingGate && !pricingGate.can_enable_pricing && Number(form.price) === 0"
                                class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent disabled:bg-gray-100 disabled:cursor-not-allowed"
                                :class="form.errors.price ? 'border-red-400' : 'border-gray-300'"
                            />
                            <p v-if="form.errors.price" class="mt-1 text-xs text-red-600">{{ form.errors.price }}</p>
                            <p v-else class="mt-1 text-xs text-gray-400">Set to 0 for free access</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Currency</label>
                            <select
                                v-model="form.currency"
                                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                            >
                                <option value="PHP">PHP – Philippine Peso</option>
                                <option value="USD">USD – US Dollar</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <input
                            id="is_private"
                            v-model="form.is_private"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label for="is_private" class="text-sm text-gray-700">
                            Private community
                            <span class="text-gray-400">(only members can see content)</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-6">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                        {{ form.processing ? 'Saving...' : 'Save changes' }}
                    </button>
                    <p v-if="saved" class="text-sm text-green-600">Changes saved!</p>
                </div>
            </form>
        </div>

        <!-- Banner & Avatar -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-900 mb-5">Images</h2>
            <form @submit.prevent="saveImages">
                <div class="space-y-5">
                    <!-- Banner -->
                    <div ref="generalCoverDropRef" class="rounded-lg transition-colors" :class="generalCoverDragging ? 'ring-2 ring-indigo-300 bg-indigo-50 ring-dashed p-2 -m-2' : ''">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Banner Image</label>
                        <div
                            v-if="coverPreview || (community.cover_image && !coverRemoved)"
                            class="relative mb-2 h-32 rounded-xl overflow-hidden border border-gray-200 group"
                        >
                            <img :src="coverPreview || community.cover_image" class="w-full h-full object-cover" alt="Banner preview" />
                            <button
                                type="button"
                                @click="removeCover"
                                class="absolute top-2 right-2 w-7 h-7 rounded-full bg-black/50 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-black/70"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <label class="flex items-center gap-2 w-fit cursor-pointer px-3.5 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            {{ coverPreview || (community.cover_image && !coverRemoved) ? 'Change banner' : 'Upload or drag & drop banner' }}
                            <input ref="coverInput" type="file" accept="image/*" class="hidden" @change="onCoverChange" />
                        </label>
                        <p class="mt-1 text-xs text-gray-400">JPG, PNG, WebP — max 15 MB &nbsp;·&nbsp; <span class="font-medium text-gray-500">Recommended: {{ IMAGE_DIMENSIONS.BANNER.width }} × {{ IMAGE_DIMENSIONS.BANNER.height }} px</span></p>
                        <p v-if="generalCoverDragging" class="text-xs text-indigo-500 font-medium mt-1">Drop image here</p>
                        <p v-if="imageForm.errors.cover_image" class="mt-1 text-xs text-red-600">{{ imageForm.errors.cover_image }}</p>
                    </div>

                    <!-- Avatar -->
                    <div ref="generalAvatarDropRef" class="rounded-lg transition-colors" :class="generalAvatarDragging ? 'ring-2 ring-indigo-300 bg-indigo-50 ring-dashed p-2 -m-2' : ''">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Community Avatar</label>
                        <div
                            v-if="avatarPreview || (community.avatar && !avatarRemoved)"
                            class="relative mb-2 w-20 h-20 rounded-full overflow-hidden border border-gray-200 group"
                        >
                            <img :src="avatarPreview || community.avatar" class="w-full h-full object-cover" alt="Avatar preview" />
                            <button
                                type="button"
                                @click="removeAvatar"
                                class="absolute inset-0 bg-black/40 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <label class="flex items-center gap-2 w-fit cursor-pointer px-3.5 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            {{ avatarPreview || (community.avatar && !avatarRemoved) ? 'Change avatar' : 'Upload or drag & drop avatar' }}
                            <input ref="avatarInput" type="file" accept="image/*" class="hidden" @change="onAvatarChange" />
                        </label>
                        <p class="mt-1 text-xs text-gray-400">Shown as your community icon. JPG, PNG, WebP — max 15 MB &nbsp;·&nbsp; <span class="font-medium text-gray-500">Recommended: {{ IMAGE_DIMENSIONS.AVATAR.width }} × {{ IMAGE_DIMENSIONS.AVATAR.height }} px</span></p>
                        <p v-if="imageForm.errors.avatar" class="mt-1 text-xs text-red-600">{{ imageForm.errors.avatar }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-6">
                    <button
                        type="submit"
                        :disabled="imageForm.processing"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                        {{ imageForm.processing ? 'Saving...' : 'Save images' }}
                    </button>
                    <p v-if="imagesSaved" class="text-sm text-green-600">Images saved!</p>
                </div>
            </form>
        </div>

        <!-- Brand Source of Truth -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
            <button type="button" @click="brandExpanded = !brandExpanded" class="w-full flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Brand Source of Truth</h2>
                    <p class="text-sm text-gray-500 mt-0.5 text-left">Define your brand identity so AI generates cohesive, on-brand content.</p>
                </div>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': brandExpanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <form v-show="brandExpanded" @submit.prevent="saveBrand" class="mt-5 space-y-6">
                <!-- 1. Brand Identity & Voice -->
                <fieldset>
                    <legend class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Brand Identity &amp; Voice
                    </legend>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Brand Personality</label>
                            <input v-model="brandForm.brand_context.brand_personality" type="text" maxlength="500" placeholder='e.g. "Professional yet gritty", "Hyper-energetic", "Nurturing and calm"' class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                            <p class="mt-0.5 text-xs text-gray-400">3-5 adjectives that define your community's vibe.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Target Audience Persona</label>
                            <textarea v-model="brandForm.brand_context.target_audience" maxlength="1000" rows="2" placeholder='e.g. "Filipino side-hustlers aged 25-40 looking for faceless marketing tips"' class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tone of Voice</label>
                            <select v-model="brandForm.brand_context.tone_of_voice" class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Select tone...</option>
                                <option value="first_person">First person — "I help you..."</option>
                                <option value="we">Community — "We learn together..."</option>
                                <option value="formal">Formal third-person — "The community offers..."</option>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <!-- 2. Hook Metadata -->
                <fieldset>
                    <legend class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Hook Metadata
                    </legend>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Core Value Proposition</label>
                            <input v-model="brandForm.brand_context.value_proposition" type="text" maxlength="500" placeholder='e.g. "We help parents of children with autism regain peace at home through proven routines."' class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                            <p class="mt-0.5 text-xs text-gray-400">One sentence explaining the transformation you offer.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Primary Keywords</label>
                            <input v-model="brandForm.brand_context.primary_keywords" type="text" maxlength="500" placeholder='e.g. "faceless marketing, Canva templates, passive income, digital products, Filipino entrepreneurs"' class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                            <p class="mt-0.5 text-xs text-gray-400">5-10 comma-separated keywords. AI weaves these into descriptions and alt-text.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">The Big Problem</label>
                            <textarea v-model="brandForm.brand_context.big_problem" maxlength="1000" rows="2" placeholder="Describe the specific pain point your community solves. AI uses this to create agitation copy." class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                    </div>
                </fieldset>

                <!-- 3. Visual Context -->
                <fieldset>
                    <legend class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                        Visual Context
                    </legend>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Color Palette</label>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Primary</label>
                                    <div class="flex items-center gap-2">
                                        <input v-model="brandForm.brand_context.color_primary" type="color" class="w-8 h-8 rounded border border-gray-300 cursor-pointer p-0.5" />
                                        <input v-model="brandForm.brand_context.color_primary" type="text" maxlength="7" placeholder="#6366f1" class="flex-1 rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Secondary</label>
                                    <div class="flex items-center gap-2">
                                        <input v-model="brandForm.brand_context.color_secondary" type="color" class="w-8 h-8 rounded border border-gray-300 cursor-pointer p-0.5" />
                                        <input v-model="brandForm.brand_context.color_secondary" type="text" maxlength="7" placeholder="#8b5cf6" class="flex-1 rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Accent</label>
                                    <div class="flex items-center gap-2">
                                        <input v-model="brandForm.brand_context.color_accent" type="color" class="w-8 h-8 rounded border border-gray-300 cursor-pointer p-0.5" />
                                        <input v-model="brandForm.brand_context.color_accent" type="text" maxlength="7" placeholder="#f59e0b" class="flex-1 rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Visual Style Guide</label>
                            <input v-model="brandForm.brand_context.visual_style" type="text" maxlength="500" placeholder='e.g. "Minimalist, high-contrast, tech-focused" or "Warm, organic, lifestyle photography"' class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Logo &amp; Asset Rules</label>
                            <input v-model="brandForm.brand_context.logo_rules" type="text" maxlength="500" placeholder='e.g. "Top left, never overlapping faces"' class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                    </div>
                </fieldset>

                <!-- 4. Conversion & Action Data -->
                <fieldset>
                    <legend class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Conversion &amp; Action Data
                    </legend>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Primary CTA Goal</label>
                            <input v-model="brandForm.brand_context.cta_goal" type="text" maxlength="300" placeholder='e.g. "Join the community", "Download the free PDF", "Book a discovery call"' class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Offer Details</label>
                            <input v-model="brandForm.brand_context.offer_details" type="text" maxlength="500" placeholder='e.g. "Free 7-day trial, then P499/month" or "One-time access at P2,499"' class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                            <p class="mt-0.5 text-xs text-gray-400">AI includes this in banners and CTA sections.</p>
                        </div>
                    </div>
                </fieldset>

                <!-- 5. Social / SEO -->
                <fieldset>
                    <legend class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Social &amp; SEO
                    </legend>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Social Share Description</label>
                        <textarea v-model="brandForm.brand_context.social_share_description" maxlength="300" rows="2" placeholder="A short TL;DR of your community for Open Graph tags and AI search engines." class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        <p class="mt-0.5 text-xs text-gray-400">Used for OG tags and GenAI search snippets (max 300 chars).</p>
                    </div>
                </fieldset>

                <!-- Save -->
                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="brandForm.processing"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                        {{ brandForm.processing ? 'Saving...' : 'Save brand context' }}
                    </button>
                    <p v-if="brandSaved" class="text-sm text-green-600">Brand context saved!</p>
                </div>
            </form>
        </div>

        <!-- Gallery Images -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6">
            <div class="flex items-center justify-between mb-1">
                <h2 class="text-base font-semibold text-gray-900">Gallery</h2>
                <button
                    v-if="isPro"
                    type="button"
                    :disabled="aiGalleryGenerating || (community.gallery_images?.length ?? 0) >= 8"
                    @click="startAiGalleryGeneration"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
                    :class="aiGalleryGenerating || (community.gallery_images?.length ?? 0) >= 8
                        ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                        : 'bg-gradient-to-r from-purple-600 to-indigo-600 text-white hover:from-purple-700 hover:to-indigo-700 shadow-sm'"
                >
                    <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': aiGalleryGenerating }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path v-if="!aiGalleryGenerating" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    <template v-if="aiGalleryGenerating">Generating...</template>
                    <template v-else>AI Generate</template>
                </button>
                <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-400 bg-gray-50 border border-gray-200 rounded-lg cursor-default" title="Upgrade to PRO to use AI gallery generation">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    AI Generate
                    <span class="text-[10px] font-bold text-amber-500 ml-0.5">PRO</span>
                </span>
            </div>
            <p class="text-sm text-gray-500 mb-4">Add up to 8 images shown as a thumbnail strip on your About page.</p>

            <!-- AI generation progress -->
            <div v-if="aiGalleryGenerating" class="mb-4 p-3 bg-indigo-50 border border-indigo-200 rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-4 h-4 text-indigo-600 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span class="text-sm font-medium text-indigo-700">Generating image {{ aiGalleryProgress + 1 }} of {{ aiGalleryTotal }}... ~10-20s per image.</span>
                </div>
                <div class="w-full bg-indigo-200 rounded-full h-1.5">
                    <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-500" :style="{ width: (aiGalleryTotal > 0 ? (aiGalleryProgress / aiGalleryTotal) * 100 : 0) + '%' }"></div>
                </div>
            </div>

            <!-- AI generation error -->
            <p v-if="aiGalleryError" class="mb-3 text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ aiGalleryError }}</p>

            <!-- Existing gallery (drag to reorder) -->
            <div v-if="community.gallery_images?.length" class="flex flex-wrap gap-2 mb-4">
                <div
                    v-for="(img, i) in community.gallery_images"
                    :key="img"
                    draggable="true"
                    @dragstart="onDragStart(i)"
                    @dragover="(e) => onDragOver(e, i)"
                    @dragleave="onDragLeave"
                    @drop="onDrop(i)"
                    @dragend="onDragEnd"
                    class="relative w-24 h-16 rounded-lg overflow-hidden border-2 group cursor-grab active:cursor-grabbing transition-all"
                    :class="[
                        dropIndex === i && dragIndex !== i ? 'border-indigo-400 scale-105' : 'border-gray-200',
                        dragIndex === i ? 'opacity-40' : 'opacity-100',
                    ]"
                >
                    <img :src="img" class="w-full h-full object-cover pointer-events-none" />
                    <span class="absolute bottom-0.5 left-0.5 bg-black/50 text-white text-[10px] leading-none px-1 py-0.5 rounded font-medium">{{ i + 1 }}</span>
                    <button
                        type="button"
                        @click="removeGalleryImage(i)"
                        class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-xs"
                    >✕</button>
                </div>
            </div>
            <p v-if="community.gallery_images?.length > 1" class="text-xs text-gray-400 mb-3 -mt-2">Drag images to reorder.</p>

            <!-- Upload new -->
            <form v-if="!community.gallery_images || community.gallery_images.length < 8" @submit.prevent="uploadGalleryImage">
                <div
                    ref="galleryDropRef"
                    class="flex items-center gap-3 rounded-lg transition-colors"
                    :class="galleryDragging ? 'ring-2 ring-indigo-300 bg-indigo-50 ring-dashed p-2 -m-2' : ''"
                >
                    <label class="flex items-center gap-2 cursor-pointer px-3.5 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        {{ galleryDragging ? 'Drop image here' : (galleryFile ? galleryFile.name : 'Choose or drag & drop image') }}
                        <input type="file" accept="image/*" class="hidden" @change="onGalleryFileChange" />
                    </label>
                    <button
                        type="submit"
                        :disabled="!galleryFile || galleryUploading"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                    >
                        {{ galleryUploading ? 'Uploading...' : 'Add image' }}
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-400">JPG, PNG, WebP — max 15 MB · {{ community.gallery_images?.length ?? 0 }}/8 images &nbsp;·&nbsp; <span class="font-medium text-gray-500">Recommended: 1200 × 800 px</span></p>
                <p v-if="galleryForm.errors.image" class="mt-1 text-xs text-red-600">{{ galleryForm.errors.image }}</p>
            </form>
            <p v-else class="text-xs text-amber-600">Maximum 8 images reached. Remove one to add more.</p>
        </div>
    </CommunitySettingsLayout>
</template>
