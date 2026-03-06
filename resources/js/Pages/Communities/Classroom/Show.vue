<template>
    <AppLayout :title="`${course.title} · ${community.name}`" :community="community">
        <CommunityTabs :community="community" active-tab="classroom" />

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
            <Link :href="`/communities/${community.slug}/classroom`" class="hover:text-indigo-600 transition-colors">
                Classroom
            </Link>
            <span>/</span>
            <span class="text-gray-800 font-medium">{{ course.title }}</span>
        </div>

        <!-- Progress bar -->
        <div class="bg-white border border-gray-200 rounded-2xl p-4 mb-6 shadow-sm flex items-center gap-4">
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Sidebar: module + lesson tree -->
            <div class="space-y-3">
                <div
                    v-for="mod in course.modules"
                    :key="mod.id"
                    class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm"
                >
                    <button
                        @click="toggleModule(mod.id)"
                        class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors"
                    >
                        <span class="text-sm font-semibold text-gray-800">{{ mod.title }}</span>
                        <span class="text-xs text-gray-400">
                            {{ completedInModule(mod) }}/{{ mod.lessons.length }}
                            <span class="ml-1">{{ openModules.has(mod.id) ? '▲' : '▼' }}</span>
                        </span>
                    </button>

                    <div v-if="openModules.has(mod.id)" class="border-t border-gray-100">
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
                        </button>

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

                                <input
                                    v-model="lessonForm.video_url"
                                    type="url"
                                    placeholder="https://youtube.com/watch?v=..."
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
            <div class="lg:col-span-2">
                <div v-if="selectedLesson" class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-lg font-black text-gray-900">{{ selectedLesson.title }}</h2>
                        <span v-if="isCompleted(selectedLesson.id)"
                            class="text-xs font-semibold text-green-600 bg-green-50 px-2.5 py-1 rounded-full">
                            ✓ Completed
                        </span>
                    </div>

                    <!-- Uploaded video → HTML5 player -->
                    <div v-if="selectedLesson.video_path" class="bg-black">
                        <video
                            :src="`/storage/${selectedLesson.video_path}`"
                            controls
                            class="w-full max-h-120 object-contain"
                            controlsList="nodownload"
                        />
                    </div>

                    <!-- YouTube / external URL → iframe -->
                    <div v-else-if="selectedLesson.video_url" class="aspect-video bg-gray-900">
                        <iframe
                            :src="embedUrl(selectedLesson.video_url)"
                            class="w-full h-full"
                            allowfullscreen
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        />
                    </div>

                    <div class="px-6 py-5">
                        <p v-if="selectedLesson.content && !editingLesson"
                            class="text-sm text-gray-700 whitespace-pre-line leading-relaxed mb-6">
                            {{ selectedLesson.content }}
                        </p>

                        <!-- Edit form (owner only) -->
                        <div v-if="isOwner && editingLesson" class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200 space-y-3">
                            <p class="text-xs font-semibold text-gray-700">Edit Lesson</p>

                            <textarea
                                v-model="contentForm.content"
                                rows="4"
                                placeholder="Lesson description / notes..."
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                            />

                            <div>
                                <p class="text-xs text-gray-500 mb-1.5 font-medium">Video URL</p>
                                <input
                                    v-model="contentForm.video_url"
                                    type="url"
                                    placeholder="https://youtube.com/watch?v=..."
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>

                            <div class="flex gap-2 justify-end">
                                <button type="button" @click="editingLesson = false" class="px-4 py-2 text-sm text-gray-500">Cancel</button>
                                <button
                                    type="button"
                                    @click="saveContent"
                                    :disabled="contentForm.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {{ contentForm.processing ? 'Saving...' : 'Save Changes' }}
                                </button>
                            </div>
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

                <!-- No lesson selected -->
                <div v-else class="bg-white border border-gray-200 rounded-2xl p-14 text-center shadow-sm">
                    <span class="text-4xl block mb-3">🎓</span>
                    <p class="text-sm font-medium text-gray-700 mb-1">{{ course.description || course.title }}</p>
                    <p class="text-xs text-gray-400">Select a lesson from the sidebar to get started</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';

const props = defineProps({
    community:    Object,
    course:       Object,
    completedIds: Array,
    progress:     Number,
});

const page    = usePage();
const isOwner = props.community.owner_id === page.props.auth?.user?.id;

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

const selectedLesson = ref(props.course.modules[0]?.lessons[0] ?? null);

function selectLesson(lesson) {
    selectedLesson.value = lesson;
    editingLesson.value  = false;
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
const lessonForm = useForm({ title: '', content: '', video_url: '' });

function createLesson(mod) {
    lessonForm.post(
        `/communities/${props.community.slug}/classroom/courses/${props.course.id}/modules/${mod.id}/lessons`,
        { onSuccess: () => { lessonForm.reset(); addingLessonToModule.value = null; } }
    );
}

// ─── Edit lesson ──────────────────────────────────────────────────────────────
const editingLesson = ref(false);
const contentForm   = useForm({ content: '', video_url: '' });

function startEdit() {
    const l = selectedLesson.value;
    contentForm.content   = l?.content ?? '';
    contentForm.video_url = l?.video_url ?? '';
    editingLesson.value   = true;
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

// ─── Embed URL ────────────────────────────────────────────────────────────────
function embedUrl(url) {
    if (!url) return '';
    url = url.replace('youtu.be/', 'www.youtube.com/embed/');
    url = url.replace('youtube.com/watch?v=', 'youtube.com/embed/');
    return url.split('&')[0];
}
</script>
