<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <!-- Sticky Navbar -->
        <nav class="sticky top-0 z-40 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-14 items-center">

                    <!-- Logo / Community context + Switcher -->
                    <div class="flex items-center gap-1">
                        <!-- Community context (when inside a community) -->
                        <template v-if="props.community">
                            <Link :href="`/communities/${props.community.slug}`" class="flex items-center gap-2 px-1 py-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <div class="w-7 h-7 rounded-lg bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600 overflow-hidden shrink-0">
                                    <img v-if="props.community.avatar" :src="props.community.avatar" :alt="props.community.name" class="w-full h-full object-cover"/>
                                    <span v-else>{{ props.community.name.charAt(0).toUpperCase() }}</span>
                                </div>
                                <span class="text-sm font-bold text-gray-900 dark:text-gray-100 max-w-40 truncate">{{ props.community.name }}</span>
                            </Link>
                        </template>
                        <!-- Default: app logo → home -->
                        <template v-else>
                            <Link href="/communities" class="px-1 py-1.5">
                                <img
                                    :src="'/brand/logo-transparent.png'"
                                    alt="Curzzo"
                                    class="h-12 w-auto"
                                />
                            </Link>
                        </template>

                        <!-- Dropdown toggle (always visible) -->
                        <div class="relative" ref="switcherRef">
                        <button
                            @click="switcherOpen = !switcherOpen"
                            class="flex items-center justify-center w-7 h-7 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300"
                            title="Switch community"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4M16 15l-4 4-4-4"/>
                            </svg>
                        </button>

                        <!-- Community Switcher Dropdown -->
                        <Transition
                            enter-active-class="transition ease-out duration-150"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition ease-in duration-100"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95"
                        >
                            <div
                                v-if="switcherOpen"
                                class="absolute left-0 mt-1.5 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-xl overflow-hidden origin-top-left z-50"
                            >
                                <!-- Search -->
                                <div class="p-2 border-b border-gray-100 dark:border-gray-700">
                                    <div class="relative">
                                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                                        </svg>
                                        <input
                                            v-model="switcherSearch"
                                            type="text"
                                            placeholder="Search"
                                            class="w-full pl-8 pr-3 py-1.5 text-sm bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        />
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="p-1.5 border-b border-gray-100 dark:border-gray-700">
                                    <button
                                        v-if="$page.props.auth?.user"
                                        @click="openCreate"
                                        class="flex items-center gap-2.5 w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                    >
                                        <span class="w-6 h-6 rounded-md bg-indigo-100 flex items-center justify-center shrink-0">
                                            <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                            </svg>
                                        </span>
                                        <span class="font-medium">Create a community</span>
                                    </button>
                                    <Link
                                        href="/communities"
                                        @click="switcherOpen = false"
                                        class="flex items-center gap-2.5 w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                    >
                                        <span class="w-6 h-6 rounded-md bg-gray-100 flex items-center justify-center shrink-0">
                                            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                                            </svg>
                                        </span>
                                        <span class="font-medium">Discover communities</span>
                                    </Link>
                                </div>

                                <!-- User's communities -->
                                <div class="max-h-60 overflow-y-auto">
                                    <template v-if="filteredSwitcherCommunities.length">
                                        <Link
                                            v-for="community in filteredSwitcherCommunities"
                                            :key="community.id"
                                            :href="`/communities/${community.slug}`"
                                            @click="switcherOpen = false"
                                            class="flex items-center gap-2.5 w-full px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                            :class="$page.url === `/communities/${community.slug}` ? 'bg-indigo-50 dark:bg-indigo-900/30' : ''"
                                        >
                                            <div class="w-7 h-7 rounded-lg bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600 shrink-0 overflow-hidden">
                                                <img v-if="community.avatar" :src="community.avatar" :alt="community.name" class="w-full h-full object-cover"/>
                                                <span v-else>{{ community.name.charAt(0).toUpperCase() }}</span>
                                            </div>
                                            <span class="text-gray-800 dark:text-gray-200 font-medium truncate">{{ community.name }}</span>
                                            <svg v-if="$page.url === `/communities/${community.slug}`" class="w-3.5 h-3.5 text-indigo-600 ml-auto shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </Link>
                                    </template>
                                    <p v-else-if="switcherSearch" class="px-4 py-3 text-xs text-gray-400 text-center">
                                        No communities found
                                    </p>
                                </div>
                            </div>
                        </Transition>
                        </div><!-- end switcherRef -->

                        <!-- AI Assistant button -->
                        <button
                            v-if="($page.props.auth?.communities ?? []).length > 0"
                            @click="aiOpen = !aiOpen"
                            class="flex items-center gap-1 ml-1 px-2 py-1 rounded-lg text-xs font-semibold transition-colors"
                            :class="aiOpen
                                ? 'bg-indigo-100 text-indigo-700 border border-indigo-300'
                                : 'text-gray-500 hover:bg-indigo-50 hover:text-indigo-600 border border-transparent'"
                            title="AI Assistant"
                        >
                            <div class="w-5 h-5 rounded-full overflow-hidden shrink-0 bg-gray-100 ring-1 ring-indigo-200">
                                <img :src="curzzoIcon" alt="Curzzo" class="w-full h-full object-cover" />
                            </div>
                            <span class="hidden sm:inline">Curzzo</span>
                        </button>
                    </div><!-- end logo+switcher flex -->

                    <!-- Right: auth -->
                    <div class="flex items-center gap-2">
                        <template v-if="$page.props.auth?.user">

                            <!-- Direct messages dropdown -->
                            <div class="relative" ref="dmRef">
                                <button
                                    @click="toggleDm"
                                    class="relative flex items-center justify-center w-8 h-8 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-gray-500 dark:text-gray-400"
                                    title="Messages"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                    <span
                                        v-if="$page.props.unread_dms > 0"
                                        class="absolute -top-0.5 -right-0.5 min-w-4 h-4 px-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center leading-none"
                                    >
                                        {{ $page.props.unread_dms > 99 ? '99+' : $page.props.unread_dms }}
                                    </span>
                                </button>

                                <!-- DM panel -->
                                <Transition
                                    enter-active-class="transition ease-out duration-100"
                                    enter-from-class="opacity-0 scale-95"
                                    enter-to-class="opacity-100 scale-100"
                                    leave-active-class="transition ease-in duration-75"
                                    leave-from-class="opacity-100 scale-100"
                                    leave-to-class="opacity-0 scale-95"
                                >
                                    <div
                                        v-if="dmOpen"
                                        class="absolute right-0 mt-1.5 w-80 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-xl overflow-hidden origin-top-right z-50 flex flex-col"
                                        style="max-height: 480px;"
                                    >
                                        <!-- Header -->
                                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between shrink-0">
                                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Chats</p>
                                        </div>

                                        <!-- Search -->
                                        <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-700 shrink-0">
                                            <div class="relative">
                                                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                                                </svg>
                                                <input
                                                    v-model="dmSearch"
                                                    type="text"
                                                    placeholder="Search users"
                                                    class="w-full pl-8 pr-3 py-1.5 text-sm bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                    @input="searchUsers"
                                                />
                                            </div>
                                        </div>

                                        <!-- Search results -->
                                        <div v-if="dmSearch && dmSearchResults.length" class="overflow-y-auto">
                                            <Link
                                                v-for="user in dmSearchResults"
                                                :key="user.id"
                                                :href="`/messages/${user.username ?? user.id}`"
                                                @click="dmOpen = false; dmSearch = ''"
                                                class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                            >
                                                <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-600 shrink-0">
                                                    {{ user.name?.charAt(0)?.toUpperCase() }}
                                                </div>
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ user.name }}</p>
                                                    <p class="text-xs text-gray-400">@{{ user.username }}</p>
                                                </div>
                                            </Link>
                                        </div>

                                        <!-- No search results -->
                                        <div v-else-if="dmSearch && !dmSearchResults.length" class="px-4 py-6 text-center">
                                            <p class="text-sm text-gray-400">No users found</p>
                                        </div>

                                        <!-- Conversations list -->
                                        <div v-else class="overflow-y-auto flex-1">
                                            <div v-if="dmLoading" class="px-4 py-8 text-center">
                                                <p class="text-sm text-gray-400">Loading…</p>
                                            </div>
                                            <div v-else-if="!dmConversations.length" class="px-4 py-10 text-center">
                                                <p class="text-sm text-gray-500 font-medium">No chats yet</p>
                                                <p class="text-xs text-gray-400 mt-1">Search for a member to start a conversation</p>
                                            </div>
                                            <Link
                                                v-for="conv in dmConversations"
                                                :key="conv.user?.id"
                                                :href="`/messages/${conv.user?.username ?? conv.user?.id}`"
                                                @click="dmOpen = false"
                                                class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border-b border-gray-50 dark:border-gray-700 last:border-0"
                                            >
                                                <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-600 shrink-0">
                                                    {{ conv.user?.name?.charAt(0)?.toUpperCase() }}
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center justify-between">
                                                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate">{{ conv.user?.name }}</p>
                                                        <p class="text-[10px] text-gray-400 shrink-0 ml-1">{{ dmFormatTime(conv.latest_message?.created_at) }}</p>
                                                    </div>
                                                    <p class="text-xs text-gray-500 truncate">
                                                        <span v-if="conv.latest_message?.is_mine" class="text-gray-400">You: </span>
                                                        {{ conv.latest_message?.content ?? '' }}
                                                    </p>
                                                </div>
                                                <span v-if="conv.unread_count > 0" class="shrink-0 min-w-4 h-4 px-0.5 bg-indigo-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                                                    {{ conv.unread_count }}
                                                </span>
                                            </Link>
                                        </div>
                                    </div>
                                </Transition>
                            </div>

                            <!-- Notification bell -->
                            <div class="relative" ref="notifRef">
                                <button
                                    @click="toggleNotifications"
                                    class="relative flex items-center justify-center w-8 h-8 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-gray-500 dark:text-gray-400"
                                    title="Notifications"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                    </svg>
                                    <span
                                        v-if="$page.props.unread_notifications > 0"
                                        class="absolute -top-0.5 -right-0.5 min-w-4 h-4 px-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center leading-none"
                                    >
                                        {{ $page.props.unread_notifications > 99 ? '99+' : $page.props.unread_notifications }}
                                    </span>
                                </button>

                                <!-- Notification dropdown -->
                                <Transition
                                    enter-active-class="transition ease-out duration-100"
                                    enter-from-class="opacity-0 scale-95"
                                    enter-to-class="opacity-100 scale-100"
                                    leave-active-class="transition ease-in duration-75"
                                    leave-from-class="opacity-100 scale-100"
                                    leave-to-class="opacity-0 scale-95"
                                >
                                    <div
                                        v-if="notifOpen"
                                        class="absolute right-0 mt-1.5 w-80 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-xl overflow-hidden origin-top-right z-50"
                                        style="max-height: 440px;"
                                    >
                                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between shrink-0">
                                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Notifications</p>
                                            <button v-if="$page.props.unread_notifications > 0"
                                                @click="markAllRead"
                                                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                                Mark all read
                                            </button>
                                        </div>
                                        <div class="overflow-y-auto" style="max-height: 380px;">
                                            <div v-if="notifLoading" class="px-4 py-8 text-center">
                                                <p class="text-sm text-gray-400">Loading...</p>
                                            </div>
                                            <template v-else-if="notifications.length">
                                                <div
                                                    v-for="n in notifications"
                                                    :key="n.id"
                                                    class="flex items-start gap-3 px-4 py-3 border-b border-gray-50 dark:border-gray-700/50 transition-colors"
                                                    :class="!n.read_at ? 'bg-indigo-50/50 dark:bg-indigo-900/10' : 'hover:bg-gray-50 dark:hover:bg-gray-700/30'"
                                                >
                                                    <!-- Icon -->
                                                    <div class="w-8 h-8 rounded-full shrink-0 flex items-center justify-center text-sm"
                                                        :class="n.type === 'new_post' ? 'bg-indigo-100 dark:bg-indigo-900/40' : n.type === 'milestone' ? 'bg-yellow-100 dark:bg-yellow-900/40' : 'bg-green-100 dark:bg-green-900/40'">
                                                        {{ n.type === 'new_post' ? '✍️' : n.type === 'milestone' ? '🏆' : '👋' }}
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-snug">{{ n.data?.message }}</p>
                                                        <p class="text-xs text-gray-400 mt-0.5">
                                                            <span v-if="n.community_slug">
                                                                <a :href="`/communities/${n.community_slug}`" class="text-indigo-500 hover:underline">{{ n.community_name }}</a>
                                                                ·
                                                            </span>
                                                            {{ relativeTime(n.created_at) }}
                                                        </p>
                                                    </div>
                                                    <span v-if="!n.read_at" class="w-2 h-2 rounded-full bg-indigo-500 shrink-0 mt-1.5" />
                                                </div>
                                            </template>
                                            <div v-else class="px-4 py-10 text-center">
                                                <p class="text-sm text-gray-400">All caught up!</p>
                                            </div>
                                        </div>
                                    </div>
                                </Transition>
                            </div>

                            <!-- User dropdown -->
                            <div class="relative" ref="menuRef">
                                <button
                                    @click="menuOpen = !menuOpen"
                                    class="flex items-center gap-2 pl-1 pr-2 py-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                >
                                    <span class="w-7 h-7 rounded-full bg-linear-to-br from-indigo-400 to-purple-500 flex items-center justify-center font-semibold text-white text-xs shrink-0 overflow-hidden">
                                        <img v-if="$page.props.auth.user.avatar" :src="$page.props.auth.user.avatar" :alt="$page.props.auth.user.name" class="w-full h-full object-cover" />
                                        <template v-else>{{ initials }}</template>
                                    </span>
                                    <span class="hidden sm:block text-sm font-medium text-gray-700 dark:text-gray-300 max-w-28 truncate">
                                        {{ $page.props.auth.user.name }}
                                    </span>
                                    <svg class="w-3.5 h-3.5 text-gray-400 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>

                                <!-- Dropdown -->
                                <Transition
                                    enter-active-class="transition ease-out duration-100"
                                    enter-from-class="opacity-0 scale-95"
                                    enter-to-class="opacity-100 scale-100"
                                    leave-active-class="transition ease-in duration-75"
                                    leave-from-class="opacity-100 scale-100"
                                    leave-to-class="opacity-0 scale-95"
                                >
                                    <div
                                        v-if="menuOpen"
                                        class="absolute right-0 mt-1.5 w-56 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-xl overflow-hidden origin-top-right z-50"
                                    >
                                        <!-- Email -->
                                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $page.props.auth.user.email }}</p>
                                        </div>

                                        <!-- Primary actions -->
                                        <div class="p-1.5 border-b border-gray-100 dark:border-gray-700">
                                            <Link
                                                href="/profile"
                                                class="block w-full px-3 py-2 text-sm font-semibold text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                                @click="menuOpen = false"
                                            >
                                                Profile
                                            </Link>
                                            <Link
                                                href="/account/settings"
                                                class="block w-full px-3 py-2 text-sm font-semibold text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                                @click="menuOpen = false"
                                            >
                                                Settings
                                            </Link>
                                            <Link
                                                href="/my-affiliates"
                                                class="block w-full px-3 py-2 text-sm font-semibold text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                                @click="menuOpen = false"
                                            >
                                                Affiliates
                                            </Link>
                                            <Link
                                                href="/badges"
                                                class="block w-full px-3 py-2 text-sm font-semibold text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                                @click="menuOpen = false"
                                            >
                                                🎖️ Badges
                                            </Link>
                                            <Link
                                                v-if="$page.props.auth.user.is_creator || $page.props.auth.user.is_super_admin"
                                                href="/creator/dashboard"
                                                class="block w-full px-3 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-lg transition-colors"
                                                @click="menuOpen = false"
                                            >
                                                Creator Dashboard
                                            </Link>
                                            <Link
                                                v-if="$page.props.auth.user.is_creator || $page.props.auth.user.is_super_admin"
                                                href="/creator/plan"
                                                class="block w-full px-3 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-lg transition-colors"
                                                @click="menuOpen = false"
                                            >
                                                ⭐ Creator Pro Plan
                                            </Link>
                                            <Link
                                                v-if="$page.props.auth.user.is_super_admin"
                                                href="/admin"
                                                class="block w-full px-3 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-lg transition-colors"
                                                @click="menuOpen = false"
                                            >
                                                Admin Dashboard
                                            </Link>
                                            <Link
                                                v-if="$page.props.auth.user.is_super_admin"
                                                href="/admin/payouts"
                                                class="block w-full px-3 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-lg transition-colors"
                                                @click="menuOpen = false"
                                            >
                                                Admin Payouts
                                            </Link>
                                        </div>

                                        <!-- Nav links -->
                                        <div class="p-1.5 border-b border-gray-100 dark:border-gray-700">
                                            <button
                                                @click="menuOpen = false; openCreate()"
                                                class="block w-full text-left px-3 py-2 text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                            >
                                                Create a community
                                            </button>
                                            <Link
                                                href="/communities"
                                                class="block w-full px-3 py-2 text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                                @click="menuOpen = false"
                                            >
                                                Discover communities
                                            </Link>
                                        </div>

                                        <!-- Log out -->
                                        <div class="p-1.5">
                                            <Link
                                                method="post"
                                                href="/logout"
                                                as="button"
                                                class="block w-full text-left px-3 py-2 text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                                @click="menuOpen = false"
                                            >
                                                Log out
                                            </Link>
                                        </div>
                                    </div>
                                </Transition>
                            </div>
                        </template>
                        <template v-else>
                            <Link href="/login" class="text-sm text-gray-600 hover:text-gray-900 px-3 py-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                                Sign in
                            </Link>
                            <Link
                                href="/register"
                                class="text-sm bg-indigo-600 text-white px-4 py-1.5 rounded-lg hover:bg-indigo-700 transition-colors font-medium"
                            >
                                Get started
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Toast notifications -->
        <div class="fixed top-16 right-4 z-50 flex flex-col gap-2 pointer-events-none">
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-x-4"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 translate-x-4"
            >
                <div
                    v-if="flash.success"
                    class="pointer-events-auto flex items-center gap-3 px-4 py-3 bg-white dark:bg-gray-800 border border-green-200 dark:border-green-700 rounded-xl shadow-lg max-w-sm"
                >
                    <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <p class="text-sm text-gray-800 dark:text-gray-200 font-medium flex-1">{{ flash.success }}</p>
                    <button @click="flash.success = null" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </Transition>
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-x-4"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 translate-x-4"
            >
                <div
                    v-if="flash.error"
                    class="pointer-events-auto flex items-center gap-3 px-4 py-3 bg-white dark:bg-gray-800 border border-red-200 dark:border-red-700 rounded-xl shadow-lg max-w-sm"
                >
                    <div class="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <p class="text-sm text-gray-800 dark:text-gray-200 font-medium flex-1">{{ flash.error }}</p>
                    <button @click="flash.error = null" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </Transition>
        </div>

        <!-- Page content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 text-gray-900 dark:text-gray-100">
            <slot />
        </main>

        <!-- Create Community Modal — 3-step wizard -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showCreateModal"
                class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
                @click.self="closeAndResetCreate()"
            >
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">

                    <!-- Header -->
                    <div class="px-6 pt-6 pb-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Create a Community</h2>
                            <button @click="closeAndResetCreate()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <!-- Step indicators -->
                        <div class="flex items-center gap-2">
                            <template v-for="n in 3" :key="n">
                                <div class="flex items-center gap-2 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <div
                                            class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0 transition-colors"
                                            :class="createStep > n ? 'bg-indigo-600 text-white' : createStep === n ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-400'"
                                        >
                                            <svg v-if="createStep > n" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <span v-else>{{ n }}</span>
                                        </div>
                                        <span
                                            class="text-xs font-medium hidden sm:block"
                                            :class="createStep >= n ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400'"
                                        >{{ ['Basics', 'Branding', 'Pricing'][n - 1] }}</span>
                                    </div>
                                    <div v-if="n < 3" class="flex-1 h-px" :class="createStep > n ? 'bg-indigo-300' : 'bg-gray-200 dark:bg-gray-700'" />
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Step content -->
                    <form @submit.prevent="createCommunity">
                        <div class="px-6 py-5 space-y-4">

                            <!-- Step 1: Basics -->
                            <template v-if="createStep === 1">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                        Community name <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="createForm.name"
                                        type="text"
                                        autofocus
                                        placeholder="e.g. PH Developers"
                                        class="w-full px-3.5 py-2.5 border rounded-lg text-sm bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        :class="createForm.errors.name ? 'border-red-400' : 'border-gray-300 dark:border-gray-600'"
                                    />
                                    <p v-if="createForm.errors.name" class="mt-1 text-xs text-red-600">{{ createForm.errors.name }}</p>
                                    <p v-else-if="createSlugPreview" class="mt-1 text-xs text-gray-400">
                                        URL: <span class="font-mono text-gray-500">curzzo.com/communities/{{ createSlugPreview }}</span>
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                                    <textarea
                                        v-model="createForm.description"
                                        rows="3"
                                        placeholder="What is this community about?"
                                        class="w-full px-3.5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Category</label>
                                    <select
                                        v-model="createForm.category"
                                        class="w-full px-3.5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 dark:text-gray-200"
                                    >
                                        <option value="">No category</option>
                                        <option v-for="cat in CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
                                    </select>
                                </div>
                            </template>

                            <!-- Step 2: Branding -->
                            <template v-if="createStep === 2">
                                <!-- Banner / Cover -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                        Banner image <span class="text-red-500">*</span> <span class="text-gray-400 font-normal">(recommended: 1200×400)</span>
                                    </label>
                                    <div
                                        class="relative w-full aspect-3/1 rounded-xl overflow-hidden border-2 border-dashed border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 flex items-center justify-center cursor-pointer group hover:border-indigo-400 transition-colors"
                                        @click="coverInputC.click()"
                                    >
                                        <img v-if="coverPreviewC" :src="coverPreviewC" class="absolute inset-0 w-full h-full object-cover" />
                                        <div v-if="!coverPreviewC" class="flex flex-col items-center gap-1.5 text-gray-400 group-hover:text-indigo-500 transition-colors">
                                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="text-xs font-medium">Click to upload banner</span>
                                        </div>
                                        <button v-if="coverPreviewC" type="button"
                                            class="absolute top-2 right-2 w-6 h-6 bg-black/60 hover:bg-black/80 text-white rounded-full flex items-center justify-center"
                                            @click.stop="coverPreviewC = null; createForm.cover_image = null; coverInputC.value = ''">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                        <input ref="coverInputC" type="file" accept="image/*" class="hidden" @change="onCreateCoverChange" />
                                    </div>
                                    <p v-if="coverRatioError" class="mt-1 text-xs text-red-600">{{ coverRatioError }}</p>
                                    <p v-else-if="!coverPreviewC && createStep === 2 && createForm.errors.cover_image" class="mt-1 text-xs text-red-600">{{ createForm.errors.cover_image }}</p>
                                </div>

                                <!-- Avatar -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                        Community avatar <span class="text-gray-400 font-normal">(square, min 200×200)</span>
                                    </label>
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="relative w-20 h-20 rounded-2xl overflow-hidden border-2 border-dashed border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 flex items-center justify-center cursor-pointer group hover:border-indigo-400 transition-colors shrink-0"
                                            @click="avatarInputC.click()"
                                        >
                                            <img v-if="avatarPreviewC" :src="avatarPreviewC" class="absolute inset-0 w-full h-full object-cover" />
                                            <svg v-else class="w-6 h-6 text-gray-300 group-hover:text-indigo-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            <input ref="avatarInputC" type="file" accept="image/*" class="hidden" @change="onCreateAvatarChange" />
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <p>This shows as the community icon in search and navigation.</p>
                                            <button v-if="avatarPreviewC" type="button" class="text-xs text-red-500 hover:text-red-600 mt-1"
                                                @click="avatarPreviewC = null; createForm.avatar = null; avatarInputC.value = ''">
                                                Remove
                                            </button>
                                            <button v-else type="button" class="text-xs text-indigo-600 hover:text-indigo-700 mt-1" @click="avatarInputC.click()">
                                                Upload avatar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Step 3: Pricing & Settings -->
                            <template v-if="createStep === 3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Membership type</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label
                                            class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors"
                                            :class="createForm.price == 0 || createForm.price === '' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'"
                                        >
                                            <input type="radio" class="hidden" :checked="createForm.price == 0 || createForm.price === ''" @change="createForm.price = ''" />
                                            <div>
                                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Free</p>
                                                <p class="text-xs text-gray-500">Anyone can join</p>
                                            </div>
                                        </label>
                                        <label
                                            class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors"
                                            :class="createForm.price > 0 ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'"
                                        >
                                            <input type="radio" class="hidden" :checked="createForm.price > 0" @change="createForm.price = 499" />
                                            <div>
                                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Paid</p>
                                                <p class="text-xs text-gray-500">Charge for access</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div v-if="createForm.price > 0 || (createForm.price !== '' && createForm.price != 0)" class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Price (₱)</label>
                                        <input
                                            v-model="createForm.price"
                                            type="number"
                                            min="1"
                                            step="1"
                                            placeholder="e.g. 499"
                                            class="w-full px-3.5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Billing</label>
                                        <select
                                            v-model="createForm.billing_type"
                                            class="w-full px-3.5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 dark:text-gray-200"
                                        >
                                            <option value="monthly">Monthly</option>
                                            <option value="one_time">One-time</option>
                                        </select>
                                    </div>
                                </div>

                                <div v-if="createForm.price > 0 || (createForm.price !== '' && createForm.price != 0)">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                        Affiliate commission <span class="text-gray-400 font-normal">(%)</span>
                                    </label>
                                    <input
                                        v-model="createForm.affiliate_commission_rate"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        placeholder="e.g. 20"
                                        class="w-full px-3.5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    />
                                    <p class="mt-1 text-xs text-gray-400">Commission paid to affiliates who refer paying members.</p>
                                </div>

                                <div class="pt-1">
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <div class="relative">
                                            <input v-model="createForm.is_private" type="checkbox" class="sr-only peer" />
                                            <div class="w-9 h-5 bg-gray-200 dark:bg-gray-600 peer-checked:bg-indigo-600 rounded-full transition-colors"></div>
                                            <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Private community</p>
                                            <p class="text-xs text-gray-400">Only invited members can join</p>
                                        </div>
                                    </label>
                                </div>
                            </template>
                        </div>

                        <!-- Footer -->
                        <div class="px-6 pb-6 flex gap-3">
                            <button
                                type="button"
                                @click="createStep > 1 ? createStep-- : closeAndResetCreate()"
                                class="flex-1 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            >
                                {{ createStep > 1 ? 'Back' : 'Cancel' }}
                            </button>
                            <button
                                v-if="createStep < 3"
                                type="button"
                                :disabled="(createStep === 1 && !createForm.name.trim()) || (createStep === 2 && (!createForm.cover_image || coverRatioError))"
                                class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                @click="createStep++"
                            >
                                Next
                            </button>
                            <button
                                v-else
                                type="submit"
                                :disabled="createForm.processing"
                                class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                            >
                                {{ createForm.processing ? 'Creating...' : 'Create community' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>

        <!-- ─── AI Assistant Panel ──────────────────────────────────────────── -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-x-4"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 translate-x-4"
        >
            <div
                v-if="aiOpen"
                class="fixed top-14 right-0 bottom-0 w-80 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 shadow-xl z-40 flex flex-col"
            >
                <!-- Header -->
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <!-- Curzzo avatar -->
                        <div class="w-8 h-8 rounded-full shrink-0 shadow-sm overflow-hidden bg-gray-100">
                            <img :src="curzzoIcon" alt="Curzzo" class="w-full h-full object-cover" />
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-tight">Curzzo</p>
                            <p class="text-[10px] text-indigo-400 leading-tight">AI Learning Assistant</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            @click="aiNewChat"
                            class="text-xs text-gray-400 hover:text-indigo-600 px-2 py-1 rounded-lg hover:bg-indigo-50 transition-colors"
                            title="New conversation"
                        >New chat</button>
                        <button
                            @click="aiOpen = false"
                            class="w-6 h-6 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <div ref="aiScrollRef" class="flex-1 overflow-y-auto p-4 space-y-3">
                    <div v-if="!aiMessages.length && !aiLoading" class="flex flex-col items-center justify-center h-full text-center text-gray-400 gap-2">
                        <div class="w-14 h-14 rounded-full shadow-md overflow-hidden bg-gray-100">
                            <img :src="curzzoIcon" alt="Curzzo" class="w-full h-full object-cover" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Hi, I'm Curzzo!</p>
                            <p class="text-xs mt-0.5 text-gray-400">Ask me about your lessons, quizzes &amp; progress.</p>
                        </div>
                    </div>

                    <template v-for="(msg, i) in aiMessages" :key="i">
                        <!-- User message -->
                        <div v-if="msg.role === 'user'" class="flex justify-end items-end gap-2">
                            <div class="max-w-[75%] px-3 py-2 bg-indigo-600 text-white text-sm rounded-2xl rounded-tr-sm">
                                {{ msg.content }}
                            </div>
                            <div class="w-6 h-6 rounded-full shrink-0 mb-0.5 overflow-hidden bg-indigo-200 flex items-center justify-center text-[10px] font-bold text-indigo-700">
                                <img v-if="$page.props.auth?.user?.avatar" :src="$page.props.auth.user.avatar" class="w-full h-full object-cover" />
                                <span v-else>{{ initials }}</span>
                            </div>
                        </div>
                        <!-- Curzzo message -->
                        <div v-else class="flex justify-start items-end gap-2">
                            <div class="w-6 h-6 rounded-full shrink-0 mb-0.5 overflow-hidden bg-gray-100">
                                <img :src="curzzoIcon" alt="Curzzo" class="w-full h-full object-cover" />
                            </div>
                            <div class="max-w-[75%] px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm rounded-2xl rounded-tl-sm whitespace-pre-wrap">
                                {{ msg.content }}
                            </div>
                        </div>
                    </template>

                    <!-- Loading dots -->
                    <div v-if="aiLoading" class="flex justify-start items-end gap-2">
                        <div class="w-6 h-6 rounded-full shrink-0 mb-0.5 overflow-hidden bg-gray-100">
                            <img :src="curzzoIcon" alt="Curzzo" class="w-full h-full object-cover" />
                        </div>
                        <div class="px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-2xl rounded-tl-sm">
                            <span class="flex gap-1">
                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:150ms"></span>
                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:300ms"></span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Input -->
                <div class="p-3 border-t border-gray-100 dark:border-gray-700 shrink-0">
                    <div class="flex gap-2 items-end">
                        <textarea
                            v-model="aiInput"
                            @keydown.enter.exact.prevent="sendAiMessage"
                            placeholder="Ask about your progress..."
                            rows="1"
                            class="flex-1 px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                            style="max-height:100px; overflow-y:auto;"
                            :disabled="aiLoading"
                        ></textarea>
                        <button
                            @click="sendAiMessage"
                            :disabled="aiLoading || !aiInput.trim()"
                            class="shrink-0 w-8 h-8 flex items-center justify-center bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 disabled:opacity-40 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                        </button>
                    </div>
                    <p class="text-[10px] text-gray-300 mt-1.5 text-center">Enter to send · Shift+Enter for newline</p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, watch, watchEffect, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Link, usePage, useForm, router } from '@inertiajs/vue3';
import { useCreateModal } from '@/composables/useCreateModal';
import { usePixel } from '@/composables/usePixel';
import { useTiktokPixel } from '@/composables/useTiktokPixel';
import { useGoogleAnalytics } from '@/composables/useGoogleAnalytics';

const curzzoIcon = '/brand/ICON/CURZZO LOGO WHIT BG ROUND.png';

const props = defineProps({
    title:     String,
    community: Object,   // when set, navbar shows community context
});

const page = usePage();

// ─── Dark mode ─────────────────────────────────────────────────────────────────

watchEffect(() => {
    const theme = page.props.auth?.user?.theme ?? 'light';
    document.documentElement.classList.toggle('dark', theme === 'dark');
});

// ─── Dropdowns ────────────────────────────────────────────────────────────────

const switcherOpen   = ref(false);
const switcherSearch = ref('');
const switcherRef    = ref(null);

const menuOpen  = ref(false);
const menuRef   = ref(null);

const notifOpen    = ref(false);
const notifRef     = ref(null);
const notifLoading = ref(false);
const notifications = ref([]);

async function toggleNotifications() {
    notifOpen.value = !notifOpen.value;
    if (notifOpen.value) {
        notifLoading.value = true;
        try {
            const axios = (await import('axios')).default;
            const res = await axios.get('/notifications/recent');
            notifications.value = res.data;
        } catch (e) {
            // ignore
        } finally {
            notifLoading.value = false;
        }
    }
}

function markAllRead() {
    import('@inertiajs/vue3').then(({ router: r }) => {
        r.post('/notifications/read-all', {}, {
            preserveScroll: true,
            onSuccess: () => {
                notifications.value = notifications.value.map(n => ({ ...n, read_at: new Date().toISOString() }));
            },
        });
    });
}

function relativeTime(dateStr) {
    if (!dateStr) return '';
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1)  return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24)  return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

// ─── DM panel ──────────────────────────────────────────────────────────────────
const dmOpen            = ref(false);
const dmRef             = ref(null);
const dmLoading         = ref(false);
const dmConversations   = ref([]);
const dmSearch          = ref('');
const dmSearchResults   = ref([]);
let   dmSearchTimer     = null;

async function toggleDm() {
    dmOpen.value = !dmOpen.value;
    if (dmOpen.value && !dmConversations.value.length) {
        dmLoading.value = true;
        try {
            const res = await (await import('axios')).default.get('/messages', {
                headers: { Accept: 'application/json' },
            });
            dmConversations.value = res.data.conversations ?? [];
        } catch { /* ignore */ } finally {
            dmLoading.value = false;
        }
    }
}

