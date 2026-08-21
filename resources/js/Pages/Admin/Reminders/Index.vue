<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    awaiting: Array,
    cancelled: Array,
    defaultDeadline: String,
});

const skupiny = computed(() => ({
    po_termine: props.awaiting.filter(r => r.stav === 'po_termine'),
    posledna_vyzva: props.awaiting.filter(r => r.stav === 'posledna_vyzva'),
    caka: props.awaiting.filter(r => r.stav === 'caka'),
    bez_terminu: props.awaiting.filter(r => r.stav === 'bez_terminu'),
}));

// --- odoslanie e-mailu ---
const showSendModal = ref(false);
const cielova = ref(null);
const jePoslednaVyzva = ref(false);

const sendForm = useForm({ deadline: props.defaultDeadline });

const openSend = (registracia, finalNotice) => {
    cielova.value = registracia;
    jePoslednaVyzva.value = finalNotice;
    sendForm.clearErrors();
    sendForm.deadline = registracia.deadline_at ?? props.defaultDeadline;
    showSendModal.value = true;
};

const submitSend = () => {
    const cesta = jePoslednaVyzva.value ? 'admin.reminders.final_notice' : 'admin.reminders.send';
    sendForm.post(route(cesta, cielova.value.id), {
        preserveScroll: true,
        onSuccess: () => { showSendModal.value = false; },
    });
};

// --- storno ---
const showCancelModal = ref(false);
const stornovana = ref(null);
const cancelForm = useForm({ notify: true });

const openCancel = (registracia) => {
    stornovana.value = registracia;
    cancelForm.notify = true;
    showCancelModal.value = true;
};

const submitCancel = () => {
    cancelForm.post(route('admin.reminders.cancel', stornovana.value.id), {
        preserveScroll: true,
        onSuccess: () => { showCancelModal.value = false; },
    });
};

const obnovit = (guestId) => {
    router.post(route('admin.guests.restore', guestId), {}, { preserveScroll: true });
};

const zostava = (dni) => {
    if (dni === null) return '';
    if (dni < 0) return `po termíne o ${Math.abs(dni)} ${Math.abs(dni) === 1 ? 'deň' : 'dní'}`;
    if (dni === 0) return 'termín je dnes';
    return `zostáva ${dni} ${dni === 1 ? 'deň' : dni < 5 ? 'dni' : 'dní'}`;
};
</script>

