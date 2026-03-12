<template>
    <AppLayout :title="`${community.name} · Settings`">
        <div class="max-w-2xl">
            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                <Link :href="`/communities/${community.slug}`" class="hover:text-indigo-600 transition-colors">
                    {{ community.name }}
                </Link>
                <span>/</span>
                <span>Settings</span>
            </div>

            <div class="flex items-center justify-between mb-8">
                <h1 class="text-2xl font-bold text-gray-900">Community Settings</h1>
                <Link
                    :href="`/communities/${community.slug}/analytics`"
                    class="flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors"
                >
                    <span>📊</span> Analytics
                </Link>
            </div>

            <!-- General settings -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                <h2 class="text-base font-semibold text-gray-900 mb-5">General</h2>
                <form @submit.prevent="save">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Community name <span class="text-red-500">*</span></label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="form.errors.name ? 'border-red-400' : 'border-gray-300'"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Category</label>
                            <select
                                v-model="form.category"
                                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                            >
                                <option value="">No category</option>
                                <option v-for="cat in CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                        </div>

                        <!-- Pricing requirements checklist -->
                        <div v-if="pricingGate && !pricingGate.can_enable_pricing" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <p class="text-sm font-semibold text-amber-800 mb-3">Complete these requirements to enable paid pricing:</p>
                            <ul class="space-y-2 text-sm">
                                <li class="flex items-center gap-2" :class="pricingGate.module_count >= 5 ? 'text-green-700' : 'text-gray-600'">
                                    <span>{{ pricingGate.module_count >= 5 ? '✅' : '☐' }}</span>
                                    <span>At least 5 modules <span class="text-gray-400">({{ pricingGate.module_count }}/5)</span></span>
                                </li>
                                <li class="flex items-center gap-2" :class="pricingGate.has_banner ? 'text-green-700' : 'text-gray-600'">
                                    <span>{{ pricingGate.has_banner ? '✅' : '☐' }}</span>
                                    <span>Banner image uploaded</span>
                                </li>
                                <li class="flex items-center gap-2" :class="pricingGate.has_description ? 'text-green-700' : 'text-gray-600'">
                                    <span>{{ pricingGate.has_description ? '✅' : '☐' }}</span>
                                    <span>Community description filled</span>
                                </li>
                                <li class="flex items-center gap-2" :class="pricingGate.profile_complete ? 'text-green-700' : 'text-gray-600'">
                                    <span>{{ pricingGate.profile_complete ? '✅' : '☐' }}</span>
                                    <span>Your profile complete (name, bio, avatar)</span>
                                </li>
                            </ul>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Price (₱ per month)</label>
                                <input
                                    v-model="form.price"
                                    type="number"
                                    min="0"
                                    step="1"
                                    :disabled="pricingGate && !pricingGate.can_enable_pricing && Number(form.price) === 0"
                                    class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    :class="form.errors.price ? 'border-red-400' : 'border-gray-300'"
                                />
                                <p v-if="form.errors.price" class="mt-1 text-xs text-red-600">{{ form.errors.price }}</p>
                                <p v-else class="mt-1 text-xs text-gray-400">Set to 0 for free access</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Currency</label>
                                <select
                                    v-model="form.currency"
                                    class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                                >
                                    <option value="PHP">PHP – Philippine Peso</option>
                                    <option value="USD">USD – US Dollar</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <input
                                id="is_private"
                                v-model="form.is_private"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <label for="is_private" class="text-sm text-gray-700">
                                Private community
                                <span class="text-gray-400">(only members can see content)</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 mt-6">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving...' : 'Save changes' }}
                        </button>
                        <p v-if="saved" class="text-sm text-green-600">Changes saved!</p>
                    </div>
                </form>
            </div>

            <!-- Banner & Avatar -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                <h2 class="text-base font-semibold text-gray-900 mb-5">Images</h2>
                <form @submit.prevent="saveImages">
                    <div class="space-y-5">
                        <!-- Banner -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Banner Image</label>
                            <div
                                v-if="coverPreview || community.cover_image"
                                class="relative mb-2 h-32 rounded-xl overflow-hidden border border-gray-200 group"
                            >
                                <img :src="coverPreview || community.cover_image" class="w-full h-full object-cover" alt="Banner preview" />
                                <button
                                    type="button"
                                    @click="removeCover"
                                    class="absolute top-2 right-2 w-7 h-7 rounded-full bg-black/50 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-black/70"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <label class="flex items-center gap-2 w-fit cursor-pointer px-3.5 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                {{ coverPreview || community.cover_image ? 'Change banner' : 'Upload banner' }}
                                <input ref="coverInput" type="file" accept="image/*" class="hidden" @change="onCoverChange" />
                            </label>
                            <p class="mt-1 text-xs text-gray-400">JPG, PNG, WebP — max 5 MB &nbsp;·&nbsp; <span class="font-medium text-gray-500">Recommended: 1920 × 480 px</span></p>
                        </div>

                        <!-- Avatar -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Community Avatar</label>
                            <div
                                v-if="avatarPreview || community.avatar"
                                class="relative mb-2 w-20 h-20 rounded-full overflow-hidden border border-gray-200 group"
                            >
                                <img :src="avatarPreview || community.avatar" class="w-full h-full object-cover" alt="Avatar preview" />
                                <button
                                    type="button"
                                    @click="removeAvatar"
                                    class="absolute inset-0 bg-black/40 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <label class="flex items-center gap-2 w-fit cursor-pointer px-3.5 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                {{ avatarPreview || community.avatar ? 'Change avatar' : 'Upload avatar' }}
                                <input ref="avatarInput" type="file" accept="image/*" class="hidden" @change="onAvatarChange" />
                            </label>
                            <p class="mt-1 text-xs text-gray-400">Shown as your community icon. JPG, PNG, WebP — max 5 MB</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 mt-6">
                        <button
                            type="submit"
                            :disabled="imageForm.processing"
                            class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                        >
                            {{ imageForm.processing ? 'Saving...' : 'Save images' }}
                        </button>
                        <p v-if="imagesSaved" class="text-sm text-green-600">Images saved!</p>
                    </div>
                </form>
            </div>

            <!-- Gallery Images -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                <h2 class="text-base font-semibold text-gray-900 mb-1">Gallery</h2>
                <p class="text-sm text-gray-500 mb-4">Add up to 8 images shown as a thumbnail strip on your About page.</p>

                <!-- Existing gallery -->
                <div v-if="community.gallery_images?.length" class="flex flex-wrap gap-2 mb-4">
                    <div
                        v-for="(img, i) in community.gallery_images"
                        :key="i"
                        class="relative w-24 h-16 rounded-lg overflow-hidden border border-gray-200 group"
                    >
                        <img :src="img" class="w-full h-full object-cover" />
                        <button
                            type="button"
                            @click="removeGalleryImage(i)"
                            class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-xs"
                        >✕</button>
                    </div>
                </div>

                <!-- Upload new -->
                <form v-if="!community.gallery_images || community.gallery_images.length < 8" @submit.prevent="uploadGalleryImage">
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer px-3.5 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            {{ galleryFile ? galleryFile.name : 'Choose image' }}
                            <input type="file" accept="image/*" class="hidden" @change="onGalleryFileChange" />
                        </label>
                        <button
                            type="submit"
                            :disabled="!galleryFile || galleryUploading"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                        >
                            {{ galleryUploading ? 'Uploading...' : 'Add image' }}
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-400">JPG, PNG, WebP — max 5 MB · {{ community.gallery_images?.length ?? 0 }}/8 images</p>
                </form>
                <p v-else class="text-xs text-amber-600">Maximum 8 images reached. Remove one to add more.</p>
            </div>

            <!-- Affiliate Program -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-base font-semibold text-gray-900">Affiliate Program</h2>
                    <Link :href="`/communities/${community.slug}/affiliates`"
                          class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        View affiliates →
                    </Link>
                </div>
                <p class="text-sm text-gray-500 mb-5">
                    Members can become affiliates and earn a commission for every new subscriber they refer.
                    The platform always takes 15% off the top.
                </p>
                <form @submit.prevent="saveAffiliate">
                    <div class="flex items-end gap-4">
                        <div class="flex-1 max-w-xs">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Affiliate Commission Rate (%)
                            </label>
                            <input
                                v-model="affiliateForm.affiliate_commission_rate"
                                type="number"
                                min="0"
                                max="85"
                                step="1"
                                placeholder="e.g. 50"
                                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="affiliateForm.errors.affiliate_commission_rate ? 'border-red-400' : ''"
                            />
                            <p class="mt-1 text-xs text-gray-400">
                                0 = disable affiliate program. Max 85 (platform takes 15%).
                            </p>
                            <p v-if="affiliateForm.errors.affiliate_commission_rate" class="mt-1 text-xs text-red-600">
                                {{ affiliateForm.errors.affiliate_commission_rate }}
                            </p>
                        </div>
                        <button
                            type="submit"
                            :disabled="affiliateForm.processing"
                            class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                        >
                            Save
                        </button>
                        <p v-if="affiliateSaved" class="text-sm text-green-600 self-center">Saved!</p>
                    </div>
                    <div v-if="community.affiliate_commission_rate" class="mt-3 p-3 bg-gray-50 rounded-lg text-xs text-gray-500">
                        Example split on ₱{{ community.price }} sale:
                        <strong class="text-red-500">Platform ₱{{ (community.price * 0.03).toFixed(2) }}</strong>
                        · <strong class="text-orange-600">Affiliate ₱{{ (community.price * community.affiliate_commission_rate / 100).toFixed(2) }}</strong>
                        · <strong class="text-green-700">You ₱{{ (community.price - community.price * 0.03 - community.price * community.affiliate_commission_rate / 100).toFixed(2) }}</strong>
                    </div>
                </form>
            </div>

            <!-- Announcement Blast -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                <h2 class="text-base font-semibold text-gray-900 mb-1">📢 Send Announcement</h2>
                <p class="text-sm text-gray-500 mb-5">Email all members of this community at once.</p>
                <form @submit.prevent="sendAnnouncement" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Subject</label>
                        <input v-model="announceForm.subject" type="text" required maxlength="200"
                            placeholder="e.g. New content dropped!"
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :class="announceForm.errors.subject ? 'border-red-400' : ''" />
                        <p v-if="announceForm.errors.subject" class="mt-1 text-xs text-red-600">{{ announceForm.errors.subject }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Message</label>
                        <textarea v-model="announceForm.message" rows="5" required maxlength="5000"
                            placeholder="Write your announcement here..."
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                            :class="announceForm.errors.message ? 'border-red-400' : ''" />
                        <p v-if="announceForm.errors.message" class="mt-1 text-xs text-red-600">{{ announceForm.errors.message }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="announceForm.processing"
                            class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                            {{ announceForm.processing ? 'Sending...' : 'Send to all members' }}
                        </button>
                        <p v-if="announceSent" class="text-sm text-green-600">Announcement sent!</p>
                    </div>
                </form>
            </div>

            <!-- Level perks -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                <h2 class="text-base font-semibold text-gray-900 mb-1">Level Perks</h2>
                <p class="text-sm text-gray-500 mb-4">
                    Set an unlock reward for each level. Members see this on the leaderboard as motivation.
                    Leave blank to show no perk for that level.
                </p>
                <form @submit.prevent="saveLevelPerks" class="space-y-2">
                    <div v-for="lvl in 9" :key="lvl" class="flex items-center gap-3">
                        <span class="w-16 text-xs font-semibold text-gray-500 shrink-0">Level {{ lvl }}</span>
                        <input
                            v-model="levelPerksForm[lvl]"
                            type="text"
                            :placeholder="lvl === 4 ? 'e.g. Chat with members' : 'e.g. Access to bonus content'"
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" :disabled="perksSaving"
                            class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                            Save perks
                        </button>
                        <p v-if="perksSaved" class="text-sm text-green-600">Saved!</p>
                    </div>
                </form>
            </div>

            <!-- Invite Members -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                <h2 class="text-base font-semibold text-gray-900 mb-1">✉️ Invite Members</h2>
                <p class="text-sm text-gray-500 mb-5">
                    Add existing members by email, or batch upload a CSV file (one email per row).
                    They'll receive a personal invite link granting instant access.
                </p>

                <!-- Tab toggle -->
                <div class="flex gap-2 mb-4">
                    <button
                        type="button"
                        @click="inviteTab = 'single'"
                        class="px-3.5 py-1.5 text-sm font-medium rounded-lg border transition-colors"
                        :class="inviteTab === 'single' ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-600 border-gray-300 hover:bg-gray-50'"
                    >Single email</button>
                    <button
                        type="button"
                        @click="inviteTab = 'csv'"
                        class="px-3.5 py-1.5 text-sm font-medium rounded-lg border transition-colors"
                        :class="inviteTab === 'csv' ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-600 border-gray-300 hover:bg-gray-50'"
                    >Batch CSV upload</button>
                </div>

                <!-- Single email -->
                <form v-if="inviteTab === 'single'" @submit.prevent="sendSingleInvite" class="flex items-end gap-3">
                    <div class="flex-1 max-w-sm">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                        <input
                            v-model="inviteForm.email"
                            type="email"
                            required
                            placeholder="member@example.com"
                            class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :class="inviteForm.errors.email ? 'border-red-400' : 'border-gray-300'"
                        />
                        <p v-if="inviteForm.errors.email" class="mt-1 text-xs text-red-600">{{ inviteForm.errors.email }}</p>
                    </div>
                    <button
                        type="submit"
                        :disabled="inviteForm.processing"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                        {{ inviteForm.processing ? 'Sending...' : 'Send invite' }}
                    </button>
                </form>

                <!-- CSV batch -->
                <form v-else @submit.prevent="sendCsvInvite" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">CSV file <span class="text-gray-400 font-normal">(one email per row)</span></label>
                        <label class="flex items-center gap-2 w-fit cursor-pointer px-3.5 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            {{ csvFile ? csvFile.name : 'Choose CSV file' }}
                            <input type="file" accept=".csv,.txt" class="hidden" @change="onCsvChange" />
                        </label>
                        <p class="mt-1 text-xs text-gray-400">Max 2 MB · .csv or .txt · one email per line</p>
                        <p v-if="csvInviteForm.errors.csv" class="mt-1 text-xs text-red-600">{{ csvInviteForm.errors.csv }}</p>
                    </div>
                    <button
                        type="submit"
                        :disabled="!csvFile || csvInviteForm.processing"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ csvInviteForm.processing ? 'Sending invites...' : 'Upload &amp; send invites' }}
                    </button>
                </form>

                <p v-if="inviteSent" class="mt-3 text-sm text-green-600">{{ inviteSentMessage }}</p>
            </div>

            <!-- Danger zone -->
            <div class="bg-white border border-red-200 rounded-2xl p-6">
                <h2 class="text-base font-semibold text-red-600 mb-1">Danger zone</h2>
                <p class="text-sm text-gray-500 mb-4">Permanently delete this community and all its data. This cannot be undone.</p>
                <button
                    @click="deleteCommunity"
                    class="px-4 py-2 border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors"
                >
                    Delete community
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const CATEGORIES = ['Tech', 'Business', 'Design', 'Health', 'Education', 'Finance', 'Other'];

