<template>
    <AppLayout :title="`${community.name} · Certifications`" :community="community">
        <CommunityTabs :community="community" active-tab="certifications" />

        <!-- Header row -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-1 bg-gray-100 rounded-xl p-1">
                <button
                    @click="activeTab = 'exams'"
                    :class="['px-4 py-1.5 rounded-lg text-sm font-semibold transition-all',
                        activeTab === 'exams' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700']"
                >
                    Exams
                    <span class="ml-1.5 text-xs font-normal text-gray-400">{{ certifications.length }}</span>
                </button>
                <button
                    @click="activeTab = 'certificates'"
                    :class="['px-4 py-1.5 rounded-lg text-sm font-semibold transition-all',
                        activeTab === 'certificates' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700']"
                >
                    Certificates
                    <span v-if="issuedCertificates.length" class="ml-1.5 text-xs font-normal text-gray-400">{{ issuedCertificates.length }}</span>
                </button>
            </div>
            <button
                v-if="isOwner && activeTab === 'exams' && !showBuilder"
                @click="initBuilder()"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors"
            >
                + New Certification
            </button>
        </div>

        <!-- ─── Exams Tab ──────────────────────────────────────────────── -->
        <div v-if="activeTab === 'exams'">

            <!-- Exam Builder (owner) -->
            <div v-if="showBuilder && isOwner" class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm mb-6">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-gray-900">{{ editingCert ? 'Edit Certification Exam' : 'New Certification Exam' }}</h3>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Exam Title <span class="text-gray-400 font-normal">(shown before taking)</span></label>
                            <input v-model="builderForm.title" type="text" placeholder="e.g. AI Beginner Cert Level 1"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Certificate Title <span class="text-gray-400 font-normal">(on certificate)</span></label>
                            <input v-model="builderForm.cert_title" type="text" placeholder="e.g. Certified AI Beginner"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Certificate Description <span class="text-gray-400 font-normal">(optional, shown on certificate)</span></label>
                        <textarea v-model="builderForm.description" rows="2" placeholder="Brief description displayed on the certificate..."
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Certificate Cover Image <span class="text-gray-400 font-normal">(optional, recommended: 1200×400)</span></label>
                        <div v-if="coverPreview" class="mb-2 flex items-start gap-3">
                            <img :src="coverPreview" alt="Cover preview" class="h-24 rounded-xl object-cover border border-gray-100" />
                            <button @click="removeCover" type="button" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                        </div>
                        <input v-else type="file" accept="image/*" @change="onCoverChange"
                            class="w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100" />
                    </div>

                    <!-- Community Logo -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Community Logo <span class="text-gray-400 font-normal">(shown on certificate, square, min 200×200)</span></label>
                        <div class="flex items-center gap-3">
                            <div class="w-14 h-14 rounded-xl overflow-hidden border border-gray-200 bg-gray-50 flex items-center justify-center shrink-0">
                                <img v-if="community.avatar" :src="community.avatar" class="w-full h-full object-cover" />
                                <span v-else class="text-lg font-bold text-indigo-600">{{ community.name.charAt(0).toUpperCase() }}</span>
                            </div>
                            <p class="text-xs text-gray-400">The community avatar is used as the logo on certificates. You can change it in the <a :href="`/communities/${community.slug}/about`" class="text-indigo-500 hover:underline">About</a> settings.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-6">
                        <div class="flex items-center gap-3">
                            <label class="text-xs text-gray-600 font-semibold shrink-0">Pass Score (%)</label>
                            <input v-model.number="builderForm.pass_score" type="number" min="50" max="100"
                                class="w-24 px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <span class="text-xs text-gray-400">min 50%</span>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="builderForm.randomize_questions" class="accent-indigo-600 w-4 h-4" />
                            <span class="text-xs text-gray-600 font-semibold">Randomize Questions</span>
                        </label>
                    </div>

                    <!-- Pricing -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Price <span class="text-gray-400 font-normal">(0 = free)</span></label>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-500">₱</span>
                                <input v-model.number="builderForm.price" type="number" min="0" step="0.01" placeholder="0"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Affiliate Commission <span class="text-gray-400 font-normal">(%)</span></label>
                            <div class="flex items-center gap-2">
                                <input v-model.number="builderForm.affiliate_commission_rate" type="number" min="0" max="100" placeholder="0"
                                    :disabled="!builderForm.price || builderForm.price <= 0"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed" />
                                <span class="text-xs text-gray-400 shrink-0">max 100%</span>
                            </div>
                            <p v-if="builderForm.price > 0 && builderForm.affiliate_commission_rate > 0" class="text-xs text-gray-400 mt-1">
                                Affiliate earns ₱{{ (builderForm.price * builderForm.affiliate_commission_rate / 100).toFixed(2) }} per sale
                            </p>
                        </div>
                    </div>

                    <!-- Questions -->
                    <div>
                        <p class="text-xs font-semibold text-gray-700 mb-3">Questions</p>
                        <div v-if="builderForm.questions.length === 0" class="text-center py-6 text-sm text-gray-400 border border-dashed border-gray-200 rounded-xl">
                            No questions yet. Click "+ Add Question" below.
                        </div>
                        <div v-for="(q, qi) in builderForm.questions" :key="qi" class="p-4 bg-gray-50 rounded-xl border border-gray-200 space-y-2 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-gray-500 shrink-0">Q{{ qi + 1 }}</span>
                                <input v-model="q.question" type="text" placeholder="Question text"
                                    class="flex-1 px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <select v-model="q.type" class="px-2 py-1.5 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="multiple_choice">Multiple Choice</option>
                                    <option value="true_false">True / False</option>
                                </select>
                                <button @click="builderForm.questions.splice(qi, 1)" class="text-red-400 hover:text-red-600 text-xs shrink-0">x</button>
                            </div>
                            <div v-for="(opt, oi) in q.options" :key="oi" class="flex items-center gap-2 pl-6">
                                <input type="radio" :name="`builder_q_${qi}`" @change="setCorrect(qi, oi)"
                                    :checked="opt.is_correct" class="accent-indigo-600" title="Mark as correct answer" />
                                <input v-model="opt.label" type="text" :placeholder="`Option ${oi + 1}`"
                                    class="flex-1 px-2.5 py-1 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <button v-if="q.options.length > 2" @click="q.options.splice(oi, 1)" class="text-red-400 text-xs">x</button>
                            </div>
                            <button @click="q.options.push({ label: '', is_correct: false })"
                                class="text-xs text-indigo-500 hover:text-indigo-700 pl-6 font-medium">+ Add Option</button>
                        </div>

                        <button @click="addQuestion"
                            class="w-full py-2 border border-dashed border-indigo-300 text-sm text-indigo-500 hover:text-indigo-700 rounded-xl">
                            + Add Question
                        </button>
                    </div>

                    <div class="flex gap-3 justify-end pt-2 border-t border-gray-100">
                        <button @click="cancelBuilder" type="button" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                        <button @click="saveExam" :disabled="builderForm.processing"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                            {{ builderForm.processing ? 'Saving...' : 'Save Exam' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Certification list -->
            <div v-if="!showBuilder && certifications.length" class="space-y-4">
                <div v-for="cert in certifications" :key="cert.id"
                    class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">

                    <!-- Cert header -->
                    <div class="px-6 py-5">
                        <div class="flex items-start gap-4">
                            <div v-if="cert.cover_image" class="shrink-0">
                                <img :src="cert.cover_image" alt="Exam cover" class="w-24 h-16 rounded-xl object-cover border border-gray-100" />
                            </div>
                            <div class="flex-1">
                                <span class="text-xs font-semibold text-amber-600 uppercase tracking-wide">Certification Exam</span>
                                <h2 class="text-xl font-black text-gray-900 mt-0.5">{{ cert.title }}</h2>
                                <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                                    <span>{{ cert.questions_count }} questions</span>
                                    <span>Pass score: {{ cert.pass_score }}%</span>
                                    <span v-if="cert.price > 0" class="font-semibold text-amber-600">₱{{ Number(cert.price).toLocaleString() }}</span>
                                    <span v-else class="font-semibold text-green-600">Free</span>
                                </div>
                                <p v-if="cert.description" class="text-sm text-gray-500 mt-2">{{ cert.description }}</p>
                            </div>
                            <div class="shrink-0 flex items-center gap-2">
                                <span v-if="attempts[cert.id]?.passed" class="text-sm font-bold text-green-700 bg-green-50 border border-green-200 px-3 py-1.5 rounded-xl">Passed</span>
                                <span v-else-if="attempts[cert.id]" class="text-sm font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-xl">{{ attempts[cert.id].score }}%</span>
                                <template v-if="isOwner">
                                    <button @click="initBuilder(cert)" class="text-xs text-indigo-500 hover:text-indigo-700 border border-indigo-200 px-2.5 py-1 rounded-lg">Edit</button>
                                    <button @click="deleteExam(cert)" class="text-xs text-red-400 hover:text-red-600 border border-red-200 px-2.5 py-1 rounded-lg">Delete</button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Student: previous attempt / certificate link -->
                    <div v-if="!isOwner && attempts[cert.id]" class="px-6 pb-4">
                        <div class="p-4 rounded-xl border"
                            :class="attempts[cert.id].passed ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200'">
                            <p class="text-sm font-bold" :class="attempts[cert.id].passed ? 'text-green-800' : 'text-amber-800'">
                                {{ attempts[cert.id].passed ? 'You have passed this exam' : `Previous attempt: ${attempts[cert.id].score}%` }}
                            </p>
                            <p class="text-xs mt-0.5" :class="attempts[cert.id].passed ? 'text-green-600' : 'text-amber-600'">
                                {{ attempts[cert.id].passed ? `Completed on ${attempts[cert.id].completed_at}` : 'You can retake the exam below' }}
                            </p>
                            <a v-if="attempts[cert.id].passed && userCertificates[cert.id]?.uuid"
                                :href="`/certificates/${userCertificates[cert.id].uuid}`" target="_blank"
                                class="inline-flex items-center gap-2 mt-2 px-4 py-2 bg-amber-500 text-white text-sm font-bold rounded-xl hover:bg-amber-600 transition-colors">
                                View Certificate
                            </a>
                        </div>
                    </div>

                    <!-- Exam result (just submitted) -->
                    <div v-if="examResult && examResult.certification_id === cert.id" class="px-6 pb-4">
                        <div class="p-5 rounded-xl border shadow-sm"
                            :class="examResult.passed ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'">
                            <p class="font-black text-lg" :class="examResult.passed ? 'text-green-800' : 'text-red-800'">
                                {{ examResult.passed ? 'Congratulations! You passed!' : 'Not quite \u2014 keep studying!' }}
                            </p>
                            <p class="text-sm mt-1" :class="examResult.passed ? 'text-green-700' : 'text-red-700'">
                                Score: {{ examResult.score }}% ({{ examResult.correct }}/{{ examResult.total }} correct)
                            </p>
                            <div v-if="examResult.passed && examResult.certificate_uuid" class="mt-3">
                                <a :href="`/certificates/${examResult.certificate_uuid}`" target="_blank"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-500 text-white text-sm font-bold rounded-xl hover:bg-amber-600 transition-colors">
                                    View Your Certificate
                                </a>
                            </div>
                            <button v-if="!examResult.passed" @click="retakeExam(cert)"
                                class="mt-3 text-sm text-indigo-600 font-semibold hover:text-indigo-800">
                                Retake Exam
                            </button>
                        </div>
                    </div>

                    <!-- Take exam (student, not passed or retaking) -->
                    <div v-if="!isOwner && takingExamId === cert.id" class="border-t border-gray-100">
                        <div class="px-6 py-4 border-b border-gray-50">
                            <h3 class="font-bold text-gray-900">Answer all questions to submit</h3>
                        </div>
                        <div class="p-6 space-y-6">
                            <div v-for="(question, qi) in cert.questions" :key="question.id" class="space-y-2">
                                <p class="text-sm font-semibold text-gray-800">{{ qi + 1 }}. {{ question.question }}</p>
                                <div class="space-y-1.5">
                                    <label
                                        v-for="option in question.options"
                                        :key="option.id"
                                        class="flex items-center gap-3 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                                        :class="examAnswers[question.id] === option.id
                                            ? 'border-indigo-400 bg-indigo-50'
                                            : 'border-gray-200 hover:bg-gray-50'"
                                    >
                                        <input type="radio" :name="`q_${question.id}`" :value="option.id"
                                            v-model="examAnswers[question.id]" class="accent-indigo-600" />
                                        <span class="text-sm text-gray-700">{{ option.label }}</span>
                                    </label>
                                </div>
                            </div>

                            <button
                                @click="submitExam(cert)"
                                :disabled="examForm.processing || !allAnswered(cert)"
                                class="w-full py-3 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                            >
                                {{ examForm.processing ? 'Submitting...' : 'Submit Exam' }}
                            </button>
                        </div>
                    </div>

                    <!-- Take exam button (student, not currently taking) -->
                    <div v-if="!isOwner && takingExamId !== cert.id && (!examResult || examResult.certification_id !== cert.id || !examResult.passed)"
                        class="px-6 pb-5">
                        <template v-if="!attempts[cert.id]?.passed">
                            <!-- Paid cert: not yet purchased -->
                            <button
                                v-if="cert.price > 0 && !purchases[cert.id]"
                                @click="checkoutCert(cert)"
                                :disabled="checkoutLoading === cert.id"
                                class="px-5 py-2.5 bg-amber-500 text-white text-sm font-bold rounded-xl hover:bg-amber-600 disabled:opacity-50 transition-colors"
                            >
                                {{ checkoutLoading === cert.id ? 'Redirecting...' : `Pay ₱${Number(cert.price).toLocaleString()} & Take Exam` }}
                            </button>
                            <!-- Free or already purchased -->
                            <button
                                v-else
                                @click="startExam(cert)"
                                class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors"
                            >
                                {{ attempts[cert.id] ? 'Retake Exam' : 'Take Exam' }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="!showBuilder && !certifications.length" class="bg-white border border-gray-200 rounded-2xl p-16 text-center shadow-sm">
                <span class="text-4xl block mb-3">🏆</span>
                <p class="text-sm font-medium text-gray-700 mb-1">No certifications yet</p>
                <p class="text-xs text-gray-400">
                    {{ isOwner ? 'Create a certification exam for your community members.' : 'Certification exams will appear here once available.' }}
                </p>
            </div>
        </div>

        <!-- ─── Certificates Tab ───────────────────────────────────────── -->
        <div v-if="activeTab === 'certificates'">
            <div v-if="issuedCertificates.length" class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <p class="text-sm font-bold text-gray-700">Issued Certificates</p>
                    <span class="text-xs text-gray-400">{{ issuedCertificates.length }} total</span>
                </div>
                <div class="divide-y divide-gray-50">
                    <div v-for="cert in issuedCertificates" :key="cert.uuid"
                        class="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-50 transition-colors">
                        <template v-if="isOwner && cert.student_name">
                            <img v-if="cert.student_avatar" :src="cert.student_avatar"
                                class="w-9 h-9 rounded-full object-cover shrink-0 border border-gray-100" />
                            <div v-else
                                class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center shrink-0 text-sm font-bold text-indigo-600">
                                {{ cert.student_name.charAt(0).toUpperCase() }}
                            </div>
                        </template>
                        <div v-else class="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center shrink-0 text-base">🏆</div>

                        <div class="flex-1 min-w-0">
                            <p v-if="isOwner && cert.student_name" class="text-sm font-semibold text-gray-800 truncate">{{ cert.student_name }}</p>
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ cert.cert_title }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ cert.exam_title }} · {{ cert.issued_at }}</p>
                        </div>

                        <a :href="`/certificates/${cert.uuid}`" target="_blank"
                            class="shrink-0 px-3 py-1.5 text-xs font-semibold text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                            View
                        </a>
                    </div>
                </div>
            </div>
            <div v-else class="bg-white border border-gray-200 rounded-2xl p-16 text-center shadow-sm">
                <span class="text-4xl block mb-3">🏆</span>
                <p class="text-sm font-medium text-gray-700 mb-1">No certificates yet</p>
                <p class="text-xs text-gray-400">
                    {{ isOwner ? 'Certificates will appear here once students pass a certification exam.' : 'Complete a certification exam to earn your first certificate.' }}
                </p>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';

