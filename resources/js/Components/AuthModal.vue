<template>
    <Teleport to="body">
        <transition name="fade">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="$emit('close')">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 pt-5 pb-3">
                        <h2 class="text-lg font-bold text-gray-900">{{ mode === 'login' ? 'Sign in' : 'Create your account' }}</h2>
                        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Tabs -->
                    <div class="flex border-b border-gray-100 px-6">
                        <button @click="mode = 'login'"
                            class="pb-2.5 text-sm font-medium border-b-2 transition-colors mr-6"
                            :class="mode === 'login' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-400 hover:text-gray-600'">
                            Sign in
                        </button>
                        <button @click="mode = 'register'"
                            class="pb-2.5 text-sm font-medium border-b-2 transition-colors"
                            :class="mode === 'register' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-400 hover:text-gray-600'">
                            Sign up
                        </button>
                    </div>

                    <!-- Login form -->
                    <form v-if="mode === 'login'" @submit.prevent="submitLogin" class="p-6 space-y-4">
                        <div>
                            <label for="login-email" class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                            <input id="login-email" v-model="loginForm.email" type="email" autocomplete="email" required
                                class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="loginForm.errors.email ? 'border-red-400' : 'border-gray-300'" />
                            <p v-if="loginForm.errors.email" class="mt-1 text-xs text-red-600">{{ loginForm.errors.email }}</p>
                        </div>
                        <div>
                            <label for="login-password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                            <input id="login-password" v-model="loginForm.password" type="password" autocomplete="current-password" required
                                class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="loginForm.errors.password ? 'border-red-400' : 'border-gray-300'" />
                            <p v-if="loginForm.errors.password" class="mt-1 text-xs text-red-600">{{ loginForm.errors.password }}</p>
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2">
                                <input v-model="loginForm.remember" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-600">Remember me</span>
                            </label>
                            <Link href="/forgot-password" class="text-sm text-indigo-600 hover:underline">Forgot password?</Link>
                        </div>
                        <button type="submit" :disabled="loginForm.processing"
                            class="w-full py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                            {{ loginForm.processing ? 'Signing in...' : 'Sign in' }}
                        </button>
                    </form>

                    <!-- Register form -->
                    <form v-else @submit.prevent="submitRegister" class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="reg-first" class="block text-sm font-medium text-gray-700 mb-1.5">First name</label>
                                <input id="reg-first" v-model="registerForm.first_name" type="text" autocomplete="given-name" required
                                    class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    :class="registerForm.errors.first_name ? 'border-red-400' : 'border-gray-300'" />
                                <p v-if="registerForm.errors.first_name" class="mt-1 text-xs text-red-600">{{ registerForm.errors.first_name }}</p>
                            </div>
                            <div>
                                <label for="reg-last" class="block text-sm font-medium text-gray-700 mb-1.5">Last name</label>
                                <input id="reg-last" v-model="registerForm.last_name" type="text" autocomplete="family-name" required
                                    class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    :class="registerForm.errors.last_name ? 'border-red-400' : 'border-gray-300'" />
                                <p v-if="registerForm.errors.last_name" class="mt-1 text-xs text-red-600">{{ registerForm.errors.last_name }}</p>
                            </div>
                        </div>
                        <div>
                            <label for="reg-email" class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                            <input id="reg-email" v-model="registerForm.email" type="email" autocomplete="email" required
                                class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="registerForm.errors.email ? 'border-red-400' : 'border-gray-300'" />
                            <p v-if="registerForm.errors.email" class="mt-1 text-xs text-red-600">{{ registerForm.errors.email }}</p>
                        </div>
                        <div>
                            <label for="reg-phone" class="block text-sm font-medium text-gray-700 mb-1.5">Mobile number</label>
                            <input id="reg-phone" v-model="registerForm.phone" type="tel" autocomplete="tel" placeholder="e.g. 09xxxxxxxxx"
                                class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="registerForm.errors.phone ? 'border-red-400' : 'border-gray-300'" />
                            <p v-if="registerForm.errors.phone" class="mt-1 text-xs text-red-600">{{ registerForm.errors.phone }}</p>
                        </div>
                        <div>
                            <label for="reg-password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                            <input id="reg-password" v-model="registerForm.password" type="password" autocomplete="new-password" required
                                class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="registerForm.errors.password ? 'border-red-400' : 'border-gray-300'" />
                            <p v-if="registerForm.errors.password" class="mt-1 text-xs text-red-600">{{ registerForm.errors.password }}</p>
                        </div>
                        <div>
                            <label for="reg-password-confirm" class="block text-sm font-medium text-gray-700 mb-1.5">Confirm password</label>
                            <input id="reg-password-confirm" v-model="registerForm.password_confirmation" type="password" autocomplete="new-password" required
                                class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="registerForm.errors.password_confirmation ? 'border-red-400' : 'border-gray-300'" />
                            <p v-if="registerForm.errors.password_confirmation" class="mt-1 text-xs text-red-600">{{ registerForm.errors.password_confirmation }}</p>
                        </div>
                        <button type="submit" :disabled="registerForm.processing"
                            class="w-full py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                            {{ registerForm.processing ? 'Creating account...' : 'Sign up' }}
                        </button>
                    </form>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';

defineProps({
    show: { type: Boolean, default: false },
    initialMode: { type: String, default: 'register' },
});

defineEmits(['close']);

const mode = ref('register');

const loginForm = useForm({
    email: '',
    password: '',
    remember: false,
});

const registerForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

function submitLogin() {
    loginForm.post('/login', {
        onFinish: () => loginForm.reset('password'),
    });
}

function submitRegister() {
    registerForm.post('/register', {
        onFinish: () => registerForm.reset('password', 'password_confirmation'),
    });
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
