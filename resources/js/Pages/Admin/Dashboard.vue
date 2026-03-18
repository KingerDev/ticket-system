<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: Object,
});

const ticketsPercentage = computed(() => {
    if (props.stats.totalGuests === 0) return 0;
    return Math.round((props.stats.ticketsIssued / props.stats.totalGuests) * 100);
});

const checkInPercentage = computed(() => {
    if (props.stats.totalGuests === 0) return 0;
    return Math.round((props.stats.guestsCheckedIn / props.stats.totalGuests) * 100);
});

const seatsPercentage = computed(() => {
    if (props.stats.totalGuests === 0) return 0;
    return Math.round((props.stats.guestsWithSeats / props.stats.totalGuests) * 100);
});

const teachersPercentage = computed(() => {
    if (props.stats.totalGuests === 0) return 0;
    return Math.round((props.stats.teachersCount / props.stats.totalGuests) * 100);
});

const paidPercentage = computed(() => {
    if (props.stats.totalGuests === 0) return 0;
    return Math.round((props.stats.paidCount / props.stats.totalGuests) * 100);
});

const capacityFillPercentage = computed(() => {
    if (props.stats.totalCapacity === 0) return 0;
    return Math.round((props.stats.totalGuests / props.stats.totalCapacity) * 100);
});

