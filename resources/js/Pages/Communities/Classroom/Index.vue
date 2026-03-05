<template>
    <AppLayout :title="`${community.name} · Classroom`" :community="community">
        <CommunityTabs :community="community" active-tab="classroom" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-black text-gray-900">Classroom</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ courses.length }} course{{ courses.length !== 1 ? 's' : '' }}</p>
            </div>
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
                <div class="flex gap-2 justify-end">
                    <button type="button" @click="showForm = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancel</button>
                    <button
                        type="submit"
                        :disabled="courseForm.processing"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {{ courseForm.processing ? 'Creating...' : 'Create Course' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Course grid -->
        <div v-if="courses.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link
                v-for="course in courses"
                :key="course.id"
                :href="`/communities/${community.slug}/classroom/courses/${course.id}`"
                class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm hover:border-indigo-200 hover:shadow-md transition-all group"
            >
                <h3 class="font-bold text-gray-900 mb-1 group-hover:text-indigo-700 transition-colors">{{ course.title }}</h3>
                <p v-if="course.description" class="text-sm text-gray-500 mb-4 line-clamp-2">{{ course.description }}</p>
                <div v-else class="mb-4" />

                <div class="flex items-center justify-between text-xs text-gray-400 mb-2">
                    <span>{{ course.total }} lesson{{ course.total !== 1 ? 's' : '' }}</span>
                    <span v-if="course.total > 0">{{ course.completed }}/{{ course.total }} done</span>
                </div>

                <!-- Progress bar -->
                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-indigo-500 rounded-full transition-all"
                        :style="{ width: course.total > 0 ? `${Math.round(course.completed / course.total * 100)}%` : '0%' }"
                    />
                </div>
            </Link>
        </div>

        <!-- Empty state -->
        <div v-else class="bg-white border border-gray-200 rounded-2xl p-16 text-center shadow-sm">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 flex items-center justify-center mx-auto mb-4">
                <span class="text-3xl">🎓</span>
            </div>
            <p class="text-sm font-medium text-gray-700 mb-1">No courses yet</p>
            <p class="text-xs text-gray-400">{{ isOwner ? 'Create your first course to get started.' : 'The owner hasn\'t added any courses yet.' }}</p>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommunityTabs from '@/Components/CommunityTabs.vue';

const props = defineProps({
    community: Object,
    courses:   Array,
});

const page     = usePage();
const isOwner  = props.community.owner_id === page.props.auth?.user?.id;
const showForm = ref(false);

const courseForm = useForm({ title: '', description: '' });

function createCourse() {
    courseForm.post(`/communities/${props.community.slug}/classroom/courses`, {
        onSuccess: () => {
            courseForm.reset();
            showForm.value = false;
        },
    });
}
</script>
