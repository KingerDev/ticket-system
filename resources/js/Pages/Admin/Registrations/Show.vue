<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import TableMap from '../TableMap.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    registration: Object,
    tables: Array,
    // Zoznam alergénov chodí z modelu Guest, nech nie je duplikovaný vo frontende.
    allergens: { type: Array, default: () => [] },
});

// Currently selected guest id for assignment
const activeGuestId = ref(null);
const activeGuest = ref(null);

// Currently selected seat on map
const selectedSeat = ref(null);

const selectGuestForAssignment = (guest) => {
    activeGuestId.value = guest.id;
    activeGuest.value = guest;
    if (guest.table_id && guest.seat_number) {
        selectedSeat.value = { tableId: guest.table_id, seatNum: guest.seat_number };
    } else {
        selectedSeat.value = null;
    }
    // Scroll to map smoothly
    document.getElementById('map-section').scrollIntoView({ behavior: 'smooth' });
};

const cancelAssignment = () => {
    activeGuestId.value = null;
    activeGuest.value = null;
    selectedSeat.value = null;
};

const handleSeatSelected = ({ table, seatNum, guest }) => {
    // If we have an active guest that needs a seat mapped to them
    if (!activeGuestId.value) return;
    
    // Cannot select an occupied seat (unless it is exactly the seat this guest already has)
    if (guest && guest.id !== activeGuestId.value) {
        alert('Toto miesto je už obsadené.');
        return;
    }

    selectedSeat.value = { tableId: table.id, seatNum: seatNum };
};

const saveSeatAssignment = () => {
    if (!selectedSeat.value || !activeGuestId.value) return;

    router.post(route('admin.registrations.assign', props.registration.id), {
        guest_id: activeGuestId.value,
        table_id: selectedSeat.value.tableId,
        seat_number: selectedSeat.value.seatNum,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            // Find next unassigned guest
            const nextGuest = props.registration.guests.find(g => g.id !== activeGuestId.value && !g.table_id);
            if (nextGuest) {
                selectGuestForAssignment(nextGuest);
            } else {
                cancelAssignment();
            }
        }
    });
};

// --- Úprava údajov hosťa ---------------------------------------------------

const showEditModal = ref(false);
const editingGuest = ref(null);

const editForm = useForm({
    name: '',
    email: '',
    allergen_ids: [],
    is_vegan: false,
    is_vegetarian: false,
    is_teacher: false,
    allergen_note: '',
    note: '',
});

const openEditModal = (guest) => {
    editingGuest.value = guest;
    editForm.clearErrors();
    editForm.name          = guest.name ?? '';
    editForm.email         = guest.email ?? '';
    editForm.allergen_ids  = [...(guest.allergen_ids ?? [])];
    editForm.is_vegan      = !!guest.is_vegan;
    editForm.is_vegetarian = !!guest.is_vegetarian;
    editForm.is_teacher    = !!guest.is_teacher;
    editForm.allergen_note = guest.allergen_note ?? '';
    editForm.note          = guest.note ?? '';
    showEditModal.value = true;
};

const saveGuest = () => {
    editForm.patch(route('admin.guests.update', editingGuest.value.id), {
        preserveScroll: true,
        onSuccess: () => { showEditModal.value = false; },
    });
};

// --- Odstránenie hosťa -----------------------------------------------------

const showDeleteModal = ref(false);
const deletingGuest = ref(null);
const deleting = ref(false);

/** Posledný hosť: s ním padne celá rezervácia, na to treba upozorniť zvlášť. */
const isLastGuest = computed(() => props.registration.guests.length <= 1);

const openDeleteModal = (guest) => {
    deletingGuest.value = guest;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    deleting.value = true;
    router.delete(route('admin.guests.destroy', deletingGuest.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            showDeleteModal.value = false;
        },
    });
};

// --- Úprava kontaktu rezervácie --------------------------------------------

const showContactModal = ref(false);

const contactForm = useForm({
    registrant_name: '',
    registrant_email: '',
});

