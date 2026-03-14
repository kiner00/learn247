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

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <!-- Left: Editor -->
            <div class="space-y-4">
                <!-- Mode toggle -->
                <div class="flex items-center gap-2 bg-gray-100 rounded-xl p-1 w-fit">
                    <button
                        type="button"
                        @click="mode = 'simple'"
                        :class="mode === 'simple'
                            ? 'bg-white text-gray-900 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700'"
                        class="px-4 py-1.5 rounded-lg text-sm font-medium transition-all"
                    >
                        ✏️ Simple
                    </button>
                    <button
                        type="button"
                        @click="switchToAdvanced"
                        :class="mode === 'advanced'
                            ? 'bg-white text-gray-900 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700'"
                        class="px-4 py-1.5 rounded-lg text-sm font-medium transition-all"
                    >
                        &lt;/&gt; HTML
                    </button>
                </div>

                <!-- Subject -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Subject Line</label>
                    <input
                        v-model="form.subject"
                        type="text"
                        @input="refreshPreview"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="errors.subject" class="mt-1 text-xs text-red-500">{{ errors.subject }}</p>
                </div>

                <!-- ── Simple mode ── -->
                <template v-if="mode === 'simple'">
                    <!-- Badge -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Badge Label <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input
                            v-model="simple.badge"
                            type="text"
                            placeholder="e.g. PAYMENT CONFIRMED"
                            @input="syncHtmlFromSimple"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>

                    <!-- Heading -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Heading</label>
                        <input
                            v-model="simple.heading"
                            type="text"
                            @input="syncHtmlFromSimple"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <p class="text-xs text-gray-400 mt-1">Use <code class="bg-gray-100 px-1 rounded">&#123;&#123;variable&#125;&#125;</code> for dynamic values</p>
                    </div>

                    <!-- Body -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Body</label>
                        <div
                            class="border border-gray-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500"
                        >
                            <!-- TipTap toolbar -->
                            <div v-if="editor" class="flex items-center gap-0.5 px-2 py-1.5 border-b border-gray-100 bg-gray-50">
                                <button type="button" @click="editor.chain().focus().toggleBold().run()"
                                    :class="editor.isActive('bold') ? 'bg-gray-200' : 'hover:bg-gray-100'"
                                    class="px-2 py-1 rounded text-xs font-bold text-gray-700 transition-colors">B</button>
                                <button type="button" @click="editor.chain().focus().toggleItalic().run()"
                                    :class="editor.isActive('italic') ? 'bg-gray-200' : 'hover:bg-gray-100'"
                                    class="px-2 py-1 rounded text-xs italic text-gray-700 transition-colors">I</button>
                                <button type="button" @click="editor.chain().focus().toggleBulletList().run()"
                                    :class="editor.isActive('bulletList') ? 'bg-gray-200' : 'hover:bg-gray-100'"
                                    class="px-2 py-1 rounded text-xs text-gray-700 transition-colors">• List</button>
                                <div class="w-px h-4 bg-gray-200 mx-1"></div>
                                <span class="text-xs text-gray-400 ml-1">Variables: </span>
                                <button
                                    v-for="(desc, varName) in template.variables"
                                    :key="varName"
                                    type="button"
                                    @click="insertVarIntoEditor(varName)"
                                    class="ml-1 px-2 py-1 rounded text-xs font-mono text-indigo-600 hover:bg-indigo-50 transition-colors"
                                    :title="desc"
                                >{{ wrap(varName) }}</button>
                            </div>
                            <editor-content :editor="editor" class="prose prose-sm max-w-none p-3 min-h-40 text-sm" />
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Supports bold, italic, bullet lists, and variables.</p>
                    </div>

                    <!-- Button -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Call to Action Button <span class="text-gray-400 font-normal">(optional)</span></label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">Button Label</label>
                                <input
                                    v-model="simple.buttonLabel"
                                    type="text"
                                    placeholder="e.g. Log In Now"
                                    @input="syncHtmlFromSimple"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">Link URL / Variable</label>
                                <input
                                    v-model="simple.buttonUrl"
                                    type="text"
                                    placeholder="e.g. {{login_url}}"
                                    @input="syncHtmlFromSimple"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Footer Text <span class="text-gray-400 font-normal">(optional)</span></label>
                        <textarea
                            v-model="simple.footer"
                            rows="2"
                            @input="syncHtmlFromSimple"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                        />
                    </div>
                </template>

                <!-- ── Advanced HTML mode ── -->
                <template v-else>
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">HTML Body</label>
                        <textarea
                            v-model="form.html_body"
                            rows="28"
                            @input="refreshPreview"
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl text-xs font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"
                        />
                        <p v-if="errors.html_body" class="mt-1 text-xs text-red-500">{{ errors.html_body }}</p>
                        <!-- Variables hint -->
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="(desc, varName) in template.variables"
                                :key="varName"
                                type="button"
                                @click="appendVar(varName)"
                                class="px-2 py-1 rounded-lg border border-gray-100 hover:border-indigo-300 hover:bg-indigo-50 transition-colors"
                                :title="desc"
                            >
                                <span class="text-xs font-mono text-indigo-600">{{ wrap(varName) }}</span>
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Save -->
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

            <!-- Right: Live preview -->
            <div class="xl:sticky xl:top-6 xl:self-start">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                        <span class="text-sm font-semibold text-gray-700">Live Preview</span>
                        <span class="text-xs text-gray-400">Variables shown as [labels]</span>
                    </div>
                    <iframe
                        ref="previewFrame"
                        :srcdoc="previewHtml"
                        class="w-full"
                        style="height: 580px"
                        sandbox="allow-same-origin"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import axios from 'axios'

