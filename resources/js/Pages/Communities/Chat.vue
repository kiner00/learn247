<template>
    <AppLayout :title="`${community.name} · Chat`" :community="community">
        <CommunityTabs :community="community" active-tab="chat" />

        <!-- Mobile tabs (horizontal) -->
        <div class="flex gap-1.5 mb-3 md:hidden">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                @click="switchTab(tab.key)"
                class="px-3.5 py-2 text-xs font-semibold rounded-lg whitespace-nowrap transition-colors"
                :class="activeTab === tab.key ? 'bg-indigo-600 text-white' : 'text-gray-600 bg-gray-100 hover:bg-gray-200'"
            >{{ tab.label }}</button>
        </div>

        <div class="flex gap-0 rounded-2xl overflow-hidden h-[calc(100vh-280px)] md:h-[calc(100vh-220px)]">

            <!-- ── Mobile: Conversation list (full width, no chat selected) ── -->
            <div
                v-if="activeTab === 'personal' && !personalSelectedId"
                class="w-full bg-white border border-gray-200 overflow-y-auto md:hidden"
            >
                <button
                    v-if="!isOwner"
                    @click="selectCreatorChat()"
                    class="w-full flex items-center gap-3 px-4 py-3.5 border-b border-gray-100 hover:bg-gray-50 transition-colors text-left"
                >
                    <div class="relative shrink-0">
                        <img v-if="community.owner?.avatar" :src="community.owner.avatar" class="w-12 h-12 rounded-full object-cover" />
                        <div v-else class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center text-base font-bold text-indigo-600">{{ community.owner?.name?.charAt(0)?.toUpperCase() }}</div>
                        <span class="absolute -top-0.5 -right-0.5 w-3.5 h-3.5 bg-green-400 border-2 border-white rounded-full"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900">{{ community.owner?.name }}</p>
                        <p class="text-xs text-gray-400">Talk to creator</p>
                    </div>
                </button>
                <template v-if="isOwner">
                    <div v-if="!conversationList.length" class="p-6 text-center"><p class="text-sm text-gray-400">No conversations yet.</p></div>
                    <button
                        v-for="u in conversationList"
                        :key="u.id"
                        @click="selectMemberChat(u)"
                        class="w-full flex items-center gap-3 px-4 py-3.5 border-b border-gray-50 hover:bg-gray-50 transition-colors text-left"
                    >
                        <img v-if="u.avatar" :src="u.avatar" class="w-12 h-12 rounded-full object-cover shrink-0" />
                        <div v-else class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center text-base font-bold text-indigo-600 shrink-0">{{ u.name?.charAt(0)?.toUpperCase() }}</div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ u.name }}</p>
                            <p class="text-xs text-gray-400">{{ Math.floor(u.message_count / 2) }} messages</p>
                        </div>
                        <span class="text-xs text-gray-300">{{ formatRelativeTime(u.last_chat_at) }}</span>
                    </button>
                </template>
            </div>

            <!-- ── Left: Tabs + Conversation list (desktop) ───────────────── -->
            <div class="hidden md:flex shrink-0">
                <!-- Tab buttons -->
                <div class="flex flex-col gap-1 p-2 border-r border-gray-200 bg-gray-50 rounded-l-2xl">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="switchTab(tab.key)"
                        class="px-3 py-2 text-xs font-semibold rounded-lg whitespace-nowrap transition-colors text-left"
                        :class="activeTab === tab.key ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-200'"
                    >{{ tab.label }}</button>
                </div>

                <!-- Conversation list (Personal tab) -->
                <div v-if="activeTab === 'personal'" class="w-52 border-r border-gray-200 bg-white overflow-y-auto">
                    <!-- Pinned: Talk to Creator (members only) -->
                    <button
                        v-if="!isOwner"
                        @click="selectCreatorChat()"
                        class="w-full flex items-center gap-2.5 px-3 py-3 border-b border-gray-100 hover:bg-gray-50 transition-colors text-left"
                        :class="personalSelectedId === 'creator' ? 'bg-indigo-50' : ''"
                    >
                        <div class="relative shrink-0">
                            <img v-if="community.owner?.avatar" :src="community.owner.avatar" class="w-10 h-10 rounded-full object-cover" />
                            <div v-else class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-600">{{ community.owner?.name?.charAt(0)?.toUpperCase() }}</div>
                            <span class="absolute -top-0.5 -right-0.5 w-3 h-3 bg-green-400 border-2 border-white rounded-full"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ community.owner?.name }}</p>
                            <p class="text-xs text-gray-400 truncate">Talk to creator</p>
                        </div>
                    </button>

                    <!-- Member conversations (creator view) -->
                    <template v-if="isOwner">
                        <div v-if="!conversationList.length" class="p-4 text-center">
                            <p class="text-xs text-gray-400">No conversations yet.</p>
                        </div>
                        <button
                            v-for="u in conversationList"
                            :key="u.id"
                            @click="selectMemberChat(u)"
                            class="w-full flex items-center gap-2.5 px-3 py-3 border-b border-gray-50 hover:bg-gray-50 transition-colors text-left"
                            :class="personalSelectedId === u.id ? 'bg-indigo-50' : ''"
                        >
                            <div class="relative shrink-0">
                                <img v-if="u.avatar" :src="u.avatar" class="w-10 h-10 rounded-full object-cover" />
                                <div v-else class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-600">{{ u.name?.charAt(0)?.toUpperCase() }}</div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ u.name }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ Math.floor(u.message_count / 2) }} messages</p>
                            </div>
                            <span class="text-xs text-gray-300 shrink-0">{{ formatRelativeTime(u.last_chat_at) }}</span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- ── Right: Content area ────────────────────────────────────── -->
            <div
                class="flex-1 min-w-0 flex flex-col bg-white border border-gray-200 md:rounded-r-2xl rounded-2xl md:rounded-l-none overflow-hidden shadow-sm"
                :class="{ 'hidden md:flex': activeTab === 'personal' && !personalSelectedId }"
            >

                <!-- ═══ Community tab ═══ -->
                <template v-if="activeTab === 'community'">
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center gap-2 shrink-0">
                        <div class="w-2 h-2 rounded-full bg-green-400"></div>
                        <span class="text-sm font-semibold text-gray-900"># general</span>
                        <div v-if="telegramConnected" class="ml-auto flex items-center gap-1.5 px-2.5 py-1 bg-sky-50 border border-sky-200 rounded-full">
                            <svg class="w-3 h-3 text-sky-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z"/></svg>
                            <span class="text-xs font-medium text-sky-600">Telegram connected</span>
                        </div>
                    </div>
                    <div ref="messagesEl" class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                        <div v-if="groupMessages.length" class="flex items-center gap-3 py-1"><div class="flex-1 h-px bg-gray-100"></div><span class="text-xs text-gray-400">Today</span><div class="flex-1 h-px bg-gray-100"></div></div>
                        <div v-if="!groupMessages.length" class="flex flex-col items-center justify-center h-full text-center py-16">
                            <div class="w-14 h-14 rounded-2xl bg-indigo-50 flex items-center justify-center mx-auto mb-4">
                                <svg class="w-7 h-7 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            </div>
                            <p class="text-sm font-medium text-gray-700 mb-1">No messages yet</p>
                            <p class="text-xs text-gray-400">Be the first to say something!</p>
                        </div>
                        <div v-for="(msg, i) in groupMessages" :key="msg.id" class="flex gap-3 group" :class="{ 'mt-4': i > 0 && msgGroupKey(groupMessages[i - 1]) !== msgGroupKey(msg) }">
                            <div class="shrink-0 w-8 mt-0.5">
                                <template v-if="i === 0 || msgGroupKey(groupMessages[i - 1]) !== msgGroupKey(msg)">
                                    <div v-if="msg.telegram_author" class="w-8 h-8 rounded-full bg-sky-100 flex items-center justify-center"><svg class="w-4 h-4 text-sky-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z"/></svg></div>
                                    <div v-else class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold" :class="avatarColor(msg.user?.name)">{{ msg.user?.name?.charAt(0)?.toUpperCase() }}</div>
                                </template>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div v-if="i === 0 || msgGroupKey(groupMessages[i - 1]) !== msgGroupKey(msg)" class="flex items-baseline gap-2 mb-0.5">
                                    <span v-if="msg.telegram_author" class="flex items-center gap-1 text-sm font-semibold text-sky-600">{{ msg.telegram_author }}<svg class="w-3 h-3 opacity-70" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z"/></svg></span>
                                    <span v-else class="text-sm font-semibold text-gray-900">{{ msg.user?.name }}</span>
                                    <span class="text-xs text-gray-400">{{ formatTime(msg.created_at) }}</span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <div class="flex-1 min-w-0">
                                        <img v-if="msg.media_type === 'image' && msg.media_url" :src="msg.media_url" class="max-w-xs rounded-lg mb-1 cursor-pointer" @click="() => window.open(msg.media_url, '_blank')" />
                                        <video v-else-if="msg.media_type === 'video' && msg.media_url" :src="msg.media_url" controls class="max-w-xs rounded-lg mb-1" />
                                        <p v-if="msg.content" class="text-sm text-gray-700 leading-relaxed wrap-break-word">{{ msg.content }}</p>
                                    </div>
                                    <button v-if="canDelete(msg)" @click="deleteMessage(msg)" class="opacity-0 group-hover:opacity-100 shrink-0 text-gray-300 hover:text-red-500 transition-all mt-0.5" title="Delete"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                </div>
                            </div>
                        </div>
                        <div v-if="sending" class="flex gap-3 items-center px-1"><div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center shrink-0"><span class="text-xs text-gray-400">...</span></div><span class="text-xs text-gray-400">Sending…</span></div>
                    </div>
                    <div class="px-4 py-3 border-t border-gray-100 shrink-0">
                        <div v-if="mediaPreviewUrl" class="mb-2 relative inline-block">
                            <img v-if="mediaFile?.type?.startsWith('image/')" :src="mediaPreviewUrl" class="h-24 rounded-lg object-cover border border-gray-200" />
                            <video v-else :src="mediaPreviewUrl" class="h-24 rounded-lg border border-gray-200" />
                            <button type="button" @click="clearMedia" class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-gray-800 text-white rounded-full flex items-center justify-center text-xs leading-none">×</button>
                        </div>
                        <form @submit.prevent="send" class="flex items-end gap-2">
                            <input ref="fileInputEl" type="file" accept="image/*,video/*" class="hidden" @change="onFileSelected" />
                            <button type="button" @click="fileInputEl.click()" class="shrink-0 w-9 h-9 flex items-center justify-center text-gray-400 hover:text-indigo-600 transition-colors"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg></button>
                            <div class="flex-1"><textarea v-model="groupContent" ref="inputEl" rows="1" placeholder="Message #general" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none bg-gray-50" style="max-height: 120px; overflow-y: auto;" @keydown.enter.exact.prevent="send" @keydown.enter.shift.exact="groupContent += '\n'" @input="autoResize"></textarea></div>
                            <button type="submit" :disabled="(!groupContent.trim() && !mediaFile) || sending" class="shrink-0 w-9 h-9 flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white rounded-xl transition-colors"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg></button>
                        </form>
                        <p class="text-xs text-gray-400 mt-1.5 ml-1">Enter to send · Shift+Enter for new line</p>
                    </div>
                </template>

                <!-- ═══ Personal tab ═══ -->
                <template v-else-if="activeTab === 'personal'">
                    <div v-if="!personalSelectedId" class="flex-1 flex items-center justify-center text-center px-6">
                        <div>
                            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            </div>
                            <p class="text-sm text-gray-400">Select a conversation</p>
                        </div>
                    </div>

                    <template v-else>
                        <!-- Chat header -->
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2.5 shrink-0">
                            <button @click="personalSelectedId = null; stopPersonalPolling()" class="md:hidden shrink-0 w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 -ml-1">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <img v-if="personalHeaderAvatar" :src="personalHeaderAvatar" class="w-9 h-9 rounded-full object-cover" />
                            <div v-else class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-600">{{ personalHeaderName?.charAt(0)?.toUpperCase() }}</div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ personalHeaderName }}</p>
                                <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-400"></span><p class="text-xs text-gray-400">Online</p></div>
                            </div>
                        </div>

                        <!-- Messages -->
                        <div ref="personalChatEl" class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                            <!-- Welcome state (empty) -->
                            <div v-if="!personalMessages.length && !personalLoading" class="flex flex-col items-center justify-center h-full text-center px-2">
                                <img v-if="personalHeaderAvatar" :src="personalHeaderAvatar" class="w-14 h-14 rounded-full object-cover mb-3" />
                                <div v-else class="w-14 h-14 rounded-full bg-indigo-100 flex items-center justify-center text-lg font-bold text-indigo-600 mb-3">{{ personalHeaderName?.charAt(0)?.toUpperCase() }}</div>
                                <p class="text-sm font-medium text-gray-700 mb-1">Chat with {{ personalHeaderName }}</p>
                                <p class="text-xs text-gray-400 leading-relaxed">{{ personalSelectedId === 'creator' ? `Ask me anything about ${community.name}!` : 'View the conversation' }}</p>
                            </div>

                            <div v-for="msg in personalMessages" :key="msg.id" class="flex gap-2.5" :class="msg.role === 'user' ? 'justify-end' : ''">
                                <div v-if="msg.role === 'creator'" class="shrink-0 mt-0.5">
                                    <img v-if="community.owner?.avatar" :src="community.owner.avatar" class="w-7 h-7 rounded-full object-cover" />
                                    <div v-else class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">{{ community.owner?.name?.charAt(0)?.toUpperCase() }}</div>
                                </div>
                                <div class="max-w-[75%]">
                                    <div class="px-3 py-2 rounded-2xl text-sm leading-relaxed" :class="msg.role === 'user' ? 'bg-indigo-600 text-white rounded-br-md' : 'bg-gray-100 text-gray-700 rounded-bl-md'">
                                        <p class="whitespace-pre-wrap break-words">{{ msg.content ?? msg.text }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Typing indicator -->
                            <div v-if="personalLoading" class="flex gap-2.5">
                                <div class="shrink-0 mt-0.5">
                                    <img v-if="community.owner?.avatar" :src="community.owner.avatar" class="w-7 h-7 rounded-full object-cover" />
                                    <div v-else class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">{{ community.owner?.name?.charAt(0)?.toUpperCase() }}</div>
                                </div>
                                <div class="bg-gray-100 px-3 py-2 rounded-2xl rounded-bl-md">
                                    <div class="flex gap-1 items-center">
                                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Input -->
                        <div class="px-3 py-3 border-t border-gray-100 shrink-0">
                            <form @submit.prevent="sendPersonalMessage" class="flex items-end gap-2">
                                <textarea v-model="personalInput" rows="1" :placeholder="personalSelectedId === 'creator' ? `Message ${community.owner?.name}...` : `Reply to ${personalHeaderName}...`" class="flex-1 px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none bg-gray-50" style="max-height: 80px;" @keydown.enter.exact.prevent="sendPersonalMessage"></textarea>
                                <button type="submit" :disabled="!personalInput.trim() || personalLoading || personalSending" class="shrink-0 w-8 h-8 flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white rounded-xl transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                </button>
                            </form>
                        </div>
                    </template>
                </template>

                <!-- ═══ AI Assistant tab (creator only) ═══ -->
                <template v-else-if="activeTab === 'assistant'">
                    <div class="px-5 py-4 border-b border-gray-100 shrink-0">
                        <h2 class="text-sm font-semibold text-gray-900">AI Assistant Settings</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Set instructions for how your AI assistant responds to members.</p>
                    </div>
                    <div class="flex-1 overflow-y-auto p-5">
                        <div class="max-w-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Your Instructions</label>
                            <textarea v-model="aiInstructions" rows="10" placeholder="e.g. I'm Coach Francis, a faceless marketing expert. I always encourage my members and give actionable advice." class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none" />
                            <p class="mt-1.5 text-xs text-gray-400">Write as yourself. The assistant uses your community content to give accurate answers.</p>
                            <div class="flex items-center gap-3 mt-4">
                                <button @click="saveAiInstructions" :disabled="aiSaving" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors">{{ aiSaving ? 'Saving...' : 'Save Instructions' }}</button>
                                <p v-if="aiSaved" class="text-sm text-green-600 font-medium">Saved!</p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';

const props = defineProps({
    community:         Object,
    messages:          Array,
    affiliate:         Object,
    telegramConnected: { type: Boolean, default: false },
    isOwner:           { type: Boolean, default: false },
    chatbotUsers:      { type: Array, default: () => [] },
});

const page      = usePage();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

// ── Tabs ──────────────────────────────────────────────────────────────────────
const activeTab = ref('community');
const tabs = computed(() => {
    const t = [
        { key: 'community', label: 'Community' },
        { key: 'personal',  label: 'Personal' },
    ];
    if (props.isOwner) t.push({ key: 'assistant', label: 'AI Assistant' });
    return t;
});

function switchTab(key) {
    activeTab.value = key;
    if (key === 'personal' && !props.isOwner && !personalSelectedId.value) {
        selectCreatorChat();
    }
}

// ── Community tab ─────────────────────────────────────────────────────────────
const groupMessages   = ref([...props.messages]);
const groupContent    = ref('');
const sending         = ref(false);
const messagesEl      = ref(null);
const inputEl         = ref(null);
const fileInputEl     = ref(null);
const mediaFile       = ref(null);
const mediaPreviewUrl = ref(null);

function onFileSelected(e) { const f = e.target.files[0]; if (!f) return; mediaFile.value = f; mediaPreviewUrl.value = URL.createObjectURL(f); }
function clearMedia() { mediaFile.value = null; mediaPreviewUrl.value = null; if (fileInputEl.value) fileInputEl.value.value = ''; }
const avatarColors = ['bg-indigo-100 text-indigo-600','bg-violet-100 text-violet-600','bg-pink-100 text-pink-600','bg-emerald-100 text-emerald-600','bg-amber-100 text-amber-600','bg-sky-100 text-sky-600'];
function msgGroupKey(msg) { return msg.telegram_author ? `tg:${msg.telegram_author}` : `u:${msg.user?.id}`; }
function avatarColor(name) { return avatarColors[(name?.charCodeAt(0) ?? 0) % avatarColors.length]; }
function formatTime(d) { return d ? new Date(d).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' }) : ''; }
function formatRelativeTime(d) { if (!d) return ''; const diff = Math.floor((Date.now() - new Date(d)) / 86400000); if (diff === 0) return formatTime(d); if (diff === 1) return 'Yesterday'; if (diff < 7) return new Date(d).toLocaleDateString('en-PH', { weekday: 'short' }); return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }); }
function autoResize(e) { e.target.style.height = 'auto'; e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px'; }
function scrollToBottom(smooth = false) { nextTick(() => { messagesEl.value?.scrollTo({ top: messagesEl.value.scrollHeight, behavior: smooth ? 'smooth' : 'instant' }); }); }
function canDelete(msg) { const u = page.props.auth?.user; return u && (msg.user?.id === u.id || u.is_super_admin); }
async function deleteMessage(msg) { if (!confirm('Delete this message?')) return; try { await axios.delete(`/communities/${props.community.slug}/chat/${msg.id}`, { headers: { 'X-CSRF-TOKEN': csrfToken } }); groupMessages.value = groupMessages.value.filter(m => m.id !== msg.id); } catch {} }

async function send() {
    const text = groupContent.value.trim();
    if ((!text && !mediaFile.value) || sending.value) return;
    sending.value = true;
    const savedText = text, savedFile = mediaFile.value;
    groupContent.value = ''; clearMedia();
    if (inputEl.value) inputEl.value.style.height = 'auto';
    try {
        const fd = new FormData();
        if (savedText) fd.append('content', savedText);
        if (savedFile) fd.append('media', savedFile);
        const res = await axios.post(`/communities/${props.community.slug}/chat`, fd, { headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'multipart/form-data' } });
        groupMessages.value.push(res.data.message);
        scrollToBottom(true);
    } catch { groupContent.value = savedText; }
    finally { sending.value = false; }
}

