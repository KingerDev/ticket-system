<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import TableMap from './TableMap.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    tables: Array,
    guest: Object,
    error: String,
});

const ticketCode = ref('');
const inputRef = ref(null);

const selectedSeat = computed(() => {
    if (!props.guest?.table_id || !props.guest?.seat_number) return null;
    return { tableId: props.guest.table_id, seatNum: props.guest.seat_number };
});

const checkingIn = ref(false);

const confirmArrival = () => {
    if (!props.guest || props.guest.checked_in) return;

    checkingIn.value = true;
    router.post(route('admin.seating.check_in'), { guest_id: props.guest.id }, {
        preserveScroll: true,
        onFinish: () => { checkingIn.value = false; },
    });
};

const lookup = () => {
    if (!ticketCode.value) return;
    router.get(route('admin.seating.lookup'), {
        ticket_code: ticketCode.value,
    }, { preserveState: false });
};
</script>

<template>
    <Head title="Usádzač" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Usádzač hostí</h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- Search bar -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Zadajte 3-miestne číslo lístka a nájdite miesto hosťa na mape.</p>
                    <form @submit.prevent="lookup" class="flex items-center gap-3 max-w-sm">
                        <TextInput
                            ref="inputRef"
                            v-model="ticketCode"
                            type="text"
                            maxlength="3"
                            placeholder="001"
                            autocomplete="off"
                            class="w-32 text-center text-2xl font-mono tracking-widest"
                        />
                        <PrimaryButton type="submit">Nájsť</PrimaryButton>
                    </form>
                </div>

                <!-- Error -->
                <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 rounded-xl px-5 py-4 text-sm font-medium">
                    {{ error }}
                </div>

                <!-- Guest info card -->
                <div v-if="guest" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-1">
                                <span class="text-2xl font-black text-gray-900 dark:text-white">{{ guest.name }}</span>
                                <span v-if="guest.is_teacher" class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400">Učiteľ</span>
                            </div>
                            <div v-if="guest.allergens" class="text-sm text-red-600 dark:text-red-400 font-medium">
                                Alergény: {{ guest.allergens }}
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <div v-if="guest.table_name" class="text-center bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg px-6 py-3">
                                <div class="text-xs font-semibold text-blue-500 dark:text-blue-400 uppercase tracking-wider mb-0.5">Stôl</div>
                                <div class="text-3xl font-black text-blue-700 dark:text-blue-300">{{ guest.table_name }}</div>
                            </div>
                            <div v-if="guest.seat_number" class="text-center bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg px-6 py-3">
                                <div class="text-xs font-semibold text-blue-500 dark:text-blue-400 uppercase tracking-wider mb-0.5">Miesto</div>
                                <div class="text-3xl font-black text-blue-700 dark:text-blue-300">{{ guest.seat_number }}</div>
                            </div>
                            <div v-if="!guest.table_name" class="text-center bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg px-6 py-3">
                                <div class="text-sm text-gray-500 dark:text-gray-400">Miesto ešte nepridelené</div>
                            </div>
                        </div>
                    </div>

                    <!-- Príchod hosťa -->
                    <div class="mt-5 pt-5 border-t border-gray-200 dark:border-gray-700 flex flex-wrap items-center gap-4">
                        <div
                            v-if="guest.checked_in"
                            class="flex items-center gap-2 px-4 py-2 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800"
                        >
                            <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-sm font-semibold text-green-700 dark:text-green-300">
                                Príchod potvrdený o {{ guest.checked_in_at }}
                            </span>
                        </div>

                        <template v-else>
                            <button
                                @click="confirmArrival"
                                :disabled="checkingIn"
                                class="px-6 py-3 rounded-lg bg-green-600 hover:bg-green-700 text-white text-base font-bold shadow transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ checkingIn ? 'Zapisujem…' : 'Potvrdiť príchod' }}
                            </button>
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                Hosť zatiaľ nie je zapísaný pri vstupe.
                            </span>
                        </template>
                    </div>
                </div>

                <!-- Map -->
                <div class="bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden">
                    <div class="h-[620px] relative overflow-hidden">
                        <TableMap
                            :tables="tables"
                            :assignMode="false"
                            :selectedSeat="selectedSeat"
                            :embedded="true"
                        />
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