const props = defineProps({ template: Object })

// ── State ─────────────────────────────────────────────────────────────────────
const mode    = ref('simple')
const saving  = ref(false)
const saved   = ref(false)
const errors  = ref({})
const previewFrame = ref(null)

const form = ref({
    subject:   props.template.subject,
    html_body: props.template.html_body,
})

// Simple mode fields (parsed from the stored HTML on mount)
const simple = ref({
    badge:       '',
    heading:     '',
    buttonLabel: '',
    buttonUrl:   '',
    footer:      '',
})

// ── TipTap editor ─────────────────────────────────────────────────────────────
const editor = useEditor({
    extensions: [StarterKit],
    content: '',
    onUpdate() { syncHtmlFromSimple() },
})

// ── Parse stored HTML → simple fields on mount ────────────────────────────────
onMounted(() => {
    parseSimpleFromHtml(form.value.html_body)
    refreshPreview()
})

onBeforeUnmount(() => editor.value?.destroy())

function parseSimpleFromHtml(html) {
    try {
        const parser = new DOMParser()
        const doc    = parser.parseFromString(html, 'text/html')

        simple.value.badge       = doc.querySelector('.badge')?.textContent?.trim() ?? ''
        simple.value.heading     = doc.querySelector('h2')?.innerHTML?.trim() ?? ''
        simple.value.buttonLabel = doc.querySelector('.btn')?.textContent?.trim() ?? ''
        simple.value.buttonUrl   = doc.querySelector('.btn')?.getAttribute('href') ?? ''
        simple.value.footer      = doc.querySelector('.footer')?.innerHTML?.trim() ?? ''

        // Body: all <p> tags that are direct children of .card, excluding the login-email line
        const card = doc.querySelector('.card')
        const bodyParts = []
        if (card) {
            card.childNodes.forEach(node => {
                if (node.nodeName === 'P' && !node.style?.fontSize) {
                    bodyParts.push(node.outerHTML)
                }
            })
        }
        editor.value?.commands.setContent(bodyParts.join('') || '')
    } catch {
        // If parsing fails, stay in simple mode with empty fields
    }
}

// ── Rebuild full HTML from simple fields ─────────────────────────────────────
function buildHtmlFromSimple() {
    const s    = simple.value
    const body = editor.value ? editor.value.getHTML() : ''

    const badgeHtml  = s.badge
        ? `<div class="badge">${s.badge}</div>\n`
        : ''
    const headingHtml = s.heading
        ? `<h2 style="margin-top:0">${s.heading}</h2>\n`
        : ''
    const btnHtml = s.buttonLabel
        ? `<a href="${s.buttonUrl}" class="btn">${s.buttonLabel}</a>\n`
        : ''
    const footerHtml = s.footer
        ? `<div class="footer">${s.footer}</div>\n`
        : ''

    return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
body{font-family:sans-serif;color:#1f2937;background:#f9fafb;margin:0;padding:40px 0}
.card{background:white;max-width:540px;margin:0 auto;border-radius:12px;padding:40px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.badge{display:inline-block;background:#ecfdf5;color:#059669;font-size:12px;font-weight:700;padding:4px 10px;border-radius:99px;margin-bottom:16px;letter-spacing:.5px}
.btn{display:inline-block;background:#4F46E5;color:white;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:600;margin:20px 0}
.footer{font-size:13px;color:#6b7280;margin-top:24px;border-top:1px solid #f3f4f6;padding-top:16px}
p{line-height:1.7}
ul{padding-left:20px;line-height:1.8}
</style>
</head>
<body>
<div class="card">
${badgeHtml}${headingHtml}${body}
${btnHtml}${footerHtml}
</div>
</body>
</html>`
}

function syncHtmlFromSimple() {
    form.value.html_body = buildHtmlFromSimple()
    refreshPreview()
}

// ── Switch to advanced mode ───────────────────────────────────────────────────
function switchToAdvanced() {
    // Sync latest simple → HTML before switching
    if (mode.value === 'simple') {
        syncHtmlFromSimple()
    }
    mode.value = 'advanced'
}

// ── Live preview (client-side var replacement) ────────────────────────────────
const previewHtml = computed(() => {
    let html = form.value.html_body
    const vars = props.template.variables ?? {}
    Object.keys(vars).forEach(v => {
        html = html.replaceAll(`{{${v}}}`, `<span style="background:#e0e7ff;color:#4338ca;padding:0 3px;border-radius:3px;font-size:.85em">[${v}]</span>`)
    })
    return html
})

function refreshPreview() {
    // previewHtml is computed, nothing needed — it auto-updates
}

// ── Save ──────────────────────────────────────────────────────────────────────
async function save() {
    if (mode.value === 'simple') syncHtmlFromSimple()

    saving.value = true
    saved.value  = false
    errors.value = {}

    try {
        await axios.put(`/admin/email-templates/${props.template.key}`, form.value)
        saved.value = true
        setTimeout(() => (saved.value = false), 3000)
    } catch (e) {
        if (e.response?.status === 422) errors.value = e.response.data.errors ?? {}
    } finally {
        saving.value = false
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function wrap(varName) { return '{{' + varName + '}}' }

function insertVarIntoEditor(varName) {
    editor.value?.chain().focus().insertContent(`{{${varName}}}`).run()
}

function appendVar(varName) {
    form.value.html_body += `{{${varName}}}`
    refreshPreview()
}
</script>

<style>
/* TipTap content styles */
.ProseMirror { outline: none; }
.ProseMirror p { margin: 0.5rem 0; }
.ProseMirror ul { list-style: disc; padding-left: 1.25rem; }
</style>
