<template>
    <AppLayout :title="`${community.name} — Calendar`" :community="community">
        <CommunityTabs :community="community" active-tab="calendar" />

        <div class="flex items-center justify-between mb-6">
            <!-- Month nav -->
            <div class="flex items-center gap-3">
                <button @click="prevMonth" class="p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div class="text-center">
                    <h2 class="text-xl font-bold text-gray-900">{{ monthLabel }}</h2>
                    <p class="text-xs text-gray-400 mt-0.5">{{ currentTimeLabel }}</p>
                </div>
                <button @click="nextMonth" class="p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <button @click="goToday" class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors">
                    Today
                </button>
            </div>

            <!-- Add event (owner only) -->
            <button
                v-if="isOwner"
                @click="openCreateModal"
                class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Event
            </button>
        </div>

        <!-- Calendar grid -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <!-- Day headers -->
            <div class="grid grid-cols-7 border-b border-gray-100">
                <div
                    v-for="day in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']"
                    :key="day"
                    class="py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide"
                >{{ day }}</div>
            </div>

            <!-- Weeks -->
            <div class="grid grid-cols-7 divide-x divide-gray-100">
                <div
                    v-for="(cell, i) in calendarCells"
                    :key="i"
                    class="min-h-28 p-2 border-b border-gray-100 relative"
                    :class="!cell.currentMonth ? 'bg-gray-50' : ''"
                >
                    <!-- Date number -->
                    <span
                        class="inline-flex items-center justify-center w-7 h-7 rounded-full text-sm font-medium mb-1"
                        :class="cell.isToday
                            ? 'bg-indigo-600 text-white'
                            : cell.currentMonth ? 'text-gray-700' : 'text-gray-300'"
                    >{{ cell.day }}</span>

                    <!-- Events on this day -->
                    <div class="space-y-1">
                        <button
                            v-for="ev in cell.events"
                            :key="ev.id"
                            @click="openEvent(ev)"
                            class="w-full text-left px-2 py-1 rounded-md text-xs font-medium truncate transition-colors"
                            :class="{
                                'bg-indigo-50 text-indigo-700 hover:bg-indigo-100': ev.visibility === 'public',
                                'bg-green-50 text-green-700 hover:bg-green-100':   ev.visibility === 'free',
                                'bg-amber-50 text-amber-700 hover:bg-amber-100':   ev.visibility === 'paid',
                            }"
                        >
                            <span class="mr-0.5">{{ ev.visibility === 'paid' ? '🔒' : ev.visibility === 'free' ? '🟢' : '' }}</span>
                            {{ formatTime(ev.start_at) }} · {{ ev.title }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Event detail modal ── -->
        <Teleport to="body">
            <div
                v-if="selectedEvent"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="selectedEvent = null"
            >
                <div class="absolute inset-0 bg-black/50" @click="selectedEvent = null" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                    <!-- Cover image -->
                    <div
                        v-if="selectedEvent.cover_image"
                        class="w-full h-48 bg-gray-900 overflow-hidden"
                    >
                        <img :src="selectedEvent.cover_image" class="w-full h-full object-cover" />
                    </div>
                    <div v-else class="w-full h-24 bg-linear-to-br from-indigo-600 to-purple-600" />

                    <!-- Content -->
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">
                            <span v-if="selectedEvent.visibility === 'paid'">🔒 </span>
                            <span v-else-if="selectedEvent.visibility === 'free'">🟢 </span>
                            {{ selectedEvent.title }}
                        </h3>

                        <!-- Date/time -->
                        <div class="flex items-start gap-3 mb-3">
                            <span class="text-xl mt-0.5">📅</span>
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ formatEventDate(selectedEvent) }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ selectedEvent.timezone }}</p>
                            </div>
                        </div>

                        <!-- URL -->
                        <div v-if="selectedEvent.url" class="flex items-center gap-3 mb-3">
                            <span class="text-xl">🔗</span>
                            <a
                                :href="selectedEvent.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm text-indigo-600 hover:underline truncate"
                            >{{ selectedEvent.url }}</a>
                        </div>

                        <!-- Description -->
                        <p v-if="selectedEvent.description" class="text-sm text-gray-600 mb-5 leading-relaxed">
                            {{ selectedEvent.description }}
                        </p>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <button
                                @click="addToCalendar(selectedEvent)"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Add to Calendar
                            </button>
                            <div v-if="isOwner" class="flex gap-2">
                                <button @click="openEditModal(selectedEvent)" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50 transition-colors">Edit</button>
                                <button @click="confirmDelete(selectedEvent)" class="px-3 py-2.5 border border-red-100 text-red-500 rounded-xl text-sm hover:bg-red-50 transition-colors">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Create / Edit event modal ── -->
        <Teleport to="body">
            <div
                v-if="showForm"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="showForm = false"
            >
                <div class="absolute inset-0 bg-black/50" @click="showForm = false" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900">{{ editingEvent ? 'Edit Event' : 'New Event' }}</h3>
                        <button @click="showForm = false" class="text-gray-400 hover:text-gray-600">✕</button>
                    </div>

                    <form @submit.prevent="submitForm" class="p-6 space-y-4">
                        <!-- Title -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                            <input v-model="form.title" type="text" required
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>

                        <!-- Start / End -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start *</label>
                                <input
                                    v-model="form.start_at"
                                    type="datetime-local"
                                    required
                                    :min="nowLocal"
                                    @change="onStartChange"
                                    class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    End
                                    <span v-if="!form.start_at" class="text-gray-400 font-normal text-xs">(set start first)</span>
                                </label>
                                <input
                                    v-model="form.end_at"
                                    type="datetime-local"
                                    :min="form.start_at || nowLocal"
                                    :disabled="!form.start_at"
                                    class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed"
                                />
                            </div>
                        </div>

                        <!-- Timezone -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
                            <select v-model="form.timezone"
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option v-for="tz in commonTimezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
                            </select>
                        </div>

                        <!-- URL -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Link / Meeting URL</label>
                            <input v-model="form.url" type="url" placeholder="https://"
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea v-model="form.description" rows="3"
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
                        </div>

                        <!-- Cover image -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cover Image</label>
                            <div
                                ref="coverDropRef"
                                class="border-2 border-dashed rounded-xl p-4 text-center transition-colors cursor-pointer"
                                :class="coverDragging ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300'"
                                @click="$refs.coverFileInput.click()"
                            >
                                <p class="text-sm text-gray-500">{{ coverDragging ? 'Drop image here' : (coverFile ? coverFile.name : 'Click or drag & drop cover image') }}</p>
                                <input ref="coverFileInput" type="file" accept="image/*" class="hidden" @change="onCoverChange" />
                            </div>
                        </div>

                        <!-- Visibility -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Visibility</label>
                            <div class="flex gap-2">
                                <label v-for="opt in visibilityOptions" :key="opt.value"
                                    :class="['flex-1 cursor-pointer rounded-xl border-2 p-2.5 text-center transition-all',
                                        form.visibility === opt.value ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                                    <input type="radio" :value="opt.value" v-model="form.visibility" class="sr-only" />
                                    <div class="text-base mb-0.5">{{ opt.icon }}</div>
                                    <div class="text-xs font-semibold text-gray-800">{{ opt.label }}</div>
                                    <div class="text-[10px] text-gray-400 leading-tight mt-0.5">{{ opt.hint }}</div>
                                </label>
                            </div>
                        </div>

                        <p v-if="formError" class="text-sm text-red-500">{{ formError }}</p>

                        <div class="flex items-center gap-3 pt-2">
                            <button type="submit" :disabled="submitting"
                                class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                                {{ submitting ? 'Saving…' : (editingEvent ? 'Save Changes' : 'Create Event') }}
                            </button>
                            <button type="button" @click="showForm = false"
                                class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
        <ConfirmModal :show="confirmShow" :title="confirmTitle" :message="confirmMessage" :confirm-label="confirmLabel" :destructive="confirmDestructive" @confirm="onConfirm" @cancel="onCancel" />
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import CommunityTabs from '@/Components/CommunityTabs.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { useDropzone } from '@/composables/useDropzone'
import { useConfirm } from '@/composables/useConfirm'

