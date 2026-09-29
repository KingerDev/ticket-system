<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';

/**
 * Potvrdenie zmeny platby. Jeden klik na odznak by ľahko prepol platbu
 * omylom – a od nej závisí vydanie lístka aj vstup na ples.
 */
const props = defineProps({
    guest: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const processing = ref(false);

const confirm = () => {
    processing.value = true;
    router.post(route('admin.guests.toggle_paid', props.guest.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            emit('close');
        },
    });
};
</script>

<template>
    <Modal :show="!!guest" @close="emit('close')" maxWidth="md">
        <div v-if="guest" class="p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                {{ guest.paid ? 'Zrušiť platbu' : 'Označiť ako zaplatené' }}
            </h2>

            <p v-if="guest.paid" class="text-gray-600 dark:text-gray-400 mb-4">
                Naozaj chcete hosťovi <strong class="text-gray-900 dark:text-gray-100">{{ guest.name }}</strong>
                zrušiť označenie platby?
            </p>
            <p v-else class="text-gray-600 dark:text-gray-400 mb-4">
                Prevzali ste od hosťa <strong class="text-gray-900 dark:text-gray-100">{{ guest.name }}</strong>
                platbu v hotovosti?
            </p>

            <div
                v-if="guest.paid && guest.ticket_issued"
                class="mb-4 rounded-lg border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-900/20 p-3 text-sm text-amber-800 dark:text-amber-300"
            >
                Hosť už má lístok č. {{ guest.ticket_code }}. Bez platby ho check-in nepustí dnu.
            </div>

            <div class="flex justify-end space-x-3">
                <button
                    type="button"
                    @click="emit('close')"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                >
                    Zrušiť
                </button>
                <button
                    type="button"
                    @click="confirm"
                    :disabled="processing"
                    class="px-4 py-2 rounded-md text-sm font-semibold text-white disabled:opacity-50"
                    :class="guest.paid ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'"
                >
                    {{ guest.paid ? 'Zrušiť platbu' : 'Áno, zaplatené' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
