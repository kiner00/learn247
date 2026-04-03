<template>
    <!-- Locked overlay (covers main content) -->
    <div v-if="!hasAccess" class="relative bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
        <div class="blur-sm opacity-40 pointer-events-none select-none px-6 py-5 space-y-4">
            <div class="h-5 bg-gray-200 rounded w-3/4" />
            <div class="h-48 bg-gray-100 rounded-xl" />
            <div class="h-4 bg-gray-200 rounded w-full" />
            <div class="h-4 bg-gray-200 rounded w-5/6" />
            <div class="h-4 bg-gray-200 rounded w-2/3" />
        </div>
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

    <div v-if="lesson && hasAccess" class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-lg font-black text-gray-900">{{ lesson.title }}</h2>
            <span v-if="isCompleted(lesson.id)"
                class="text-xs font-semibold text-green-600 bg-green-50 px-2.5 py-1 rounded-full">
                ✓ Completed
            </span>
        </div>

        <!-- Uploaded video (served via signed URL or HLS) -->
        <div v-if="lesson.video_path" class="bg-black">
            <div v-if="videoStreamLoading" class="flex items-center justify-center h-64 text-white text-sm">
                Loading video...
            </div>

            <div v-else-if="isTranscoding" class="flex flex-col items-center justify-center h-64 text-white text-sm gap-3">
                <svg class="w-8 h-8 animate-spin text-indigo-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <div class="text-center">
                    <p class="font-medium">Transcoding video...</p>
                    <p class="text-xs text-gray-400 mt-1">{{ transcodePercent }}% complete</p>
                    <div class="w-48 bg-gray-700 rounded-full h-1.5 mt-2">
                        <div class="bg-indigo-500 h-1.5 rounded-full transition-all duration-300" :style="{ width: transcodePercent + '%' }" />
                    </div>
                </div>
            </div>

            <video
                v-else-if="videoStreamUrl"
                ref="videoPlayerRef"
                :src="videoStreamType !== 'hls' ? videoStreamUrl : undefined"
                controls
                class="w-full max-h-120 object-contain"
                controlsList="nodownload nofullscreen"
                disablePictureInPicture
                oncontextmenu="return false;"
                @play="$emit('video-play')"
                @pause="$emit('video-pause')"
                @ended="$emit('video-pause')"
            />
            <!-- Video analytics (owner only) -->
            <div v-if="isOwner && videoPlayCount > 0" class="px-4 py-2 bg-gray-900 flex items-center gap-4">
                <span class="text-[11px] text-gray-400 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ videoPlayCount }} plays
                </span>
                <span class="text-[11px] text-gray-400 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ formatTime(videoWatchSeconds) }}
                </span>
                <span class="text-[11px] text-gray-400">
                    ~{{ formatTime(Math.round(videoWatchSeconds / videoPlayCount)) }} avg
                </span>
            </div>
        </div>

        <!-- YouTube / external URL -->
        <div v-else-if="lesson.video_url" class="aspect-video bg-gray-900">
            <iframe
                :src="embedUrl(lesson.video_url)"
                class="w-full h-full"
                allowfullscreen
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            />
        </div>

        <div class="px-6 py-5">
            <SafeHtmlRenderer
                v-if="lesson.embed_html && !editingLesson"
                :html="lesson.embed_html"
            />
            <SafeHtmlRenderer
                v-if="lesson.content && !editingLesson"
                :html="lesson.content"
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
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="contentForm.errors.video_url ? 'border-red-400' : 'border-gray-200'"
                    />
                    <p v-if="contentForm.errors.video_url" class="text-xs text-red-500 mt-1">{{ contentForm.errors.video_url }}</p>
                </div>
                <div v-if="canUploadVideo">
                    <p class="text-xs text-gray-500 mb-1.5 font-medium">
                        Upload Video
                        <span class="ml-1 px-1.5 py-0.5 bg-indigo-100 text-indigo-700 text-[10px] font-bold rounded-full uppercase">Pro</span>
                    </p>
                    <div v-if="lesson.video_path && !videoUploading" class="flex items-center gap-2 mb-2 px-3 py-2 bg-green-50 border border-green-200 rounded-lg">
                        <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <span class="text-xs text-green-700 flex-1">Video uploaded</span>
                        <button
                            type="button"
                            @click="$emit('delete-video')"
                            class="text-xs text-red-500 hover:text-red-700 font-medium"
                        >
                            Remove
                        </button>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="flex-1 flex items-center justify-center gap-2 px-3 py-2.5 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/50 transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <span class="text-xs text-gray-500">
                                {{ videoUploading ? `Uploading... ${videoUploadProgress}%` : lesson.video_path ? 'Replace video (MP4, WebM, MOV — max 500MB)' : 'Choose video file (MP4, WebM, MOV — max 500MB)' }}
                            </span>
                            <input
                                type="file"
                                accept="video/mp4,video/webm,video/quicktime"
                                class="hidden"
                                :disabled="videoUploading"
                                @change="$emit('video-upload', $event)"
                            />
                        </label>
                    </div>
                    <div v-if="videoUploading" class="mt-2">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-indigo-600 h-2 rounded-full transition-all duration-300" :style="{ width: videoUploadProgress + '%' }" />
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ videoUploadProgress }}% uploaded</p>
                    </div>
                    <p v-if="videoUploadError" class="text-xs text-red-500 mt-1">{{ videoUploadError }}</p>
                    <p v-if="videoUploadSuccess" class="text-xs text-green-600 mt-1">Video uploaded successfully!</p>
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
                            class="flex-1 px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :class="contentForm.errors.cta_label ? 'border-red-400' : 'border-gray-200'"
                        />
                        <input
                            v-model="contentForm.cta_url"
                            type="url"
                            placeholder="https://..."
                            class="flex-1 px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :class="contentForm.errors.cta_url ? 'border-red-400' : 'border-gray-200'"
                        />
                    </div>
                    <p v-if="contentForm.errors.cta_label || contentForm.errors.cta_url" class="text-xs text-red-500 mt-1">
                        {{ contentForm.errors.cta_label || contentForm.errors.cta_url }}
                    </p>
                </div>
                <div class="flex gap-2 justify-end">
                    <button type="button" @click="editingLesson = false" class="px-4 py-2 text-sm text-gray-500">Cancel</button>
                    <button type="button" @click="$emit('save-content')" :disabled="contentForm.processing"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                        {{ contentForm.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </div>

            <!-- CTA Button -->
            <div v-if="lesson.cta_url && lesson.cta_label && !editingLesson" class="mb-5">
                <a
                    :href="lesson.cta_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm"
                >
                    {{ lesson.cta_label }}
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </a>
            </div>

            <!-- Action bar -->
            <div class="flex gap-3 pt-4 border-t border-gray-100">
                <button
                    @click="$emit('mark-complete')"
                    :disabled="isCompleted(lesson.id) || completeProcessing"
                    class="px-5 py-2.5 text-sm font-semibold rounded-xl transition-colors"
                    :class="isCompleted(lesson.id)
                        ? 'bg-green-100 text-green-700 cursor-default'
                        : 'bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50'"
                >
                    {{ isCompleted(lesson.id) ? '✓ Completed' : 'Mark as Complete' }}
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
</template>

<script setup>
import { ref } from 'vue';
import SafeHtmlRenderer from '@/Components/SafeHtmlRenderer.vue';
import LessonEditor from '@/Components/LessonEditor.vue';

const props = defineProps({
    lesson:              { type: Object, default: null },
    course:              { type: Object, required: true },
    hasAccess:           { type: Boolean, required: true },
    isOwner:             { type: Boolean, default: false },
    isCompleted:         { type: Function, required: true },
    canUploadVideo:      { type: Boolean, default: false },
    contentForm:         { type: Object, required: true },
    lessonImageUploadUrl:{ type: String, required: true },
    completeProcessing:  { type: Boolean, default: false },
    // Video streaming state
    videoStreamUrl:      { type: String, default: null },
    videoStreamType:     { type: String, default: null },
    videoStreamLoading:  { type: Boolean, default: false },
    isTranscoding:       { type: Boolean, default: false },
    transcodePercent:    { type: Number, default: 0 },
    // Video upload state
    videoUploading:      { type: Boolean, default: false },
    videoUploadProgress: { type: Number, default: 0 },
    videoUploadError:    { type: String, default: '' },
    videoUploadSuccess:  { type: Boolean, default: false },
    // Video analytics (owner only)
    videoPlayCount:      { type: Number, default: 0 },
    videoWatchSeconds:   { type: Number, default: 0 },
});

defineEmits(['mark-complete', 'save-content', 'delete-video', 'video-upload', 'video-play', 'video-pause']);

const editingLesson = ref(false);
const videoPlayerRef = ref(null);

function startEdit() {
    const l = props.lesson;
    props.contentForm.title      = l?.title ?? '';
    props.contentForm.content    = l?.content ?? '';
    props.contentForm.embed_html = l?.embed_html ?? '';
    props.contentForm.video_url  = l?.video_url ?? '';
    props.contentForm.cta_label  = l?.cta_label ?? '';
    props.contentForm.cta_url    = l?.cta_url ?? '';
    editingLesson.value          = true;
}

function embedUrl(url) {
    if (!url) return '';
    url = url.replace('youtu.be/', 'www.youtube.com/embed/');
    url = url.replace('youtube.com/watch?v=', 'youtube.com/embed/');
    url = url.replace(/vimeo\.com\/(\d+)/, 'player.vimeo.com/video/$1');
    url = url.replace(/drive\.google\.com\/file\/d\/([^/]+)\/view/, 'drive.google.com/file/d/$1/preview');
    return url.split('&')[0];
}

function formatTime(seconds) {
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return `${h}h ${m}m`;
}

defineExpose({ editingLesson, videoPlayerRef });
</script>