async function searchUsers() {
    clearTimeout(dmSearchTimer);
    if (!dmSearch.value.trim()) { dmSearchResults.value = []; return; }
    dmSearchTimer = setTimeout(async () => {
        try {
            const res = await (await import('axios')).default.get('/users/search', {
                params: { q: dmSearch.value },
            });
            dmSearchResults.value = res.data.users ?? [];
        } catch { /* ignore */ }
    }, 300);
}

function dmFormatTime(str) {
    if (!str) return '';
    const d = new Date(str);
    const now = new Date();
    const diffDays = Math.floor((now - d) / 86400000);
    if (diffDays === 0) return d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7)  return d.toLocaleDateString('en-PH', { weekday: 'short' });
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}

// ─── Flash ────────────────────────────────────────────────────────────────────

const flash = ref({
    success: page.props.flash?.success ?? null,
    error:   page.props.flash?.error   ?? null,
});

watch(() => flash.value.success, (val) => {
    if (val) setTimeout(() => { flash.value.success = null; }, 4000);
});
watch(() => flash.value.error, (val) => {
    if (val) setTimeout(() => { flash.value.error = null; }, 4000);
});

watch(() => page.props.flash, (f) => {
    if (f?.success) flash.value.success = f.success;
    if (f?.error)   flash.value.error   = f.error;
}, { deep: true });

