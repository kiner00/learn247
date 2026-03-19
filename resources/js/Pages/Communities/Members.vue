<template>
    <AppLayout :title="`${community.name} · Members`" :community="community">
        <CommunityTabs :community="community" active-tab="members" />

        <div class="flex flex-col lg:flex-row gap-6 items-start">

            <!-- ── Main column ─────────────────────────────────────────── -->
            <div class="flex-1 min-w-0 w-full">

                <!-- Filter tabs + Invite -->
                <div class="flex items-center justify-between mb-5">
                    <div class="flex gap-2">
                        <Link
                            :href="`/communities/${community.slug}/members`"
                            class="px-4 py-1.5 text-sm rounded-full font-medium border transition-colors"
                            :class="!currentFilter
                                ? 'bg-gray-900 text-white border-gray-900'
                                : 'bg-white text-gray-600 border-gray-200 hover:border-gray-400'"
                        >
                            Members <span class="ml-1 opacity-70">{{ totalCount }}</span>
                        </Link>
                        <Link
                            :href="`/communities/${community.slug}/members?filter=admin`"
                            class="px-4 py-1.5 text-sm rounded-full font-medium border transition-colors"
                            :class="currentFilter === 'admin'
                                ? 'bg-gray-900 text-white border-gray-900'
                                : 'bg-white text-gray-600 border-gray-200 hover:border-gray-400'"
                        >
                            Admins <span class="ml-1 opacity-70">{{ adminCount }}</span>
                        </Link>
                        <span class="px-4 py-1.5 text-sm rounded-full font-medium border border-gray-200 bg-white text-gray-400 cursor-default">
                            Online <span class="ml-1">0</span>
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="isOwner && community.sms_provider"
                            @click="showSmsModal = true"
                            class="px-4 py-1.5 text-sm font-semibold rounded-full bg-emerald-500 hover:bg-emerald-600 text-white transition-colors flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Send SMS
                        </button>
                        <button
                            v-if="$page.props.auth?.user"
                            @click="showInviteModal = true"
                            class="px-4 py-1.5 text-sm font-semibold rounded-full bg-yellow-400 hover:bg-yellow-500 text-gray-900 transition-colors"
                        >
                            Invite
                        </button>
                    </div>
                </div>

                <!-- Member list -->
                <div class="divide-y divide-gray-100 bg-white border border-gray-200 rounded-2xl overflow-hidden">
                    <div
                        v-for="member in members.data"
                        :key="member.id"
                        class="flex items-start gap-4 px-5 py-4 hover:bg-gray-50 transition-colors"
                    >
                        <!-- Avatar + level badge -->
                        <div class="relative shrink-0">
                            <div
                                class="w-12 h-12 rounded-full flex items-center justify-center text-lg font-bold"
                                :class="avatarColor(member.user?.name)"
                            >
                                {{ member.user?.name?.charAt(0)?.toUpperCase() }}
                            </div>
                            <span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-indigo-600 text-white text-[10px] font-bold flex items-center justify-center ring-2 ring-white">
                                {{ computeLevel(member.points ?? 0) }}
                            </span>
                        </div>

                        <!-- Info + actions -->
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-900 text-sm leading-tight">{{ member.user?.name }}</p>
                            <p class="text-xs text-gray-400 mb-1">@{{ member.user?.username ?? `user${member.user?.id}` }}</p>
                            <p v-if="member.user?.bio" class="text-sm text-gray-600 mb-2">{{ member.user.bio }}</p>

                            <div class="flex items-center gap-3 text-xs text-gray-400 flex-wrap">
                                <span>Joined {{ formatDate(member.joined_at) }}</span>
                                <span class="text-gray-200">·</span>
                                <span class="font-medium text-indigo-500">{{ member.points ?? 0 }} pts</span>
                                <span class="text-gray-200">·</span>
                                <span
                                    class="px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="{
                                        'bg-indigo-100 text-indigo-700': member.role === 'admin',
                                        'bg-purple-100 text-purple-700': member.role === 'moderator',
                                        'bg-gray-100 text-gray-500':     member.role === 'member',
                                    }"
                                >
                                    {{ member.role }}
                                </span>
                            </div>

                            <!-- Actions (below info on all screen sizes) -->
                            <div class="flex items-center gap-2 mt-2 flex-wrap">
                                <Link
                                    v-if="$page.props.auth?.user && member.user?.id !== $page.props.auth.user.id"
                                    :href="`/messages/${member.user?.username ?? member.user?.id}`"
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-full text-gray-500 hover:border-indigo-300 hover:text-indigo-600 transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                    Chat
                                </Link>
                                <template v-if="isAdmin && member.user?.id !== community.owner_id">
                                    <select
                                        :value="member.role"
                                        @change="changeRole(member, $event.target.value)"
                                        class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                                    >
                                        <option value="member">Member</option>
                                        <option value="moderator">Moderator</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <button
                                        @click="removeMember(member)"
                                        class="text-xs text-gray-400 hover:text-red-500 transition-colors"
                                    >
                                        Remove
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-if="!members.data.length" class="text-center py-16">
                        <p class="text-sm text-gray-500">No members found.</p>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="members.last_page > 1" class="mt-5 flex justify-center gap-2">
                    <Link
                        v-for="link in members.links"
                        :key="link.label"
                        :href="link.url ?? ''"
                        v-html="link.label"
                        class="px-3 py-1.5 text-sm rounded-lg border transition-colors"
                        :class="link.active
                            ? 'bg-indigo-600 text-white border-indigo-600'
                            : link.url
                                ? 'border-gray-200 text-gray-600 hover:border-indigo-300'
                                : 'border-gray-100 text-gray-300 cursor-default'"
                    />
                </div>
            </div>

            <!-- ── Right sidebar ────────────────────────────────────────── -->
            <div class="w-full lg:w-72 shrink-0 space-y-4">
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                    <!-- Cover image -->
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
                        <p v-if="community.description" class="text-sm text-gray-600 mb-4 line-clamp-3">{{ community.description }}</p>

                        <!-- Stats -->
                        <div class="flex justify-around text-center border-t border-gray-100 pt-3 mb-4">
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ totalCount }}</p>
                                <p class="text-xs text-gray-400">Members</p>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-900">0</p>
                                <p class="text-xs text-gray-400">Online</p>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ adminCount }}</p>
                                <p class="text-xs text-gray-400">Admin</p>
                            </div>
                        </div>

                        <!-- Invite button -->
                        <button
                            v-if="$page.props.auth?.user"
                            @click="showInviteModal = true"
                            class="w-full py-2 text-sm font-semibold border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Invite People
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <InviteModal
            :show="showInviteModal"
            :community-name="community.name"
            :invite-url="inviteUrl"
            @close="showInviteModal = false"
        />

        <!-- SMS Blast Modal -->
        <Teleport to="body">
            <div
                v-if="showSmsModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="showSmsModal = false"
            >
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-semibold text-gray-900">Send SMS Blast</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Sends to all members with a phone number on their profile.</p>
                        </div>
                        <button @click="showSmsModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Provider badge -->
                    <div class="flex items-center gap-2 mb-4 p-3 bg-gray-50 rounded-xl">
                        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <span class="text-sm text-gray-600">
                            Provider: <strong>{{ smsProviderLabel }}</strong>
                        </span>
                        <Link :href="`/communities/${community.slug}/settings`" class="ml-auto text-xs text-indigo-500 hover:underline">
                            Change
                        </Link>
                    </div>

                    <form @submit.prevent="sendSmsBlast">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Message</label>
                            <textarea
                                v-model="smsBlastMessage"
                                rows="5"
                                maxlength="1600"
                                placeholder="Type your SMS message here..."
                                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                            />
                            <p class="mt-1 text-xs text-gray-400 text-right">{{ smsBlastMessage.length }} / 1600</p>
                        </div>

                        <p v-if="smsBlastError" class="mb-3 text-sm text-red-600">{{ smsBlastError }}</p>

                        <div class="flex gap-3">
                            <button
                                type="button"
                                @click="showSmsModal = false"
                                class="flex-1 px-4 py-2.5 border border-gray-300 text-sm font-medium rounded-xl text-gray-700 hover:bg-gray-50 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="smsSending || !smsBlastMessage.trim()"
                                class="flex-1 px-4 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold rounded-xl transition-colors disabled:opacity-50"
                            >
                                {{ smsSending ? 'Sending…' : 'Send SMS' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, usePage, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import InviteModal from '@/Components/InviteModal.vue';

const props = defineProps({
    community:  Object,
    members:    Object,
    totalCount: Number,
    adminCount: Number,
    affiliate:  Object,
});

const page = usePage();

const currentFilter = computed(() => {
    const url = new URL(window.location.href);
    return url.searchParams.get('filter') ?? null;
});

const currentUserId = computed(() => page.props.auth?.user?.id);

const showInviteModal = ref(false);

// SMS blast
const showSmsModal    = ref(false);
const smsBlastMessage = ref('');
const smsSending      = ref(false);
const smsBlastError   = ref('');

const SMS_PROVIDER_LABELS = {
    semaphore:  'Semaphore',
    philsms:    'PhilSMS',
    xtreme_sms: 'Xtreme SMS',
};

const smsProviderLabel = computed(() =>
    SMS_PROVIDER_LABELS[props.community.sms_provider] ?? props.community.sms_provider
);

function sendSmsBlast() {
    if (!smsBlastMessage.value.trim()) return;
    smsSending.value   = true;
    smsBlastError.value = '';
    router.post(
        `/communities/${props.community.slug}/sms-blast`,
        { message: smsBlastMessage.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showSmsModal.value    = false;
                smsBlastMessage.value = '';
            },
            onError: (errors) => {
                smsBlastError.value = errors.message ?? 'Something went wrong.';
            },
            onFinish: () => { smsSending.value = false; },
        },
    );
}

const inviteUrl = computed(() =>
    props.affiliate?.code
        ? `${window.location.origin}/ref/${props.affiliate.code}`
        : `${window.location.origin}/communities/${props.community.slug}`
);

const isOwner = computed(() => currentUserId.value === props.community.owner_id);

const isAdmin = computed(() => {
    const me = props.members.data.find((m) => m.user?.id === currentUserId.value);
    return me?.role === 'admin' || isOwner.value;
});

const avatarColors = [
    'bg-indigo-100 text-indigo-600',
    'bg-violet-100 text-violet-600',
    'bg-pink-100 text-pink-600',
    'bg-emerald-100 text-emerald-600',
    'bg-amber-100 text-amber-600',
    'bg-sky-100 text-sky-600',
];

function avatarColor(name) {
    if (!name) return avatarColors[0];
    return avatarColors[name.charCodeAt(0) % avatarColors.length];
}

function changeRole(member, role) {
    router.patch(
        `/communities/${props.community.slug}/members/${member.user.id}/role`,
        { role },
        { preserveScroll: true },
    );
}

function removeMember(member) {
    if (!confirm(`Remove ${member.user?.name} from the community?`)) return;
    router.delete(
        `/communities/${props.community.slug}/members/${member.user.id}`,
        { preserveScroll: true },
    );
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}

const LEVEL_THRESHOLDS = [0, 50, 150, 350, 700, 1250, 2000, 3000, 4500, 6500, 9000, 12500];

function computeLevel(points) {
    for (let i = LEVEL_THRESHOLDS.length - 1; i >= 0; i--) {
        if (points >= LEVEL_THRESHOLDS[i]) return i + 1;
    }
    return 1;
}
</script>