const props = defineProps({
    community:          Object,
    certifications:     Array,
    attempts:           Object,
    userCertificates:   Object,
    issuedCertificates: Array,
    canManage:          Boolean,
    purchases:          { type: Object, default: () => ({}) },
});

const page    = usePage();
const isOwner = props.canManage;

const activeTab = ref('exams');

// ─── Exam Builder ────────────────────────────────────────────────────────────
const showBuilder  = ref(false);
const editingCert  = ref(null);
const coverPreview = ref(null);

const builderForm = useForm({
    title:                     '',
    cert_title:                '',
    description:               '',
    cover_image:               null,
    pass_score:                70,
    randomize_questions:       false,
    price:                     0,
    affiliate_commission_rate: null,
    questions:                 [],
});

function initBuilder(cert = null) {
    editingCert.value = cert;
    if (cert) {
        builderForm.title                     = cert.title;
        builderForm.cert_title                = cert.cert_title;
        builderForm.description               = cert.description ?? '';
        builderForm.cover_image               = null;
        builderForm.pass_score                = cert.pass_score;
        builderForm.randomize_questions       = cert.randomize_questions;
        builderForm.price                     = cert.price ?? 0;
        builderForm.affiliate_commission_rate = cert.affiliate_commission_rate ?? null;
        builderForm.questions           = cert.questions.map((q) => ({
            question: q.question,
            type:     q.type,
            options:  q.options.map((o) => ({ label: o.label, is_correct: o.is_correct })),
        }));
        coverPreview.value = cert.cover_image ?? null;
    } else {
        builderForm.reset();
        builderForm.questions = [];
        coverPreview.value    = null;
    }
    showBuilder.value = true;
}