const props = defineProps({
    community:    Object,
    membership:   Object,
    events:       Array,
    year:         Number,
    month:        Number,
    isOwner:      Boolean,
    userTimezone: String,
})

const { show: confirmShow, title: confirmTitle, message: confirmMessage, confirmLabel, destructive: confirmDestructive, ask, onConfirm, onCancel } = useConfirm();

// ── Calendar navigation ───────────────────────────────────────────────────────
const curYear  = ref(props.year)
const curMonth = ref(props.month)  // 1-indexed

function navigate(year, month) {
    router.get(`/communities/${props.community.slug}/calendar`, { year, month }, { preserveScroll: true })
}
function prevMonth() {
    const d = new Date(curYear.value, curMonth.value - 2, 1)
    navigate(d.getFullYear(), d.getMonth() + 1)
}
function nextMonth() {
    const d = new Date(curYear.value, curMonth.value, 1)
    navigate(d.getFullYear(), d.getMonth() + 1)
}
function goToday() {
    const now = new Date()
    navigate(now.getFullYear(), now.getMonth() + 1)
}

const monthLabel = computed(() => {
    return new Date(curYear.value, curMonth.value - 1, 1)
        .toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

const currentTimeLabel = computed(() => {
    const tz = props.userTimezone || Intl.DateTimeFormat().resolvedOptions().timeZone
    return new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', timeZone: tz, timeZoneName: 'short' })
})

// ── Build calendar grid ───────────────────────────────────────────────────────
const calendarCells = computed(() => {
    const year  = curYear.value
    const month = curMonth.value  // 1-indexed

    const firstDay = new Date(year, month - 1, 1)
    // Monday = 0 offset (Mon-Sun grid)
    let startOffset = firstDay.getDay() - 1
    if (startOffset < 0) startOffset = 6

    const daysInMonth = new Date(year, month, 0).getDate()
    const daysInPrev  = new Date(year, month - 1, 0).getDate()

    const tz = props.userTimezone || Intl.DateTimeFormat().resolvedOptions().timeZone
    const todayParts = new Date().toLocaleDateString('en-CA', { timeZone: tz }).split('-')
    const todayKey = `${+todayParts[0]}-${+todayParts[1]}-${+todayParts[2]}`

    const cells = []

    // Previous month days
    for (let i = startOffset - 1; i >= 0; i--) {
        cells.push({ day: daysInPrev - i, currentMonth: false, date: null, events: [], isToday: false })
    }

    // Current month
    for (let d = 1; d <= daysInMonth; d++) {
        const key   = `${year}-${month}-${d}`
        const dayEvents = props.events.filter(ev => {
            const tz = props.userTimezone || Intl.DateTimeFormat().resolvedOptions().timeZone
            const parts = new Date(ev.start_at).toLocaleDateString('en-CA', { timeZone: tz }).split('-')
            return +parts[0] === year && +parts[1] === month && +parts[2] === d
        })
        cells.push({
            day: d,
            currentMonth: true,
            date: new Date(year, month - 1, d),
            events: dayEvents,
            isToday: key === todayKey,
        })
    }

    // Fill remaining
    const remaining = (7 - (cells.length % 7)) % 7
    for (let d = 1; d <= remaining; d++) {
        cells.push({ day: d, currentMonth: false, date: null, events: [], isToday: false })
    }

    return cells
})

// ── Formatting ────────────────────────────────────────────────────────────────
function formatTime(isoString) {
    const tz = props.userTimezone || Intl.DateTimeFormat().resolvedOptions().timeZone
    return new Date(isoString).toLocaleTimeString('en-US', {
        hour: 'numeric', minute: '2-digit', hour12: true, timeZone: tz,
    })
}

function formatEventDate(ev) {
    const tz = props.userTimezone || Intl.DateTimeFormat().resolvedOptions().timeZone
    const start = new Date(ev.start_at)
    const opts = { weekday: 'long', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true, timeZone: tz }
    let label = start.toLocaleString('en-US', opts)
    if (ev.end_at) {
        const end = new Date(ev.end_at)
        label += ' – ' + end.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true, timeZone: tz })
    }
    return label
}

