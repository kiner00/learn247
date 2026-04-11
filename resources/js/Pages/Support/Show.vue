<template>
    <AppLayout :title="`Ticket #${ticket.id}`">
        <div class="max-w-3xl mx-auto">
            <!-- Back link -->
            <Link href="/support" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-4">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back to tickets
            </Link>

            <!-- Ticket header -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm mb-4">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span
                                class="px-2 py-0.5 text-xs font-medium rounded-full"
                                :class="typeStyles[ticket.type]"
                            >
                                {{ ticket.type }}
                            </span>
                            <span
                                class="px-2 py-0.5 text-xs font-medium rounded-full"
                                :class="statusStyles[ticket.status]"
                            >
                                {{ ticket.status.replace('_', ' ') }}
                            </span>
                            <span
                                class="px-2 py-0.5 text-xs font-medium rounded-full"
                                :class="priorityStyles[ticket.priority]"
                            >
                                {{ ticket.priority }}
                            </span>
                        </div>
                        <h1 class="text-lg font-bold text-gray-900">{{ ticket.subject }}</h1>
                        <p class="text-xs text-gray-400 mt-1">
                            Submitted by {{ ticket.user?.name }} on {{ formatDate(ticket.created_at) }}
                        </p>
                    </div>
                </div>
                <div class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ ticket.description }}</div>

                <!-- Attachments -->
                <div v-if="ticket.attachments?.length" class="mt-4">
                    <p class="text-xs font-medium text-gray-500 mb-2">Attachments</p>
                    <div class="flex gap-3 flex-wrap">
                        <button
                            v-for="att in ticket.attachments"
                            :key="att.id"
                            type="button"
                            @click="previewImage = att.file_url"
                            class="block cursor-pointer"
                        >
                            <img
                                :src="att.file_url"
                                :alt="att.file_name"
                                class="w-24 h-24 object-cover rounded-xl border border-gray-200 hover:border-amber-400 transition-colors"
                            />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Replies -->
            <div class="space-y-3 mb-4">
                <div
                    v-for="reply in ticket.replies"
                    :key="reply.id"
                    class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm"
                    :class="reply.is_admin ? 'border-l-4 border-l-amber-400' : ''"
                >
                    <div class="flex items-center gap-2 mb-2">
                        <div
                            class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                            :class="reply.is_admin ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600'"
                        >
                            {{ reply.is_admin ? 'S' : reply.user?.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <p class="text-sm font-semibold text-gray-900">
                            {{ reply.is_admin ? 'Support' : reply.user?.name }}
                            <span v-if="reply.is_admin" class="text-xs font-medium text-amber-600 ml-1">Admin</span>
                        </p>
                        <span class="text-xs text-gray-400 ml-auto">{{ formatDate(reply.created_at) }}</span>
                    </div>
                    <div class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ reply.content }}</div>
                </div>
            </div>

            <!-- Reply form -->
            <div v-if="ticket.status !== 'closed'" class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <form @submit.prevent="submitReply">
                    <textarea
                        v-model="replyForm.content"
                        rows="3"
                        placeholder="Write a reply..."
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500 mb-3"
                    />
                    <p v-if="replyForm.errors.content" class="text-xs text-red-500 mb-2">{{ replyForm.errors.content }}</p>
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="replyForm.processing || !replyForm.content.trim()"
                            class="px-5 py-2 bg-amber-500 text-white text-sm font-semibold rounded-xl hover:bg-amber-600 transition-colors disabled:opacity-50"
                        >
                            {{ replyForm.processing ? 'Sending...' : 'Reply' }}
                        </button>
                    </div>
                </form>
            </div>
            <div v-else class="bg-gray-50 border border-gray-200 rounded-2xl p-5 text-center">
                <p class="text-sm text-gray-500">This ticket is closed.</p>
            </div>
        </div>

        <!-- Image preview modal -->
        <Teleport to="body">
            <div
                v-if="previewImage"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
                @click.self="previewImage = null"
            >
                <button
                    type="button"
                    class="absolute top-4 right-4 text-white text-3xl leading-none hover:text-gray-300"
                    @click="previewImage = null"
                >&times;</button>
                <img
                    :src="previewImage"
                    class="max-w-[90vw] max-h-[90vh] rounded-xl shadow-2xl"
                />
            </div>
        </Teleport>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ ticket: Object });

const previewImage = ref(null);

const replyForm = useForm({ content: '' });

function submitReply() {
    replyForm.post(`/support/${props.ticket.id}/reply`, {
        preserveScroll: true,
        onSuccess: () => replyForm.reset(),
    });
}

const typeStyles = {
    bug:        'bg-red-100 text-red-700',
    suggestion: 'bg-blue-100 text-blue-700',
    question:   'bg-violet-100 text-violet-700',
    other:      'bg-gray-100 text-gray-600',
};

const statusStyles = {
    open:        'bg-green-100 text-green-700',
    in_progress: 'bg-amber-100 text-amber-700',
    resolved:    'bg-blue-100 text-blue-700',
    closed:      'bg-gray-100 text-gray-500',
};

const priorityStyles = {
    low:    'bg-gray-100 text-gray-600',
    medium: 'bg-yellow-100 text-yellow-700',
    high:   'bg-red-100 text-red-700',
};

function formatDate(str) {
    if (!str) return '';
    const d = new Date(str);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}
</script>
