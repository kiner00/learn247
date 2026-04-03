<template>
    <Head :title="`${community.name} · Landing Page`" />

    <!-- Owner toolbar -->
    <div v-if="isOwner" class="fixed top-0 inset-x-0 z-50 bg-gray-900/95 backdrop-blur text-white text-sm flex items-center justify-between px-4 py-2.5 gap-3">
        <span class="font-semibold text-white/80 truncate">Landing Page Preview</span>
        <div class="flex items-center gap-2 shrink-0">
            <a :href="`/communities/${community.slug}`" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 transition text-xs font-medium">
                ← Back
            </a>
            <button
                @click="generate"
                :disabled="generating"
                class="flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-indigo-500 hover:bg-indigo-600 transition text-xs font-bold disabled:opacity-60"
            >
                <svg v-if="!generating" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                </svg>
                <svg v-else class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                {{ generating ? 'Generating…' : (lp ? 'Regenerate All' : 'Generate with AI') }}
            </button>
            <button
                v-if="lp"
                @click="inlineMode = !inlineMode; showEditPanel = false"
                :class="inlineMode ? 'bg-amber-400 text-gray-900 hover:bg-amber-500' : 'bg-white/10 hover:bg-white/20 text-white'"
                class="px-3 py-1.5 rounded-lg transition text-xs font-bold"
            >
                {{ inlineMode ? '✓ Done Editing' : '✏️ Edit Text' }}
            </button>
            <button
                v-if="lp"
                @click="showEditPanel = true; inlineMode = false"
                class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 transition text-xs font-bold"
            >
                Edit Sections
            </button>
            <button
                v-if="lp"
                @click="copyLink"
                class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 transition text-xs font-medium"
            >
                {{ copied ? 'Copied!' : 'Copy Link' }}
            </button>
        </div>
    </div>

    <!-- Inline toolbar + color popover -->
    <LandingInlineToolbar
        v-if="isOwner && inlineMode"
        :visible="toolbarVisible"
        :position="toolbarPos"
        :fmt-active="fmtActive"
        :active-color="activeColor"
        :color-popover="colorPopover"
        :get-color-value="getColorValue"
        @exec-fmt="execFmt"
        @update:active-color="activeColor = $event"
        @close-color-popover="closeColorPopover"
        @set-color-value="setColorValue"
    />

    <!-- Color popover (when not in inline mode but edit panel is open) -->
    <Teleport to="body" v-if="isOwner && !inlineMode">
        <Transition enter-from-class="opacity-0 scale-95" enter-active-class="transition duration-150" leave-to-class="opacity-0 scale-95" leave-active-class="transition duration-100">
            <div v-if="colorPopover.visible" class="fixed z-[400]" :style="{ top: colorPopover.top + 'px', left: colorPopover.left + 'px' }">
                <div class="fixed inset-0" @click="closeColorPopover" />
                <div class="relative bg-white rounded-xl shadow-2xl border border-gray-200 p-3 w-[240px] space-y-3">
                    <div v-for="field in colorPopover.fields" :key="field.path">
                        <label class="text-[10px] text-gray-500 font-semibold uppercase tracking-wide">{{ field.label }}</label>
                        <div class="flex items-center gap-2 mt-1">
                            <input type="color" :value="getColorValue(field.path) || field.fallback" @input="setColorValue(field.path, $event.target.value)" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                            <input type="text" :value="getColorValue(field.path) || field.fallback" @input="setColorValue(field.path, $event.target.value)" :placeholder="field.fallback" class="flex-1 text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Editor panel -->
    <LandingEditorPanel
        :show="showEditPanel"
        :lp="lp"
        :edit-draft="editDraft"
        :edit-saving="editSaving"
        :edit-error="editError"
        :regen-loading="regenLoading"
        :upload-loading="uploadLoading"
        :section-video-uploading="sectionVideoUploading"
        :section-video-progress="sectionVideoProgress"
        :section-video-error="sectionVideoError"
        :can-upload-section-video="canUploadSectionVideo"
        :community="community"
        :all-courses="allCourses"
        :certifications="certifications"
        :all-courses-selected="allCoursesSelected"
        :SECTION_DEFS="SECTION_DEFS"
        :DEFAULT_SECTION_ORDER="DEFAULT_SECTION_ORDER"
        @close="showEditPanel = false"
        @save="saveEdits"
        @regen-section="regenSection"
        @upload-image="uploadImage"
        @section-video-upload="handleSectionVideoUpload"
        @toggle-course-selection="toggleCourseSelection"
        @toggle-all-courses="toggleAllCourses"
    />

    <!-- Empty state for owners -->
    <div v-if="!lp && isOwner" :class="isOwner ? 'pt-12' : ''" class="min-h-screen bg-linear-to-br from-slate-900 via-indigo-950 to-slate-900 flex items-center justify-center p-6">
        <div class="text-center max-w-md">
            <div class="w-20 h-20 mx-auto mb-6 rounded-2xl bg-indigo-500/20 flex items-center justify-center">
                <svg class="w-10 h-10 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                </svg>
            </div>
            <h2 class="text-2xl font-black text-white mb-3">No landing page yet</h2>
            <p class="text-slate-400 mb-8 leading-relaxed">Click <strong class="text-white">Generate with AI</strong> above and we'll build a beautiful, high-converting funnel page for your community in seconds.</p>
            <button @click="generate" :disabled="generating"
                class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-500 hover:bg-indigo-600 text-white font-bold rounded-2xl transition disabled:opacity-60">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                </svg>
                {{ generating ? 'Generating…' : 'Generate with AI' }}
            </button>
            <p v-if="generateError" class="mt-4 text-red-400 text-sm">{{ generateError }}</p>
        </div>
    </div>

    <!-- Empty state for visitors -->
    <div v-else-if="!lp && !isOwner" class="min-h-screen bg-linear-to-br from-slate-900 via-indigo-950 to-slate-900 flex items-center justify-center p-6">
        <div class="text-center max-w-sm">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full overflow-hidden bg-indigo-900 flex items-center justify-center">
                <img v-if="community.cover_image" :src="community.cover_image" class="w-full h-full object-cover" />
                <span v-else class="text-2xl font-black text-white">{{ community.name.charAt(0) }}</span>
            </div>
            <h1 class="text-2xl font-black text-white mb-2">{{ community.name }}</h1>
            <p class="text-slate-400 mb-6">{{ community.description || 'Join this community today.' }}</p>
            <button @click="handleCta"
                class="px-8 py-3 bg-amber-400 hover:bg-amber-500 text-gray-900 font-black rounded-2xl transition uppercase tracking-wide">
                {{ community.price > 0 ? `Join · ₱${Number(community.price).toLocaleString()}` : 'Join for Free' }}
            </button>
        </div>
    </div>

    <!-- Full landing page -->
    <div v-else :class="isOwner ? 'pt-12' : ''" class="bg-white font-sans antialiased">

        <!-- Inline edit mode hint bar -->
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 -translate-y-2" leave-active-class="transition duration-150" leave-to-class="opacity-0 -translate-y-2">
            <div v-if="isOwner && inlineMode" class="sticky top-12 z-40 bg-amber-400 text-gray-900 text-xs font-semibold text-center py-2 px-4">
                ✏️ Click on any text to edit it. Use the toolbar to format. Click away to save automatically.
            </div>
        </Transition>

        <!-- Hero section -->
        <LandingHeroSection
            v-if="isVisible('hero')"
            :lp="lp"
            :community="community"
            :invited-by="invitedBy"
            :is-owner="isOwner"
            :inline-mode="inlineMode"
            :show-edit-panel="showEditPanel"
            :render-key="renderKey"
            :editable-class="editableClass"
            :normalize-video-url="normalizeVideoUrl"
            @open-color-popover="openColorPopover"
            @el-focus="onElFocus"
            @el-blur="saveFromEl"
            @cta="handleCta"
        />

        <!-- All other display sections -->
        <LandingDisplaySections
            :lp="lp"
            :community="community"
            :courses="props.courses"
            :certifications="props.certifications"
            :is-owner="isOwner"
            :owner-is-pro="ownerIsPro"
            :inline-mode="inlineMode"
            :show-edit-panel="showEditPanel"
            :editable-class="editableClass"
            :normalize-video-url="normalizeVideoUrl"
            :is-visible="isVisible"
            @open-color-popover="openColorPopover"
            @el-focus="onElFocus"
            @el-blur="saveFromEl"
            @cta="handleCta"
        />

    </div>

    <!-- Join modal -->
    <LandingJoinModal
        :show="showJoinModal"
        :community="community"
        :invited-by="invitedBy"
        :join-form="joinForm"
        @close="showJoinModal = false"
        @submit="submitJoin"
    />
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { usePixel } from '@/composables/usePixel';
import { useTiktokPixel } from '@/composables/useTiktokPixel';
import { useGoogleAnalytics } from '@/composables/useGoogleAnalytics';

