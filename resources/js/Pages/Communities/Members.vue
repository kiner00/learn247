<template>
    <AppLayout :title="`${community.name} · Members`">
        <div class="flex items-center justify-between mb-6">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                    <Link
                        :href="`/communities/${community.slug}`"
                        class="hover:text-indigo-600 transition-colors"
                    >
                        {{ community.name }}
                    </Link>
                    <span>/</span>
                    <span>Members</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Members</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ members.total }} total</p>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Member</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Role</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Joined</th>
                        <th v-if="isAdmin" class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr
                        v-for="member in members.data"
                        :key="member.id"
                        class="hover:bg-gray-50 transition-colors"
                    >
                        <!-- Avatar + name -->
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-semibold text-indigo-600 shrink-0">
                                    {{ member.user?.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ member.user?.name }}</p>
                                    <p class="text-xs text-gray-400">{{ member.user?.email }}</p>
                                </div>
                            </div>
                        </td>

                        <!-- Role -->
                        <td class="px-5 py-3.5">
                            <!-- Admin can change role for non-owners -->
                            <select
                                v-if="isAdmin && member.user?.id !== community.owner_id"
                                :value="member.role"
                                @change="changeRole(member, $event.target.value)"
                                class="text-xs border border-gray-200 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                            >
                                <option value="member">Member</option>
                                <option value="moderator">Moderator</option>
                                <option value="admin">Admin</option>
                            </select>
                            <span
                                v-else
                                class="text-xs font-medium px-2.5 py-0.5 rounded-full"
                                :class="{
                                    'bg-indigo-100 text-indigo-700': member.role === 'admin',
                                    'bg-purple-100 text-purple-700': member.role === 'moderator',
                                    'bg-gray-100 text-gray-600':     member.role === 'member',
                                }"
                            >
                                {{ member.role }}
                            </span>
                        </td>

                        <!-- Joined date -->
                        <td class="px-5 py-3.5 text-gray-500 text-xs">
                            {{ formatDate(member.joined_at) }}
                        </td>

                        <!-- Remove action -->
                        <td v-if="isAdmin" class="px-5 py-3.5 text-right">
                            <button
                                v-if="member.user?.id !== community.owner_id"
                                @click="removeMember(member)"
                                class="text-xs text-gray-400 hover:text-red-500 transition-colors"
                            >
                                Remove
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!members.data.length" class="text-center py-12">
                <p class="text-sm text-gray-500">No members found.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="members.last_page > 1" class="mt-6 flex justify-center gap-2">
            <Link
                v-for="link in members.links"
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
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    community: Object,
    members:   Object,
});

const page = usePage();

const isAdmin = computed(() => {
    const userId = page.props.auth?.user?.id;
    const me = props.members.data.find((m) => m.user?.id === userId);
    return me?.role === 'admin';
});

function changeRole(member, role) {
    router.patch(
        `/communities/${props.community.slug}/members/${member.user.id}/role`,
        { role },
        { preserveScroll: true },
    );
}

function removeMember(member) {
    if (!confirm(`Remove ${member.user?.name} from the community?`)) return;
    router.delete(
        `/communities/${props.community.slug}/members/${member.user.id}`,
        { preserveScroll: true },
    );
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}
</script>
