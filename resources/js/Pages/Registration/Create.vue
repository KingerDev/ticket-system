<script setup>
import { ref, computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const ALLERGENS = [
    { id: 1,  name: 'Obilniny s lepkom' },
    { id: 2,  name: 'Kôrovce' },
    { id: 3,  name: 'Vajcia' },
    { id: 4,  name: 'Ryby' },
    { id: 5,  name: 'Arašidy' },
    { id: 6,  name: 'Sója' },
    { id: 7,  name: 'Mlieko' },
    { id: 8,  name: 'Orechy' },
    { id: 9,  name: 'Zeler' },
    { id: 10, name: 'Horčica' },
    { id: 11, name: 'Sezamové semená' },
    { id: 12, name: 'Siričitany' },
    { id: 13, name: 'Lupina' },
    { id: 14, name: 'Mäkkýše' },
];

const newGuest = () => ({
    name: '',
    email: '',
    allergen_ids: [],
    is_vegan: false,
    is_vegetarian: false,
    allergen_note: '',
    note: '',
});

const form = useForm({
    guests: [newGuest()],
});

const addGuest = () => form.guests.push(newGuest());

const removeGuest = (index) => {
    if (form.guests.length > 1) form.guests.splice(index, 1);
};

const submit = () => form.post(route('register.store'));
</script>

<template>
    <Head title="Registrácia na Ples" />

    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8 flex items-center justify-center">
        <div class="max-w-3xl w-full space-y-8 bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700">
            <div>
                <h2 class="text-center text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-purple-600 dark:from-blue-400 dark:to-purple-400">
                    Registrácia na Ples
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                    Vyplňte formulár pre každého hosťa. Potvrdenie bude zaslané na e-mail prvého hosťa.
                </p>
            </div>

            <form @submit.prevent="submit" class="mt-8 space-y-6">
                <div class="space-y-6">
                    <div
                        v-for="(guest, index) in form.guests"
                        :key="index"
                        class="relative bg-gray-50 dark:bg-gray-700/50 p-6 rounded-xl border border-gray-200 dark:border-gray-600"
                    >
                        <button
                            v-if="form.guests.length > 1"
                            @click.prevent="removeGuest(index)"
                            type="button"
                            class="absolute top-4 right-4 text-red-500 hover:text-red-700 dark:text-red-400"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>

                        <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-4 uppercase tracking-wider">
                            Hosť #{{ index + 1 }}
                        </h4>

                        <div class="space-y-4">
                            <!-- Name + Email -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel :for="'name_' + index" value="Meno a priezvisko" />
                                    <TextInput
                                        :id="'name_' + index"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="guest.name"
                                        required
                                        :autofocus="index === 0"
                                    />
                                    <InputError class="mt-1" :message="form.errors[`guests.${index}.name`]" />
                                </div>
                                <div>
                                    <InputLabel :for="'email_' + index">
                                        Email
                                        <span v-if="index === 0" class="text-red-500">*</span>
                                        <span v-else class="text-gray-400 text-xs ml-1">(nepovinné)</span>
                                    </InputLabel>
                                    <TextInput
                                        :id="'email_' + index"
                                        type="email"
                                        class="mt-1 block w-full"
                                        v-model="guest.email"
                                        :required="index === 0"
                                    />
                                    <InputError class="mt-1" :message="form.errors[`guests.${index}.email`]" />
                                </div>
                            </div>

                            <!-- Allergens 1–14 -->
                            <div>
                                <InputLabel value="Alergény (podľa slovenských noriem)" class="mb-2" />
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2">
                                    <label
                                        v-for="allergen in ALLERGENS"
                                        :key="allergen.id"
                                        class="flex items-center gap-2 cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="allergen.id"
                                            v-model="guest.allergen_ids"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4"
                                        />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            <span class="font-semibold text-gray-500 dark:text-gray-400 mr-0.5">{{ allergen.id }}.</span>{{ allergen.name }}
                                        </span>
                                    </label>
                                </div>
                                <InputError class="mt-1" :message="form.errors[`guests.${index}.allergen_ids`]" />
                            </div>

                            <!-- Vegan / Vegetarian -->
                            <div class="flex gap-6">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="guest.is_vegan" class="rounded border-gray-300 text-green-600 focus:ring-green-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">Vegán</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="guest.is_vegetarian" class="rounded border-gray-300 text-green-600 focus:ring-green-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">Vegetarián</span>
                                </label>
                            </div>

                            <!-- Allergen note + General note -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel :for="'allergen_note_' + index" value="Poznámka k alergénom" />
                                    <textarea
                                        :id="'allergen_note_' + index"
                                        v-model="guest.allergen_note"
                                        rows="2"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                        placeholder="Napr. silná alergia na orechy..."
                                    ></textarea>
                                    <InputError class="mt-1" :message="form.errors[`guests.${index}.allergen_note`]" />
                                </div>
                                <div>
                                    <InputLabel :for="'note_' + index" value="Všeobecná poznámka" />
                                    <textarea
                                        :id="'note_' + index"
                                        v-model="guest.note"
                                        rows="2"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                        placeholder="Akákoľvek iná informácia..."
                                    ></textarea>
                                    <InputError class="mt-1" :message="form.errors[`guests.${index}.note`]" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add guest -->
                <button
                    @click.prevent="addGuest"
                    type="button"
                    class="w-full flex justify-center items-center py-3 px-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl text-sm font-medium text-gray-600 dark:text-gray-400 hover:border-blue-500 hover:text-blue-500 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Pridať ďalšieho hosťa
                </button>

                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <PrimaryButton
                        class="w-full justify-center py-4 text-lg bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 shadow-md hover:shadow-lg"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        Potvrdiť rezerváciu (Počet hostí: {{ form.guests.length }})
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>
