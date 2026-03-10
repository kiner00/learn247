<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <Link href="/" class="inline-block">
                    <img :src="`/brand/logo-${page.props.app_theme ?? 'green'}.png`" alt="Curzzo" class="h-10 w-auto mx-auto" />
                </Link>
                <p class="mt-2 text-gray-500 text-sm">Set your password to access the community</p>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">
                <form @submit.prevent="submit">
                    <!-- Password -->
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            :class="form.errors.password ? 'border-red-400' : 'border-gray-300'"
                        />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-6">
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1.5">Confirm password</label>
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />
                    </div>

                    <p v-if="form.errors.email" class="mb-4 text-sm text-red-600 text-center">{{ form.errors.email }}</p>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'Saving...' : 'Set Password & Enter' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    token: String,
    email: String,
});

const page = usePage();

const form = useForm({
    token:                 props.token,
    email:                 props.email,
    password:              '',
    password_confirmation: '',
});

function submit() {
    form.post('/set-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>