// ─── Community switcher ────────────────────────────────────────────────────────

const filteredSwitcherCommunities = computed(() => {
    const list = page.props.auth?.communities ?? [];
    if (!switcherSearch.value.trim()) return list;
    const q = switcherSearch.value.toLowerCase();
    return list.filter((c) => c.name.toLowerCase().includes(q));
});

// ─── Create community modal ────────────────────────────────────────────────────

const CATEGORIES = ['Tech', 'Business', 'Design', 'Health', 'Education', 'Finance', 'Other'];

const { showCreateModal, openCreateModal, closeCreateModal } = useCreateModal();

const createStep      = ref(1);
const coverPreviewC   = ref(null);
const avatarPreviewC  = ref(null);
const coverInputC     = ref(null);
const avatarInputC    = ref(null);
const coverRatioError = ref(null);

const createForm = useForm({
    name:                      '',
    description:               '',
    category:                  '',
    price:                     '',
    billing_type:              'monthly',
    currency:                  'PHP',
    is_private:                false,
    affiliate_commission_rate: '',
    cover_image:               null,
    avatar:                    null,
});

const createSlugPreview = computed(() =>
    createForm.name ? createForm.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') : ''
);

function openCreate() {
    switcherOpen.value = false;
    openCreateModal();
}

function closeAndResetCreate() {
    closeCreateModal();
    createStep.value = 1;
    coverPreviewC.value  = null;
    avatarPreviewC.value = null;
    createForm.reset();
}

