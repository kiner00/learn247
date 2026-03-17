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
                    class="w-full px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none mb-3"
                />

                <!-- Access type -->
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Access</label>
                    <div class="flex gap-2">
                        <label v-for="opt in accessOptions" :key="opt.value"
                            :class="['flex-1 cursor-pointer rounded-lg border-2 p-2.5 text-center transition-all',
                                courseForm.access_type === opt.value
                                    ? 'border-indigo-500 bg-indigo-50'
                                    : 'border-gray-200 hover:border-gray-300']">
                            <input type="radio" :value="opt.value" v-model="courseForm.access_type" class="sr-only" />
                            <div class="text-base mb-0.5">{{ opt.icon }}</div>
                            <div class="text-xs font-semibold text-gray-800">{{ opt.label }}</div>
                            <div class="text-[10px] text-gray-400 leading-tight mt-0.5">{{ opt.desc }}</div>
                        </label>
                    </div>
                </div>

                <!-- Price (paid_once only) -->
                <div v-if="courseForm.access_type === 'paid_once'" class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Price (PHP)</label>
                    <input v-model="courseForm.price" type="number" min="1" step="0.01" required placeholder="e.g. 1500"
                        class="w-48 px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
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
        <div v-if="courses.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <div v-for="course in courses" :key="course.id" class="relative group">
                <!-- Clickable wrapper (always navigates; access enforced on Show page) -->
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
                            :class="['w-full h-full object-cover transition-transform duration-300',
                                course.has_access ? 'group-hover:scale-105' : 'blur-sm scale-105']"
                        />
                        <div v-else
                            :class="['w-full h-full bg-gradient-to-br from-indigo-600 to-purple-700 flex items-center justify-center',
                                !course.has_access && 'opacity-50']">
                            <span class="text-4xl font-black text-white/20 select-none">{{ course.title.charAt(0).toUpperCase() }}</span>
                        </div>

                        <!-- Lock overlay for inaccessible courses -->
                        <div v-if="!course.has_access"
                            class="absolute inset-0 flex flex-col items-center justify-center bg-black/40 backdrop-blur-[1px]">
                            <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center mb-2">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <!-- CTA label -->
                            <span v-if="course.access_type === 'paid_once'"
                                class="text-white text-xs font-bold bg-indigo-600 px-3 py-1 rounded-full">
                                ₱{{ Number(course.price).toLocaleString() }}
                            </span>
                            <span v-else class="text-white text-xs font-semibold bg-black/40 px-3 py-1 rounded-full">
                                Members only
                            </span>
                        </div>

                        <!-- Access type badge (top-right when accessible) -->
                        <div v-if="course.has_access && course.total > 0 && course.completed > 0"
                            class="absolute top-2.5 right-2.5 px-2 py-0.5 bg-black/60 text-white text-xs font-semibold rounded-full backdrop-blur-sm">
                            {{ course.progress }}%
                        </div>

                        <!-- Access badge (top-right when locked) -->
                        <div v-if="!course.has_access"
                            class="absolute top-2.5 right-2.5">
                            <span v-if="course.access_type === 'free'"
                                class="px-2 py-0.5 bg-green-500 text-white text-xs font-bold rounded-full">Free</span>
                            <span v-else-if="course.access_type === 'inclusive'"
                                class="px-2 py-0.5 bg-indigo-500 text-white text-xs font-bold rounded-full">Included</span>
                            <span v-else
                                class="px-2 py-0.5 bg-amber-500 text-white text-xs font-bold rounded-full">
                                ₱{{ Number(course.price).toLocaleString() }}
                            </span>
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <h3 class="font-bold text-gray-900 group-hover:text-indigo-700 transition-colors line-clamp-1">{{ course.title }}</h3>
                            <!-- Access badge (card body) -->
                            <span v-if="course.access_type === 'free'"
                                class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-green-100 text-green-700">FREE</span>
                            <span v-else-if="course.access_type === 'inclusive'"
                                class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700">INCLUDED</span>
                            <span v-else
                                class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700">
                                ₱{{ Number(course.price).toLocaleString() }}
                            </span>
                        </div>
                        <p v-if="course.description" class="text-sm text-gray-500 mb-3 line-clamp-2 leading-relaxed">{{ course.description }}</p>
                        <div v-else class="mb-3" />

                        <!-- Progress bar (only if accessible) -->
                        <div v-if="course.has_access" class="flex items-center gap-2">
                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-indigo-500 rounded-full transition-all" :style="{ width: `${course.progress}%` }" />
                            </div>
                            <span class="text-xs text-gray-400 shrink-0">{{ course.progress }}%</span>
                        </div>

                        <!-- Locked: show enroll or join CTA -->
                        <div v-else class="text-xs text-gray-400">
                            <span v-if="course.access_type === 'paid_once'">One-time purchase to unlock</span>
                            <span v-else-if="course.access_type === 'inclusive'">Join the community to unlock</span>
                        </div>
                    </div>
                </Link>

                <!-- Owner actions -->
                <div v-if="isOwner" class="absolute top-2.5 left-2.5 flex gap-1.5 z-10">
                    <button @click.prevent="openEdit(course)"
                        class="w-7 h-7 bg-black/50 hover:bg-black/80 text-white rounded-full flex items-center justify-center transition-colors" title="Edit course">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 11l6-6 3 3-6 6H9v-3z"/>
                        </svg>
                    </button>
                    <button @click.prevent="deleteCourse(course)"
                        class="w-7 h-7 bg-black/50 hover:bg-red-600 text-white rounded-full flex items-center justify-center transition-colors" title="Delete course">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
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
                                <label v-for="opt in accessOptions" :key="opt.value"
                                    :class="['flex-1 cursor-pointer rounded-lg border-2 p-2.5 text-center transition-all',
                                        editForm.access_type === opt.value
                                            ? 'border-indigo-500 bg-indigo-50'
                                            : 'border-gray-200 hover:border-gray-300']">
                                    <input type="radio" :value="opt.value" v-model="editForm.access_type" class="sr-only" />
                                    <div class="text-base mb-0.5">{{ opt.icon }}</div>
                                    <div class="text-xs font-semibold text-gray-800">{{ opt.label }}</div>
                                    <div class="text-[10px] text-gray-400 leading-tight mt-0.5">{{ opt.desc }}</div>
                                </label>
                            </div>
                        </div>

                        <!-- Price -->
                        <div v-if="editForm.access_type === 'paid_once'" class="mb-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Price (PHP)</label>
                            <input v-model="editForm.price" type="number" min="1" step="0.01" required placeholder="e.g. 1500"
                                class="w-48 px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
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
import { ref, computed } from 'vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';
import InviteModal from '@/Components/InviteModal.vue';

