<template>
    <Teleport to="body">
        <Transition enter-from-class="translate-x-full" enter-active-class="transition-transform duration-300" leave-to-class="translate-x-full" leave-active-class="transition-transform duration-300">
            <div v-if="show && lp" class="fixed inset-0 z-60 flex justify-end">
                <div class="absolute inset-0 bg-black/40" @click="$emit('close')" />
                <div class="relative w-full max-w-md bg-white h-full flex flex-col shadow-2xl overflow-hidden">

                    <!-- Panel header -->
                    <div class="flex items-center justify-between px-5 py-4 border-b bg-gray-50 shrink-0">
                        <h2 class="font-bold text-gray-900 text-base">Edit Sections</h2>
                        <div class="flex items-center gap-2">
                            <button @click="$emit('save')" :disabled="editSaving"
                                class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition disabled:opacity-50">
                                {{ editSaving ? 'Saving…' : 'Save Changes' }}
                            </button>
                            <button @click="$emit('close')" class="text-gray-400 hover:text-gray-700 transition">
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

                                <!-- Move up/down buttons -->
                                <div class="flex flex-col gap-0.5 shrink-0">
                                    <button v-if="idx > 0 && !SECTION_DEFS[sec.type]?.required"
                                        @click.stop="moveSection(idx, -1)" title="Move up"
                                        class="w-5 h-3.5 flex items-center justify-center text-gray-300 hover:text-gray-600 transition">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                    </button>
                                    <button v-if="idx < editDraft._sections.length - 1 && !SECTION_DEFS[sec.type]?.required"
                                        @click.stop="moveSection(idx, 1)" title="Move down"
                                        class="w-5 h-3.5 flex items-center justify-center text-gray-300 hover:text-gray-600 transition">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </div>

                                <!-- Section name (click to expand) -->
                                <button @click="expandedSection = expandedSection === sec.type ? null : sec.type" class="flex-1 text-left">
                                    <span class="text-sm font-medium text-gray-800">
                                        {{ getSectionDef(sec.type)?.icon ?? '📄' }} {{ getSectionDef(sec.type)?.label ?? sec.type }}
                                    </span>
                                    <span v-if="!sec.visible" class="ml-2 text-xs text-gray-400 font-normal">hidden</span>
                                </button>

                                <!-- AI Regen button (not for custom sections) -->
                                <button v-if="!sec.type.startsWith('custom_')"
                                    @click.stop="$emit('regenSection', sec.type)"
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
                                        <label class="field-label">Price Note <span class="text-gray-400 font-normal">(below button — leave empty to hide)</span></label>
                                        <input v-model="editDraft.hero.price_note" type="text" placeholder="e.g. PHP 999/month · cancel anytime" class="field-input" />
                                    </div>
                                    <div>
                                        <label class="field-label">Video Type</label>
                                        <div class="flex items-center gap-1 p-0.5 bg-gray-100 rounded-lg w-fit">
                                            <button type="button" @click="editDraft.hero.video_type = 'vsl'" class="px-3 py-1 text-xs font-medium rounded-md transition-colors" :class="(!editDraft.hero.video_type || editDraft.hero.video_type === 'vsl') ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                                VSL Video URL
                                            </button>
                                            <button type="button" @click="editDraft.hero.video_type = 'upload'" class="px-3 py-1 text-xs font-medium rounded-md transition-colors" :class="editDraft.hero.video_type === 'upload' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                                Upload Video
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
                                    <div v-else-if="editDraft.hero.video_type === 'upload'">
                                        <div v-if="editDraft.hero.video_url" class="mb-2">
                                            <p class="text-xs text-green-600 font-medium mb-1">Video uploaded</p>
                                            <div class="flex items-center gap-2">
                                                <input :value="editDraft.hero.video_url" type="text" readonly class="field-input flex-1 text-xs text-gray-400" />
                                                <button type="button" @click="editDraft.hero.video_url = ''" class="text-xs text-red-500 hover:text-red-700 font-medium shrink-0">Remove</button>
                                            </div>
                                        </div>
                                        <div v-if="canUploadSectionVideo">
                                            <label class="flex items-center justify-center gap-2 px-3 py-2.5 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/50 transition-colors">
                                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                                </svg>
                                                <span class="text-xs text-gray-500">
                                                    {{ sectionVideoUploading === 'hero' ? `Uploading... ${sectionVideoProgress}%` : 'Choose video file (MP4, WebM, MOV)' }}
                                                </span>
                                                <input
                                                    type="file"
                                                    accept="video/mp4,video/webm,video/quicktime"
                                                    class="hidden"
                                                    :disabled="sectionVideoUploading === 'hero'"
                                                    @change="$emit('sectionVideoUpload', 'hero', $event)"
                                                />
                                            </label>
                                            <p v-if="sectionVideoError" class="text-xs text-red-500 mt-1">{{ sectionVideoError }}</p>
                                        </div>
                                        <p v-else class="text-xs text-gray-400 italic">Video upload requires a Pro plan.</p>
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
                                            <input type="file" accept="image/*" class="sr-only" @change="$emit('uploadImage', 'hero', $event)" />
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
                                                    <input type="file" accept="image/*" class="sr-only" @change="$emit('uploadImage', 'creator', $event)" />
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
                                        <div>
                                            <label class="field-label">Card Background Color</label>
                                            <div class="flex items-center gap-2">
                                                <input type="color" v-model="editDraft.creator.bg_color" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                <input v-model="editDraft.creator.bg_color" type="text" placeholder="#1e1b4b" class="field-input flex-1 text-xs" />
                                            </div>
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
                                                    {{ sectionVideoUploading === sec.type ? `Uploading... ${sectionVideoProgress}%` : 'Choose video file (MP4, WebM, MOV — max 5GB)' }}
                                                </span>
                                                <input
                                                    type="file"
                                                    accept="video/mp4,video/webm,video/quicktime"
                                                    class="hidden"
                                                    :disabled="sectionVideoUploading === sec.type"
                                                    @change="$emit('sectionVideoUpload', sec.type, $event)"
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
                                    <div v-if="!allCourses.length" class="text-xs text-gray-500 italic">
                                        No published courses yet. Add courses in the Classroom.
                                    </div>
                                    <template v-else>
                                        <div>
                                            <label class="field-label">Section Headline</label>
                                            <input v-model="editDraft.included_courses_headline" type="text" placeholder="Everything included in your membership" class="field-input" />
                                        </div>
                                        <div>
                                            <label class="field-label">Section Subtitle</label>
                                            <input v-model="editDraft.included_courses_subtitle" type="text" placeholder="All courses below are unlocked the moment you join." class="field-input" />
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
                                            <button type="button" @click="$emit('toggleAllCourses')" class="text-xs text-indigo-600 hover:underline">
                                                {{ allCoursesSelected ? 'Deselect All' : 'Select All' }}
                                            </button>
                                        </div>
                                        <div v-for="c in allCourses" :key="c.id"
                                            class="flex items-center gap-3 bg-white rounded-xl p-3 border border-gray-200 cursor-pointer hover:border-indigo-300 transition-colors"
                                            @click="$emit('toggleCourseSelection', c.id)">
                                            <input type="checkbox"
                                                :checked="(editDraft.included_courses_selected ?? []).includes(c.id)"
                                                @click.stop="$emit('toggleCourseSelection', c.id)"
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
                                    <div v-if="!certifications.length" class="text-xs text-gray-500 italic">
                                        No certification exams yet. Create one in the Certifications tab.
                                    </div>
                                    <template v-else>
                                        <div>
                                            <label class="field-label">Section Headline</label>
                                            <input v-model="editDraft.certifications_headline" type="text" placeholder="Get Certified" class="field-input" />
                                        </div>
                                        <p class="text-xs text-gray-400">Certifications are pulled automatically from your community exams.</p>
                                        <div v-for="c in certifications" :key="c.id" class="flex items-center gap-3 bg-white rounded-xl p-3 border border-gray-200">
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

                                <!-- CURZZOS (AI BOTS) -->
                                <template v-if="sec.type === 'curzzos'">
                                    <div v-if="!curzzos.length" class="text-xs text-gray-500 italic">
                                        No AI bots yet. Create one in the Curzzos settings.
                                    </div>
                                    <template v-else>
                                        <div>
                                            <label class="field-label">Section Headline</label>
                                            <input v-model="editDraft.curzzos_headline" type="text" placeholder="AI-Powered Bots" class="field-input" />
                                        </div>
                                        <p class="text-xs text-gray-400">Bots are pulled automatically from your community's Curzzos.</p>
                                        <div v-for="bot in curzzos" :key="bot.id" class="flex items-center gap-3 bg-white rounded-xl p-3 border border-gray-200">
                                            <div class="w-10 h-10 rounded-lg overflow-hidden shrink-0 bg-indigo-100 flex items-center justify-center text-lg">
                                                <img v-if="bot.avatar" :src="bot.avatar" class="w-full h-full object-cover" />
                                                <span v-else>🤖</span>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-800 truncate">{{ bot.name }}</p>
                                                <p class="text-xs text-gray-400">{{ bot.access_type || 'free' }}</p>
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

                                <!-- CAROUSEL SECTION (custom_* with kind=carousel) -->
                                <template v-if="sec.type.startsWith('custom_') && getCustomData(sec.type).kind === 'carousel'">
                                    <div class="space-y-3">
                                        <div>
                                            <label class="field-label">Title</label>
                                            <input v-model="getCustomData(sec.type).title" type="text" placeholder="Section heading" class="field-input" />
                                        </div>
                                        <div>
                                            <label class="field-label">Subtitle</label>
                                            <input v-model="getCustomData(sec.type).subtitle" type="text" placeholder="Short supporting line" class="field-input" />
                                        </div>
                                        <div class="pt-2 border-t border-gray-200 mt-1">
                                            <div class="flex items-center justify-between mb-2">
                                                <label class="field-label !mb-0">Slides</label>
                                                <span class="text-[10px] text-gray-400">{{ (getCustomData(sec.type).slides || []).length }} slide(s)</span>
                                            </div>
                                            <div class="space-y-3">
                                                <div v-for="(slide, si) in (getCustomData(sec.type).slides || [])" :key="si"
                                                    class="bg-white rounded-xl p-3 border border-gray-200 space-y-2">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-[11px] font-semibold text-gray-500">Slide {{ si + 1 }}</span>
                                                        <button @click="removeCarouselSlide(sec.type, si)"
                                                            class="text-[11px] text-red-400 hover:text-red-600">Remove</button>
                                                    </div>
                                                    <img v-if="slide.image_url" :src="slide.image_url" class="w-full h-28 object-cover rounded-lg border border-gray-100" />
                                                    <input v-model="slide.image_url" type="url" placeholder="https://... or upload below" class="field-input text-xs" />
                                                    <label class="flex items-center gap-2 cursor-pointer text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                                        {{ uploadLoading === `${sec.type}:${si}` ? 'Uploading...' : 'Upload image' }}
                                                        <input type="file" accept="image/*" class="sr-only" @change="$emit('uploadCarouselSlide', sec.type, si, $event)" />
                                                    </label>
                                                    <input v-model="slide.alt" type="text" placeholder="Alt text (optional)" class="field-input text-xs" />
                                                </div>
                                            </div>
                                            <button @click="addCarouselSlide(sec.type)" class="mt-2 text-xs text-indigo-600 font-medium hover:underline">+ Add slide</button>
                                        </div>
                                        <div class="pt-2 border-t border-gray-200 mt-1">
                                            <label class="field-label">CTA Button Label <span class="text-gray-400 font-normal">(leave empty to hide)</span></label>
                                            <input v-model="getCustomData(sec.type).cta_label" type="text" placeholder="e.g. Learn more" class="field-input" />
                                        </div>
                                        <div>
                                            <label class="field-label">CTA Button URL</label>
                                            <input v-model="getCustomData(sec.type).cta_url" type="url" placeholder="https://..." class="field-input" />
                                        </div>
                                        <div class="pt-2 border-t border-gray-200 mt-1">
                                            <label class="field-label mb-2">Text Colors</label>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="text-[10px] text-gray-400 font-medium">Title</label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="color" v-model="getCustomData(sec.type).text_color" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                        <input v-model="getCustomData(sec.type).text_color" type="text" placeholder="#111827" class="field-input flex-1 text-xs" />
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="text-[10px] text-gray-400 font-medium">Subtitle</label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="color" v-model="getCustomData(sec.type).subtitle_color" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                        <input v-model="getCustomData(sec.type).subtitle_color" type="text" placeholder="#4b5563" class="field-input flex-1 text-xs" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="pt-2 border-t border-gray-200 mt-1">
                                            <label class="field-label mb-2">Button Colors</label>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="text-[10px] text-gray-400 font-medium">Button BG</label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="color" v-model="getCustomData(sec.type).btn_bg" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                        <input v-model="getCustomData(sec.type).btn_bg" type="text" placeholder="#fbbf24" class="field-input flex-1 text-xs" />
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="text-[10px] text-gray-400 font-medium">Button Text</label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="color" v-model="getCustomData(sec.type).btn_text" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                        <input v-model="getCustomData(sec.type).btn_text" type="text" placeholder="#111827" class="field-input flex-1 text-xs" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="pt-2 border-t border-gray-200 mt-1">
                                            <label class="field-label">Background Color</label>
                                            <div class="flex items-center gap-2">
                                                <input type="color" v-model="getCustomData(sec.type).bg_color" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                <input v-model="getCustomData(sec.type).bg_color" type="text" placeholder="#ffffff" class="field-input flex-1 text-xs" />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="field-label">Background Image <span class="text-gray-400 font-normal">(overrides bg color)</span></label>
                                            <input v-model="getCustomData(sec.type).bg_image" type="url" placeholder="https://..." class="field-input" />
                                            <label class="mt-2 flex items-center gap-2 cursor-pointer text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                                {{ uploadLoading === `${sec.type}:bg` ? 'Uploading...' : 'Upload image' }}
                                                <input type="file" accept="image/*" class="sr-only" @change="$emit('uploadCarouselBg', sec.type, $event)" />
                                            </label>
                                        </div>
                                    </div>
                                </template>

                                <!-- CUSTOM SECTION -->
                                <template v-else-if="sec.type.startsWith('custom_')">
                                    <div class="space-y-3">
                                        <div>
                                            <label class="field-label">Section Title</label>
                                            <input v-model="getCustomData(sec.type).title" type="text" placeholder="Your section heading" class="field-input" />
                                        </div>
                                        <div>
                                            <label class="field-label">Text Content</label>
                                            <textarea v-model="getCustomData(sec.type).text" rows="4" placeholder="Add your text content here..." class="field-input resize-none" />
                                        </div>
                                        <div>
                                            <label class="field-label">Image</label>
                                            <div class="flex items-center gap-2">
                                                <input v-model="getCustomData(sec.type).image_url" type="url" placeholder="https://... or upload below" class="field-input flex-1" />
                                            </div>
                                            <label class="mt-2 flex items-center gap-2 cursor-pointer text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                                {{ uploadLoading === sec.type ? 'Uploading...' : 'Upload image' }}
                                                <input type="file" accept="image/*" class="sr-only" @change="$emit('uploadCustomImage', sec.type, $event)" />
                                            </label>
                                        </div>
                                        <div>
                                            <label class="field-label">Video URL <span class="text-gray-400 font-normal">(YouTube, Vimeo, or direct link)</span></label>
                                            <input v-model="getCustomData(sec.type).video_url" type="url" placeholder="https://youtube.com/watch?v=..." class="field-input" />
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
                                                    {{ sectionVideoUploading === sec.type ? `Uploading... ${sectionVideoProgress}%` : 'Choose video file (MP4, WebM, MOV)' }}
                                                </span>
                                                <input type="file" accept="video/mp4,video/webm,video/quicktime" class="hidden"
                                                    :disabled="sectionVideoUploading === sec.type"
                                                    @change="$emit('customVideoUpload', sec.type, $event)" />
                                            </label>
                                            <p v-if="sectionVideoError && sectionVideoUploading === null" class="text-xs text-red-500 mt-1">{{ sectionVideoError }}</p>
                                        </div>
                                        <div>
                                            <label class="field-label">Embed Code <span class="text-gray-400 font-normal">(iframe / script)</span></label>
                                            <textarea v-model="getCustomData(sec.type).embed_html" rows="4" placeholder='<iframe src="..." ...></iframe>' class="field-input font-mono resize-none" />
                                        </div>
                                        <div class="pt-2 border-t border-gray-200 mt-1">
                                            <label class="field-label mb-2">Colors</label>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="text-[10px] text-gray-400 font-medium">Background</label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="color" v-model="getCustomData(sec.type).bg_color" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                        <input v-model="getCustomData(sec.type).bg_color" type="text" placeholder="#ffffff" class="field-input flex-1 text-xs" />
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="text-[10px] text-gray-400 font-medium">Text Color</label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="color" v-model="getCustomData(sec.type).text_color" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                                                        <input v-model="getCustomData(sec.type).text_color" type="text" placeholder="#111827" class="field-input flex-1 text-xs" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
                                        <div>
                                            <label class="field-label">Price Note <span class="text-gray-400 font-normal">(below button — leave empty to hide)</span></label>
                                            <input v-model="editDraft.cta_section.price_note" type="text" placeholder="e.g. PHP 999/month · cancel anytime" class="field-input" />
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
                                                <input type="file" accept="image/*" class="sr-only" @change="$emit('uploadImage', 'cta_section', $event)" />
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
                        <div v-if="showAddSection" class="px-5 pb-4 space-y-3">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="type in availableSectionsToAdd" :key="type"
                                    @click="addSection(type)"
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-gray-100 hover:bg-indigo-50 hover:text-indigo-700 text-gray-600 rounded-lg border border-gray-200 hover:border-indigo-200 transition">
                                    <span>{{ SECTION_DEFS[type]?.icon }}</span>
                                    {{ SECTION_DEFS[type]?.label }}
                                </button>
                                <button
                                    @click="addCustomSection"
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg border border-emerald-200 hover:border-emerald-300 transition">
                                    <span>🧩</span>
                                    Custom Section
                                </button>
                                <button
                                    @click="addCarouselSection"
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-sky-50 hover:bg-sky-100 text-sky-700 rounded-lg border border-sky-200 hover:border-sky-300 transition">
                                    <span>🎠</span>
                                    Carousel
                                </button>
                            </div>
                            <p v-if="availableSectionsToAdd.length === 0" class="text-xs text-gray-400">All standard sections are added. You can still add unlimited custom sections.</p>
                        </div>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    lp: { type: Object, default: null },
    editDraft: { type: Object, required: true },
    editSaving: { type: Boolean, default: false },
    editError: { type: [String, null], default: null },
    regenLoading: { type: [String, null], default: null },
    uploadLoading: { type: [String, null], default: null },
    sectionVideoUploading: { type: [String, null], default: null },
    sectionVideoProgress: { type: Number, default: 0 },
    sectionVideoError: { type: String, default: '' },
    canUploadSectionVideo: { type: Boolean, default: false },
    community: { type: Object, required: true },
    allCourses: { type: Array, default: () => [] },
    certifications: { type: Array, default: () => [] },
    curzzos: { type: Array, default: () => [] },
    allCoursesSelected: { type: Boolean, default: false },
    SECTION_DEFS: { type: Object, required: true },
    DEFAULT_SECTION_ORDER: { type: Array, required: true },
    getSectionDef: { type: Function, required: true },
});

