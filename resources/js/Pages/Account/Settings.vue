<template>
    <AppLayout title="Settings">
        <div class="flex gap-0 items-start -mx-4 sm:-mx-6 lg:-mx-8">

            <!-- ── Left sidebar nav ───────────────────────────────────────── -->
            <div class="w-52 shrink-0 py-2 px-2">
                <nav class="space-y-0.5">
                    <button
                        v-for="item in navItems"
                        :key="item.key"
                        @click="activeTab = item.key"
                        class="w-full text-left px-4 py-2.5 text-sm rounded-xl font-medium transition-colors"
                        :class="activeTab === item.key
                            ? 'bg-amber-100 text-amber-800'
                            : 'text-gray-700 hover:bg-gray-100'"
                    >
                        {{ item.label }}
                    </button>
                </nav>
            </div>

            <!-- ── Main content ───────────────────────────────────────────── -->
            <div class="flex-1 min-w-0 py-2 pr-4 sm:pr-6 lg:pr-8">

                <!-- Communities -->
                <div v-if="activeTab === 'communities'">
                    <div class="bg-white border border-gray-200 rounded-2xl p-6">
                        <h2 class="text-base font-bold text-gray-900 mb-1">Communities</h2>
                        <p class="text-sm text-gray-400 mb-5">Your community memberships.</p>

                        <div v-if="memberships.length" class="space-y-2">
                            <div
                                v-for="m in memberships"
                                :key="m.community_id"
                                class="flex items-center gap-3 p-3 border border-gray-100 rounded-xl"
                            >
                                <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-sm font-bold text-gray-600 shrink-0 overflow-hidden">
                                    <img v-if="m.avatar" :src="m.avatar" :alt="m.name" class="w-full h-full object-cover" />
                                    <span v-else>{{ m.name?.charAt(0)?.toUpperCase() }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ m.name }}</p>
                                    <p class="text-xs text-gray-400">
                                        {{ m.is_owner ? 'Owner' : m.role }}
                                        · {{ m.price > 0 ? `₱${m.price}/month` : 'Free' }}
                                    </p>
                                </div>
                                <Link
                                    v-if="m.is_owner"
                                    :href="`/communities/${m.slug}/settings`"
                                    class="text-xs text-gray-400 hover:text-indigo-600 transition-colors px-2 py-1 border border-gray-200 rounded-lg"
                                >
                                    Settings
                                </Link>
                                <Link
                                    v-else
                                    :href="`/communities/${m.slug}`"
                                    class="text-xs text-gray-400 hover:text-indigo-600 transition-colors px-2 py-1 border border-gray-200 rounded-lg"
                                >
                                    View
                                </Link>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-400">You haven't joined any communities yet.</p>
                    </div>
                </div>

                <!-- Profile -->
                <div v-else-if="activeTab === 'profile'">
                    <div class="bg-white border border-gray-200 rounded-2xl p-6">
                        <h2 class="text-base font-bold text-gray-900 mb-1">Profile</h2>
                        <p class="text-sm text-gray-400 mb-6">Update your public profile information.</p>

                        <form @submit.prevent="saveProfile" class="space-y-5 max-w-lg">

                            <!-- Avatar upload -->
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 rounded-full bg-indigo-100 flex items-center justify-center text-xl font-bold text-indigo-600 shrink-0 overflow-hidden">
                                    <img
                                        v-if="avatarPreview || props.profileUser?.avatar"
                                        :src="avatarPreview || props.profileUser?.avatar"
                                        class="w-full h-full object-cover"
                                        alt="Avatar"
                                    />
                                    <span v-else>{{ props.profileUser?.name?.charAt(0)?.toUpperCase() }}</span>
                                </div>
                                <div>
                                    <label class="cursor-pointer">
                                        <span class="text-sm font-medium text-indigo-600 hover:text-indigo-700 transition-colors">Change profile photo</span>
                                        <input type="file" accept="image/*" class="hidden" @change="onAvatarChange" />
                                    </label>
                                    <p class="text-xs text-gray-400 mt-0.5">JPG, PNG or GIF. Max 5 MB.</p>
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

                            <!-- Username (read-only) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Username</label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm">@</span>
                                    <input
                                        :value="props.profileUser?.username"
                                        type="text"
                                        readonly
                                        tabindex="-1"
                                        class="w-full pl-7 pr-3.5 py-2.5 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-400 cursor-not-allowed"
                                        @keydown.prevent
                                    />
                                </div>
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
                </div>

                <!-- Account -->
                <div v-else-if="activeTab === 'account'">
                    <div class="bg-white border border-gray-200 rounded-2xl p-6">
                        <h2 class="text-base font-bold text-gray-900 mb-6">Account</h2>

                        <!-- Email row -->
                        <div class="flex items-center justify-between py-4 border-b border-gray-100">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Email</p>
                                <p class="text-sm text-gray-500 mt-0.5">{{ props.profileUser?.email }}</p>
                            </div>
                            <button @click="showEmailForm = !showEmailForm" class="px-4 py-2 text-xs font-bold text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 tracking-wide transition-colors">
                                CHANGE EMAIL
                            </button>
                        </div>
                        <div v-if="showEmailForm" class="py-4 border-b border-gray-100">
                            <form @submit.prevent="saveEmail" class="space-y-3 max-w-sm">
                                <input v-model="emailForm.email" type="email" placeholder="New email address"
                                    class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    :class="emailForm.errors.email ? 'border-red-400' : 'border-gray-300'" />
                                <p v-if="emailForm.errors.email" class="text-xs text-red-600">{{ emailForm.errors.email }}</p>
                                <button type="submit" :disabled="emailForm.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                                    {{ emailForm.processing ? 'Saving...' : 'Save email' }}
                                </button>
                            </form>
                        </div>

                        <!-- Password row -->
                        <div class="flex items-center justify-between py-4 border-b border-gray-100">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Password</p>
                                <p class="text-sm text-gray-500 mt-0.5">Change your password</p>
                            </div>
                            <button @click="showPasswordForm = !showPasswordForm" class="px-4 py-2 text-xs font-bold text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 tracking-wide transition-colors">
                                CHANGE PASSWORD
                            </button>
                        </div>
                        <div v-if="showPasswordForm" class="py-4 border-b border-gray-100">
                            <form @submit.prevent="savePassword" class="space-y-3 max-w-sm">
                                <input v-model="passwordForm.current_password" type="password" placeholder="Current password"
                                    class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    :class="passwordForm.errors.current_password ? 'border-red-400' : 'border-gray-300'" />
                                <p v-if="passwordForm.errors.current_password" class="text-xs text-red-600">{{ passwordForm.errors.current_password }}</p>
                                <input v-model="passwordForm.password" type="password" placeholder="New password"
                                    class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    :class="passwordForm.errors.password ? 'border-red-400' : 'border-gray-300'" />
                                <p v-if="passwordForm.errors.password" class="text-xs text-red-600">{{ passwordForm.errors.password }}</p>
                                <input v-model="passwordForm.password_confirmation" type="password" placeholder="Confirm new password"
                                    class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <button type="submit" :disabled="passwordForm.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                                    {{ passwordForm.processing ? 'Saving...' : 'Save password' }}
                                </button>
                            </form>
                        </div>

                        <!-- Timezone row -->
                        <div class="py-4 border-b border-gray-100">
                            <p class="text-sm font-semibold text-gray-800 mb-3">Timezone</p>
                            <div class="flex gap-2 max-w-sm">
                                <select v-model="timezoneForm.timezone"
                                    class="flex-1 px-3.5 py-2.5 border border-gray-200 rounded-lg text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                                    <option value="Asia/Manila">(GMT +08:00) Asia/Manila</option>
                                    <option value="UTC">(GMT +00:00) UTC</option>
                                    <option value="America/New_York">(GMT -05:00) America/New_York</option>
                                    <option value="America/Los_Angeles">(GMT -08:00) America/Los_Angeles</option>
                                    <option value="Asia/Tokyo">(GMT +09:00) Asia/Tokyo</option>
                                    <option value="Asia/Singapore">(GMT +08:00) Asia/Singapore</option>
                                </select>
                                <button @click="saveTimezone" :disabled="timezoneForm.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                                    Save
                                </button>
                            </div>
                        </div>

                        <!-- Log out everywhere -->
                        <div class="flex items-center justify-between pt-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Log out of all devices</p>
                                <p class="text-sm text-gray-500 mt-0.5">Log out of all active sessions on all devices.</p>
                            </div>
                            <button @click="logoutEverywhere" class="px-4 py-2 text-xs font-bold text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 tracking-wide transition-colors">
                                LOG OUT EVERYWHERE
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Affiliates -->
                <div v-else-if="activeTab === 'affiliates'">
                    <div class="bg-white border border-gray-200 rounded-2xl p-6">
                        <h2 class="text-base font-bold text-gray-900 mb-1">Affiliates</h2>
                        <p class="text-sm text-gray-400 mb-6">Earn commission for life when you invite somebody to create or join a community.</p>

                        <!-- Stats row -->
                        <div class="flex gap-3 mb-2">
                            <div class="flex-1 border border-gray-200 rounded-xl p-4">
                                <p class="text-xl font-bold text-gray-900">₱0</p>
                                <p class="text-xs text-gray-400 mt-0.5">Last 30 days</p>
                            </div>
                            <div class="flex-1 border border-gray-200 rounded-xl p-4">
                                <p class="text-xl font-bold text-gray-900">₱0</p>
                                <p class="text-xs text-gray-400 mt-0.5">Lifetime</p>
                            </div>
                            <div class="flex-1 border border-gray-200 rounded-xl p-4">
                                <p class="text-xl font-bold text-green-600">₱0</p>
                                <p class="text-xs text-gray-400 mt-0.5">Account balance</p>
                            </div>
                            <div class="flex items-center">
                                <button disabled class="px-5 py-3 bg-gray-100 text-gray-400 text-sm font-bold rounded-xl cursor-not-allowed tracking-wide">
                                    PAYOUT
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mb-6">₱0 available soon</p>

                        <!-- Affiliate link -->
                        <div>
                            <p class="text-sm font-bold text-gray-900 mb-3">Your affiliate links</p>
                            <div class="flex gap-2 mb-3">
                                <span class="px-3 py-1.5 bg-gray-700 text-white text-xs font-medium rounded-full">Platform</span>
                            </div>
                            <p class="text-sm text-gray-600 mb-3">
                                Earn <strong>20% commission</strong> when you invite somebody to create a community.
                            </p>
                            <div class="flex gap-2">
                                <input
                                    :value="affiliateLink"
                                    readonly
                                    class="flex-1 px-3.5 py-2.5 border border-gray-200 rounded-lg text-sm text-indigo-600 bg-white"
                                />
                                <button
                                    @click="copyLink"
                                    class="px-5 py-2.5 bg-amber-400 hover:bg-amber-500 text-white text-sm font-bold rounded-lg transition-colors tracking-wide"
                                >
                                    {{ copied ? 'COPIED!' : 'COPY' }}
                                </button>
                            </div>
                        </div>

                        <!-- Referrals empty state -->
                        <div class="border border-gray-100 rounded-xl p-12 text-center mt-6">
                            <div class="text-4xl mb-3">💰</div>
                            <p class="text-sm text-gray-400">Your referrals will show here</p>
                        </div>
                    </div>
                </div>


                <!-- Notifications -->
                <div v-else-if="activeTab === 'notifications'">
                    <div class="bg-white border border-gray-200 rounded-2xl p-6">
                        <h2 class="text-base font-bold text-gray-900 mb-6">Notifications</h2>

                        <div class="space-y-0 divide-y divide-gray-100">
                            <div v-for="n in notificationToggles" :key="n.key" class="flex items-center justify-between py-3.5">
                                <p class="text-sm text-gray-700">{{ n.label }}</p>
                                <button
                                    @click="toggleNotif(n)"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                    :class="notifForm[n.key] ? 'bg-green-500' : 'bg-gray-200'"
                                >
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                        :class="notifForm[n.key] ? 'translate-x-6' : 'translate-x-1'" />
                                </button>
                            </div>
                        </div>

                        <!-- Per-community -->
                        <div class="mt-4 space-y-2">
                            <div v-for="m in communityMembers" :key="m.community_id"
                                class="border border-gray-100 rounded-xl overflow-hidden">
                                <button
                                    @click="toggleCommunityNotif(m.community_id)"
                                    class="w-full flex items-center gap-3 p-4 hover:bg-gray-50 transition-colors"
                                >
                                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-600 shrink-0 overflow-hidden">
                                        <img v-if="m.avatar" :src="m.avatar" :alt="m.name" class="w-full h-full object-cover" />
                                        <span v-else>{{ m.name?.charAt(0)?.toUpperCase() }}</span>
                                    </div>
                                    <span class="flex-1 text-sm font-medium text-gray-800 text-left">{{ m.name }}</span>
                                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="openCommunityNotif === m.community_id ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div v-if="openCommunityNotif === m.community_id" class="px-4 pb-4 space-y-3 border-t border-gray-100 pt-3">
                                    <div v-for="item in communityNotifItems" :key="item.key" class="flex items-center justify-between">
                                        <p class="text-sm text-gray-600">{{ item.label }}</p>
                                        <button @click="toggleCommunityNotifItem(m, item.key)"
                                            class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
                                            :class="m.notif_prefs[item.key] ? 'bg-green-500' : 'bg-gray-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
                                                :class="m.notif_prefs[item.key] ? 'translate-x-5' : 'translate-x-0.5'" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chat -->
                <div v-else-if="activeTab === 'chat'">
                    <div class="bg-white border border-gray-200 rounded-2xl p-6 space-y-6">

                        <!-- Notifications toggle -->
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-bold text-gray-900 mb-1">Notifications</p>
                                <p class="text-sm text-gray-500">Notify me with sound and blinking tab header when somebody messages me.</p>
                            </div>
                            <button @click="toggleChat('notifications')"
                                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors mt-0.5"
                                :class="chatForm.notifications ? 'bg-green-500' : 'bg-gray-200'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                    :class="chatForm.notifications ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>

                        <!-- Email notifications toggle -->
                        <div class="flex items-start justify-between gap-4 border-t border-gray-100 pt-6">
                            <div>
                                <p class="text-sm font-bold text-gray-900 mb-1">Email notifications</p>
                                <p class="text-sm text-gray-500">If you're offline and somebody messages you, we'll let you know via email. We won't email you if you're online.</p>
                            </div>
                            <button @click="toggleChat('email_notifications')"
                                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors mt-0.5"
                                :class="chatForm.email_notifications ? 'bg-green-500' : 'bg-gray-200'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                    :class="chatForm.email_notifications ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>

                        <!-- Who can message me -->
                        <div class="border-t border-gray-100 pt-6">
                            <p class="text-sm font-bold text-gray-900 mb-1">Who can message me?</p>
                            <p class="text-sm text-gray-500 mb-4">Only members in the group you're in can message you. You choose what group users can message you from by turning your chat on/off below.</p>
                            <div class="space-y-2">
                                <div v-for="m in communityMembers" :key="m.community_id"
                                    class="flex items-center justify-between p-3 border border-gray-100 rounded-xl">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-600 shrink-0 overflow-hidden">
                                            <img v-if="m.avatar" :src="m.avatar" :alt="m.name" class="w-full h-full object-cover" />
                                            <span v-else>{{ m.name?.charAt(0)?.toUpperCase() }}</span>
                                        </div>
                                        <span class="text-sm font-medium text-gray-800">{{ m.name }}</span>
                                    </div>
                                    <button @click="toggleCommunityChat(m)"
                                        class="flex items-center gap-1.5 px-3 py-1.5 border rounded-lg text-xs font-medium transition-colors"
                                        :class="m.chat_enabled ? 'border-green-300 text-green-700 bg-green-50' : 'border-gray-200 text-gray-500 hover:bg-gray-50'">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                        {{ m.chat_enabled ? 'ON' : 'OFF' }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Blocked users -->
                        <div class="border-t border-gray-100 pt-6">
                            <p class="text-sm font-bold text-gray-900 mb-1">Blocked users</p>
                            <p class="text-sm text-gray-500">You have no blocked users.</p>
                        </div>
                    </div>
                </div>

                <!-- Payment methods -->
                <div v-else-if="activeTab === 'payment_methods'">
                    <div class="bg-white border border-gray-200 rounded-2xl p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-base font-bold text-gray-900">Payment methods</h2>
                            <button class="px-5 py-2.5 bg-amber-400 hover:bg-amber-500 text-white text-xs font-bold rounded-lg tracking-wide transition-colors">
                                ADD PAYMENT METHOD
                            </button>
                        </div>
                        <p class="text-sm text-gray-400">No cards on file</p>
                    </div>
                </div>

                <!-- Payment history -->
                <div v-else-if="activeTab === 'payment_history'">
                    <div class="bg-white border border-gray-200 rounded-2xl p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-base font-bold text-gray-900">Payment history</h2>
                            <button class="text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </button>
                        </div>
                        <p class="text-sm text-gray-400">You have no payments.</p>
                    </div>
                </div>

                <!-- Theme -->
                <div v-else-if="activeTab === 'theme'">
                    <div class="bg-white border border-gray-200 rounded-2xl p-6">
                        <h2 class="text-base font-bold text-gray-900 mb-6">Theme</h2>
                        <div class="max-w-sm space-y-4">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1.5">Theme</label>
                                <div class="relative">
                                    <select v-model="themeForm.theme"
                                        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white appearance-none pr-8">
                                        <option value="light">Light (default)</option>
                                        <option value="dark">Dark</option>
                                    </select>
                                    <svg class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </div>
                            <button @click="saveTheme" :disabled="themeForm.processing"
                                class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg tracking-wide transition-colors disabled:opacity-50">
                                SAVE
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Payout Settings -->
                <div v-else-if="activeTab === 'payouts'">
                    <div class="bg-white border border-gray-200 rounded-2xl p-6">
                        <h2 class="text-base font-bold text-gray-900 mb-1">Payout Settings</h2>
                        <p class="text-sm text-gray-400 mb-6">
                            Set where you want to receive your earnings as a community owner.
                            This applies to all communities you own.
                        </p>
                        <form @submit.prevent="savePayout" class="max-w-sm space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Payout Method</label>
                                <select v-model="payoutForm.payout_method"
                                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="gcash">GCash</option>
                                    <option value="maya">Maya</option>
                                    <option value="bank">Bank Transfer</option>
                                    <option value="paypal">PayPal</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">
                                    {{ payoutForm.payout_method === 'bank' ? 'Account Number / Bank Name' : 'Account / Mobile Number' }}
                                </label>
                                <input v-model="payoutForm.payout_details" type="text"
                                       placeholder="e.g. 09xxxxxxxxx or account number"
                                       class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <button type="submit" :disabled="payoutForm.processing"
                                    class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg transition-colors disabled:opacity-50">
                                {{ payoutForm.processing ? 'Saving...' : 'SAVE PAYOUT DETAILS' }}
                            </button>
                            <p v-if="payoutForm.recentlySuccessful" class="text-xs text-green-600 font-medium text-center">Saved!</p>
                        </form>
                    </div>
                </div>

                <!-- Placeholder tabs -->
                <div v-else>
                    <div class="bg-white border border-gray-200 rounded-2xl p-16 text-center">
                        <p class="text-sm font-medium text-gray-700 capitalize mb-1">{{ activeTab }}</p>
                        <p class="text-xs text-gray-400">Coming soon</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    tab:           String,
    profileUser:   Object,
    memberships:   Array,
    affiliateLink: String,
    timezone:      String,
    theme:         String,
    notifPrefs:    Object,
    chatPrefs:     Object,
    payoutMethod:  String,
    payoutDetails: String,
});

