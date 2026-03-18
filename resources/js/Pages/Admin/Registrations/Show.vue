<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import TableMap from '../TableMap.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    registration: Object,
    tables: Array,
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
                    <span>Potvrdenie: {{ registration.registrant_email }}</span>
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
                                        <span v-if="guest.table_id" class="ml-3 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                            Umiestnený
                                        </span>
                                        <span v-else class="ml-3 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                            Bez miesta
                                        </span>
                                    </h4>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-1 space-y-0.5">
                                        <div v-if="guest.allergen_ids?.length || guest.is_vegan || guest.is_vegetarian || guest.allergen_note" class="text-red-500 dark:text-red-400 font-medium">
                                            Alergény:
                                            <span v-if="guest.allergen_ids?.length">{{ guest.allergen_ids.join(', ') }}</span>
                                            <span v-if="guest.is_vegan"> · Vegán</span>
                                            <span v-if="guest.is_vegetarian"> · Vegetarián</span>
                                            <span v-if="guest.allergen_note"> · {{ guest.allergen_note }}</span>
                                        </div>
                                        <div v-if="guest.note" class="text-gray-400 dark:text-gray-500 italic">Poznámka: {{ guest.note }}</div>
                                        <div v-if="guest.table_id">Stôl: {{ guest.table.name }}, Miesto: {{ guest.seat_number }}</div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2 justify-end">
                                    <button
                                        @click="togglePaid(guest)"
                                        class="px-2.5 py-1 rounded-full text-xs font-semibold transition-colors cursor-pointer"
                                        :class="guest.paid
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 hover:bg-green-200'
                                            : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 hover:bg-red-200'"
                                    >
                                        {{ guest.paid ? '✓ Zaplatené' : '✗ Nezaplatené' }}
                                    </button>

                                    <button
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
                                        v-if="guest.table_id && !guest.ticket_issued"
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
