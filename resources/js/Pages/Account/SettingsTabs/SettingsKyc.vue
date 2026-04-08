<template>
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h2 class="text-base font-bold text-gray-900 mb-1">Identity Verification</h2>
        <p class="text-sm text-gray-400 mb-6">Verify your identity to make your communities visible on the directory.</p>

        <!-- Approved -->
        <div v-if="kyc?.status === 'approved'" class="rounded-xl border border-green-200 bg-green-50 p-4">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-sm font-semibold text-green-800">Your identity has been verified</span>
            </div>
            <p class="text-sm text-green-700 mt-1">Your communities are now visible on the directory.</p>
        </div>

        <!-- Submitted / Pending review -->
        <div v-else-if="kyc?.status === 'submitted'" class="rounded-xl border border-blue-200 bg-blue-50 p-4">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                <span class="text-sm font-semibold text-blue-800">Your documents are under review</span>
            </div>
            <p class="text-sm text-blue-700 mt-1">We'll notify you once verification is complete. This usually takes 1–2 business days.</p>
        </div>

        <!-- Rejected -->
        <div v-else>
            <div v-if="kyc?.status === 'rejected'" class="rounded-xl border border-red-200 bg-red-50 p-4 mb-6">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-sm font-semibold text-red-800">Verification was rejected</span>
                </div>
                <p v-if="kyc?.rejected_reason" class="text-sm text-red-700 mt-1">Reason: {{ kyc.rejected_reason }}</p>
                <p v-if="kyc?.ai_rejections >= 3" class="text-sm text-red-700 mt-1">Automatic verification has been unable to verify your documents. You can request a manual review by our team.</p>
                <p v-else class="text-sm text-red-700 mt-1">Please re-submit your documents below.</p>
            </div>

            <!-- Manual review button (after 3 AI rejections) -->
            <div v-if="kyc?.status === 'rejected' && kyc?.ai_rejections >= 3" class="max-w-lg">
                <button
                    @click="requestManualReview"
                    :disabled="manualReviewRequesting"
                    class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold rounded-lg tracking-wide transition-colors disabled:opacity-50 mb-4"
                >
                    {{ manualReviewRequesting ? 'Requesting...' : 'REQUEST MANUAL REVIEW' }}
                </button>
                <p class="text-xs text-gray-400 text-center">Or you can try re-submitting with clearer photos below.</p>
            </div>

            <!-- Upload form -->
            <form @submit.prevent="submitKyc" class="space-y-5 max-w-lg">
                <!-- ID Document -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Government-issued ID</label>
                    <p class="text-xs text-gray-400 mb-2">Upload a clear photo of your valid government ID (passport, driver's license, national ID).</p>
                    <div
                        ref="idDropRef"
                        class="border-2 border-dashed rounded-xl p-6 text-center transition-colors cursor-pointer"
                        :class="idDragging ? 'border-indigo-400 bg-indigo-50' : (idPreview ? 'border-green-300 bg-green-50' : 'border-gray-200 hover:border-indigo-300')"
                        @click="$refs.idInput.click()"
                    >
                        <img v-if="idPreview" :src="idPreview" class="mx-auto max-h-48 rounded-lg mb-2" />
                        <div v-else>
                            <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <p class="text-sm text-gray-500 mt-2">{{ idDragging ? 'Drop image here' : 'Click or drag & drop ID photo' }}</p>
                        </div>
                        <input ref="idInput" type="file" accept="image/*" class="hidden" @change="onIdChange" />
                    </div>
                    <p v-if="kycForm.errors.id_document" class="mt-1 text-xs text-red-600">{{ kycForm.errors.id_document }}</p>
                </div>

                <!-- Selfie with ID -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Selfie with ID</label>
                    <p class="text-xs text-gray-400 mb-2">Take a photo of yourself holding your ID next to your face. Make sure both your face and the ID are clearly visible.</p>
                    <div
                        ref="selfieDropRef"
                        class="border-2 border-dashed rounded-xl p-6 text-center transition-colors cursor-pointer"
                        :class="selfieDragging ? 'border-indigo-400 bg-indigo-50' : (selfiePreview ? 'border-green-300 bg-green-50' : 'border-gray-200 hover:border-indigo-300')"
                        @click="$refs.selfieInput.click()"
                    >
                        <img v-if="selfiePreview" :src="selfiePreview" class="mx-auto max-h-48 rounded-lg mb-2" />
                        <div v-else>
                            <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            <p class="text-sm text-gray-500 mt-2">{{ selfieDragging ? 'Drop image here' : 'Click or drag & drop selfie with ID' }}</p>
                        </div>
                        <input ref="selfieInput" type="file" accept="image/*" class="hidden" @change="onSelfieChange" />
                    </div>
                    <p v-if="kycForm.errors.selfie" class="mt-1 text-xs text-red-600">{{ kycForm.errors.selfie }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="kycForm.processing || !kycForm.id_document || !kycForm.selfie"
                    class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg tracking-wide transition-colors disabled:opacity-50"
                >
                    {{ kycForm.processing ? 'Submitting...' : 'SUBMIT FOR VERIFICATION' }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useDropzone } from '@/composables/useDropzone';

const props = defineProps({
    kyc: { type: Object, default: null },
});

const idPreview     = ref(null);
const selfiePreview = ref(null);

const kycForm = useForm({
    id_document: null,
    selfie:      null,
});

function onIdChange(e) {
    const file = e instanceof File ? e : e.target.files[0];
    if (!file) return;
    kycForm.id_document = file;
    idPreview.value = URL.createObjectURL(file);
}

function onSelfieChange(e) {
    const file = e instanceof File ? e : e.target.files[0];
    if (!file) return;
    kycForm.selfie = file;
    selfiePreview.value = URL.createObjectURL(file);
}

const idDropRef = ref(null);
const selfieDropRef = ref(null);
const { isDragging: idDragging } = useDropzone(idDropRef, files => onIdChange(files[0]), { accept: 'image/*' });
const { isDragging: selfieDragging } = useDropzone(selfieDropRef, files => onSelfieChange(files[0]), { accept: 'image/*' });

function submitKyc() {
    kycForm.post('/account/settings/kyc', { preserveScroll: true });
}

const manualReviewRequesting = ref(false);
function requestManualReview() {
    manualReviewRequesting.value = true;
    router.post('/account/settings/kyc/manual-review', {}, {
        preserveScroll: true,
        onFinish: () => { manualReviewRequesting.value = false; },
    });
}
</script>
