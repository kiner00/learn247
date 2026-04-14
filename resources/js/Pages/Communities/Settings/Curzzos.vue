<template>
    <CommunitySettingsLayout :community="community">
        <div class="flex items-center justify-between mb-1">
            <h2 class="text-2xl font-bold text-gray-900">
                Curzzos
                <span class="ml-1.5 text-sm font-normal text-gray-400">{{ curzzos.length }}</span>
            </h2>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full">⭐ Pro</span>
                <button
                    v-if="creatorPlan === 'pro' && !showForm"
                    @click="showForm = true"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors"
                >
                    + New Curzzo
                </button>
            </div>
        </div>
        <p class="text-sm text-gray-500 mb-6">Create custom AI bots that your members can chat with. Each Curzzo has its own personality, expertise, and instructions.</p>

        <!-- Locked for non-Pro -->
        <div v-if="creatorPlan !== 'pro'" class="rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-6 text-center">
            <p class="text-sm font-semibold text-indigo-800 mb-1">Creator Pro feature</p>
            <p class="text-xs text-indigo-600 mb-3">Upgrade to create custom AI bots for your community.</p>
            <Link href="/creator/plan" class="inline-block px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                Upgrade to Creator Pro →
            </Link>
        </div>

        <!-- PRO content -->
        <template v-else>
            <NewCurzzoForm
                v-if="showForm"
                :community="community"
                :model-tiers="modelTiers"
                @cancel="showForm = false"
                @created="showForm = false"
            />

            <!-- Curzzo card grid (draggable) -->
            <template v-if="localCurzzos.length">
                <p class="text-xs text-gray-400 mb-3">Drag to reorder. {{ localCurzzos.length }} / 5 bots</p>
                <draggable
                    v-model="localCurzzos"
                    item-key="id"
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5"
                    handle=".drag-handle"
                    @end="onReorder"
                >
                    <template #item="{ element: bot }">
                        <div class="relative group h-full">
                            <div class="flex flex-col h-full bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all border border-gray-100">
                                <!-- Cover image -->
                                <div class="relative aspect-video bg-gray-900 overflow-hidden shrink-0">
                                    <img v-if="bot.cover_image" :src="bot.cover_image" :alt="bot.name"
                                        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />
                                    <div v-else class="w-full h-full bg-gradient-to-br from-indigo-600 to-purple-700 flex items-center justify-center">
                                        <span class="text-4xl font-black text-white/20 select-none">{{ bot.name.charAt(0).toUpperCase() }}</span>
                                    </div>
                                    <!-- Avatar overlay -->
                                    <div v-if="bot.avatar" class="absolute bottom-2.5 left-2.5 w-8 h-8 rounded-full border-2 border-white overflow-hidden shadow-sm">
                                        <img :src="bot.avatar" :alt="bot.name" class="w-full h-full object-cover" />
                                    </div>
                                </div>
                                <!-- Content -->
                                <div class="p-4 flex flex-col flex-1">
                                    <div class="flex items-start justify-between gap-2 mb-1">
                                        <h3 class="font-bold text-gray-900 group-hover:text-indigo-700 transition-colors line-clamp-1">{{ bot.name }}</h3>
                                        <span v-if="bot.access_type === 'free' || !bot.access_type" class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-green-100 text-green-700">FREE</span>
                                        <span v-else-if="bot.access_type === 'inclusive'" class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700">INCLUDED</span>
                                        <span v-else-if="bot.access_type === 'member_once'" class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700">ONE-TIME</span>
                                        <span v-else class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700">
                                            ₱{{ Number(bot.price).toLocaleString() }}{{ bot.access_type === 'paid_monthly' ? '/mo' : '' }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500 line-clamp-2 leading-relaxed flex-1">{{ bot.description ?? '' }}</p>
                                    <div class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-100">
                                        <span class="px-1.5 py-0.5 text-[10px] font-bold rounded-full"
                                            :class="bot.model_tier === 'pro' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-500'">
                                            {{ tierMap[bot.model_tier] ?? bot.model_tier }}
                                        </span>
                                        <span v-if="bot.is_active" class="px-1.5 py-0.5 text-[10px] font-bold bg-green-100 text-green-700 rounded-full">Active</span>
                                        <span v-else class="px-1.5 py-0.5 text-[10px] font-bold bg-gray-100 text-gray-500 rounded-full">Inactive</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Owner controls overlay -->
                            <div class="absolute top-2.5 left-2.5 flex gap-1.5 z-10">
                                <div class="drag-handle w-7 h-7 bg-black/50 hover:bg-black/80 text-white rounded-full flex items-center justify-center cursor-grab active:cursor-grabbing" title="Drag to reorder">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16"/>
                                    </svg>
                                </div>
                                <button @click.prevent="openEdit(bot)"
                                    class="w-7 h-7 bg-black/50 hover:bg-black/80 text-white rounded-full flex items-center justify-center transition-colors" title="Edit">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 11l6-6 3 3-6 6H9v-3z"/>
                                    </svg>
                                </button>
                                <button @click.prevent="toggleActive(bot)"
                                    :class="['w-7 h-7 rounded-full flex items-center justify-center transition-colors text-white',
                                        bot.is_active ? 'bg-green-500/80 hover:bg-green-600' : 'bg-red-500/80 hover:bg-red-600']"
                                    :title="bot.is_active ? 'Active · click to deactivate' : 'Inactive · click to activate'">
                                    <svg v-if="bot.is_active" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                    </svg>
                                </button>
                                <button @click.prevent="deleteBot(bot)"
                                    class="w-7 h-7 bg-black/50 hover:bg-red-600 text-white rounded-full flex items-center justify-center transition-colors" title="Delete">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                            <!-- Inactive badge -->
                            <div v-if="!bot.is_active" class="absolute bottom-[calc(56%+8px)] right-2.5 z-10">
                                <span class="px-2 py-0.5 bg-yellow-400 text-yellow-900 text-[10px] font-bold rounded-full uppercase tracking-wide">Inactive</span>
                            </div>
                        </div>
                    </template>
                </draggable>
            </template>

            <!-- Empty state -->
            <div v-if="!curzzos.length && !showForm" class="bg-white border border-gray-200 rounded-2xl p-12 text-center">
                <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-indigo-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 mb-1">No Curzzos yet</h3>
                <p class="text-sm text-gray-500 mb-4">Create your first AI bot to help engage your community members.</p>
                <button @click="showForm = true"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    Create your first Curzzo
                </button>
            </div>
        </template>

        <!-- Edit Curzzo modal -->
        <Teleport to="body">
            <div v-if="editingBot" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="editingBot = null">
                <div class="bg-white rounded-2xl w-full max-w-lg p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                    <h2 class="text-base font-bold text-gray-900 mb-4">Edit Curzzo</h2>
                    <form @submit.prevent="submitEdit">
                        <input v-model="editForm.name" type="text" required maxlength="100" placeholder="Curzzo title"
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-2" />
                        <textarea v-model="editForm.description" rows="2" placeholder="Description (optional)" maxlength="500"
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none mb-3" />

                        <!-- Access type -->
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Access</label>
                            <div class="flex gap-2">
                                <label :class="['flex-1 cursor-pointer rounded-lg border-2 p-2.5 text-center transition-all',
                                    editForm.access_type === 'free' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                                    <input type="radio" value="free" v-model="editForm.access_type" class="sr-only" />
                                    <div class="text-base mb-0.5">🌐</div>
                                    <div class="text-xs font-semibold text-gray-800">Free</div>
                                    <div class="text-[10px] text-gray-400 leading-tight mt-0.5">Anyone can access</div>
                                </label>
                                <label :class="['flex-1 cursor-pointer rounded-lg border-2 p-2.5 text-center transition-all',
                                    editForm.access_type === 'inclusive' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                                    <input type="radio" value="inclusive" v-model="editForm.access_type" class="sr-only" />
                                    <div class="text-base mb-0.5">⭐</div>
                                    <div class="text-xs font-semibold text-gray-800">Included</div>
                                    <div class="text-[10px] text-gray-400 leading-tight mt-0.5">Members only</div>
                                </label>
                                <label :class="['flex-1 cursor-pointer rounded-lg border-2 p-2.5 text-center transition-all',
                                    editForm.access_type === 'member_once' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                                    <input type="radio" value="member_once" v-model="editForm.access_type" class="sr-only" />
                                    <div class="text-base mb-0.5">🎟️</div>
                                    <div class="text-xs font-semibold text-gray-800">One-Time</div>
                                    <div class="text-[10px] text-gray-400 leading-tight mt-0.5">Past members included</div>
                                </label>
                                <label :class="['flex-1 cursor-pointer rounded-lg border-2 p-2.5 text-center transition-all',
                                    isPaidType(editForm.access_type) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']"
                                    @click="selectPaidIfNeeded(editForm)">
                                    <div class="text-base mb-0.5">💳</div>
                                    <div class="text-xs font-semibold text-gray-800">Paid</div>
                                    <div class="text-[10px] text-gray-400 leading-tight mt-0.5">Separate payment</div>
                                </label>
                            </div>
                            <div v-if="isPaidType(editForm.access_type)" class="mt-2 flex gap-2">
                                <label :class="['flex-1 cursor-pointer rounded-lg border px-3 py-2 flex items-center gap-2 transition-all',
                                    editForm.access_type === 'paid_once' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                                    <input type="radio" value="paid_once" v-model="editForm.access_type" class="accent-indigo-600" />
                                    <div>
                                        <div class="text-xs font-semibold text-gray-800">One-time</div>
                                        <div class="text-[10px] text-gray-400">Pay once, access forever</div>
                                    </div>
                                </label>
                                <label :class="['flex-1 cursor-pointer rounded-lg border px-3 py-2 flex items-center gap-2 transition-all',
                                    editForm.access_type === 'paid_monthly' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                                    <input type="radio" value="paid_monthly" v-model="editForm.access_type" class="accent-indigo-600" />
                                    <div>
                                        <div class="text-xs font-semibold text-gray-800">Monthly</div>
                                        <div class="text-[10px] text-gray-400">Recurring monthly payment</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Price -->
                        <div v-if="isPaidType(editForm.access_type)" class="mb-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Price (PHP)
                                <span class="text-gray-400 font-normal">{{ editForm.access_type === 'paid_monthly' ? '/ month' : '· one-time' }}</span>
                            </label>
                            <input v-model="editForm.price" type="number" min="1" step="0.01" required placeholder="e.g. 1500"
                                class="w-48 px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>

                        <!-- Affiliate commission -->
                        <div v-if="isPaidType(editForm.access_type)" class="mb-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Affiliate commission
                                <span class="text-gray-400 font-normal">· %</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <input v-model="editForm.affiliate_commission_rate" type="number" min="0" max="100" step="1" placeholder="e.g. 30"
                                    class="w-24 px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-500">%</span>
                            </div>
                        </div>

                        <!-- Cover image -->
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Cover image</label>
                            <div class="relative mb-2 aspect-video rounded-lg overflow-hidden border border-gray-200 bg-gray-900">
                                <img v-if="editCoverPreview || editingBot.cover_image"
                                    :src="editCoverPreview || editingBot.cover_image"
                                    class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full bg-gradient-to-br from-indigo-600 to-purple-700 flex items-center justify-center">
                                    <span class="text-3xl font-black text-white/20">{{ editingBot.name.charAt(0) }}</span>
                                </div>
                                <label class="absolute inset-0 flex items-center justify-center bg-black/30 hover:bg-black/50 cursor-pointer transition-colors group/img">
                                    <span class="text-white text-xs font-semibold bg-black/50 px-3 py-1.5 rounded-full group-hover/img:bg-black/70">
                                        {{ editCoverPreview ? 'Change photo' : 'Upload photo' }}
                                    </span>
                                    <input ref="editCoverInput" type="file" accept="image/*" class="hidden" @change="onEditCoverChange" />
                                </label>
                            </div>
                            <p class="text-xs text-gray-400">Recommended: 1280 x 720 px</p>
                        </div>

                        <!-- Preview video -->
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Preview video</label>
                            <div v-if="editVideoPreview || editingBot.preview_video" class="relative mb-2 aspect-video rounded-lg overflow-hidden border border-gray-200 bg-black">
                                <video :src="editVideoPreview || editingBot.preview_video" class="w-full h-full object-cover" muted playsinline />
                                <button type="button" @click="removeEditVideo"
                                    class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-black/50 text-white flex items-center justify-center text-xs hover:bg-black/70">x</button>
                            </div>
                            <div v-if="editVideoUploading" class="mb-2">
                                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500 rounded-full transition-all" :style="{ width: `${editVideoUploadProgress}%` }" />
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Uploading... {{ editVideoUploadProgress }}%</p>
                            </div>
                            <p v-if="editVideoUploadError" class="text-xs text-red-500 mb-1">{{ editVideoUploadError }}</p>
                            <label v-if="!editVideoUploading" class="flex items-center gap-2 w-fit cursor-pointer px-3 py-1.5 border border-gray-300 rounded-lg text-xs text-gray-600 hover:bg-gray-50 transition-colors">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                {{ (editVideoPreview || editingBot.preview_video) ? 'Change video' : 'Upload preview' }}
                                <input ref="editVideoInput" type="file" accept="video/mp4,video/quicktime,video/webm" class="hidden" @change="onEditVideoChange" />
                            </label>
                            <p class="text-xs text-gray-400 mt-1">MP4 recommended, 1280 x 720 px, max 500 MB.</p>
                        </div>

                        <!-- Instructions -->
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Curzzo Instructions</label>
                            <textarea v-model="editForm.instructions" rows="6" required maxlength="20000"
                                placeholder="Define the bot's behavior, knowledge, and rules..."
                                class="w-full px-3.5 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
                        </div>

                        <!-- Model Tier -->
                        <div v-if="modelTiers.length" class="mb-3">
                            <label class="block text-xs font-medium text-gray-600 mb-2">AI Model</label>
                            <div class="grid grid-cols-2 gap-3">
                                <button v-for="tier in modelTiers" :key="tier.value"
                                    type="button" @click="editForm.model_tier = tier.value"
                                    :class="[
                                        'relative rounded-xl border-2 p-3 text-left transition-all',
                                        editForm.model_tier === tier.value
                                            ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600'
                                            : 'border-gray-200 hover:border-gray-300'
                                    ]">
                                    <div class="text-sm font-semibold text-gray-900">{{ tier.label }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ tier.description }}</div>
                                </button>
                            </div>
                        </div>

                        <!-- Personality -->
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Personality</label>
                            <div class="grid grid-cols-2 gap-3 mb-2">
                                <div>
                                    <label class="block text-[11px] text-gray-500 mb-0.5">Tone</label>
                                    <select v-model="editForm.personality_tone"
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option v-for="t in TONES" :key="t.value" :value="t.value">{{ t.label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] text-gray-500 mb-0.5">Response Style</label>
                                    <select v-model="editForm.personality_response_style"
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option v-for="s in STYLES" :key="s.value" :value="s.value">{{ s.label }}</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] text-gray-500 mb-0.5">Expertise</label>
                                <textarea v-model="editForm.personality_expertise" rows="3" maxlength="5000"
                                    placeholder="e.g. Script writing, Sales strategies"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
                            </div>
                        </div>

                        <div class="flex gap-2 justify-end">
                            <button type="button" @click="editingBot = null; editCoverPreview = null"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancel</button>
                            <button type="submit" :disabled="editSaving"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                {{ editSaving ? 'Saving...' : 'Save changes' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
        <ConfirmModal :show="confirmShow" :title="confirmTitle" :message="confirmMessage" :confirm-label="confirmLabel" :destructive="confirmDestructive" @confirm="onConfirm" @cancel="onCancel" />
    </CommunitySettingsLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import draggable from 'vuedraggable';
import CommunitySettingsLayout from '@/Layouts/CommunitySettingsLayout.vue';
import NewCurzzoForm from '@/Components/NewCurzzoForm.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    community:  Object,
    isPro:      { type: Boolean, default: false },
    curzzos:    { type: Array, default: () => [] },
    modelTiers: { type: Array, default: () => [] },
});

