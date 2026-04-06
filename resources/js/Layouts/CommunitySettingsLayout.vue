<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCommunityUrl } from '@/composables/useCommunityUrl';

const props = defineProps({
    community: { type: Object, required: true },
});

const page = usePage();
const { communityPath } = useCommunityUrl(props.community.slug);
const base = computed(() => communityPath('/settings'));

const emailBase = computed(() => communityPath('/email'));

const navItems = computed(() => [
    { href: `${base.value}/general`,        label: 'General' },
    { href: `${base.value}/affiliate`,      label: 'Affiliate' },
    { href: `${base.value}/ai-tools`,       label: 'AI Tools' },
    { href: `${base.value}/announcements`,  label: 'Announcements' },
    { href: `${base.value}/level-perks`,    label: 'Level Perks' },
    { href: `${base.value}/invite-members`, label: 'Invite Members' },
    { href: `${base.value}/integrations`,   label: 'Integrations' },
    { href: `${base.value}/domain`,         label: 'Domain' },
    { href: `${base.value}/sms`,            label: 'SMS' },
    { href: `${base.value}/email`,          label: 'Email', children: [
        { href: `${emailBase.value}-campaigns`,  label: 'Campaigns' },
        { href: `${emailBase.value}-sequences`,  label: 'Sequences' },
        { href: `${emailBase.value}-history`,     label: 'Send History' },
        { href: `${emailBase.value}-analytics`,   label: 'Analytics' },
    ]},
    { href: `${base.value}/danger-zone`,    label: 'Danger Zone' },
]);

function isActive(href) {
    return page.url.startsWith(href);
}

function isEmailSectionActive() {
    const url = page.url;
    return url.startsWith(communityPath('/settings/email'))
        || url.startsWith(communityPath('/email-campaigns'))
        || url.startsWith(communityPath('/email-sequences'))
        || url.startsWith(communityPath('/email-history'))
        || url.startsWith(communityPath('/email-analytics'));
}

</script>

<template>
    <AppLayout :title="`${community.name} · Settings`">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
            <Link :href="communityPath()" class="hover:text-indigo-600 transition-colors">
                {{ community.name }}
            </Link>
            <span>/</span>
            <span>Settings</span>
        </div>

        <div class="flex items-center justify-between mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Community Settings</h1>
            <Link
                :href="communityPath('/analytics')"
                class="flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors"
            >
                <span>📊</span> Analytics
            </Link>
        </div>

        <div class="flex gap-6 items-start">
            <!-- Sidebar nav -->
            <div class="w-44 shrink-0 sticky top-20">
                <nav class="space-y-0.5">
                    <template v-for="item in navItems" :key="item.href">
                        <Link
                            :href="item.href"
                            class="w-full flex items-center text-left px-3 py-2 text-sm rounded-xl font-medium transition-colors"
                            :class="(item.children ? isEmailSectionActive() : isActive(item.href))
                                ? 'bg-indigo-50 text-indigo-700'
                                : 'text-gray-600 hover:bg-gray-100'"
                        >
                            {{ item.label }}
                        </Link>
                        <template v-if="item.children && isEmailSectionActive()">
                            <Link
                                v-for="child in item.children"
                                :key="child.href"
                                :href="child.href"
                                class="w-full flex items-center text-left pl-6 pr-3 py-1.5 text-sm rounded-xl font-medium transition-colors"
                                :class="isActive(child.href)
                                    ? 'text-indigo-600'
                                    : 'text-gray-400 hover:text-gray-600'"
                            >
                                {{ child.label }}
                            </Link>
                        </template>
                    </template>
                </nav>
            </div>

            <!-- Main content -->
            <div class="flex-1 min-w-0" :class="isEmailSectionActive() ? 'max-w-4xl' : 'max-w-2xl'">
                <slot />
            </div>
        </div>
    </AppLayout>
</template>
