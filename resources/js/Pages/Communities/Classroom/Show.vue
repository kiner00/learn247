<template>
    <AppLayout :title="`${course.title} · ${community.name}`" :community="community">
        <CommunityTabs :community="community" active-tab="classroom" />

        <!-- Breadcrumb -->
        <div class="flex items-center justify-between gap-2 text-sm text-gray-500 mb-4">
            <div class="flex items-center gap-2">
                <Link :href="`/communities/${community.slug}/classroom`" class="hover:text-indigo-600 transition-colors">
                    Classroom
                </Link>
                <span>/</span>
                <span class="text-gray-800 font-medium">{{ course.title }}</span>
                <span v-if="isOwner && !course.is_published" class="px-2 py-0.5 bg-red-100 text-red-600 text-xs font-bold rounded-full uppercase tracking-wide">Draft</span>
            </div>
            <button v-if="isOwner" @click="togglePublish"
                :class="['inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                    course.is_published
                        ? 'bg-gray-100 text-gray-600 hover:bg-red-50 hover:text-red-600'
                        : 'bg-green-100 text-green-700 hover:bg-green-200']">
                <svg v-if="course.is_published" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
                <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                {{ course.is_published ? 'Set to Draft' : 'Publish' }}
            </button>
        </div>

        <!-- Progress bar (only shown when enrolled) -->
        <div v-if="hasAccess" class="bg-white border border-gray-200 rounded-2xl p-4 mb-6 shadow-sm flex items-center gap-4">
            <div class="flex-1">
                <div class="flex items-center justify-between text-xs text-gray-500 mb-1.5">
                    <span class="font-medium text-gray-700">{{ course.title }}</span>
                    <span>{{ doneIds.size }}/{{ totalLessons }} lessons</span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-indigo-500 rounded-full transition-all"
                        :style="{ width: `${currentProgress}%` }"
                    />
                </div>
            </div>
            <span class="text-sm font-black text-indigo-600 shrink-0">{{ currentProgress }}%</span>
        </div>

        <!-- Sales landing page (locked course) -->
        <div v-if="!hasAccess" class="mb-8">

            <!-- Hero: cover + title + CTA -->
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm mb-4">
                <!-- Cover image -->
                <div class="relative w-full bg-gray-900" style="aspect-ratio:16/7;">
                    <img
                        v-if="course.cover_image"
                        :src="course.cover_image"
                        :alt="course.title"
                        class="w-full h-full object-cover opacity-90"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-600 to-purple-700">
                        <svg class="w-16 h-16 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <!-- Price badge overlay -->
                    <div class="absolute top-3 right-3">
                        <span class="px-3 py-1.5 bg-indigo-600 text-white text-sm font-black rounded-xl shadow-lg">
                            {{ course.access_type === 'paid_once' ? `₱${Number(course.price).toLocaleString()}` : course.access_type === 'paid_monthly' ? `₱${Number(course.price).toLocaleString()}/mo` : 'Members Only' }}
                        </span>
                    </div>
                </div>

                <!-- Title + description + CTA -->
                <div class="p-6">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-5">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-indigo-500 uppercase tracking-wide mb-1">{{ community.name }}</p>
                            <h1 class="text-2xl font-black text-gray-900 leading-tight mb-3">{{ course.title }}</h1>
                            <p v-if="course.description" class="text-sm text-gray-600 leading-relaxed">{{ course.description }}</p>
                            <div class="flex items-center gap-4 mt-4 text-xs text-gray-400">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                    {{ totalLessons }} lessons
                                </span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    {{ course.modules?.length ?? 0 }} modules
                                </span>
                                <span v-if="course.access_type === 'paid_once'" class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    Lifetime access
                                </span>
                            </div>
                        </div>

                        <!-- CTA block -->
                        <div class="shrink-0 flex flex-col items-center gap-2 md:items-end">
                            <template v-if="course.access_type === 'paid_once' || course.access_type === 'paid_monthly'">
                                <div v-if="!authUserId">
                                    <Link :href="`/login`"
                                        class="inline-block px-8 py-3 bg-indigo-600 text-white text-base font-black rounded-2xl hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-200">
                                        Sign in to enroll
                                    </Link>
                                </div>
                                <div v-else-if="enrollment?.status === 'pending'" class="flex items-center gap-2">
                                    <span class="text-xs text-amber-600 font-medium">Payment pending…</span>
                                    <button @click="enrollInCourse" :disabled="enrollForm.processing"
                                        class="px-5 py-2.5 border border-amber-400 text-amber-700 text-sm font-semibold rounded-xl hover:bg-amber-50 transition-colors">
                                        Retry payment
                                    </button>
                                </div>
                                <div v-else class="text-center md:text-right">
                                    <button @click="enrollInCourse" :disabled="enrollForm.processing"
                                        class="px-8 py-3 bg-indigo-600 text-white text-base font-black rounded-2xl hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-md shadow-indigo-200 whitespace-nowrap">
                                        {{ enrollForm.processing ? 'Redirecting...' : `Get Access · ₱${Number(course.price).toLocaleString()}${course.access_type === 'paid_monthly' ? '/mo' : ''}` }}
                                    </button>
                                    <p class="text-[10px] text-gray-400 mt-1.5">Processed securely under <strong>learn247</strong></p>
                                </div>
                            </template>
                            <template v-else>
                                <Link :href="`/communities/${community.slug}/about`"
                                    class="px-8 py-3 bg-indigo-600 text-white text-base font-black rounded-2xl hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-200">
                                    Join Community to Unlock
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- What's inside -->
            <div v-if="course.modules?.length" class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">What's inside</h2>
                </div>
                <div class="divide-y divide-gray-50">
                    <div v-for="mod in course.modules" :key="mod.id" class="px-5 py-3">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ mod.title }}</p>
                        <div class="space-y-1">
                            <div v-for="lesson in mod.lessons" :key="lesson.id"
                                class="flex items-center gap-2 text-sm text-gray-600">
                                <svg class="w-3.5 h-3.5 text-indigo-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>{{ lesson.title }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course complete banner -->
        <div v-if="currentProgress === 100" class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6 flex items-center gap-3 shadow-sm">
            <span class="text-2xl">🎉</span>
            <div>
                <p class="text-sm font-bold text-amber-900">Course Complete!</p>
                <p class="text-xs text-amber-700">You've finished all lessons.</p>
            </div>
        </div>

        <div v-if="hasAccess" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Sidebar: module + lesson tree -->
            <div class="space-y-3">
                <div
                    v-for="mod in course.modules"
                    :key="mod.id"
                    class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm"
                >
                    <!-- Module header: editable for owner -->
                    <div class="flex items-center justify-between px-4 py-3">
                        <div v-if="isOwner && editingModuleId === mod.id" class="flex-1 flex items-center gap-1.5 mr-2">
                            <input
                                v-model="moduleEditTitle"
                                type="text"
                                @keydown.enter.prevent="saveModuleTitle(mod)"
                                @keydown.escape="editingModuleId = null"
                                class="flex-1 px-2 py-1 border border-indigo-400 rounded-lg text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                autofocus
                            />
                            <button @click="saveModuleTitle(mod)" class="text-indigo-600 hover:text-indigo-800 text-xs font-bold px-1.5">✓</button>
                            <button @click="editingModuleId = null" class="text-gray-400 hover:text-gray-600 text-xs px-1">✕</button>
                        </div>
                        <button
                            v-else
                            @click="toggleModule(mod.id)"
                            class="flex-1 text-left text-sm font-semibold text-gray-800 hover:text-indigo-700 transition-colors"
                        >{{ mod.title }}</button>
                        <div class="flex items-center gap-1.5 shrink-0 ml-2">
                            <span v-if="mod.is_free" class="px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-green-700 bg-green-100 rounded-full">Free</span>
                            <button
                                v-if="isOwner && editingModuleId !== mod.id"
                                @click.stop="toggleModuleFree(mod)"
                                class="px-1.5 py-0.5 rounded-lg text-[10px] font-medium transition-colors"
                                :class="mod.is_free ? 'text-green-600 bg-green-50 hover:bg-green-100' : 'text-gray-400 bg-gray-50 hover:bg-gray-100'"
                                :title="mod.is_free ? 'Mark as paid' : 'Mark as free'"
                            >
                                {{ mod.is_free ? '🔓' : '🔒' }}
                            </button>
                            <button
                                v-if="isOwner && editingModuleId !== mod.id"
                                @click.stop="startEditModule(mod)"
                                class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition-colors"
                                title="Edit module title"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.293-6.293a1 1 0 011.414 0l2.586 2.586a1 1 0 010 1.414L13 17H9v-4z"/>
                                </svg>
                                Edit
                            </button>
                            <button
                                v-if="isOwner && editingModuleId !== mod.id"
                                @click.stop="deleteModule(mod)"
                                class="flex items-center px-1.5 py-1 rounded-lg text-xs font-medium text-red-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                title="Delete module"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                            <span class="text-xs text-gray-400" @click="toggleModule(mod.id)">
                                {{ completedInModule(mod) }}/{{ mod.lessons.length }}
                                <span class="ml-1">{{ openModules.has(mod.id) ? '▲' : '▼' }}</span>
                            </span>
                        </div>
                    </div>

                    <div v-if="openModules.has(mod.id)" class="border-t border-gray-100">
                        <draggable
                            v-if="isOwner"
                            :list="mod.lessons"
                            item-key="id"
                            handle=".drag-handle"
                            ghost-class="opacity-30"
                            @end="onLessonDragEnd(mod)"
                        >
                            <template #item="{ element: lesson }">
                                <div
                                    class="w-full flex items-center gap-2 px-4 py-2.5 text-left hover:bg-indigo-50 transition-colors border-b border-gray-50 last:border-0 cursor-pointer"
                                    :class="selectedLesson?.id === lesson.id ? 'bg-amber-50' : ''"
                                    @click="selectLesson(lesson)"
                                >
                                    <span class="drag-handle cursor-grab active:cursor-grabbing text-gray-300 hover:text-gray-500 shrink-0" @click.stop>
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                            <circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/>
                                            <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                                            <circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
                                        </svg>
                                    </span>
                                    <span
                                        class="w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0 text-xs"
                                        :class="isCompleted(lesson.id)
                                            ? 'bg-indigo-500 border-indigo-500 text-white'
                                            : 'border-gray-300'"
                                    >
                                        <span v-if="isCompleted(lesson.id)">✓</span>
                                    </span>
                                    <span
                                        class="text-sm text-gray-700 truncate"
                                        :class="selectedLesson?.id === lesson.id ? 'font-semibold text-indigo-700' : ''"
                                    >
                                        {{ lesson.title }}
                                    </span>
                                    <span v-if="lesson.quiz" class="shrink-0 text-xs px-1.5 py-0.5 rounded-full"
                                        :class="bestAttempt(lesson.quiz?.id)?.passed ? 'bg-green-100 text-green-700' : 'bg-indigo-50 text-indigo-500'">
                                        {{ bestAttempt(lesson.quiz?.id)?.passed ? '✓ Quiz' : '📝' }}
                                    </span>
                                    <button @click.stop="deleteLesson(mod, lesson)"
                                        class="ml-auto shrink-0 text-gray-300 hover:text-red-500 transition-colors p-0.5 rounded"
                                        title="Delete lesson">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </draggable>
                        <template v-else>
                            <button
                                v-for="lesson in mod.lessons"
                                :key="lesson.id"
                                @click="selectLesson(lesson)"
                                class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-indigo-50 transition-colors border-b border-gray-50 last:border-0"
                                :class="selectedLesson?.id === lesson.id ? 'bg-amber-50' : ''"
                            >
                                <span
                                    class="w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0 text-xs"
                                    :class="isCompleted(lesson.id)
                                        ? 'bg-indigo-500 border-indigo-500 text-white'
                                        : 'border-gray-300'"
                                >
                                    <span v-if="isCompleted(lesson.id)">✓</span>
                                </span>
                                <span
                                    class="text-sm text-gray-700 truncate"
                                    :class="selectedLesson?.id === lesson.id ? 'font-semibold text-indigo-700' : ''"
                                >
                                    {{ lesson.title }}
                                </span>
                                <span v-if="lesson.quiz" class="ml-auto shrink-0 text-xs px-1.5 py-0.5 rounded-full"
                                    :class="bestAttempt(lesson.quiz?.id)?.passed ? 'bg-green-100 text-green-700' : 'bg-indigo-50 text-indigo-500'">
                                    {{ bestAttempt(lesson.quiz?.id)?.passed ? '✓ Quiz' : '📝' }}
                                </span>
                            </button>
                        </template>

                        <!-- Add lesson (owner only) -->
                        <div v-if="isOwner" class="px-4 py-2 border-t border-gray-100">
                            <form v-if="addingLessonToModule === mod.id" @submit.prevent="createLesson(mod)" class="space-y-1.5">
                                <input
                                    v-model="lessonForm.title"
                                    type="text"
                                    placeholder="Lesson title"
                                    required
                                    class="w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                                <LessonEditor
                                    v-model="lessonForm.content"
                                    placeholder="Description (optional)"
                                    min-height="80px"
                                    :upload-url="lessonImageUploadUrl"
                                />
                                <input
                                    v-model="lessonForm.video_url"
                                    type="url"
                                    placeholder="YouTube, Vimeo, or Google Drive link"
                                    class="w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                                <div class="flex gap-1.5">
                                    <button type="button" @click="addingLessonToModule = null" class="flex-1 py-1 text-xs text-gray-500">Cancel</button>
                                    <button type="submit" :disabled="lessonForm.processing"
                                        class="flex-1 py-1 bg-indigo-600 text-white text-xs rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                        {{ lessonForm.processing ? 'Adding...' : 'Add' }}
                                    </button>
                                </div>
                            </form>
                            <button
                                v-else
                                @click="addingLessonToModule = mod.id; lessonForm.reset()"
                                class="text-xs text-indigo-500 hover:text-indigo-700 font-medium"
                            >
                                + Add Lesson
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Add module (owner only) -->
                <div v-if="isOwner" class="bg-white border border-dashed border-gray-300 rounded-2xl p-4">
                    <form v-if="showModuleForm" @submit.prevent="createModule" class="space-y-2">
                        <input
                            v-model="moduleForm.title"
                            type="text"
                            placeholder="Module title"
                            required
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <div class="flex gap-2">
                            <button type="button" @click="showModuleForm = false" class="flex-1 py-1.5 text-sm text-gray-500">Cancel</button>
                            <button type="submit" :disabled="moduleForm.processing" class="flex-1 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 disabled:opacity-50">Add</button>
                        </div>
                    </form>
                    <button v-else @click="showModuleForm = true"
                        class="w-full text-sm text-gray-400 hover:text-indigo-600 text-center font-medium">
                        + Add Module
                    </button>
                </div>
            </div>

            <!-- Main content area -->
            <div class="lg:col-span-2 space-y-4">
                <!-- Locked overlay (covers main content) -->
                <div v-if="!hasAccess" class="relative bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                    <!-- Blurred preview of first lesson content -->
                    <div class="blur-sm opacity-40 pointer-events-none select-none px-6 py-5 space-y-4">
                        <div class="h-5 bg-gray-200 rounded w-3/4" />
                        <div class="h-48 bg-gray-100 rounded-xl" />
                        <div class="h-4 bg-gray-200 rounded w-full" />
                        <div class="h-4 bg-gray-200 rounded w-5/6" />
                        <div class="h-4 bg-gray-200 rounded w-2/3" />
                    </div>
                    <!-- Lock icon centred -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <div class="w-14 h-14 rounded-full bg-white shadow-lg flex items-center justify-center mb-3">
                            <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-bold text-gray-700">Content locked</p>
                        <p class="text-xs text-gray-400 mt-1">
                            {{ course.access_type === 'paid_once' ? 'Purchase this course to unlock'
                             : course.access_type === 'paid_monthly' ? 'Subscribe to unlock this course'
                             : course.access_type === 'member_once' ? 'Available to past & current paying members'
                             : 'Join the community to unlock' }}
                        </p>
                    </div>
                </div>

                <div v-if="selectedLesson && hasAccess" class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-lg font-black text-gray-900">{{ selectedLesson.title }}</h2>
                        <span v-if="isCompleted(selectedLesson.id)"
                            class="text-xs font-semibold text-green-600 bg-green-50 px-2.5 py-1 rounded-full">
                            ✓ Completed
                        </span>
                    </div>

                    <!-- Uploaded video (served via signed URL) -->
                    <div v-if="selectedLesson.video_path" class="bg-black">
                        <div v-if="videoStreamLoading" class="flex items-center justify-center h-64 text-white text-sm">
                            Loading video…
                        </div>
                        <video
                            v-else-if="videoStreamUrl"
                            :src="videoStreamUrl"
                            controls
                            class="w-full max-h-120 object-contain"
                            controlsList="nodownload nofullscreen"
                            disablePictureInPicture
                            oncontextmenu="return false;"
                        />
                    </div>

                    <!-- YouTube / external URL -->
                    <div v-else-if="selectedLesson.video_url" class="aspect-video bg-gray-900">
                        <iframe
                            :src="embedUrl(selectedLesson.video_url)"
                            class="w-full h-full"
                            allowfullscreen
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        />
                    </div>

                    <div class="px-6 py-5">
                        <SafeHtmlRenderer
                            v-if="selectedLesson.embed_html && !editingLesson"
                            :html="selectedLesson.embed_html"
                        />
                        <SafeHtmlRenderer
                            v-if="selectedLesson.content && !editingLesson"
                            :html="selectedLesson.content"
                        />

                        <!-- Edit form (owner only) -->
                        <div v-if="isOwner && editingLesson" class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200 space-y-3">
                            <p class="text-xs font-semibold text-gray-700">Edit Lesson</p>
                            <div>
                                <p class="text-xs text-gray-500 mb-1.5 font-medium">Title</p>
                                <input
                                    v-model="contentForm.title"
                                    type="text"
                                    placeholder="Lesson title"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                            <LessonEditor
                                v-model="contentForm.content"
                                placeholder="Lesson description / notes..."
                                min-height="140px"
                                :upload-url="lessonImageUploadUrl"
                            />
                            <div>
                                <p class="text-xs text-gray-500 mb-1.5 font-medium">Video URL</p>
                                <input
                                    v-model="contentForm.video_url"
                                    type="url"
                                    placeholder="YouTube, Vimeo, or Google Drive link"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                            <div v-if="canUploadVideo">
                                <p class="text-xs text-gray-500 mb-1.5 font-medium">
                                    Upload Video
                                    <span class="ml-1 px-1.5 py-0.5 bg-indigo-100 text-indigo-700 text-[10px] font-bold rounded-full uppercase">Pro</span>
                                </p>
                                <div class="flex items-center gap-3">
                                    <label class="flex-1 flex items-center justify-center gap-2 px-3 py-2.5 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/50 transition-colors">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                        </svg>
                                        <span class="text-xs text-gray-500">
                                            {{ videoUploading ? `Uploading... ${videoUploadProgress}%` : 'Choose video file (MP4, WebM, MOV — max 500MB)' }}
                                        </span>
                                        <input
                                            type="file"
                                            accept="video/mp4,video/webm,video/quicktime"
                                            class="hidden"
                                            :disabled="videoUploading"
                                            @change="handleVideoUpload"
                                        />
                                    </label>
                                </div>
                                <p v-if="videoUploadError" class="text-xs text-red-500 mt-1">{{ videoUploadError }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1.5 font-medium">Embed Code <span class="text-gray-400">(paste iframe / script embeds here)</span></p>
                                <textarea
                                    v-model="contentForm.embed_html"
                                    placeholder="Paste your embed code here (e.g. converteai, Vimeo embed, etc.)"
                                    rows="4"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1.5 font-medium">CTA Button (optional)</p>
                                <div class="flex gap-2">
                                    <input
                                        v-model="contentForm.cta_label"
                                        type="text"
                                        placeholder="Button label (e.g. Book a Call)"
                                        class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    />
                                    <input
                                        v-model="contentForm.cta_url"
                                        type="url"
                                        placeholder="https://..."
                                        class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    />
                                </div>
                            </div>
                            <div class="flex gap-2 justify-end">
                                <button type="button" @click="editingLesson = false" class="px-4 py-2 text-sm text-gray-500">Cancel</button>
                                <button type="button" @click="saveContent" :disabled="contentForm.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                    {{ contentForm.processing ? 'Saving...' : 'Save Changes' }}
                                </button>
                            </div>
                        </div>

                        <!-- CTA Button -->
                        <div v-if="selectedLesson.cta_url && selectedLesson.cta_label && !editingLesson" class="mb-5">
                            <a
                                :href="selectedLesson.cta_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm"
                            >
                                {{ selectedLesson.cta_label }}
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                        </div>

                        <!-- Action bar -->
                        <div class="flex gap-3 pt-4 border-t border-gray-100">
                            <button
                                @click="markComplete"
                                :disabled="isCompleted(selectedLesson.id) || completeForm.processing"
                                class="px-5 py-2.5 text-sm font-semibold rounded-xl transition-colors"
                                :class="isCompleted(selectedLesson.id)
                                    ? 'bg-green-100 text-green-700 cursor-default'
                                    : 'bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50'"
                            >
                                {{ isCompleted(selectedLesson.id) ? '✓ Completed' : 'Mark as Complete' }}
                            </button>
                            <button
                                v-if="isOwner && !editingLesson"
                                @click="startEdit"
                                class="px-4 py-2.5 text-sm text-gray-500 border border-gray-200 rounded-xl hover:bg-gray-50"
                            >
                                Edit Lesson
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ─── Quiz section ─────────────────────────────────────────────── -->
                <div v-if="selectedLesson?.quiz && hasAccess" class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-base">📝 {{ selectedLesson.quiz.title }}</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Pass score: {{ selectedLesson.quiz.pass_score }}%</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span v-if="currentAttempt?.passed"
                                class="text-xs font-semibold text-green-700 bg-green-50 px-2.5 py-1 rounded-full">
                                ✓ Passed {{ currentAttempt.score }}%
                            </span>
                            <span v-else-if="currentAttempt"
                                class="text-xs font-semibold text-red-600 bg-red-50 px-2.5 py-1 rounded-full">
                                {{ currentAttempt.score }}% – Retake available
                            </span>
                            <!-- Owner: delete quiz -->
                            <button v-if="isOwner" @click="deleteQuiz"
                                class="text-xs text-red-400 hover:text-red-600 border border-red-200 px-2.5 py-1 rounded-lg">
                                Delete Quiz
                            </button>
                        </div>
                    </div>

                    <!-- Quiz result banner -->
                    <div v-if="quizResult" class="mx-6 mt-4 p-4 rounded-xl border"
                        :class="quizResult.passed ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'">
                        <p class="font-bold text-sm" :class="quizResult.passed ? 'text-green-800' : 'text-red-800'">
                            {{ quizResult.passed ? '🎉 You passed!' : '😕 Not quite' }}
                        </p>
                        <p class="text-xs mt-0.5" :class="quizResult.passed ? 'text-green-600' : 'text-red-600'">
                            Score: {{ quizResult.score }}% ({{ quizResult.correct }}/{{ quizResult.total }} correct)
                        </p>
                    </div>

                    <!-- Take quiz -->
                    <div v-if="!quizResult || !quizResult.passed" class="p-6 space-y-6">
                        <div
                            v-for="(question, qi) in selectedLesson.quiz.questions"
                            :key="question.id"
                            class="space-y-2"
                        >
                            <p class="text-sm font-semibold text-gray-800">{{ qi + 1 }}. {{ question.question }}</p>
                            <div class="space-y-1.5">
                                <label
                                    v-for="option in question.options"
                                    :key="option.id"
                                    class="flex items-center gap-3 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                                    :class="quizAnswers[question.id] === option.id
                                        ? 'border-indigo-400 bg-indigo-50'
                                        : 'border-gray-200 hover:bg-gray-50'"
                                >
                                    <input
                                        type="radio"
                                        :name="`q_${question.id}`"
                                        :value="option.id"
                                        v-model="quizAnswers[question.id]"
                                        class="accent-indigo-600"
                                    />
                                    <span class="text-sm text-gray-700">{{ option.label }}</span>
                                </label>
                            </div>
                        </div>

                        <button
                            @click="submitQuiz"
                            :disabled="quizForm.processing || !allAnswered"
                            class="w-full py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                        >
                            {{ quizForm.processing ? 'Submitting...' : 'Submit Quiz' }}
                        </button>
                    </div>

                    <!-- Retake button -->
                    <div v-if="quizResult && !quizResult.passed" class="px-6 pb-6">
                        <button @click="retakeQuiz" class="text-sm text-indigo-600 font-medium hover:text-indigo-800">
                            ↺ Retake Quiz
                        </button>
                    </div>
                </div>

                <!-- Owner: Add quiz (when no quiz exists) -->
                <div v-if="isOwner && hasAccess && selectedLesson && !selectedLesson.quiz" class="bg-white border border-dashed border-gray-300 rounded-2xl p-5">
                    <div v-if="!showQuizBuilder">
                        <button @click="showQuizBuilder = true"
                            class="w-full text-sm text-gray-400 hover:text-indigo-600 text-center font-medium">
                            + Add Quiz to this Lesson
                        </button>
                    </div>

                    <!-- Quiz builder -->
                    <div v-else class="space-y-4">
                        <h4 class="text-sm font-bold text-gray-800">Quiz Builder</h4>

                        <input v-model="quizBuilderForm.title" type="text" placeholder="Quiz title"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />

                        <div class="flex items-center gap-3">
                            <label class="text-xs text-gray-500 shrink-0">Pass score (%)</label>
                            <input v-model.number="quizBuilderForm.pass_score" type="number" min="1" max="100"
                                class="w-24 px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>

                        <div v-for="(q, qi) in quizBuilderForm.questions" :key="qi" class="p-4 bg-gray-50 rounded-xl border border-gray-200 space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-gray-500">Q{{ qi + 1 }}</span>
                                <input v-model="q.question" type="text" placeholder="Question text"
                                    class="flex-1 px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <button @click="quizBuilderForm.questions.splice(qi, 1)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                            </div>

                            <div v-for="(opt, oi) in q.options" :key="oi" class="flex items-center gap-2 pl-6">
                                <input type="radio" :name="`builder_q_${qi}`" @change="setCorrect(qi, oi)"
                                    :checked="opt.is_correct" class="accent-indigo-600" title="Mark as correct" />
                                <input v-model="opt.label" type="text" :placeholder="`Option ${oi + 1}`"
                                    class="flex-1 px-2.5 py-1 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <button v-if="q.options.length > 2" @click="q.options.splice(oi, 1)" class="text-red-400 text-xs">✕</button>
                            </div>
                            <button @click="q.options.push({ label: '', is_correct: false })"
                                class="text-xs text-indigo-500 hover:text-indigo-700 pl-6 font-medium">+ Add Option</button>
                        </div>

                        <button @click="addQuestion"
                            class="w-full py-2 border border-dashed border-indigo-300 text-sm text-indigo-500 hover:text-indigo-700 rounded-xl">
                            + Add Question
                        </button>

                        <div class="flex gap-2 justify-end">
                            <button @click="showQuizBuilder = false; resetQuizBuilder()" class="px-4 py-2 text-sm text-gray-500">Cancel</button>
                            <button @click="saveQuiz" :disabled="quizBuilderForm.processing"
                                class="px-5 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 disabled:opacity-50">
                                {{ quizBuilderForm.processing ? 'Saving...' : 'Save Quiz' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ─── Lesson comments ──────────────────────────────────────────── -->
                <div v-if="selectedLesson && hasAccess" class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="font-bold text-gray-900 text-base">
                            💬 Discussion
                            <span class="text-sm font-normal text-gray-400 ml-1">({{ currentComments.length }})</span>
                        </h3>
                    </div>

                    <div class="p-6 space-y-4">
                        <!-- Comment form -->
                        <form @submit.prevent="postComment" class="flex gap-3">
                            <div class="flex-1">
                                <textarea
                                    v-model="commentForm.content"
                                    rows="2"
                                    placeholder="Ask a question or leave a comment..."
                                    class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                                />
                            </div>
                            <button type="submit" :disabled="commentForm.processing || !commentForm.content.trim()"
                                class="self-end px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                                Post
                            </button>
                        </form>

                        <!-- Comments list -->
                        <div v-if="currentComments.length" class="space-y-3">
                            <div v-for="comment in currentComments" :key="comment.id"
                                class="flex gap-3">
                                <!-- Avatar -->
                                <div class="shrink-0">
                                    <img v-if="comment.author?.avatar" :src="comment.author.avatar"
                                        class="w-8 h-8 rounded-full object-cover" />
                                    <div v-else class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">
                                        {{ comment.author?.name?.charAt(0) }}
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="bg-gray-50 rounded-xl px-4 py-3">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs font-bold text-gray-800">{{ comment.author?.name }}</span>
                                            <button
                                                v-if="comment.author?.id === authUserId || isOwner"
                                                @click="deleteComment(comment.id)"
                                                class="text-xs text-red-400 hover:text-red-600"
                                            >✕</button>
                                        </div>
                                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ comment.content }}</p>
                                    </div>
                                    <!-- Replies -->
                                    <div v-if="comment.replies?.length" class="ml-4 mt-2 space-y-2">
                                        <div v-for="reply in comment.replies" :key="reply.id" class="flex gap-2">
                                            <img v-if="reply.author?.avatar" :src="reply.author.avatar"
                                                class="w-6 h-6 rounded-full object-cover shrink-0" />
                                            <div v-else class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-500 shrink-0">
                                                {{ reply.author?.name?.charAt(0) }}
                                            </div>
                                            <div class="bg-gray-50 rounded-xl px-3 py-2 flex-1">
                                                <p class="text-xs font-bold text-gray-800 mb-0.5">{{ reply.author?.name }}</p>
                                                <p class="text-xs text-gray-700">{{ reply.content }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-400 text-center py-4">No comments yet. Be the first to ask a question!</p>
                    </div>
                </div>

                <!-- No lesson selected -->
                <div v-if="!selectedLesson && hasAccess" class="bg-white border border-gray-200 rounded-2xl p-14 text-center shadow-sm">
                    <span class="text-4xl block mb-3">🎓</span>
                    <p class="text-sm font-medium text-gray-700 mb-1">{{ course.description || course.title }}</p>
                    <p class="text-xs text-gray-400">Select a lesson from the sidebar to get started</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import LessonEditor from '@/Components/LessonEditor.vue';
import SafeHtmlRenderer from '@/Components/SafeHtmlRenderer.vue';

const props = defineProps({
    community:          Object,
    course:             Object,
    hasAccess:          Boolean,
    enrollment:         Object,   // { status } or null
    completedIds:       Array,
    progress:           Number,
    lessonComments:     Object,   // { [lesson_id]: Comment[] }
    quizAttempts:       Object,   // { [quiz_id]: QuizAttempt }
    canManage:          Boolean,
    canUploadVideo:     Boolean,
});

const page      = usePage();
const isOwner   = props.canManage;
const authUserId = page.props.auth?.user?.id;
const lessonImageUploadUrl = `/communities/${props.community.slug}/classroom/lesson-images`;
const lessonVideoUploadUrl = `/communities/${props.community.slug}/classroom/lesson-videos`;

// ─── Completion ───────────────────────────────────────────────────────────────
const doneIds = ref(new Set(props.completedIds));
const isCompleted = (id) => doneIds.value.has(id);

const totalLessons = computed(() =>
    props.course.modules.reduce((sum, m) => sum + m.lessons.length, 0)
);

const currentProgress = computed(() => {
    const total = totalLessons.value;
    return total > 0 ? Math.round((doneIds.value.size / total) * 100) : 0;
});

function completedInModule(mod) {
    return mod.lessons.filter((l) => isCompleted(l.id)).length;
}

// ─── Sidebar ──────────────────────────────────────────────────────────────────
const openModules = ref(new Set(props.course.modules.map((m) => m.id)));

function toggleModule(id) {
    const next = new Set(openModules.value);
    next.has(id) ? next.delete(id) : next.add(id);
    openModules.value = next;
}

const selectedLesson   = ref(props.course.modules[0]?.lessons[0] ?? null);

// Keep selectedLesson in sync when Inertia refreshes props (e.g. after quiz save)
watch(() => props.course, (updatedCourse) => {
    if (!selectedLesson.value) return;
    const id = selectedLesson.value.id;
    for (const mod of updatedCourse.modules) {
        const fresh = mod.lessons.find((l) => l.id === id);
        if (fresh) {
            const videoChanged = fresh.video_path !== selectedLesson.value.video_path;
            selectedLesson.value = fresh;
            if (videoChanged) fetchVideoStreamUrl(fresh);
            return;
        }
    }
}, { deep: true });

function selectLesson(lesson) {
    selectedLesson.value  = lesson;
    editingLesson.value   = false;
    quizResult.value      = null;
    resetQuizAnswers();
    commentForm.reset();
    fetchVideoStreamUrl(lesson);
}

// ─── Secure video streaming via signed URLs ─────────────────────────────────
const videoStreamUrl    = ref(null);
const videoStreamLoading = ref(false);

async function fetchVideoStreamUrl(lesson) {
    videoStreamUrl.value = null;
    if (!lesson?.video_path) return;

    videoStreamLoading.value = true;
    try {
        const { data } = await axios.get(
            `/communities/${props.community.slug}/classroom/courses/${props.course.id}/lessons/${lesson.id}/stream`
        );
        videoStreamUrl.value = data.url;
    } catch {
        videoStreamUrl.value = null;
    } finally {
        videoStreamLoading.value = false;
    }
}

// Fetch signed URL for the initially selected lesson
if (selectedLesson.value?.video_path) {
    fetchVideoStreamUrl(selectedLesson.value);
}

// ─── Mark complete ─────────────────────────────────────────────────────────────
const completeForm = useForm({});

function markComplete() {
    if (!selectedLesson.value || isCompleted(selectedLesson.value.id)) return;
    const lessonId = selectedLesson.value.id;
    completeForm.post(
        `/communities/${props.community.slug}/classroom/courses/${props.course.id}/lessons/${lessonId}/complete`,
        {
            preserveScroll: true,
            onSuccess: () => {
                const next = new Set(doneIds.value);
                next.add(lessonId);
                doneIds.value = next;
            },
        }
    );
}

// ─── Edit module title ─────────────────────────────────────────────────────────
const editingModuleId  = ref(null);
const moduleEditTitle  = ref('');
const moduleEditForm   = useForm({ title: '' });

function startEditModule(mod) {
    editingModuleId.value = mod.id;
    moduleEditTitle.value = mod.title;
}

function saveModuleTitle(mod) {
    if (!moduleEditTitle.value.trim()) return;
    moduleEditForm.title = moduleEditTitle.value.trim();
    moduleEditForm
        .transform((data) => ({ ...data, _method: 'PATCH' }))
        .post(
            `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules/${mod.id}`,
            {
                preserveScroll: true,
                onSuccess: () => { editingModuleId.value = null; },
            }
        );
}

// ─── Delete lesson ────────────────────────────────────────────────────────────
function deleteLesson(mod, lesson) {
    if (!confirm(`Delete lesson "${lesson.title}"?`)) return;
    router.delete(
        `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules/${mod.id}/lessons/${lesson.id}`,
        { preserveScroll: true }
    );
}

// ─── Publish / unpublish course ───────────────────────────────────────────────
function togglePublish() {
    router.post(`/communities/${props.community.slug}/classroom/courses/${props.course.id}/toggle-publish`, {}, { preserveScroll: true });
}

// ─── Delete module ────────────────────────────────────────────────────────────
function deleteModule(mod) {
    if (!confirm(`Delete module "${mod.title}" and all its lessons?`)) return;
    router.delete(
        `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules/${mod.id}`,
        { preserveScroll: true }
    );
}

// ─── Toggle module free ────────────────────────────────────────────────────────
function toggleModuleFree(mod) {
    axios.patch(
        `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules/${mod.id}`,
        { title: mod.title, is_free: !mod.is_free }
    ).then(() => {
        mod.is_free = !mod.is_free;
    });
}

// ─── Add module ────────────────────────────────────────────────────────────────
const showModuleForm = ref(false);
const moduleForm     = useForm({ title: '' });

function createModule() {
    moduleForm.post(
        `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules`,
        { onSuccess: () => { moduleForm.reset(); showModuleForm.value = false; } }
    );
}

// ─── Add lesson ────────────────────────────────────────────────────────────────
const addingLessonToModule = ref(null);
const lessonForm = useForm({ title: '', content: '', video_url: '', cta_label: '', cta_url: '' });

function createLesson(mod) {
    lessonForm.post(
        `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules/${mod.id}/lessons`,
        { onSuccess: () => { lessonForm.reset(); addingLessonToModule.value = null; } }
    );
}

// ─── Reorder lessons ──────────────────────────────────────────────────────────
function onLessonDragEnd(mod) {
    const lessonIds = mod.lessons.map((l) => l.id);
    axios.post(
        `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules/${mod.id}/lessons/reorder`,
        { lesson_ids: lessonIds }
    );
}

// ─── Edit lesson ──────────────────────────────────────────────────────────────
const editingLesson = ref(false);
const contentForm   = useForm({ title: '', content: '', embed_html: '', video_url: '', cta_label: '', cta_url: '' });

function startEdit() {
    const l = selectedLesson.value;
    contentForm.title      = l?.title ?? '';
    contentForm.content    = l?.content ?? '';
    contentForm.embed_html = l?.embed_html ?? '';
    contentForm.video_url  = l?.video_url ?? '';
    contentForm.cta_label  = l?.cta_label ?? '';
    contentForm.cta_url    = l?.cta_url ?? '';
    editingLesson.value    = true;
}

function saveContent() {
    const lesson = selectedLesson.value;
    contentForm
        .transform((data) => ({ ...data, _method: 'PATCH' }))
        .post(
            `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules/${lesson.module_id}/lessons/${lesson.id}`,
            { onSuccess: () => { editingLesson.value = false; } }
        );
}

// ─── Video upload (Pro plan) ─────────────────────────────────────────────────
const videoUploading = ref(false);
const videoUploadProgress = ref(0);
const videoUploadError = ref('');

async function handleVideoUpload(e) {
    const file = e.target.files[0];
    if (!file) return;

    videoUploading.value = true;
    videoUploadProgress.value = 0;
    videoUploadError.value = '';

    const formData = new FormData();
    formData.append('video', file);

    try {
        const { data } = await axios.post(lessonVideoUploadUrl, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (e) => {
                videoUploadProgress.value = Math.round((e.loaded / e.total) * 100);
            },
        });

        // Update the lesson with the uploaded video path
        const lesson = selectedLesson.value;
        await axios.post(
            `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules/${lesson.module_id}/lessons/${lesson.id}`,
            { video_path: data.url, video_url: '', _method: 'PATCH' }
        );

        router.reload({ only: ['course'] });
    } catch (err) {
        videoUploadError.value = err.response?.data?.error || err.response?.data?.message || 'Upload failed. Please try again.';
    } finally {
        videoUploading.value = false;
        e.target.value = '';
    }
}

// ─── Embed URL ────────────────────────────────────────────────────────────────
function embedUrl(url) {
    if (!url) return '';
    // YouTube short link
    url = url.replace('youtu.be/', 'www.youtube.com/embed/');
    // YouTube full link
    url = url.replace('youtube.com/watch?v=', 'youtube.com/embed/');
    // Vimeo: https://vimeo.com/123456789 → https://player.vimeo.com/video/123456789
    url = url.replace(/vimeo\.com\/(\d+)/, 'player.vimeo.com/video/$1');
    // Google Drive: /file/d/{id}/view → /file/d/{id}/preview
    url = url.replace(/drive\.google\.com\/file\/d\/([^/]+)\/view/, 'drive.google.com/file/d/$1/preview');
    return url.split('&')[0];
}

// ─── Quiz ─────────────────────────────────────────────────────────────────────
const quizAnswers  = ref({});
const quizResult   = ref(null);
const quizForm     = useForm({});

function bestAttempt(quizId) {
    return quizId ? props.quizAttempts?.[quizId] : null;
}

const currentAttempt = computed(() => {
    const quiz = selectedLesson.value?.quiz;
    return quiz ? bestAttempt(quiz.id) : null;
});

const allAnswered = computed(() => {
    const quiz = selectedLesson.value?.quiz;
    if (!quiz) return false;
    return quiz.questions.every((q) => quizAnswers.value[q.id] != null);
});

function resetQuizAnswers() {
    quizAnswers.value = {};
}

function submitQuiz() {
    const lesson = selectedLesson.value;
    const quiz   = lesson?.quiz;
    if (!quiz) return;

    quizForm
        .transform(() => ({ answers: quizAnswers.value }))
        .post(
            `/communities/${props.community.slug}/classroom/courses/${props.course.id}/lessons/${lesson.id}/quiz/${quiz.id}/submit`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    const flash = page.props.flash?.quiz_result;
                    if (flash) quizResult.value = flash;
                },
            }
        );
}

