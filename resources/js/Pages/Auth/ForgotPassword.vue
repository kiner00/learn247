<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <Link href="/" class="inline-block">
                    <img
                        :src="brandLogo"
                        :alt="brandName"
                        class="h-30 w-auto mx-auto"
                    />
                </Link>
                <p class="mt-2 text-gray-500 text-sm">
                    Enter your email and we'll send a reset link.
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">
                <div
                    v-if="$page.props.flash?.success"
                    class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700"
                >
                    {{ $page.props.flash.success }}
                </div>

                <form @submit.prevent="submit">
                    <div class="mb-6">
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

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? "Sending..." : "Send Reset Link" }}
                    </button>
                </form>
            </div>

            <p class="text-center mt-6 text-sm text-gray-600">
                Remember your password?
                <Link
                    href="/login"
                    class="text-indigo-600 font-medium hover:underline"
                    >Sign in</Link
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

const form = useForm({ email: "" });

function submit() {
    form.post("/forgot-password");
}
</script>
