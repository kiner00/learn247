<template>
    <AppLayout :title="`${community.name} · About`" :community="community">
        <CommunityTabs :community="community" active-tab="about" />

        <!-- Invited by banner -->
        <div v-if="invitedBy" class="mb-4 flex items-center gap-3 bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3">
            <div class="w-9 h-9 rounded-full bg-indigo-200 flex items-center justify-center text-sm font-bold text-indigo-700 shrink-0 overflow-hidden">
                <img v-if="invitedBy.avatar" :src="invitedBy.avatar" class="w-full h-full object-cover" />
                <span v-else>{{ invitedBy.name.charAt(0).toUpperCase() }}</span>
            </div>
            <p class="text-sm text-indigo-700">
                <strong>{{ invitedBy.name }}</strong> invited you to join this community.
            </p>
        </div>

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

                        <!-- Stats row -->
                        <div class="grid grid-cols-3 gap-4 pt-4 border-t border-gray-100">
                            <div class="text-center">
                                <p class="text-xl font-black text-gray-900">{{ formatCount(community.members_count) }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">Members</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xl font-black text-gray-900">{{ community.is_private ? '🔒' : '🌐' }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ community.is_private ? 'Private' : 'Public' }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-sm font-black text-gray-900">{{ formatDate(community.created_at) }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">Founded</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Owner card -->
                <div v-if="community.owner" class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm flex items-center gap-4">
                    <div class="w-11 h-11 rounded-full bg-linear-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-base font-bold text-white shrink-0">
                        {{ community.owner.name.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Community Owner</p>
                        <p class="text-sm font-semibold text-gray-900">{{ community.owner.name }}</p>
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
                        <p class="text-xs text-gray-400 mt-0.5 mb-3">curzzo.com/communities/{{ community.slug }}</p>

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
                            class="w-full py-2.5 bg-amber-500 text-white text-sm font-bold rounded-xl hover:bg-amber-600 transition-colors mb-2"
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
            </div>
        </div>

        <!-- Guest Join Modal -->
        <Teleport to="body">
            <div v-if="showJoinModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-bold text-gray-900">Join {{ community.name }}</h3>
                        <button @click="showJoinModal = false" class="text-gray-400 hover:text-gray-600 p-1">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitJoin">
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">First name</label>
                                <input v-model="joinForm.first_name" type="text" required autocomplete="given-name"
                                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    :class="joinForm.errors.first_name ? 'border-red-400' : 'border-gray-300'" />
                                <p v-if="joinForm.errors.first_name" class="mt-1 text-xs text-red-600">{{ joinForm.errors.first_name }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Last name</label>
                                <input v-model="joinForm.last_name" type="text" required autocomplete="family-name"
                                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    :class="joinForm.errors.last_name ? 'border-red-400' : 'border-gray-300'" />
                                <p v-if="joinForm.errors.last_name" class="mt-1 text-xs text-red-600">{{ joinForm.errors.last_name }}</p>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
                            <input v-model="joinForm.email" type="email" required autocomplete="email"
                                class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                :class="joinForm.errors.email ? 'border-red-400' : 'border-gray-300'" />
                            <p v-if="joinForm.errors.email" class="mt-1 text-xs text-red-600">{{ joinForm.errors.email }}</p>
                        </div>

                        <div class="mb-5">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Phone number</label>
                            <input v-model="joinForm.phone" type="tel" required autocomplete="tel"
                                class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                :class="joinForm.errors.phone ? 'border-red-400' : 'border-gray-300'"
                                placeholder="+63 9XX XXX XXXX" />
                            <p v-if="joinForm.errors.phone" class="mt-1 text-xs text-red-600">{{ joinForm.errors.phone }}</p>
                        </div>

                        <button type="submit" :disabled="joinForm.processing"
                            class="w-full py-2.5 bg-amber-500 text-white text-sm font-bold rounded-xl hover:bg-amber-600 transition-colors disabled:opacity-50">
                            {{ joinForm.processing ? 'Redirecting to payment...' : `Proceed to Payment · ₱${Number(community.price).toLocaleString()}/mo` }}
                        </button>

                        <p class="text-xs text-gray-400 text-center mt-3">
                            You'll receive your login via email after payment.
                        </p>
                    </form>
                </div>
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
    community: Object,
    affiliate:  Object,
    invitedBy:  Object,
});

const showInviteModal = ref(false);
const showJoinModal   = ref(false);

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
