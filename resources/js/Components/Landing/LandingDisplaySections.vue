<template>
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
                @focus="inlineMode && $emit('elFocus', $event, 'social_proof.trust_line')"
                @blur="inlineMode && $emit('elBlur', $event, 'social_proof.trust_line')"
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
                @focus="inlineMode && $emit('elFocus', $event, 'benefits.headline')"
                @blur="inlineMode && $emit('elBlur', $event, 'benefits.headline')"
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
                            @focus="inlineMode && $emit('elFocus', $event, `benefits.items.${i}.title`)"
                            @blur="inlineMode && $emit('elBlur', $event, `benefits.items.${i}.title`)"
                            @keydown.enter.prevent
                            :class="['font-bold text-gray-900 mb-1.5', inlineMode ? editableClass : '']"
                        />
                        <p v-html="item.body"
                            :contenteditable="inlineMode ? 'true' : 'false'"
                            @focus="inlineMode && $emit('elFocus', $event, `benefits.items.${i}.body`)"
                            @blur="inlineMode && $emit('elBlur', $event, `benefits.items.${i}.body`)"
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
                @focus="inlineMode && $emit('elFocus', $event, 'for_you.headline')"
                @blur="inlineMode && $emit('elBlur', $event, 'for_you.headline')"
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
                        @focus="inlineMode && $emit('elFocus', $event, `for_you.points.${i}`)"
                        @blur="inlineMode && $emit('elBlur', $event, `for_you.points.${i}`)"
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
            <div class="flex flex-col sm:flex-row items-center gap-10 rounded-3xl p-10 text-white"
                 :style="{ backgroundColor: lp.creator.bg_color || '#1e1b4b' }">
                <div class="shrink-0 text-center">
                    <div class="w-28 h-28 rounded-2xl overflow-hidden mx-auto mb-3 ring-4 ring-white/20">
                        <img v-if="lp.creator.photo || community.owner?.avatar" :src="lp.creator.photo || community.owner.avatar" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full bg-white/20 flex items-center justify-center text-3xl font-black text-white">
                            {{ (lp.creator.name || community.owner?.name)?.charAt(0) ?? '?' }}
                        </div>
                    </div>
                    <p v-html="lp.creator.name || community.owner?.name"
                        :contenteditable="inlineMode ? 'true' : 'false'"
                        @focus="inlineMode && $emit('elFocus', $event, 'creator.name')"
                        @blur="inlineMode && $emit('elBlur', $event, 'creator.name')"
                        @keydown.enter.prevent
                        :class="['font-bold text-white text-sm', inlineMode ? editableClass : '']"
                    />
                    <p class="text-white/60 text-xs mt-0.5">Creator</p>
                </div>
                <div>
                    <h2 v-html="lp.creator.headline"
                        :contenteditable="inlineMode ? 'true' : 'false'"
                        @focus="inlineMode && $emit('elFocus', $event, 'creator.headline')"
                        @blur="inlineMode && $emit('elBlur', $event, 'creator.headline')"
                        @keydown.enter.prevent
                        :class="['text-2xl font-black mb-4 text-white', inlineMode ? editableClass : '']"
                    />
                    <p v-html="lp.creator.bio"
                        :contenteditable="inlineMode ? 'true' : 'false'"
                        @focus="inlineMode && $emit('elFocus', $event, 'creator.bio')"
                        @blur="inlineMode && $emit('elBlur', $event, 'creator.bio')"
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
                        @focus="inlineMode && $emit('elFocus', $event, `testimonials.${i}.quote`)"
                        @blur="inlineMode && $emit('elBlur', $event, `testimonials.${i}.quote`)"
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
            @click="$emit('openColorPopover', $event, [
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
            <button @click="(inlineMode || showEditPanel) ? $emit('openColorPopover', $event, [
                    { label: 'Button Background', path: 'offer_stack.btn_bg', fallback: '#fbbf24' },
                    { label: 'Button Text', path: 'offer_stack.btn_text', fallback: '#111827' },
                ]) : $emit('cta')"
                class="mt-8 inline-flex items-center gap-2 px-10 py-4 font-black text-lg rounded-2xl transition-all shadow-xl uppercase tracking-wide hover:scale-105 active:scale-95 hover:brightness-110"
                :style="{ backgroundColor: lp.offer_stack.btn_bg || '#fbbf24', color: lp.offer_stack.btn_text || '#111827' }">
                {{ lp.offer_stack.cta_label || 'Get Access Now' }}
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            </button>
        </div>
    </section>

    <!-- ── INCLUDED COURSES ── -->
    <section v-if="isVisible('included_courses') && courses.length" class="py-24 relative"
        :style="{ backgroundColor: lp?.included_courses_bg_color || '#f9fafb' }">
        <button v-if="isOwner && (inlineMode || showEditPanel)"
            @click="$emit('openColorPopover', $event, [
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
                <div v-for="course in courses" :key="course.id"
                    class="bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm flex flex-col group">
                    <div class="aspect-video bg-indigo-100 overflow-hidden relative"
                        @mouseenter="onCourseHover($event, course)"
                        @mouseleave="onCourseLeave($event, course)"
                        @touchstart.passive="onCourseTouchStart($event, course)"
                        @touchend.passive="onCourseTouchEnd(course)">
                        <video v-if="course.preview_video"
                            :data-lp-course-id="course.id"
                            :src="course.preview_video"
                            class="absolute inset-0 w-full h-full object-cover opacity-0 transition-opacity duration-300 z-[1]"
                            loop playsinline preload="none" />
                        <img v-if="course.cover_image" :src="course.cover_image" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />
                        <div v-else class="w-full h-full flex items-center justify-center text-4xl">🎓</div>
                        <!-- Tap to preview (mobile hint) -->
                        <div v-if="course.preview_video" class="absolute bottom-2 left-2 z-[2] flex items-center gap-1 px-2 py-1 bg-black/50 text-white text-[10px] font-medium rounded-full backdrop-blur-sm pointer-events-none sm:hidden">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            Tap to preview
                        </div>
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
    <section v-if="isVisible('certifications') && certifications.length" class="py-24 bg-white">
        <div class="max-w-5xl mx-auto px-6">
            <h2 class="text-3xl sm:text-4xl font-black text-gray-900 text-center mb-4">
                {{ lp?.certifications_headline || 'Get Certified' }}
            </h2>
            <p class="text-center text-gray-500 mb-12">Pass the exam and earn your certificate.</p>
            <div class="flex flex-wrap justify-center gap-6">
                <div v-for="cert in certifications" :key="cert.id"
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
            @click="$emit('openColorPopover', $event, [
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
                    <button @click="openFaqIdx = openFaqIdx === i ? null : i"
                        class="w-full flex items-center justify-between gap-4 px-6 py-4 text-left hover:bg-gray-50 transition">
                        <span class="font-semibold text-gray-900 text-sm">{{ item.question }}</span>
                        <svg class="w-5 h-5 text-gray-400 shrink-0 transition-transform"
                            :class="openFaqIdx === i ? 'rotate-180' : ''"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div v-if="openFaqIdx === i || inlineMode"
                        v-html="item.answer"
                        :contenteditable="inlineMode ? 'true' : 'false'"
                        @focus="inlineMode && $emit('elFocus', $event, `faq.${i}.answer`)"
                        @blur="inlineMode && $emit('elBlur', $event, `faq.${i}.answer`)"
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
            @click="$emit('openColorPopover', $event, [
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
                @focus="inlineMode && $emit('elFocus', $event, 'cta_section.headline')"
                @blur="inlineMode && $emit('elBlur', $event, 'cta_section.headline')"
                @keydown.enter.prevent
                :class="['text-3xl sm:text-4xl font-black mb-4 text-white', inlineMode ? editableClass : '']"
            />
            <p
                v-html="lp.cta_section?.subtext ?? 'Start your journey. Cancel anytime.'"
                :contenteditable="inlineMode ? 'true' : 'false'"
                @focus="inlineMode && $emit('elFocus', $event, 'cta_section.subtext')"
                @blur="inlineMode && $emit('elBlur', $event, 'cta_section.subtext')"
                @keydown.enter.prevent
                :class="['text-slate-400 mb-8 leading-relaxed', inlineMode ? editableClass : '']"
            />
            <button @click="(inlineMode || showEditPanel) ? $emit('openColorPopover', $event, [
                    { label: 'Button Background', path: 'cta_section.btn_bg', fallback: '#fbbf24' },
                    { label: 'Button Text', path: 'cta_section.btn_text', fallback: '#111827' },
                ]) : $emit('cta')"
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
</template>