// ── Personal tab ──────────────────────────────────────────────────────────────
const conversationList   = ref([...props.chatbotUsers]);
const personalSelectedId = ref(null);
const personalMessages   = ref([]);
const personalInput      = ref('');
const personalSending    = ref(false);
const personalLoading    = ref(false);
const personalChatEl     = ref(null);
let   personalConvId     = null;
let   personalPollTimer  = null;
let   personalLastMsgId  = 0;

const personalHeaderName = computed(() => {
    if (personalSelectedId.value === 'creator') return props.community.owner?.name;
    const u = conversationList.value.find(x => x.id === personalSelectedId.value);
    return u?.name ?? '';
});
const personalHeaderAvatar = computed(() => {
    if (personalSelectedId.value === 'creator') return props.community.owner?.avatar;
    const u = conversationList.value.find(x => x.id === personalSelectedId.value);
    return u?.avatar ?? null;
});

function scrollPersonalToBottom(smooth = false) {
    nextTick(() => personalChatEl.value?.scrollTo({ top: personalChatEl.value?.scrollHeight, behavior: smooth ? 'smooth' : 'instant' }));
}

async function selectCreatorChat() {
    personalSelectedId.value = 'creator';
    personalInput.value = '';
    personalMessages.value = [];
    personalConvId = null;
    try {
        const { data } = await axios.get(`/communities/${props.community.slug}/chatbot/history`);
        personalMessages.value = data.messages || [];
        personalLastMsgId = personalMessages.value.length ? Math.max(...personalMessages.value.map(m => m.id || 0)) : 0;
        scrollPersonalToBottom();
    } catch {}
    startPersonalPolling();
}