// ── Event detail modal ────────────────────────────────────────────────────────
const selectedEvent = ref(null)

function openEvent(ev) { selectedEvent.value = ev }

// ── Add to calendar (.ics) ────────────────────────────────────────────────────
function addToCalendar(ev) {
    const fmt = (d) => new Date(d).toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z'
    const end = ev.end_at ? fmt(ev.end_at) : fmt(new Date(new Date(ev.start_at).getTime() + 3600000))

    const ics = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'BEGIN:VEVENT',
        `DTSTART:${fmt(ev.start_at)}`,
        `DTEND:${end}`,
        `SUMMARY:${ev.title}`,
        ev.description ? `DESCRIPTION:${ev.description.replace(/\n/g, '\\n')}` : '',
        ev.url ? `URL:${ev.url}` : '',
        'END:VEVENT',
        'END:VCALENDAR',
    ].filter(Boolean).join('\r\n')

    const blob = new Blob([ics], { type: 'text/calendar' })
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    a.href     = url
    a.download = `${ev.title.replace(/\s+/g, '-')}.ics`
    a.click()
    URL.revokeObjectURL(url)
}

// ── Create / Edit form ────────────────────────────────────────────────────────
const showForm    = ref(false)
const editingEvent = ref(null)
const submitting  = ref(false)
const formError   = ref('')
const coverFile   = ref(null)

// Returns current datetime in datetime-local format (YYYY-MM-DDTHH:MM) in user's timezone
const nowLocal = computed(() => {
    const tz = props.userTimezone || Intl.DateTimeFormat().resolvedOptions().timeZone
    return new Date().toLocaleString('sv-SE', { timeZone: tz }).replace(' ', 'T').slice(0, 16)
})

