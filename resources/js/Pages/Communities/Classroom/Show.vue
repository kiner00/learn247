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
        <CourseProgressBar
            v-if="hasAccess"
            :course-title="course.title"
            :completed-count="doneIds.size"
            :total-lessons="totalLessons"
            :progress="currentProgress"
        />

        <!-- Sales landing page (locked course) -->
        <CourseSalesPage
            v-if="!hasAccess"
            :community="community"
            :course="course"
            :enrollment="enrollment"
            :auth-user-id="authUserId"
            :total-lessons="totalLessons"
            :enroll-processing="enrollForm.processing"
            @enroll="enrollInCourse"
        />

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
            <CourseSidebar
                ref="sidebarRef"
                :course="course"
                :is-owner="isOwner"
                :selected-lesson-id="selectedLesson?.id"
                :is-completed="isCompleted"
                :completed-in-module="completedInModule"
                :best-attempt="bestAttempt"
                :lesson-form="lessonForm"
                :module-form="moduleForm"
                :lesson-image-upload-url="lessonImageUploadUrl"
                @select-lesson="selectLesson"
                @delete-lesson="deleteLesson"
                @delete-module="deleteModule"
                @create-lesson="createLesson"
                @create-module="createModule"
                @lesson-reorder="onLessonDragEnd"
                @save-module-title="saveModuleTitle"
                @toggle-module-free="toggleModuleFree"
            />

            <!-- Main content area -->
            <div class="lg:col-span-2 space-y-4">
                <LessonContentPanel
                    ref="contentPanelRef"
                    :lesson="selectedLesson"
                    :course="course"
                    :has-access="hasAccess"
                    :is-owner="isOwner"
                    :is-completed="isCompleted"
                    :can-upload-video="canUploadVideo"
                    :content-form="contentForm"
                    :lesson-image-upload-url="lessonImageUploadUrl"
                    :complete-processing="completeForm.processing"
                    :video-stream-url="videoStreamUrl"
                    :video-stream-type="videoStreamType"
                    :video-stream-loading="videoStreamLoading"
                    :is-transcoding="isTranscoding"
                    :transcode-percent="transcodePercent"
                    :video-uploading="videoUploading"
                    :video-upload-progress="videoUploadProgress"
                    :video-upload-error="videoUploadError"
                    :video-upload-success="videoUploadSuccess"
                    :video-play-count="selectedLesson?.video_play_count ?? 0"
                    :video-watch-seconds="selectedLesson?.video_watch_seconds ?? 0"
                    @mark-complete="markComplete"
                    @save-content="saveContent"
                    @delete-video="deleteVideo"
                    @video-upload="handleVideoUpload"
                    @video-play="onLessonVideoPlay"
                    @video-pause="onLessonVideoPause"
                />

                <!-- Quiz section -->
                <LessonQuiz
                    v-if="selectedLesson && hasAccess"
                    ref="quizRef"
                    :lesson="selectedLesson"
                    :is-owner="isOwner"
                    :quiz-answers="quizAnswers"
                    :quiz-result="quizResult"
                    :quiz-form-processing="quizForm.processing"
                    :current-attempt="currentAttempt"
                    :quiz-builder-form="quizBuilderForm"
                    @submit-quiz="submitQuiz"
                    @retake-quiz="retakeQuiz"
                    @delete-quiz="deleteQuiz"
                    @save-quiz="saveQuiz"
                />

                <!-- Lesson comments -->
                <LessonComments
                    v-if="selectedLesson && hasAccess"
                    :comments="currentComments"
                    :comment-form="commentForm"
                    :auth-user-id="authUserId"
                    :is-owner="isOwner"
                    @post-comment="postComment"
                    @delete-comment="deleteComment"
                />

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
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import Hls from 'hls.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import CourseProgressBar from '@/Components/Classroom/CourseProgressBar.vue';
import CourseSalesPage from '@/Components/Classroom/CourseSalesPage.vue';
import CourseSidebar from '@/Components/Classroom/CourseSidebar.vue';
import LessonContentPanel from '@/Components/Classroom/LessonContentPanel.vue';
import LessonQuiz from '@/Components/Classroom/LessonQuiz.vue';
import LessonComments from '@/Components/Classroom/LessonComments.vue';

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

