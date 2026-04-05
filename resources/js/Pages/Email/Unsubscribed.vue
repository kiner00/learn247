<script setup>
defineProps({
    communityName: String,
});
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
        <div class="max-w-sm w-full bg-white border border-gray-200 rounded-2xl p-8 text-center">
            <div class="text-4xl mb-4">📭</div>
            <h1 class="text-lg font-bold text-gray-900 mb-2">Unsubscribed</h1>
            <p class="text-sm text-gray-500">
                You've been unsubscribed from emails by <strong>{{ communityName }}</strong>.
                You will no longer receive email campaigns from this community.
            </p>
        </div>
    </div>
</template>