// Clear end if it's now before the new start
function onStartChange() {
    if (form.value.end_at && form.value.end_at <= form.value.start_at) {
        form.value.end_at = ''
    }
}

const visibilityOptions = [
    { value: 'public', label: 'Public',  icon: '🌐', hint: 'Everyone can see' },
    { value: 'free',   label: 'Free',    icon: '🟢', hint: 'Free + paid members' },
    { value: 'paid',   label: 'Paid',    icon: '🔒', hint: 'Paid members only' },
]

const defaultForm = () => ({
    title:       '',
    description: '',
    start_at:    '',
    end_at:      '',
    timezone:    props.userTimezone || 'Asia/Manila',
    url:         '',
    visibility:  'public',
})

const form = ref(defaultForm())

function openCreateModal() {
    editingEvent.value = null
    form.value = defaultForm()
    coverFile.value = null
    formError.value = ''
    showForm.value = true
}

function openEditModal(ev) {
    editingEvent.value = ev
    const toLocal = (iso, tz) => {
        if (!iso) return ''
        const d = new Date(iso)
        const parts = d.toLocaleString('sv-SE', { timeZone: tz || props.userTimezone || 'UTC' }).replace(' ', 'T')
        return parts.slice(0, 16)
    }
    form.value = {
        title:       ev.title,
        description: ev.description || '',
        start_at:    toLocal(ev.start_at, ev.timezone),
        end_at:      toLocal(ev.end_at, ev.timezone),
        timezone:    ev.timezone,
        url:         ev.url || '',
        visibility:  ev.visibility ?? 'public',
    }
    coverFile.value = null
    formError.value = ''
    selectedEvent.value = null
    showForm.value = true
}

function onCoverChange(e) { const f = e instanceof File ? e : e.target.files[0]; coverFile.value = f || null }

const coverDropRef = ref(null);
const { isDragging: coverDragging } = useDropzone(coverDropRef, files => onCoverChange(files[0]), { accept: 'image/*' });

async function submitForm() {
    submitting.value = true
    formError.value  = ''

    const data = new FormData()
    Object.entries(form.value).forEach(([k, v]) => data.append(k, v === true ? '1' : v === false ? '0' : v ?? ''))
    if (coverFile.value) data.append('cover_image', coverFile.value)

    try {
        const url = editingEvent.value
            ? `/communities/${props.community.slug}/events/${editingEvent.value.id}`
            : `/communities/${props.community.slug}/events`

        await axios.post(url, data)
        showForm.value = false
        router.reload({ only: ['events'] })
    } catch (e) {
        const res = e.response?.data
        const firstError = res?.errors ? Object.values(res.errors).flat()[0] : null
        formError.value = firstError || res?.message || 'Something went wrong.'
    } finally {
        submitting.value = false
    }
}

async function confirmDelete(ev) {
    if (!await ask({ title: 'Delete Event', message: `Delete "${ev.title}"?`, confirmLabel: 'Delete', destructive: true })) return
    await axios.delete(`/communities/${props.community.slug}/events/${ev.id}`)
    selectedEvent.value = null
    router.reload({ only: ['events'] })
}

// ── Timezones list ────────────────────────────────────────────────────────────
const commonTimezones = [
    { value: 'Asia/Manila',       label: 'Asia/Manila (PHT)' },
    { value: 'Asia/Singapore',    label: 'Asia/Singapore (SGT)' },
    { value: 'Asia/Tokyo',        label: 'Asia/Tokyo (JST)' },
    { value: 'Asia/Jakarta',      label: 'Asia/Jakarta (WIB)' },
    { value: 'Asia/Kuala_Lumpur', label: 'Asia/Kuala Lumpur (MYT)' },
    { value: 'Asia/Hong_Kong',    label: 'Asia/Hong Kong (HKT)' },
    { value: 'Asia/Dubai',        label: 'Asia/Dubai (GST)' },
    { value: 'Asia/Kolkata',      label: 'Asia/Kolkata (IST)' },
    { value: 'Europe/London',     label: 'Europe/London (GMT/BST)' },
    { value: 'Europe/Paris',      label: 'Europe/Paris (CET/CEST)' },
    { value: 'America/New_York',  label: 'America/New York (ET)' },
    { value: 'America/Chicago',   label: 'America/Chicago (CT)' },
    { value: 'America/Denver',    label: 'America/Denver (MT)' },
    { value: 'America/Los_Angeles', label: 'America/Los Angeles (PT)' },
    { value: 'America/Sao_Paulo', label: 'America/São Paulo (BRT)' },
    { value: 'Australia/Sydney',  label: 'Australia/Sydney (AEST)' },
    { value: 'Pacific/Auckland',  label: 'Pacific/Auckland (NZST)' },
    { value: 'UTC',               label: 'UTC' },
]
</script>
