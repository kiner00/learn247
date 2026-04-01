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
                    <div v-if="form.hasErrors" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3">
                        <p class="text-sm font-medium text-red-800">Please fix the following errors:</p>
                        <ul class="mt-1 list-disc list-inside text-xs text-red-700">
                            <li v-for="(msg, field) in form.errors" :key="field">{{ msg }}</li>
                        </ul>
                    </div>
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

                        <!-- Billing type (only shown when price > 0) -->
                        <div v-if="Number(form.price) > 0" class="mb-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Billing type</label>
                            <div class="flex gap-3">
                                <label :class="['flex-1 cursor-pointer rounded-xl border-2 p-3 transition-all',
                                    form.billing_type === 'monthly' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                                    <input type="radio" value="monthly" v-model="form.billing_type" class="sr-only" />
                                    <div class="text-sm font-bold text-gray-800">🔄 Monthly</div>
                                    <div class="text-xs text-gray-400 mt-0.5">Members pay every month to stay active</div>
                                </label>
                                <label :class="['flex-1 cursor-pointer rounded-xl border-2 p-3 transition-all',
                                    form.billing_type === 'one_time' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                                    <input type="radio" value="one_time" v-model="form.billing_type" class="sr-only" />
                                    <div class="text-sm font-bold text-gray-800">💳 One-time</div>
                                    <div class="text-xs text-gray-400 mt-0.5">Members pay once for lifetime access</div>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Price
                                    <span class="text-gray-400 font-normal">
                                        ({{ form.billing_type === 'one_time' ? 'one-time' : 'per month' }})
                                    </span>
                                </label>
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
                            <p class="mt-1 text-xs text-gray-400">JPG, PNG, WebP — max 15 MB &nbsp;·&nbsp; <span class="font-medium text-gray-500">Recommended: {{ IMAGE_DIMENSIONS.BANNER.width }} × {{ IMAGE_DIMENSIONS.BANNER.height }} px</span></p>
                            <p v-if="imageForm.errors.cover_image" class="mt-1 text-xs text-red-600">{{ imageForm.errors.cover_image }}</p>
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
                            <p class="mt-1 text-xs text-gray-400">Shown as your community icon. JPG, PNG, WebP — max 15 MB &nbsp;·&nbsp; <span class="font-medium text-gray-500">Recommended: {{ IMAGE_DIMENSIONS.AVATAR.width }} × {{ IMAGE_DIMENSIONS.AVATAR.height }} px</span></p>
                            <p v-if="imageForm.errors.avatar" class="mt-1 text-xs text-red-600">{{ imageForm.errors.avatar }}</p>
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
                    <p class="mt-1 text-xs text-gray-400">JPG, PNG, WebP — max 15 MB · {{ community.gallery_images?.length ?? 0 }}/8 images &nbsp;·&nbsp; <span class="font-medium text-gray-500">Recommended: 1200 × 800 px</span></p>
                    <p v-if="galleryForm.errors.image" class="mt-1 text-xs text-red-600">{{ galleryForm.errors.image }}</p>
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
                    The platform takes {{ (platformFeeRate * 100).toFixed(1) }}% off the top.
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
                                0 = disable affiliate program. Max 85 (platform takes {{ (platformFeeRate * 100).toFixed(1) }}%).
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
                        <strong class="text-red-500">Platform ₱{{ (community.price * platformFeeRate).toFixed(2) }}</strong>
                        · <strong class="text-orange-600">Affiliate ₱{{ (community.price * community.affiliate_commission_rate / 100).toFixed(2) }}</strong>
                        · <strong class="text-green-700">You ₱{{ (community.price - community.price * platformFeeRate - community.price * community.affiliate_commission_rate / 100).toFixed(2) }}</strong>
                    </div>
                </form>
            </div>

            <!-- AI Landing Page Builder -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-base font-semibold text-gray-900">✨ AI Landing Page Builder</h2>
                    <span class="text-xs font-bold bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full">⭐ Pro</span>
                </div>
                <p class="text-sm text-gray-500 mb-5">Generate a compelling tagline, description, and CTA for your community page using AI.</p>

                <!-- Locked for non-Pro -->
                <div v-if="creatorPlan !== 'pro'" class="rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-6 text-center">
                    <p class="text-sm font-semibold text-indigo-800 mb-1">Creator Pro feature</p>
                    <p class="text-xs text-indigo-600 mb-3">Upgrade to unlock AI-generated landing page copy.</p>
                    <Link href="/creator/plan" class="inline-block px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                        Upgrade to Creator Pro →
                    </Link>
                </div>

                <!-- Builder for Pro -->
                <div v-else>
                    <div class="flex items-center gap-3 flex-wrap">
                        <button
                            type="button"
                            @click="generateLandingPage"
                            :disabled="aiGenerating"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                        >
                            <svg v-if="aiGenerating" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            {{ aiGenerating ? 'Generating...' : '✨ Generate copy' }}
                        </button>
                        <Link :href="`/communities/${community.slug}/landing`"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 border border-indigo-200 text-indigo-600 text-sm font-medium rounded-lg hover:bg-indigo-50 transition-colors">
                            View / Edit Landing Page →
                        </Link>
                    </div>

                    <!-- AI result -->
                    <div v-if="aiCopy" class="mt-4 rounded-xl border border-indigo-100 bg-indigo-50 p-4 space-y-3">
                        <div>
                            <p class="text-xs font-semibold text-indigo-700 mb-0.5">Tagline</p>
                            <p class="text-sm text-gray-800">{{ aiCopy.tagline }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-indigo-700 mb-0.5">Description</p>
                            <p class="text-sm text-gray-800">{{ aiCopy.description }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-indigo-700 mb-0.5">CTA button label</p>
                            <p class="text-sm text-gray-800">{{ aiCopy.cta }}</p>
                        </div>
                        <div class="flex items-center gap-3 pt-1">
                            <button
                                type="button"
                                @click="applyAiCopy"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                            >
                                Apply description
                            </button>
                            <p class="text-xs text-gray-400">Copies the description into the General form above.</p>
                        </div>
                    </div>
                    <p v-if="aiError" class="mt-3 text-sm text-red-600">{{ aiError }}</p>
                </div>
            </div>

            <!-- Announcement Blast -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-base font-semibold text-gray-900">📢 Send Announcement</h2>
                    <span class="text-xs font-bold bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full">⭐ Pro</span>
                </div>
                <p class="text-sm text-gray-500 mb-5">Email all members of this community at once.</p>

                <!-- Locked for Free -->
                <div v-if="creatorPlan === 'free'" class="rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-6 text-center">
                    <p class="text-sm font-semibold text-indigo-800 mb-1">Basic &amp; Pro feature</p>
                    <p class="text-xs text-indigo-600 mb-3">Upgrade to send broadcast emails to all your members.</p>
                    <Link href="/creator/plan" class="inline-block px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                        Upgrade Plan →
                    </Link>
                </div>

                <!-- Form for Basic/Pro -->
                <form v-else @submit.prevent="sendAnnouncement" class="space-y-4">
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

            <!-- Integrations -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                <div class="flex items-center gap-2 mb-1">
                    <h2 class="text-base font-semibold text-gray-900">Integrations</h2>
                    <span class="px-2.5 py-1 text-xs font-bold bg-blue-100 text-blue-700 rounded-full">Basic+</span>
                </div>
                <p class="text-sm text-gray-500 mb-5">
                    Connect third-party tools to track conversions and optimize your ads.
                </p>

                <!-- Locked state for Free plan -->
                <div v-if="!canUseIntegrations" class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-6 text-center">
                    <p class="text-2xl mb-2">🔒</p>
                    <p class="text-sm font-semibold text-gray-700">Available on Basic &amp; Pro</p>
                    <p class="text-xs text-gray-500 mt-1 mb-4">Upgrade to connect Facebook Pixel, TikTok Pixel, and Google Analytics.</p>
                    <a href="/creator/plan" class="inline-block px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 transition-colors">Upgrade Plan →</a>
                </div>

                <form v-else @submit.prevent="saveIntegrations" class="space-y-5">
                    <!-- Facebook Pixel -->
                    <div>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1.5">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.234 2.686.234v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
                            Facebook Pixel ID
                        </label>
                        <input
                            v-model="integrationsForm.facebook_pixel_id"
                            type="text"
                            placeholder="e.g. 1234567890123456"
                            maxlength="30"
                            class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            :class="integrationsForm.errors.facebook_pixel_id ? 'border-red-400' : ''"
                        />
                        <p class="mt-1 text-xs text-gray-400">Events Manager → your Pixel → Settings.</p>
                        <p v-if="integrationsForm.errors.facebook_pixel_id" class="mt-1 text-xs text-red-600">{{ integrationsForm.errors.facebook_pixel_id }}</p>
                    </div>

                    <!-- TikTok Pixel -->
                    <div>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1.5">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.79 1.53V6.75a4.85 4.85 0 01-1.02-.06z"/></svg>
                            TikTok Pixel ID
                        </label>
                        <input
                            v-model="integrationsForm.tiktok_pixel_id"
                            type="text"
                            placeholder="e.g. C9ABCDEF12345678"
                            maxlength="30"
                            class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            :class="integrationsForm.errors.tiktok_pixel_id ? 'border-red-400' : ''"
                        />
                        <p class="mt-1 text-xs text-gray-400">TikTok Ads Manager → Assets → Events → Web Events.</p>
                        <p v-if="integrationsForm.errors.tiktok_pixel_id" class="mt-1 text-xs text-red-600">{{ integrationsForm.errors.tiktok_pixel_id }}</p>
                    </div>

                    <!-- Google Analytics -->
                    <div>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1.5">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24"><path d="M12 22.5a2 2 0 002-2V3.5a2 2 0 00-4 0v17a2 2 0 002 2z" fill="#F9AB00"/><path d="M19.5 22.5a2 2 0 002-2v-7a2 2 0 00-4 0v7a2 2 0 002 2z" fill="#E37400"/><path d="M4.5 22.5a2.5 2.5 0 002.5-2.5v-1a2.5 2.5 0 00-5 0v1a2.5 2.5 0 002.5 2.5z" fill="#E37400"/></svg>
                            Google Analytics 4 ID
                        </label>
                        <input
                            v-model="integrationsForm.google_analytics_id"
                            type="text"
                            placeholder="e.g. G-XXXXXXXXXX"
                            maxlength="20"
                            class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            :class="integrationsForm.errors.google_analytics_id ? 'border-red-400' : ''"
                        />
                        <p class="mt-1 text-xs text-gray-400">GA4 → Admin → Data Streams → your stream → Measurement ID.</p>
                        <p v-if="integrationsForm.errors.google_analytics_id" class="mt-1 text-xs text-red-600">{{ integrationsForm.errors.google_analytics_id }}</p>
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <button
                            type="submit"
                            :disabled="integrationsForm.processing"
                            class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                        >
                            Save integrations
                        </button>
                        <p v-if="integrationsSaved" class="text-sm text-green-600">Saved!</p>
                    </div>

                    <!-- Events legend -->
                    <div v-if="integrationsForm.facebook_pixel_id || integrationsForm.tiktok_pixel_id || integrationsForm.google_analytics_id"
                         class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-xs font-semibold text-gray-600 mb-2">Events fired automatically across all active platforms:</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-gray-500">
                            <div>
                                <p class="font-semibold text-gray-600 mb-1">📄 Page Visit</p>
                                <p class="text-gray-400">FB: PageView<br>TT: page()<br>GA: page_view</p>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-600 mb-1">👁️ Landing Page</p>
                                <p class="text-gray-400">FB: ViewContent<br>TT: ViewContent<br>GA: view_item</p>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-600 mb-1">✋ Join Form</p>
                                <p class="text-gray-400">FB: Lead<br>TT: PlaceAnOrder<br>GA: begin_checkout</p>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-600 mb-1">💰 Payment</p>
                                <p class="text-gray-400">FB: Purchase<br>TT: CompletePayment<br>GA: purchase</p>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Telegram -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                <div class="flex items-center gap-2 mb-1">
                    <svg class="w-5 h-5 text-sky-500 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z"/></svg>
                    <h2 class="text-base font-semibold text-gray-900">Telegram Group Chat</h2>
                    <span class="px-2.5 py-1 text-xs font-bold bg-purple-100 text-purple-700 rounded-full">⭐ Pro</span>
                </div>
                <p class="text-sm text-gray-500 mb-5">
                    Sync your community chat with a Telegram group. Messages posted in the app are forwarded to Telegram, and messages from Telegram appear in the app chat.
                </p>

                <!-- Locked for non-Pro -->
                <div v-if="!isPro" class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-6 flex items-center gap-4">
                    <span class="text-2xl">🔒</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Available on Pro</p>
                        <p class="text-xs text-gray-500 mt-0.5 mb-3">Upgrade to connect your community chat to a Telegram group.</p>
                        <a href="/creator/plan" class="inline-block px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 transition-colors">Upgrade to Pro →</a>
                    </div>
                </div>

                <template v-else>

                <!-- Setup guide -->
                <div class="mb-5 p-4 bg-sky-50 border border-sky-200 rounded-xl space-y-2 text-sm text-sky-800">
                    <p class="font-semibold">How to set up:</p>
                    <ol class="list-decimal list-inside space-y-1 text-sky-700">
                        <li>Open Telegram and message <span class="font-mono font-bold">@BotFather</span> → send <span class="font-mono">/newbot</span> → follow the steps to get your <strong>Bot Token</strong>.</li>
                        <li>Add your bot to the Telegram group and make it an <strong>Admin</strong> (so it can read and send messages).</li>
                        <li>Get your group's <strong>Chat ID</strong>: add <span class="font-mono font-bold">@userinfobot</span> to the group → it will reply with the chat ID (a negative number like <span class="font-mono">-1001234567890</span>).</li>
                        <li>Paste the Token and Chat ID below and click <strong>Save Telegram settings</strong>. The webhook is registered automatically.</li>
                    </ol>
                </div>

                <form @submit.prevent="saveTelegram" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Bot Token</label>
                        <input
                            v-model="telegramForm.telegram_bot_token"
                            type="password"
                            placeholder="123456789:AABBccDDeeFFggHHiiJJ..."
                            autocomplete="off"
                            class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"
                            :class="telegramForm.errors.telegram_bot_token ? 'border-red-400' : ''"
                        />
                        <p class="mt-1 text-xs text-gray-400">From @BotFather. Keep this private.</p>
                        <p v-if="telegramForm.errors.telegram_bot_token" class="mt-1 text-xs text-red-600">{{ telegramForm.errors.telegram_bot_token }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Group Chat ID</label>
                        <input
                            v-model="telegramForm.telegram_chat_id"
                            type="text"
                            placeholder="-1001234567890"
                            class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"
                            :class="telegramForm.errors.telegram_chat_id ? 'border-red-400' : ''"
                        />
                        <p class="mt-1 text-xs text-gray-400">Usually a negative number. Get it from @userinfobot.</p>
                        <p v-if="telegramForm.errors.telegram_chat_id" class="mt-1 text-xs text-red-600">{{ telegramForm.errors.telegram_chat_id }}</p>
                    </div>

                    <!-- Connected status -->
                    <div v-if="community.telegram_chat_id" class="flex items-center gap-2 text-sm text-green-700">
                        <svg class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Telegram group connected
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <button
                            type="submit"
                            :disabled="telegramForm.processing"
                            class="px-5 py-2.5 bg-sky-600 text-white text-sm font-medium rounded-lg hover:bg-sky-700 transition-colors disabled:opacity-50"
                        >
                            {{ telegramForm.processing ? 'Saving…' : 'Save Telegram settings' }}
                        </button>
                        <button
                            v-if="community.telegram_bot_token"
                            type="button"
                            @click="disconnectTelegram"
                            :disabled="telegramForm.processing"
                            class="px-4 py-2.5 border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors disabled:opacity-50"
                        >
                            Disconnect
                        </button>
                        <p v-if="telegramSaved" class="text-sm text-green-600">Saved! Webhook registered.</p>
                    </div>
                </form>
                </template>
            </div>

            <!-- Domain -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                <h2 class="text-base font-semibold text-gray-900 mb-1">Domain</h2>
                <p class="text-sm text-gray-500 mb-5">
                    Give your community its own address on the web.
                </p>

                <form @submit.prevent="saveDomain" class="space-y-6">
                    <!-- ── Subdomain ────────────────────────────────────────── -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Subdomain
                            <span class="ml-1 px-2 py-0.5 text-xs font-bold bg-green-100 text-green-700 rounded-full">Free</span>
                        </label>
                        <p class="text-xs text-gray-400 mb-2">
                            Your community gets its own address under <strong>{{ baseDomain }}</strong>. No DNS setup required.
                        </p>
                        <div class="flex items-stretch max-w-sm">
                            <input
                                v-model="domainForm.subdomain"
                                type="text"
                                placeholder="yourname"
                                maxlength="63"
                                class="flex-1 min-w-0 px-3.5 py-2.5 border rounded-l-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="domainForm.errors.subdomain ? 'border-red-400' : 'border-gray-300'"
                            />
                            <span class="inline-flex items-center px-3 border border-l-0 border-gray-300 rounded-r-lg bg-gray-50 text-sm text-gray-500 whitespace-nowrap">.{{ baseDomain }}</span>
                        </div>
                        <p v-if="domainForm.errors.subdomain" class="mt-1 text-xs text-red-600">{{ domainForm.errors.subdomain }}</p>
                        <p v-else-if="domainForm.subdomain" class="mt-1 text-xs text-indigo-600 font-medium">
                            Preview: {{ domainForm.subdomain }}.{{ baseDomain }}
                        </p>
                        <p v-else class="mt-1 text-xs text-gray-400">Lowercase letters, numbers, and hyphens only. No spaces.</p>
                    </div>

                    <!-- ── Custom Domain ────────────────────────────────────── -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Custom Domain
                            <span class="ml-1 px-2 py-0.5 text-xs font-bold bg-purple-100 text-purple-700 rounded-full">Pro</span>
                        </label>
                        <p class="text-xs text-gray-400 mb-3">
                            Use your own domain like <span class="font-mono">myclassroom.com</span>. You'll need to update your DNS records.
                        </p>

                        <!-- Locked for non-Pro -->
                        <div v-if="!isPro" class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-5 flex items-center gap-4">
                            <span class="text-2xl">🔒</span>
                            <div>
                                <p class="text-sm font-semibold text-gray-700">Available on Pro</p>
                                <p class="text-xs text-gray-500 mt-0.5 mb-3">Upgrade to connect your own domain to this community.</p>
                                <a href="/creator/plan" class="inline-block px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 transition-colors">Upgrade to Pro →</a>
                            </div>
                        </div>

                        <!-- Pro: input + DNS guide -->
                        <template v-else>
                            <input
                                v-model="domainForm.custom_domain"
                                type="text"
                                placeholder="myclassroom.com"
                                maxlength="253"
                                class="w-full max-w-sm px-3.5 py-2.5 border rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="domainForm.errors.custom_domain ? 'border-red-400' : 'border-gray-300'"
                            />
                            <p v-if="domainForm.errors.custom_domain" class="mt-1 text-xs text-red-600">{{ domainForm.errors.custom_domain }}</p>
                            <p v-else class="mt-1 text-xs text-gray-400">Enter the domain you own, without http:// or a trailing slash.</p>

                            <!-- DNS instructions -->
                            <div v-if="domainForm.custom_domain" class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-xl space-y-3">
                                <p class="text-sm font-semibold text-blue-800">DNS Setup Instructions</p>
                                <p class="text-xs text-blue-700">
                                    Log in to your domain registrar (GoDaddy, Namecheap, Cloudflare, etc.) and add one of the following records:
                                </p>
                                <div class="space-y-2 text-xs">
                                    <div class="bg-white border border-blue-100 rounded-lg p-3">
                                        <p class="font-semibold text-blue-700 mb-1">Option A — A Record <span class="font-normal text-blue-500">(recommended)</span></p>
                                        <table class="w-full font-mono text-gray-700">
                                            <tr class="text-gray-400 text-[11px]"><th class="text-left pr-4">Type</th><th class="text-left pr-4">Host / Name</th><th class="text-left">Value</th></tr>
                                            <tr><td class="pr-4">A</td><td class="pr-4">@ or {{ domainForm.custom_domain }}</td><td class="text-indigo-700">{{ serverIp || 'your-server-ip' }}</td></tr>
                                        </table>
                                    </div>
                                    <div class="bg-white border border-blue-100 rounded-lg p-3">
                                        <p class="font-semibold text-blue-700 mb-1">Option B — CNAME Record</p>
                                        <table class="w-full font-mono text-gray-700">
                                            <tr class="text-gray-400 text-[11px]"><th class="text-left pr-4">Type</th><th class="text-left pr-4">Host / Name</th><th class="text-left">Value</th></tr>
                                            <tr><td class="pr-4">CNAME</td><td class="pr-4">@ or www</td><td class="text-indigo-700">{{ baseDomain }}</td></tr>
                                        </table>
                                    </div>
                                </div>
                                <p class="text-xs text-blue-600">DNS changes can take up to 24–48 hours to propagate worldwide. Once active, your community will be accessible at your domain.</p>
                            </div>
                        </template>
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <button
                            type="submit"
                            :disabled="domainForm.processing"
                            class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                        >
                            {{ domainForm.processing ? 'Saving...' : 'Save domain settings' }}
                        </button>
                        <p v-if="domainSaved" class="text-sm text-green-600">Saved!</p>
                    </div>
                </form>
            </div>

            <!-- SMS Integration -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                <div class="flex items-center gap-2 mb-1">
                    <h2 class="text-base font-semibold text-gray-900">SMS Blast</h2>
                    <span class="px-2.5 py-1 text-xs font-bold bg-indigo-100 text-indigo-700 rounded-full">⭐ Pro</span>
                </div>
                <p class="text-sm text-gray-500 mb-5">
                    Connect your SMS provider to send text blasts to your members. Members must have a phone number on their profile.
                </p>
                <form @submit.prevent="saveSmsConfig" class="space-y-5">
                    <!-- Provider -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">SMS Provider</label>
                        <select
                            v-model="smsForm.sms_provider"
                            class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white"
                        >
                            <option value="">— Select provider —</option>
                            <option value="semaphore">Semaphore (PH)</option>
                            <option value="philsms">PhilSMS (PH)</option>
                            <option value="xtreme_sms">Xtreme SMS (Android Gateway)</option>
                        </select>
                    </div>

                    <template v-if="smsForm.sms_provider">
                        <!-- API Key -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">API Key</label>
                            <input
                                v-model="smsForm.sms_api_key"
                                type="text"
                                placeholder="Your API key"
                                class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            />
                            <p class="mt-1 text-xs text-gray-400">
                                <template v-if="smsForm.sms_provider === 'semaphore'">semaphore.co → Account → API Key</template>
                                <template v-else-if="smsForm.sms_provider === 'philsms'">app.philsms.com → Profile → API Token</template>
                                <template v-else>Your Xtreme SMS API key from the dashboard</template>
                            </p>
                        </div>

                        <!-- Sender name (Semaphore / PhilSMS) -->
                        <div v-if="smsForm.sms_provider !== 'xtreme_sms'">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Sender Name <span class="text-gray-400 font-normal">(max 11 chars)</span>
                            </label>
                            <input
                                v-model="smsForm.sms_sender_name"
                                type="text"
                                maxlength="11"
                                placeholder="e.g. MyBrand"
                                class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            />
                            <p class="mt-1 text-xs text-gray-400">
                                <template v-if="smsForm.sms_provider === 'semaphore'">Approved sender name on your Semaphore account.</template>
                                <template v-else>Approved Sender ID on your PhilSMS account (optional).</template>
                            </p>
                        </div>

                        <!-- Device URL (Xtreme SMS only) -->
                        <div v-if="smsForm.sms_provider === 'xtreme_sms'">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Gateway URL</label>
                            <input
                                v-model="smsForm.sms_device_url"
                                type="url"
                                placeholder="https://your-xtreme-sms-server.com"
                                class="w-full max-w-sm px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            />
                            <p class="mt-1 text-xs text-gray-400">Your Xtreme SMS server URL (e.g. https://sms.xtremesuccess.ph)</p>
                        </div>
                    </template>

                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <button
                            type="submit"
                            :disabled="smsForm.processing"
                            class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                        >
                            Save SMS settings
                        </button>
                        <template v-if="community.sms_provider && community.sms_api_key">
                            <input
                                v-model="smsTestPhone"
                                type="tel"
                                placeholder="e.g. 09171234567"
                                class="px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 w-40"
                            />
                            <button
                                type="button"
                                :disabled="smsTesting || !smsTestPhone.trim()"
                                @click="sendTestSms"
                                class="px-4 py-2.5 border border-emerald-400 text-emerald-700 text-sm font-medium rounded-lg hover:bg-emerald-50 transition-colors disabled:opacity-50 flex items-center gap-1.5"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ smsTesting ? 'Sending…' : 'Test' }}
                            </button>
                        </template>
                        <p v-if="smsSaved" class="text-sm text-green-600">Saved!</p>
                        <p v-if="smsTestSuccess" class="text-sm text-green-600">{{ smsTestSuccess }}</p>
                        <p v-if="smsTestError" class="text-sm text-red-600">{{ smsTestError }}</p>
                    </div>

                    <!-- Provider info strip -->
                    <div v-if="smsForm.sms_provider" class="p-3 bg-gray-50 rounded-lg text-xs text-gray-500 space-y-1">
                        <template v-if="smsForm.sms_provider === 'semaphore'">
                            <p><strong>Semaphore</strong> — Philippine SMS gateway. Charges per message sent. Sign up at <span class="font-mono">semaphore.co</span>.</p>
                        </template>
                        <template v-else-if="smsForm.sms_provider === 'philsms'">
                            <p><strong>PhilSMS</strong> — Philippine SMS gateway. Pay-as-you-go credits. Sign up at <span class="font-mono">philsms.com</span>.</p>
                        </template>
                        <template v-else>
                            <p><strong>Xtreme SMS</strong> — Uses an Android phone as an SMS gateway. Requires the Xtreme SMS app installed on your device.</p>
                        </template>
                    </div>
                </form>
            </div>

            <!-- Danger zone -->
            <div class="bg-white border border-red-200 rounded-2xl p-6">
                <h2 class="text-base font-semibold text-red-600 mb-1">Danger zone</h2>

                <!-- Pending deletion notice -->
                <div v-if="community.deletion_requested_at" class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                    <p class="text-sm font-semibold text-amber-800 mb-1">Deletion scheduled</p>
                    <p class="text-sm text-amber-700">
                        This community is pending deletion. No new members can join and subscriptions will not renew.
                        It will be automatically deleted once all active subscribers expire.
                    </p>
                    <button
                        @click="cancelDeletion"
                        class="mt-3 px-4 py-2 border border-amber-400 text-amber-800 text-sm font-medium rounded-lg hover:bg-amber-100 transition-colors"
                    >
                        Cancel scheduled deletion
                    </button>
                </div>

                <template v-else>
                    <p class="text-sm text-gray-500 mb-4">
                        If the community has active subscribers, deletion will be scheduled — no new joins, no renewals.
                        The community will be automatically deleted once all subscriptions expire.
                    </p>
                    <button
                        @click="deleteCommunity"
                        class="px-4 py-2 border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors"
                    >
                        Delete community
                    </button>
                </template>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const page = usePage();
const creatorPlan = computed(() => page.props.auth.user?.creator_plan ?? 'free');
const platformFeeRate = computed(() => {
    if (creatorPlan.value === 'pro')   return 0.029;
    if (creatorPlan.value === 'basic') return 0.049;
    return 0.098;
});
import AppLayout from '@/Layouts/AppLayout.vue';
import { IMAGE_DIMENSIONS } from '@/constants';

const CATEGORIES = ['Tech', 'Business', 'Design', 'Health', 'Education', 'Finance', 'Other'];

const props = defineProps({
    community:          Object,
    pricingGate:        Object,
    levelPerks:         { type: Object, default: () => ({}) },
    canUseIntegrations: { type: Boolean, default: false },
    isPro:              { type: Boolean, default: false },
    baseDomain:         { type: String, default: 'curzzo.com' },
    serverIp:           { type: String, default: '' },
});

const saved             = ref(false);
const imagesSaved       = ref(false);
const affiliateSaved    = ref(false);
const integrationsSaved = ref(false);
const perksSaved        = ref(false);
const perksSaving    = ref(false);
const announceSent   = ref(false);
const coverPreview  = ref(null);
const coverInput    = ref(null);
const avatarPreview = ref(null);
const avatarInput   = ref(null);

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
    price:        props.community.price ?? 0,
    currency:     props.community.currency ?? 'PHP',
    billing_type: props.community.billing_type ?? 'monthly',
    is_private:   props.community.is_private ?? false,
});