const openContactModal = () => {
    contactForm.clearErrors();
    contactForm.registrant_name  = props.registration.registrant_name ?? '';
    contactForm.registrant_email = props.registration.registrant_email ?? '';
    showContactModal.value = true;
};

const saveContact = () => {
    contactForm.patch(route('admin.registrations.update_contact', props.registration.id), {
        preserveScroll: true,
        onSuccess: () => { showContactModal.value = false; },
    });
};

const showIssueModal = ref(false);
const issueModalGuest = ref(null);
const issueIsTeacher = ref(false);

const openIssueModal = (guest) => {
    issueModalGuest.value = guest;
    issueIsTeacher.value = guest.is_teacher ?? false;
    showIssueModal.value = true;
};

const confirmIssueTicket = () => {
    router.post(route('admin.guests.issue_ticket', issueModalGuest.value.id), {
        is_teacher: issueIsTeacher.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { showIssueModal.value = false; },
    });
};

const togglePaid = (guest) => {
    router.post(route('admin.guests.toggle_paid', guest.id), {}, { preserveScroll: true });
};

const page = usePage();
const showTicketModal = ref(false);
const issuedTicketCode = ref('');
const issuedTicketName = ref('');

watch(() => page.props.flash.ticket_issued_code, (newCode) => {
    if (newCode) {
        issuedTicketCode.value = newCode;
        issuedTicketName.value = page.props.flash.ticket_issued_name;
        showTicketModal.value = true;
    }
}, { immediate: true });

const closeTicketModal = () => {
    showTicketModal.value = false;
};
</script>

