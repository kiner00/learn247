import { ref, watch, onBeforeUnmount } from 'vue';

/**
 * Composable that adds drag-and-drop file support to any element.
 *
 * @param {Ref<HTMLElement>} dropRef  - template ref of the drop target
 * @param {(files: FileList) => void} onFiles - callback when files are dropped
 * @param {Object} options
 * @param {string} options.accept - MIME pattern to filter (e.g. 'image/*', 'video/*', '.csv')
 */
export function useDropzone(dropRef, onFiles, options = {}) {
    const isDragging = ref(false);
    let dragCounter = 0;
    let currentEl = null;

    function handleDragEnter(e) {
        e.preventDefault();
        dragCounter++;
        isDragging.value = true;
    }

    function handleDragOver(e) {
        e.preventDefault();
    }

    function handleDragLeave(e) {
        e.preventDefault();
        dragCounter--;
        if (dragCounter <= 0) {
            dragCounter = 0;
            isDragging.value = false;
        }
    }

    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        dragCounter = 0;
        isDragging.value = false;

        const files = e.dataTransfer?.files;
        if (!files?.length) return;

        if (options.accept) {
            const filtered = Array.from(files).filter(f => matchAccept(f, options.accept));
            if (filtered.length) {
                const dt = new DataTransfer();
                filtered.forEach(f => dt.items.add(f));
                onFiles(dt.files);
            }
        } else {
            onFiles(files);
        }
    }

    function matchAccept(file, accept) {
        return accept.split(',').some(pattern => {
            const p = pattern.trim();
            if (p.startsWith('.')) return file.name.toLowerCase().endsWith(p.toLowerCase());
            if (p.endsWith('/*')) return file.type.startsWith(p.replace('/*', '/'));
            return file.type === p;
        });
    }

    function attach(el) {
        if (!el || el === currentEl) return;
        detach();
        currentEl = el;
        el.addEventListener('dragenter', handleDragEnter);
        el.addEventListener('dragover', handleDragOver);
        el.addEventListener('dragleave', handleDragLeave);
        el.addEventListener('drop', handleDrop);
    }

    function detach() {
        if (!currentEl) return;
        currentEl.removeEventListener('dragenter', handleDragEnter);
        currentEl.removeEventListener('dragover', handleDragOver);
        currentEl.removeEventListener('dragleave', handleDragLeave);
        currentEl.removeEventListener('drop', handleDrop);
        currentEl = null;
    }

    // Watch the ref so listeners attach when v-if renders the element
    watch(() => dropRef.value?.$el || dropRef.value, (el) => {
        if (el) attach(el);
        else detach();
    }, { immediate: true, flush: 'post' });

    onBeforeUnmount(() => detach());

    return { isDragging };
}
