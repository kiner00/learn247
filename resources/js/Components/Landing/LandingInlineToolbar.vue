<template>
    <!-- ── Floating inline format toolbar ── -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0 scale-95">
            <div v-if="visible"
                data-inline-toolbar
                tabindex="-1"
                @mousedown.prevent
                class="fixed z-[300] bg-gray-900 text-white rounded-xl shadow-2xl flex items-center gap-0.5 px-2 py-1.5"
                :style="{ top: position.top + 'px', left: position.left + 'px' }">
                <!-- Bold -->
                <button @click="execFmt('bold')"
                    :class="{'bg-white/20': fmtActive.bold}"
                    class="w-8 h-7 rounded font-bold text-sm hover:bg-white/20 active:bg-white/30 transition select-none">B</button>
                <!-- Italic -->
                <button @click="execFmt('italic')"
                    :class="{'bg-white/20': fmtActive.italic}"
                    class="w-8 h-7 rounded italic text-sm hover:bg-white/20 active:bg-white/30 transition select-none">I</button>
                <!-- Underline -->
                <button @click="execFmt('underline')"
                    :class="{'bg-white/20': fmtActive.underline}"
                    class="w-8 h-7 rounded underline text-sm hover:bg-white/20 active:bg-white/30 transition select-none">U</button>
                <div class="w-px h-4 bg-white/20 mx-1" />
                <!-- Font size -->
                <button @click="execFmt('fontSize', '5')" title="Larger" class="w-8 h-7 rounded text-xs hover:bg-white/20 transition font-bold select-none">A+</button>
                <button @click="execFmt('fontSize', '2')" title="Smaller" class="w-8 h-7 rounded text-xs hover:bg-white/20 transition select-none opacity-80">A-</button>
                <div class="w-px h-4 bg-white/20 mx-1" />
                <!-- Text color -->
                <label title="Text color" class="relative w-8 h-7 flex items-center justify-center rounded hover:bg-white/20 transition cursor-pointer select-none">
                    <span class="text-sm font-bold" :style="{ color: activeColor }">A</span>
                    <span class="absolute bottom-1 left-1.5 right-1.5 h-0.5 rounded" :style="{ background: activeColor }"></span>
                    <input type="color" v-model="localActiveColor" @input="execFmt('foreColor', localActiveColor)" @mousedown.stop class="absolute inset-0 opacity-0 w-full h-full cursor-pointer" />
                </label>
                <div class="w-px h-4 bg-white/20 mx-1" />
                <!-- Align -->
                <button @click="execFmt('justifyLeft')" title="Align left" class="w-7 h-7 rounded hover:bg-white/20 transition select-none text-xs">⬅</button>
                <button @click="execFmt('justifyCenter')" title="Center" class="w-7 h-7 rounded hover:bg-white/20 transition select-none text-xs">↔</button>
                <div class="w-px h-4 bg-white/20 mx-1" />
                <!-- Save hint -->
                <span class="text-white/40 text-xs px-1">Click away to save</span>
            </div>
        </Transition>
    </Teleport>

    <!-- ── INLINE COLOR POPOVER ── -->
    <Teleport to="body">
        <Transition enter-from-class="opacity-0 scale-95" enter-active-class="transition duration-150" leave-to-class="opacity-0 scale-95" leave-active-class="transition duration-100">
            <div v-if="colorPopover.visible" class="fixed z-[400]" :style="{ top: colorPopover.top + 'px', left: colorPopover.left + 'px' }">
                <div class="fixed inset-0" @click="$emit('closeColorPopover')" />
                <div class="relative bg-white rounded-xl shadow-2xl border border-gray-200 p-3 w-[240px] space-y-3">
                    <div v-for="field in colorPopover.fields" :key="field.path">
                        <label class="text-[10px] text-gray-500 font-semibold uppercase tracking-wide">{{ field.label }}</label>
                        <div class="flex items-center gap-2 mt-1">
                            <input type="color" :value="getColorValue(field.path) || field.fallback" @input="$emit('setColorValue', field.path, $event.target.value)" class="w-8 h-8 rounded cursor-pointer border border-gray-200 p-0.5" />
                            <input type="text" :value="getColorValue(field.path) || field.fallback" @input="$emit('setColorValue', field.path, $event.target.value)" :placeholder="field.fallback" class="flex-1 text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    visible: { type: Boolean, default: false },
    position: { type: Object, default: () => ({ top: 0, left: 0 }) },
    fmtActive: { type: Object, default: () => ({ bold: false, italic: false, underline: false }) },
    activeColor: { type: String, default: '#ffffff' },
    colorPopover: { type: Object, default: () => ({ visible: false, top: 0, left: 0, fields: [] }) },
    getColorValue: { type: Function, required: true },
});

const emit = defineEmits(['execFmt', 'update:activeColor', 'closeColorPopover', 'setColorValue']);

const localActiveColor = ref(props.activeColor);
watch(() => props.activeColor, (v) => { localActiveColor.value = v; });
watch(localActiveColor, (v) => { emit('update:activeColor', v); });

function execFmt(cmd, value = null) {
    emit('execFmt', cmd, value);
}
</script>
