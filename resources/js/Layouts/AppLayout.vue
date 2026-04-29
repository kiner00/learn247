<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 overflow-x-clip">
        <!-- Sticky Navbar -->
        <nav class="sticky top-0 z-40 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-14 items-center">

                    <!-- Logo / Community context + Switcher -->
                    <div class="flex items-center gap-1">
                        <!-- Community context (when inside a community) -->
                        <template v-if="props.community">
                            <Link :href="communityPath()" class="flex items-center gap-2 px-1 py-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
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
                                    :src="'/brand/logo-new.png'"
                                    alt="Curzzo"
                                    class="h-6 w-auto"
                                />
                            </Link>
                        </template>

                        <!-- Dropdown toggle (hidden on custom domain) -->
                        <div v-if="!isOnDomain" class="relative" ref="switcherRef">
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
                            <span class="hidden sm:inline">AI Assistant</span>
                        </button>
                    </div><!-- end logo+switcher flex -->

                    <!-- Right: auth -->
                    <div class="flex items-center gap-2">
                        <template v-if="$page.props.auth?.user">

                            <!-- Direct messages / Community chat (hidden on custom domain) -->
                            <div v-if="!isOnDomain" class="relative" ref="dmRef">
                                <Link
                                    v-if="props.community"
                                    :href="communityPath('/chat?tab=personal')"
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
                                </Link>
                                <button
                                    v-else
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

                            <!-- Notification bell (hidden on custom domain) -->
                            <div v-if="!isOnDomain" class="relative" ref="notifRef">
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
                                        <!-- Email + CXP -->
                                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $page.props.auth.user.email }}</p>
                                            <div class="flex items-center gap-1.5 mt-1.5">
                                                <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded-full">CXP {{ $page.props.auth.user.cxp_balance ?? 0 }}</span>
                                            </div>
                                        </div>

                                        <!-- Primary actions (hidden on custom domain) -->
                                        <template v-if="!isOnDomain">
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
                                                href="/badges"
                                                class="block w-full px-3 py-2 text-sm font-semibold text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                                @click="menuOpen = false"
                                            >
                                                🎖️ Badges
                                            </Link>
                                            <Link
                                                href="/support"
                                                class="block w-full px-3 py-2 text-sm font-semibold text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                                @click="menuOpen = false"
                                            >
                                                Support
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
                                                href="/my-affiliates"
                                                class="block w-full px-3 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-lg transition-colors"
                                                @click="menuOpen = false"
                                            >
                                                Affiliate Dashboard
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
                                        </template>

                                        <!-- Legal -->
                                        <div class="p-1.5 border-t border-gray-100 dark:border-gray-700">
                                            <Link
                                                href="/terms"
                                                class="block w-full px-3 py-2 text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                                @click="menuOpen = false"
                                            >
                                                Terms of Service
                                            </Link>
                                            <Link
                                                href="/privacy"
                                                class="block w-full px-3 py-2 text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                                @click="menuOpen = false"
                                            >
                                                Privacy Policy
                                            </Link>
                                        </div>

                                        <!-- Log out -->
                                        <div class="p-1.5 border-t border-gray-100 dark:border-gray-700">
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
                            <Link href="/login" class="text-sm text-gray-600 hover:text-gray-900 px-2 sm:px-3 py-1.5 rounded-lg hover:bg-gray-100 transition-colors whitespace-nowrap shrink-0">
                                Sign in
                            </Link>
                            <Link
                                href="/register"
                                class="text-sm bg-indigo-600 text-white px-3 sm:px-4 py-1.5 rounded-lg hover:bg-indigo-700 transition-colors font-medium whitespace-nowrap shrink-0"
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

        <!-- Create Community Modal -->
        <CreateCommunityModal />

        <!-- AI Assistant Panel -->
        <AiAssistantPanel v-model="aiOpen" :initials="initials" :user-avatar="$page.props.auth?.user?.avatar" />
    </div>
</template>

<script setup>
import { ref, computed, watch, watchEffect, onMounted, onBeforeUnmount } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useCreateModal } from '@/composables/useCreateModal';
import { useCommunityUrl } from '@/composables/useCommunityUrl';
import { usePixel } from '@/composables/usePixel';
import { useTiktokPixel } from '@/composables/useTiktokPixel';
import { useGoogleAnalytics } from '@/composables/useGoogleAnalytics';
import CreateCommunityModal from '@/Components/Layout/CreateCommunityModal.vue';
import AiAssistantPanel from '@/Components/Layout/AiAssistantPanel.vue';

const curzzoIcon = '/brand/ICON/CURZZO LOGO WHIT BG ROUND.png';

const props = defineProps({
    title:     String,
    community: Object,   // when set, navbar shows community context
});

const page = usePage();
const { communityPath, isOnDomain } = useCommunityUrl(props.community?.slug);

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

const { openCreateModal } = useCreateModal();

function openCreate() {
    switcherOpen.value = false;
    openCreateModal();
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

const aiOpen = ref(false);
</script>