const page = usePage();
const creatorPlan = computed(() => page.props.auth.user?.creator_plan ?? 'free');
const { communityPath } = useCommunityUrl(props.community.slug);
const { show: confirmShow, title: confirmTitle, message: confirmMessage, confirmLabel, destructive: confirmDestructive, ask, onConfirm, onCancel } = useConfirm();

const tierMap = Object.fromEntries(props.modelTiers.map(t => [t.value, t.label]));

const TONES = [
    { value: '', label: 'Default' },
    { value: 'friendly', label: 'Friendly' },
    { value: 'professional', label: 'Professional' },
    { value: 'casual', label: 'Casual' },
    { value: 'formal', label: 'Formal' },
];

const STYLES = [
    { value: '', label: 'Default' },
    { value: 'concise', label: 'Concise' },
    { value: 'detailed', label: 'Detailed' },
    { value: 'conversational', label: 'Conversational' },
];

const isPaidType = (type) => type === 'paid_once' || type === 'paid_monthly';
const selectPaidIfNeeded = (f) => { if (!isPaidType(f.access_type)) f.access_type = 'paid_once'; };

// ── Local copy for draggable ───────────────────────────────────────────────
const localCurzzos = ref([...props.curzzos]);
watch(() => props.curzzos, (val) => { localCurzzos.value = [...val]; });