const emit = defineEmits([
    'close', 'save', 'regenSection', 'uploadImage', 'uploadCustomImage',
    'uploadCarouselSlide', 'uploadCarouselBg',
    'sectionVideoUpload', 'customVideoUpload',
    'toggleCourseSelection', 'toggleAllCourses',
]);

const expandedSection = ref(null);
const showAddSection = ref(false);

const availableSectionsToAdd = computed(() => {
    if (!props.editDraft._sections) return [];
    const existing = new Set(props.editDraft._sections.map(s => s.type));
    return props.DEFAULT_SECTION_ORDER.filter(t => !existing.has(t));
});

function addSection(type) {
    if (!props.editDraft._sections) return;
    const ctaIdx = props.editDraft._sections.findIndex(s => s.type === 'cta_section');
    const newSec = { type, visible: true };
    if (ctaIdx !== -1) {
        props.editDraft._sections.splice(ctaIdx, 0, newSec);
    } else {
        props.editDraft._sections.push(newSec);
    }
    expandedSection.value = type;
}

function removeSection(type) {
    if (!props.editDraft._sections) return;
    if (type.startsWith('custom_')) {
        // Custom sections: fully remove from array and clean up data
        props.editDraft._sections = props.editDraft._sections.filter(s => s.type !== type);
        if (props.editDraft.custom_sections) {
            delete props.editDraft.custom_sections[type];
        }
    } else {
        // Built-in sections: hide instead of removing so they stay in _sections
        const sec = props.editDraft._sections.find(s => s.type === type);
        if (sec) sec.visible = false;
    }
    if (expandedSection.value === type) expandedSection.value = null;
}

