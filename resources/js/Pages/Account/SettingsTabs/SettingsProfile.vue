<template>
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h2 class="text-base font-bold text-gray-900 mb-1">Profile</h2>
        <p class="text-sm text-gray-400 mb-6">Update your public profile information.</p>

        <form @submit.prevent="saveProfile" class="space-y-5 max-w-lg">

            <!-- Avatar upload -->
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-indigo-100 flex items-center justify-center text-xl font-bold text-indigo-600 shrink-0 overflow-hidden">
                    <img
                        v-if="(avatarPreview || profileUser?.avatar) && !avatarBroken"
                        :src="avatarPreview || profileUser?.avatar"
                        class="w-full h-full object-cover"
                        alt="Avatar"
                        @error="avatarBroken = true"
                    />
                    <span v-else>{{ profileUser?.first_name?.charAt(0)?.toUpperCase() || '?' }}</span>
                </div>
                <div>
                    <label class="cursor-pointer">
                        <span class="text-sm font-medium text-indigo-600 hover:text-indigo-700 transition-colors">Change profile photo</span>
                        <input type="file" accept="image/*" class="hidden" @change="onAvatarChange" />
                    </label>
                    <p class="text-xs text-gray-400 mt-0.5">JPG, PNG or GIF — max 5 MB &nbsp;·&nbsp; <span class="font-medium text-gray-500">Recommended: 400 × 400 px</span></p>
                </div>
            </div>

            <!-- First / Last name -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name</label>
                    <input
                        v-model="profileForm.first_name"
                        type="text"
                        class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="profileForm.errors.first_name ? 'border-red-400' : 'border-gray-300'"
                    />
                    <p v-if="profileForm.errors.first_name" class="mt-1 text-xs text-red-600">{{ profileForm.errors.first_name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name</label>
                    <input
                        v-model="profileForm.last_name"
                        type="text"
                        class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="profileForm.errors.last_name ? 'border-red-400' : 'border-gray-300'"
                    />
                    <p v-if="profileForm.errors.last_name" class="mt-1 text-xs text-red-600">{{ profileForm.errors.last_name }}</p>
                </div>
            </div>

            <!-- Username -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Username</label>
                <div class="relative">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm">@</span>
                    <input
                        v-model="profileForm.username"
                        type="text"
                        class="w-full pl-7 pr-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                        :class="profileForm.errors.username ? 'border-red-400' : 'border-gray-300'"
                    />
                </div>
                <p v-if="profileForm.errors.username" class="mt-1 text-xs text-red-600">{{ profileForm.errors.username }}</p>
            </div>

            <!-- Bio -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Bio</label>
                <textarea
                    v-model="profileForm.bio"
                    rows="3"
                    maxlength="300"
                    placeholder="Tell people about yourself..."
                    class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                />
                <p class="text-xs text-gray-400 mt-1">{{ (profileForm.bio ?? '').length }}/300</p>
            </div>

            <!-- Location -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Location</label>
                <input
                    v-model="profileForm.location"
                    type="text"
                    placeholder="City, Country"
                    class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
            </div>

            <!-- Social links (collapsible) -->
            <div class="border border-gray-200 rounded-xl overflow-hidden">
                <button
                    type="button"
                    @click="socialLinksOpen = !socialLinksOpen"
                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition-colors"
                >
                    <span class="text-sm font-medium text-gray-800">Social links</span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="socialLinksOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div v-if="socialLinksOpen" class="border-t border-gray-100 p-4 space-y-3">
                    <div v-for="s in socialFields" :key="s.key">
                        <label class="block text-xs text-gray-500 mb-1">{{ s.label }}</label>
                        <input
                            v-model="profileForm.social_links[s.key]"
                            type="text"
                            :placeholder="s.placeholder"
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                </div>
            </div>

            <!-- Membership visibility (collapsible) -->
            <div class="border border-gray-200 rounded-xl overflow-hidden">
                <button
                    type="button"
                    @click="membershipVisOpen = !membershipVisOpen"
                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition-colors"
                >
                    <span class="text-sm font-medium text-gray-800">Membership visibility</span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="membershipVisOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div v-if="membershipVisOpen" class="border-t border-gray-100 p-4">
                    <p class="text-xs text-gray-400 mb-3">Control what groups show on your profile.</p>
                    <p class="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">Member of</p>
                    <div class="space-y-2">
                        <div
                            v-for="m in communityMembers"
                            :key="m.community_id"
                            class="flex items-center justify-between p-3 border border-gray-100 rounded-xl"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-600 shrink-0 overflow-hidden">
                                    <img v-if="m.avatar" :src="m.avatar" :alt="m.name" class="w-full h-full object-cover" />
                                    <span v-else>{{ m.name?.charAt(0)?.toUpperCase() }}</span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ m.name }}</p>
                                    <p class="text-xs text-gray-400">{{ m.price > 0 ? 'Private' : 'Free' }} · {{ m.role }}</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="toggleMembershipVisibility(m)"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="m.show_on_profile ? 'bg-green-500' : 'bg-gray-200'"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                    :class="m.show_on_profile ? 'translate-x-6' : 'translate-x-1'"
                                />
                            </button>
                        </div>
                        <p v-if="!communityMembers.length" class="text-xs text-gray-400">No community memberships.</p>
                    </div>
                </div>
            </div>

            <!-- Advanced (collapsible) -->
            <div class="border border-gray-200 rounded-xl overflow-hidden">
                <button
                    type="button"
                    @click="advancedOpen = !advancedOpen"
                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition-colors"
                >
                    <span class="text-sm font-medium text-gray-800">Advanced</span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="advancedOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div v-if="advancedOpen" class="border-t border-gray-100 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-700">Hide profile from search engines</p>
                        <button
                            type="button"
                            @click="profileForm.hide_from_search = !profileForm.hide_from_search"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            :class="profileForm.hide_from_search ? 'bg-green-500' : 'bg-gray-200'"
                        >
                            <span
                                class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                :class="profileForm.hide_from_search ? 'translate-x-6' : 'translate-x-1'"
                            />
                        </button>
                    </div>
                </div>
            </div>

            <button
                type="submit"
                :disabled="profileForm.processing"
                class="w-full py-2.5 bg-amber-400 hover:bg-amber-500 text-white text-sm font-bold rounded-lg tracking-wide transition-colors disabled:opacity-50"
            >
                {{ profileForm.processing ? 'Saving...' : 'UPDATE PROFILE' }}
            </button>
        </form>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    profileUser:      { type: Object, required: true },
    communityMembers: { type: Array, required: true },
});