const props = defineProps({
    community:   Object,
    pricingGate: Object,
    levelPerks:  { type: Object, default: () => ({}) },
});

const saved          = ref(false);
const imagesSaved    = ref(false);
const affiliateSaved = ref(false);
const perksSaved     = ref(false);
const perksSaving    = ref(false);
const announceSent   = ref(false);
const coverPreview   = ref(null);
const coverInput     = ref(null);
const avatarPreview  = ref(null);
const avatarInput    = ref(null);

// Invite members
const inviteTab        = ref('single');
const inviteSent       = ref(false);
const inviteSentMessage = ref('');
const csvFile          = ref(null);

// Level perks: keyed by level number (1-9)
const levelPerksForm = ref(
    Object.fromEntries(Array.from({ length: 9 }, (_, i) => [i + 1, props.levelPerks[i + 1] ?? '']))
);

const form = useForm({
    name:        props.community.name,
    description: props.community.description ?? '',
    category:    props.community.category ?? '',
    price:       props.community.price ?? 0,
    currency:    props.community.currency ?? 'PHP',
    is_private:  props.community.is_private ?? false,
});

const imageForm = useForm({
    name:        props.community.name,  // required by validator
    cover_image: null,
    avatar:      null,
});

function onCoverChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    imageForm.cover_image = file;
    coverPreview.value = URL.createObjectURL(file);
}