import LandingInlineToolbar from '@/Components/Landing/LandingInlineToolbar.vue';
import LandingEditorPanel from '@/Components/Landing/LandingEditorPanel.vue';
import LandingHeroSection from '@/Components/Landing/LandingHeroSection.vue';
import LandingDisplaySections from '@/Components/Landing/LandingDisplaySections.vue';
import LandingJoinModal from '@/Components/Landing/LandingJoinModal.vue';

function xsrfToken() {
    return decodeURIComponent(
        document.cookie.split('; ').find(r => r.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? ''
    );
}
function jsonHeaders() {
    return { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrfToken() };
}

// ── Section definitions ───────────────────────────────────────────────────────
const SECTION_DEFS = {
    hero:                 { label: 'Headline & Hero',      icon: '🎯', required: true },
    social_proof:         { label: 'Social Proof Bar',     icon: '👥' },
    benefits:             { label: 'Benefits',             icon: '✨' },
    for_you:              { label: 'This Is For You',      icon: '🙋' },
    creator:              { label: 'Authority / Creator',  icon: '👤' },
    video_creator:        { label: 'Video (After Creator)',     icon: '🎬' },
    testimonials:         { label: 'Testimonials',         icon: '⭐' },
    video_testimonials:   { label: 'Video (After Testimonials)', icon: '🎬' },
    offer_stack:          { label: 'Offer Stack',          icon: '💎' },
    included_courses:     { label: 'Included Courses',     icon: '🎓' },
    video_courses:        { label: 'Video (After Courses)',     icon: '🎬' },
    certifications:       { label: 'Certifications',       icon: '🏆' },
    price_justification:  { label: 'Price Justification',  icon: '💰' },
    guarantee:            { label: 'Guarantee',            icon: '🛡️' },
    faq:                  { label: 'FAQ',                  icon: '❓' },
    cta_section:          { label: 'Final CTA',            icon: '🚀', required: true },
};

const DEFAULT_SECTION_ORDER = [
    'hero', 'social_proof', 'benefits', 'for_you', 'creator', 'video_creator',
    'testimonials', 'video_testimonials', 'offer_stack', 'included_courses', 'video_courses', 'certifications', 'price_justification', 'guarantee',
    'faq', 'cta_section',
];

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
    community:  Object,
    affiliate:  Object,
    invitedBy:  Object,
    membership: Object,
    ownerIsPro: { type: Boolean, default: false },
    isOwner:    { type: Boolean, default: false },
    courses:        { type: Array, default: () => [] },
    allCourses:     { type: Array, default: () => [] },
    certifications: { type: Array, default: () => [] },
});

