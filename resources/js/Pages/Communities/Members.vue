<template>
    <AppLayout :title="`${community.name} · Members`" :community="community">
        <CommunityTabs :community="community" active-tab="members" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ── Main column ─────────────────────────────────────────── -->
            <div class="lg:col-span-2 min-w-0">

                <!-- Filter tabs + Invite -->
                <div class="flex items-center justify-between mb-5">
                    <div class="flex gap-2 flex-wrap">
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
                        <template v-if="isOwner">
                            <Link
                                :href="`/communities/${community.slug}/members?filter=paid`"
                                class="px-4 py-1.5 text-sm rounded-full font-medium border transition-colors"
                                :class="currentFilter === 'paid'
                                    ? 'bg-gray-900 text-white border-gray-900'
                                    : 'bg-white text-gray-600 border-gray-200 hover:border-gray-400'"
                            >
                                Paid <span class="ml-1 opacity-70">{{ paidCount }}</span>
                            </Link>
                            <Link
                                :href="`/communities/${community.slug}/members?filter=free`"
                                class="px-4 py-1.5 text-sm rounded-full font-medium border transition-colors"
                                :class="currentFilter === 'free'
                                    ? 'bg-green-700 text-white border-green-700'
                                    : 'bg-white text-green-700 border-green-300 hover:border-green-500'"
                            >
                                Free <span class="ml-1 opacity-70">{{ freeCount }}</span>
                            </Link>
                        </template>
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

                <!-- Batch action bar (owner only, members selected) -->
                <div v-if="isOwner && selectedIds.length > 0"
                    class="flex items-center gap-3 bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3 mb-4 flex-wrap">
                    <span class="text-sm font-medium text-indigo-800">{{ selectedIds.length }} selected</span>
                    <div class="flex-1" />

                    <!-- Tag assign -->
                    <button @click="showTagAssignModal = true"
                        class="px-4 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        Apply Tags
                    </button>

                    <!-- Extend access -->
                    <label class="text-xs font-medium text-indigo-700">Extend by</label>
                    <select v-model="extendMonths"
                        class="text-xs border border-indigo-300 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option :value="1">1 month</option>
                        <option :value="3">3 months</option>
                        <option :value="6">6 months</option>
                        <option :value="12">12 months</option>
                        <option :value="24">24 months</option>
                    </select>
                    <button @click="extendAccess" :disabled="extendForm.processing"
                        class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg transition-colors disabled:opacity-50">
                        {{ extendForm.processing ? 'Extending…' : 'Extend access' }}
                    </button>
                    <button @click="selectedIds = []" class="text-xs text-gray-400 hover:text-gray-600">Clear</button>
                </div>

                <!-- Member list -->
                <div class="divide-y divide-gray-100 bg-white border border-gray-200 rounded-2xl overflow-hidden">
                    <!-- Select all (owner only) -->
                    <div v-if="isOwner" class="flex items-center gap-3 px-5 py-2.5 bg-gray-50 border-b border-gray-100">
                        <input type="checkbox" :checked="allSelected" @change="toggleSelectAll"
                            class="w-4 h-4 accent-indigo-600 rounded cursor-pointer" />
                        <span class="text-xs text-gray-500">Select all on this page</span>
                    </div>
                    <div
                        v-for="member in members.data"
                        :key="member.id"
                        class="flex items-start gap-4 px-5 py-4 hover:bg-gray-50 transition-colors"
                    >
                        <!-- Checkbox (owner only) -->
                        <div v-if="isOwner" class="shrink-0 mt-1">
                            <input type="checkbox" :value="member.id" v-model="selectedIds"
                                class="w-4 h-4 accent-indigo-600 rounded cursor-pointer" />
                        </div>
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

                            <!-- Tags -->
                            <div v-if="member.tags?.length" class="flex flex-wrap gap-1 mb-2">
                                <span
                                    v-for="tag in member.tags"
                                    :key="tag.id"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium"
                                    :style="tagStyle(tag)"
                                >
                                    {{ tag.name }}
                                    <button
                                        v-if="isOwner"
                                        @click.stop="removeTagFromMember(member, tag)"
                                        class="ml-0.5 opacity-60 hover:opacity-100 transition-opacity"
                                        title="Remove tag"
                                    >
                                        &times;
                                    </button>
                                </span>
                            </div>

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
                                <template v-if="isOwner && member.membership_type === 'free'">
                                    <span class="text-gray-200">·</span>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Free</span>
                                    <span v-if="member.expires_at" class="text-gray-400">
                                        · expires {{ formatDate(member.expires_at) }}
                                    </span>
                                    <span v-else class="text-gray-400">· no expiry</span>
                                </template>
                            </div>

                            <!-- Actions (below info on all screen sizes) -->
                            <div class="flex items-center gap-2 mt-2 flex-wrap">
                                <Link
                                    v-if="$page.props.auth?.user && member.user?.id !== $page.props.auth.user.id && member.user?.username"
                                    :href="`/communities/${community.slug}/chat?tab=personal&user=${member.user.id}`"
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-full text-gray-500 hover:border-indigo-300 hover:text-indigo-600 transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                    Chat
                                </Link>
                                <!-- Quick tag button (owner only) -->
                                <button
                                    v-if="isOwner && tags.length"
                                    @click="openQuickTag(member)"
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-full text-gray-500 hover:border-purple-300 hover:text-purple-600 transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    Tag
                                </button>
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
                                        @click="toggleBlock(member)"
                                        class="text-xs font-medium transition-colors"
                                        :class="member.is_blocked ? 'text-amber-500 hover:text-amber-700' : 'text-gray-400 hover:text-amber-500'"
                                    >
                                        {{ member.is_blocked ? '🔓 Unblock' : '🚫 Block' }}
                                    </button>
                                    <button
                                        @click="removeMember(member)"
                                        class="text-xs text-gray-400 hover:text-red-500 transition-colors"
                                    >
                                        Remove
                                    </button>
                                </template>
                                <!-- Blocked badge visible to admin -->
                                <span v-if="isAdmin && member.is_blocked" class="text-[10px] font-bold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Blocked</span>
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
            <div class="space-y-4">
                <CommunitySidebarCard
                    :community="community"
                    :members-count="totalCount"
                    :admin-count="adminCount"
                >
                    <button
                        v-if="$page.props.auth?.user"
                        @click="showInviteModal = true"
                        class="w-full py-2 text-sm font-semibold border border-gray-300 dark:border-gray-600 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        Invite People
                    </button>
                </CommunitySidebarCard>

                <!-- Tag Management (owner only) -->
                <div v-if="isOwner" class="bg-white border border-gray-200 rounded-2xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-900">Tags</h3>
                        <button @click="showCreateTagModal = true" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ New Tag</button>
                    </div>

                    <div v-if="tags.length" class="space-y-1.5">
                        <div
                            v-for="tag in tags"
                            :key="tag.id"
                            class="flex items-center justify-between group px-3 py-2 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: tag.color || '#6366f1' }"></span>
                                <span class="text-sm text-gray-700">{{ tag.name }}</span>
                                <span class="text-xs text-gray-400">{{ tag.members_count ?? 0 }}</span>
                            </div>
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="editTag(tag)" class="text-xs text-gray-400 hover:text-indigo-600">Edit</button>
                                <button @click="deleteTag(tag)" class="text-xs text-gray-400 hover:text-red-500">Delete</button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-xs text-gray-400 text-center py-4">No tags yet. Create one to start organizing members.</p>
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
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="font-semibold text-gray-900 text-base">Send SMS Blast</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Only members with a phone number will receive this.</p>
                        </div>
                        <button @click="showSmsModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="sendSmsBlast" class="space-y-4">

                        <!-- Audience Filter -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Audience</label>
                            <div class="space-y-2">
                                <!-- All members -->
                                <label class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer transition-colors"
                                    :class="smsFilter === 'all' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'">
                                    <input type="radio" v-model="smsFilter" value="all" class="accent-indigo-600" />
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">All members</p>
                                        <p class="text-xs text-gray-400">Everyone in the community with a phone number</p>
                                    </div>
                                </label>

                                <!-- New members -->
                                <label class="flex items-start gap-3 p-3 border rounded-xl cursor-pointer transition-colors"
                                    :class="smsFilter === 'new_members' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'">
                                    <input type="radio" v-model="smsFilter" value="new_members" class="accent-indigo-600 mt-0.5" />
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-800">New members</p>
                                        <p class="text-xs text-gray-400 mb-2">Recently joined — great for coaching call follow-ups</p>
                                        <div v-if="smsFilter === 'new_members'" class="flex gap-2">
                                            <button
                                                v-for="d in [7, 14, 30]" :key="d"
                                                type="button"
                                                @click="smsFilterDays = d"
                                                class="px-3 py-1 text-xs rounded-lg border transition-colors"
                                                :class="smsFilterDays === d ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 text-gray-600 hover:border-indigo-300'"
                                            >
                                                Last {{ d }} days
                                            </button>
                                        </div>
                                    </div>
                                </label>

                                <!-- Course enrollees -->
                                <label v-if="courses.length" class="flex items-start gap-3 p-3 border rounded-xl cursor-pointer transition-colors"
                                    :class="smsFilter === 'course' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'">
                                    <input type="radio" v-model="smsFilter" value="course" class="accent-indigo-600 mt-0.5" />
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-800">Course enrollees</p>
                                        <p class="text-xs text-gray-400 mb-2">Members who paid for a specific course</p>
                                        <select
                                            v-if="smsFilter === 'course'"
                                            v-model="smsFilterCourseId"
                                            @click.stop
                                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                                        >
                                            <option value="">— Select a course —</option>
                                            <option v-for="c in courses" :key="c.id" :value="c.id">
                                                {{ c.title }}
                                                <template v-if="c.access_type === 'free'"> (Free)</template>
                                                <template v-else-if="c.access_type === 'inclusive'"> (Included)</template>
                                                <template v-else> (Paid)</template>
                                            </option>
                                        </select>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Message -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Message</label>
                            <textarea
                                v-model="smsBlastMessage"
                                rows="4"
                                maxlength="1600"
                                placeholder="e.g. Hi! You're invited to our Zoom call this Saturday at 10am. Join here: [link]"
                                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                            />
                            <div class="flex justify-between mt-1">
                                <p class="text-xs text-gray-400">{{ Math.ceil(smsBlastMessage.length / 160) > 1 ? `${Math.ceil(smsBlastMessage.length / 160)} SMS parts` : '1 SMS part' }}</p>
                                <p class="text-xs text-gray-400">{{ smsBlastMessage.length }} / 1600</p>
                            </div>
                        </div>

                        <!-- Provider badge -->
                        <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg">
                            <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-xs text-gray-500">via <strong>{{ smsProviderLabel }}</strong></span>
                            <Link :href="`/communities/${community.slug}/settings`" class="ml-auto text-xs text-indigo-500 hover:underline">Change</Link>
                        </div>

                        <p v-if="smsBlastError" class="text-sm text-red-600">{{ smsBlastError }}</p>

                        <div class="flex gap-3 pt-1">
                            <button
                                type="button"
                                @click="showSmsModal = false"
                                class="flex-1 px-4 py-2.5 border border-gray-300 text-sm font-medium rounded-xl text-gray-700 hover:bg-gray-50 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="smsSending || !smsBlastMessage.trim() || (smsFilter === 'course' && !smsFilterCourseId)"
                                class="flex-1 px-4 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold rounded-xl transition-colors disabled:opacity-50"
                            >
                                {{ smsSending ? 'Sending…' : 'Send SMS' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Create / Edit Tag Modal -->
        <Teleport to="body">
            <div
                v-if="showCreateTagModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="showCreateTagModal = false"
            >
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
                    <h3 class="font-semibold text-gray-900 text-base mb-4">{{ editingTag ? 'Edit Tag' : 'Create Tag' }}</h3>
                    <form @submit.prevent="saveTag" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input
                                v-model="tagForm.name"
                                type="text"
                                maxlength="100"
                                placeholder="e.g. LEAD, VIP, Buyer"
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                            <div class="flex gap-2 flex-wrap">
                                <button
                                    v-for="c in TAG_COLORS"
                                    :key="c"
                                    type="button"
                                    @click="tagForm.color = c"
                                    class="w-7 h-7 rounded-full border-2 transition-all"
                                    :class="tagForm.color === c ? 'border-gray-900 scale-110' : 'border-transparent'"
                                    :style="{ backgroundColor: c }"
                                />
                            </div>
                        </div>
                        <p v-if="tagFormError" class="text-sm text-red-600">{{ tagFormError }}</p>
                        <div class="flex gap-3 pt-1">
                            <button type="button" @click="showCreateTagModal = false"
                                class="flex-1 px-4 py-2.5 border border-gray-300 text-sm font-medium rounded-xl text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" :disabled="tagFormSaving || !tagForm.name.trim()"
                                class="flex-1 px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold rounded-xl transition-colors disabled:opacity-50">
                                {{ tagFormSaving ? 'Saving…' : editingTag ? 'Update' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Bulk Tag Assign Modal -->
        <Teleport to="body">
            <div
                v-if="showTagAssignModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="showTagAssignModal = false"
            >
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
                    <h3 class="font-semibold text-gray-900 text-base mb-1">Apply Tags</h3>
                    <p class="text-xs text-gray-400 mb-4">{{ selectedIds.length }} member(s) selected</p>

                    <div v-if="tags.length" class="space-y-2 max-h-60 overflow-y-auto mb-4">
                        <label
                            v-for="tag in tags"
                            :key="tag.id"
                            class="flex items-center gap-3 px-3 py-2.5 border rounded-xl cursor-pointer transition-colors"
                            :class="bulkTagIds.includes(tag.id) ? 'border-purple-400 bg-purple-50' : 'border-gray-200 hover:border-gray-300'"
                        >
                            <input type="checkbox" :value="tag.id" v-model="bulkTagIds" class="accent-purple-600" />
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: tag.color || '#6366f1' }"></span>
                            <span class="text-sm text-gray-700">{{ tag.name }}</span>
                        </label>
                    </div>
                    <div v-else class="text-center py-6">
                        <p class="text-sm text-gray-400 mb-2">No tags yet.</p>
                        <button @click="showTagAssignModal = false; showCreateTagModal = true"
                            class="text-sm text-purple-600 hover:text-purple-800 font-medium">Create your first tag</button>
                    </div>

                    <div v-if="tags.length" class="flex gap-3">
                        <button @click="showTagAssignModal = false"
                            class="flex-1 px-4 py-2.5 border border-gray-300 text-sm font-medium rounded-xl text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button @click="bulkAssignTags('detach')" :disabled="!bulkTagIds.length || bulkAssigning"
                            class="px-4 py-2.5 border border-red-200 text-sm font-medium rounded-xl text-red-600 hover:bg-red-50 transition-colors disabled:opacity-50">
                            Remove
                        </button>
                        <button @click="bulkAssignTags('attach')" :disabled="!bulkTagIds.length || bulkAssigning"
                            class="flex-1 px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold rounded-xl transition-colors disabled:opacity-50">
                            {{ bulkAssigning ? 'Applying…' : 'Apply' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Quick Tag Dropdown (per-member) -->
        <Teleport to="body">
            <div
                v-if="quickTagMember"
                class="fixed inset-0 z-50"
                @click="quickTagMember = null"
            >
                <div
                    class="absolute bg-white rounded-xl shadow-xl border border-gray-200 w-56 p-3"
                    :style="quickTagPos"
                    @click.stop
                >
                    <p class="text-xs font-semibold text-gray-500 mb-2">Tag {{ quickTagMember.user?.name }}</p>
                    <div class="space-y-1 max-h-48 overflow-y-auto">
                        <label
                            v-for="tag in tags"
                            :key="tag.id"
                            class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-50 cursor-pointer text-sm"
                        >
                            <input
                                type="checkbox"
                                :checked="quickTagMember.tags?.some(t => t.id === tag.id)"
                                @change="toggleQuickTag(quickTagMember, tag, $event.target.checked)"
                                class="accent-purple-600 w-3.5 h-3.5"
                            />
                            <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: tag.color || '#6366f1' }"></span>
                            <span class="text-gray-700">{{ tag.name }}</span>
                        </label>
                    </div>
                    <button
                        @click="quickTagMember = null; showCreateTagModal = true"
                        class="mt-2 w-full text-xs text-center text-purple-600 hover:text-purple-800 font-medium py-1"
                    >
                        + Create new tag
                    </button>
                </div>
            </div>
        </Teleport>

        <ConfirmModal :show="confirmShow" :title="confirmTitle" :message="confirmMessage" :confirm-label="confirmLabel" :destructive="confirmDestructive" @confirm="onConfirm" @cancel="onCancel" />
    </AppLayout>
</template>

<script setup>
import { computed, ref, reactive } from 'vue';
import { Link, usePage, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import CommunitySidebarCard from '@/Components/CommunitySidebarCard.vue';
import InviteModal from '@/Components/InviteModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useConfirm } from '@/composables/useConfirm';

const { show: confirmShow, title: confirmTitle, message: confirmMessage, confirmLabel, destructive: confirmDestructive, ask, onConfirm, onCancel } = useConfirm();

const props = defineProps({
    community:  Object,
    members:    Object,
    totalCount: Number,
    adminCount: Number,
    freeCount:  { type: Number, default: 0 },
    paidCount:  { type: Number, default: 0 },
    affiliate:  Object,
    courses:    { type: Array, default: () => [] },
    tags:       { type: Array, default: () => [] },
});

const page = usePage();

const currentFilter = computed(() => {
    const url = new URL(window.location.href);
    return url.searchParams.get('filter') ?? null;
});

const currentUserId = computed(() => page.props.auth?.user?.id);

const showInviteModal = ref(false);

// SMS blast
const showSmsModal      = ref(false);
const smsBlastMessage   = ref('');
const smsSending        = ref(false);
const smsBlastError     = ref('');
const smsFilter         = ref('all');
const smsFilterDays     = ref(7);
const smsFilterCourseId = ref('');

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
    smsSending.value    = true;
    smsBlastError.value = '';

    const payload = {
        message:     smsBlastMessage.value,
        filter_type: smsFilter.value,
    };
    if (smsFilter.value === 'new_members') payload.filter_days = smsFilterDays.value;
    if (smsFilter.value === 'course')      payload.filter_course_id = smsFilterCourseId.value;

    router.post(`/communities/${props.community.slug}/sms-blast`, payload, {
        preserveScroll: true,
        onSuccess: () => {
            showSmsModal.value      = false;
            smsBlastMessage.value   = '';
            smsFilter.value         = 'all';
            smsFilterDays.value     = 7;
            smsFilterCourseId.value = '';
        },
        onError: (errors) => {
            smsBlastError.value = errors.message ?? 'Something went wrong.';
        },
        onFinish: () => { smsSending.value = false; },
    });
}

const inviteUrl = computed(() =>
    props.affiliate?.code
        ? `${window.location.origin}/ref/${props.affiliate.code}`
        : `${window.location.origin}/communities/${props.community.slug}`
);

const isOwner = computed(() => currentUserId.value === props.community.owner_id);

// ── Extend free access ────────────────────────────────────────────────────────
const selectedIds   = ref([]);
const extendMonths  = ref(3);
const extendForm    = useForm({});

const allSelected = computed(() =>
    props.members.data.length > 0 && props.members.data.every(m => selectedIds.value.includes(m.id))
);

function toggleSelectAll() {
    if (allSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.members.data.map(m => m.id).filter(Boolean);
    }
}

function extendAccess() {
    extendForm.transform(() => ({
        user_ids: selectedIds.value.map(memberId => {
            const member = props.members.data.find(m => m.id === memberId);
            return member?.user?.id;
        }).filter(Boolean),
        months: extendMonths.value,
    })).patch(`/communities/${props.community.slug}/members/extend-access`, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = []; },
    });
}

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

async function toggleBlock(member) {
    const action = member.is_blocked ? 'unblock' : 'block';
    const confirmed = await ask({
        title: action === 'block' ? 'Block member' : 'Unblock member',
        message: action === 'block'
            ? `Block ${member.user?.name}? They will not be able to post, comment, or chat.`
            : `Unblock ${member.user?.name}? They will regain posting and chat access.`,
        confirmLabel: action === 'block' ? 'Block' : 'Unblock',
        destructive: action === 'block',
    });
    if (!confirmed) return;
    router.patch(
        `/communities/${props.community.slug}/members/${member.user.id}/block`,
        {},
        { preserveScroll: true },
    );
}

async function removeMember(member) {
    const confirmed = await ask({
        title: 'Remove member',
        message: `Remove ${member.user?.name} from the community?`,
        confirmLabel: 'Remove',
        destructive: true,
    });
    if (!confirmed) return;
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

// ── Tag Management ────────────────────────────────────────────────────────────
const TAG_COLORS = ['#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f59e0b', '#10b981', '#06b6d4', '#3b82f6', '#64748b'];

const showCreateTagModal = ref(false);
const editingTag         = ref(null);
const tagForm            = reactive({ name: '', color: '#6366f1' });
const tagFormSaving      = ref(false);
const tagFormError       = ref('');

function editTag(tag) {
    editingTag.value  = tag;
    tagForm.name      = tag.name;
    tagForm.color     = tag.color || '#6366f1';
    tagFormError.value = '';
    showCreateTagModal.value = true;
}

function saveTag() {
    tagFormSaving.value = true;
    tagFormError.value  = '';

    const url = editingTag.value
        ? `/communities/${props.community.slug}/tags/${editingTag.value.id}`
        : `/communities/${props.community.slug}/tags`;

    const method = editingTag.value ? 'patch' : 'post';

    router[method](url, {
        name:  tagForm.name,
        color: tagForm.color,
        type:  'manual',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateTagModal.value = false;
            editingTag.value = null;
            tagForm.name  = '';
            tagForm.color = '#6366f1';
        },
        onError: (errors) => {
            tagFormError.value = errors.name ?? 'Something went wrong.';
        },
        onFinish: () => { tagFormSaving.value = false; },
    });
}

async function deleteTag(tag) {
    const confirmed = await ask({
        title: 'Delete tag',
        message: `Delete tag "${tag.name}"? It will be removed from all members.`,
        confirmLabel: 'Delete',
        destructive: true,
    });
    if (!confirmed) return;
    router.delete(`/communities/${props.community.slug}/tags/${tag.id}`, {
        preserveScroll: true,
    });
}

// ── Bulk Tag Assign ───────────────────────────────────────────────────────────
const showTagAssignModal = ref(false);
const bulkTagIds         = ref([]);
const bulkAssigning      = ref(false);

function bulkAssignTags(action) {
    bulkAssigning.value = true;
    router.post(`/communities/${props.community.slug}/tags/assign`, {
        member_ids: selectedIds.value,
        tag_ids:    bulkTagIds.value,
        action,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showTagAssignModal.value = false;
            bulkTagIds.value = [];
            selectedIds.value = [];
        },
        onFinish: () => { bulkAssigning.value = false; },
    });
}

// ── Per-member tag removal ────────────────────────────────────────────────────
function removeTagFromMember(member, tag) {
    router.post(`/communities/${props.community.slug}/tags/assign`, {
        member_ids: [member.id],
        tag_ids:    [tag.id],
        action:     'detach',
    }, { preserveScroll: true });
}

// ── Quick Tag (per-member dropdown) ───────────────────────────────────────────
const quickTagMember = ref(null);
const quickTagPos    = ref({});

function openQuickTag(member) {
    quickTagMember.value = member;
    // Position near the center of the viewport
    quickTagPos.value = { top: '30%', left: '50%', transform: 'translate(-50%, 0)' };
}

function toggleQuickTag(member, tag, checked) {
    router.post(`/communities/${props.community.slug}/tags/assign`, {
        member_ids: [member.id],
        tag_ids:    [tag.id],
        action:     checked ? 'attach' : 'detach',
    }, { preserveScroll: true });
}

// ── Tag style helper ──────────────────────────────────────────────────────────
function tagStyle(tag) {
    const color = tag.color || '#6366f1';
    return {
        backgroundColor: color + '1A', // ~10% opacity
        color: color,
        border: `1px solid ${color}33`,
    };
}
</script>
