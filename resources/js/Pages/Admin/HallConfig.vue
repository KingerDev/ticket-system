<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    config: Object,
});

const form = useForm({
    num_rows: props.config ? props.config.num_rows : 3,
    tables_per_row: props.config ? props.config.tables_per_row : [4, 4, 4],
    seats_per_table: props.config ? props.config.seats_per_table : 8,
});

// Watch for row additions or removals
watch(() => form.num_rows, (newVal) => {
    let rows = parseInt(newVal) || 0;
    if (rows > 26) rows = 26; // max Z
    
    const currentLengths = form.tables_per_row.length;
    if (rows > currentLengths) {
        // Add default columns for new rows
        for (let i = currentLengths; i < rows; i++) {
            form.tables_per_row.push(4);
        }
    } else if (rows < currentLengths) {
        // Remove extra columns
        form.tables_per_row.splice(rows);
    }
});

const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');

const submit = () => {
    form.post(route('admin.hall.update'));
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
                
                <div v-if="config?.locked" class="mb-6 bg-yellow-50 dark:bg-yellow-900/30 border-l-4 border-yellow-400 p-4 rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700 dark:text-yellow-300 font-medium">
                                Konfigurácia je uzamknutá. Sála bola vygenerovaná a lístky už boli vydané. Tieto nastavenia už nie je možné zmeniť.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100 dark:border-gray-700 relative">
                    <!-- Overlay if locked -->
                    <div v-if="config?.locked" class="absolute inset-0 bg-gray-50/50 dark:bg-gray-900/50 z-10 cursor-not-allowed"></div>

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
                                    :disabled="config?.locked"
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
                                    :disabled="config?.locked"
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
                                            :disabled="config?.locked"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8">
                            <PrimaryButton :disabled="config?.locked || form.processing">
                                Uložiť konfiguráciu a generovať stoly
                            </PrimaryButton>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