const capacityPaidPercentage = computed(() => {
    if (props.stats.totalCapacity === 0) return 0;
    return Math.round((props.stats.paidCount / props.stats.totalCapacity) * 100);
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Prehľad</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Top stat cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Registrations -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                        <div class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Registrácie</div>
                        <div class="text-4xl font-black text-gray-900 dark:text-gray-100">{{ stats.totalRegistrations }}</div>
                        <div class="text-xs text-gray-400 mt-1">{{ stats.totalGuests }} hostí celkom</div>
                    </div>

                    <!-- Capacity -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                        <div class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Obsadenosť sály</div>
                        <div class="flex items-end gap-2 mb-2">
                            <span class="text-4xl font-black text-gray-900 dark:text-gray-100">{{ stats.totalGuests }}</span>
                            <span class="text-lg text-gray-400 dark:text-gray-500 mb-1">/ {{ stats.totalCapacity }}</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5 mb-1">
                            <div class="bg-blue-500 h-1.5 rounded-full transition-all duration-500" :style="`width: ${capacityFillPercentage}%`"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-400">
                            <span>Registrovaní {{ stats.totalGuests }}</span>
                            <span class="text-green-600 dark:text-green-400">Zaplatení {{ stats.paidCount }}</span>
                        </div>
                    </div>

                    <!-- Tickets issued -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                        <div class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Vydané lístky</div>
                        <div class="flex items-end gap-2 mb-2">
                            <span class="text-4xl font-black text-gray-900 dark:text-gray-100">{{ stats.ticketsIssued }}</span>
                            <span class="text-lg text-gray-400 dark:text-gray-500 mb-1">/ {{ stats.totalGuests }}</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
                            <div class="bg-yellow-400 h-1.5 rounded-full transition-all duration-500" :style="`width: ${ticketsPercentage}%`"></div>
                        </div>
                        <div class="text-xs text-gray-400 mt-1">{{ ticketsPercentage }}%</div>
                    </div>

                    <!-- Check-in -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                        <div class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Check-in</div>
                        <div class="flex items-end gap-2 mb-2">
                            <span class="text-4xl font-black text-green-600 dark:text-green-400">{{ stats.guestsCheckedIn }}</span>
                            <span class="text-lg text-gray-400 dark:text-gray-500 mb-1">/ {{ stats.totalGuests }}</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
                            <div class="bg-green-500 h-1.5 rounded-full transition-all duration-500" :style="`width: ${checkInPercentage}%`"></div>
                        </div>
                        <div class="text-xs text-gray-400 mt-1">{{ checkInPercentage }}%</div>
                    </div>
                </div>

                <!-- Middle section: breakdown panels -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                    <!-- Seats progress -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">Pridelenie miest</h3>
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-700 dark:text-gray-300">Umiestnení</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ stats.guestsWithSeats }} / {{ stats.totalGuests }}</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-3">
                                    <div class="bg-blue-500 h-3 rounded-full transition-all duration-500" :style="`width: ${seatsPercentage}%`"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-700 dark:text-gray-300">Bez miesta</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ stats.totalGuests - stats.guestsWithSeats }}</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-3">
                                    <div class="bg-gray-300 h-3 rounded-full transition-all duration-500" :style="`width: ${100 - seatsPercentage}%`"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hostia breakdown -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">Hostia podľa typu</h3>
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-700 dark:text-gray-300">Študenti</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ stats.studentsCount }}</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-3">
                                    <div class="bg-indigo-400 h-3 rounded-full transition-all duration-500" :style="`width: ${100 - teachersPercentage}%`"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-700 dark:text-gray-300">Učitelia</span>
                                    <span class="font-semibold text-purple-700 dark:text-purple-400">{{ stats.teachersCount }}</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-3">
                                    <div class="bg-purple-500 h-3 rounded-full transition-all duration-500" :style="`width: ${teachersPercentage}%`"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Platby breakdown -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">Stav platieb</h3>
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-700 dark:text-gray-300">Zaplatené</span>
                                    <span class="font-semibold text-green-700 dark:text-green-400">{{ stats.paidCount }} / {{ stats.totalGuests }}</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-3">
                                    <div class="bg-green-500 h-3 rounded-full transition-all duration-500" :style="`width: ${paidPercentage}%`"></div>
                                </div>
                                <div class="text-xs text-gray-400 mt-1">{{ stats.paidCount }} z {{ stats.totalGuests }} hostí · {{ stats.paidCount }} z {{ stats.totalCapacity }} kapacity sály</div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-700 dark:text-gray-300">Nezaplatené</span>
                                    <span class="font-semibold text-red-600 dark:text-red-400">{{ stats.unpaidCount }}</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-3">
                                    <div class="bg-red-400 h-3 rounded-full transition-all duration-500" :style="`width: ${100 - paidPercentage}%`"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">Rýchle akcie</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Link :href="route('admin.registrations.index')" class="flex items-center p-4 bg-white dark:bg-gray-800 border border-transparent rounded-xl shadow-sm hover:shadow-md hover:border-gray-300 dark:hover:border-gray-600 transition-all cursor-pointer">
                            <div class="bg-blue-100 text-blue-600 p-3 rounded-lg mr-4 dark:bg-blue-900 dark:text-blue-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Správa registrácií</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Výdaj lístkov a usádzanie hostí</p>
                            </div>
                        </Link>

                        <Link :href="route('admin.checkin')" class="flex items-center p-4 bg-white dark:bg-gray-800 border border-transparent rounded-xl shadow-sm hover:shadow-md hover:border-gray-300 dark:hover:border-gray-600 transition-all cursor-pointer">
                            <div class="bg-green-100 text-green-600 p-3 rounded-lg mr-4 dark:bg-green-900 dark:text-green-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Check-in skener</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Skener lístkov pri vstupe</p>
                            </div>
                        </Link>

                        <Link :href="route('admin.tables.map')" class="flex items-center p-4 bg-white dark:bg-gray-800 border border-transparent rounded-xl shadow-sm hover:shadow-md hover:border-gray-300 dark:hover:border-gray-600 transition-all cursor-pointer">
                            <div class="bg-purple-100 text-purple-600 p-3 rounded-lg mr-4 dark:bg-purple-900 dark:text-purple-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Mapa sály</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Zobrazenie plného stavu sádlania</p>
                            </div>
                        </Link>

                        <Link :href="route('admin.hall.edit')" class="flex items-center p-4 bg-white dark:bg-gray-800 border border-transparent rounded-xl shadow-sm hover:shadow-md hover:border-gray-300 dark:hover:border-gray-600 transition-all cursor-pointer">
                            <div class="bg-orange-100 text-orange-600 p-3 rounded-lg mr-4 dark:bg-orange-900 dark:text-orange-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Konfigurácia sály</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Nastavenie stolov a rozloženia</p>
                            </div>
                        </Link>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
