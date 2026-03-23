<template>
    <Head :title="`${community.name} · Landing Page`" />

    <!-- Pixel trackers (silent) -->

    <!-- Owner toolbar -->
    <div v-if="isOwner" class="fixed top-0 inset-x-0 z-50 bg-gray-900/95 backdrop-blur text-white text-sm flex items-center justify-between px-4 py-2.5 gap-3">
        <span class="font-semibold text-white/80 truncate">Landing Page Preview</span>
        <div class="flex items-center gap-2 shrink-0">
            <a :href="`/communities/${community.slug}`" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 transition text-xs font-medium">
                ← Back to Community
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
                {{ generating ? 'Generating…' : (lp ? 'Regenerate with AI' : 'Generate with AI') }}
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
            <button @click="showJoinModal = true"
                class="px-8 py-3 bg-amber-400 hover:bg-amber-500 text-gray-900 font-black rounded-2xl transition uppercase tracking-wide">
                {{ community.price > 0 ? `Join · ₱${Number(community.price).toLocaleString()}` : 'Join for Free' }}
            </button>
        </div>
    </div>

    <!-- Full landing page -->
    <div v-else :class="isOwner ? 'pt-12' : ''" class="bg-white font-sans antialiased">

        <!-- ── HERO ── -->
        <section class="relative overflow-hidden bg-linear-to-br from-slate-900 via-indigo-950 to-slate-900 text-white">
            <!-- Background cover -->
            <div v-if="community.cover_image" class="absolute inset-0 opacity-20">
                <img :src="community.cover_image" class="w-full h-full object-cover" />
                <div class="absolute inset-0 bg-gradient-to-b from-slate-900/60 via-transparent to-slate-900" />
            </div>
            <!-- Glow -->
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
                <!-- Badge -->
                <div class="inline-flex items-center gap-2 bg-indigo-500/30 border border-indigo-400/40 text-indigo-200 text-xs font-semibold px-4 py-1.5 rounded-full mb-8 uppercase tracking-widest">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 inline-block"></span>
                    {{ community.category || 'Community' }}
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black leading-tight mb-6 text-white">
                    {{ lp.hero.headline }}
                </h1>
                <p class="text-lg sm:text-xl text-slate-300 mb-10 max-w-xl mx-auto leading-relaxed">
                    {{ lp.hero.subheadline }}
                </p>

                <button @click="showJoinModal = true"
                    class="inline-flex items-center gap-2 px-10 py-4 bg-amber-400 hover:bg-amber-500 text-gray-900 font-black text-lg rounded-2xl transition-all shadow-xl shadow-amber-500/30 uppercase tracking-wide hover:scale-105 active:scale-95">
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
        <section v-if="lp.social_proof" class="bg-indigo-600 text-white py-5">
            <div class="max-w-4xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-center gap-4 text-center sm:text-left">
                <div class="flex items-center gap-3">
                    <!-- Member avatars stack -->
                    <div class="flex -space-x-2">
                        <div v-for="i in 4" :key="i"
                            class="w-8 h-8 rounded-full bg-indigo-400 border-2 border-indigo-600 overflow-hidden flex items-center justify-center text-xs font-bold text-white"
                            :style="`background-color: hsl(${i * 60}, 70%, 60%)`">
                            {{ String.fromCharCode(64 + i) }}
                        </div>
                    </div>
                    <div>
                        <span class="font-black text-xl">{{ formatCount(community.members_count) }}</span>
                        <span class="text-indigo-200 ml-1.5">{{ lp.social_proof.stat_label }}</span>
                    </div>
                </div>
                <div class="hidden sm:block w-px h-6 bg-indigo-400/40"></div>
                <p class="text-indigo-100 text-sm font-medium">{{ lp.social_proof.trust_line }}</p>
            </div>
        </section>

        <!-- ── BENEFITS ── -->
        <section v-if="lp.benefits" class="py-24 bg-white">
            <div class="max-w-5xl mx-auto px-6">
                <h2 class="text-3xl sm:text-4xl font-black text-gray-900 text-center mb-16">{{ lp.benefits.headline }}</h2>
                <div class="grid sm:grid-cols-2 gap-8">
                    <div v-for="(item, i) in lp.benefits.items" :key="i"
                        class="flex gap-5 p-6 rounded-2xl border border-gray-100 bg-gray-50 hover:border-indigo-200 hover:bg-indigo-50/40 transition-all group">
                        <div class="w-12 h-12 shrink-0 rounded-xl bg-indigo-100 flex items-center justify-center text-2xl group-hover:bg-indigo-200 transition">
                            {{ item.icon }}
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 mb-1.5">{{ item.title }}</h3>
                            <p class="text-sm text-gray-500 leading-relaxed">{{ item.body }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── FOR YOU ── -->
        <section v-if="lp.for_you" class="py-20 bg-linear-to-br from-indigo-50 to-white">
            <div class="max-w-2xl mx-auto px-6 text-center">
                <h2 class="text-3xl sm:text-4xl font-black text-gray-900 mb-12">{{ lp.for_you.headline }}</h2>
                <div class="space-y-4 text-left">
                    <div v-for="(point, i) in lp.for_you.points" :key="i"
                        class="flex items-start gap-4 bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
                        <div class="w-7 h-7 rounded-full bg-emerald-100 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        <p class="text-gray-700 font-medium leading-relaxed">{{ point }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── CREATOR ── -->
        <section v-if="lp.creator" class="py-24 bg-white">
            <div class="max-w-3xl mx-auto px-6">
                <div class="flex flex-col sm:flex-row items-center gap-10 bg-linear-to-br from-slate-900 to-indigo-950 rounded-3xl p-10 text-white">
                    <div class="shrink-0 text-center">
                        <div class="w-28 h-28 rounded-2xl overflow-hidden mx-auto mb-3 ring-4 ring-indigo-500/40">
                            <img v-if="community.owner?.avatar" :src="community.owner.avatar" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full bg-indigo-700 flex items-center justify-center text-3xl font-black text-white">
                                {{ community.owner?.name?.charAt(0) ?? '?' }}
                            </div>
                        </div>
                        <p class="font-bold text-white text-sm">{{ community.owner?.name }}</p>
                        <p class="text-indigo-300 text-xs mt-0.5">Creator</p>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black mb-4 text-white">{{ lp.creator.headline }}</h2>
                        <p class="text-slate-300 leading-relaxed">{{ lp.creator.bio }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── TESTIMONIALS ── -->
        <section v-if="lp.testimonials?.length" class="py-20 bg-gray-50">
            <div class="max-w-5xl mx-auto px-6">
                <h2 class="text-3xl font-black text-gray-900 text-center mb-12">What members are saying</h2>
                <div class="grid sm:grid-cols-3 gap-6">
                    <div v-for="(t, i) in lp.testimonials" :key="i"
                        class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex flex-col gap-4">
                        <!-- Stars -->
                        <div class="flex gap-0.5">
                            <svg v-for="s in 5" :key="s" class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        </div>
                        <p class="text-gray-600 text-sm leading-relaxed italic flex-1">"{{ t.quote }}"</p>
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

        <!-- ── FAQ ── -->
        <section v-if="lp.faq?.length" class="py-24 bg-white">
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
                        <div v-if="openFaq === i" class="px-6 pb-4 text-sm text-gray-500 leading-relaxed border-t border-gray-100 pt-3">
                            {{ item.answer }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── FINAL CTA ── -->
        <section class="py-24 bg-linear-to-br from-slate-900 via-indigo-950 to-slate-900 text-white text-center relative overflow-hidden">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-125 h-125 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none" />
            <div class="relative z-10 max-w-xl mx-auto px-6">
                <h2 class="text-3xl sm:text-4xl font-black mb-4 text-white">
                    {{ lp.cta_section?.headline ?? `Join ${community.name} Today` }}
                </h2>
                <p class="text-slate-400 mb-8 leading-relaxed">
                    {{ lp.cta_section?.subtext ?? 'Start your journey. Cancel anytime.' }}
                </p>
                <button @click="showJoinModal = true"
                    class="inline-flex items-center gap-2 px-10 py-4 bg-amber-400 hover:bg-amber-500 text-gray-900 font-black text-lg rounded-2xl transition-all shadow-xl shadow-amber-500/20 uppercase tracking-wide hover:scale-105 active:scale-95">
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

    <!-- Join Modal -->
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
                        <!-- Cover -->
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
                            <!-- Invited by -->
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
import { ref, computed, onMounted } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { usePixel } from '@/composables/usePixel';
import { useTiktokPixel } from '@/composables/useTiktokPixel';
import { useGoogleAnalytics } from '@/composables/useGoogleAnalytics';

const props = defineProps({
    community:  Object,
    affiliate:  Object,
    invitedBy:  Object,
    membership: Object,
    ownerIsPro: { type: Boolean, default: false },
    isOwner:    { type: Boolean, default: false },
});

const lp           = ref(props.community.landing_page ?? null);
const showJoinModal = ref(false);
const generating   = ref(false);
const generateError = ref(null);
const openFaq      = ref(null);
const copied       = ref(false);

// ── Pixels ──────────────────────────────────────────────────────────────────
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
    trackers.forEach(t => t.init());
    trackers.forEach(t => t.viewContent({
        content_name:     props.community.name,
        content_category: props.community.category ?? 'Community',
        content_type:     'product',
        value:            Number(props.community.price ?? 0),
        currency:         props.community.currency ?? 'PHP',
    }));
});

// ── AI Generate ──────────────────────────────────────────────────────────────
async function generate() {
    generating.value   = true;
    generateError.value = null;

    try {
        const res = await fetch(`/communities/${props.community.slug}/ai-landing`, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept':       'application/json',
            },
        });

        const data = await res.json();

        if (!res.ok) {
            generateError.value = data.error ?? 'Generation failed. Please try again.';
            return;
        }

        lp.value = data;
    } catch (e) {
        generateError.value = e?.message ?? 'Something went wrong. Please try again.';
    } finally {
        generating.value = false;
    }
}

// ── Join form ────────────────────────────────────────────────────────────────
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
        joinForm.post(`/communities/${props.community.slug}/join`);
    }
}

// ── Copy link ────────────────────────────────────────────────────────────────
function copyLink() {
    navigator.clipboard.writeText(window.location.href);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function formatCount(n) {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(n ?? 0);
}
</script>
