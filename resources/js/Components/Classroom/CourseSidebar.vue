<template>
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
                        @keydown.enter.prevent="handleSaveModuleTitle(mod)"
                        @keydown.escape="editingModuleId = null"
                        class="flex-1 px-2 py-1 border border-indigo-400 rounded-lg text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        autofocus
                    />
                    <button @click="handleSaveModuleTitle(mod)" class="text-indigo-600 hover:text-indigo-800 text-xs font-bold px-1.5">✓</button>
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
                        @click.stop="$emit('toggle-module-free', mod)"
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
                        @click.stop="$emit('delete-module', mod)"
                        class="flex items-center px-1.5 py-1 rounded-lg text-xs font-medium text-red-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                        title="Delete module"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                    <span class="text-xs text-gray-400" @click="toggleModule(mod.id)">
                        {{ completedInModule(mod) }}/{{ mod.lessons.length }}
                        <span class="ml-1">{{ localOpenModules.has(mod.id) ? '▲' : '▼' }}</span>
                    </span>
                </div>
            </div>

            <div v-if="localOpenModules.has(mod.id)" class="border-t border-gray-100">
                <draggable
                    v-if="isOwner"
                    :list="mod.lessons"
                    item-key="id"
                    handle=".drag-handle"
                    ghost-class="opacity-30"
                    @end="$emit('lesson-reorder', mod)"
                >
                    <template #item="{ element: lesson }">
                        <div
                            class="w-full flex items-center gap-2 px-4 py-2.5 text-left hover:bg-indigo-50 transition-colors border-b border-gray-50 last:border-0 cursor-pointer"
                            :class="selectedLessonId === lesson.id ? 'bg-amber-50' : ''"
                            @click="$emit('select-lesson', lesson)"
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
                                :class="selectedLessonId === lesson.id ? 'font-semibold text-indigo-700' : ''"
                            >
                                {{ lesson.title }}
                            </span>
                            <span v-if="lesson.quiz" class="shrink-0 text-xs px-1.5 py-0.5 rounded-full"
                                :class="bestAttempt(lesson.quiz?.id)?.passed ? 'bg-green-100 text-green-700' : 'bg-indigo-50 text-indigo-500'">
                                {{ bestAttempt(lesson.quiz?.id)?.passed ? '✓ Quiz' : '📝' }}
                            </span>
                            <button @click.stop="$emit('delete-lesson', mod, lesson)"
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
                        @click="$emit('select-lesson', lesson)"
                        class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-indigo-50 transition-colors border-b border-gray-50 last:border-0"
                        :class="selectedLessonId === lesson.id ? 'bg-amber-50' : ''"
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
                            :class="selectedLessonId === lesson.id ? 'font-semibold text-indigo-700' : ''"
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
                    <form v-if="addingLessonToModule === mod.id" @submit.prevent="$emit('create-lesson', mod)" class="space-y-1.5">
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
            <form v-if="showModuleForm" @submit.prevent="$emit('create-module')" class="space-y-2">
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
</template>

<script setup>
import { ref } from 'vue';
import draggable from 'vuedraggable';
import LessonEditor from '@/Components/LessonEditor.vue';

const props = defineProps({
    course:              { type: Object, required: true },
    isOwner:             { type: Boolean, default: false },
    selectedLessonId:    { type: [Number, String], default: null },
    isCompleted:         { type: Function, required: true },
    completedInModule:   { type: Function, required: true },
    bestAttempt:         { type: Function, required: true },
    lessonForm:          { type: Object, required: true },
    moduleForm:          { type: Object, required: true },
    lessonImageUploadUrl:{ type: String, required: true },
});

// Local UI state
const localOpenModules = ref(new Set(props.course.modules.map((m) => m.id)));
const editingModuleId = ref(null);
const moduleEditTitle = ref('');
const addingLessonToModule = ref(null);
const showModuleForm = ref(false);

function toggleModule(id) {
    const next = new Set(localOpenModules.value);
    next.has(id) ? next.delete(id) : next.add(id);
    localOpenModules.value = next;
}

function startEditModule(mod) {
    editingModuleId.value = mod.id;
    moduleEditTitle.value = mod.title;
}

function handleSaveModuleTitle(mod) {
    if (!moduleEditTitle.value.trim()) return;
    emit('save-module-title', mod, moduleEditTitle.value.trim());
    editingModuleId.value = null;
}

const emit = defineEmits([
    'select-lesson',
    'delete-lesson',
    'delete-module',
    'create-lesson',
    'create-module',
    'lesson-reorder',
    'save-module-title',
    'toggle-module-free',
]);

// Expose for parent to reset after lesson creation
defineExpose({ addingLessonToModule, showModuleForm });
</script>
