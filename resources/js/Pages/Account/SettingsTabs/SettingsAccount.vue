<template>
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h2 class="text-base font-bold text-gray-900 mb-6">Account</h2>

        <!-- Email row -->
        <div class="flex items-center justify-between py-4 border-b border-gray-100">
            <div>
                <p class="text-sm font-semibold text-gray-800">Email</p>
                <p class="text-sm text-gray-500 mt-0.5">{{ profileUser?.email }}</p>
            </div>
            <button @click="showEmailForm = !showEmailForm" class="px-4 py-2 text-xs font-bold text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 tracking-wide transition-colors">
                CHANGE EMAIL
            </button>
        </div>
        <div v-if="showEmailForm" class="py-4 border-b border-gray-100">
            <form @submit.prevent="saveEmail" class="space-y-3 max-w-sm">
                <input v-model="emailForm.email" type="email" placeholder="New email address"
                    class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    :class="emailForm.errors.email ? 'border-red-400' : 'border-gray-300'" />
                <p v-if="emailForm.errors.email" class="text-xs text-red-600">{{ emailForm.errors.email }}</p>
                <button type="submit" :disabled="emailForm.processing"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                    {{ emailForm.processing ? 'Saving...' : 'Save email' }}
                </button>
            </form>
        </div>

        <!-- Password row -->
        <div class="flex items-center justify-between py-4 border-b border-gray-100">
            <div>
                <p class="text-sm font-semibold text-gray-800">Password</p>
                <p class="text-sm text-gray-500 mt-0.5">Change your password</p>
            </div>
            <button @click="showPasswordForm = !showPasswordForm" class="px-4 py-2 text-xs font-bold text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 tracking-wide transition-colors">
                CHANGE PASSWORD
            </button>
        </div>
        <div v-if="showPasswordForm" class="py-4 border-b border-gray-100">
            <form @submit.prevent="savePassword" class="space-y-3 max-w-sm">
                <input v-model="passwordForm.current_password" type="password" placeholder="Current password"
                    class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    :class="passwordForm.errors.current_password ? 'border-red-400' : 'border-gray-300'" />
                <p v-if="passwordForm.errors.current_password" class="text-xs text-red-600">{{ passwordForm.errors.current_password }}</p>
                <input v-model="passwordForm.password" type="password" placeholder="New password"
                    class="w-full px-3.5 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    :class="passwordForm.errors.password ? 'border-red-400' : 'border-gray-300'" />
                <p v-if="passwordForm.errors.password" class="text-xs text-red-600">{{ passwordForm.errors.password }}</p>
                <input v-model="passwordForm.password_confirmation" type="password" placeholder="Confirm new password"
                    class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                <button type="submit" :disabled="passwordForm.processing"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                    {{ passwordForm.processing ? 'Saving...' : 'Save password' }}
                </button>
            </form>
        </div>

        <!-- Timezone row -->
        <div class="py-4 border-b border-gray-100">
            <p class="text-sm font-semibold text-gray-800 mb-3">Timezone</p>
            <div class="flex gap-2 max-w-sm">
                <select v-model="timezoneForm.timezone"
                    class="flex-1 px-3.5 py-2.5 border border-gray-200 rounded-lg text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                    <option value="Asia/Manila">(GMT +08:00) Asia/Manila</option>
                    <option value="UTC">(GMT +00:00) UTC</option>
                    <option value="America/New_York">(GMT -05:00) America/New_York</option>
                    <option value="America/Los_Angeles">(GMT -08:00) America/Los_Angeles</option>
                    <option value="Asia/Tokyo">(GMT +09:00) Asia/Tokyo</option>
                    <option value="Asia/Singapore">(GMT +08:00) Asia/Singapore</option>
                </select>
                <button @click="saveTimezone" :disabled="timezoneForm.processing"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                    Save
                </button>
            </div>
        </div>

        <!-- Log out everywhere -->
        <div class="flex items-center justify-between pt-4">
            <div>
                <p class="text-sm font-semibold text-gray-800">Log out of all devices</p>
                <p class="text-sm text-gray-500 mt-0.5">Log out of all active sessions on all devices.</p>
            </div>
            <button @click="logoutEverywhere" class="px-4 py-2 text-xs font-bold text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 tracking-wide transition-colors">
                LOG OUT EVERYWHERE
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    profileUser: { type: Object, required: true },
    timezone:    { type: String, default: 'Asia/Manila' },
});

const showEmailForm    = ref(false);
const showPasswordForm = ref(false);

const emailForm = useForm({ email: props.profileUser?.email ?? '' });
function saveEmail() {
    emailForm.patch('/account/settings/email', {
        preserveScroll: true,
        onSuccess: () => { showEmailForm.value = false; },
    });
}

const passwordForm = useForm({
    current_password:      '',
    password:              '',
    password_confirmation: '',
});
function savePassword() {
    passwordForm.patch('/account/settings/password', {
        preserveScroll: true,
        onSuccess: () => { showPasswordForm.value = false; passwordForm.reset(); },
    });
}

const timezoneForm = useForm({ timezone: props.timezone ?? 'Asia/Manila' });
function saveTimezone() {
    timezoneForm.patch('/account/settings/timezone', { preserveScroll: true });
}

function logoutEverywhere() {
    router.post('/account/settings/logout-everywhere', {}, { preserveScroll: true });
}
</script>