async function selectMemberChat(u) {
    personalSelectedId.value = u.id;
    personalInput.value = '';
    personalMessages.value = [];
    try {
        const { data } = await axios.get(`/communities/${props.community.slug}/chatbot/poll`, { params: { after: 0, user_id: u.id } });
        personalMessages.value = data.messages || [];
        personalLastMsgId = personalMessages.value.length ? Math.max(...personalMessages.value.map(m => m.id || 0)) : 0;
        scrollPersonalToBottom();
    } catch {}
    startPersonalPolling();
}

function startPersonalPolling() {
    stopPersonalPolling();
    personalPollTimer = setInterval(async () => {
        if (!personalSelectedId.value) return;
        const params = { after: personalLastMsgId };
        if (props.isOwner && personalSelectedId.value !== 'creator') params.user_id = personalSelectedId.value;
        try {
            const { data } = await axios.get(`/communities/${props.community.slug}/chatbot/poll`, { params });
            if (data.messages?.length) {
                for (const msg of data.messages) {
                    if (!personalMessages.value.some(m => m.id === msg.id)) {
                        personalMessages.value.push(msg);
                        if (msg.id > personalLastMsgId) personalLastMsgId = msg.id;
                    }
                }
                scrollPersonalToBottom(true);
            }
        } catch {}
    }, 5000);
}
function stopPersonalPolling() { if (personalPollTimer) { clearInterval(personalPollTimer); personalPollTimer = null; } }