function addCustomSection() {
    if (!props.editDraft._sections) return;
    const id = 'custom_' + Math.random().toString(36).slice(2, 10);
    const ctaIdx = props.editDraft._sections.findIndex(s => s.type === 'cta_section');
    const newSec = { type: id, visible: true };
    if (ctaIdx !== -1) {
        props.editDraft._sections.splice(ctaIdx, 0, newSec);
    } else {
        props.editDraft._sections.push(newSec);
    }
    // Initialize custom section data
    if (!props.editDraft.custom_sections) props.editDraft.custom_sections = {};
    props.editDraft.custom_sections[id] = { title: '', text: '', image_url: '', video_url: '', embed_html: '', bg_color: '', text_color: '' };
    expandedSection.value = id;
}

function getCustomData(sectionId) {
    if (!props.editDraft.custom_sections) props.editDraft.custom_sections = {};
    if (!props.editDraft.custom_sections[sectionId]) {
        props.editDraft.custom_sections[sectionId] = { title: '', text: '', image_url: '', video_url: '', embed_html: '', bg_color: '', text_color: '' };
    }
    return props.editDraft.custom_sections[sectionId];
}

function addCarouselSection() {
    if (!props.editDraft._sections) return;
    const id = 'custom_' + Math.random().toString(36).slice(2, 10);
    const ctaIdx = props.editDraft._sections.findIndex(s => s.type === 'cta_section');
    const newSec = { type: id, visible: true };
    if (ctaIdx !== -1) {
        props.editDraft._sections.splice(ctaIdx, 0, newSec);
    } else {
        props.editDraft._sections.push(newSec);
    }
    if (!props.editDraft.custom_sections) props.editDraft.custom_sections = {};
    props.editDraft.custom_sections[id] = {
        kind: 'carousel',
        title: 'Featured',
        subtitle: '',
        slides: [
            { image_url: '', alt: '' },
            { image_url: '', alt: '' },
            { image_url: '', alt: '' },
        ],
        cta_label: '',
        cta_url: '',
        text_color: '#111827',
        subtitle_color: '#4b5563',
        btn_bg: '#fbbf24',
        btn_text: '#111827',
        bg_color: '#ffffff',
        bg_image: '',
    };
    expandedSection.value = id;
}

function addCarouselSlide(sectionId) {
    const data = getCustomData(sectionId);
    if (!Array.isArray(data.slides)) data.slides = [];
    data.slides.push({ image_url: '', alt: '' });
}

function removeCarouselSlide(sectionId, idx) {
    const data = getCustomData(sectionId);
    if (Array.isArray(data.slides)) data.slides.splice(idx, 1);
}

function moveSection(idx, direction) {
    const sections = props.editDraft._sections;
    const newIdx = idx + direction;
    if (newIdx < 0 || newIdx >= sections.length) return;
    // Don't allow moving past required sections (hero stays first, cta stays last)
    const target = sections[newIdx];
    if (props.SECTION_DEFS[target.type]?.required) return;
    // Swap
    [sections[idx], sections[newIdx]] = [sections[newIdx], sections[idx]];
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
