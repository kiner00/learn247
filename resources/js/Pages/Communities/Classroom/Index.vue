<template>
    <AppLayout :title="`${community.name} · Classroom`" :community="community">
        <CommunityTabs :community="community" active-tab="classroom" />

        <!-- Header row -->
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900">
                Courses
                <span class="ml-1.5 text-sm font-normal text-gray-400">{{ courses.length }}</span>
            </h2>
            <template v-if="isOwner">
                <Link
                    v-if="courseLimit !== null && courses.length >= courseLimit"
                    href="/creator/plan"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-100 text-indigo-700 text-sm font-semibold rounded-xl hover:bg-indigo-200 transition-colors"
                >
                    🔒 Upgrade for more courses
                    <span class="text-[10px] font-bold bg-indigo-600 text-white px-1.5 py-0.5 rounded-full">Upgrade</span>
                </Link>
                <button
                    v-else
                    @click="showForm = !showForm"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors"
                >
                    + New Course
                </button>
            </template>
        </div>

        <!-- New course form -->
        <div v-if="showForm" class="bg-white border border-indigo-200 rounded-2xl p-5 shadow-sm mb-6">
            <h2 class="text-sm font-bold text-gray-900 mb-3">New Course</h2>
            <form @submit.prevent="createCourse">
                <input
                    v-model="courseForm.title"
                    type="text"
                    placeholder="Course title"
                    required
                    class="w-full px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-2"
                />
                <textarea
                    v-model="courseForm.description"
                    rows="2"
                    placeholder="Description (optional)"
                    class="w-full px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none mb-3"
                />

                <!-- Access type -->
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Access</label>
                    <div class="flex gap-2">
                        <!-- Free -->
                        <label :class="['flex-1 cursor-pointer rounded-lg border-2 p-2.5 text-center transition-all',
                            courseForm.access_type === 'free' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                            <input type="radio" value="free" v-model="courseForm.access_type" class="sr-only" />
                            <div class="text-base mb-0.5">🌐</div>
                            <div class="text-xs font-semibold text-gray-800">Free</div>
                            <div class="text-[10px] text-gray-400 leading-tight mt-0.5">Anyone can access</div>
                        </label>
                        <!-- Included -->
                        <label :class="['flex-1 cursor-pointer rounded-lg border-2 p-2.5 text-center transition-all',
                            courseForm.access_type === 'inclusive' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                            <input type="radio" value="inclusive" v-model="courseForm.access_type" class="sr-only" />
                            <div class="text-base mb-0.5">⭐</div>
                            <div class="text-xs font-semibold text-gray-800">Included</div>
                            <div class="text-[10px] text-gray-400 leading-tight mt-0.5">Members only</div>
                        </label>
                        <!-- One-Time (member_once) -->
                        <label :class="['flex-1 cursor-pointer rounded-lg border-2 p-2.5 text-center transition-all',
                            courseForm.access_type === 'member_once' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                            <input type="radio" value="member_once" v-model="courseForm.access_type" class="sr-only" />
                            <div class="text-base mb-0.5">🎟️</div>
                            <div class="text-xs font-semibold text-gray-800">One-Time</div>
                            <div class="text-[10px] text-gray-400 leading-tight mt-0.5">Past members included</div>
                        </label>
                        <!-- Paid (covers both paid_once + paid_monthly) -->
                        <label :class="['flex-1 cursor-pointer rounded-lg border-2 p-2.5 text-center transition-all',
                            isPaidType(courseForm.access_type) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']"
                            @click="selectPaidIfNeeded(courseForm)">
                            <div class="text-base mb-0.5">💳</div>
                            <div class="text-xs font-semibold text-gray-800">Paid</div>
                            <div class="text-[10px] text-gray-400 leading-tight mt-0.5">Separate payment</div>
                        </label>
                    </div>

                    <!-- Paid sub-choice: monthly vs one-time -->
                    <div v-if="isPaidType(courseForm.access_type)" class="mt-2 flex gap-2 pl-0.5">
                        <label :class="['flex-1 cursor-pointer rounded-lg border px-3 py-2 flex items-center gap-2 transition-all',
                            courseForm.access_type === 'paid_once' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                            <input type="radio" value="paid_once" v-model="courseForm.access_type" class="accent-indigo-600" />
                            <div>
                                <div class="text-xs font-semibold text-gray-800">One-time</div>
                                <div class="text-[10px] text-gray-400">Pay once, access forever</div>
                            </div>
                        </label>
                        <label :class="['flex-1 cursor-pointer rounded-lg border px-3 py-2 flex items-center gap-2 transition-all',
                            courseForm.access_type === 'paid_monthly' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                            <input type="radio" value="paid_monthly" v-model="courseForm.access_type" class="accent-indigo-600" />
                            <div>
                                <div class="text-xs font-semibold text-gray-800">Monthly</div>
                                <div class="text-[10px] text-gray-400">Recurring monthly payment</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Price (paid types) -->
                <div v-if="isPaidType(courseForm.access_type)" class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Price (PHP)
                        <span class="text-gray-400 font-normal">{{ courseForm.access_type === 'paid_monthly' ? '/ month' : '· one-time' }}</span>
                    </label>
                    <input v-model="courseForm.price" type="number" min="1" step="0.01" required placeholder="e.g. 1500"
                        class="w-48 px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>

                <!-- Affiliate commission rate (paid types) -->
                <div v-if="isPaidType(courseForm.access_type)" class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Affiliate commission
                        <span class="text-gray-400 font-normal">· % of sale price paid to referring affiliate</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <input v-model="courseForm.affiliate_commission_rate" type="number" min="0" max="100" step="1" placeholder="e.g. 30"
                            class="w-24 px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <span class="text-sm text-gray-500">%</span>
                        <span v-if="courseForm.affiliate_commission_rate && courseForm.price" class="text-xs text-gray-400">
                            = ₱{{ (courseForm.price * courseForm.affiliate_commission_rate / 100).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }} per sale
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Leave blank or 0 to disable affiliate commission for this course.</p>
                </div>

                <!-- Cover image -->
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Cover image <span class="text-gray-400 font-normal">(optional)</span></label>
                    <div v-if="coverPreview" class="relative mb-2 h-28 rounded-lg overflow-hidden border border-gray-200">
                        <img :src="coverPreview" class="w-full h-full object-cover" />
                        <button type="button" @click="removeCover"
                            class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-black/50 text-white flex items-center justify-center text-xs hover:bg-black/70">x</button>
                    </div>
                    <label class="flex items-center gap-2 w-fit cursor-pointer px-3 py-1.5 border border-gray-300 rounded-lg text-xs text-gray-600 hover:bg-gray-50 transition-colors">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        {{ coverPreview ? 'Change image' : 'Upload cover' }}
                        <input ref="coverInput" type="file" accept="image/*" class="hidden" @change="onCoverChange" />
                    </label>
                    <p class="text-xs text-gray-400 mt-1">Recommended: 1280 x 720 px</p>
                </div>

                <!-- Preview video (Pro only) -->
                <div v-if="isPro" class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Preview video <span class="text-gray-400 font-normal">(optional)</span></label>
                    <div v-if="videoPreview" class="relative mb-2 aspect-video rounded-lg overflow-hidden border border-gray-200 bg-black">
                        <video :src="videoPreview" class="w-full h-full object-cover" muted playsinline />
                        <button type="button" @click="removeVideo"
                            class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-black/50 text-white flex items-center justify-center text-xs hover:bg-black/70">x</button>
                    </div>
                    <div v-if="videoUploading" class="mb-2">
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-indigo-500 rounded-full transition-all" :style="{ width: `${videoUploadProgress}%` }" />
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Uploading... {{ videoUploadProgress }}%</p>
                    </div>
                    <p v-if="videoUploadError" class="text-xs text-red-500 mb-1">{{ videoUploadError }}</p>
                    <label v-if="!videoUploading" class="flex items-center gap-2 w-fit cursor-pointer px-3 py-1.5 border border-gray-300 rounded-lg text-xs text-gray-600 hover:bg-gray-50 transition-colors">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        {{ videoPreview ? 'Change video' : 'Upload preview' }}
                        <input ref="videoInput" type="file" accept="video/mp4,video/quicktime,video/webm" class="hidden" @change="onVideoChange" />
                    </label>
                    <p class="text-xs text-gray-400 mt-1">MP4 recommended, 1280 x 720 px, max 500 MB. Plays on hover.</p>
                    <label v-if="videoPreview || courseForm.preview_video" class="flex items-center gap-2 mt-2 cursor-pointer">
                        <input type="checkbox" v-model="courseForm.preview_video_sound" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-xs text-gray-600">Enable sound on preview</span>
                    </label>
                </div>

                <div class="flex gap-2 justify-end">
                    <button type="button" @click="showForm = false; removeCover()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancel</button>
                    <button type="submit" :disabled="courseForm.processing"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                        {{ courseForm.processing ? 'Creating...' : 'Create Course' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Course grid -->
        <div v-if="courses.length">

            <!-- Owner: draggable grid (manual order) -->
            <template v-if="isOwner">
                <p class="text-xs text-gray-400 mb-3">Drag to reorder. Visitors see: Free → Included → One-Time → Paid.</p>
                <draggable
                    v-model="localCourses"
                    item-key="id"
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5"
                    handle=".drag-handle"
                    @end="onCourseReorder"
                >
                    <template #item="{ element: course }">
                        <div class="relative group h-full">
                            <Link
                                :href="`/communities/${community.slug}/classroom/courses/${course.id}`"
                                class="flex flex-col h-full bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all border border-gray-100"
                            >
                                <div class="relative aspect-video bg-gray-900 overflow-hidden shrink-0"
                                    @mouseenter="onCardHover($event, course)"
                                    @mouseleave="onCardLeave($event, course)"
                                    @touchstart.passive="onCardTouchStart($event, course)"
                                    @touchend.passive="onCardTouchEnd(course)">
                                    <video v-if="course.preview_video"
                                        :data-course-id="course.id"
                                        :src="course.preview_video"
                                        class="absolute inset-0 w-full h-full object-cover opacity-0 transition-opacity duration-300 z-[1]"
                                        loop playsinline preload="none" />
                                    <img v-if="course.cover_image" :src="course.cover_image" :alt="course.title"
                                        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />
                                    <div v-else class="w-full h-full bg-gradient-to-br from-indigo-600 to-purple-700 flex items-center justify-center">
                                        <span class="text-4xl font-black text-white/20 select-none">{{ course.title.charAt(0).toUpperCase() }}</span>
                                    </div>
                                    <div v-if="course.preview_video" class="absolute bottom-2 left-2 z-[2] flex items-center gap-1 px-2 py-1 bg-black/50 text-white text-[10px] font-medium rounded-full backdrop-blur-sm pointer-events-none sm:hidden">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                        Tap to preview
                                    </div>
                                    <div v-if="course.total > 0 && course.completed > 0"
                                        class="absolute top-2.5 right-2.5 px-2 py-0.5 bg-black/60 text-white text-xs font-semibold rounded-full backdrop-blur-sm">
                                        {{ course.progress }}%
                                    </div>
                                </div>
                                <div class="p-4 flex flex-col flex-1">
                                    <div class="flex items-start justify-between gap-2 mb-1">
                                        <h3 class="font-bold text-gray-900 group-hover:text-indigo-700 transition-colors line-clamp-1">{{ course.title }}</h3>
                                        <span v-if="course.access_type === 'free'" class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-green-100 text-green-700">FREE</span>
                                        <span v-else-if="course.access_type === 'inclusive'" class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700">INCLUDED</span>
                                        <span v-else-if="course.access_type === 'member_once'" class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700">ONE-TIME</span>
                                        <span v-else class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700">
                                            ₱{{ Number(course.price).toLocaleString() }}{{ course.access_type === 'paid_monthly' ? '/mo' : '' }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500 line-clamp-2 leading-relaxed flex-1">{{ course.description ?? '' }}</p>
                                    <div v-if="course.preview_video && course.preview_play_count > 0" class="flex items-center gap-3 mt-2 pt-2 border-t border-gray-100">
                                        <span class="text-[11px] text-gray-400 flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ course.preview_play_count }} plays
                                        </span>
                                        <span class="text-[11px] text-gray-400 flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ formatWatchTime(course.preview_watch_seconds) }}
                                        </span>
                                        <span v-if="course.preview_play_count > 0" class="text-[11px] text-gray-400">
                                            ~{{ Math.round(course.preview_watch_seconds / course.preview_play_count) }}s avg
                                        </span>
                                    </div>
                                </div>
                            </Link>
                            <!-- Owner actions -->
                            <div class="absolute top-2.5 left-2.5 flex gap-1.5 z-10">
                                <div class="drag-handle w-7 h-7 bg-black/50 hover:bg-black/80 text-white rounded-full flex items-center justify-center cursor-grab active:cursor-grabbing" title="Drag to reorder">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16"/>
                                    </svg>
                                </div>
                                <button @click.prevent="openEdit(course)"
                                    class="w-7 h-7 bg-black/50 hover:bg-black/80 text-white rounded-full flex items-center justify-center transition-colors" title="Edit course">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 11l6-6 3 3-6 6H9v-3z"/>
                                    </svg>
                                </button>
                                <button @click.prevent="togglePublish(course)"
                                    :class="['w-7 h-7 rounded-full flex items-center justify-center transition-colors text-white',
                                        course.is_published ? 'bg-green-500/80 hover:bg-green-600' : 'bg-red-500/80 hover:bg-red-600']"
                                    :title="course.is_published ? 'Published · click to unpublish' : 'Draft · click to publish'">
                                    <svg v-if="course.is_published" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                    </svg>
                                </button>
                                <button @click.prevent="deleteCourse(course)"
                                    class="w-7 h-7 bg-black/50 hover:bg-red-600 text-white rounded-full flex items-center justify-center transition-colors" title="Delete course">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                            <!-- Draft badge -->
                            <div v-if="!course.is_published" class="absolute bottom-[calc(56%+8px)] right-2.5 z-10">
                                <span class="px-2 py-0.5 bg-yellow-400 text-yellow-900 text-[10px] font-bold rounded-full uppercase tracking-wide">Draft</span>
                            </div>
                        </div>
                    </template>
                </draggable>
            </template>

            <!-- Non-owner: grouped sections (Free → Included → Paid) -->
            <template v-else>
                <template v-for="(group, label) in groupedCourses" :key="label">
                    <div v-if="group.length" class="mb-8">
                        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                            <span>{{ label }}</span><span class="flex-1 h-px bg-gray-100"></span>
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                            <div v-for="course in group" :key="course.id" class="relative group h-full">
                                <Link
                                    :href="`/communities/${community.slug}/classroom/courses/${course.id}`"
                                    class="flex flex-col h-full bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all border border-gray-100"
                                >
                                    <div class="relative aspect-video bg-gray-900 overflow-hidden"
                                        @mouseenter="onCardHover($event, course)"
                                        @mouseleave="onCardLeave($event, course)"
                                        @touchstart.passive="onCardTouchStart($event, course)"
                                        @touchend.passive="onCardTouchEnd(course)">
                                        <video v-if="course.preview_video"
                                            :data-course-id="course.id"
                                            :src="course.preview_video"
                                            class="absolute inset-0 w-full h-full object-cover opacity-0 transition-opacity duration-300 z-[1]"
                                            loop playsinline preload="none" />
                                        <img v-if="course.cover_image" :src="course.cover_image" :alt="course.title"
                                            :class="['w-full h-full object-cover transition-transform duration-300',
                                                course.has_access ? 'group-hover:scale-105' : 'blur-[1.5px]']" />
                                        <div v-else
                                            :class="['w-full h-full bg-gradient-to-br from-indigo-600 to-purple-700 flex items-center justify-center', !course.has_access && 'opacity-80']">
                                            <span class="text-4xl font-black text-white/20 select-none">{{ course.title.charAt(0).toUpperCase() }}</span>
                                        </div>
                                        <div v-if="course.preview_video" class="absolute bottom-2 left-2 z-[2] flex items-center gap-1 px-2 py-1 bg-black/50 text-white text-[10px] font-medium rounded-full backdrop-blur-sm pointer-events-none sm:hidden">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                            Tap to preview
                                        </div>
                                        <!-- Lock overlay -->
                                        <div v-if="!course.has_access"
                                            class="absolute inset-0 flex flex-col items-center justify-center bg-black/20">
                                            <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center mb-2">
                                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                                </svg>
                                            </div>
                                            <span v-if="course.access_type === 'paid_once'" class="text-white text-xs font-bold bg-indigo-600 px-3 py-1 rounded-full">₱{{ Number(course.price).toLocaleString() }}</span>
                                            <span v-else-if="course.access_type === 'paid_monthly'" class="text-white text-xs font-bold bg-indigo-600 px-3 py-1 rounded-full">₱{{ Number(course.price).toLocaleString() }}/mo</span>
                                            <span v-else-if="course.access_type === 'member_once'" class="text-white text-xs font-semibold bg-purple-600/80 px-3 py-1 rounded-full">For past members</span>
                                            <span v-else-if="course.access_type === 'free'" class="text-white text-xs font-semibold bg-green-600/80 px-3 py-1 rounded-full">Subscribe for Free</span>
                                            <span v-else class="text-white text-xs font-semibold bg-black/40 px-3 py-1 rounded-full">Members only</span>
                                        </div>
                                        <!-- Progress badge -->
                                        <div v-if="course.has_access && course.total > 0 && course.completed > 0"
                                            class="absolute top-2.5 right-2.5 px-2 py-0.5 bg-black/60 text-white text-xs font-semibold rounded-full backdrop-blur-sm">
                                            {{ course.progress }}%
                                        </div>
                                    </div>
                                    <div class="p-4 flex flex-col flex-1">
                                        <div class="flex items-start justify-between gap-2 mb-1">
                                            <h3 class="font-bold text-gray-900 group-hover:text-indigo-700 transition-colors line-clamp-1">{{ course.title }}</h3>
                                            <span v-if="course.access_type === 'free'" class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-green-100 text-green-700">FREE</span>
                                            <span v-else-if="course.access_type === 'inclusive'" class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700">INCLUDED</span>
                                            <span v-else-if="course.access_type === 'member_once'" class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700">ONE-TIME</span>
                                            <span v-else class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700">
                                                ₱{{ Number(course.price).toLocaleString() }}{{ course.access_type === 'paid_monthly' ? '/mo' : '' }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-500 mb-3 line-clamp-2 leading-relaxed flex-1">{{ course.description ?? '' }}</p>
                                        <div v-if="course.has_access" class="flex items-center gap-2">
                                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                                <div class="h-full bg-indigo-500 rounded-full transition-all" :style="{ width: `${course.progress}%` }" />
                                            </div>
                                            <span class="text-xs text-gray-400 shrink-0">{{ course.progress }}%</span>
                                        </div>
                                        <div v-else class="text-xs text-gray-400">
                                            <span v-if="course.access_type === 'paid_once'">One-time purchase to unlock</span>
                                            <span v-else-if="course.access_type === 'paid_monthly'">Monthly subscription to unlock</span>
                                            <span v-else-if="course.access_type === 'member_once'">Available to past & current members</span>
                                            <span v-else-if="course.access_type === 'free'">Subscribe for free to unlock</span>
                                            <span v-else-if="course.access_type === 'inclusive'">Join the community to unlock</span>
                                        </div>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </div>
                </template>
            </template>
        </div>

        <!-- Edit course modal -->
        <Teleport to="body">
            <div v-if="editingCourse" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="editingCourse = null">
                <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                    <h2 class="text-base font-bold text-gray-900 mb-4">Edit Course</h2>
                    <form @submit.prevent="submitEdit">
                        <input v-model="editForm.title" type="text" required placeholder="Course title"
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-2" />
                        <textarea v-model="editForm.description" rows="2" placeholder="Description (optional)"
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
                                <!-- One-Time (member_once) -->
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
                            <!-- Paid sub-choice -->
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

                        <!-- Affiliate commission rate -->
                        <div v-if="isPaidType(editForm.access_type)" class="mb-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Affiliate commission
                                <span class="text-gray-400 font-normal">· % of sale price paid to referring affiliate</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <input v-model="editForm.affiliate_commission_rate" type="number" min="0" max="100" step="1" placeholder="e.g. 30"
                                    class="w-24 px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-500">%</span>
                                <span v-if="editForm.affiliate_commission_rate && editForm.price" class="text-xs text-gray-400">
                                    = ₱{{ (editForm.price * editForm.affiliate_commission_rate / 100).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }} per sale
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Leave blank or 0 to disable affiliate commission for this course.</p>
                        </div>

                        <!-- Cover image -->
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Cover image</label>
                            <div class="relative mb-2 aspect-video rounded-lg overflow-hidden border border-gray-200 bg-gray-900">
                                <img v-if="editCoverPreview || editingCourse.cover_image"
                                    :src="editCoverPreview || editingCourse.cover_image"
                                    class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full bg-gradient-to-br from-indigo-600 to-purple-700 flex items-center justify-center">
                                    <span class="text-3xl font-black text-white/20">{{ editingCourse.title.charAt(0) }}</span>
                                </div>
                                <label class="absolute inset-0 flex items-center justify-center bg-black/30 hover:bg-black/50 cursor-pointer transition-colors group/img">
                                    <span class="text-white text-xs font-semibold bg-black/50 px-3 py-1.5 rounded-full group-hover/img:bg-black/70">
                                        {{ editCoverPreview ? 'Change photo' : 'Upload photo' }}
                                    </span>
                                    <input ref="editCoverInput" type="file" accept="image/*" class="hidden" @change="onEditCoverChange" />
                                </label>
                            </div>
                            <p class="text-xs text-gray-400">Recommended: 1280 × 720 px</p>
                        </div>

                        <!-- Preview video (Pro only) -->
                        <div v-if="isPro" class="mb-4">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Preview video</label>
                            <div v-if="editVideoPreview || editingCourse.preview_video" class="relative mb-2 aspect-video rounded-lg overflow-hidden border border-gray-200 bg-black">
                                <video :src="editVideoPreview || editingCourse.preview_video" class="w-full h-full object-cover" muted playsinline />
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
                                {{ (editVideoPreview || editingCourse.preview_video) ? 'Change video' : 'Upload preview' }}
                                <input ref="editVideoInput" type="file" accept="video/mp4,video/quicktime,video/webm" class="hidden" @change="onEditVideoChange" />
                            </label>
                            <p class="text-xs text-gray-400 mt-1">MP4 recommended, 1280 × 720 px, max 500 MB. Plays on hover.</p>
                            <label v-if="editVideoPreview || editingCourse?.preview_video" class="flex items-center gap-2 mt-2 cursor-pointer">
                                <input type="checkbox" v-model="editForm.preview_video_sound" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-xs text-gray-600">Enable sound on preview</span>
                            </label>
                        </div>

                        <div class="flex gap-2 justify-end">
                            <button type="button" @click="editingCourse = null; editCoverPreview = null"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancel</button>
                            <button type="submit" :disabled="editForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                {{ editForm.processing ? 'Saving...' : 'Save changes' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Empty state -->
        <div v-if="!courses.length" class="bg-white border border-gray-200 rounded-2xl p-16 text-center shadow-sm">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 flex items-center justify-center mx-auto mb-4">
                <span class="text-3xl">🎓</span>
            </div>
            <p class="text-sm font-medium text-gray-700 mb-1">No courses yet</p>
            <p class="text-xs text-gray-400">{{ isOwner ? 'Create your first course to get started.' : "The owner hasn't added any courses yet." }}</p>
        </div>

        <InviteModal
            :show="showInviteModal"
            :community-name="community.name"
            :community-slug="community.slug"
            :invite-url="inviteUrl"
            @close="showInviteModal = false"
        />
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';

import axios from 'axios';
import draggable from 'vuedraggable';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import InviteModal from '@/Components/InviteModal.vue';

const props = defineProps({
    community:     Object,
    courses:       Array,
    affiliate:     Object,
    membership:    Object,
    canManage:     Boolean,
    ownerPlan:     String,
});

const page      = usePage();
const isOwner   = props.canManage;
const isMember  = computed(() => !!props.membership);
const isPro     = computed(() => props.ownerPlan === 'pro');


// null = unlimited — based on community owner's plan
const courseLimit = computed(() => {
    const plan = props.ownerPlan ?? 'free';
    if (plan === 'pro')   return null;
    if (plan === 'basic') return 5;
    return 3;
});

// Local copy for draggable (owner only)
const localCourses = ref([...props.courses]);
watch(() => props.courses, (val) => { localCourses.value = [...val]; });

const groupedCourses = computed(() => ({
    'Free':      props.courses.filter(c => c.access_type === 'free'),
    'Included':  props.courses.filter(c => c.access_type === 'inclusive'),
    'One-Time':  props.courses.filter(c => c.access_type === 'member_once'),
    'Paid':      props.courses.filter(c => c.access_type === 'paid_once' || c.access_type === 'paid_monthly'),
}));

function onCourseReorder() {
    router.post(`/communities/${props.community.slug}/classroom/courses/reorder`, {
        course_ids: localCourses.value.map(c => c.id),
    }, { preserveScroll: true });
}

const showForm        = ref(false);
const showInviteModal = ref(false);
const coverPreview    = ref(null);
const coverInput      = ref(null);
const videoPreview       = ref(null);
const videoInput         = ref(null);
const videoUploading     = ref(false);
const videoUploadProgress = ref(0);
const videoUploadError   = ref('');

const isPaidType = (type) => type === 'paid_once' || type === 'paid_monthly';
const selectPaidIfNeeded = (form) => { if (!isPaidType(form.access_type)) form.access_type = 'paid_once'; };

const inviteUrl = computed(() =>
    props.affiliate?.code
        ? `${window.location.origin}/ref/${props.affiliate.code}`
        : `${window.location.origin}/communities/${props.community.slug}`
);

const courseForm = useForm({ title: '', description: '', cover_image: null, preview_video: null, preview_video_sound: false, access_type: 'inclusive', price: '', affiliate_commission_rate: '' });

function onCoverChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    courseForm.cover_image = file;
    coverPreview.value = URL.createObjectURL(file);
}

function removeCover() {
    courseForm.cover_image = null;
    coverPreview.value = null;
    if (coverInput.value) coverInput.value.value = '';
}

async function onVideoChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    videoUploading.value = true;
    videoUploadProgress.value = 0;
    videoUploadError.value = '';

    try {
        const { data } = await axios.post(`/communities/${props.community.slug}/classroom/preview-videos`, {
            filename: file.name,
            content_type: file.type,
            size: file.size,
        });

        const { default: rawAxios } = await import('axios');
        const s3Client = rawAxios.create({ withCredentials: false });
        await s3Client.put(data.upload_url, file, {
            headers: { 'Content-Type': file.type },
            onUploadProgress: (p) => { videoUploadProgress.value = Math.round((p.loaded / p.total) * 100); },
        });

        courseForm.preview_video = data.key;
        videoPreview.value = URL.createObjectURL(file);
    } catch (err) {
        videoUploadError.value = err.response?.data?.error || err.response?.data?.message || 'Upload failed. Please try again.';
        console.error('Preview video upload error:', err);
    } finally {
        videoUploading.value = false;
        e.target.value = '';
    }
}

