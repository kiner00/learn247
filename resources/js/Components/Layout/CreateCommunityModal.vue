<template>
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="showCreateModal"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="closeCreateModal()"
        >
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">

                <!-- Header -->
                <div class="px-6 pt-6 pb-4 border-b border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Create a Community</h2>
                        <button @click="closeCreateModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <!-- Step indicators -->
                    <div class="flex items-center gap-2">
                        <template v-for="n in 3" :key="n">
                            <div class="flex items-center gap-2 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <div
                                        class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0 transition-colors"
                                        :class="createStep > n ? 'bg-indigo-600 text-white' : createStep === n ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-400'"
                                    >
                                        <svg v-if="createStep > n" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <span v-else>{{ n }}</span>
                                    </div>
                                    <span
                                        class="text-xs font-medium hidden sm:block"
                                        :class="createStep >= n ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400'"
                                    >{{ ['Basics', 'Branding', 'Pricing'][n - 1] }}</span>
                                </div>
                                <div v-if="n < 3" class="flex-1 h-px" :class="createStep > n ? 'bg-indigo-300' : 'bg-gray-200 dark:bg-gray-700'" />
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Step content -->
                <form @submit.prevent="createCommunity">
                    <div class="px-6 py-5 space-y-4">

                        <!-- Step 1: Basics -->
                        <template v-if="createStep === 1">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Community name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="createForm.name"
                                    type="text"
                                    autofocus
                                    placeholder="e.g. PH Developers"
                                    class="w-full px-3.5 py-2.5 border rounded-lg text-sm bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    :class="createForm.errors.name ? 'border-red-400' : 'border-gray-300 dark:border-gray-600'"
                                />
                                <p v-if="createForm.errors.name" class="mt-1 text-xs text-red-600">{{ createForm.errors.name }}</p>
                                <p v-else-if="createSlugPreview" class="mt-1 text-xs text-gray-400">
                                    URL: <span class="font-mono text-gray-500">curzzo.com/communities/{{ createSlugPreview }}</span>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                                <textarea
                                    v-model="createForm.description"
                                    rows="3"
                                    placeholder="What is this community about?"
                                    class="w-full px-3.5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Category</label>
                                <select
                                    v-model="createForm.category"
                                    class="w-full px-3.5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 dark:text-gray-200"
                                >
                                    <option value="">No category</option>
                                    <option v-for="cat in CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
                                </select>
                            </div>
                        </template>

                        <!-- Step 2: Branding -->
                        <template v-if="createStep === 2">
                            <!-- Banner / Cover -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Banner image <span class="text-red-500">*</span> <span class="text-gray-400 font-normal">(recommended: {{ IMAGE_DIMENSIONS.BANNER.width }}×{{ IMAGE_DIMENSIONS.BANNER.height }})</span>
                                </label>
                                <div
                                    ref="bannerDropRef"
                                    class="relative w-full aspect-3/1 rounded-xl overflow-hidden border-2 border-dashed bg-gray-50 dark:bg-gray-700 flex items-center justify-center cursor-pointer group transition-colors"
                                    :class="bannerDragging ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-indigo-400'"
                                    @click="coverInputC.click()"
                                >
                                    <img v-if="coverPreviewC" :src="coverPreviewC" class="absolute inset-0 w-full h-full object-cover" />
                                    <div v-if="!coverPreviewC" class="flex flex-col items-center gap-1.5 text-gray-400 group-hover:text-indigo-500 transition-colors">
                                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="text-xs font-medium">{{ bannerDragging ? 'Drop image here' : 'Click or drag & drop banner' }}</span>
                                    </div>
                                    <button v-if="coverPreviewC" type="button"
                                        class="absolute top-2 right-2 w-6 h-6 bg-black/60 hover:bg-black/80 text-white rounded-full flex items-center justify-center"
                                        @click.stop="coverPreviewC = null; createForm.cover_image = null; coverInputC.value = ''">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                    <input ref="coverInputC" type="file" accept="image/*" class="hidden" @change="onCreateCoverChange" />
                                </div>
                                <p v-if="coverSizeError" class="mt-1 text-xs text-red-600">{{ coverSizeError }}</p>
                                <p v-else-if="coverRatioError" class="mt-1 text-xs text-red-600">{{ coverRatioError }}</p>
                                <p v-else-if="!coverPreviewC && createStep === 2 && createForm.errors.cover_image" class="mt-1 text-xs text-red-600">{{ createForm.errors.cover_image }}</p>
                            </div>

                            <!-- Avatar -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Community avatar <span class="text-gray-400 font-normal">(square, min {{ IMAGE_DIMENSIONS.AVATAR.width }}×{{ IMAGE_DIMENSIONS.AVATAR.height }})</span>
                                </label>
                                <div class="flex items-center gap-4">
                                    <div
                                        ref="avatarDropRefC"
                                        class="relative w-20 h-20 rounded-2xl overflow-hidden border-2 border-dashed bg-gray-50 dark:bg-gray-700 flex items-center justify-center cursor-pointer group transition-colors shrink-0"
                                        :class="avatarDraggingC ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-indigo-400'"
                                        @click="avatarInputC.click()"
                                    >
                                        <img v-if="avatarPreviewC" :src="avatarPreviewC" class="absolute inset-0 w-full h-full object-cover" />
                                        <svg v-else class="w-6 h-6 text-gray-300 group-hover:text-indigo-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                        <input ref="avatarInputC" type="file" accept="image/*" class="hidden" @change="onCreateAvatarChange" />
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <p>This shows as the community icon in search and navigation.</p>
                                        <button v-if="avatarPreviewC" type="button" class="text-xs text-red-500 hover:text-red-600 mt-1"
                                            @click="avatarPreviewC = null; createForm.avatar = null; avatarInputC.value = ''">
                                            Remove
                                        </button>
                                        <button v-else type="button" class="text-xs text-indigo-600 hover:text-indigo-700 mt-1" @click="avatarInputC.click()">
                                            Upload avatar
                                        </button>
                                    </div>
                                </div>
                                <p v-if="avatarSizeError" class="mt-1.5 text-xs text-red-600">{{ avatarSizeError }}</p>
                            </div>
                        </template>

                        <!-- Step 3: Pricing & Settings -->
                        <template v-if="createStep === 3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Membership type</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <label
                                        class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors"
                                        :class="createForm.price == 0 || createForm.price === '' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'"
                                    >
                                        <input type="radio" class="hidden" :checked="createForm.price == 0 || createForm.price === ''" @change="createForm.price = ''" />
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Free</p>
                                            <p class="text-xs text-gray-500">Anyone can join</p>
                                        </div>
                                    </label>
                                    <label
                                        class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors"
                                        :class="createForm.price > 0 ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'"
                                    >
                                        <input type="radio" class="hidden" :checked="createForm.price > 0" @change="createForm.price = 499" />
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Paid</p>
                                            <p class="text-xs text-gray-500">Charge for access</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div v-if="createForm.price > 0 || (createForm.price !== '' && createForm.price != 0)" class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Price (₱)</label>
                                    <input
                                        v-model="createForm.price"
                                        type="number"
                                        min="1"
                                        step="1"
                                        placeholder="e.g. 499"
                                        class="w-full px-3.5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Billing</label>
                                    <select
                                        v-model="createForm.billing_type"
                                        class="w-full px-3.5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 dark:text-gray-200"
                                    >
                                        <option value="monthly">Monthly</option>
                                        <option value="one_time">One-time</option>
                                    </select>
                                </div>
                            </div>

                            <div v-if="createForm.price > 0 || (createForm.price !== '' && createForm.price != 0)">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Affiliate commission <span class="text-gray-400 font-normal">(%)</span>
                                </label>
                                <input
                                    v-model="createForm.affiliate_commission_rate"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="1"
                                    placeholder="e.g. 20"
                                    class="w-full px-3.5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                />
                                <p class="mt-1 text-xs text-gray-400">Commission paid to affiliates who refer paying members.</p>
                            </div>

                            <div class="pt-1">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <div class="relative">
                                        <input v-model="createForm.is_private" type="checkbox" class="sr-only peer" />
                                        <div class="w-9 h-5 bg-gray-200 dark:bg-gray-600 peer-checked:bg-indigo-600 rounded-full transition-colors"></div>
                                        <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Private community</p>
                                        <p class="text-xs text-gray-400">Only invited members can join</p>
                                    </div>
                                </label>
                            </div>
                        </template>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 pb-6 flex gap-3">
                        <button
                            type="button"
                            @click="createStep > 1 ? createStep-- : closeAndReset()"
                            class="flex-1 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            {{ createStep > 1 ? 'Back' : 'Cancel' }}
                        </button>
                        <button
                            v-if="createStep < 3"
                            type="button"
                            :disabled="(createStep === 1 && !createForm.name.trim()) || (createStep === 2 && (!createForm.cover_image || coverRatioError || coverSizeError || avatarSizeError))"
                            class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            @click="createStep++"
                        >
                            Next
                        </button>
                        <button
                            v-else
                            type="submit"
                            :disabled="createForm.processing"
                            class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                        >
                            {{ createForm.processing ? 'Creating...' : 'Create community' }}
                        </button>
                    </div>
                    <div v-if="Object.keys(createForm.errors).length" class="mt-3 p-3 bg-red-50 rounded-lg">
                        <p v-for="(error, field) in createForm.errors" :key="field" class="text-xs text-red-600">{{ field }}: {{ error }}</p>
                    </div>
                </form>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useCreateModal } from '@/composables/useCreateModal';
import { IMAGE_DIMENSIONS } from '@/constants';
import { useDropzone } from '@/composables/useDropzone';

const CATEGORIES = ['Tech', 'Business', 'Design', 'Health', 'Education', 'Finance', 'Other'];

const { showCreateModal, closeCreateModal } = useCreateModal();

const createStep      = ref(1);
const coverPreviewC   = ref(null);
const avatarPreviewC  = ref(null);
const coverInputC     = ref(null);
const avatarInputC    = ref(null);
const coverRatioError = ref(null);
const coverSizeError  = ref(null);
const avatarSizeError = ref(null);
const MAX_FILE_SIZE   = 15 * 1024 * 1024; // 15 MB

const createForm = useForm({
    name:                      '',
    description:               '',
    category:                  '',
    price:                     '',
    billing_type:              'monthly',
    currency:                  'PHP',
    is_private:                false,
    affiliate_commission_rate: '',
    cover_image:               null,
    avatar:                    null,
});

const createSlugPreview = computed(() =>
    createForm.name ? createForm.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') : ''
);

function closeAndReset() {
    closeCreateModal();
    createStep.value = 1;
    coverPreviewC.value  = null;
    avatarPreviewC.value = null;
    createForm.reset();
}

const bannerDropRef = ref(null);
const avatarDropRefC = ref(null);
const { isDragging: bannerDragging } = useDropzone(bannerDropRef, files => onCreateCoverChange(files[0]), { accept: 'image/*' });
const { isDragging: avatarDraggingC } = useDropzone(avatarDropRefC, files => onCreateAvatarChange(files[0]), { accept: 'image/*' });

function onCreateCoverChange(e) {
    const file = e instanceof File ? e : e.target.files[0];
    if (!file) return;
    coverRatioError.value = null;
    coverSizeError.value  = null;
    if (file.size > MAX_FILE_SIZE) {
        coverSizeError.value = 'Banner image must be under 15 MB.';
        coverInputC.value.value = '';
        return;
    }
    createForm.cover_image = file;
    coverPreviewC.value = URL.createObjectURL(file);
}

function onCreateAvatarChange(e) {
    const file = e instanceof File ? e : e.target.files[0];
    if (!file) return;
    avatarSizeError.value = null;
    if (file.size > MAX_FILE_SIZE) {
        avatarSizeError.value = 'Avatar must be under 15 MB.';
        avatarInputC.value.value = '';
        return;
    }
    createForm.avatar = file;
    avatarPreviewC.value = URL.createObjectURL(file);
}

function createCommunity() {
    createForm.post('/communities', {
        forceFormData: true,
        onSuccess: () => closeAndReset(),
    });
}
</script>
