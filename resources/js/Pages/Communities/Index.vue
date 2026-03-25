<template>
    <AppLayout title="Communities">

        <!-- Hero -->
        <div class="text-center py-10 mb-6">
            <h1 class="text-4xl font-black text-gray-900 tracking-tight mb-2">
                Teach. Learn. Earn.
            </h1>
            <p class="text-gray-500 text-base">
                <button
                    v-if="$page.props.auth?.user"
                    @click="openCreateModal()"
                    class="text-indigo-600 font-medium underline underline-offset-2 hover:text-indigo-800 inline-flex items-center gap-1"
                >
                    create your community today. <span>→</span>
                </button>
                <Link v-else href="/register" class="text-indigo-600 font-medium underline underline-offset-2 hover:text-indigo-800 inline-flex items-center gap-1">
                    create your community today. <span>→</span>
                </Link>
            </p>

            <!-- Search bar -->
            <div class="mt-6 max-w-lg mx-auto relative">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                </svg>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search communities..."
                    class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white"
                />
            </div>

            <!-- Category chips -->
            <div class="mt-4 flex flex-wrap justify-center gap-2">
                <button
                    v-for="cat in categories"
                    :key="cat"
                    @click="activeCategory = cat"
                    class="px-4 py-1.5 rounded-full text-sm font-medium border transition-colors"
                    :class="activeCategory === cat
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300 hover:text-indigo-600'"
                >
                    {{ cat }}
                </button>
            </div>
        </div>

        <!-- Community count + Sort + New button -->
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm text-gray-500">
                {{ communities.total }}
                {{ communities.total === 1 ? 'community' : 'communities' }}
            </p>
            <div class="flex items-center gap-2">
                <!-- Sort toggle -->
                <div class="flex items-center gap-1 p-0.5 bg-gray-100 rounded-lg">
                    <button
                        @click="activeSort = 'latest'"
                        class="px-3 py-1 text-xs font-medium rounded-md transition-colors"
                        :class="activeSort === 'latest' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    >Latest</button>
                    <button
                        @click="activeSort = 'popular'"
                        class="px-3 py-1 text-xs font-medium rounded-md transition-colors"
                        :class="activeSort === 'popular' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    >Popular</button>
                </div>
                <button
                    v-if="$page.props.auth?.user"
                    @click="openCreateModal()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                    </svg>
                    New
                </button>
            </div>
        </div>

        <!-- Featured Communities (Pro) -->
        <div v-if="featured.length" class="mb-8">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-base">⭐</span>
                <h2 class="text-sm font-bold text-gray-900">Featured Communities</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link
                    v-for="c in featured"
                    :key="c.id"
                    :href="`/communities/${c.slug}`"
                    class="group relative bg-white border-2 border-indigo-200 rounded-2xl overflow-hidden hover:shadow-lg hover:border-indigo-400 transition-all duration-200"
                >
                    <!-- Featured badge -->
                    <div class="absolute top-2 left-2 z-10 bg-indigo-600 text-white text-xs font-bold px-2.5 py-0.5 rounded-full shadow">
                        ⭐ Featured
                    </div>

                    <!-- Admin unfeature button -->
                    <button
                        v-if="$page.props.auth?.user?.is_super_admin"
                        @click.prevent="toggleFeatured(c.slug)"
                        class="absolute top-2 right-2 z-10 bg-white text-red-600 text-xs font-semibold px-2 py-0.5 rounded-full shadow border border-red-200 hover:bg-red-50"
                    >
                        Remove
                    </button>

                    <div class="relative h-28 overflow-hidden">
                        <img v-if="c.cover_image" :src="c.cover_image" :alt="c.name" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                        <div v-else class="w-full h-full bg-linear-to-br from-indigo-400 to-purple-500" />
                    </div>
                    <div class="p-4">
                        <p class="font-bold text-gray-900 text-sm truncate">{{ c.name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ c.description ?? 'A great community.' }}</p>
                        <div class="flex items-center justify-between mt-3">
                            <span class="text-xs text-gray-400">{{ c.members_count }} members</span>
                            <span class="text-xs font-semibold" :class="c.price > 0 ? 'text-amber-600' : 'text-green-600'">
                                {{ c.price > 0 ? `₱${Number(c.price).toLocaleString()}${c.billing_type === 'one_time' ? '' : '/mo'}` : 'Free' }}
                            </span>
                        </div>
                    </div>
                </Link>
            </div>
        </div>

        <!-- Grid -->
        <div v-if="filteredCommunities.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <Link
                v-for="(community, index) in filteredCommunities"
                :key="community.id"
                :href="`/communities/${community.slug}`"
                class="group bg-white border border-gray-200 rounded-2xl overflow-hidden hover:shadow-lg hover:border-indigo-200 transition-all duration-200 flex flex-col"
            >
                <!-- Cover image / gradient -->
                <div class="relative h-40 overflow-hidden">
                    <img
                        v-if="community.cover_image"
                        :src="community.cover_image"
                        :alt="community.name"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    />
                    <div
                        v-else
                        class="w-full h-full"
                        :class="coverGradient(index)"
                    >
                        <!-- gradient fallback -->
                    </div>

                    <!-- Rank badge -->
                    <div class="absolute top-2 left-2">
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-black/40 text-white backdrop-blur-sm">
                            #{{ index + 1 }}
                        </span>
                    </div>

                    <!-- Milestone plaque badge -->
                    <div v-if="getMilestone(community.members_count)" class="absolute top-2 right-2">
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-black/50 text-white backdrop-blur-sm flex items-center gap-1">
                            {{ getMilestone(community.members_count).icon }} {{ getMilestone(community.members_count).label }}
                        </span>
                    </div>

                </div>

                <!-- Card body -->
                <div class="p-4 flex flex-col flex-1">
                    <!-- Logo + name row -->
                    <div class="flex items-center gap-3 -mt-6 mb-2">
                        <div class="w-10 h-10 rounded-xl shrink-0 overflow-hidden border-2 border-white bg-white flex items-center justify-center font-bold text-indigo-600 text-base" style="box-shadow: 0 2px 8px rgba(0,0,0,0.35), 0 0 0 1px rgba(255,255,255,0.6);">
                            <img v-if="community.avatar" :src="community.avatar" :alt="community.name" class="w-full h-full object-cover" />
                            <span v-else>{{ community.name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <h2 class="font-bold text-gray-900 text-sm leading-tight truncate group-hover:text-indigo-600 transition-colors">
                            {{ community.name }}
                        </h2>
                    </div>

                    <p class="text-xs text-gray-500 line-clamp-2 leading-relaxed mb-2 h-10">
                        {{ community.description ?? '' }}
                    </p>

                    <!-- Category badge -->
                    <span v-if="community.category" class="self-start mb-2 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 text-indigo-600">
                        {{ community.category }}
                    </span>

                    <!-- Footer: members left, price right -->
                    <div class="flex items-center justify-between mt-auto pt-2">
                        <span class="text-xs text-gray-500 font-medium">{{ formatCount(community.members_count) }} {{ community.members_count === 1 ? 'Member' : 'Members' }}</span>
                        <span class="text-xs font-semibold" :class="community.price > 0 ? 'text-amber-600' : 'text-green-600'">
                            {{ community.price > 0 ? `₱${Number(community.price).toLocaleString()}/mo` : 'Free' }}
                        </span>
                    </div>
                </div>
            </Link>
        </div>

        <!-- Empty state -->
        <div v-else class="text-center py-20">
            <div class="w-16 h-16 rounded-2xl bg-indigo-50 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-1">No communities found</h3>
            <p class="text-sm text-gray-500 mb-6">
                {{ search ? 'Try a different search term.' : 'Be the first to create one.' }}
            </p>
            <button
                v-if="$page.props.auth?.user && !search"
                @click="openCreateModal()"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
            >
                Create community
            </button>
        </div>

        <!-- Pagination -->
        <div v-if="communities.last_page > 1" class="mt-8 flex justify-center gap-2">
            <Link
                v-for="link in communities.links"
                :key="link.label"
                :href="link.url ?? ''"
                v-html="link.label"
                class="px-3 py-1.5 text-sm rounded-lg border transition-colors"
                :class="link.active
                    ? 'bg-indigo-600 text-white border-indigo-600'
                    : link.url
                        ? 'border-gray-200 text-gray-600 hover:border-indigo-300'
                        : 'border-gray-100 text-gray-300 cursor-default'"
            />
        </div>

    </AppLayout>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCreateModal } from '@/composables/useCreateModal';

const props = defineProps({
    communities: Object,
    filters:     Object,
    featured:    { type: Array, default: () => [] },
});

const { openCreateModal } = useCreateModal();

function toggleFeatured(communityId) {
    router.post(`/admin/communities/${communityId}/toggle-featured`, {}, { preserveScroll: true });
}

const search         = ref(props.filters?.search ?? '');
const activeCategory = ref(props.filters?.category ?? 'All');
const activeSort     = ref(props.filters?.sort ?? 'latest');

const categories = ['All', 'Tech', 'Business', 'Design', 'Health', 'Education', 'Finance', 'Other'];

const gradients = [
    'bg-linear-to-br from-indigo-400 to-purple-600',
    'bg-linear-to-br from-pink-400 to-rose-600',
    'bg-linear-to-br from-amber-400 to-orange-500',
    'bg-linear-to-br from-teal-400 to-cyan-600',
    'bg-linear-to-br from-green-400 to-emerald-600',
    'bg-linear-to-br from-sky-400 to-blue-600',
    'bg-linear-to-br from-violet-400 to-purple-700',
    'bg-linear-to-br from-red-400 to-pink-600',
];

function coverGradient(index) {
    return gradients[index % gradients.length];
}

function formatCount(n) {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
    return n.toString();
}

function getMilestone(count) {
    if (count >= 100_000)   return { icon: '🌟', label: '100K Plaque' };
    if (count >= 50_000)    return { icon: '🏆', label: 'Platinum' };
    if (count >= 10_000)    return { icon: '💎', label: 'Diamond' };
    if (count >= 1_000)     return { icon: '🥇', label: 'Gold' };
    if (count >= 500)       return { icon: '🥈', label: 'Silver' };
    if (count >= 100)       return { icon: '🥉', label: 'Bronze' };
    return null;
}

let searchTimer = null;

function applyFilters() {
    router.get('/communities', {
        search:   search.value || undefined,
        category: activeCategory.value !== 'All' ? activeCategory.value : undefined,
        sort:     activeSort.value !== 'latest' ? activeSort.value : undefined,
    }, { preserveState: 'errors', replace: true });
}

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
});

watch(activeCategory, () => { clearTimeout(searchTimer); applyFilters(); });
watch(activeSort,     () => { clearTimeout(searchTimer); applyFilters(); });

const filteredCommunities = computed(() => props.communities.data);
</script>
