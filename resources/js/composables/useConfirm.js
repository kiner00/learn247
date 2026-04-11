import { ref } from 'vue';

const show = ref(false);
const title = ref('Are you sure?');
const message = ref('');
const confirmLabel = ref('Confirm');
const destructive = ref(false);
const processing = ref(false);
let resolvePromise = null;

export function useConfirm() {
    function ask(opts = {}) {
        title.value = opts.title ?? 'Are you sure?';
        message.value = opts.message ?? '';
        confirmLabel.value = opts.confirmLabel ?? 'Confirm';
        destructive.value = opts.destructive ?? false;
        processing.value = false;
        show.value = true;

        return new Promise((resolve) => {
            resolvePromise = resolve;
        });
    }

    function onConfirm() {
        show.value = false;
        resolvePromise?.(true);
    }

    function onCancel() {
        show.value = false;
        resolvePromise?.(false);
    }

    return { show, title, message, confirmLabel, destructive, processing, ask, onConfirm, onCancel };
}