const avatarPreview = ref(null);
const avatarBroken  = ref(false);

const socialFields = [
    { key: 'website',   label: 'Website',   placeholder: 'https://yourwebsite.com' },
    { key: 'instagram', label: 'Instagram', placeholder: 'https://instagram.com/yourhandle' },
    { key: 'x',         label: 'X',         placeholder: 'https://x.com/yourhandle' },
    { key: 'youtube',   label: 'YouTube',   placeholder: 'https://youtube.com/@yourchannel' },
    { key: 'linkedin',  label: 'LinkedIn',  placeholder: 'https://linkedin.com/in/yourprofile' },
    { key: 'facebook',  label: 'Facebook',  placeholder: 'https://facebook.com/yourprofile' },
];

const socialLinksOpen   = ref(false);
const membershipVisOpen = ref(false);
const advancedOpen      = ref(false);

const communityMembers = reactive(props.communityMembers.map(m => ({ ...m })));

const profileForm = useForm({
    username:         props.profileUser?.username         ?? '',
    first_name:       props.profileUser?.first_name       ?? '',
    last_name:        props.profileUser?.last_name        ?? '',
    bio:              props.profileUser?.bio              ?? '',
    location:         props.profileUser?.location         ?? '',
    social_links:     {
        website:   props.profileUser?.social_links?.website   ?? '',
        instagram: props.profileUser?.social_links?.instagram ?? '',
        x:         props.profileUser?.social_links?.x         ?? '',
        youtube:   props.profileUser?.social_links?.youtube   ?? '',
        linkedin:  props.profileUser?.social_links?.linkedin  ?? '',
        facebook:  props.profileUser?.social_links?.facebook  ?? '',
    },
    hide_from_search: props.profileUser?.hide_from_search ?? false,
    avatar:           null,
});

function onAvatarChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    profileForm.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
}

function saveProfile() {
    profileForm
        .transform(data => ({ ...data, _method: 'patch' }))
        .post('/account/settings/profile', { preserveScroll: true });
}

function toggleMembershipVisibility(m) {
    m.show_on_profile = !m.show_on_profile;
    router.patch(`/account/settings/profile/visibility/${m.community_id}`, {
        show_on_profile: m.show_on_profile,
    }, { preserveScroll: true });
}
</script>
