<template>
    <AppLayout :title="title">
        <div class="flex gap-0 items-start -mx-4 sm:-mx-6 lg:-mx-8">

            <!-- Sidebar nav -->
            <div class="w-48 shrink-0 py-2 px-2 sticky top-20">
                <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wide">Admin</p>
                <nav class="space-y-0.5">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="w-full flex items-center gap-2 text-left px-4 py-2 text-sm rounded-xl font-medium transition-colors"
                        :class="isActive(item.href)
                            ? 'bg-indigo-50 text-indigo-700'
                            : 'text-gray-600 hover:bg-gray-100'"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </div>

            <!-- Main content -->
            <div class="flex-1 min-w-0 py-2 pr-4 sm:pr-6 lg:pr-8">
                <slot />
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    title: { type: String, default: 'Admin' },
});

const page = usePage();

const navItems = [
    { href: '/admin',                    label: 'Dashboard' },
    { href: '/admin/users',              label: 'Users' },
    { href: '/admin/kyc-reviews',        label: 'KYC Reviews' },
    { href: '/admin/payouts',            label: 'Payouts' },
    { href: '/admin/coupons',            label: 'Coupons' },
    { href: '/admin/creator-analytics',  label: 'Creator Analytics' },
    { href: '/admin/affiliate-analytics',label: 'Affiliate Analytics' },
    { href: '/admin/posts/trashed',      label: 'Trashed Posts' },
    { href: '/admin/announcements',      label: 'Announcements' },
    { href: '/admin/email-templates',    label: 'Email Templates' },
];

function isActive(href) {
    const url = page.url;
    if (href === '/admin') return url === '/admin';
    return url.startsWith(href);
}
</script>