const imageForm = useForm({
    name:        props.community.name,  // required by validator
    cover_image: null,
    avatar:      null,
});

function onCoverChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    if (file.size > 15 * 1024 * 1024) {
        imageForm.errors.cover_image = 'The banner must not be larger than 15 MB.';
        if (coverInput.value) coverInput.value.value = '';
        return;
    }
    imageForm.errors.cover_image = null;
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
    if (file.size > 15 * 1024 * 1024) {
        imageForm.errors.avatar = 'The avatar must not be larger than 15 MB.';
        if (avatarInput.value) avatarInput.value.value = '';
        return;
    }
    imageForm.errors.avatar = null;
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

const integrationsForm = useForm({
    name:                 props.community.name,
    facebook_pixel_id:    props.community.facebook_pixel_id   ?? '',
    tiktok_pixel_id:      props.community.tiktok_pixel_id     ?? '',
    google_analytics_id:  props.community.google_analytics_id ?? '',
});

const telegramSaved = ref(false);
const telegramForm  = useForm({
    name:                props.community.name,
    telegram_bot_token:  '',   // never pre-fill token for security
    telegram_chat_id:    props.community.telegram_chat_id ?? '',
    telegram_clear:      false,
});

function saveTelegram() {
    telegramForm.telegram_clear = false;
    telegramForm.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            onSuccess: () => {
                telegramSaved.value = true;
                telegramForm.telegram_bot_token = '';
                setTimeout(() => (telegramSaved.value = false), 4000);
            },
        });
}