function removeVideo() {
    courseForm.preview_video = null;
    videoPreview.value = null;
    videoUploadError.value = '';
    if (videoInput.value) videoInput.value.value = '';
}

function createCourse() {
    courseForm.post(`/communities/${props.community.slug}/classroom/courses`, {
        forceFormData: true,
        onSuccess: () => {
            courseForm.reset();
            courseForm.access_type = 'inclusive';
            courseForm.affiliate_commission_rate = '';
            removeCover();
            removeVideo();
            showForm.value = false;
        },
    });
}

// ── Edit course ───────────────────────────────────────────────────────────────
const editingCourse    = ref(null);
const editCoverPreview = ref(null);
const editCoverInput   = ref(null);
const editVideoPreview       = ref(null);
const editVideoInput         = ref(null);
const editVideoUploading     = ref(false);
const editVideoUploadProgress = ref(0);
const editVideoUploadError   = ref('');
const editForm         = useForm({ title: '', description: '', cover_image: null, preview_video: null, preview_video_sound: false, remove_preview_video: false, access_type: 'inclusive', price: '', affiliate_commission_rate: '' });

function openEdit(course) {
    editingCourse.value    = course;
    editCoverPreview.value = null;
    editVideoPreview.value = null;
    editForm.title         = course.title;
    editForm.description   = course.description ?? '';
    editForm.cover_image   = null;
    editForm.preview_video        = null;
    editForm.preview_video_sound  = course.preview_video_sound ?? false;
    editForm.remove_preview_video = false;
    editForm.access_type              = course.access_type ?? 'inclusive';
    editForm.price                    = course.price ?? '';
    editForm.affiliate_commission_rate = course.affiliate_commission_rate ?? '';
}

function onEditCoverChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    editForm.cover_image   = file;
    editCoverPreview.value = URL.createObjectURL(file);
}

async function onEditVideoChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    editVideoUploading.value = true;
    editVideoUploadProgress.value = 0;
    editVideoUploadError.value = '';

    try {
        const { data } = await axios.post(`/communities/${props.community.slug}/classroom/preview-videos`, {
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

        editForm.preview_video        = data.key;
        editForm.remove_preview_video = false;
        editVideoPreview.value = URL.createObjectURL(file);
    } catch (err) {
        editVideoUploadError.value = err.response?.data?.error || err.response?.data?.message || 'Upload failed. Please try again.';
        console.error('Preview video upload error:', err);
    } finally {
        editVideoUploading.value = false;
        e.target.value = '';
    }
}

function removeEditVideo() {
    editForm.preview_video        = null;
    editForm.remove_preview_video = true;
    editVideoPreview.value = null;
    editVideoUploadError.value = '';
    if (editVideoInput.value) editVideoInput.value.value = '';
    if (editingCourse.value) editingCourse.value = { ...editingCourse.value, preview_video: null };
}

function submitEdit() {
    editForm.post(
        `/communities/${props.community.slug}/classroom/courses/${editingCourse.value.id}/update`,
        {
            forceFormData: true,
            onSuccess: () => {
                editingCourse.value    = null;
                editCoverPreview.value = null;
                editVideoPreview.value = null;
            },
        }
    );
}

function togglePublish(course) {
    router.post(`/communities/${props.community.slug}/classroom/courses/${course.id}/toggle-publish`);
}

function deleteCourse(course) {
    if (!confirm(`Delete "${course.title}"? This will permanently remove all modules, lessons, quizzes, and certificates.`)) return;
    router.delete(`/communities/${props.community.slug}/classroom/courses/${course.id}`);
}

// ── Hover / tap-to-play preview video ────────────────────────────────────────
let touchTimer = null;
let hoverTimer = null;
const playStartTimes = {};

function getVideoEl(container, courseId) {
    return container.querySelector(`video[data-course-id="${courseId}"]`);
}

function findCourse(courseId) {
    return props.courses.find(c => c.id === courseId);
}

function startVideo(video, courseId) {
    const course = findCourse(courseId);
    const wantSound = course?.preview_video_sound;
    video.muted = !wantSound;
    video.currentTime = 0;
    video.play().then(() => {
        video.style.opacity = '1';
        playStartTimes[courseId] = Date.now();
    }).catch(() => {
        // Autoplay with sound blocked — fallback to muted
        video.muted = true;
        video.play().then(() => {
            video.style.opacity = '1';
            playStartTimes[courseId] = Date.now();
        }).catch(() => {});
    });
}

function stopVideoAndTrack(video, courseId) {
    video.style.opacity = '0';
    video.pause();
    video.muted = true;
    video.currentTime = 0;

    const startTime = playStartTimes[courseId];
    if (startTime) {
        const seconds = Math.round((Date.now() - startTime) / 1000);
        delete playStartTimes[courseId];
        if (seconds >= 1) {
            axios.post(`/communities/${props.community.slug}/classroom/courses/${courseId}/preview-play`, { seconds }).catch(() => {});
        }
    }
}

function onCardHover(e, course) {
    if (!course.preview_video) return;
    const container = e.currentTarget;
    hoverTimer = setTimeout(() => {
        const video = getVideoEl(container, course.id);
        if (!video) return;
        startVideo(video, course.id);
    }, 500);
}

function onCardLeave(e, course) {
    clearTimeout(hoverTimer);
    if (!course.preview_video) return;
    const video = getVideoEl(e.currentTarget, course.id);
    if (!video) return;
    stopVideoAndTrack(video, course.id);
}

function onCardTouchStart(e, course) {
    if (!course.preview_video) return;
    const container = e.currentTarget;
    touchTimer = setTimeout(() => {
        const video = getVideoEl(container, course.id);
        if (!video) return;
        startVideo(video, course.id);
    }, 400);
}

function onCardTouchEnd(course) {
    clearTimeout(touchTimer);
    if (!course.preview_video) return;
    document.querySelectorAll(`video[data-course-id="${course.id}"]`).forEach(v => {
        stopVideoAndTrack(v, course.id);
    });
}

function formatWatchTime(seconds) {
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return `${h}h ${m}m`;
}
</script>