// ── State ─────────────────────────────────────────────────────────────────────
const lp             = ref(props.community.landing_page ?? null);
const showJoinModal  = ref(false);
const generating     = ref(false);
const generateError  = ref(null);
const copied         = ref(false);
const showEditPanel  = ref(false);
const editSaving     = ref(false);
const editError      = ref(null);
const editDraft      = ref({});
const regenLoading   = ref(null);
const uploadLoading  = ref(null);

const canUploadSectionVideo = computed(() => {
    const user = usePage().props.auth?.user;
    return props.ownerIsPro || user?.is_super_admin;
});

// ── Inline color picker ──────────────────────────────────────────────────────
const colorPopover = ref({ visible: false, top: 0, left: 0, fields: [] });

function openColorPopover(event, fields) {
    if (!inlineMode.value && !showEditPanel.value) return;
    event.stopPropagation();
    const rect = event.currentTarget.getBoundingClientRect();
    colorPopover.value = {
        visible: true,
        top: rect.bottom + 8,
        left: Math.max(8, Math.min(rect.left + rect.width / 2 - 120, window.innerWidth - 260)),
        fields,
    };
}

function closeColorPopover() {
    colorPopover.value.visible = false;
}

function getColorValue(path) {
    const parts = path.split('.');
    let cur = lp.value;
    for (const p of parts) {
        if (!cur) return '';
        cur = cur[p];
    }
    return cur || '';
}

