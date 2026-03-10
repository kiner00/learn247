<template>
    <AppLayout :title="`${community.name} · About`" :community="community">
        <CommunityTabs :community="community" active-tab="about" />


        <div class="flex gap-6 items-start">

            <!-- Main content -->
            <div class="flex-1 min-w-0">
                <!-- Cover image -->
                <div class="rounded-2xl overflow-hidden mb-6 h-48 shadow-sm">
                    <img
                        v-if="community.cover_image"
                        :src="community.cover_image"
                        :alt="community.name"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full bg-linear-to-br from-indigo-500 to-purple-700" />
                </div>

                <!-- Main card -->
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm mb-4">
                    <div class="p-6">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div>
                                <h1 class="text-xl font-black text-gray-900">{{ community.name }}</h1>
                                <span v-if="community.category" class="inline-block mt-1 text-xs font-medium px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                                    {{ community.category }}
                                </span>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-lg font-black text-gray-900">
                                    {{ community.price > 0 ? `₱${Number(community.price).toLocaleString()}` : 'Free' }}
                                </p>
                                <p v-if="community.price > 0" class="text-xs text-gray-400">/month</p>
                            </div>
                        </div>

                        <p v-if="community.description" class="text-sm text-gray-600 leading-relaxed mb-6">
                            {{ community.description }}
                        </p>
                        <p v-else class="text-sm text-gray-400 italic mb-6">No description provided.</p>

                        <!-- Stats row (Skool-style inline) -->
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 pt-4 border-t border-gray-100 text-sm text-gray-500">
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
                                <div class="w-4 h-4 rounded-full bg-indigo-400 flex items-center justify-center text-white text-[9px] font-bold shrink-0">
                                    {{ community.owner.name.charAt(0).toUpperCase() }}
                                </div>
                                By {{ community.owner.name }}
                            </span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right sidebar -->
            <div class="w-72 shrink-0">
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                    <div class="h-32 bg-gray-900 flex items-center justify-center overflow-hidden">
                        <img
                            v-if="community.cover_image"
                            :src="community.cover_image"
                            :alt="community.name"
                            class="w-full h-full object-cover"
                        />
                        <span v-else class="text-3xl font-black text-white opacity-20">
                            {{ community.name.charAt(0).toUpperCase() }}
                        </span>
                    </div>

                    <div class="p-4">
                        <h2 class="font-bold text-gray-900 text-sm">{{ community.name }}</h2>
                        <p class="text-xs text-gray-400 mt-0.5">curzzo.com/communities/{{ community.slug }}</p>
                        <p v-if="community.description" class="text-xs text-gray-500 mt-2 mb-1 line-clamp-2">{{ community.description }}</p>

                        <div class="flex justify-around text-center border-t border-gray-100 pt-3 mb-4">
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ formatCount(community.members_count) }}</p>
                                <p class="text-xs text-gray-400">Members</p>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-900">0</p>
                                <p class="text-xs text-gray-400">Online</p>
                            </div>
                        </div>

                        <!-- Join button (shown when coming via affiliate link and not logged in) -->
                        <button
                            v-if="invitedBy && !$page.props.auth?.user"
                            @click="showJoinModal = true"
                            class="w-full py-3 bg-amber-400 hover:bg-amber-500 text-gray-900 text-sm font-black rounded-xl tracking-wide uppercase transition-colors shadow-sm mb-2"
                        >
                            {{ community.price ? `Join · ₱${Number(community.price).toLocaleString()}/mo` : 'Join Group' }}
                        </button>

                        <button
                            v-else-if="$page.props.auth?.user"
                            @click="showInviteModal = true"
                            class="w-full py-2 text-sm font-semibold border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Invite People
                        </button>
                    </div>
                </div>

                <!-- Powered by Curzzo -->
                <p class="text-center text-xs text-gray-400 mt-3">
                    Powered by <span class="font-semibold"><span class="text-indigo-500">C</span><span class="text-purple-500">u</span><span class="text-amber-500">r</span><span class="text-green-500">z</span><span class="text-red-500">z</span><span class="text-blue-500">o</span></span>
                </p>
            </div>
        </div>

        <!-- Invite Popup Modal (auto-opens when visiting via affiliate link) -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="showJoinModal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeModal">
                    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden">

                        <!-- Step 1: Invite card -->
                        <div v-if="joinStep === 1">
                            <!-- Community cover banner -->
                            <div class="relative h-36 bg-gray-900 overflow-hidden">
                                <img
                                    v-if="community.cover_image"
                                    :src="community.cover_image"
                                    class="w-full h-full object-cover opacity-80"
                                />
                                <div v-else class="w-full h-full bg-linear-to-br from-indigo-500 to-purple-700" />
                                <!-- Close btn -->
                                <button @click="closeModal" class="absolute top-3 right-3 w-7 h-7 flex items-center justify-center rounded-full bg-black/30 hover:bg-black/50 text-white transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <div class="px-6 pt-5 pb-6">
                                <!-- Inviter pill -->
                                <div v-if="invitedBy" class="flex items-center gap-2.5 mb-5">
                                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center font-bold text-indigo-600 text-sm shrink-0 overflow-hidden ring-2 ring-indigo-200">
                                        <img v-if="invitedBy.avatar" :src="invitedBy.avatar" class="w-full h-full object-cover" />
                                        <span v-else>{{ invitedBy.name.charAt(0).toUpperCase() }}</span>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400 leading-none mb-0.5">Invited by</p>
                                        <p class="text-sm font-bold text-gray-900">{{ invitedBy.name }}</p>
                                    </div>
                                </div>

                                <!-- Community info -->
                                <h2 class="text-lg font-black text-gray-900 mb-1">{{ community.name }}</h2>
                                <p v-if="community.description" class="text-sm text-gray-500 line-clamp-2 mb-4">{{ community.description }}</p>

                                <div class="flex items-center gap-4 text-xs text-gray-400 mb-5">
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        {{ formatCount(community.members_count) }} members
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                                        {{ community.price > 0 ? `₱${Number(community.price).toLocaleString()}/mo` : 'Free' }}
                                    </span>
                                </div>

                                <button
                                    @click="joinStep = 2"
                                    class="w-full py-3 bg-amber-400 hover:bg-amber-500 text-gray-900 text-sm font-black rounded-2xl tracking-wide uppercase transition-colors shadow-sm"
                                >
                                    {{ community.price > 0 ? `Join · ₱${Number(community.price).toLocaleString()}/mo` : 'Join Group' }}
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Join form -->
                        <div v-else>
                            <div class="px-6 pt-6 pb-6">
                                <div class="flex items-center justify-between mb-5">
                                    <button @click="joinStep = 1" class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                        Back
                                    </button>
                                    <h3 class="text-base font-bold text-gray-900">Your details</h3>
                                    <div class="w-10" /><!-- spacer -->
                                </div>

                                <form @submit.prevent="submitJoin">
                                    <div class="grid grid-cols-2 gap-3 mb-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">First name</label>
                                            <input v-model="joinForm.first_name" type="text" required autocomplete="given-name"
                                                class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                :class="joinForm.errors.first_name ? 'border-red-400' : 'border-gray-300'" />
                                            <p v-if="joinForm.errors.first_name" class="mt-1 text-xs text-red-600">{{ joinForm.errors.first_name }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Last name</label>
                                            <input v-model="joinForm.last_name" type="text" required autocomplete="family-name"
                                                class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                :class="joinForm.errors.last_name ? 'border-red-400' : 'border-gray-300'" />
                                            <p v-if="joinForm.errors.last_name" class="mt-1 text-xs text-red-600">{{ joinForm.errors.last_name }}</p>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
                                        <input v-model="joinForm.email" type="email" required autocomplete="email"
                                            class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            :class="joinForm.errors.email ? 'border-red-400' : 'border-gray-300'" />
                                        <p v-if="joinForm.errors.email" class="mt-1 text-xs text-red-600">{{ joinForm.errors.email }}</p>
                                    </div>

                                    <div class="mb-5">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Phone number</label>
                                        <input v-model="joinForm.phone" type="tel" required autocomplete="tel"
                                            class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            :class="joinForm.errors.phone ? 'border-red-400' : 'border-gray-300'"
                                            placeholder="+63 9XX XXX XXXX" />
                                        <p v-if="joinForm.errors.phone" class="mt-1 text-xs text-red-600">{{ joinForm.errors.phone }}</p>
                                    </div>

                                    <button type="submit" :disabled="joinForm.processing"
                                        class="w-full py-3 bg-amber-400 hover:bg-amber-500 text-gray-900 text-sm font-black rounded-2xl tracking-wide uppercase transition-colors disabled:opacity-50">
                                        {{ joinForm.processing ? 'Redirecting...' : `Pay ₱${Number(community.price).toLocaleString()}/mo` }}
                                    </button>

                                    <p class="text-xs text-gray-400 text-center mt-3">
                                        You'll receive your login via email after payment.
                                    </p>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </Transition>
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
import { ref, computed, onMounted } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import InviteModal from '@/Components/InviteModal.vue';

const props = defineProps({
    community: Object,
    affiliate:  Object,
    invitedBy:  Object,
});

const page = usePage();
const showInviteModal = ref(false);
const showJoinModal   = ref(false);
const joinStep        = ref(1);

// Auto-open invite popup when visiting via affiliate link (not logged in)
onMounted(() => {
    if (props.invitedBy && !page.props.auth?.user) {
        showJoinModal.value = true;
    }
});

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
    joinStep.value = 1;
}

function submitJoin() {
    joinForm.post(`/ref-checkout/${props.invitedBy.code}`);
}

function formatDate(str) {
    if (!str) return '—';
    return new Date(str).toLocaleDateString('en-PH', { month: 'short', year: 'numeric' });
}

function formatCount(n) {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(n ?? 0);
}
</script>
