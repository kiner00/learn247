<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <Link href="/" class="inline-block">
                    <img :src="`/brand/logo-${page.props.app_theme ?? 'green'}.png`" alt="Curzzo" class="h-10 w-auto mx-auto" />
                </Link>
            </div>

            <!-- Community info card -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">
                <div class="mb-6">
                    <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">You're joining</p>
                    <h2 class="text-xl font-bold text-gray-900">{{ community.name }}</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        ₱{{ Number(community.price).toLocaleString() }}/month
                    </p>
                </div>

                <form @submit.prevent="submit">
                    <!-- First name + Last name -->
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1.5">First name</label>
                            <input
                                id="first_name"
                                v-model="form.first_name"
                                type="text"
                                autocomplete="given-name"
                                required
                                class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="form.errors.first_name ? 'border-red-400' : 'border-gray-300'"
                            />
                            <p v-if="form.errors.first_name" class="mt-1 text-xs text-red-600">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1.5">Last name</label>
                            <input
                                id="last_name"
                                v-model="form.last_name"
                                type="text"
                                autocomplete="family-name"
                                required
                                class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="form.errors.last_name ? 'border-red-400' : 'border-gray-300'"
                            />
                            <p v-if="form.errors.last_name" class="mt-1 text-xs text-red-600">{{ form.errors.last_name }}</p>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="mb-6">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                            class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            :class="form.errors.email ? 'border-red-400' : 'border-gray-300'"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 bg-amber-500 text-white text-sm font-bold rounded-xl hover:bg-amber-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'Redirecting to payment...' : `Proceed to Payment · ₱${Number(community.price).toLocaleString()}/mo` }}
                    </button>
                </form>

                <p class="text-xs text-gray-400 text-center mt-4">
                    You'll set your password after payment is confirmed.
                </p>
            </div>

            <p class="text-center mt-6 text-sm text-gray-600">
                Already have an account?
                <Link href="/login" class="text-indigo-600 font-medium hover:underline">Log in</Link>
            </p>
        </div>
    </div>
</template>

<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    community: Object,
    refCode: String,
});

const page = usePage();

const form = useForm({
    first_name: '',
    last_name:  '',
    email:      '',
});

function submit() {
    form.post(`/ref-checkout/${props.refCode}`);
}
</script>