async function sendPersonalMessage() {
    const text = personalInput.value.trim();
    if (!text || personalSending.value || personalLoading.value) return;

    // Creator replying to a member
    if (props.isOwner && personalSelectedId.value !== 'creator') {
        personalSending.value = true;
        personalInput.value = '';
        try {
            const { data } = await axios.post(`/communities/${props.community.slug}/chatbot/reply`, {
                user_id: personalSelectedId.value, message: text,
            }, { headers: { 'X-CSRF-TOKEN': csrfToken } });
            personalMessages.value.push(data.message);
            if (data.message.id > personalLastMsgId) personalLastMsgId = data.message.id;
            scrollPersonalToBottom(true);
        } catch { personalInput.value = text; }
        finally { personalSending.value = false; }
        return;
    }

    // Member chatting with creator (AI)
    personalMessages.value.push({ role: 'user', text, content: text });
    personalInput.value = '';
    personalLoading.value = true;
    scrollPersonalToBottom(true);
    try {
        const res = await axios.post(`/communities/${props.community.slug}/chatbot`, {
            message: text, conversation_id: personalConvId,
        }, { headers: { 'X-CSRF-TOKEN': csrfToken } });
        personalConvId = res.data.conversation_id;
        personalMessages.value.push({ role: 'creator', text: res.data.message, content: res.data.message });
        // Reload to get proper IDs
        const { data } = await axios.get(`/communities/${props.community.slug}/chatbot/history`);
        if (data.messages?.length) {
            personalMessages.value = data.messages;
            personalLastMsgId = Math.max(...data.messages.map(m => m.id || 0));
        }
    } catch {
        personalMessages.value.push({ role: 'creator', text: 'Sorry, something went wrong. Try again in a bit!', content: 'Sorry, something went wrong. Try again in a bit!' });
    } finally { personalLoading.value = false; scrollPersonalToBottom(true); }
}