function setColorValue(path, value) {
    const parts = path.split('.');
    let cur = lp.value;
    for (let i = 0; i < parts.length - 1; i++) {
        if (!cur[parts[i]]) cur[parts[i]] = {};
        cur = cur[parts[i]];
    }
    cur[parts[parts.length - 1]] = value;
    // Also update editDraft if panel is open
    if (showEditPanel.value) {
        let d = editDraft.value;
        for (let i = 0; i < parts.length - 1; i++) {
            if (!d[parts[i]]) d[parts[i]] = {};
            d = d[parts[i]];
        }
        d[parts[parts.length - 1]] = value;
    }
    autoSave();
}

// ── Course selection helpers ──────────────────────────────────────────────────
function toggleCourseSelection(courseId) {
    if (!editDraft.value.included_courses_selected) {
        editDraft.value.included_courses_selected = [];
    }
    const idx = editDraft.value.included_courses_selected.indexOf(courseId);
    if (idx === -1) {
        editDraft.value.included_courses_selected.push(courseId);
    } else {
        editDraft.value.included_courses_selected.splice(idx, 1);
    }
}

const allCoursesSelected = computed(() => {
    const sel = editDraft.value.included_courses_selected ?? [];
    return props.allCourses.length > 0 && props.allCourses.every(c => sel.includes(c.id));
});

function toggleAllCourses() {
    if (allCoursesSelected.value) {
        editDraft.value.included_courses_selected = [];
    } else {
        editDraft.value.included_courses_selected = props.allCourses.map(c => c.id);
    }
}

// ── Inline text editing ───────────────────────────────────────────────────────
const inlineMode      = ref(false);
const toolbarVisible  = ref(false);
const toolbarPos      = ref({ top: 0, left: 0 });
const renderKey       = ref(0);
const fmtActive       = ref({ bold: false, italic: false, underline: false });
const activeColor     = ref('#ffffff');

// Shared CSS class applied to editable elements when inlineMode is on
const editableClass = 'outline-none cursor-text rounded hover:ring-2 hover:ring-amber-400/50 focus:ring-2 focus:ring-amber-400 transition-shadow';

function onElFocus(event) {
    const rect = event.target.getBoundingClientRect();
    toolbarPos.value = {
        top:  Math.max(56, rect.top - 48),
        left: Math.max(8, Math.min(rect.left, window.innerWidth - 340)),
    };
    toolbarVisible.value = true;
    updateFmtState();
}