function disconnectTelegram() {
    if (!confirm('Disconnect Telegram? Messages will no longer sync.')) return;
    telegramForm.telegram_clear     = true;
    telegramForm.telegram_bot_token = '';
    telegramForm.telegram_chat_id   = '';
    telegramForm.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            onSuccess: () => {
                telegramForm.telegram_clear = false;
            },
        });
}

function saveIntegrations() {
    integrationsForm.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            onSuccess: () => {
                integrationsSaved.value = true;
                setTimeout(() => (integrationsSaved.value = false), 3000);
            },
        });
}

const domainSaved = ref(false);

const domainForm = useForm({
    name:          props.community.name,
    subdomain:     props.community.subdomain    ?? '',
    custom_domain: props.community.custom_domain ?? '',
});

function saveDomain() {
    domainForm.transform(data => ({ ...data, _method: 'PATCH' }))
        .post(`/communities/${props.community.slug}`, {
            onSuccess: () => {
                domainSaved.value = true;
                setTimeout(() => (domainSaved.value = false), 3000);
            },
        });
}

const smsSaved       = ref(false);
const smsTesting     = ref(false);
const smsTestPhone   = ref('');
const smsTestSuccess = ref('');
const smsTestError   = ref('');

const smsForm = useForm({
    sms_provider:    props.community.sms_provider    ?? '',
    sms_api_key:     props.community.sms_api_key     ?? '',
    sms_sender_name: props.community.sms_sender_name ?? '',
    sms_device_url:  props.community.sms_device_url  ?? '',
});