function onCreateCoverChange(e) {
    const file = e.target.files[0];
    if (!file) return;

    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
        const ratio = img.width / img.height;
        const target = 16 / 9;
        const tolerance = 0.1;
        const tooSmall = img.width < 720 || img.height < 383;
        const wrongRatio = Math.abs(ratio - target) > target * tolerance;
        if (tooSmall || wrongRatio) {
            coverRatioError.value = `Banner must be at least 720×383 px and 16:9 ratio (e.g. 1280×720, 1920×1080). Yours is ${img.width}×${img.height}.`;
            createForm.cover_image = null;
            coverPreviewC.value = null;
            coverInputC.value.value = '';
        } else {
            coverRatioError.value = null;
            createForm.cover_image = file;
            coverPreviewC.value = url;
        }
    };
    img.src = url;
}

function onCreateAvatarChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    createForm.avatar = file;
    avatarPreviewC.value = URL.createObjectURL(file);
}

function createCommunity() {
    createForm.post('/communities', {
        forceFormData: true,
        onSuccess: () => closeAndResetCreate(),
    });
}

// ─── User initials ─────────────────────────────────────────────────────────────

const initials = computed(() => {
    const name = page.props.auth?.user?.name ?? '';
    return name.split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();
});

// ─── Outside click handler ─────────────────────────────────────────────────────