function cancelBuilder() {
    showBuilder.value = false;
    editingCert.value = null;
    builderForm.reset();
    builderForm.questions = [];
    coverPreview.value    = null;
}

function addQuestion() {
    builderForm.questions.push({
        question: '',
        type:     'multiple_choice',
        options:  [
            { label: '', is_correct: true },
            { label: '', is_correct: false },
        ],
    });
}

function setCorrect(qi, oi) {
    builderForm.questions[qi].options.forEach((o, i) => {
        o.is_correct = i === oi;
    });
}

function onCoverChange(event) {
    const file = event.target.files[0];
    if (!file) return;
    builderForm.cover_image = file;
    coverPreview.value = URL.createObjectURL(file);
}

function removeCover() {
    builderForm.cover_image = null;
    coverPreview.value      = null;
}

function saveExam() {
    const url = editingCert.value
        ? `/communities/${props.community.slug}/certifications/${editingCert.value.id}`
        : `/communities/${props.community.slug}/certifications`;

    builderForm
        .transform((data) => {
            const fd = new FormData();
            fd.append('title', data.title);
            fd.append('cert_title', data.cert_title);
            fd.append('description', data.description ?? '');
            fd.append('pass_score', data.pass_score);
            fd.append('randomize_questions', data.randomize_questions ? '1' : '0');
            fd.append('price', data.price ?? 0);
            if (data.affiliate_commission_rate != null) {
                fd.append('affiliate_commission_rate', data.affiliate_commission_rate);
            }
            if (data.cover_image) {
                fd.append('cover_image', data.cover_image);
            }
            data.questions.forEach((q, qi) => {
                fd.append(`questions[${qi}][question]`, q.question);
                fd.append(`questions[${qi}][type]`, q.type);
                q.options.forEach((o, oi) => {
                    fd.append(`questions[${qi}][options][${oi}][label]`, o.label);
                    fd.append(`questions[${qi}][options][${oi}][is_correct]`, o.is_correct ? '1' : '0');
                });
            });
            return fd;
        })
        .post(url, {
            forceFormData: true,
            onSuccess: () => {
                showBuilder.value = false;
                editingCert.value = null;
            },
        });
}

