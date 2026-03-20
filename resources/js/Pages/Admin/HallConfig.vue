<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    config: Object,
    tables: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    num_rows:       props.config ? props.config.num_rows       : 3,
    tables_per_row: props.config ? props.config.tables_per_row : [4, 4, 4],
    seats_per_table: props.config ? props.config.seats_per_table : 8,
});

watch(() => form.num_rows, (newVal) => {
    let rows = parseInt(newVal) || 0;
    if (rows > 26) rows = 26;
    const current = form.tables_per_row.length;
    if (rows > current) {
        for (let i = current; i < rows; i++) form.tables_per_row.push(4);
    } else if (rows < current) {
        form.tables_per_row.splice(rows);
    }
});

const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');

// Compute which table names the new config produces
const newTableNames = computed(() => {
    const names = new Set();
    const rows = parseInt(form.num_rows) || 0;
    for (let r = 0; r < rows; r++) {
        const count = parseInt(form.tables_per_row[r]) || 0;
        for (let t = 1; t <= count; t++) {
            names.add(alphabet[r] + t);
        }
    }
    return names;
});

// Tables that exist now but won't exist after saving
const tablesToRemove = computed(() =>
    props.tables.filter(t => !newTableNames.value.has(t.name))
);

// Subset of tablesToRemove that have at least one guest assigned
const affectedTables = computed(() =>
    tablesToRemove.value.filter(t => t.guests_count > 0)
);

const totalAffectedGuests = computed(() =>
    affectedTables.value.reduce((sum, t) => sum + t.guests_count, 0)
);

// Slovak plural helper
const plural = (n, one, few, many) => n === 1 ? one : n < 5 ? few : many;

const confirmPending = ref(false);

const submit = () => {
    if (affectedTables.value.length > 0 && !confirmPending.value) {
        confirmPending.value = true;
        return;
    }
    confirmPending.value = false;
    form.post(route('admin.hall.update'));
};

const cancelConfirm = () => {
    confirmPending.value = false;
};
</script>

<template>
    <Head title="Konfigurácia Sály" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Konfigurácia sály</h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

                <!-- Warning: removing tables that have guests -->
                <div v-if="affectedTables.length > 0" class="mb-6 bg-red-50 dark:bg-red-900/30 border-l-4 border-red-500 p-4 rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-semibold text-red-700 dark:text-red-300 mb-2">
                                Nasledujúce stoly budú odobrané a majú priradených {{ plural(totalAffectedGuests, 'hosťa', 'hostí', 'hostí') }}:
                            </p>
                            <ul class="text-sm text-red-700 dark:text-red-300 space-y-0.5 mb-2">
                                <li v-for="table in affectedTables" :key="table.name">
                                    · Stôl <strong>{{ table.name }}</strong> — {{ table.guests_count }} {{ plural(table.guests_count, 'hosť', 'hostia', 'hostí') }}
                                </li>
                            </ul>
                            <p class="text-sm text-red-700 dark:text-red-300">
                                Celkovo <strong>{{ totalAffectedGuests }} {{ plural(totalAffectedGuests, 'hosť', 'hostia', 'hostí') }}</strong> stratí priradenie k miestu.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Info: removing empty tables (no guests affected) -->
                <div v-else-if="tablesToRemove.length > 0" class="mb-6 bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-400 p-4 rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                Budú odobrané prázdne stoly: <strong>{{ tablesToRemove.map(t => t.name).join(', ') }}</strong>. Žiadni hostia nie sú ovplyvnení.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100 dark:border-gray-700">
                    <form @submit.prevent="submit" class="space-y-6">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="num_rows" value="Počet radov (A-Z)" />
                                <TextInput
                                    id="num_rows"
                                    type="number"
                                    min="1"
                                    max="26"
                                    class="mt-1 block w-full"
                                    v-model="form.num_rows"
                                />
                                <InputError class="mt-2" :message="form.errors.num_rows" />
                            </div>

                            <div>
                                <InputLabel for="seats_per_table" value="Počet stoličiek na stôl" />
                                <TextInput
                                    id="seats_per_table"
                                    type="number"
                                    min="1"
                                    class="mt-1 block w-full"
                                    v-model="form.seats_per_table"
                                />
                                <InputError class="mt-2" :message="form.errors.seats_per_table" />
                            </div>
                        </div>

                        <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Rozloženie počtu stolov podľa radu</h3>

                            <div class="space-y-4">
                                <div v-for="(val, index) in form.tables_per_row" :key="index" class="flex items-center space-x-4">
                                    <span class="w-16 font-semibold text-gray-700 dark:text-gray-300 text-lg">Rad {{ alphabet[index] }}</span>
                                    <div class="flex-1">
                                        <TextInput
                                            :id="'row_' + index"
                                            type="number"
                                            min="0"
                                            class="mt-1 block w-full md:w-1/3"
                                            v-model="form.tables_per_row[index]"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8">
                            <!-- Inline confirmation when removing tables with guests -->
                            <div v-if="confirmPending" class="mb-4 p-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 rounded-lg">
                                <p class="text-sm font-semibold text-red-700 dark:text-red-300 mb-3">
                                    Naozaj chcete odobrať {{ affectedTables.length }} {{ plural(affectedTables.length, 'stôl', 'stoly', 'stolov') }}?
                                    Priradenia <strong>{{ totalAffectedGuests }} {{ plural(totalAffectedGuests, 'hosťa', 'hostí', 'hostí') }}</strong> budú zrušené.
                                </p>
                                <div class="flex gap-3">
                                    <button
                                        type="submit"
                                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors"
                                    >
                                        Áno, odobrať stoly
                                    </button>
                                    <button
                                        type="button"
                                        @click="cancelConfirm"
                                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg transition-colors"
                                    >
                                        Zrušiť
                                    </button>
                                </div>
                            </div>

                            <PrimaryButton v-else :disabled="form.processing">
                                Uložiť konfiguráciu
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
