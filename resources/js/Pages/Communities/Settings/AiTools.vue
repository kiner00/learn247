<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';

const props = defineProps({
    community: Object,
    isPro:     { type: Boolean, default: false },
});

const page = usePage();
const creatorPlan = computed(() => page.props.auth.user?.creator_plan ?? 'free');

// ─── AI Chatbot ──────────────────────────────────────────────────────────────
const chatbotSaved = ref(false);
const chatbotForm  = useForm({
    name: props.community.name,
    ai_chatbot_instructions: props.community.ai_chatbot_instructions ?? '',
});

function saveChatbot() {
    chatbotForm.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            preserveScroll: true,
            onSuccess: () => {
                chatbotSaved.value = true;
                setTimeout(() => (chatbotSaved.value = false), 4000);
            },
        });
}

const aiGenerating = ref(false);
const aiCopy       = ref(null);
const aiError      = ref('');
const aiApplied    = ref(false);

async function generateLandingPage() {
    aiGenerating.value = true;
    aiCopy.value       = null;
    aiError.value      = '';
    try {
        const { data } = await axios.post(`/communities/${props.community.slug}/ai-landing`);
        aiCopy.value = {
            tagline:     data.hero?.headline,
            description: data.hero?.subheadline,
            cta:         data.hero?.cta_label,
        };
    } catch (e) {
        aiError.value = e?.response?.data?.error ?? 'Something went wrong. Please try again.';
    } finally {
        aiGenerating.value = false;
    }
}

function applyAiCopy() {
    if (!aiCopy.value) return;
    axios.patch(`/communities/${props.community.slug}`, {
        name: props.community.name,
        description: aiCopy.value.description,
    }).then(() => {
        aiApplied.value = true;
        aiCopy.value = null;
        setTimeout(() => (aiApplied.value = false), 4000);
        router.reload({ only: ['community'], preserveScroll: true });
    });
}
</script>

<template>
    <CommunitySettingsLayout :community="community">
        <div class="bg-white border border-gray-200 rounded-2xl p-6">
            <div class="flex items-center justify-between mb-1">
                <h2 class="text-base font-semibold text-gray-900">✨ AI Landing Page Builder</h2>
                <span class="text-xs font-bold bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full">⭐ Pro</span>
            </div>
            <p class="text-sm text-gray-500 mb-5">Generate a compelling tagline, description, and CTA for your community page using AI.</p>

            <!-- Locked for non-Pro -->
            <div v-if="creatorPlan !== 'pro'" class="rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-6 text-center">
                <p class="text-sm font-semibold text-indigo-800 mb-1">Creator Pro feature</p>
                <p class="text-xs text-indigo-600 mb-3">Upgrade to unlock AI-generated landing page copy.</p>
                <Link href="/creator/plan" class="inline-block px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                    Upgrade to Creator Pro →
                </Link>
            </div>

            <!-- Builder for Pro -->
            <div v-else>
                <div class="flex items-center gap-3 flex-wrap">
                    <button
                        type="button"
                        @click="generateLandingPage"
                        :disabled="aiGenerating"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                        <svg v-if="aiGenerating" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        {{ aiGenerating ? 'Generating...' : '✨ Generate copy' }}
                    </button>
                    <Link :href="`/communities/${community.slug}/landing`"
                        class="inline-flex items-center gap-1.5 px-4 py-2.5 border border-indigo-200 text-indigo-600 text-sm font-medium rounded-lg hover:bg-indigo-50 transition-colors">
                        View / Edit Landing Page →
                    </Link>
                </div>

                <!-- AI result -->
                <div v-if="aiCopy" class="mt-4 rounded-xl border border-indigo-100 bg-indigo-50 p-4 space-y-3">
                    <div>
                        <p class="text-xs font-semibold text-indigo-700 mb-0.5">Tagline</p>
                        <p class="text-sm text-gray-800">{{ aiCopy.tagline }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-indigo-700 mb-0.5">Description</p>
                        <p class="text-sm text-gray-800">{{ aiCopy.description }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-indigo-700 mb-0.5">CTA button label</p>
                        <p class="text-sm text-gray-800">{{ aiCopy.cta }}</p>
                    </div>
                    <div class="flex items-center gap-3 pt-1">
                        <button
                            type="button"
                            @click="applyAiCopy"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                        >
                            Apply description
                        </button>
                        <p class="text-xs text-gray-400">Saves the description to your community.</p>
                    </div>
                </div>
                <p v-if="aiApplied" class="mt-3 text-sm text-green-600">Description updated!</p>
                <p v-if="aiError" class="mt-3 text-sm text-red-600">{{ aiError }}</p>
            </div>
        </div>
        <!-- ── AI Chatbot Instructions ─────────────────────────────────── -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 mt-6">
            <div class="flex items-center gap-2 mb-1">
                <h2 class="text-base font-semibold text-gray-900">🤖 AI Chatbot</h2>
            </div>
            <p class="text-sm text-gray-500 mb-5">
                Your community has an AI chatbot that members can use to ask questions about your courses, lessons, and posts.
                Customize its behavior by setting instructions below.
            </p>

            <form @submit.prevent="saveChatbot" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Chatbot Instructions</label>
                    <textarea
                        v-model="chatbotForm.ai_chatbot_instructions"
                        rows="6"
                        placeholder="e.g. You are a marketing coach. Always be encouraging and give actionable advice. When someone asks about pricing, explain the value of investing in their skills. Focus on faceless marketing strategies."
                        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                    />
                    <p class="mt-1.5 text-xs text-gray-400">
                        Tell the AI how to behave, what topics to focus on, what tone to use, and any specific rules.
                        The AI automatically has access to your community's courses, lessons, and posts.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        :disabled="chatbotForm.processing"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                    >
                        {{ chatbotForm.processing ? 'Saving...' : 'Save Instructions' }}
                    </button>
                    <p v-if="chatbotSaved" class="text-sm text-green-600 font-medium">Saved!</p>
                </div>
            </form>
        </div>
    </CommunitySettingsLayout>
</template>