function saveSmsConfig() {
    smsForm.post(`/communities/${props.community.slug}/sms-config`, {
        preserveScroll: true,
        onSuccess: () => {
            smsSaved.value = true;
            setTimeout(() => (smsSaved.value = false), 3000);
        },
    });
}

function sendTestSms() {
    smsTesting.value     = true;
    smsTestSuccess.value = '';
    smsTestError.value   = '';
    router.post(`/communities/${props.community.slug}/sms-test`, { phone: smsTestPhone.value }, {
        preserveScroll: true,
        onSuccess: (page) => {
            smsTestSuccess.value = page.props.flash?.success ?? 'Test SMS sent!';
            setTimeout(() => (smsTestSuccess.value = ''), 5000);
        },
        onError: (errors) => {
            smsTestError.value = errors.sms_test ?? 'Test failed.';
            setTimeout(() => (smsTestError.value = ''), 6000);
        },
        onFinish: () => { smsTesting.value = false; },
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
    const file = e.target.files[0] ?? null;
    if (file && file.size > 15 * 1024 * 1024) {
        galleryForm.errors.image = 'The image must not be larger than 15 MB.';
        galleryFile.value = null;
        galleryForm.image = null;
        return;
    }
    galleryForm.errors.image = null;
    galleryFile.value = file;
    galleryForm.image = file;
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
    if (!confirm('Are you sure you want to delete this community? If there are active subscribers, deletion will be scheduled and the community will be removed once all subscriptions expire.')) return;
    router.delete(`/communities/${props.community.slug}`, {
        onSuccess: () => {
            // If community is still accessible, we're still on the page (scheduled deletion)
            // If redirected, the community was deleted immediately
        },
    });
}

function cancelDeletion() {
    if (!confirm('Cancel the scheduled deletion? The community will become active again.')) return;
    router.post(`/communities/${props.community.slug}/cancel-deletion`, {}, { preserveScroll: true });
}

// ─── AI Landing Page Builder ───────────────────────────────────────────────────
const aiGenerating = ref(false);
const aiCopy       = ref(null);
const aiError      = ref('');

async function generateLandingPage() {
    aiGenerating.value = true;
    aiCopy.value       = null;
    aiError.value      = '';
    try {
        const { data } = await axios.post(`/communities/${props.community.slug}/ai-landing`);
        aiCopy.value = {
            tagline:     data.hero?.headline,
            description: data.hero?.subheadline,
            cta:         data.hero?.cta_label,
        };
    } catch (e) {
        aiError.value = e?.response?.data?.error ?? 'Something went wrong. Please try again.';
    } finally {
        aiGenerating.value = false;
    }
}

function applyAiCopy() {
    if (!aiCopy.value) return;
    form.description = aiCopy.value.description;
    aiCopy.value = null;
}
</script>
