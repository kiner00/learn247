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

    <!-- ── Floating inline format toolbar ── -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0 scale-95">
            <div v-if="isOwner && inlineMode && toolbarVisible"
                data-inline-toolbar
                tabindex="-1"
                @mousedown.prevent
                class="fixed z-[300] bg-gray-900 text-white rounded-xl shadow-2xl flex items-center gap-0.5 px-2 py-1.5"
                :style="{ top: toolbarPos.top + 'px', left: toolbarPos.left + 'px' }">
                <!-- Bold -->
                <button @click="execFmt('bold')"
                    :class="{'bg-white/20': fmtActive.bold}"
                    class="w-8 h-7 rounded font-bold text-sm hover:bg-white/20 active:bg-white/30 transition select-none">B</button>
                <!-- Italic -->
                <button @click="execFmt('italic')"
                    :class="{'bg-white/20': fmtActive.italic}"
                    class="w-8 h-7 rounded italic text-sm hover:bg-white/20 active:bg-white/30 transition select-none">I</button>
                <!-- Underline -->
                <button @click="execFmt('underline')"
                    :class="{'bg-white/20': fmtActive.underline}"
                    class="w-8 h-7 rounded underline text-sm hover:bg-white/20 active:bg-white/30 transition select-none">U</button>
                <div class="w-px h-4 bg-white/20 mx-1" />
                <!-- Font size -->
                <button @click="execFmt('fontSize', '5')" title="Larger" class="w-8 h-7 rounded text-xs hover:bg-white/20 transition font-bold select-none">A+</button>
                <button @click="execFmt('fontSize', '2')" title="Smaller" class="w-8 h-7 rounded text-xs hover:bg-white/20 transition select-none opacity-80">A-</button>
                <div class="w-px h-4 bg-white/20 mx-1" />
                <!-- Text color -->
                <label title="Text color" class="relative w-8 h-7 flex items-center justify-center rounded hover:bg-white/20 transition cursor-pointer select-none">
                    <span class="text-sm font-bold" :style="{ color: activeColor }">A</span>
                    <span class="absolute bottom-1 left-1.5 right-1.5 h-0.5 rounded" :style="{ background: activeColor }"></span>
                    <input type="color" v-model="activeColor" @input="execFmt('foreColor', activeColor)" @mousedown.stop class="absolute inset-0 opacity-0 w-full h-full cursor-pointer" />
                </label>
                <div class="w-px h-4 bg-white/20 mx-1" />
                <!-- Align -->
                <button @click="execFmt('justifyLeft')" title="Align left" class="w-7 h-7 rounded hover:bg-white/20 transition select-none text-xs">⬅</button>
                <button @click="execFmt('justifyCenter')" title="Center" class="w-7 h-7 rounded hover:bg-white/20 transition select-none text-xs">↔</button>
                <div class="w-px h-4 bg-white/20 mx-1" />
                <!-- Save hint -->
                <span class="text-white/40 text-xs px-1">Click away to save</span>
            </div>
        </Transition>
    </Teleport>

    <!-- ── INLINE COLOR POPOVER ── -->
    <Teleport to="body">
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

    <!-- ═══════════════════════════════════════════════════════════
         SECTION-BASED EDITOR PANEL
    ════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
        <Transition enter-from-class="translate-x-full" enter-active-class="transition-transform duration-300" leave-to-class="translate-x-full" leave-active-class="transition-transform duration-300">
            <div v-if="showEditPanel && lp" class="fixed inset-0 z-60 flex justify-end">
                <div class="absolute inset-0 bg-black/40" @click="showEditPanel = false" />
                <div class="relative w-full max-w-md bg-white h-full flex flex-col shadow-2xl overflow-hidden">

                    <!-- Panel header -->
                    <div class="flex items-center justify-between px-5 py-4 border-b bg-gray-50 shrink-0">
                        <h2 class="font-bold text-gray-900 text-base">Edit Sections</h2>
                        <div class="flex items-center gap-2">
                            <button @click="saveEdits" :disabled="editSaving"
                                class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition disabled:opacity-50">
                                {{ editSaving ? 'Saving…' : 'Save Changes' }}
                            </button>
                            <button @click="showEditPanel = false" class="text-gray-400 hover:text-gray-700 transition">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                    <p v-if="editError" class="text-xs text-red-600 px-5 pt-3 shrink-0">{{ editError }}</p>

                    <!-- Section list -->
                    <div class="flex-1 overflow-y-auto">
                        <div v-for="(sec, idx) in editDraft._sections" :key="sec.type" class="border-b border-gray-100">

                            <!-- Section card header -->
                            <div class="flex items-center gap-2 px-4 py-3 hover:bg-gray-50/60 transition">
                                <!-- Visibility toggle -->
                                <button
                                    @click="sec.visible = !sec.visible"
                                    :title="sec.visible ? 'Hide section' : 'Show section'"
                                    :class="['w-7 h-7 flex items-center justify-center rounded-md transition shrink-0', sec.visible ? 'text-indigo-600 bg-indigo-50 hover:bg-indigo-100' : 'text-gray-300 bg-gray-100 hover:bg-gray-200']">
                                    <svg v-if="sec.visible" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                </button>

                                <!-- Section name (click to expand) -->
                                <button @click="expandedSection = expandedSection === sec.type ? null : sec.type" class="flex-1 text-left">
                                    <span class="text-sm font-medium text-gray-800">
                                        {{ SECTION_DEFS[sec.type]?.icon }} {{ SECTION_DEFS[sec.type]?.label }}
                                    </span>
                                    <span v-if="!sec.visible" class="ml-2 text-xs text-gray-400 font-normal">hidden</span>
                                </button>

                                <!-- AI Regen button -->
                                <button
                                    @click.stop="regenSection(sec.type)"
                                    :disabled="regenLoading === sec.type"
                                    class="flex items-center gap-1 px-2 py-1 rounded-md bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-xs font-semibold transition disabled:opacity-40 shrink-0">
                                    <svg v-if="regenLoading === sec.type" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                                    <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                                    {{ regenLoading === sec.type ? 'AI…' : 'AI' }}
                                </button>

                                <!-- Remove (only for non-core sections) -->
                                <button v-if="!SECTION_DEFS[sec.type]?.required"
                                    @click.stop="removeSection(sec.type)"
                                    title="Remove section"
                                    class="w-7 h-7 flex items-center justify-center rounded-md text-gray-300 hover:text-red-500 hover:bg-red-50 transition shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>

                                <!-- Expand chevron -->
                                <button @click="expandedSection = expandedSection === sec.type ? null : sec.type"
                                    class="w-6 h-6 flex items-center justify-center text-gray-400 transition shrink-0"
                                    :class="expandedSection === sec.type ? 'rotate-180' : ''">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            </div>

                            <!-- Expanded editor for this section -->
                            <div v-if="expandedSection === sec.type" class="px-4 pb-5 pt-2 bg-gray-50/50 space-y-3 text-sm">

                                <!-- HERO -->
                                <template v-if="sec.type === 'hero' && editDraft.hero">
                                    <div>
                                        <label class="field-label">Pre-Headline / Who Is This For</label>
                                        <input v-model="editDraft.hero.pre_headline" type="text" placeholder="e.g. Attention: Filipino Entrepreneurs" class="field-input" />
                                    </div>
                                    <div>
                                        <label class="field-label">Main Headline</label>
                                        <input v-model="editDraft.hero.headline" type="text" class="field-input" />
                                    </div>
                                    <div>
                                        <label class="field-label">Subheadline</label>
                                        <textarea v-model="editDraft.hero.subheadline" rows="2" class="field-input resize-none" />
                                    </div>
                                    <div>
                                        <label class="field-label">Headline Font Size <span class="text-gray-400 font-normal">({{ editDraft.hero.headline_font_size || 48 }}px)</span></label>
                                        <input type="range" v-model.number="editDraft.hero.headline_font_size" min="24" max="80" step="2" class="w-full accent-indigo-600" />
                                    </div>
                                    <div>
                                        <label class="field-label">Subheadline Font Size <span class="text-gray-400 font-normal">({{ editDraft.hero.subheadline_font_size || 20 }}px)</span></label>
                                        <input type="range" v-model.number="editDraft.hero.subheadline_font_size" min="12" max="40" step="1" class="w-full accent-indigo-600" />
                                    </div>
                                    <div>
                                        <label class="field-label">CTA Button Label</label>
                                        <input v-model="editDraft.hero.cta_label" type="text" class="field-input" />
                                    </div>
                                    <div>
                                        <label class="field-label">Video Type</label>
                                        <div class="flex items-center gap-1 p-0.5 bg-gray-100 rounded-lg w-fit">
                                            <button type="button" @click="editDraft.hero.video_type = 'vsl'" class="px-3 py-1 text-xs font-medium rounded-md transition-colors" :class="(!editDraft.hero.video_type || editDraft.hero.video_type === 'vsl') ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                                VSL Video URL
                                            </button>
                                            <button type="button" @click="editDraft.hero.video_type = 'embed'" class="px-3 py-1 text-xs font-medium rounded-md transition-colors" :class="editDraft.hero.video_type === 'embed' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                                Embed Script
                                            </button>
                                        </div>
                                    </div>
                                    <div v-if="!editDraft.hero.video_type || editDraft.hero.video_type === 'vsl'">
                                        <label class="field-label">VSL Video URL <span class="text-gray-400 font-normal">(YouTube, Drive, Vimeo)</span></label>
                                        <input v-model="editDraft.hero.vsl_url" type="url" placeholder="https://youtube.com/watch?v=..." class="field-input" />
                                    </div>
                                    <div v-else>
                                        <label class="field-label">Embed Code <span class="text-gray-400 font-normal">(paste iframe / script embed)</span></label>
                                        <textarea v-model="editDraft.hero.embed_html" rows="5" placeholder="Paste your embed code here (converteai, Vimeo, etc.)" class="field-input font-mono resize-none" />
                                    </div>
                                    <div>
                                        <label class="field-label">Background Image</label>
                                        <div class="flex items-center gap-2">
                                            <input v-model="editDraft.hero.bg_image" type="url" placeholder="https://... or upload below" class="field-input flex-1" />
                                        </div>
                                        <label class="mt-2 flex items-center gap-2 cursor-pointer text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                            Upload image
                                            <input type="file" accept="image/*" class="sr-only" @change="uploadImage('hero', $event)" />
                                        </label>
                                        <div v-if="uploadLoading === 'hero'" class="text-xs text-indigo-600 mt-1">Uploading…</div>
                                    </div>
                                    <div class="pt-2 border-t border-gray-200 mt-1">
                                        <label class="field-label mb-2">Button Colors</label>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="text-[10px] text-gray-400 font-medium">Button BG</label>
                                                <div class="flex items-center gap-2">
                                                    <input type="color" v-model="editDraft.hero.btn_bg" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                    <input v-model="editDraft.hero.btn_bg" type="text" placeholder="#fbbf24" class="field-input flex-1 text-xs" />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="text-[10px] text-gray-400 font-medium">Button Text</label>
                                                <div class="flex items-center gap-2">
                                                    <input type="color" v-model="editDraft.hero.btn_text" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                    <input v-model="editDraft.hero.btn_text" type="text" placeholder="#111827" class="field-input flex-1 text-xs" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- SOCIAL PROOF -->
                                <template v-if="sec.type === 'social_proof'">
                                    <div v-if="!editDraft.social_proof" class="text-xs text-gray-500">
                                        <button @click="editDraft.social_proof = { stat_label: 'members and growing', trust_line: '' }" class="text-indigo-600 font-medium hover:underline">+ Initialize section</button>
                                    </div>
                                    <template v-else>
                                        <div>
                                            <label class="field-label">Stat Label <span class="text-gray-400 font-normal">(e.g. "members and growing")</span></label>
                                            <input v-model="editDraft.social_proof.stat_label" type="text" class="field-input" />
                                        </div>
                                        <div>
                                            <label class="field-label">Trust Line</label>
                                            <input v-model="editDraft.social_proof.trust_line" type="text" class="field-input" />
                                        </div>
                                        <div>
                                            <label class="field-label">Background Color</label>
                                            <div class="flex items-center gap-2">
                                                <input type="color" v-model="editDraft.social_proof.bg_color" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                <input v-model="editDraft.social_proof.bg_color" type="text" placeholder="#4f46e5" class="field-input flex-1 text-xs" />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" v-model="editDraft.social_proof.hide_avatars" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                                <span class="field-label !mb-0">Hide avatar circles</span>
                                            </label>
                                        </div>
                                    </template>
                                </template>

                                <!-- BENEFITS -->
                                <template v-if="sec.type === 'benefits'">
                                    <div v-if="!editDraft.benefits" class="text-xs text-gray-500">
                                        <button @click="editDraft.benefits = { headline: '', items: [] }" class="text-indigo-600 font-medium hover:underline">+ Initialize section</button>
                                    </div>
                                    <template v-else>
                                        <div>
                                            <label class="field-label">Section Headline</label>
                                            <input v-model="editDraft.benefits.headline" type="text" class="field-input" />
                                        </div>
                                        <div v-for="(item, i) in editDraft.benefits.items" :key="i" class="bg-white rounded-xl p-3 border border-gray-200 space-y-2">
                                            <div class="flex gap-2 items-center">
                                                <input v-model="item.icon" type="text" placeholder="🎯" class="field-input w-10 text-center text-base shrink-0" />
                                                <div class="flex-1">
                                                    <label class="text-[10px] text-gray-400 font-medium">Title</label>
                                                    <input v-model="item.title" type="text" placeholder="Benefit title" class="field-input w-full" />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="text-[10px] text-gray-400 font-medium">Description</label>
                                                <textarea v-model="item.body" rows="2" placeholder="Description…" class="field-input resize-none w-full" />
                                            </div>
                                        </div>
                                        <button @click="editDraft.benefits.items.push({ icon: '✨', title: '', body: '' })"
                                            class="text-xs text-indigo-600 font-medium hover:underline">+ Add item</button>
                                    </template>
                                </template>

                                <!-- FOR YOU -->
                                <template v-if="sec.type === 'for_you'">
                                    <div v-if="!editDraft.for_you" class="text-xs text-gray-500">
                                        <button @click="editDraft.for_you = { headline: 'This is for you if...', points: ['', '', ''] }" class="text-indigo-600 font-medium hover:underline">+ Initialize section</button>
                                    </div>
                                    <template v-else>
                                        <div>
                                            <label class="field-label">Section Headline</label>
                                            <input v-model="editDraft.for_you.headline" type="text" class="field-input" />
                                        </div>
                                        <div v-for="(_, i) in editDraft.for_you.points" :key="i">
                                            <label class="field-label">Point {{ i + 1 }}</label>
                                            <input v-model="editDraft.for_you.points[i]" type="text" class="field-input" />
                                        </div>
                                        <button @click="editDraft.for_you.points.push('')" class="text-xs text-indigo-600 font-medium hover:underline">+ Add point</button>
                                    </template>
                                </template>

                                <!-- CREATOR -->
                                <template v-if="sec.type === 'creator'">
                                    <div v-if="!editDraft.creator" class="text-xs text-gray-500">
                                        <button @click="editDraft.creator = { headline: 'Meet Your Coach', bio: '', name: '', photo: '' }" class="text-indigo-600 font-medium hover:underline">+ Initialize section</button>
                                    </div>
                                    <template v-else>
                                        <div>
                                            <label class="field-label">Photo</label>
                                            <div class="flex items-center gap-3">
                                                <div class="w-14 h-14 rounded-xl overflow-hidden bg-gray-100 shrink-0">
                                                    <img v-if="editDraft.creator.photo || community.owner?.avatar"
                                                         :src="editDraft.creator.photo || community.owner?.avatar"
                                                         class="w-full h-full object-cover" />
                                                    <div v-else class="w-full h-full flex items-center justify-center text-lg font-black text-gray-400">
                                                        {{ community.owner?.name?.charAt(0) ?? '?' }}
                                                    </div>
                                                </div>
                                                <label class="relative cursor-pointer text-xs text-indigo-600 font-medium hover:underline">
                                                    {{ uploadLoading === 'creator' ? 'Uploading…' : 'Change photo' }}
                                                    <input type="file" accept="image/*" class="sr-only" @change="uploadImage('creator', $event)" />
                                                </label>
                                                <button v-if="editDraft.creator.photo" @click="editDraft.creator.photo = ''"
                                                        class="text-xs text-red-400 hover:text-red-600">Remove</button>
                                            </div>
                                            <p class="text-xs text-gray-400 mt-1">Leave empty to use account avatar</p>
                                        </div>
                                        <div>
                                            <label class="field-label">Display Name</label>
                                            <input v-model="editDraft.creator.name" type="text" class="field-input" :placeholder="community.owner?.name ?? 'Creator name'" />
                                            <p class="text-xs text-gray-400 mt-1">Leave empty to use account name</p>
                                        </div>
                                        <div>
                                            <label class="field-label">Headline <span class="text-gray-400 font-normal">(e.g. "Meet Your Coach")</span></label>
                                            <input v-model="editDraft.creator.headline" type="text" class="field-input" />
                                        </div>
                                        <div>
                                            <label class="field-label">Bio</label>
                                            <textarea v-model="editDraft.creator.bio" rows="4" class="field-input resize-none" />
                                        </div>
                                    </template>
                                </template>

                                <!-- VIDEO (generic handler for all 3 video sections) -->
                                <template v-if="sec.type === 'video_creator' || sec.type === 'video_testimonials' || sec.type === 'video_courses'">
                                    <div v-if="!editDraft[sec.type]" class="text-xs text-gray-500">
                                        <button @click="editDraft[sec.type] = { embed_html: '', video_url: '' }" class="text-indigo-600 font-medium hover:underline">+ Initialize section</button>
                                    </div>
                                    <template v-else>
                                        <div>
                                            <label class="field-label">Video URL <span class="text-gray-400 font-normal">(YouTube, Vimeo, or Google Drive link)</span></label>
                                            <input v-model="editDraft[sec.type].video_url" type="url" placeholder="https://youtube.com/watch?v=..." class="field-input" />
                                        </div>
                                        <div v-if="canUploadSectionVideo">
                                            <p class="text-xs text-gray-500 mb-1.5 font-medium">
                                                Upload Video
                                                <span class="ml-1 px-1.5 py-0.5 bg-indigo-100 text-indigo-700 text-[10px] font-bold rounded-full uppercase">Pro</span>
                                            </p>
                                            <label class="flex items-center justify-center gap-2 px-3 py-2.5 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/50 transition-colors">
                                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                                </svg>
                                                <span class="text-xs text-gray-500">
                                                    {{ sectionVideoUploading === sec.type ? `Uploading... ${sectionVideoProgress}%` : 'Choose video file (MP4, WebM, MOV — max 500MB)' }}
                                                </span>
                                                <input
                                                    type="file"
                                                    accept="video/mp4,video/webm,video/quicktime"
                                                    class="hidden"
                                                    :disabled="sectionVideoUploading === sec.type"
                                                    @change="handleSectionVideoUpload(sec.type, $event)"
                                                />
                                            </label>
                                            <p v-if="sectionVideoError" class="text-xs text-red-500 mt-1">{{ sectionVideoError }}</p>
                                        </div>
                                        <div>
                                            <label class="field-label">Embed Code <span class="text-gray-400 font-normal">(paste YouTube / Vimeo iframe or any embed script)</span></label>
                                            <textarea v-model="editDraft[sec.type].embed_html" rows="5" placeholder='<iframe src="https://www.youtube.com/embed/..." ...></iframe>' class="field-input font-mono resize-none" />
                                        </div>
                                        <p class="text-xs text-gray-400">Paste the full embed code from YouTube, Vimeo, or any video platform.</p>
                                    </template>
                                </template>

                                <!-- TESTIMONIALS -->
                                <template v-if="sec.type === 'testimonials'">
                                    <div>
                                        <label class="field-label">Display Type</label>
                                        <div class="flex items-center gap-1 p-0.5 bg-gray-100 rounded-lg w-fit">
                                            <button type="button" @click="editDraft.testimonials_type = 'manual'" class="px-3 py-1 text-xs font-medium rounded-md transition-colors" :class="(!editDraft.testimonials_type || editDraft.testimonials_type === 'manual') ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                                Manual
                                            </button>
                                            <button type="button" @click="editDraft.testimonials_type = 'embed'" class="px-3 py-1 text-xs font-medium rounded-md transition-colors" :class="editDraft.testimonials_type === 'embed' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                                Embed Script
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Manual testimonials -->
                                    <template v-if="!editDraft.testimonials_type || editDraft.testimonials_type === 'manual'">
                                        <div v-if="!editDraft.testimonials?.length" class="text-xs text-gray-500">
                                            <button @click="editDraft.testimonials = [{ name: '', role: '', quote: '' }]" class="text-indigo-600 font-medium hover:underline">+ Initialize section</button>
                                        </div>
                                        <template v-else>
                                            <div v-for="(t, i) in editDraft.testimonials" :key="i" class="bg-white rounded-xl p-3 border border-gray-200 space-y-2">
                                                <div class="flex gap-2">
                                                    <input v-model="t.name" type="text" placeholder="Name" class="field-input flex-1" />
                                                    <input v-model="t.role" type="text" placeholder="Role" class="field-input flex-1" />
                                                </div>
                                                <textarea v-model="t.quote" rows="2" placeholder="Quote…" class="field-input resize-none w-full" />
                                                <button @click="editDraft.testimonials.splice(i, 1)" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                                            </div>
                                            <button @click="editDraft.testimonials.push({ name: '', role: '', quote: '' })" class="text-xs text-indigo-600 font-medium hover:underline">+ Add testimonial</button>
                                        </template>
                                    </template>

                                    <!-- Embed script -->
                                    <div v-else>
                                        <label class="field-label">Embed Code <span class="text-gray-400 font-normal">(paste iframe / script embed)</span></label>
                                        <textarea v-model="editDraft.testimonials_embed_html" rows="5" placeholder="Paste your testimonial widget embed code here (e.g. Senja, Testimonial.to, etc.)" class="field-input font-mono resize-none" />
                                    </div>
                                </template>

                                <!-- OFFER STACK -->
                                <template v-if="sec.type === 'offer_stack'">
                                    <div v-if="!editDraft.offer_stack" class="text-xs text-gray-500">
                                        <button @click="editDraft.offer_stack = { headline: 'Here\'s everything you get today', items: [], total_value: '', price: '', price_note: '' }" class="text-indigo-600 font-medium hover:underline">+ Initialize section</button>
                                    </div>
                                    <template v-else>
                                        <div>
                                            <label class="field-label">Section Headline</label>
                                            <input v-model="editDraft.offer_stack.headline" type="text" class="field-input" />
                                        </div>
                                        <div v-for="(item, i) in editDraft.offer_stack.items" :key="i" class="bg-white rounded-xl p-3 border border-gray-200 space-y-2">
                                            <input v-model="item.name" type="text" placeholder="Item name (e.g. The Faceless Marketer Community)" class="field-input w-full font-medium" />
                                            <textarea v-model="item.description" rows="2" placeholder="Description (what it includes…)" class="field-input resize-none w-full" />
                                            <input v-model="item.value" type="text" placeholder="₱5,000 (original value)" class="field-input w-full" />
                                            <button @click="editDraft.offer_stack.items.splice(i, 1)" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                                        </div>
                                        <button @click="editDraft.offer_stack.items.push({ name: '', value: '', description: '' })" class="text-xs text-indigo-600 font-medium hover:underline">+ Add item</button>
                                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-gray-200 mt-1">
                                            <div>
                                                <label class="field-label">Total Value</label>
                                                <input v-model="editDraft.offer_stack.total_value" type="text" placeholder="₱10,000" class="field-input" />
                                            </div>
                                            <div>
                                                <label class="field-label">Your Price</label>
                                                <input v-model="editDraft.offer_stack.price" type="text" placeholder="₱999" class="field-input" />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="field-label">Price Note <span class="text-gray-400 font-normal">(e.g. /month)</span></label>
                                            <input v-model="editDraft.offer_stack.price_note" type="text" placeholder="/month" class="field-input" />
                                        </div>
                                        <div class="pt-2 border-t border-gray-200 mt-1">
                                            <label class="field-label mb-2">Colors</label>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="text-[10px] text-gray-400 font-medium">Background</label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="color" v-model="editDraft.offer_stack.bg_color" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                        <input v-model="editDraft.offer_stack.bg_color" type="text" placeholder="#1e1b4b" class="field-input flex-1 text-xs" />
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="text-[10px] text-gray-400 font-medium">Price Color</label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="color" v-model="editDraft.offer_stack.price_color" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                        <input v-model="editDraft.offer_stack.price_color" type="text" placeholder="#fbbf24" class="field-input flex-1 text-xs" />
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="text-[10px] text-gray-400 font-medium">Button BG</label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="color" v-model="editDraft.offer_stack.btn_bg" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                        <input v-model="editDraft.offer_stack.btn_bg" type="text" placeholder="#fbbf24" class="field-input flex-1 text-xs" />
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="text-[10px] text-gray-400 font-medium">Button Text</label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="color" v-model="editDraft.offer_stack.btn_text" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                        <input v-model="editDraft.offer_stack.btn_text" type="text" placeholder="#111827" class="field-input flex-1 text-xs" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="field-label">CTA Button Label</label>
                                            <input v-model="editDraft.offer_stack.cta_label" type="text" placeholder="Get Access Now" class="field-input" />
                                        </div>
                                    </template>
                                </template>

                                <!-- INCLUDED COURSES -->
                                <template v-if="sec.type === 'included_courses'">
                                    <div v-if="!props.allCourses.length" class="text-xs text-gray-500 italic">
                                        No published courses yet. Add courses in the Classroom.
                                    </div>
                                    <template v-else>
                                        <div>
                                            <label class="field-label">Section Headline</label>
                                            <input v-model="editDraft.included_courses_headline" type="text" placeholder="Everything included in your membership" class="field-input" />
                                        </div>
                                        <div>
                                            <label class="field-label">Background Color</label>
                                            <div class="flex items-center gap-2">
                                                <input type="color" v-model="editDraft.included_courses_bg_color" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                <input v-model="editDraft.included_courses_bg_color" type="text" placeholder="#f9fafb" class="field-input flex-1 text-xs" />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="field-label">Button Background</label>
                                            <div class="flex items-center gap-2">
                                                <input type="color" v-model="editDraft.included_courses_btn_bg" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                <input v-model="editDraft.included_courses_btn_bg" type="text" placeholder="#059669" class="field-input flex-1 text-xs" />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="field-label">Button Text Color</label>
                                            <div class="flex items-center gap-2">
                                                <input type="color" v-model="editDraft.included_courses_btn_text" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                <input v-model="editDraft.included_courses_btn_text" type="text" placeholder="#ffffff" class="field-input flex-1 text-xs" />
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-400">Select which courses to display on this landing page.</p>
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="field-label mb-0">Courses</label>
                                            <button type="button" @click="toggleAllCourses" class="text-xs text-indigo-600 hover:underline">
                                                {{ allCoursesSelected ? 'Deselect All' : 'Select All' }}
                                            </button>
                                        </div>
                                        <div v-for="c in props.allCourses" :key="c.id"
                                            class="flex items-center gap-3 bg-white rounded-xl p-3 border border-gray-200 cursor-pointer hover:border-indigo-300 transition-colors"
                                            @click="toggleCourseSelection(c.id)">
                                            <input type="checkbox"
                                                :checked="(editDraft.included_courses_selected ?? []).includes(c.id)"
                                                @click.stop="toggleCourseSelection(c.id)"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 shrink-0" />
                                            <div class="w-10 h-10 rounded-lg overflow-hidden shrink-0 bg-indigo-100 flex items-center justify-center text-lg">
                                                <img v-if="c.cover_image" :src="c.cover_image" class="w-full h-full object-cover" />
                                                <span v-else>🎓</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-gray-800 truncate">{{ c.title }}</p>
                                                <span class="text-[10px] text-gray-400">{{ c.access_type }}</span>
                                            </div>
                                        </div>
                                    </template>
                                </template>

                                <!-- CERTIFICATIONS -->
                                <template v-if="sec.type === 'certifications'">
                                    <div v-if="!props.certifications.length" class="text-xs text-gray-500 italic">
                                        No certification exams yet. Create one in the Certifications tab.
                                    </div>
                                    <template v-else>
                                        <div>
                                            <label class="field-label">Section Headline</label>
                                            <input v-model="editDraft.certifications_headline" type="text" placeholder="Get Certified" class="field-input" />
                                        </div>
                                        <p class="text-xs text-gray-400">Certifications are pulled automatically from your community exams.</p>
                                        <div v-for="c in props.certifications" :key="c.id" class="flex items-center gap-3 bg-white rounded-xl p-3 border border-gray-200">
                                            <div class="w-10 h-10 rounded-lg overflow-hidden shrink-0 bg-amber-100 flex items-center justify-center text-lg">
                                                <img v-if="c.cover_image" :src="c.cover_image" class="w-full h-full object-cover" />
                                                <span v-else>🏆</span>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-800 truncate">{{ c.title }}</p>
                                                <p class="text-xs text-gray-400">{{ c.questions_count }} questions</p>
                                            </div>
                                        </div>
                                    </template>
                                </template>

                                <!-- GUARANTEE -->
                                <template v-if="sec.type === 'guarantee'">
                                    <div v-if="!editDraft.guarantee" class="text-xs text-gray-500">
                                        <button @click="editDraft.guarantee = { headline: '100% Money-Back Guarantee', days: 30, body: '' }" class="text-indigo-600 font-medium hover:underline">+ Initialize section</button>
                                    </div>
                                    <template v-else>
                                        <div>
                                            <label class="field-label">Headline</label>
                                            <input v-model="editDraft.guarantee.headline" type="text" class="field-input" />
                                        </div>
                                        <div>
                                            <label class="field-label">Days</label>
                                            <input v-model.number="editDraft.guarantee.days" type="number" min="1" max="365" class="field-input" />
                                        </div>
                                        <div>
                                            <label class="field-label">Guarantee Statement</label>
                                            <textarea v-model="editDraft.guarantee.body" rows="3" class="field-input resize-none" />
                                        </div>
                                    </template>
                                </template>

                                <!-- PRICE JUSTIFICATION -->
                                <template v-if="sec.type === 'price_justification'">
                                    <div v-if="!editDraft.price_justification" class="text-xs text-gray-500">
                                        <button @click="editDraft.price_justification = { headline: 'You have two choices', options: [{ label: 'Option 1', description: '' }, { label: 'Option 2', description: '' }, { label: 'The Smart Choice', description: '' }] }" class="text-indigo-600 font-medium hover:underline">+ Initialize section</button>
                                    </div>
                                    <template v-else>
                                        <div>
                                            <label class="field-label">Headline</label>
                                            <input v-model="editDraft.price_justification.headline" type="text" class="field-input" />
                                        </div>
                                        <div v-for="(opt, i) in editDraft.price_justification.options" :key="i" class="bg-white rounded-xl p-3 border border-gray-200 space-y-2">
                                            <input v-model="opt.label" type="text" placeholder="Option label" class="field-input font-medium" />
                                            <textarea v-model="opt.description" rows="2" placeholder="Description…" class="field-input resize-none w-full" />
                                        </div>
                                        <button @click="editDraft.price_justification.options.push({ label: '', description: '' })" class="text-xs text-indigo-600 font-medium hover:underline">+ Add option</button>
                                        <div class="pt-2 border-t border-gray-200 mt-1">
                                            <label class="field-label">Background Color</label>
                                            <div class="flex items-center gap-2">
                                                <input type="color" v-model="editDraft.price_justification.bg_color" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                <input v-model="editDraft.price_justification.bg_color" type="text" placeholder="#f9fafb" class="field-input flex-1 text-xs" />
                                            </div>
                                        </div>
                                    </template>
                                </template>

                                <!-- FAQ -->
                                <template v-if="sec.type === 'faq'">
                                    <div v-if="!editDraft.faq?.length" class="text-xs text-gray-500">
                                        <button @click="editDraft.faq = [{ question: '', answer: '' }]" class="text-indigo-600 font-medium hover:underline">+ Initialize section</button>
                                    </div>
                                    <template v-else>
                                        <div v-for="(item, i) in editDraft.faq" :key="i" class="bg-white rounded-xl p-3 border border-gray-200 space-y-2">
                                            <input v-model="item.question" type="text" placeholder="Question" class="field-input w-full font-medium" />
                                            <textarea v-model="item.answer" rows="2" placeholder="Answer…" class="field-input resize-none w-full" />
                                            <button @click="editDraft.faq.splice(i, 1)" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                                        </div>
                                        <button @click="editDraft.faq.push({ question: '', answer: '' })" class="text-xs text-indigo-600 font-medium hover:underline">+ Add FAQ</button>
                                    </template>
                                </template>

                                <template v-if="sec.type === 'cta_section'">
                                    <div v-if="!editDraft.cta_section" class="text-xs text-gray-500">
                                        <button @click="editDraft.cta_section = { headline: '', subtext: '', cta_label: '' }" class="text-indigo-600 font-medium hover:underline">+ Initialize section</button>
                                    </div>
                                    <template v-else>
                                        <div>
                                            <label class="field-label">Closing Headline</label>
                                            <input v-model="editDraft.cta_section.headline" type="text" class="field-input" />
                                        </div>
                                        <div>
                                            <label class="field-label">Subtext</label>
                                            <input v-model="editDraft.cta_section.subtext" type="text" class="field-input" />
                                        </div>
                                        <div>
                                            <label class="field-label">CTA Button Label</label>
                                            <input v-model="editDraft.cta_section.cta_label" type="text" class="field-input" />
                                        </div>
                                        <div class="pt-2 border-t border-gray-200 mt-1">
                                            <label class="field-label mb-2">Button Colors</label>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="text-[10px] text-gray-400 font-medium">Button BG</label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="color" v-model="editDraft.cta_section.btn_bg" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                        <input v-model="editDraft.cta_section.btn_bg" type="text" placeholder="#fbbf24" class="field-input flex-1 text-xs" />
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="text-[10px] text-gray-400 font-medium">Button Text</label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="color" v-model="editDraft.cta_section.btn_text" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                        <input v-model="editDraft.cta_section.btn_text" type="text" placeholder="#111827" class="field-input flex-1 text-xs" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="field-label">Background Image</label>
                                            <input v-model="editDraft.cta_section.bg_image" type="url" placeholder="https://…" class="field-input" />
                                            <label class="mt-2 flex items-center gap-2 cursor-pointer text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                                Upload image
                                                <input type="file" accept="image/*" class="sr-only" @change="uploadImage('cta_section', $event)" />
                                            </label>
                                            <div v-if="uploadLoading === 'cta_section'" class="text-xs text-indigo-600 mt-1">Uploading…</div>
                                        </div>
                                    </template>
                                </template>

                            </div>
                        </div>

                    </div>

                    <!-- Sticky footer: Add Section -->
                    <div class="shrink-0 border-t border-gray-100 bg-white">
                        <!-- collapsed toggle -->
                        <button @click="showAddSection = !showAddSection"
                            class="w-full flex items-center justify-between px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                Add Section
                            </span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="showAddSection ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div v-if="showAddSection" class="px-5 pb-4">
                            <div v-if="availableSectionsToAdd.length > 0" class="flex flex-wrap gap-2">
                                <button
                                    v-for="type in availableSectionsToAdd" :key="type"
                                    @click="addSection(type)"
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-gray-100 hover:bg-indigo-50 hover:text-indigo-700 text-gray-600 rounded-lg border border-gray-200 hover:border-indigo-200 transition">
                                    <span>{{ SECTION_DEFS[type]?.icon }}</span>
                                    {{ SECTION_DEFS[type]?.label }}
                                </button>
                            </div>
                            <p v-else class="text-xs text-gray-400">All sections are already added. Remove a section above to re-add it.</p>
                        </div>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ═══════════════════════════════════════════════════════════
         EMPTY STATES
    ════════════════════════════════════════════════════════════ -->

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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
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

    <!-- ═══════════════════════════════════════════════════════════
         FULL LANDING PAGE
    ════════════════════════════════════════════════════════════ -->
    <div v-else :class="isOwner ? 'pt-12' : ''" class="bg-white font-sans antialiased">

        <!-- Inline edit mode hint bar -->
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 -translate-y-2" leave-active-class="transition duration-150" leave-to-class="opacity-0 -translate-y-2">
            <div v-if="isOwner && inlineMode" class="sticky top-12 z-40 bg-amber-400 text-gray-900 text-xs font-semibold text-center py-2 px-4">
                ✏️ Click on any text to edit it. Use the toolbar to format. Click away to save automatically.
            </div>
        </Transition>

        <!-- ── HERO ── -->
        <section v-if="isVisible('hero')" class="relative overflow-hidden bg-linear-to-br from-slate-900 via-indigo-950 to-slate-900 text-white">
            <button v-if="isOwner && (inlineMode || showEditPanel)"
                @click="openColorPopover($event, [
                    { label: 'Button Background', path: 'hero.btn_bg', fallback: '#fbbf24' },
                    { label: 'Button Text', path: 'hero.btn_text', fallback: '#111827' },
                ])"
                class="absolute top-3 left-3 z-20 w-8 h-8 bg-white/20 hover:bg-white/40 rounded-full shadow-lg flex items-center justify-center transition hover:scale-110 backdrop-blur"
                title="Edit colors">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
            </button>
            <!-- Background image (custom upload or community cover) -->
            <div v-if="lp.hero?.bg_image || community.cover_image" class="absolute inset-0 opacity-20">
                <img :src="lp.hero?.bg_image || community.cover_image" class="w-full h-full object-cover" />
                <div class="absolute inset-0 bg-gradient-to-b from-slate-900/60 via-transparent to-slate-900" />
            </div>
            <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-150 h-150 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none" />

            <!-- Invited-by pill -->
            <div v-if="invitedBy" class="relative z-10 flex justify-center pt-10">
                <div class="flex items-center gap-2.5 bg-white/10 backdrop-blur border border-white/20 rounded-full pl-1.5 pr-4 py-1.5">
                    <div class="w-8 h-8 rounded-full bg-indigo-400 overflow-hidden shrink-0">
                        <img v-if="invitedBy.avatar" :src="invitedBy.avatar" class="w-full h-full object-cover" />
                        <span v-else class="flex items-center justify-center w-full h-full text-xs font-bold text-white">{{ invitedBy.name.charAt(0) }}</span>
                    </div>
                    <p class="text-sm text-white/80"><span class="font-semibold text-white">{{ invitedBy.name }}</span> invited you</p>
                </div>
            </div>

            <div class="relative z-10 max-w-3xl mx-auto px-6 py-24 text-center">
                <!-- Pre-headline -->
                <p v-if="lp.hero?.pre_headline"
                    v-html="lp.hero.pre_headline"
                    :contenteditable="inlineMode ? 'true' : 'false'"
                    @focus="inlineMode && onElFocus($event, 'hero.pre_headline')"
                    @blur="inlineMode && saveFromEl($event, 'hero.pre_headline')"
                    @keydown.enter.prevent
                    :class="['text-indigo-300 text-sm font-semibold uppercase tracking-widest mb-4', inlineMode ? editableClass : '']"
                />
                <!-- Badge -->
                <div v-else class="inline-flex items-center gap-2 bg-indigo-500/30 border border-indigo-400/40 text-indigo-200 text-xs font-semibold px-4 py-1.5 rounded-full mb-8 uppercase tracking-widest">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 inline-block"></span>
                    {{ community.category || 'Community' }}
                </div>

                <h1
                    :key="renderKey + '_hero_h'"
                    v-html="lp.hero.headline"
                    :contenteditable="inlineMode ? 'true' : 'false'"
                    @focus="inlineMode && onElFocus($event, 'hero.headline')"
                    @blur="inlineMode && saveFromEl($event, 'hero.headline')"
                    @keydown.enter.prevent
                    :class="[lp.hero.headline_font_size ? '' : 'text-4xl sm:text-5xl lg:text-6xl', 'font-black leading-tight mb-6 text-white', inlineMode ? editableClass : '']"
                    :style="lp.hero.headline_font_size ? { fontSize: lp.hero.headline_font_size + 'px' } : {}"
                />
                <p
                    :key="renderKey + '_hero_sub'"
                    v-html="lp.hero.subheadline"
                    :contenteditable="inlineMode ? 'true' : 'false'"
                    @focus="inlineMode && onElFocus($event, 'hero.subheadline')"
                    @blur="inlineMode && saveFromEl($event, 'hero.subheadline')"
                    :class="[lp.hero.subheadline_font_size ? '' : 'text-lg sm:text-xl', 'text-slate-300 mb-10 max-w-xl mx-auto leading-relaxed', inlineMode ? editableClass : '']"
                    :style="lp.hero.subheadline_font_size ? { fontSize: lp.hero.subheadline_font_size + 'px' } : {}"
                />

                <!-- VSL Video -->
                <div v-if="(!lp.hero.video_type || lp.hero.video_type === 'vsl') && lp.hero.vsl_url" class="mb-10 w-full max-w-2xl mx-auto rounded-2xl overflow-hidden shadow-2xl shadow-black/40 border border-white/10">
                    <p class="text-xs text-indigo-300 text-center py-2 bg-black/30 font-medium tracking-wide uppercase">🔊 Make sure your sound is on</p>
                    <div class="relative w-full" style="padding-bottom: 56.25%;">
                        <iframe
                            :src="normalizeVideoUrl(lp.hero.vsl_url)"
                            class="absolute inset-0 w-full h-full"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        />
                    </div>
                </div>

                <!-- Embed Script -->
                <div v-else-if="lp.hero.video_type === 'embed' && lp.hero.embed_html" class="mb-10 w-full max-w-2xl mx-auto">
                    <SafeHtmlRenderer :html="lp.hero.embed_html" />
                </div>

                <button @click="(inlineMode || showEditPanel) ? openColorPopover($event, [
                        { label: 'Button Background', path: 'hero.btn_bg', fallback: '#fbbf24' },
                        { label: 'Button Text', path: 'hero.btn_text', fallback: '#111827' },
                    ]) : handleCta()"
                    class="inline-flex items-center gap-2 px-10 py-4 font-black text-lg rounded-2xl transition-all shadow-xl uppercase tracking-wide hover:scale-105 active:scale-95 hover:brightness-110"
                    :style="{ backgroundColor: lp.hero?.btn_bg || '#fbbf24', color: lp.hero?.btn_text || '#111827' }">
                    {{ lp.hero.cta_label }}
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </button>

                <p class="mt-4 text-slate-400 text-sm">
                    {{ community.price > 0
                        ? `${community.currency ?? 'PHP'} ${Number(community.price).toLocaleString()}${community.billing_type === 'one_time' ? ' one-time' : '/month'}`
                        : 'Free to join' }}
                </p>
            </div>
        </section>

        <!-- ── SOCIAL PROOF BAR ── -->
        <section v-if="isVisible('social_proof') && lp.social_proof" class="text-white py-5"
            :style="{ backgroundColor: lp.social_proof.bg_color || '#4f46e5' }">
            <div class="max-w-4xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-center gap-4 text-center sm:text-left">
                <div class="flex items-center gap-3">
                    <div v-if="!lp.social_proof.hide_avatars" class="flex -space-x-2">
                        <div v-for="i in 4" :key="i"
                            class="w-8 h-8 rounded-full border-2 overflow-hidden flex items-center justify-center text-xs font-bold text-white"
                            :style="`background-color: hsl(${i * 60}, 70%, 60%); border-color: ${lp.social_proof.bg_color || '#4f46e5'}`">
                            {{ String.fromCharCode(64 + i) }}
                        </div>
                    </div>
                    <div>
                        <span class="font-black text-xl">{{ formatCount(community.members_count) }}</span>
                        <span class="ml-1.5 text-white/70">{{ lp.social_proof.stat_label }}</span>
                    </div>
                </div>
                <div class="hidden sm:block w-px h-6 bg-white/20"></div>
                <p v-html="lp.social_proof.trust_line"
                    :contenteditable="inlineMode ? 'true' : 'false'"
                    @focus="inlineMode && onElFocus($event, 'social_proof.trust_line')"
                    @blur="inlineMode && saveFromEl($event, 'social_proof.trust_line')"
                    @keydown.enter.prevent
                    :class="['text-white/80 text-sm font-medium', inlineMode ? editableClass : '']"
                />
            </div>
        </section>

        <!-- ── BENEFITS ── -->
        <section v-if="isVisible('benefits') && lp.benefits" class="py-24 bg-white">
            <div class="max-w-5xl mx-auto px-6">
                <h2 v-html="lp.benefits.headline"
                    :contenteditable="inlineMode ? 'true' : 'false'"
                    @focus="inlineMode && onElFocus($event, 'benefits.headline')"
                    @blur="inlineMode && saveFromEl($event, 'benefits.headline')"
                    @keydown.enter.prevent
                    :class="['text-3xl sm:text-4xl font-black text-gray-900 text-center mb-16', inlineMode ? editableClass : '']"
                />
                <div class="grid sm:grid-cols-2 gap-8">
                    <div v-for="(item, i) in lp.benefits.items" :key="i"
                        class="flex gap-5 p-6 rounded-2xl border border-gray-100 bg-gray-50 hover:border-indigo-200 hover:bg-indigo-50/40 transition-all group">
                        <div class="w-12 h-12 shrink-0 rounded-xl bg-indigo-100 flex items-center justify-center text-2xl group-hover:bg-indigo-200 transition">
                            {{ item.icon }}
                        </div>
                        <div>
                            <h3 v-html="item.title"
                                :contenteditable="inlineMode ? 'true' : 'false'"
                                @focus="inlineMode && onElFocus($event, `benefits.items.${i}.title`)"
                                @blur="inlineMode && saveFromEl($event, `benefits.items.${i}.title`)"
                                @keydown.enter.prevent
                                :class="['font-bold text-gray-900 mb-1.5', inlineMode ? editableClass : '']"
                            />
                            <p v-html="item.body"
                                :contenteditable="inlineMode ? 'true' : 'false'"
                                @focus="inlineMode && onElFocus($event, `benefits.items.${i}.body`)"
                                @blur="inlineMode && saveFromEl($event, `benefits.items.${i}.body`)"
                                :class="['text-sm text-gray-500 leading-relaxed', inlineMode ? editableClass : '']"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── FOR YOU ── -->
        <section v-if="isVisible('for_you') && lp.for_you" class="py-20 bg-linear-to-br from-indigo-50 to-white">
            <div class="max-w-2xl mx-auto px-6 text-center">
                <h2 v-html="lp.for_you.headline"
                    :contenteditable="inlineMode ? 'true' : 'false'"
                    @focus="inlineMode && onElFocus($event, 'for_you.headline')"
                    @blur="inlineMode && saveFromEl($event, 'for_you.headline')"
                    @keydown.enter.prevent
                    :class="['text-3xl sm:text-4xl font-black text-gray-900 mb-12', inlineMode ? editableClass : '']"
                />
                <div class="space-y-4 text-left">
                    <div v-for="(point, i) in lp.for_you.points" :key="i"
                        class="flex items-start gap-4 bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
                        <div class="w-7 h-7 rounded-full bg-emerald-100 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        <p v-html="point"
                            :contenteditable="inlineMode ? 'true' : 'false'"
                            @focus="inlineMode && onElFocus($event, `for_you.points.${i}`)"
                            @blur="inlineMode && saveFromEl($event, `for_you.points.${i}`)"
                            @keydown.enter.prevent
                            :class="['text-gray-700 font-medium leading-relaxed flex-1', inlineMode ? editableClass : '']"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- ── CREATOR / AUTHORITY ── -->
        <section v-if="isVisible('creator') && lp.creator" class="py-24 bg-white">
            <div class="max-w-3xl mx-auto px-6">
                <div class="flex flex-col sm:flex-row items-center gap-10 bg-linear-to-br from-slate-900 to-indigo-950 rounded-3xl p-10 text-white">
                    <div class="shrink-0 text-center">
                        <div class="w-28 h-28 rounded-2xl overflow-hidden mx-auto mb-3 ring-4 ring-indigo-500/40">
                            <img v-if="lp.creator.photo || community.owner?.avatar" :src="lp.creator.photo || community.owner.avatar" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full bg-indigo-700 flex items-center justify-center text-3xl font-black text-white">
                                {{ (lp.creator.name || community.owner?.name)?.charAt(0) ?? '?' }}
                            </div>
                        </div>
                        <p class="font-bold text-white text-sm">{{ lp.creator.name || community.owner?.name }}</p>
                        <p class="text-indigo-300 text-xs mt-0.5">Creator</p>
                    </div>
                    <div>
                        <h2 v-html="lp.creator.headline"
                            :contenteditable="inlineMode ? 'true' : 'false'"
                            @focus="inlineMode && onElFocus($event, 'creator.headline')"
                            @blur="inlineMode && saveFromEl($event, 'creator.headline')"
                            @keydown.enter.prevent
                            :class="['text-2xl font-black mb-4 text-white', inlineMode ? editableClass : '']"
                        />
                        <p v-html="lp.creator.bio"
                            :contenteditable="inlineMode ? 'true' : 'false'"
                            @focus="inlineMode && onElFocus($event, 'creator.bio')"
                            @blur="inlineMode && saveFromEl($event, 'creator.bio')"
                            :class="['text-slate-300 leading-relaxed', inlineMode ? editableClass : '']"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- ── VIDEO AFTER CREATOR ── -->
        <section v-if="isVisible('video_creator') && (lp.video_creator?.embed_html || lp.video_creator?.video_url)" class="py-16 bg-white">
            <div class="max-w-3xl mx-auto px-6">
                <SafeHtmlRenderer v-if="lp.video_creator.embed_html" :html="lp.video_creator.embed_html" />
                <div v-else-if="lp.video_creator.video_url" class="aspect-video rounded-2xl overflow-hidden shadow-2xl">
                    <video v-if="lp.video_creator.video_url.includes('.mp4') || lp.video_creator.video_url.includes('.webm') || lp.video_creator.video_url.includes('.mov')"
                        :src="lp.video_creator.video_url" controls class="w-full h-full object-cover" />
                    <iframe v-else :src="normalizeVideoUrl(lp.video_creator.video_url)" class="w-full h-full" frameborder="0" allowfullscreen allow="autoplay; encrypted-media" />
                </div>
            </div>
        </section>

        <!-- ── TESTIMONIALS ── -->
        <!-- Embed testimonials -->
        <section v-if="isVisible('testimonials') && lp.testimonials_type === 'embed' && lp.testimonials_embed_html" class="py-20 bg-gray-50">
            <div class="max-w-5xl mx-auto px-6 text-center">
                <h2 class="text-3xl font-black text-gray-900 text-center mb-12">What members are saying</h2>
                <SafeHtmlRenderer :html="lp.testimonials_embed_html" />
            </div>
        </section>
        <!-- Manual testimonials -->
        <section v-else-if="isVisible('testimonials') && lp.testimonials?.length" class="py-20 bg-gray-50">
            <div class="max-w-5xl mx-auto px-6">
                <h2 class="text-3xl font-black text-gray-900 text-center mb-12">What members are saying</h2>
                <div class="grid sm:grid-cols-3 gap-6">
                    <div v-for="(t, i) in lp.testimonials" :key="i"
                        class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex flex-col gap-4">
                        <div class="flex gap-0.5">
                            <svg v-for="s in 5" :key="s" class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        </div>
                        <p v-html="`&ldquo;${t.quote}&rdquo;`"
                            :contenteditable="inlineMode ? 'true' : 'false'"
                            @focus="inlineMode && onElFocus($event, `testimonials.${i}.quote`)"
                            @blur="inlineMode && saveFromEl($event, `testimonials.${i}.quote`)"
                            :class="['text-gray-600 text-sm leading-relaxed italic flex-1', inlineMode ? editableClass : '']"
                        />
                        <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm text-white"
                                :style="`background-color: hsl(${i * 120}, 60%, 55%)`">
                                {{ t.name.charAt(0) }}
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ t.name }}</p>
                                <p class="text-xs text-gray-400">{{ t.role }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── VIDEO AFTER TESTIMONIALS ── -->
        <section v-if="isVisible('video_testimonials') && (lp.video_testimonials?.embed_html || lp.video_testimonials?.video_url)" class="py-16 bg-white">
            <div class="max-w-3xl mx-auto px-6">
                <SafeHtmlRenderer v-if="lp.video_testimonials.embed_html" :html="lp.video_testimonials.embed_html" />
                <div v-else-if="lp.video_testimonials.video_url" class="aspect-video rounded-2xl overflow-hidden shadow-2xl">
                    <video v-if="lp.video_testimonials.video_url.includes('.mp4') || lp.video_testimonials.video_url.includes('.webm') || lp.video_testimonials.video_url.includes('.mov')"
                        :src="lp.video_testimonials.video_url" controls class="w-full h-full object-cover" />
                    <iframe v-else :src="normalizeVideoUrl(lp.video_testimonials.video_url)" class="w-full h-full" frameborder="0" allowfullscreen allow="autoplay; encrypted-media" />
                </div>
            </div>
        </section>

        <!-- ── OFFER STACK ── -->
        <section v-if="isVisible('offer_stack') && lp.offer_stack" class="py-24 text-white relative"
            :style="{ backgroundColor: lp.offer_stack.bg_color || '#1e1b4b' }">
            <button v-if="isOwner && (inlineMode || showEditPanel)"
                @click="openColorPopover($event, [
                    { label: 'Section Background', path: 'offer_stack.bg_color', fallback: '#1e1b4b' },
                    { label: 'Price Color', path: 'offer_stack.price_color', fallback: '#fbbf24' },
                ])"
                class="absolute top-3 left-3 z-20 w-8 h-8 bg-white/90 hover:bg-white rounded-full shadow-lg flex items-center justify-center transition hover:scale-110"
                title="Edit colors">
                <svg class="w-4 h-4 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
            </button>
            <div class="max-w-2xl mx-auto px-6 text-center">
                <h2 class="text-3xl sm:text-4xl font-black mb-12">{{ lp.offer_stack.headline }}</h2>
                <div class="space-y-3 mb-8 text-left">
                    <div v-for="(item, i) in lp.offer_stack.items" :key="i"
                        class="flex items-center justify-between rounded-xl px-5 py-4 border border-white/10" style="background: rgba(255,255,255,0.1);">
                        <div class="text-left">
                            <p class="font-bold text-white">{{ item.name }}</p>
                            <p v-if="item.description" class="text-sm mt-0.5" style="color: rgba(255,255,255,0.6);">{{ item.description }}</p>
                        </div>
                        <span class="font-semibold shrink-0 ml-4 line-through opacity-60" style="color: rgba(255,255,255,0.7);">{{ item.value }}</span>
                    </div>
                </div>
                <div class="border-t border-white/20 pt-8">
                    <p class="text-sm mb-1" style="color: rgba(255,255,255,0.6);">Total Value: <span class="line-through">{{ lp.offer_stack.total_value }}</span></p>
                    <p class="text-5xl font-black" :style="{ color: lp.offer_stack.price_color || '#fbbf24' }">{{ lp.offer_stack.price }}</p>
                    <p v-if="lp.offer_stack.price_note" class="text-sm mt-2" style="color: rgba(255,255,255,0.6);">{{ lp.offer_stack.price_note }}</p>
                </div>
                <button @click="(inlineMode || showEditPanel) ? openColorPopover($event, [
                        { label: 'Button Background', path: 'offer_stack.btn_bg', fallback: '#fbbf24' },
                        { label: 'Button Text', path: 'offer_stack.btn_text', fallback: '#111827' },
                    ]) : handleCta()"
                    class="mt-8 inline-flex items-center gap-2 px-10 py-4 font-black text-lg rounded-2xl transition-all shadow-xl uppercase tracking-wide hover:scale-105 active:scale-95 hover:brightness-110"
                    :style="{ backgroundColor: lp.offer_stack.btn_bg || '#fbbf24', color: lp.offer_stack.btn_text || '#111827' }">
                    {{ lp.offer_stack.cta_label || 'Get Access Now' }}
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </button>
            </div>
        </section>

        <!-- ── INCLUDED COURSES ── -->
        <section v-if="isVisible('included_courses') && props.courses.length" class="py-24 relative"
            :style="{ backgroundColor: lp?.included_courses_bg_color || '#f9fafb' }">
            <button v-if="isOwner && (inlineMode || showEditPanel)"
                @click="openColorPopover($event, [
                    { label: 'Section Background', path: 'included_courses_bg_color', fallback: '#f9fafb' },
                    { label: 'Badge Background', path: 'included_courses_btn_bg', fallback: '#059669' },
                    { label: 'Badge Text', path: 'included_courses_btn_text', fallback: '#ffffff' },
                ])"
                class="absolute top-3 left-3 z-20 w-8 h-8 bg-white/90 hover:bg-white rounded-full shadow-lg flex items-center justify-center transition hover:scale-110"
                title="Edit colors">
                <svg class="w-4 h-4 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
            </button>
            <div class="max-w-5xl mx-auto px-6">
                <h2 class="text-3xl sm:text-4xl font-black text-gray-900 text-center mb-4">
                    {{ lp?.included_courses_headline || 'Everything included in your membership' }}
                </h2>
                <p class="text-center text-gray-500 mb-12">All courses below are unlocked the moment you join.</p>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="course in props.courses" :key="course.id"
                        class="bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm flex flex-col">
                        <div class="aspect-video bg-indigo-100 overflow-hidden">
                            <img v-if="course.cover_image" :src="course.cover_image" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-4xl">🎓</div>
                        </div>
                        <div class="p-5 flex flex-col flex-1">
                            <h3 class="font-bold text-gray-900 text-base mb-1">{{ course.title }}</h3>
                            <p v-if="course.description" class="text-gray-500 text-sm leading-relaxed line-clamp-2 flex-1">{{ course.description }}</p>
                            <span class="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full self-start"
                                :style="{ backgroundColor: lp?.included_courses_btn_bg || '#059669', color: lp?.included_courses_btn_text || '#ffffff' }">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                Included
                            </span>
                        </div>
                    </div>
                </div>
                <!-- Video embed (configured via Video After Courses section) -->
                <div v-if="isVisible('video_courses') && (lp.video_courses?.embed_html || lp.video_courses?.video_url)" class="mt-12 max-w-3xl mx-auto">
                    <SafeHtmlRenderer v-if="lp.video_courses.embed_html" :html="lp.video_courses.embed_html" />
                    <div v-else-if="lp.video_courses.video_url" class="aspect-video rounded-2xl overflow-hidden shadow-2xl">
                        <video v-if="lp.video_courses.video_url.includes('.mp4') || lp.video_courses.video_url.includes('.webm') || lp.video_courses.video_url.includes('.mov')"
                            :src="lp.video_courses.video_url" controls class="w-full h-full object-cover" />
                        <iframe v-else :src="normalizeVideoUrl(lp.video_courses.video_url)" class="w-full h-full" frameborder="0" allowfullscreen allow="autoplay; encrypted-media" />
                    </div>
                </div>
            </div>
        </section>

        <!-- ── CERTIFICATIONS ── -->
        <section v-if="isVisible('certifications') && props.certifications.length" class="py-24 bg-white">
            <div class="max-w-5xl mx-auto px-6">
                <h2 class="text-3xl sm:text-4xl font-black text-gray-900 text-center mb-4">
                    {{ lp?.certifications_headline || 'Get Certified' }}
                </h2>
                <p class="text-center text-gray-500 mb-12">Pass the exam and earn your certificate.</p>
                <div class="flex flex-wrap justify-center gap-6">
                    <div v-for="cert in props.certifications" :key="cert.id"
                        class="bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm flex flex-col w-full sm:w-[calc(50%-12px)] lg:w-[calc(33.333%-16px)]">
                        <div class="aspect-video bg-amber-50 overflow-hidden">
                            <img v-if="cert.cover_image" :src="cert.cover_image" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-4xl">🏆</div>
                        </div>
                        <div class="p-5 flex flex-col flex-1">
                            <h3 class="font-bold text-gray-900 text-base mb-1">{{ cert.title }}</h3>
                            <p v-if="cert.description" class="text-gray-500 text-sm leading-relaxed line-clamp-2 flex-1">{{ cert.description }}</p>
                            <div class="flex items-center gap-3 mt-4">
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-full">
                                    🏆 {{ cert.cert_title || 'Certificate' }}
                                </span>
                                <span class="text-xs text-gray-400">{{ cert.questions_count }} questions</span>
                                <span v-if="cert.price > 0" class="text-xs font-semibold text-amber-600">₱{{ Number(cert.price).toLocaleString() }}</span>
                                <span v-else class="text-xs font-semibold text-green-600">Free</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── PRICE JUSTIFICATION ── -->
        <section v-if="isVisible('price_justification') && lp.price_justification" class="py-20 relative"
            :style="{ backgroundColor: lp.price_justification.bg_color || '#f9fafb' }">
            <button v-if="isOwner && (inlineMode || showEditPanel)"
                @click="openColorPopover($event, [
                    { label: 'Section Background', path: 'price_justification.bg_color', fallback: '#f9fafb' },
                ])"
                class="absolute top-3 left-3 z-20 w-8 h-8 bg-white/90 hover:bg-white rounded-full shadow-lg flex items-center justify-center transition hover:scale-110"
                title="Edit colors">
                <svg class="w-4 h-4 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
            </button>
            <div class="max-w-2xl mx-auto px-6">
                <h2 class="text-3xl font-black text-gray-900 text-center mb-12">{{ lp.price_justification.headline }}</h2>
                <div class="space-y-4">
                    <div v-for="(opt, i) in lp.price_justification.options" :key="i"
                        :class="['rounded-2xl p-6 border-2 transition', i === lp.price_justification.options.length - 1 ? 'border-indigo-500 bg-indigo-50 shadow-md shadow-indigo-100' : 'border-gray-200 bg-white']">
                        <p class="font-bold text-gray-900 mb-1.5" :class="i === lp.price_justification.options.length - 1 ? 'text-indigo-700' : ''">{{ opt.label }}</p>
                        <p class="text-gray-600 text-sm leading-relaxed">{{ opt.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── GUARANTEE ── -->
        <section v-if="isVisible('guarantee') && lp.guarantee" class="py-16 bg-emerald-50">
            <div class="max-w-2xl mx-auto px-6 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 mb-6 text-3xl">🛡️</div>
                <h2 class="text-2xl sm:text-3xl font-black text-gray-900 mb-4">{{ lp.guarantee.headline }}</h2>
                <p class="text-gray-600 leading-relaxed max-w-lg mx-auto">{{ lp.guarantee.body }}</p>
                <div class="mt-6 inline-flex items-center gap-2 bg-emerald-100 text-emerald-700 font-bold text-sm px-5 py-2.5 rounded-full">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    {{ lp.guarantee.days }}-Day Money-Back Guarantee
                </div>
            </div>
        </section>

        <!-- ── FAQ ── -->
        <section v-if="isVisible('faq') && lp.faq?.length" class="py-24 bg-white">
            <div class="max-w-2xl mx-auto px-6">
                <h2 class="text-3xl font-black text-gray-900 text-center mb-12">Frequently Asked Questions</h2>
                <div class="space-y-3">
                    <div v-for="(item, i) in lp.faq" :key="i"
                        class="border border-gray-200 rounded-2xl overflow-hidden">
                        <button @click="openFaq = openFaq === i ? null : i"
                            class="w-full flex items-center justify-between gap-4 px-6 py-4 text-left hover:bg-gray-50 transition">
                            <span class="font-semibold text-gray-900 text-sm">{{ item.question }}</span>
                            <svg class="w-5 h-5 text-gray-400 shrink-0 transition-transform"
                                :class="openFaq === i ? 'rotate-180' : ''"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div v-if="openFaq === i || inlineMode"
                            v-html="item.answer"
                            :contenteditable="inlineMode ? 'true' : 'false'"
                            @focus="inlineMode && onElFocus($event, `faq.${i}.answer`)"
                            @blur="inlineMode && saveFromEl($event, `faq.${i}.answer`)"
                            :class="['px-6 pb-4 text-sm text-gray-500 leading-relaxed border-t border-gray-100 pt-3', inlineMode ? editableClass : '']"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- ── FINAL CTA ── -->

        <section v-if="isVisible('cta_section')" class="py-24 text-white text-center relative overflow-hidden"
            :style="lp.cta_section?.bg_image ? `background-image: url('${lp.cta_section.bg_image}'); background-size: cover; background-position: center;` : ''">
            <div v-if="!lp.cta_section?.bg_image" class="absolute inset-0 bg-linear-to-br from-slate-900 via-indigo-950 to-slate-900" />
            <div v-else class="absolute inset-0 bg-slate-900/80" />
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-125 h-125 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none" />
            <button v-if="isOwner && (inlineMode || showEditPanel)"
                @click="openColorPopover($event, [
                    { label: 'Button Background', path: 'cta_section.btn_bg', fallback: '#fbbf24' },
                    { label: 'Button Text', path: 'cta_section.btn_text', fallback: '#111827' },
                ])"
                class="absolute top-3 left-3 z-20 w-8 h-8 bg-white/20 hover:bg-white/40 rounded-full shadow-lg flex items-center justify-center transition hover:scale-110 backdrop-blur"
                title="Edit colors">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
            </button>
            <div class="relative z-10 max-w-xl mx-auto px-6">
                <h2
                    v-html="lp.cta_section?.headline ?? `Join ${community.name} Today`"
                    :contenteditable="inlineMode ? 'true' : 'false'"
                    @focus="inlineMode && onElFocus($event, 'cta_section.headline')"
                    @blur="inlineMode && saveFromEl($event, 'cta_section.headline')"
                    @keydown.enter.prevent
                    :class="['text-3xl sm:text-4xl font-black mb-4 text-white', inlineMode ? editableClass : '']"
                />
                <p
                    v-html="lp.cta_section?.subtext ?? 'Start your journey. Cancel anytime.'"
                    :contenteditable="inlineMode ? 'true' : 'false'"
                    @focus="inlineMode && onElFocus($event, 'cta_section.subtext')"
                    @blur="inlineMode && saveFromEl($event, 'cta_section.subtext')"
                    @keydown.enter.prevent
                    :class="['text-slate-400 mb-8 leading-relaxed', inlineMode ? editableClass : '']"
                />
                <button @click="(inlineMode || showEditPanel) ? openColorPopover($event, [
                        { label: 'Button Background', path: 'cta_section.btn_bg', fallback: '#fbbf24' },
                        { label: 'Button Text', path: 'cta_section.btn_text', fallback: '#111827' },
                    ]) : handleCta()"
                    class="inline-flex items-center gap-2 px-10 py-4 font-black text-lg rounded-2xl transition-all shadow-xl uppercase tracking-wide hover:scale-105 active:scale-95 hover:brightness-110"
                    :style="{ backgroundColor: lp.cta_section?.btn_bg || lp.hero?.btn_bg || '#fbbf24', color: lp.cta_section?.btn_text || lp.hero?.btn_text || '#111827' }">
                    {{ lp.cta_section?.cta_label ?? lp.hero.cta_label }}
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </button>
                <p class="mt-4 text-slate-500 text-sm">
                    {{ community.price > 0
                        ? `${community.currency ?? 'PHP'} ${Number(community.price).toLocaleString()}${community.billing_type === 'one_time' ? ' · one-time payment' : '/month · cancel anytime'}`
                        : '100% free · no credit card required' }}
                </p>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-slate-950 text-slate-500 text-center text-xs py-6 px-4">
            <p>
                © {{ new Date().getFullYear() }} {{ community.name }} ·
                <span v-if="!ownerIsPro"> Powered by
                    <a href="/" class="text-slate-400 hover:text-white transition">Curzzo</a>
                </span>
            </p>
        </footer>

    </div>

    <!-- ═══════════════════════════════════════════════════════════
         JOIN MODAL
    ════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showJoinModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" @click.self="showJoinModal = false">
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 translate-y-4 scale-95"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 translate-y-4 scale-95"
                    appear
                >
                    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden">
                        <div class="relative h-44 bg-gray-900 overflow-hidden">
                            <img v-if="community.cover_image" :src="community.cover_image" class="w-full h-full object-cover opacity-80" />
                            <div v-else class="w-full h-full bg-linear-to-br from-indigo-500 to-purple-700" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" />
                            <div class="absolute bottom-4 left-6">
                                <h2 class="text-xl font-black text-white">{{ community.name }}</h2>
                                <p class="text-sm text-white/70 mt-0.5">
                                    {{ community.price > 0
                                        ? `₱${Number(community.price).toLocaleString()}${community.billing_type === 'one_time' ? '' : '/mo'}`
                                        : 'Free' }}
                                    &nbsp;·&nbsp; {{ formatCount(community.members_count) }} members
                                </p>
                            </div>
                            <button @click="showJoinModal = false"
                                class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full bg-black/40 hover:bg-black/60 text-white transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="p-8">
                            <div v-if="invitedBy" class="flex items-center gap-3 mb-6 pb-6 border-b border-gray-100">
                                <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center font-bold text-indigo-600 text-sm shrink-0 overflow-hidden ring-2 ring-indigo-200">
                                    <img v-if="invitedBy.avatar" :src="invitedBy.avatar" class="w-full h-full object-cover" />
                                    <span v-else>{{ invitedBy.name.charAt(0).toUpperCase() }}</span>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400">Invited by</p>
                                    <p class="text-sm font-bold text-gray-900">{{ invitedBy.name }}</p>
                                </div>
                            </div>

                            <h3 class="text-lg font-black text-gray-900 mb-5">Create your account to join</h3>

                            <form @submit.prevent="submitJoin">
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">First name</label>
                                        <input v-model="joinForm.first_name" type="text" required
                                            class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                            :class="joinForm.errors.first_name ? 'border-red-400' : 'border-gray-300'" />
                                        <p v-if="joinForm.errors.first_name" class="mt-1 text-xs text-red-600">{{ joinForm.errors.first_name }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Last name</label>
                                        <input v-model="joinForm.last_name" type="text" required
                                            class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                            :class="joinForm.errors.last_name ? 'border-red-400' : 'border-gray-300'" />
                                        <p v-if="joinForm.errors.last_name" class="mt-1 text-xs text-red-600">{{ joinForm.errors.last_name }}</p>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                                    <input v-model="joinForm.email" type="email" required
                                        class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        :class="joinForm.errors.email ? 'border-red-400' : 'border-gray-300'" />
                                    <p v-if="joinForm.errors.email" class="mt-1 text-xs text-red-600">{{ joinForm.errors.email }}</p>
                                </div>
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone number</label>
                                    <input v-model="joinForm.phone" type="tel" required
                                        class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        :class="joinForm.errors.phone ? 'border-red-400' : 'border-gray-300'"
                                        placeholder="+63 9XX XXX XXXX" />
                                    <p v-if="joinForm.errors.phone" class="mt-1 text-xs text-red-600">{{ joinForm.errors.phone }}</p>
                                </div>
                                <button type="submit" :disabled="joinForm.processing"
                                    class="w-full py-3.5 bg-amber-400 hover:bg-amber-500 text-gray-900 text-sm font-black rounded-2xl tracking-wide uppercase transition disabled:opacity-50 shadow-sm">
                                    {{ joinForm.processing ? 'Redirecting…' : (community.price > 0 ? `Proceed to Payment · ₱${Number(community.price).toLocaleString()}` : 'Join for Free') }}
                                </button>
                                <p class="text-xs text-gray-400 text-center mt-4">
                                    Secure checkout powered by <strong>learn247</strong>. Your login credentials will be sent to your email after payment.
                                </p>
                            </form>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import SafeHtmlRenderer from '@/Components/SafeHtmlRenderer.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { usePixel } from '@/composables/usePixel';
import { useTiktokPixel } from '@/composables/useTiktokPixel';
import { useGoogleAnalytics } from '@/composables/useGoogleAnalytics';

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
const openFaq        = ref(null);
const copied         = ref(false);
const showEditPanel  = ref(false);
const editSaving     = ref(false);
const editError      = ref(null);
const editDraft      = ref({});
const expandedSection = ref(null);
const regenLoading   = ref(null);
const uploadLoading  = ref(null);
const showAddSection  = ref(false);

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

// ── Available sections to add (not yet in _sections) ─────────────────────────
const availableSectionsToAdd = computed(() => {
    if (!editDraft.value._sections) return [];
    const existing = new Set(editDraft.value._sections.map(s => s.type));
    return DEFAULT_SECTION_ORDER.filter(t => !existing.has(t));
});

// ── Open edit panel ───────────────────────────────────────────────────────────
watch(showEditPanel, (open) => {
    if (open && lp.value) {
        editDraft.value = JSON.parse(JSON.stringify(lp.value));
        editError.value = null;
        expandedSection.value = null;

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

// ── Section management ────────────────────────────────────────────────────────
function addSection(type) {
    if (!editDraft.value._sections) return;
    // Initialize data object for sections that need it
    // Insert before cta_section if present, otherwise at end
    const ctaIdx = editDraft.value._sections.findIndex(s => s.type === 'cta_section');
    const newSec = { type, visible: true };
    if (ctaIdx !== -1) {
        editDraft.value._sections.splice(ctaIdx, 0, newSec);
    } else {
        editDraft.value._sections.push(newSec);
    }
    expandedSection.value = type;
}

function removeSection(type) {
    if (!editDraft.value._sections) return;
    editDraft.value._sections = editDraft.value._sections.filter(s => s.type !== type);
    if (expandedSection.value === type) expandedSection.value = null;
}

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

        // Step 2: Upload directly to S3 — must set withCredentials: false to avoid CORS rejection
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