// ─── Refs for child components ───────────────────────────────────────────────
const sidebarRef      = ref(null);
const contentPanelRef = ref(null);
const quizRef         = ref(null);

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
const selectedLesson = ref(props.course.modules[0]?.lessons[0] ?? null);

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
    onLessonVideoPause(); // Track watch time for previous lesson
    selectedLesson.value = lesson;
    if (contentPanelRef.value) contentPanelRef.value.editingLesson = false;
    quizResult.value = null;
    resetQuizAnswers();
    commentForm.reset();
    fetchVideoStreamUrl(lesson);
}

// ─── Secure video streaming via signed URLs (HLS + raw fallback) ─────────────
const videoStreamUrl     = ref(null);
const videoStreamType    = ref(null); // 'hls' | 'raw'
const videoStreamLoading = ref(false);
const transcodePercent   = ref(0);
const transcodeStatus    = ref(null);
let hlsInstance          = null;
let transcodePoller      = null;

const isTranscoding = computed(() =>
    selectedLesson.value?.video_path &&
    (transcodeStatus.value === 'pending' || transcodeStatus.value === 'processing')
);

function destroyHls() {
    if (hlsInstance) {
        hlsInstance.destroy();
        hlsInstance = null;
    }
}

function stopTranscodePolling() {
    if (transcodePoller) {
        clearInterval(transcodePoller);
        transcodePoller = null;
    }
}

async function fetchVideoStreamUrl(lesson) {
    videoStreamUrl.value  = null;
    videoStreamType.value = null;
    transcodeStatus.value = null;
    transcodePercent.value = 0;
    destroyHls();
    stopTranscodePolling();

    if (!lesson?.video_path) return;

    videoStreamLoading.value = true;
    try {
        const { data } = await axios.get(
            `/communities/${props.community.slug}/classroom/courses/${props.course.id}/lessons/${lesson.id}/stream`
        );

        transcodeStatus.value = data.transcode_status;

        // If transcoding is still in progress, poll for status
        if (data.transcode_status === 'pending' || data.transcode_status === 'processing') {
            videoStreamLoading.value = false;
            startTranscodePolling(lesson);
            return;
        }

        videoStreamUrl.value  = data.url;
        videoStreamType.value = data.type || 'raw';

        // Attach HLS.js or set src after DOM update
        await nextTick();
        attachVideoSource();
    } catch {
        videoStreamUrl.value = null;
    } finally {
        videoStreamLoading.value = false;
    }
}

function attachVideoSource() {
    const videoEl = contentPanelRef.value?.videoPlayerRef;
    if (!videoEl || !videoStreamUrl.value) return;

    if (videoStreamType.value === 'hls' && Hls.isSupported()) {
        destroyHls();
        hlsInstance = new Hls();
        hlsInstance.loadSource(videoStreamUrl.value);
        hlsInstance.attachMedia(videoEl);
    } else if (videoStreamType.value === 'hls' && videoEl.canPlayType('application/vnd.apple.mpegurl')) {
        // Safari has native HLS support
        videoEl.src = videoStreamUrl.value;
    } else {
        // Raw video fallback
        videoEl.src = videoStreamUrl.value;
    }
}

function startTranscodePolling(lesson) {
    stopTranscodePolling();

    const poll = async () => {
        try {
            const { data } = await axios.get(
                `/communities/${props.community.slug}/classroom/courses/${props.course.id}/lessons/${lesson.id}/transcode-status`
            );
            transcodeStatus.value  = data.status;
            transcodePercent.value = data.percent;

            if (data.status === 'completed') {
                stopTranscodePolling();
                // Reload to get the HLS stream URL
                fetchVideoStreamUrl(lesson);
            } else if (data.status === 'failed') {
                stopTranscodePolling();
                // Fallback: try loading raw video
                fetchVideoStreamUrl(lesson);
            }
        } catch {
            // Silently retry on next interval
        }
    };

    poll(); // Immediate first check
    transcodePoller = setInterval(poll, 3000);
}

