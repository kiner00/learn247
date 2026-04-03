<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" @click.self="$emit('close')">
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 translate-y-4 scale-95"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 translate-y-4 scale-95"
                    appear
                >
                    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden">
                        <div class="relative h-44 bg-gray-900 overflow-hidden">
                            <img v-if="community.cover_image" :src="community.cover_image" class="w-full h-full object-cover opacity-80" />
                            <div v-else class="w-full h-full bg-linear-to-br from-indigo-500 to-purple-700" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" />
                            <div class="absolute bottom-4 left-6">
                                <h2 class="text-xl font-black text-white">{{ community.name }}</h2>
                                <p class="text-sm text-white/70 mt-0.5">
                                    {{ community.price > 0
                                        ? `₱${Number(community.price).toLocaleString()}${community.billing_type === 'one_time' ? '' : '/mo'}`
                                        : 'Free' }}
                                    &nbsp;·&nbsp; {{ formatCount(community.members_count) }} members
                                </p>
                            </div>
                            <button @click="$emit('close')"
                                class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full bg-black/40 hover:bg-black/60 text-white transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="p-8">
                            <div v-if="invitedBy" class="flex items-center gap-3 mb-6 pb-6 border-b border-gray-100">
                                <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center font-bold text-indigo-600 text-sm shrink-0 overflow-hidden ring-2 ring-indigo-200">
                                    <img v-if="invitedBy.avatar" :src="invitedBy.avatar" class="w-full h-full object-cover" />
                                    <span v-else>{{ invitedBy.name.charAt(0).toUpperCase() }}</span>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400">Invited by</p>
                                    <p class="text-sm font-bold text-gray-900">{{ invitedBy.name }}</p>
                                </div>
                            </div>

                            <h3 class="text-lg font-black text-gray-900 mb-5">Create your account to join</h3>

                            <form @submit.prevent="$emit('submit')">
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">First name</label>
                                        <input v-model="joinForm.first_name" type="text" required
                                            class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                            :class="joinForm.errors.first_name ? 'border-red-400' : 'border-gray-300'" />
                                        <p v-if="joinForm.errors.first_name" class="mt-1 text-xs text-red-600">{{ joinForm.errors.first_name }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Last name</label>
                                        <input v-model="joinForm.last_name" type="text" required
                                            class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                            :class="joinForm.errors.last_name ? 'border-red-400' : 'border-gray-300'" />
                                        <p v-if="joinForm.errors.last_name" class="mt-1 text-xs text-red-600">{{ joinForm.errors.last_name }}</p>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                                    <input v-model="joinForm.email" type="email" required
                                        class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        :class="joinForm.errors.email ? 'border-red-400' : 'border-gray-300'" />
                                    <p v-if="joinForm.errors.email" class="mt-1 text-xs text-red-600">{{ joinForm.errors.email }}</p>
                                </div>
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone number</label>
                                    <input v-model="joinForm.phone" type="tel" required
                                        class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        :class="joinForm.errors.phone ? 'border-red-400' : 'border-gray-300'"
                                        placeholder="+63 9XX XXX XXXX" />
                                    <p v-if="joinForm.errors.phone" class="mt-1 text-xs text-red-600">{{ joinForm.errors.phone }}</p>
                                </div>
                                <button type="submit" :disabled="joinForm.processing"
                                    class="w-full py-3.5 bg-amber-400 hover:bg-amber-500 text-gray-900 text-sm font-black rounded-2xl tracking-wide uppercase transition disabled:opacity-50 shadow-sm">
                                    {{ joinForm.processing ? 'Redirecting…' : (community.price > 0 ? `Proceed to Payment · ₱${Number(community.price).toLocaleString()}` : 'Join for Free') }}
                                </button>
                                <p class="text-xs text-gray-400 text-center mt-4">
                                    Secure checkout powered by <strong>learn247</strong>. Your login credentials will be sent to your email after payment.
                                </p>
                            </form>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
defineProps({
    show: { type: Boolean, default: false },
    community: { type: Object, required: true },
    invitedBy: { type: Object, default: null },
    joinForm: { type: Object, required: true },
});

defineEmits(['close', 'submit']);

function formatCount(n) {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(n ?? 0);
}
</script>
