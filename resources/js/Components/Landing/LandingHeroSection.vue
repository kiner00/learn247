<template>
    <section class="relative overflow-hidden bg-linear-to-br from-slate-900 via-indigo-950 to-slate-900 text-white">
        <button v-if="isOwner && (inlineMode || showEditPanel)"
            @click="$emit('openColorPopover', $event, [
                { label: 'Button Background', path: 'hero.btn_bg', fallback: '#fbbf24' },
                { label: 'Button Text', path: 'hero.btn_text', fallback: '#111827' },
            ])"
            class="absolute top-3 left-3 z-20 w-8 h-8 bg-white/20 hover:bg-white/40 rounded-full shadow-lg flex items-center justify-center transition hover:scale-110 backdrop-blur"
            title="Edit colors">
            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
        </button>
        <!-- Background image -->
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
                @focus="inlineMode && $emit('elFocus', $event, 'hero.pre_headline')"
                @blur="inlineMode && $emit('elBlur', $event, 'hero.pre_headline')"
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
                @focus="inlineMode && $emit('elFocus', $event, 'hero.headline')"
                @blur="inlineMode && $emit('elBlur', $event, 'hero.headline')"
                @keydown.enter.prevent
                :class="[lp.hero.headline_font_size ? '' : 'text-4xl sm:text-5xl lg:text-6xl', 'font-black leading-tight mb-6 text-white', inlineMode ? editableClass : '']"
                :style="lp.hero.headline_font_size ? { fontSize: lp.hero.headline_font_size + 'px' } : {}"
            />
            <p
                :key="renderKey + '_hero_sub'"
                v-html="lp.hero.subheadline"
                :contenteditable="inlineMode ? 'true' : 'false'"
                @focus="inlineMode && $emit('elFocus', $event, 'hero.subheadline')"
                @blur="inlineMode && $emit('elBlur', $event, 'hero.subheadline')"
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

            <button @click="(inlineMode || showEditPanel) ? $emit('openColorPopover', $event, [
                    { label: 'Button Background', path: 'hero.btn_bg', fallback: '#fbbf24' },
                    { label: 'Button Text', path: 'hero.btn_text', fallback: '#111827' },
                ]) : $emit('cta')"
                class="inline-flex items-center gap-2 px-10 py-4 font-black text-lg rounded-2xl transition-all shadow-xl uppercase tracking-wide hover:scale-105 active:scale-95 hover:brightness-110"
                :style="{ backgroundColor: lp.hero?.btn_bg || '#fbbf24', color: lp.hero?.btn_text || '#111827' }">
                {{ lp.hero.cta_label }}
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                </svg>
            </button>

            <p v-if="lp.hero?.price_note !== ''" class="mt-4 text-slate-400 text-sm">
                {{ lp.hero?.price_note
                    || (community.price > 0
                        ? `${community.currency ?? 'PHP'} ${Number(community.price).toLocaleString()}${community.billing_type === 'one_time' ? ' one-time' : '/month'}`
                        : 'Free to join') }}
            </p>
        </div>
    </section>
</template>

<script setup>
import SafeHtmlRenderer from '@/Components/SafeHtmlRenderer.vue';

defineProps({
    lp: { type: Object, required: true },
    community: { type: Object, required: true },
    invitedBy: { type: Object, default: null },
    isOwner: { type: Boolean, default: false },
    inlineMode: { type: Boolean, default: false },
    showEditPanel: { type: Boolean, default: false },
    renderKey: { type: Number, default: 0 },
    editableClass: { type: String, default: '' },
    normalizeVideoUrl: { type: Function, required: true },
});

defineEmits(['openColorPopover', 'elFocus', 'elBlur', 'cta']);
</script>