<script setup>
import { ref } from 'vue';
import SafeHtmlRenderer from '@/Components/SafeHtmlRenderer.vue';

defineProps({
    lp: { type: Object, required: true },
    community: { type: Object, required: true },
    courses: { type: Array, default: () => [] },
    certifications: { type: Array, default: () => [] },
    isOwner: { type: Boolean, default: false },
    ownerIsPro: { type: Boolean, default: false },
    inlineMode: { type: Boolean, default: false },
    showEditPanel: { type: Boolean, default: false },
    editableClass: { type: String, default: '' },
    normalizeVideoUrl: { type: Function, required: true },
    isVisible: { type: Function, required: true },
});

defineEmits(['openColorPopover', 'elFocus', 'elBlur', 'cta']);

const openFaqIdx = ref(null);

function formatCount(n) {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(n ?? 0);
}

// ── Preview video hover/tap-to-play ──────────────────────────────────────────
let lpHoverTimer = null;
let lpTouchTimer = null;

function getLpVideoEl(container, id) {
    return container.querySelector(`video[data-lp-course-id="${id}"]`);
}

function playLpVideo(video, course) {
    const wantSound = course?.preview_video_sound;
    video.muted = !wantSound;
    video.currentTime = 0;
    video.play().then(() => { video.style.opacity = '1'; }).catch(() => {
        video.muted = true;
        video.play().then(() => { video.style.opacity = '1'; }).catch(() => {});
    });
}

function stopLpVideo(video) {
    video.style.opacity = '0';
    video.pause();
    video.muted = true;
    video.currentTime = 0;
}

function onCourseHover(e, course) {
    if (!course.preview_video) return;
    const container = e.currentTarget;
    lpHoverTimer = setTimeout(() => {
        const video = getLpVideoEl(container, course.id);
        if (video) playLpVideo(video, course);
    }, 500);
}

function onCourseLeave(e, course) {
    clearTimeout(lpHoverTimer);
    if (!course.preview_video) return;
    const video = getLpVideoEl(e.currentTarget, course.id);
    if (video) stopLpVideo(video);
}

function onCourseTouchStart(e, course) {
    if (!course.preview_video) return;
    const container = e.currentTarget;
    lpTouchTimer = setTimeout(() => {
        const video = getLpVideoEl(container, course.id);
        if (video) playLpVideo(video, course);
    }, 400);
}

function onCourseTouchEnd(course) {
    clearTimeout(lpTouchTimer);
    if (!course.preview_video) return;
    document.querySelectorAll(`video[data-lp-course-id="${course.id}"]`).forEach(v => stopLpVideo(v));
}
</script>