function onReorder() {
    router.post(communityPath('/curzzos/reorder'), {
        ids: localCurzzos.value.map(c => c.id),
    }, { preserveScroll: true });
}

// ── Create form toggle ─────────────────────────────────────────────────────
const showForm = ref(false);

// ── Edit ─────────────────────────────────────────────────────────────────────
const editingBot       = ref(null);
const editSaving       = ref(false);
const editCoverPreview = ref(null);
const editCoverInput   = ref(null);
const editVideoPreview       = ref(null);
const editVideoInput         = ref(null);
const editVideoUploading     = ref(false);
const editVideoUploadProgress = ref(0);
const editVideoUploadError   = ref('');

const editForm = ref({
    name: '', description: '', instructions: '',
    access_type: 'free', price: '', affiliate_commission_rate: '',
    cover_image: null, remove_cover_image: false,
    preview_video: null, remove_preview_video: false,
    model_tier: 'basic',
    personality_tone: '', personality_response_style: '', personality_expertise: '',
});

function openEdit(bot) {
    editingBot.value = bot;
    editCoverPreview.value = null;
    editVideoPreview.value = null;
    editForm.value = {
        name: bot.name,
        description: bot.description ?? '',
        instructions: bot.instructions ?? '',
        access_type: bot.access_type ?? 'free',
        price: bot.price ?? '',
        affiliate_commission_rate: bot.affiliate_commission_rate ?? '',
        cover_image: null,
        remove_cover_image: false,
        preview_video: null,
        remove_preview_video: false,
        model_tier: bot.model_tier ?? 'basic',
        personality_tone: bot.personality?.tone ?? '',
        personality_response_style: bot.personality?.response_style ?? '',
        personality_expertise: bot.personality?.expertise ?? '',
    };
}

function onEditCoverChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    editForm.value.cover_image = file;
    editForm.value.remove_cover_image = false;
    editCoverPreview.value = URL.createObjectURL(file);
}

async function onEditVideoChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    editVideoUploading.value = true;
    editVideoUploadProgress.value = 0;
    editVideoUploadError.value = '';

    try {
        const { data } = await axios.post(communityPath('/curzzos/preview-videos'), {
            filename: file.name,
            content_type: file.type,
            size: file.size,
        });

        const { default: rawAxios } = await import('axios');
        const s3Client = rawAxios.create({ withCredentials: false });
        await s3Client.put(data.upload_url, file, {
            headers: { 'Content-Type': file.type },
            onUploadProgress: (p) => { editVideoUploadProgress.value = Math.round((p.loaded / p.total) * 100); },
        });

        editForm.value.preview_video = data.key;
        editForm.value.remove_preview_video = false;
        editVideoPreview.value = URL.createObjectURL(file);
    } catch (err) {
        editVideoUploadError.value = err.response?.data?.error || err.response?.data?.message || 'Upload failed. Please try again.';
    } finally {
        editVideoUploading.value = false;
        e.target.value = '';
    }
}

function removeEditVideo() {
    editForm.value.preview_video = null;
    editForm.value.remove_preview_video = true;
    editVideoPreview.value = null;
    editVideoUploadError.value = '';
    if (editVideoInput.value) editVideoInput.value.value = '';
    if (editingBot.value) editingBot.value = { ...editingBot.value, preview_video: null };
}