function saveFromEl(event, path) {
    setTimeout(() => {
        if (document.activeElement?.closest('[data-inline-toolbar]')) return;
        setNestedValue(path, event.target.innerHTML);
        toolbarVisible.value = false;
        autoSave();
    }, 160);
}

function setNestedValue(path, value) {
    const parts = path.split('.');
    let cur = lp.value;
    for (let i = 0; i < parts.length - 1; i++) {
        const key = /^\d+$/.test(parts[i]) ? parseInt(parts[i]) : parts[i];
        if (!cur[key] && cur[key] !== 0) return;
        cur = cur[key];
    }
    const last = parts[parts.length - 1];
    cur[/^\d+$/.test(last) ? parseInt(last) : last] = value;
}

function execFmt(cmd, value = null) {
    document.execCommand(cmd, false, value);
    updateFmtState();
}

function updateFmtState() {
    fmtActive.value = {
        bold:      document.queryCommandState('bold'),
        italic:    document.queryCommandState('italic'),
        underline: document.queryCommandState('underline'),
    };
}

let autoSaveTimer = null;
async function autoSave() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(async () => {
        try {
            await fetch(`/communities/${props.community.slug}/landing-page`, {
                method:  'PATCH',
                headers: jsonHeaders(),
                body: JSON.stringify(lp.value),
            });
        } catch (e) { /* silent */ }
    }, 1500);
}

// Turn off inline mode → blur any active editable
watch(inlineMode, (on) => {
    if (!on) {
        document.querySelectorAll('[contenteditable="true"]').forEach(el => el.blur());
        toolbarVisible.value = false;
    }
});

// ── Section visibility helpers ────────────────────────────────────────────────
function isVisible(type) {
    if (!lp.value) return false;
    const sections = lp.value._sections;
    if (!sections) return true; // backward compat: old pages show all
    const sec = sections.find(s => s.type === type);
    return sec ? sec.visible : false;
}

// ── Open edit panel ───────────────────────────────────────────────────────────
watch(showEditPanel, (open) => {
    if (open && lp.value) {
        editDraft.value = JSON.parse(JSON.stringify(lp.value));
        editError.value = null;

        // Initialize course selection from saved data or default to current inclusive courses
        if (!editDraft.value.included_courses_selected) {
            editDraft.value.included_courses_selected = props.courses.map(c => c.id);
        }

        // Ensure _sections exists for old landing pages
        if (!editDraft.value._sections) {
            editDraft.value._sections = DEFAULT_SECTION_ORDER.map(type => ({
                type,
                visible: type === 'hero' || type === 'cta_section'
                    ? true
                    : !!(editDraft.value[type] && (Array.isArray(editDraft.value[type]) ? editDraft.value[type].length > 0 : true)),
            }));
        }

        // Ensure any new section types are present in _sections at the correct position
        if (editDraft.value._sections) {
            const existing = new Set(editDraft.value._sections.map(s => s.type));
            for (let i = 0; i < DEFAULT_SECTION_ORDER.length; i++) {
                const type = DEFAULT_SECTION_ORDER[i];
                if (!existing.has(type)) {
                    // Find the best insertion index: after the previous default section
                    let insertIdx = editDraft.value._sections.length;
                    for (let j = i - 1; j >= 0; j--) {
                        const prevIdx = editDraft.value._sections.findIndex(s => s.type === DEFAULT_SECTION_ORDER[j]);
                        if (prevIdx !== -1) { insertIdx = prevIdx + 1; break; }
                    }
                    editDraft.value._sections.splice(insertIdx, 0, { type, visible: false });
                }
            }
        }

        // Ensure hero video_type is initialized
        if (!editDraft.value.hero.video_type) {
            editDraft.value.hero.video_type = editDraft.value.hero.vsl_url ? 'vsl' : 'vsl';
        }
        if (!editDraft.value.hero.embed_html) {
            editDraft.value.hero.embed_html = editDraft.value.embed?.html || '';
        }
    }
});