// Mutable copy of memberships for local toggle state
const communityMembers = reactive(props.memberships.map(m => ({ ...m })));

const activeTab = ref(props.tab ?? 'communities');

const navItems = [
    { key: 'communities',      label: 'Communities' },
    { key: 'profile',          label: 'Profile' },
    { key: 'affiliates',       label: 'Affiliates' },
    { key: 'payouts',          label: 'Payouts' },
    { key: 'account',          label: 'Account' },
    { key: 'notifications',    label: 'Notifications' },
    { key: 'chat',             label: 'Chat' },
    { key: 'payment_methods',  label: 'Payment methods' },
    { key: 'payment_history',  label: 'Payment history' },
    { key: 'theme',            label: 'Theme' },
];

// ── Affiliate ──────────────────────────────────────────────────────────────────
const copied = ref(false);
function copyLink() {
    navigator.clipboard.writeText(props.affiliateLink);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

// ── Profile form ──────────────────────────────────────────────────────────────
const avatarPreview = ref(null);

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

const profileForm = useForm({
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

// ── Account: email & password ─────────────────────────────────────────────────
const showEmailForm    = ref(false);
const showPasswordForm = ref(false);

const emailForm = useForm({ email: props.profileUser?.email ?? '' });
function saveEmail() {
    emailForm.patch('/account/settings/email', {
        preserveScroll: true,
        onSuccess: () => { showEmailForm.value = false; },
    });
}

const passwordForm = useForm({
    current_password:      '',
    password:              '',
    password_confirmation: '',
});
function savePassword() {
    passwordForm.patch('/account/settings/password', {
        preserveScroll: true,
        onSuccess: () => { showPasswordForm.value = false; passwordForm.reset(); },
    });
}

// ── Payout ────────────────────────────────────────────────────────────────────
const payoutForm = useForm({
    payout_method:  props.payoutMethod  ?? 'gcash',
    payout_details: props.payoutDetails ?? '',
});
function savePayout() {
    payoutForm.patch('/account/settings/payout', { preserveScroll: true });
}

// ── Timezone ──────────────────────────────────────────────────────────────────
const timezoneForm = useForm({ timezone: props.timezone ?? 'Asia/Manila' });
function saveTimezone() {
    timezoneForm.patch('/account/settings/timezone', { preserveScroll: true });
}

// ── Logout everywhere ─────────────────────────────────────────────────────────
function logoutEverywhere() {
    router.post('/account/settings/logout-everywhere', {}, { preserveScroll: true });
}

// ── Notifications ─────────────────────────────────────────────────────────────
const notificationToggles = [
    { key: 'follower',  label: 'New follower' },
    { key: 'likes',     label: 'Likes' },
    { key: 'kaching',   label: 'Ka-ching' },
    { key: 'affiliate', label: 'Affiliate referral' },
];
const notifForm = useForm({ ...props.notifPrefs });

function toggleNotif(n) {
    notifForm[n.key] = !notifForm[n.key];
    notifForm.patch('/account/settings/notifications', { preserveScroll: true });
}

const openCommunityNotif = ref(null);
const communityNotifItems = [
    { key: 'new_posts', label: 'New posts' },
    { key: 'comments',  label: 'Comments' },
    { key: 'mentions',  label: 'Mentions' },
];
function toggleCommunityNotif(id) {
    openCommunityNotif.value = openCommunityNotif.value === id ? null : id;
}
function toggleCommunityNotifItem(m, key) {
    m.notif_prefs[key] = !m.notif_prefs[key];
    router.patch(`/account/settings/notifications/${m.community_id}`, m.notif_prefs, { preserveScroll: true });
}

// ── Chat ──────────────────────────────────────────────────────────────────────
const chatForm = useForm({ ...props.chatPrefs });
function toggleChat(key) {
    chatForm[key] = !chatForm[key];
    chatForm.patch('/account/settings/chat', { preserveScroll: true });
}
function toggleCommunityChat(m) {
    m.chat_enabled = !m.chat_enabled;
    router.patch(`/account/settings/chat/${m.community_id}`, { chat_enabled: m.chat_enabled }, { preserveScroll: true });
}

// ── Theme ─────────────────────────────────────────────────────────────────────
const themeForm = useForm({ theme: props.theme ?? 'light' });
function saveTheme() {
    themeForm.patch('/account/settings/theme', { preserveScroll: true });
}
</script>