function deleteExam(cert) {
    if (!confirm('Delete this certification exam? All attempts and certificates will also be removed.')) return;
    router.delete(`/communities/${props.community.slug}/certifications/${cert.id}`, { preserveScroll: true });
}

// ─── Certification Checkout ──────────────────────────────────────────────────
const checkoutLoading = ref(null);

function checkoutCert(cert) {
    checkoutLoading.value = cert.id;
    router.post(
        `/communities/${props.community.slug}/certifications/${cert.id}/checkout`,
        {},
        {
            onError: () => { checkoutLoading.value = null; },
        }
    );
}

// ─── Exam Taking (student) ───────────────────────────────────────────────────
const takingExamId = ref(null);
const examAnswers  = ref({});
const examResult   = ref(null);
const examForm     = useForm({});

function startExam(cert) {
    takingExamId.value = cert.id;
    examAnswers.value  = {};
    examResult.value   = null;
}

function allAnswered(cert) {
    return cert.questions.every((q) => examAnswers.value[q.id] != null);
}

function submitExam(cert) {
    examForm
        .transform(() => ({ answers: examAnswers.value }))
        .post(
            `/communities/${props.community.slug}/certifications/${cert.id}/submit`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    const flash = page.props.flash?.cert_exam_result;
                    if (flash) {
                        examResult.value   = flash;
                        takingExamId.value = null;
                    }
                },
            }
        );
}

function retakeExam(cert) {
    examResult.value = null;
    startExam(cert);
}
</script>