<template>
    <Head :title="`Detail | ${registration.reservation_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('admin.registrations.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    &larr; Späť
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Rezervácia {{ registration.reservation_number }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Group info -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                    <span class="font-semibold text-gray-900 dark:text-white text-base">{{ registration.reservation_number }}</span>
                    <span class="text-gray-400">·</span>
                    <span>Skupinová registrácia — {{ registration.guests.length }} {{ registration.guests.length === 1 ? 'hosť' : registration.guests.length < 5 ? 'hostia' : 'hostí' }}</span>
                    <span class="text-gray-400">·</span>
                    <span>Kontakt: {{ registration.registrant_name }} &lt;{{ registration.registrant_email }}&gt;</span>
                    <button
                        @click="openContactModal"
                        class="ml-auto px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 bg-white dark:bg-gray-800"
                    >
                        Upraviť kontakt
                    </button>
                </div>

                <!-- Guests List -->
                <div class="bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Hostia a Lístky</h3>
                    </div>
                    
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-for="(guest, index) in registration.guests" :key="guest.id" class="p-6" :class="{'bg-blue-50 dark:bg-blue-900/20': activeGuestId === guest.id}">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                                        {{ guest.name }}
                                        <span v-if="guest.cancelled_at" class="ml-3 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                            Stornovaný
                                        </span>
                                        <span v-else-if="guest.table_id" class="ml-3 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                            Umiestnený
                                        </span>
                                        <span v-else class="ml-3 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                            Bez miesta
                                        </span>
                                    </h4>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-1 space-y-0.5">
                                        <div v-if="guest.email">{{ guest.email }}</div>
                                        <div v-if="guest.allergen_ids?.length || guest.is_vegan || guest.is_vegetarian || guest.allergen_note" class="text-red-500 dark:text-red-400 font-medium">
                                            Alergény:
                                            <span v-if="guest.allergen_ids?.length">{{ guest.allergen_ids.join(', ') }}</span>
                                            <span v-if="guest.is_vegan"> · Vegán</span>
                                            <span v-if="guest.is_vegetarian"> · Vegetarián</span>
                                            <span v-if="guest.allergen_note"> · {{ guest.allergen_note }}</span>
                                        </div>
                                        <div v-if="guest.note" class="text-gray-400 dark:text-gray-500 italic">Poznámka: {{ guest.note }}</div>
                                        <div v-if="!guest.paid && !guest.cancelled_at && guest.payment_deadline_at" class="text-amber-600 dark:text-amber-400">
                                            Termín na úhradu: {{ new Date(guest.payment_deadline_at).toLocaleDateString('sk-SK') }}
                                        </div>
                                        <div v-if="guest.table_id">Stôl: {{ guest.table.name }}, Miesto: {{ guest.seat_number }}</div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2 justify-end">
                                    <button
                                        v-if="!guest.cancelled_at"
                                        @click="togglePaid(guest)"
                                        class="px-2.5 py-1 rounded-full text-xs font-semibold transition-colors cursor-pointer"
                                        :class="guest.paid
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 hover:bg-green-200'
                                            : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 hover:bg-red-200'"
                                    >
                                        {{ guest.paid ? '✓ Zaplatené' : '✗ Nezaplatené' }}
                                    </button>

                                    <button
                                        @click="openEditModal(guest)"
                                        class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 bg-white dark:bg-gray-800"
                                    >
                                        Upraviť údaje
                                    </button>

                                    <button
                                        @click="openDeleteModal(guest)"
                                        title="Odstrániť hosťa z rezervácie"
                                        class="px-3 py-1.5 border border-red-200 dark:border-red-900/50 rounded-md text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 bg-white dark:bg-gray-800"
                                    >
                                        Odstrániť
                                    </button>

                                    <button
                                        v-if="!guest.cancelled_at"
                                        @click="selectGuestForAssignment(guest)"
                                        class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 bg-white dark:bg-gray-800"
                                        :class="{'ring-2 ring-blue-500': activeGuestId === guest.id}"
                                    >
                                        {{ guest.table_id ? 'Zmeniť miesto' : 'Vybrať miesto' }}
                                    </button>

                                    <span v-if="guest.is_teacher" class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400 self-center">
                                        Učiteľ
                                    </span>
                                    <PrimaryButton
                                        v-if="!guest.cancelled_at && guest.table_id && !guest.ticket_issued"
                                        @click="openIssueModal(guest)"
                                        class="bg-blue-600 hover:bg-blue-700 text-white"
                                    >
                                        Vydať lístok
                                    </PrimaryButton>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Embedded Interactive Table Map -->
                <div id="map-section" class="bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 rounded-lg overflow-hidden relative">
                    
                    <div v-if="activeGuestId" class="absolute top-0 left-0 w-full z-20 bg-blue-600 shadow-lg text-white px-6 py-4 flex justify-between items-center transition-all">
                        <div>
                            <span class="block text-sm text-blue-200">Vyberte voľné miesto na mape pre hosťa:</span>
                            <span class="text-xl font-bold">{{ activeGuest.name }}</span>
                        </div>
                        <div class="flex space-x-3">
                            <button @click="cancelAssignment" class="px-4 py-2 border border-blue-400 bg-blue-700 hover:bg-blue-800 rounded-md text-sm font-medium transition-colors">
                                Zrušiť
                            </button>
                            <button @click="saveSeatAssignment" :disabled="!selectedSeat" class="px-6 py-2 bg-white text-blue-700 hover:bg-gray-100 rounded-md text-sm font-bold shadow transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                Potvrdiť miesto
                            </button>
                        </div>
                    </div>

                    <div v-else class="absolute top-0 left-0 w-full z-20 bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center h-[76px]">
                        <span class="text-gray-500 dark:text-gray-400 text-sm font-medium flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                            Najprv kliknite na "Vybrať miesto" pri konkrétnom hosťovi
                        </span>
                    </div>

                    <!-- We render TableMap without standard page wrapper by just importing the raw component logic, but we can also just use the TableMap in assign mode taking full width. To prevent its own Layout from rendering, we must configure TableMap.vue to support standalone rendering or we refactored it. Ah wait, TableMap.vue wraps everything in AuthenticatedLayout!! I must extract the core map. -->
                    <div class="h-[600px] relative overflow-hidden mt-[76px]">
                        <TableMap :tables="tables" :assignMode="true" :selectedSeat="selectedSeat" @seat-selected="handleSeatSelected" :embedded="true" />
                    </div>
                </div>

            </div>
        </div>

        <!-- Úprava údajov hosťa -->
        <Modal :show="showEditModal" @close="showEditModal = false" maxWidth="2xl">
            <form v-if="editingGuest" @submit.prevent="saveGuest" class="p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-1">Upraviť údaje hosťa</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    Rezervácia {{ registration.reservation_number }}
                </p>

                <div class="space-y-5 max-h-[60vh] overflow-y-auto pr-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="edit_name">
                                Meno a priezvisko <span class="text-red-500">*</span>
                            </InputLabel>
                            <TextInput id="edit_name" type="text" class="mt-1 block w-full" v-model="editForm.name" required />
                            <InputError class="mt-1" :message="editForm.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="edit_email">E-mail</InputLabel>
                            <TextInput id="edit_email" type="email" class="mt-1 block w-full" v-model="editForm.email" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nepovinné. Potvrdenia chodia na kontaktný e-mail rezervácie.</p>
                            <InputError class="mt-1" :message="editForm.errors.email" />
                        </div>
                    </div>

                    <fieldset>
                        <legend class="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">Alergie na jedlo</legend>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2">
                            <label v-for="allergen in allergens" :key="allergen.id" class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    :value="allergen.id"
                                    v-model="editForm.allergen_ids"
                                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4"
                                />
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    <span class="font-semibold text-gray-500 dark:text-gray-400 mr-0.5">{{ allergen.id }}.</span>{{ allergen.name }}
                                </span>
                            </label>
                        </div>
                        <InputError class="mt-1" :message="editForm.errors.allergen_ids" />
                    </fieldset>

                    <fieldset>
                        <legend class="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">Strava a rola</legend>
                        <div class="flex flex-wrap gap-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="editForm.is_vegan" class="rounded border-gray-300 text-green-600 focus:ring-green-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4" />
                                <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">Vegán</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="editForm.is_vegetarian" class="rounded border-gray-300 text-green-600 focus:ring-green-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4" />
                                <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">Vegetarián</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="editForm.is_teacher" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4" />
                                <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">Učiteľ / učiteľka</span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="edit_allergen_note" value="Doplnenie k alergiám" />
                            <textarea
                                id="edit_allergen_note"
                                v-model="editForm.allergen_note"
                                rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                            ></textarea>
                            <InputError class="mt-1" :message="editForm.errors.allergen_note" />
                        </div>
                        <div>
                            <InputLabel for="edit_note" value="Odkaz pre organizátorov" />
                            <textarea
                                id="edit_note"
                                v-model="editForm.note"
                                rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                            ></textarea>
                            <InputError class="mt-1" :message="editForm.errors.note" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button
                        type="button"
                        @click="showEditModal = false"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        Zrušiť
                    </button>
                    <PrimaryButton :disabled="editForm.processing" :class="{ 'opacity-25': editForm.processing }">
                        {{ editForm.processing ? 'Ukladám…' : 'Uložiť zmeny' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Úprava kontaktu rezervácie -->
        <Modal :show="showContactModal" @close="showContactModal = false" maxWidth="md">
            <form @submit.prevent="saveContact" class="p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-1">Upraviť kontakt rezervácie</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    Adresa, na ktorú sa posielajú potvrdenia k rezervácii {{ registration.reservation_number }}.
                </p>

                <div class="space-y-4">
                    <div>
                        <InputLabel for="contact_name">
                            Kontaktná osoba <span class="text-red-500">*</span>
                        </InputLabel>
                        <TextInput id="contact_name" type="text" class="mt-1 block w-full" v-model="contactForm.registrant_name" required />
                        <InputError class="mt-1" :message="contactForm.errors.registrant_name" />
                    </div>
                    <div>
                        <InputLabel for="contact_email">
                            Kontaktný e-mail <span class="text-red-500">*</span>
                        </InputLabel>
                        <TextInput id="contact_email" type="email" class="mt-1 block w-full" v-model="contactForm.registrant_email" required />
                        <InputError class="mt-1" :message="contactForm.errors.registrant_email" />
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button
                        type="button"
                        @click="showContactModal = false"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        Zrušiť
                    </button>
                    <PrimaryButton :disabled="contactForm.processing" :class="{ 'opacity-25': contactForm.processing }">
                        {{ contactForm.processing ? 'Ukladám…' : 'Uložiť' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Potvrdenie odstránenia hosťa -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false" maxWidth="lg">
            <div class="p-6" v-if="deletingGuest">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Odstrániť hosťa</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    Naozaj chcete odstrániť hosťa <strong class="text-gray-900 dark:text-gray-100">{{ deletingGuest.name }}</strong>?
                    Túto akciu nie je možné vrátiť späť.
                </p>

                <!-- Čo sa odstránením stratí – admin to musí vidieť pred kliknutím. -->
                <ul class="mb-4 space-y-1.5 text-sm">
                    <li v-if="deletingGuest.table_id" class="text-gray-600 dark:text-gray-400">
                        Uvoľní sa miesto {{ deletingGuest.seat_number }} pri stole {{ deletingGuest.table?.name }}.
                    </li>
                    <li v-if="deletingGuest.paid" class="text-amber-700 dark:text-amber-400 font-medium">
                        Hosť má označenú uhradenú platbu.
                    </li>
                    <li v-if="deletingGuest.ticket_issued" class="text-amber-700 dark:text-amber-400 font-medium">
                        Hosťovi už bol vydaný lístok č. {{ deletingGuest.ticket_code }}.
                    </li>
                    <li v-if="deletingGuest.checked_in" class="text-amber-700 dark:text-amber-400 font-medium">
                        Hosť už bol zapísaný pri vstupe.
                    </li>
                </ul>

                <div v-if="isLastGuest" class="mb-6 rounded-lg border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/20 p-4 text-sm text-red-700 dark:text-red-300">
                    Toto je posledný hosť rezervácie, takže sa odstráni aj celá rezervácia
                    <strong>{{ registration.reservation_number }}</strong>.
                </div>

                <div class="flex justify-end space-x-3">
                    <button
                        @click="showDeleteModal = false"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        Zrušiť
                    </button>
                    <button
                        @click="confirmDelete"
                        :disabled="deleting"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ deleting ? 'Odstraňujem…' : (isLastGuest ? 'Odstrániť aj rezerváciu' : 'Odstrániť hosťa') }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Issue Ticket Confirmation Modal -->
        <Modal :show="showIssueModal" @close="showIssueModal = false" maxWidth="sm">
            <div class="p-6" v-if="issueModalGuest">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Vydať lístok</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    Vydávate lístok pre hosťa <strong>{{ issueModalGuest.name }}</strong>.
                </p>

                <label class="flex items-center space-x-3 cursor-pointer mb-8">
                    <input
                        type="checkbox"
                        v-model="issueIsTeacher"
                        class="w-5 h-5 rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                    />
                    <span class="text-gray-800 dark:text-gray-200 font-medium">Je to učiteľ / učiteľka</span>
                </label>

                <div class="flex justify-end space-x-3">
                    <button
                        @click="showIssueModal = false"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        Zrušiť
                    </button>
                    <PrimaryButton @click="confirmIssueTicket" class="bg-blue-600 hover:bg-blue-700 text-white">
                        Potvrdiť a vydať
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Ticket Issued Modal -->
        <Modal :show="showTicketModal" @close="closeTicketModal" maxWidth="md">
            <div class="p-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-4 text-center">
                    Lístok vydaný!
                </h2>
                
                <p class="text-gray-600 dark:text-gray-400 mb-6 text-center text-lg">
                    Zapíšte toto unikátne 3-miestne ID na vstupenku pre hosťa <strong>{{ issuedTicketName }}</strong>:
                </p>

                <div class="flex justify-center mb-8">
                    <div class="text-7xl font-black text-blue-600 dark:text-blue-400 tracking-widest bg-blue-50 dark:bg-gray-800 py-6 px-10 rounded-lg border-2 border-dashed border-blue-300 dark:border-blue-700 select-all">
                        {{ issuedTicketCode }}
                    </div>
                </div>

                <div class="mt-6 flex justify-center">
                    <PrimaryButton @click="closeTicketModal">
                        Rozumiem, lístok je popísaný
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>
