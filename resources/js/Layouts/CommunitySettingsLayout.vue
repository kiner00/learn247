<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    community: { type: Object, required: true },
});

const page = usePage();
const base = computed(() => `/communities/${props.community.slug}/settings`);

const navItems = computed(() => [
    { href: `${base.value}/general`,        label: 'General' },
    { href: `${base.value}/affiliate`,      label: 'Affiliate' },
    { href: `${base.value}/ai-tools`,       label: 'AI Tools' },
    { href: `${base.value}/chat-history`,  label: 'Chat History' },
    { href: `${base.value}/announcements`,  label: 'Announcements' },
    { href: `${base.value}/level-perks`,    label: 'Level Perks' },
    { href: `${base.value}/invite-members`, label: 'Invite Members' },
    { href: `${base.value}/integrations`,   label: 'Integrations' },
    { href: `${base.value}/domain`,         label: 'Domain' },
    { href: `${base.value}/sms`,            label: 'SMS' },
    { href: `${base.value}/danger-zone`,    label: 'Danger Zone' },
]);

function isActive(href) {
    return page.url.startsWith(href);
}
</script>

<template>
    <AppLayout :title="`${community.name} · Settings`">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
            <Link :href="`/communities/${community.slug}`" class="hover:text-indigo-600 transition-colors">
                {{ community.name }}
            </Link>
            <span>/</span>
            <span>Settings</span>
        </div>

        <div class="flex items-center justify-between mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Community Settings</h1>
            <Link
                :href="`/communities/${community.slug}/analytics`"
                class="flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors"
            >
                <span>📊</span> Analytics
            </Link>
        </div>

        <div class="flex gap-6 items-start">
            <!-- Sidebar nav -->
            <div class="w-44 shrink-0 sticky top-20">
                <nav class="space-y-0.5">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="w-full flex items-center text-left px-3 py-2 text-sm rounded-xl font-medium transition-colors"
                        :class="isActive(item.href)
                            ? 'bg-indigo-50 text-indigo-700'
                            : 'text-gray-600 hover:bg-gray-100'"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </div>

            <!-- Main content -->
            <div class="flex-1 min-w-0 max-w-2xl">
                <slot />
            </div>
        </div>
    </AppLayout>
</template>