// ── Per-section AI regeneration ───────────────────────────────────────────────
async function regenSection(type) {
    regenLoading.value = type;
    try {
        const res = await fetch(`/communities/${props.community.slug}/ai-landing/section`, {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({ section: type }),
        });

        const data = await res.json();
        if (!res.ok) {
            alert(data.error ?? 'Regeneration failed. Please try again.');
            return;
        }

        // Update editDraft with the regenerated section data
        editDraft.value[type] = data.data;
        // Also update lp so preview reflects it immediately
        if (lp.value) lp.value[type] = data.data;
        renderKey.value++;
    } catch (e) {
        alert(e?.message ?? 'Something went wrong.');
    } finally {
        regenLoading.value = null;
    }
}

// ── Image upload ──────────────────────────────────────────────────────────────
async function uploadImage(section, event) {
    const file = event.target.files?.[0];
    if (!file) return;

    uploadLoading.value = section;
    const formData = new FormData();
    formData.append('image', file);

    try {
        const res = await fetch(`/communities/${props.community.slug}/landing-page/upload-image`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            body: formData,
        });
        const data = await res.json();
        if (!res.ok) {
            alert(data.message ?? 'Upload failed.');
            return;
        }

        if (!editDraft.value[section]) editDraft.value[section] = {};
        if (section === 'creator') {
            editDraft.value[section].photo = data.url;
        } else {
            editDraft.value[section].bg_image = data.url;
        }
    } catch (e) {
        alert(e?.message ?? 'Upload failed.');
    } finally {
        uploadLoading.value = null;
        event.target.value = '';
    }
}

// ── Section video upload (Pro) ────────────────────────────────────────────
const sectionVideoUploading = ref(null);
const sectionVideoProgress  = ref(0);
const sectionVideoError     = ref('');

async function handleSectionVideoUpload(sectionType, e) {
    const file = e.target.files?.[0];
    if (!file) return;

    sectionVideoUploading.value = sectionType;
    sectionVideoProgress.value  = 0;
    sectionVideoError.value     = '';

    try {
        // Step 1: Get presigned upload URL
        const res = await fetch(`/communities/${props.community.slug}/landing-page/upload-video`, {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({
                filename: file.name,
                content_type: file.type,
                size: file.size,
            }),
        });
        const data = await res.json();
        if (!res.ok) {
            sectionVideoError.value = data.error ?? data.message ?? 'Failed to get upload URL.';
            return;
        }

        // Step 2: Upload directly to S3
        const { default: rawAxios } = await import('axios');
        const s3Client = rawAxios.create({ withCredentials: false });
        await s3Client.put(data.upload_url, file, {
            headers: { 'Content-Type': file.type },
            onUploadProgress: (evt) => {
                sectionVideoProgress.value = Math.round((evt.loaded / evt.total) * 100);
            },
        });

        // Step 3: Store the S3 URL in the section draft
        if (!editDraft.value[sectionType]) editDraft.value[sectionType] = {};
        editDraft.value[sectionType].video_url = data.url;
    } catch (err) {
        if (typeof err.response?.data === 'string' && err.response.data.includes('<Message>')) {
            const match = err.response.data.match(/<Message>([^<]+)<\/Message>/);
            sectionVideoError.value = match ? `S3: ${match[1]}` : 'Upload to storage failed.';
        } else {
            sectionVideoError.value = err.message || 'Upload failed. Please try again.';
        }
        console.error('Landing video upload error:', err);
    } finally {
        sectionVideoUploading.value = null;
        e.target.value = '';
    }
}

// ── Pixels ────────────────────────────────────────────────────────────────────
const affFbPixelId = props.invitedBy?.facebook_pixel_id;
const affTtPixelId = props.invitedBy?.tiktok_pixel_id;
const affGaId      = props.invitedBy?.google_analytics_id;

