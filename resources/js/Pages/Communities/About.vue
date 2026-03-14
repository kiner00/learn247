<template>
    <AppLayout :title="`${community.name} · About`" :community="community">
        <CommunityTabs :community="community" active-tab="about" />

        <!-- Invited-by pill (small, top center, Skool-style) -->
        <div v-if="invitedBy" class="flex justify-center mb-6">
            <div class="flex items-center gap-2.5 bg-white border border-gray-200 shadow-md rounded-full pl-1.5 pr-5 py-1.5">
                <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-600 shrink-0 overflow-hidden ring-2 ring-white">
                    <img v-if="invitedBy.avatar" :src="invitedBy.avatar" class="w-full h-full object-cover" />
                    <span v-else>{{ invitedBy.name.charAt(0).toUpperCase() }}</span>
                </div>
                <p class="text-sm text-gray-700">
                    <span class="font-semibold text-gray-900">{{ invitedBy.name }}</span> invited you
                </p>
            </div>
        </div>

        <div class="flex flex-col-reverse lg:flex-row gap-6 items-start">

            <!-- Main content: one unified white card like Skool -->
            <div class="flex-1 min-w-0 w-full">
                <div class="bg-white rounded-2xl overflow-hidden mb-4">

                    <!-- Community name (above banner) -->
                    <div class="px-6 pt-5 pb-3">
                        <h1 class="text-xl font-black text-gray-900">{{ community.name }}</h1>
                        <span v-if="community.category" class="inline-block mt-1 text-xs font-medium px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700">{{ community.category }}</span>
                    </div>

                    <!-- Banner image (flush, no side padding) -->
                    <div class="w-full bg-gray-900 overflow-hidden" style="aspect-ratio: 16/9;">
                        <img
                            v-if="activeBannerImg"
                            :src="activeBannerImg"
                            :alt="community.name"
                            class="w-full h-full object-contain transition-all duration-300"
                        />
                        <div v-else class="w-full h-full bg-linear-to-br from-indigo-500 to-purple-700" />
                    </div>

                    <!-- Gallery strip (directly under banner) — clicking swaps the banner -->
                    <div v-if="community.gallery_images?.length" class="flex gap-2 px-4 pt-3 overflow-x-auto pb-1">
                        <div
                            v-for="(img, i) in community.gallery_images"
                            :key="i"
                            class="w-20 h-20 rounded-lg overflow-hidden shrink-0 cursor-pointer border-2 hover:opacity-90 transition-opacity"
                            :class="activeBannerImg === img ? 'border-indigo-500' : 'border-gray-200'"
                            @click="activeBannerImg = img"
                        >
                            <img :src="img" class="w-full h-full object-cover" />
                        </div>
                    </div>

                    <div class="px-6 py-4">
                        <!-- Stats row -->
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mb-4 text-sm text-gray-500">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                {{ community.is_private ? 'Private' : 'Public' }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ formatCount(community.members_count) }} members
                            </span>
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                                {{ community.price > 0 ? `₱${Number(community.price).toLocaleString()}/mo` : 'Free' }}
                            </span>
                            <span v-if="community.owner" class="flex items-center gap-1.5">
                                <div class="w-4 h-4 rounded-full bg-indigo-400 flex items-center justify-center text-white text-[9px] font-bold shrink-0 overflow-hidden">
                                    <img v-if="community.owner.avatar" :src="community.owner.avatar" class="w-full h-full object-cover" />
                                    <span v-else>{{ community.owner.name.charAt(0).toUpperCase() }}</span>
                                </div>
                                By {{ community.owner.name }}
                            </span>
                            <span v-if="getMilestone(community.members_count)"
                                class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-0.5 rounded-full border"
                                :class="getMilestone(community.members_count).classes">
                                {{ getMilestone(community.members_count).icon }} {{ getMilestone(community.members_count).label }}
                            </span>
                        </div>

                        <!-- Description -->
                        <p v-if="community.description" class="text-sm text-gray-600 leading-relaxed">{{ community.description }}</p>
                        <p v-else class="text-sm text-gray-400 italic">No description provided.</p>
                    </div>
                </div>

            </div>

            <!-- Right sidebar (Skool-style) -->
            <div class="w-full lg:w-72 shrink-0">
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">

                    <!-- Community image: avatar if set, else banner -->
                    <div class="relative overflow-hidden bg-gray-900" style="aspect-ratio: 1/1;">
                        <img
                            v-if="community.avatar || community.cover_image"
                            :src="community.avatar || community.cover_image"
                            :alt="community.name"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="w-full h-full bg-linear-to-br from-indigo-500 to-purple-700 flex items-center justify-center">
                            <span class="text-4xl font-black text-white opacity-40">{{ community.name.charAt(0).toUpperCase() }}</span>
                        </div>
                    </div>

                    <div class="p-4">
                        <h2 class="font-bold text-gray-900 text-sm leading-tight">{{ community.name }}</h2>
                        <p class="text-xs text-gray-400 mt-0.5 mb-3">curzzo.com/communities/{{ community.slug }}</p>

                        <p v-if="community.description" class="text-xs text-gray-500 leading-relaxed mb-3 line-clamp-3">{{ community.description }}</p>

                        <!-- Stats -->
                        <div class="flex gap-4 text-center py-3 border-t border-b border-gray-100 mb-3">
                            <div class="flex-1">
                                <p class="text-sm font-bold text-gray-900">{{ formatCount(community.members_count) }}</p>
                                <p class="text-xs text-gray-400">Members</p>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-bold text-gray-900">0</p>
                                <p class="text-xs text-gray-400">Online</p>
                            </div>
                            <div v-if="community.owner" class="flex-1">
                                <div class="flex justify-center mb-0.5">
                                    <div class="w-5 h-5 rounded-full bg-indigo-100 overflow-hidden flex items-center justify-center">
                                        <img v-if="community.owner.avatar" :src="community.owner.avatar" class="w-full h-full object-cover" />
                                        <span v-else class="text-[9px] font-bold text-indigo-600">{{ community.owner.name.charAt(0) }}</span>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-400">Admin</p>
                            </div>
                        </div>

                        <!-- Recent member avatars -->
                        <div v-if="recentMembers.length" class="flex flex-wrap gap-1 mb-3">
                            <div
                                v-for="(member, i) in recentMembers.slice(0, 8)"
                                :key="i"
                                class="w-7 h-7 rounded-full bg-indigo-100 overflow-hidden border-2 border-white shadow-sm shrink-0"
                                :title="member.name"
                            >
                                <img v-if="member.avatar" :src="member.avatar" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-[9px] font-bold text-indigo-600">
                                    {{ member.name.charAt(0).toUpperCase() }}
                                </div>
                            </div>
                        </div>

                        <!-- Join / Invite button -->
                        <button
                            v-if="invitedBy && !$page.props.auth?.user"
                            @click="showJoinModal = true"
                            class="w-full py-3 bg-amber-400 hover:bg-amber-500 text-gray-900 text-sm font-black rounded-xl tracking-wide uppercase transition-colors shadow-sm"
                        >
                            {{ community.price > 0 ? `Join · ₱${Number(community.price).toLocaleString()}/mo` : 'Join Group' }}
                        </button>
                        <button
                            v-else-if="membership"
                            @click="showInviteModal = true"
                            class="w-full py-2.5 bg-amber-400 hover:bg-amber-500 text-gray-900 text-sm font-bold rounded-xl transition-colors"
                        >
                            Invite People
                        </button>
                        <button
                            v-else-if="!$page.props.auth?.user"
                            @click="showJoinModal = true"
                            class="w-full py-3 bg-amber-400 hover:bg-amber-500 text-gray-900 text-sm font-black rounded-xl tracking-wide uppercase transition-colors shadow-sm"
                        >
                            {{ community.price > 0 ? `Join · ₱${Number(community.price).toLocaleString()}/mo` : 'Join Group' }}
                        </button>
                    </div>
                </div>

                <!-- Powered by Curzzo -->
                <div class="flex items-center justify-center gap-1.5 mt-3">
                    <span class="text-xs text-gray-400">Powered by</span>
                    <img src="/brand/logo-transparent.png" alt="Curzzo" class="h-5 w-auto" />
                </div>
            </div>
        </div>

        <!-- Join Modal (big, centered, opens on JOIN click) -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showJoinModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeModal">
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

                            <!-- Cover banner -->
                            <div class="relative h-48 bg-gray-900 overflow-hidden">
                                <img
                                    v-if="community.cover_image"
                                    :src="community.cover_image"
                                    class="w-full h-full object-cover opacity-80"
                                />
                                <div v-else class="w-full h-full bg-linear-to-br from-indigo-500 to-purple-700" />

                                <!-- Overlay gradient -->
                                <div class="absolute inset-0 bg-linear-to-t from-black/50 to-transparent" />

                                <!-- Community name overlay -->
                                <div class="absolute bottom-4 left-6">
                                    <h2 class="text-xl font-black text-white">{{ community.name }}</h2>
                                    <p class="text-sm text-white/70 mt-0.5">
                                        {{ community.price > 0 ? `₱${Number(community.price).toLocaleString()}/mo` : 'Free' }}
                                        &nbsp;·&nbsp; {{ formatCount(community.members_count) }} members
                                    </p>
                                </div>

                                <!-- Close button -->
                                <button @click="closeModal" class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full bg-black/40 hover:bg-black/60 text-white transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- Form body -->
                            <div class="p-8">
                                <!-- Invited by row -->
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
                                            <input v-model="joinForm.first_name" type="text" required autocomplete="given-name"
                                                class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                                :class="joinForm.errors.first_name ? 'border-red-400' : 'border-gray-300'" />
                                            <p v-if="joinForm.errors.first_name" class="mt-1 text-xs text-red-600">{{ joinForm.errors.first_name }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Last name</label>
                                            <input v-model="joinForm.last_name" type="text" required autocomplete="family-name"
                                                class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                                :class="joinForm.errors.last_name ? 'border-red-400' : 'border-gray-300'" />
                                            <p v-if="joinForm.errors.last_name" class="mt-1 text-xs text-red-600">{{ joinForm.errors.last_name }}</p>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                                        <input v-model="joinForm.email" type="email" required autocomplete="email"
                                            class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                            :class="joinForm.errors.email ? 'border-red-400' : 'border-gray-300'" />
                                        <p v-if="joinForm.errors.email" class="mt-1 text-xs text-red-600">{{ joinForm.errors.email }}</p>
                                    </div>

                                    <div class="mb-6">
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone number</label>
                                        <input v-model="joinForm.phone" type="tel" required autocomplete="tel"
                                            class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                            :class="joinForm.errors.phone ? 'border-red-400' : 'border-gray-300'"
                                            placeholder="+63 9XX XXX XXXX" />
                                        <p v-if="joinForm.errors.phone" class="mt-1 text-xs text-red-600">{{ joinForm.errors.phone }}</p>
                                    </div>

                                    <button type="submit" :disabled="joinForm.processing"
                                        class="w-full py-3.5 bg-amber-400 hover:bg-amber-500 text-gray-900 text-sm font-black rounded-2xl tracking-wide uppercase transition-colors disabled:opacity-50 shadow-sm">
                                        {{ joinForm.processing ? 'Redirecting to payment...' : (community.price > 0 ? `Proceed to Payment · ₱${Number(community.price).toLocaleString()}/mo` : 'Join for Free') }}
                                    </button>

                                    <p class="text-xs text-gray-400 text-center mt-4">
                                        Your login credentials will be sent to your email after payment.
                                    </p>
                                </form>
                            </div>

                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- Lightbox -->
        <Teleport to="body">
            <div v-if="lightboxImg" class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4" @click="lightboxImg = null">
                <img :src="lightboxImg" class="max-w-full max-h-full rounded-xl shadow-2xl" @click.stop />
            </div>
        </Teleport>

        <InviteModal
            :show="showInviteModal"
            :community-name="community.name"
            :invite-url="inviteUrl"
            @close="showInviteModal = false"
        />
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import InviteModal from '@/Components/InviteModal.vue';

const props = defineProps({
    community:     Object,
    affiliate:     Object,
    invitedBy:     Object,
    membership:    Object,
    recentMembers: { type: Array, default: () => [] },
});

const showInviteModal = ref(false);
const showJoinModal   = ref(false);
const lightboxImg     = ref(null);
const activeBannerImg = ref(props.community.cover_image || null);

const inviteUrl = computed(() =>
    props.affiliate?.code
        ? `${window.location.origin}/ref/${props.affiliate.code}`
        : `${window.location.origin}/communities/${props.community.slug}`
);

const joinForm = useForm({
    first_name: '',
    last_name:  '',
    email:      '',
    phone:      '',
});

function closeModal() {
    showJoinModal.value = false;
}

function submitJoin() {
    joinForm.post(`/ref-checkout/${props.invitedBy.code}`);
}

function formatCount(n) {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(n ?? 0);
}

function getMilestone(count) {
    if (count >= 100_000)   return { icon: '🌟', label: '100K Plaque', classes: 'bg-yellow-50 border-yellow-300 text-yellow-700' };
    if (count >= 50_000)    return { icon: '🏆', label: 'Platinum',    classes: 'bg-slate-100 border-slate-400 text-slate-700' };
    if (count >= 10_000)    return { icon: '💎', label: 'Diamond',     classes: 'bg-cyan-50 border-cyan-300 text-cyan-700' };
    if (count >= 1_000)     return { icon: '🥇', label: 'Gold',        classes: 'bg-amber-50 border-amber-300 text-amber-700' };
    if (count >= 500)       return { icon: '🥈', label: 'Silver',      classes: 'bg-gray-100 border-gray-400 text-gray-600' };
    if (count >= 100)       return { icon: '🥉', label: 'Bronze',      classes: 'bg-orange-50 border-orange-300 text-orange-700' };
    return null;
}
</script>