function handleOutsideClick(e) {
    if (switcherRef.value && !switcherRef.value.contains(e.target)) {
        switcherOpen.value = false;
        switcherSearch.value = '';
    }
    if (menuRef.value && !menuRef.value.contains(e.target)) {
        menuOpen.value = false;
    }
    if (notifRef.value && !notifRef.value.contains(e.target)) {
        notifOpen.value = false;
    }
    if (dmRef.value && !dmRef.value.contains(e.target)) {
        dmOpen.value = false;
        dmSearch.value = '';
        dmSearchResults.value = [];
    }
}

onMounted(() => {
    document.addEventListener('click', handleOutsideClick);

    // ── Tracking pixels — init each once, fire PageView on every SPA navigation ──
    const trackers = [
        props.community?.facebook_pixel_id  ? usePixel(props.community.facebook_pixel_id)                   : null,
        props.community?.tiktok_pixel_id    ? useTiktokPixel(props.community.tiktok_pixel_id)               : null,
        props.community?.google_analytics_id ? useGoogleAnalytics(props.community.google_analytics_id)      : null,
    ].filter(Boolean);

    trackers.forEach(t => { t.init(); t.pageView(); });
    router.on('navigate', () => trackers.forEach(t => t.pageView()));
});
onBeforeUnmount(() => document.removeEventListener('click', handleOutsideClick));