const trackers = [
    props.community.facebook_pixel_id   ? usePixel(props.community.facebook_pixel_id)             : null,
    props.community.tiktok_pixel_id     ? useTiktokPixel(props.community.tiktok_pixel_id)         : null,
    props.community.google_analytics_id ? useGoogleAnalytics(props.community.google_analytics_id) : null,
    affFbPixelId && affFbPixelId !== props.community.facebook_pixel_id ? usePixel(affFbPixelId)           : null,
    affTtPixelId && affTtPixelId !== props.community.tiktok_pixel_id   ? useTiktokPixel(affTtPixelId)     : null,
    affGaId      && affGaId      !== props.community.google_analytics_id ? useGoogleAnalytics(affGaId)    : null,
].filter(Boolean);

onMounted(() => {
    if (props.isOwner) return;
    trackers.forEach(t => t.init());
    trackers.forEach(t => t.viewContent({
        content_name:     props.community.name,
        content_category: props.community.category ?? 'Community',
        content_type:     'product',
        value:            Number(props.community.price ?? 0),
        currency:         props.community.currency ?? 'PHP',
    }));
});

// ── AI Generate (full page) ───────────────────────────────────────────────────
async function generate() {
    generating.value   = true;
    generateError.value = null;

    try {
        const res = await fetch(`/communities/${props.community.slug}/ai-landing`, {
            method:  'POST',
            headers: jsonHeaders(),
        });

        const data = await res.json();

        if (!res.ok) {
            generateError.value = data.error ?? 'Generation failed. Please try again.';
            return;
        }

        lp.value = data;
        renderKey.value++;
    } catch (e) {
        generateError.value = e?.message ?? 'Something went wrong. Please try again.';
    } finally {
        generating.value = false;
    }
}

// ── CTA handler ───────────────────────────────────────────────────────────────
function handleCta() {
    if (props.membership || props.isOwner) {
        window.location.href = `/communities/${props.community.slug}`;
        return;
    }
    showJoinModal.value = true;
}

// ── Join form ─────────────────────────────────────────────────────────────────
const joinForm = useForm({
    first_name: '',
    last_name:  '',
    email:      '',
    phone:      '',
});

function submitJoin() {
    trackers.forEach(t => t.lead({
        content_name: props.community.name,
        content_type: 'product',
        value:        Number(props.community.price ?? 0),
        currency:     props.community.currency ?? 'PHP',
    }));

    if (props.invitedBy?.code) {
        joinForm.post(`/ref-checkout/${props.invitedBy.code}`);
    } else {
        joinForm.post(`/communities/${props.community.slug}/guest-checkout`);
    }
}

// ── Save edits ────────────────────────────────────────────────────────────────
async function saveEdits() {
    editSaving.value = true;
    editError.value  = null;
    try {
        const res = await fetch(`/communities/${props.community.slug}/landing-page`, {
            method:  'PATCH',
            headers: jsonHeaders(),
            body: JSON.stringify(editDraft.value),
        });
        const data = await res.json();
        if (!res.ok) { editError.value = data.message ?? 'Save failed.'; return; }
        lp.value          = data;
        showEditPanel.value = false;
    } catch (e) {
        editError.value = e?.message ?? 'Something went wrong.';
    } finally {
        editSaving.value = false;
    }
}

// ── Copy link ─────────────────────────────────────────────────────────────────
function copyLink() {
    navigator.clipboard.writeText(window.location.href);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function normalizeVideoUrl(url) {
    if (!url) return url;
    const ytWatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/);
    if (ytWatch) return `https://www.youtube.com/embed/${ytWatch[1]}`;
    const gdrive = url.match(/drive\.google\.com\/file\/d\/([A-Za-z0-9_-]+)/);
    if (gdrive) return `https://drive.google.com/file/d/${gdrive[1]}/preview`;
    return url;
}

function formatCount(n) {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(n ?? 0);
}
</script>

<style scoped>
@reference "tailwindcss";

.field-label {
    @apply block text-xs font-medium text-gray-600 mb-1;
}
.field-input {
    @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500;
}
</style>