function submitEdit() {
    editSaving.value = true;

    const formData = new FormData();
    formData.append('_method', 'PATCH');
    formData.append('name', editForm.value.name);
    formData.append('description', editForm.value.description);
    formData.append('instructions', editForm.value.instructions);
    formData.append('access_type', editForm.value.access_type);
    formData.append('model_tier', editForm.value.model_tier);
    formData.append('is_active', editingBot.value.is_active ? '1' : '0');

    if (editForm.value.cover_image) formData.append('cover_image', editForm.value.cover_image);
    if (editForm.value.remove_cover_image) formData.append('remove_cover_image', '1');
    if (editForm.value.preview_video) formData.append('preview_video', editForm.value.preview_video);
    if (editForm.value.remove_preview_video) formData.append('remove_preview_video', '1');

    if (isPaidType(editForm.value.access_type)) {
        if (editForm.value.price) formData.append('price', editForm.value.price);
        formData.append('currency', 'PHP');
        formData.append('billing_type', editForm.value.access_type === 'paid_monthly' ? 'monthly' : 'one_time');
        if (editForm.value.affiliate_commission_rate) formData.append('affiliate_commission_rate', editForm.value.affiliate_commission_rate);
    }

    if (editForm.value.personality_tone) formData.append('personality[tone]', editForm.value.personality_tone);
    if (editForm.value.personality_response_style) formData.append('personality[response_style]', editForm.value.personality_response_style);
    if (editForm.value.personality_expertise) formData.append('personality[expertise]', editForm.value.personality_expertise);

    router.post(communityPath(`/curzzos/${editingBot.value.id}`), formData, {
        preserveScroll: true,
        onSuccess: () => {
            editingBot.value = null;
            editCoverPreview.value = null;
            editVideoPreview.value = null;
        },
        onError: (errors) => {
            console.error('Update curzzo errors:', errors);
        },
        onFinish: () => { editSaving.value = false; },
    });
}

function toggleActive(bot) {
    router.post(communityPath(`/curzzos/${bot.id}/toggle-active`), {}, { preserveScroll: true });
}

async function deleteBot(bot) {
    if (!await ask({ title: 'Delete Curzzo', message: `Delete "${bot.name}"? All conversation history will be lost.`, confirmLabel: 'Delete', destructive: true })) return;
    router.delete(communityPath(`/curzzos/${bot.id}`), { preserveScroll: true });
}
</script>