function removeCover() {
    imageForm.cover_image = null;
    coverPreview.value = null;
    if (coverInput.value) coverInput.value.value = '';
}

function onAvatarChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    imageForm.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
}

function removeAvatar() {
    imageForm.avatar = null;
    avatarPreview.value = null;
    if (avatarInput.value) avatarInput.value.value = '';
}

function saveImages() {
    imageForm.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            onSuccess: () => {
                coverPreview.value = null;
                avatarPreview.value = null;
                imagesSaved.value = true;
                setTimeout(() => (imagesSaved.value = false), 3000);
            },
        });
}

function save() {
    form.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            onSuccess: () => {
                saved.value = true;
                setTimeout(() => (saved.value = false), 3000);
            },
        });
}

const affiliateForm = useForm({
    name:                      props.community.name,   // required by the update validator
    affiliate_commission_rate: props.community.affiliate_commission_rate ?? '',
});

function saveAffiliate() {
    affiliateForm.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            onSuccess: () => {
                affiliateSaved.value = true;
                setTimeout(() => (affiliateSaved.value = false), 3000);
            },
        });
}

function saveLevelPerks() {
    perksSaving.value = true;
    router.patch(`/communities/${props.community.slug}/level-perks`, { perks: levelPerksForm.value }, {
        preserveScroll: true,
        onSuccess: () => { perksSaved.value = true; setTimeout(() => (perksSaved.value = false), 3000); },
        onFinish: () => { perksSaving.value = false; },
    });
}

