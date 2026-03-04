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

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Banner Image</label>
                            <!-- Preview: new file selection or existing -->
                            <div
                                v-if="coverPreview || community.cover_image"
                                class="relative mb-2 h-32 rounded-xl overflow-hidden border border-gray-200 group"
                            >
                                <img
                                    :src="coverPreview || community.cover_image"
                                    class="w-full h-full object-cover"
                                    alt="Banner preview"
                                />
                                <button
                                    type="button"
                                    @click="removeCover"
                                    class="absolute top-2 right-2 w-7 h-7 rounded-full bg-black/50 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-black/70"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <!-- Upload button -->
                            <label class="flex items-center gap-2 w-fit cursor-pointer px-3.5 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                {{ coverPreview || community.cover_image ? 'Change banner' : 'Upload banner' }}
                                <input ref="coverInput" type="file" accept="image/*" class="hidden" @change="onCoverChange" />
                            </label>
                            <p class="mt-1 text-xs text-gray-400">JPG, PNG, WebP — max 5 MB</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Avatar URL</label>
                            <input
                                v-model="form.avatar"
                                type="url"
                                placeholder="https://example.com/avatar.png"
                                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Price (₱ per month)</label>
                                <input
                                    v-model="form.price"
                                    type="number"
                                    min="0"
                                    step="1"
                                    class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                />
                                <p class="mt-1 text-xs text-gray-400">Set to 0 for free access</p>
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

            <!-- Danger zone -->
            <div class="bg-white border border-red-200 rounded-2xl p-6">
                <h2 class="text-base font-semibold text-red-600 mb-1">Danger zone</h2>
                <p class="text-sm text-gray-500 mb-4">Permanently delete this community and all its data. This cannot be undone.</p>
                <button
                    @click="deleteCommunity"
                    class="px-4 py-2 border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors"
                >
                    Delete community
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const CATEGORIES = ['Tech', 'Business', 'Design', 'Health', 'Education', 'Finance', 'Other'];

const props = defineProps({
    community: Object,
});

const saved       = ref(false);
const coverPreview = ref(null);
const coverInput   = ref(null);

const form = useForm({
    name:        props.community.name,
    description: props.community.description ?? '',
    category:    props.community.category ?? '',
    avatar:      props.community.avatar ?? '',
    cover_image: null,   // File object — null means "no change"
    price:       props.community.price ?? 0,
    currency:    props.community.currency ?? 'PHP',
    is_private:  props.community.is_private ?? false,
});

function onCoverChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    form.cover_image = file;
    coverPreview.value = URL.createObjectURL(file);
}

function removeCover() {
    form.cover_image = null;
    coverPreview.value = null;
    if (coverInput.value) coverInput.value.value = '';
}

function save() {
    form.post(`/communities/${props.community.slug}`, {
        // Inertia uses POST + _method:PATCH for file uploads
        headers: { 'X-HTTP-Method-Override': 'PATCH' },
        onSuccess: () => {
            coverPreview.value = null;
            saved.value = true;
            setTimeout(() => (saved.value = false), 3000);
        },
    });
}

function deleteCommunity() {
    if (!confirm('Are you sure? This will permanently delete the community and all its data.')) return;
    router.delete(`/communities/${props.community.slug}`, {
        onSuccess: () => router.visit('/communities'),
    });
}
</script>