// ─── Lesson video analytics ──────────────────────────────────────────────────
let lessonVideoPlayStart = null;

function onLessonVideoPlay() {
    lessonVideoPlayStart = Date.now();
}

function onLessonVideoPause() {
    if (!lessonVideoPlayStart || !selectedLesson.value) return;
    const seconds = Math.round((Date.now() - lessonVideoPlayStart) / 1000);
    lessonVideoPlayStart = null;
    if (seconds >= 1) {
        axios.post(
            `/communities/${props.community.slug}/classroom/courses/${props.course.id}/lessons/${selectedLesson.value.id}/video-play`,
            { seconds }
        ).catch(() => {});
    }
}

// Clean up on unmount
onBeforeUnmount(() => {
    onLessonVideoPause();
    destroyHls();
    stopTranscodePolling();
});

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
const moduleEditForm = useForm({ title: '' });

function saveModuleTitle(mod, title) {
    moduleEditForm.title = title;
    moduleEditForm
        .transform((data) => ({ ...data, _method: 'PATCH' }))
        .post(
            `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules/${mod.id}`,
            { preserveScroll: true }
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
const moduleForm = useForm({ title: '' });

function createModule() {
    moduleForm.post(
        `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules`,
        {
            onSuccess: () => {
                moduleForm.reset();
                if (sidebarRef.value) sidebarRef.value.showModuleForm = false;
            },
        }
    );
}

// ─── Add lesson ────────────────────────────────────────────────────────────────
const lessonForm = useForm({ title: '', content: '', video_url: '', cta_label: '', cta_url: '' });

function createLesson(mod) {
    lessonForm.post(
        `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules/${mod.id}/lessons`,
        {
            onSuccess: () => {
                lessonForm.reset();
                if (sidebarRef.value) sidebarRef.value.addingLessonToModule = null;
            },
        }
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
const contentForm = useForm({ title: '', content: '', embed_html: '', video_url: '', cta_label: '', cta_url: '' });

function saveContent() {
    const lesson = selectedLesson.value;
    contentForm
        .transform((data) => ({ ...data, _method: 'PATCH' }))
        .post(
            `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules/${lesson.module_id}/lessons/${lesson.id}`,
            {
                onSuccess: () => {
                    if (contentPanelRef.value) contentPanelRef.value.editingLesson = false;
                },
            }
        );
}

// ─── Video upload (Pro plan) ─────────────────────────────────────────────────
const videoUploading = ref(false);
const videoUploadProgress = ref(0);
const videoUploadError = ref('');
const videoUploadSuccess = ref(false);

const CHUNK_SIZE = 10 * 1024 * 1024; // 10MB per chunk
const MAX_CONCURRENT_CHUNKS = 3;

async function handleVideoUpload(e) {
    const file = e.target.files[0];
    if (!file) return;

    videoUploading.value = true;
    videoUploadProgress.value = 0;
    videoUploadError.value = '';
    videoUploadSuccess.value = false;

    const multipartBase = `/communities/${props.community.slug}/classroom/multipart`;
    let uploadId = null;
    let key = null;

    try {
        // Step 1: Initiate multipart upload
        const { data: initData } = await axios.post(`${multipartBase}/initiate`, {
            filename: file.name,
            content_type: file.type,
            size: file.size,
        });
        uploadId = initData.upload_id;
        key = initData.key;

        // Step 2: Upload chunks with presigned URLs
        const { default: rawAxios } = await import('axios');
        const s3Client = rawAxios.create({ withCredentials: false });

        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        const completedParts = [];
        const chunkProgress = new Array(totalChunks).fill(0);

        function updateProgress() {
            const loaded = chunkProgress.reduce((sum, v) => sum + v, 0);
            videoUploadProgress.value = Math.round((loaded / file.size) * 100);
        }

        async function uploadChunk(partNumber) {
            const start = (partNumber - 1) * CHUNK_SIZE;
            const end = Math.min(start + CHUNK_SIZE, file.size);
            const chunk = file.slice(start, end);

            // Get presigned URL for this part
            const { data: partData } = await axios.post(`${multipartBase}/part-url`, {
                key, upload_id: uploadId, part_number: partNumber,
            });

            // Upload chunk to S3
            const response = await s3Client.put(partData.url, chunk, {
                headers: { 'Content-Type': file.type },
                onUploadProgress: (e) => {
                    chunkProgress[partNumber - 1] = e.loaded;
                    updateProgress();
                },
            });

            const etag = response.headers['etag'] || response.headers['ETag'];
            completedParts.push({ PartNumber: partNumber, ETag: etag });
        }

        // Upload chunks with concurrency limit
        for (let i = 0; i < totalChunks; i += MAX_CONCURRENT_CHUNKS) {
            const batch = [];
            for (let j = i; j < Math.min(i + MAX_CONCURRENT_CHUNKS, totalChunks); j++) {
                batch.push(uploadChunk(j + 1));
            }
            await Promise.all(batch);
        }

        // Sort parts by part number (required by S3)
        completedParts.sort((a, b) => a.PartNumber - b.PartNumber);

        // Step 3: Complete multipart upload
        await axios.post(`${multipartBase}/complete`, {
            key, upload_id: uploadId, parts: completedParts,
        });

        // Step 4: Save the S3 key on the lesson
        const lesson = selectedLesson.value;
        await axios.post(
            `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules/${lesson.module_id}/lessons/${lesson.id}`,
            { video_path: key, video_url: '', _method: 'PATCH' }
        );

        lesson.video_path = key;
        lesson.video_url  = '';
        await fetchVideoStreamUrl(lesson);

        videoUploadSuccess.value = true;
        setTimeout(() => (videoUploadSuccess.value = false), 5000);

        router.reload({ only: ['course'] });
    } catch (err) {
        // Abort the multipart upload on failure to clean up S3
        if (uploadId && key) {
            try {
                await axios.post(`${multipartBase}/abort`, { key, upload_id: uploadId });
            } catch {}
        }

        if (err.response?.data?.error) {
            videoUploadError.value = err.response.data.error;
        } else if (err.response?.data?.message) {
            videoUploadError.value = err.response.data.message;
        } else if (typeof err.response?.data === 'string' && err.response.data.includes('<Message>')) {
            const match = err.response.data.match(/<Message>([^<]+)<\/Message>/);
            videoUploadError.value = match ? `S3: ${match[1]}` : 'Upload to storage failed. Please try again.';
        } else if (err.message) {
            videoUploadError.value = err.message;
        } else {
            videoUploadError.value = 'Upload failed. Please try again.';
        }
        console.error('Video upload error:', err);
    } finally {
        videoUploading.value = false;
        e.target.value = '';
    }
}

// ─── Delete video ────────────────────────────────────────────────────────────
async function deleteVideo() {
    if (!confirm('Remove this video? This cannot be undone.')) return;
    const lesson = selectedLesson.value;
    try {
        await axios.patch(
            `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules/${lesson.module_id}/lessons/${lesson.id}`,
            { video_path: '', video_url: '' }
        );
        lesson.video_path = null;
        lesson.video_url  = null;
        videoStreamUrl.value  = null;
        videoStreamType.value = null;
        destroyHls();
        stopTranscodePolling();
        router.reload({ only: ['course'] });
    } catch (err) {
        console.error('Failed to delete video:', err);
    }
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
const quizBuilderForm = useForm({
    title:      '',
    pass_score: 70,
    questions:  [],
});

function saveQuiz() {
    const lesson = selectedLesson.value;
    quizBuilderForm.post(
        `/communities/${props.community.slug}/classroom/courses/${props.course.id}/lessons/${lesson.id}/quiz`,
        {
            onSuccess: () => {
                if (quizRef.value) {
                    quizRef.value.showQuizBuilder = false;
                    quizRef.value.resetQuizBuilder();
                }
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
</script>
