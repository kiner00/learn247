<template>
    <AppLayout :title="`${community.name} · Classroom`" :community="community">
        <CommunityTabs :community="community" active-tab="classroom" />

        <!-- Header row -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-black text-gray-900">
                Classroom
                <span class="text-sm font-normal text-gray-400 ml-2">{{ courses.length }} course{{ courses.length !== 1 ? 's' : '' }}</span>
            </h1>
            <button
                v-if="isOwner"
                @click="showForm = !showForm"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors"
            >
                + New Course
            </button>
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
                    class="w-full px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none mb-2"
                />
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
                <div class="flex gap-2 justify-end">
                    <button type="button" @click="showForm = false; removeCover()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancel</button>
                    <button type="submit" :disabled="courseForm.processing"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                        {{ courseForm.processing ? 'Creating...' : 'Create Course' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Course grid (Skool-style, full-width 3-col) -->
        <div v-if="courses.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <div v-for="course in courses" :key="course.id" class="relative group">
                <Link
                    :href="`/communities/${community.slug}/classroom/courses/${course.id}`"
                    class="block bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all border border-gray-100"
                >
                    <!-- Cover image -->
                    <div class="relative aspect-video bg-gray-900 overflow-hidden">
                        <img
                            v-if="course.cover_image"
                            :src="course.cover_image"
                            :alt="course.title"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                        />
                        <div v-else class="w-full h-full bg-gradient-to-br from-indigo-600 to-purple-700 flex items-center justify-center">
                            <span class="text-4xl font-black text-white/20 select-none">{{ course.title.charAt(0).toUpperCase() }}</span>
                        </div>
                        <!-- Progress badge -->
                        <div v-if="course.total > 0 && course.completed > 0"
                            class="absolute top-2.5 right-2.5 px-2 py-0.5 bg-black/60 text-white text-xs font-semibold rounded-full backdrop-blur-sm">
                            {{ Math.round(course.completed / course.total * 100) }}%
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="p-4">
                        <h3 class="font-bold text-gray-900 mb-1 group-hover:text-indigo-700 transition-colors line-clamp-1">{{ course.title }}</h3>
                        <p v-if="course.description" class="text-sm text-gray-500 mb-3 line-clamp-2 leading-relaxed">{{ course.description }}</p>
                        <div v-else class="mb-3" />

                        <!-- Progress bar -->
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-indigo-500 rounded-full transition-all"
                                    :style="{ width: course.total > 0 ? `${Math.round(course.completed / course.total * 100)}%` : '0%' }"
                                />
                            </div>
                            <span class="text-xs text-gray-400 shrink-0">
                                {{ course.total > 0 ? `${Math.round(course.completed / course.total * 100)}%` : '0%' }}
                            </span>
                        </div>
                    </div>
                </Link>

                <!-- Edit button (owner only, top-left of image) -->
                <button
                    v-if="isOwner"
                    @click.prevent="openEdit(course)"
                    class="absolute top-2.5 left-2.5 w-7 h-7 bg-black/50 hover:bg-black/70 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-10"
                    title="Edit course"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 11l6-6 3 3-6 6H9v-3z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Edit course modal -->
        <Teleport to="body">
            <div v-if="editingCourse" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="editingCourse = null">
                <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
                    <h2 class="text-base font-bold text-gray-900 mb-4">Edit Course</h2>
                    <form @submit.prevent="submitEdit">
                        <input v-model="editForm.title" type="text" required placeholder="Course title"
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-2" />
                        <textarea v-model="editForm.description" rows="2" placeholder="Description (optional)"
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none mb-3" />

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
        <div v-else class="bg-white border border-gray-200 rounded-2xl p-16 text-center shadow-sm">
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
import { ref, computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import InviteModal from '@/Components/InviteModal.vue';

const props = defineProps({
    community: Object,
    courses:   Array,
    affiliate: Object,
});

const page     = usePage();
const isOwner  = props.community.owner_id === page.props.auth?.user?.id;
const showForm = ref(false);
const showInviteModal = ref(false);
const coverPreview = ref(null);
const coverInput   = ref(null);

const inviteUrl = computed(() =>
    props.affiliate?.code
        ? `${window.location.origin}/ref/${props.affiliate.code}`
        : `${window.location.origin}/communities/${props.community.slug}`
);

const courseForm = useForm({ title: '', description: '', cover_image: null });

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

function createCourse() {
    courseForm.post(`/communities/${props.community.slug}/classroom/courses`, {
        forceFormData: true,
        onSuccess: () => {
            courseForm.reset();
            removeCover();
            showForm.value = false;
        },
    });
}

// ── Edit course ──────────────────────────────────────────────────────────────
const editingCourse   = ref(null);
const editCoverPreview = ref(null);
const editCoverInput  = ref(null);
const editForm        = useForm({ title: '', description: '', cover_image: null });

function openEdit(course) {
    editingCourse.value    = course;
    editCoverPreview.value = null;
    editForm.title       = course.title;
    editForm.description = course.description ?? '';
    editForm.cover_image = null;
}

function onEditCoverChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    editForm.cover_image   = file;
    editCoverPreview.value = URL.createObjectURL(file);
}

function submitEdit() {
    editForm.post(
        `/communities/${props.community.slug}/classroom/courses/${editingCourse.value.id}/update`,
        {
            forceFormData: true,
            onSuccess: () => {
                editingCourse.value   = null;
                editCoverPreview.value = null;
            },
        }
    );
}
</script>