<template>
    <Head title="Pripomienky a storná" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Pripomienky a storná
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-8">

                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Rezervácie, ktoré ešte nie sú zaplatené. Aplikácia nič neposiela ani neruší sama —
                    e-mail odchádza až keď ho odošlete, a storno až keď ho potvrdíte.
                </p>

                <!-- Súhrn -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div v-for="(polozka, kluc) in {
                            po_termine: { popis: 'Po termíne', trieda: 'text-red-600 dark:text-red-400' },
                            posledna_vyzva: { popis: 'Po poslednej výzve', trieda: 'text-amber-600 dark:text-amber-400' },
                            caka: { popis: 'Čaká na platbu', trieda: 'text-blue-600 dark:text-blue-400' },
                            bez_terminu: { popis: 'Bez pripomienky', trieda: 'text-gray-600 dark:text-gray-400' },
                        }" :key="kluc"
                        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4 shadow-sm"
                    >
                        <div class="text-3xl font-black" :class="polozka.trieda">{{ skupiny[kluc].length }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ polozka.popis }}</div>
                    </div>
                </div>

                <!-- Zoznam nezaplatených -->
                <div v-if="awaiting.length" class="bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Čaká sa na platbu</h3>
                    </div>

                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-for="r in awaiting" :key="r.id" class="p-5"
                            :class="{ 'bg-red-50/50 dark:bg-red-900/10': r.stav === 'po_termine' }">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <Link :href="route('admin.registrations.show', r.id)"
                                              class="font-semibold text-gray-900 dark:text-white hover:underline">
                                            {{ r.reservation_number }}
                                        </Link>
                                        <span class="text-gray-500 dark:text-gray-400">{{ r.registrant_name }}</span>

                                        <span v-if="r.stav === 'po_termine'" class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                            Po termíne
                                        </span>
                                        <span v-else-if="r.stav === 'posledna_vyzva'" class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                            Posledná výzva odoslaná
                                        </span>
                                        <span v-else-if="r.stav === 'bez_terminu'" class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                            Bez pripomienky
                                        </span>
                                    </div>

                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Nezaplatení ({{ r.unpaid_count }}):
                                        <span class="text-gray-800 dark:text-gray-200">{{ r.guests.map(g => g.name).join(', ') }}</span>
                                    </p>

                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        <template v-if="r.deadline_label">
                                            Termín {{ r.deadline_label }} — {{ zostava(r.days_left) }}.
                                        </template>
                                        <template v-if="r.reminder_sent_at">
                                            Pripomienka {{ r.reminder_sent_at }}.
                                        </template>
                                        <template v-if="r.final_notice_sent_at">
                                            Posledná výzva {{ r.final_notice_sent_at }}.
                                        </template>
                                        <span class="text-gray-400">{{ r.registrant_email }}</span>
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2 justify-end">
                                    <button @click="openSend(r, false)"
                                        class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 bg-white dark:bg-gray-800">
                                        {{ r.reminder_sent_at ? 'Poslať znova' : 'Poslať pripomienku' }}
                                    </button>
                                    <button @click="openSend(r, true)"
                                        class="px-3 py-1.5 border border-amber-300 dark:border-amber-800 rounded-md text-sm font-medium text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 bg-white dark:bg-gray-800">
                                        Posledná výzva
                                    </button>
                                    <button @click="openCancel(r)"
                                        class="px-3 py-1.5 border border-red-200 dark:border-red-900/50 rounded-md text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 bg-white dark:bg-gray-800">
                                        Stornovať
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <div v-else class="bg-white dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700 p-10 text-center text-gray-500 dark:text-gray-400">
                    Všetci hostia majú zaplatené. Niet komu pripomínať.
                </div>

                <!-- Stornované -->
                <div v-if="cancelled.length" class="bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Stornované</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            Záznamy zostávajú. Ak sa hosť ozve, obnovte ho — miesto mu potom prideľte nanovo.
                        </p>
                    </div>

                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-for="r in cancelled" :key="r.id" class="p-5">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <Link :href="route('admin.registrations.show', r.id)"
                                              class="font-semibold text-gray-900 dark:text-white hover:underline">
                                            {{ r.reservation_number }}
                                        </Link>
                                        <span class="text-gray-500 dark:text-gray-400">{{ r.registrant_name }}</span>
                                    </div>
                                    <ul class="mt-2 space-y-1">
                                        <li v-for="g in r.guests" :key="g.id" class="flex items-center gap-3 text-sm">
                                            <span class="text-gray-700 dark:text-gray-300">{{ g.name }}</span>
                                            <span class="text-xs text-gray-400">stornované {{ g.cancelled_at }}</span>
                                            <button @click="obnovit(g.id)"
                                                class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                                                Obnoviť
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Odoslanie pripomienky -->
        <Modal :show="showSendModal" @close="showSendModal = false" maxWidth="lg">
            <form v-if="cielova" @submit.prevent="submitSend" class="p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-1">
                    {{ jePoslednaVyzva ? 'Posledná výzva' : 'Pripomienka platby' }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
                    Rezervácia {{ cielova.reservation_number }} — odoslanie na {{ cielova.registrant_email }}
                </p>

                <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 p-4 mb-5">
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        E-mail sa týka {{ cielova.unpaid_count === 1 ? 'hosťa' : 'hostí' }}:
                        <strong>{{ cielova.guests.map(g => g.name).join(', ') }}</strong>
                    </p>
                </div>

                <div>
                    <InputLabel for="deadline">
                        Termín na dokončenie <span class="text-red-500">*</span>
                    </InputLabel>
                    <TextInput id="deadline" type="date" class="mt-1 block w-full" v-model="sendForm.deadline" required />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Tento dátum sa napíše do e-mailu a uloží k hosťom. Po ňom sa rezervácia objaví medzi tými po termíne.
                    </p>
                    <InputError class="mt-1" :message="sendForm.errors.deadline" />
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" @click="showSendModal = false"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Zrušiť
                    </button>
                    <PrimaryButton :disabled="sendForm.processing" :class="{ 'opacity-25': sendForm.processing }">
                        {{ sendForm.processing ? 'Odosielam…' : 'Odoslať e-mail' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Storno -->
        <Modal :show="showCancelModal" @close="showCancelModal = false" maxWidth="lg">
            <div v-if="stornovana" class="p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Stornovať rezerváciu</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    Rezervácia <strong class="text-gray-900 dark:text-gray-100">{{ stornovana.reservation_number }}</strong>
                    — stornujú sa nezaplatení hostia:
                    <strong class="text-gray-900 dark:text-gray-100">{{ stornovana.guests.map(g => g.name).join(', ') }}</strong>
                </p>

                <ul class="mb-4 space-y-1.5 text-sm text-gray-600 dark:text-gray-400">
                    <li>Uvoľnia sa im pridelené miesta.</li>
                    <li>Zaplatení hostia z tej istej rezervácie zostanú nedotknutí.</li>
                    <li>Záznam sa nemaže — hosťa viete kedykoľvek obnoviť.</li>
                </ul>

                <label class="flex items-start gap-3 cursor-pointer mb-6">
                    <input type="checkbox" v-model="cancelForm.notify" class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Poslať hosťovi e-mail o storne
                        <span class="block text-xs text-gray-500 dark:text-gray-400">Obsahuje výzvu, nech sa ozve, ak ide o omyl.</span>
                    </span>
                </label>

                <div class="flex justify-end space-x-3">
                    <button @click="showCancelModal = false"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Zrušiť
                    </button>
                    <button @click="submitCancel" :disabled="cancelForm.processing"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm font-semibold disabled:opacity-50">
                        {{ cancelForm.processing ? 'Stornujem…' : 'Stornovať' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
