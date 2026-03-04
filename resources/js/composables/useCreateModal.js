import { ref } from 'vue';

const show = ref(false);

export function useCreateModal() {
    return {
        showCreateModal: show,
        openCreateModal:  () => { show.value = true; },
        closeCreateModal: () => { show.value = false; },
    };
}