const props = defineProps({
    community: Object,
    courses:   Array,
    affiliate: Object,
});

const page    = usePage();
const isOwner = props.community.owner_id === page.props.auth?.user?.id;

const showForm        = ref(false);
const showInviteModal = ref(false);
const coverPreview    = ref(null);
const coverInput      = ref(null);

const accessOptions = [
    { value: 'free',      icon: '🌐', label: 'Free',     desc: 'Anyone can access' },
    { value: 'inclusive', icon: '⭐', label: 'Included',  desc: 'Members only' },
    { value: 'paid_once', icon: '💳', label: 'Paid',      desc: 'One-time purchase' },
];

const inviteUrl = computed(() =>
    props.affiliate?.code
        ? `${window.location.origin}/ref/${props.affiliate.code}`
        : `${window.location.origin}/communities/${props.community.slug}`
);

const courseForm = useForm({ title: '', description: '', cover_image: null, access_type: 'inclusive', price: '' });

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
            courseForm.access_type = 'inclusive';
            removeCover();
            showForm.value = false;
        },
    });
}

// ── Edit course ───────────────────────────────────────────────────────────────
const editingCourse    = ref(null);
const editCoverPreview = ref(null);
const editCoverInput   = ref(null);
const editForm         = useForm({ title: '', description: '', cover_image: null, access_type: 'inclusive', price: '' });

function openEdit(course) {
    editingCourse.value    = course;
    editCoverPreview.value = null;
    editForm.title         = course.title;
    editForm.description   = course.description ?? '';
    editForm.cover_image   = null;
    editForm.access_type   = course.access_type ?? 'inclusive';
    editForm.price         = course.price ?? '';
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
                editingCourse.value    = null;
                editCoverPreview.value = null;
            },
        }
    );
}

function deleteCourse(course) {
    if (!confirm(`Delete "${course.title}"? This will permanently remove all modules, lessons, quizzes, and certificates.`)) return;
    router.delete(`/communities/${props.community.slug}/classroom/courses/${course.id}`);
}
</script>