const announceForm = useForm({ subject: '', message: '' });

function sendAnnouncement() {
    announceForm.post(`/communities/${props.community.slug}/announce`, {
        preserveScroll: true,
        onSuccess: () => {
            announceForm.reset();
            announceSent.value = true;
            setTimeout(() => (announceSent.value = false), 4000);
        },
    });
}

const inviteForm = useForm({ email: '' });

function sendSingleInvite() {
    inviteForm.post(`/communities/${props.community.slug}/invite`, {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset();
            inviteSentMessage.value = usePage().props.flash?.success ?? 'Invite sent!';
            inviteSent.value = true;
            setTimeout(() => (inviteSent.value = false), 4000);
        },
    });
}

const csvInviteForm = useForm({ csv: null });

function onCsvChange(e) {
    csvFile.value = e.target.files[0] ?? null;
    csvInviteForm.csv = csvFile.value;
}

function sendCsvInvite() {
    csvInviteForm.post(`/communities/${props.community.slug}/invite`, {
        preserveScroll: true,
        onSuccess: () => {
            csvFile.value = null;
            csvInviteForm.reset();
            inviteSentMessage.value = usePage().props.flash?.success ?? 'Invites sent!';
            inviteSent.value = true;
            setTimeout(() => (inviteSent.value = false), 4000);
        },
    });
}

// ─── Gallery ──────────────────────────────────────────────────────────────────
const galleryFile      = ref(null);
const galleryUploading = ref(false);
const galleryForm      = useForm({ image: null });

function onGalleryFileChange(e) {
    galleryFile.value = e.target.files[0] ?? null;
    galleryForm.image = galleryFile.value;
}

function uploadGalleryImage() {
    if (!galleryFile.value) return;
    galleryUploading.value = true;
    galleryForm.post(`/communities/${props.community.slug}/gallery`, {
        preserveScroll: true,
        onSuccess: () => {
            galleryFile.value = null;
            galleryForm.reset();
        },
        onFinish: () => { galleryUploading.value = false; },
    });
}

function removeGalleryImage(index) {
    router.delete(`/communities/${props.community.slug}/gallery/${index}`, { preserveScroll: true });
}

function deleteCommunity() {
    if (!confirm('Are you sure? This will permanently delete the community and all its data.')) return;
    router.delete(`/communities/${props.community.slug}`, {
        onSuccess: () => router.visit('/communities'),
    });
}
</script>
