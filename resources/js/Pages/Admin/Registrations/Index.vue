<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    guests: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');

let searchTimeout;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            route('admin.registrations.index'),
            { search: value },
            { preserveState: true, replace: true }
        );
    }, 300);
});

const togglePaid = (guest) => {
    router.post(route('admin.guests.toggle_paid', guest.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Správa Registrácií" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Správa Registrácií</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700">

                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800">
                        <div class="w-1/3">
                            <TextInput
                                v-model="search"
                                type="text"
                                class="w-full"
                                placeholder="Hľadať podľa mena, rezervácie..."
                            />
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Celkom: {{ guests.total }} hostí
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Meno hosťa</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rezervácia</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Miesto</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Platba</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Lístok</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Akcia</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="guest in guests.data" :key="guest.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">

                                    <!-- Meno -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ guest.name }}</span>
                                            <span v-if="guest.is_teacher" class="px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400">Učiteľ</span>
                                        </div>
                                    </td>

                                    <!-- Rezervácia -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ guest.registration?.reservation_number }}</div>
                                        <div class="text-xs text-gray-400">{{ guest.registration?.registrant_name }}</div>
                                    </td>

                                    <!-- Miesto -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                        <span v-if="guest.table">{{ guest.table.name }} / {{ guest.seat_number }}</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>

                                    <!-- Platba -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button
                                            @click="togglePaid(guest)"
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full transition-colors cursor-pointer"
                                            :class="guest.paid
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 hover:bg-green-200'
                                                : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 hover:bg-red-200'"
                                        >
                                            {{ guest.paid ? '✓ Zaplatené' : '✗ Nezaplatené' }}
                                        </button>
                                    </td>

                                    <!-- Lístok -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span v-if="guest.ticket_issued" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                            Vydaný
                                        </span>
                                        <span v-else class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                            Čaká
                                        </span>
                                    </td>

                                    <!-- Akcia -->
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <Link :href="route('admin.registrations.show', guest.registration?.id)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                            Detail &rarr;
                                        </Link>
                                    </td>

                                </tr>
                                <tr v-if="guests.data.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        Neboli nájdené žiadne záznamy.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-between">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Zobrazuje sa <span class="font-medium">{{ guests.from || 0 }}</span> do <span class="font-medium">{{ guests.to || 0 }}</span> z <span class="font-medium">{{ guests.total }}</span>
                        </p>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                            <Link v-for="(link, i) in guests.links" :key="i" :href="link.url || '#'" v-html="link.label"
                                :class="[
                                    link.active ? 'z-10 bg-blue-50 dark:bg-blue-900/30 border-blue-500 text-blue-600 dark:text-blue-400' : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700',
                                    'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                                    !link.url ? 'opacity-50 cursor-not-allowed' : ''
                                ]" />
                        </nav>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
