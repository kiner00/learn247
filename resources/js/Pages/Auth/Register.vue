<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <Link href="/" class="inline-block">
                    <img :src="brandLogo" :alt="brandName" class="h-30 w-auto mx-auto" />
                </Link>
                <p class="mt-2 text-gray-500 text-sm">Create your {{ brandName }} account</p>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">
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
                    <div class="mb-4">
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

                    <!-- Mobile number -->
                    <div class="mb-4">
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1.5">Mobile number</label>
                        <input
                            id="phone"
                            v-model="form.phone"
                            type="tel"
                            autocomplete="tel"
                            placeholder="e.g. 09xxxxxxxxx"
                            class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            :class="form.errors.phone ? 'border-red-400' : 'border-gray-300'"
                        />
                        <p v-if="form.errors.phone" class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
                    </div>

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
                            class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            :class="form.errors.password_confirmation ? 'border-red-400' : 'border-gray-300'"
                        />
                        <p v-if="form.errors.password_confirmation" class="mt-1 text-xs text-red-600">{{ form.errors.password_confirmation }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'Creating account...' : 'SIGN UP' }}
                    </button>
                </form>
            </div>

            <p class="text-center mt-6 text-sm text-gray-600">
                Already have an account?
                <Link href="/login" class="text-indigo-600 font-medium hover:underline">Log in</Link>
            </p>

            <p class="text-center mt-3 text-xs text-gray-400">
                By signing up, you agree to our
                <Link href="/terms" class="text-indigo-500 hover:underline">Terms of Service</Link>
                and
                <Link href="/privacy" class="text-indigo-500 hover:underline">Privacy Policy</Link>.
            </p>

            <p v-if="isCustomDomain" class="text-center mt-4 text-xs text-gray-400">
                Powered by
                <a href="https://curzzo.com" target="_blank" rel="noopener" class="font-medium text-gray-500 hover:text-indigo-600">Curzzo</a>
            </p>
        </div>
    </div>
</template>

<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
const page = usePage();
const dc = computed(() => page.props.domain_community);
const isCustomDomain = computed(() => !!dc.value?.is_custom_domain);
const brandLogo = computed(() => isCustomDomain.value && dc.value?.avatar ? dc.value.avatar : '/brand/logo-transparent.png');
const brandName = computed(() => isCustomDomain.value && dc.value?.name ? dc.value.name : 'Curzzo');

const form = useForm({
    first_name:            '',
    last_name:             '',
    email:                 '',
    phone:                 '',
    password:              '',
    password_confirmation: '',
});

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>