// ─── AI Assistant ──────────────────────────────────────────────────────────────

const aiOpen           = ref(false);
const aiMessages       = ref([]);
const aiInput          = ref('');
const aiLoading        = ref(false);
const aiConversationId = ref(null);
const aiScrollRef      = ref(null);

function aiNewChat() {
    aiMessages.value       = [];
    aiConversationId.value = null;
}

async function fetchAiGreeting() {
    aiOpen.value    = true;
    aiLoading.value = true;
    await nextTick();
    if (aiScrollRef.value) aiScrollRef.value.scrollTop = aiScrollRef.value.scrollHeight;

    try {
        const axios = (await import('axios')).default;
        const res   = await axios.post('/ai/greet');
        aiConversationId.value = res.data.conversation_id;
        aiMessages.value.push({ role: 'assistant', content: res.data.message });
    } catch (e) {
        aiMessages.value.push({ role: 'assistant', content: 'Hey! How can I help you today?' });
    } finally {
        aiLoading.value = false;
        await nextTick();
        if (aiScrollRef.value) aiScrollRef.value.scrollTop = aiScrollRef.value.scrollHeight;
    }
}

watch(() => page.props.flash?.show_ai_greeting, (val) => {
    if (val && (page.props.auth?.communities ?? []).length > 0) {
        fetchAiGreeting();
    }
}, { immediate: true });

async function sendAiMessage() {
    const text = aiInput.value.trim();
    if (!text || aiLoading.value) return;

    aiMessages.value.push({ role: 'user', content: text });
    aiInput.value  = '';
    aiLoading.value = true;

    await nextTick();
    if (aiScrollRef.value) aiScrollRef.value.scrollTop = aiScrollRef.value.scrollHeight;

    try {
        const axios = (await import('axios')).default;
        const res   = await axios.post('/ai/chat', {
            message:         text,
            conversation_id: aiConversationId.value,
        });

        aiConversationId.value = res.data.conversation_id;
        aiMessages.value.push({ role: 'assistant', content: res.data.message });
    } catch (e) {
        const msg = e?.response?.data?.message
            ?? e?.response?.data?.error
            ?? 'Something went wrong. Please try again.';
        aiMessages.value.push({ role: 'assistant', content: msg });
    } finally {
        aiLoading.value = false;
        await nextTick();
        if (aiScrollRef.value) aiScrollRef.value.scrollTop = aiScrollRef.value.scrollHeight;
    }
}
</script>