// ── AI Assistant tab ──────────────────────────────────────────────────────────
const aiInstructions = ref(props.community.ai_chatbot_instructions ?? '');
const aiSaving = ref(false);
const aiSaved  = ref(false);

async function saveAiInstructions() {
    aiSaving.value = true;
    try {
        await axios.patch(`/communities/${props.community.slug}`, { name: props.community.name, ai_chatbot_instructions: aiInstructions.value }, { headers: { 'X-CSRF-TOKEN': csrfToken } });
        aiSaved.value = true;
        setTimeout(() => (aiSaved.value = false), 4000);
    } catch {}
    finally { aiSaving.value = false; }
}

// ── Echo / Reverb ─────────────────────────────────────────────────────────────
let echoChannel = null;
function onIncomingMessage(e) { const msg = e.message; if (groupMessages.value.some(m => m.id === msg.id)) return; const atBottom = messagesEl.value ? messagesEl.value.scrollHeight - messagesEl.value.scrollTop - messagesEl.value.clientHeight < 100 : true; groupMessages.value.push(msg); if (atBottom) scrollToBottom(true); }
function onDeletedMessage(e) { groupMessages.value = groupMessages.value.filter(m => m.id !== e.message_id); }

onMounted(() => {
    scrollToBottom();
    if (window.Echo) {
        echoChannel = window.Echo.join(`community.${props.community.id}.chat`)
            .listen('ChatMessageSent', onIncomingMessage)
            .listen('ChatMessageDeleted', onDeletedMessage);
    }
});
onBeforeUnmount(() => {
    stopPersonalPolling();
    if (echoChannel) { echoChannel.stopListening('ChatMessageSent'); echoChannel.stopListening('ChatMessageDeleted'); window.Echo.leave(`community.${props.community.id}.chat`); }
});
</script>