function retakeQuiz() {
    quizResult.value = null;
    resetQuizAnswers();
}

function deleteQuiz() {
    const lesson = selectedLesson.value;
    const quiz   = lesson?.quiz;
    if (!quiz || !confirm('Delete this quiz?')) return;
    router.delete(
        `/communities/${props.community.slug}/classroom/courses/${props.course.id}/lessons/${lesson.id}/quiz/${quiz.id}`,
        { preserveScroll: true }
    );
}

// ─── Quiz Builder (owner) ─────────────────────────────────────────────────────
const showQuizBuilder    = ref(false);
const quizBuilderForm    = useForm({
    title:      '',
    pass_score: 70,
    questions:  [],
});

function addQuestion() {
    quizBuilderForm.questions.push({
        question: '',
        type:     'multiple_choice',
        options:  [
            { label: '', is_correct: true },
            { label: '', is_correct: false },
        ],
    });
}

function setCorrect(qi, oi) {
    quizBuilderForm.questions[qi].options.forEach((o, i) => {
        o.is_correct = i === oi;
    });
}

function resetQuizBuilder() {
    quizBuilderForm.reset();
    quizBuilderForm.questions = [];
}

function saveQuiz() {
    const lesson = selectedLesson.value;
    quizBuilderForm.post(
        `/communities/${props.community.slug}/classroom/courses/${props.course.id}/lessons/${lesson.id}/quiz`,
        {
            onSuccess: () => {
                showQuizBuilder.value = false;
                resetQuizBuilder();
            },
        }
    );
}

// ─── Lesson comments ──────────────────────────────────────────────────────────
const commentForm = useForm({ content: '' });

const currentComments = computed(() => {
    if (!selectedLesson.value) return [];
    return props.lessonComments?.[selectedLesson.value.id] ?? [];
});

function postComment() {
    const lesson = selectedLesson.value;
    commentForm.post(
        `/communities/${props.community.slug}/classroom/courses/${props.course.id}/lessons/${lesson.id}/comments`,
        {
            preserveScroll: true,
            onSuccess: () => commentForm.reset(),
        }
    );
}

function deleteComment(commentId) {
    router.delete(`/lesson-comments/${commentId}`, { preserveScroll: true });
}

// ─── Course enrollment (paid_once) ────────────────────────────────────────────
const enrollForm = useForm({});

function enrollInCourse() {
    enrollForm.post(`/communities/${props.community.slug}/classroom/courses/${props.course.id}/enroll`);
}

// (Certification logic moved to Communities/Certifications/Index.vue)


</script>
