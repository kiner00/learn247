<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <Link href="/" class="inline-block">
                    <img
                        :src="brandLogo"
                        :alt="brandName"
                        class="h-30 w-auto mx-auto"
                    />
                </Link>
                <p class="mt-2 text-gray-500 text-sm">
                    Sign in to your account
                </p>
            </div>

            <div
                class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8"
            >
                <form @submit.prevent="submit">
                    <!-- Email -->
                    <div class="mb-4">
                        <label
                            for="email"
                            class="block text-sm font-medium text-gray-700 mb-1.5"
                            >Email</label
                        >
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                            class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            :class="
                                form.errors.email
                                    ? 'border-red-400'
                                    : 'border-gray-300'
                            "
                        />
                        <p
                            v-if="form.errors.email"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Password -->
                    <div class="mb-6">
                        <label
                            for="password"
                            class="block text-sm font-medium text-gray-700 mb-1.5"
                            >Password</label
                        >
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            :class="form.errors.password ? 'border-red-400' : 'border-gray-300'"
                        />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Remember me + Forgot password -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <input
                                id="remember"
                                v-model="form.remember"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <label for="remember" class="ml-2 text-sm text-gray-600"
                                >Remember me</label
                            >
                        </div>
                        <Link
                            href="/forgot-password"
                            class="text-sm text-indigo-600 hover:underline"
                            >Forgot password?</Link
                        >
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? "Signing in..." : "Sign in" }}
                    </button>
                </form>
            </div>

            <p class="text-center mt-6 text-sm text-gray-600">
                Don't have an account?
                <Link
                    href="/register"
                    class="text-indigo-600 font-medium hover:underline"
                    >Get started free</Link
                >
            </p>

            <p v-if="isCommunityDomain" class="text-center mt-4 text-xs text-gray-400">
                Powered by
                <a href="https://curzzo.com" target="_blank" rel="noopener" class="font-medium text-gray-500 hover:text-indigo-600">Curzzo</a>
            </p>
        </div>
    </div>
</template>

<script setup>
import { Link, useForm, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

const dc = computed(() => usePage().props.domain_community);
const isCommunityDomain = computed(() => !!dc.value);
const brandLogo = computed(() => dc.value?.avatar || '/brand/logo-transparent.png');
const brandName = computed(() => dc.value?.name || 'Curzzo');

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

function submit() {
    form.post("/login", {
        onFinish: () => form.reset("password"),
    });
}
</script>
