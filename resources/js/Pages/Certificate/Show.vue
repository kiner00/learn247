<template>
    <Head :title="`Certificate – ${certificate.cert_title}`" />

    <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-amber-50 flex items-center justify-center p-6">
        <div class="w-full max-w-2xl">

            <!-- Certificate card -->
            <div class="bg-white rounded-3xl shadow-2xl overflow-hidden border border-gray-100 print:shadow-none">

                <!-- Top banner -->
                <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 px-8 py-6 flex items-center justify-between">
                    <div>
                        <p class="text-indigo-200 text-xs font-semibold uppercase tracking-widest mb-1">Certificate of Completion</p>
                        <h1 class="text-white text-xl font-black">{{ certificate.exam_title }}</h1>
                    </div>
                    <img :src="'/brand/logo-new.png'" alt="Curzzo" class="h-10 object-contain brightness-0 invert" />
                </div>

                <!-- Cover image (optional) -->
                <div v-if="certificate.cover_image" class="w-full" style="aspect-ratio:16/6;">
                    <img :src="certificate.cover_image" alt="Certificate cover" class="w-full h-full object-cover" />
                </div>

                <!-- Body -->
                <div class="px-10 py-10 text-center">
                    <p class="text-gray-500 text-sm mb-4">This is to certify that</p>

                    <!-- Avatar + name -->
                    <div class="flex flex-col items-center mb-6">
                        <img
                            v-if="certificate.student_avatar"
                            :src="certificate.student_avatar"
                            :alt="certificate.student_name"
                            class="w-20 h-20 rounded-full object-cover border-4 border-indigo-100 mb-3"
                        />
                        <div v-else class="w-20 h-20 rounded-full bg-indigo-100 flex items-center justify-center text-3xl font-black text-indigo-600 mb-3">
                            {{ certificate.student_name.charAt(0).toUpperCase() }}
                        </div>
                        <h2 class="text-3xl font-black text-gray-900">{{ certificate.student_name }}</h2>
                    </div>

                    <p class="text-gray-500 text-sm mb-2">has successfully completed</p>
                    <h3 class="text-xl font-bold text-indigo-700 mb-2">{{ certificate.cert_title }}</h3>
                    <p v-if="certificate.description" class="text-sm text-gray-500 mb-6 max-w-md mx-auto">{{ certificate.description }}</p>
                    <div v-else class="mb-6" />

                    <div class="h-px bg-gray-100 mb-6" />

                    <p class="text-xs text-gray-400">Issued on {{ certificate.issued_at }}</p>
                    <p class="text-xs text-gray-300 mt-1 font-mono">{{ certificate.uuid }}</p>

                    <img :src="'/brand/logo-new.png'" alt="Curzzo" class="h-10 mx-auto mt-6 opacity-60" />
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-10 py-4 flex items-center justify-between border-t border-gray-100 print:hidden">
                    <span class="text-xs text-gray-400">Powered by Curzzo</span>
                    <div class="flex items-center gap-2">
                        <button
                            @click="shareToFacebook"
                            class="px-3 py-2 bg-[#1877F2] text-white text-xs font-semibold rounded-lg hover:bg-[#166FE5] transition-colors flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            Share to Facebook
                        </button>
                        <button
                            @click="shareToCommunity"
                            class="px-3 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition-colors flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Share to Community
                        </button>
                        <button
                            @click="print"
                            class="px-3 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition-colors"
                        >
                            Print / Save PDF
                        </button>
                    </div>
                </div>
            </div>

            <!-- Back link -->
            <div class="text-center mt-6 print:hidden">
                <a
                    :href="`/communities/${certificate.community_slug}/certifications`"
                    class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                >
                    &larr; Back to Certifications
                </a>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';

const props = defineProps({ certificate: Object });

function print() {
    window.print();
}

function shareToFacebook() {
    const url = window.location.href;
    window.open(
        `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`,
        '_blank',
        'width=600,height=400'
    );
}

function shareToCommunity() {
    const certUrl = window.location.href;
    const text = `I just earned my ${props.certificate.cert_title} certificate!`;
    window.location.href = `/communities/${props.certificate.community_slug}?share_certificate=${encodeURIComponent(certUrl)}&text=${encodeURIComponent(text)}`;
}
</script>

<style>
@media print {
    body { background: white; }
    .print\:hidden { display: none !important; }
    .print\:shadow-none { box-shadow: none !important; }

    /* Force browsers to print background colors & gradients */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }
}
</style>
