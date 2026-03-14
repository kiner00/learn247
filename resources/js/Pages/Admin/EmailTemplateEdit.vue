<template>
    <AppLayout :title="`Edit: ${template.name}`">
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-black text-gray-900">{{ template.name }}</h1>
                <p class="text-sm text-gray-400 mt-0.5 font-mono">{{ template.key }}</p>
            </div>
            <Link href="/admin/email-templates" class="text-sm text-indigo-600 hover:underline">← All Templates</Link>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <!-- Editor panel -->
            <div class="xl:col-span-2 space-y-5">
                <!-- Subject -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Subject Line</label>
                    <input
                        v-model="form.subject"
                        type="text"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Email subject..."
                    />
                    <p v-if="errors.subject" class="mt-1 text-xs text-red-500">{{ errors.subject }}</p>
                </div>

                <!-- HTML Body -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-semibold text-gray-700">HTML Body</label>
                        <button
                            type="button"
                            @click="togglePreview"
                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                        >
                            {{ showPreview ? 'Edit HTML' : 'Preview' }}
                        </button>
                    </div>

                    <div v-if="!showPreview">
                        <textarea
                            v-model="form.html_body"
                            rows="24"
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl text-xs font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"
                            placeholder="Paste or write HTML here..."
                        />
                        <p v-if="errors.html_body" class="mt-1 text-xs text-red-500">{{ errors.html_body }}</p>
                    </div>

                    <!-- Preview iframe -->
                    <div v-else class="border border-gray-200 rounded-xl overflow-hidden" style="height:520px">
                        <iframe
                            v-if="previewHtml"
                            :srcdoc="previewHtml"
                            class="w-full h-full"
                            sandbox="allow-same-origin"
                        />
                        <div v-else class="flex items-center justify-center h-full text-sm text-gray-400">
                            Loading preview...
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        :disabled="saving"
                        @click="save"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                    >
                        {{ saving ? 'Saving…' : 'Save Template' }}
                    </button>
                    <span v-if="saved" class="text-sm text-green-600 font-medium">✓ Saved</span>
                </div>
            </div>

            <!-- Variables sidebar -->
            <div class="space-y-5">
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Available Variables</h3>
                    <p class="text-xs text-gray-500 mb-4">Click to insert at cursor, or type them manually in the HTML.</p>

                    <div v-if="template.variables" class="space-y-2">
                        <button
                            v-for="(desc, varName) in template.variables"
                            :key="varName"
                            type="button"
                            @click="insertVariable(varName)"
                            class="w-full text-left px-3 py-2 rounded-lg border border-gray-100 hover:border-indigo-300 hover:bg-indigo-50 transition-colors"
                        >
                            <span class="text-xs font-mono text-indigo-600 block">{{ '{{' + varName + '}}' }}</span>
                            <span class="text-xs text-gray-400">{{ desc }}</span>
                        </button>
                    </div>
                    <p v-else class="text-xs text-gray-400 italic">No variables for this template.</p>
                </div>

                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4">
                    <p class="text-xs text-amber-800 font-semibold mb-1">Tips</p>
                    <ul class="text-xs text-amber-700 space-y-1 list-disc list-inside">
                        <li>Use <code class="bg-amber-100 px-1 rounded">{{'{{'}}variable{{'}}'}}</code> syntax for dynamic values</li>
                        <li>Write full HTML including <code class="bg-amber-100 px-1 rounded">&lt;style&gt;</code> tags</li>
                        <li>Use inline CSS for best email client compatibility</li>
                        <li>Preview shows variables replaced with placeholder labels</li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
    template: Object,
})

const form = ref({
    subject:   props.template.subject,
    html_body: props.template.html_body,
})
const errors   = ref({})
const saving   = ref(false)
const saved    = ref(false)
const showPreview = ref(false)
const previewHtml = ref('')
const textareaRef = ref(null)

async function save() {
    saving.value = true
    saved.value  = false
    errors.value = {}

    try {
        await axios.put(`/admin/email-templates/${props.template.key}`, form.value)
        saved.value = true
        setTimeout(() => (saved.value = false), 3000)
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {}
        }
    } finally {
        saving.value = false
    }
}

async function togglePreview() {
    if (!showPreview.value) {
        // fetch preview from server
        try {
            const res = await axios.post(`/admin/email-templates/${props.template.key}/preview`, {
                subject:   form.value.subject,
                html_body: form.value.html_body,
            })
            previewHtml.value = res.data
        } catch {
            previewHtml.value = '<p style="padding:20px;color:red">Failed to load preview.</p>'
        }
    }
    showPreview.value = !showPreview.value
}

function insertVariable(varName) {
    const placeholder = `{{${varName}}}`
    // Insert into html_body at end (textarea focus would be nicer but requires ref)
    const ta = document.querySelector('textarea')
    if (ta) {
        const start = ta.selectionStart
        const end   = ta.selectionEnd
        const val   = form.value.html_body
        form.value.html_body = val.slice(0, start) + placeholder + val.slice(end)
        // Restore cursor after next tick
        setTimeout(() => {
            ta.focus()
            ta.selectionStart = ta.selectionEnd = start + placeholder.length
        }, 0)
    } else {
        form.value.html_body += placeholder
    }
}
</script>
