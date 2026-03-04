<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Navbar -->
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <!-- Logo -->
                    <Link href="/" class="text-xl font-bold text-indigo-600">
                        Learn247
                    </Link>

                    <!-- Nav links -->
                    <div class="flex items-center gap-4">
                        <Link
                            href="/communities"
                            class="text-sm text-gray-600 hover:text-indigo-600 transition-colors"
                        >
                            Communities
                        </Link>

                        <template v-if="$page.props.auth?.user">
                            <!-- User menu -->
                            <div class="relative" ref="menuRef">
                                <button
                                    @click="menuOpen = !menuOpen"
                                    class="flex items-center gap-2 text-sm text-gray-700 hover:text-indigo-600 transition-colors"
                                >
                                    <span class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-medium text-xs">
                                        {{ initials }}
                                    </span>
                                    <span class="hidden sm:block">{{ $page.props.auth.user.name }}</span>
                                </button>

                                <div
                                    v-if="menuOpen"
                                    class="absolute right-0 mt-2 w-44 bg-white border border-gray-200 rounded-lg shadow-lg z-50"
                                >
                                    <Link
                                        method="post"
                                        href="/logout"
                                        as="button"
                                        class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg"
                                        @click="menuOpen = false"
                                    >
                                        Sign out
                                    </Link>
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <Link href="/login" class="text-sm text-gray-600 hover:text-indigo-600 transition-colors">
                                Sign in
                            </Link>
                            <Link
                                href="/register"
                                class="text-sm bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors"
                            >
                                Get started
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Flash messages -->
        <div v-if="flash.success || flash.error" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
            <div
                v-if="flash.success"
                class="flex items-center gap-2 p-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg"
            >
                <span>{{ flash.success }}</span>
                <button @click="flash.success = null" class="ml-auto text-green-600 hover:text-green-800">&times;</button>
            </div>
            <div
                v-if="flash.error"
                class="flex items-center gap-2 p-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg"
            >
                <span>{{ flash.error }}</span>
                <button @click="flash.error = null" class="ml-auto text-red-600 hover:text-red-800">&times;</button>
            </div>
        </div>

        <!-- Page content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <slot />
        </main>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const menuOpen = ref(false);
const menuRef = ref(null);

const flash = ref({
    success: page.props.flash?.success ?? null,
    error: page.props.flash?.error ?? null,
});

const initials = computed(() => {
    const name = page.props.auth?.user?.name ?? '';
    return name.split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();
});

function handleOutsideClick(e) {
    if (menuRef.value && !menuRef.value.contains(e.target)) {
        menuOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', handleOutsideClick));
onBeforeUnmount(() => document.removeEventListener('click', handleOutsideClick));
</script>
