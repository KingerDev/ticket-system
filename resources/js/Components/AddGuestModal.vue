<script setup>
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    action: { type: String, required: true },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    allergens: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const form = useForm({
    name: '',
    email: '',
    allergen_ids: [],
    is_vegan: false,
    is_vegetarian: false,
    is_teacher: false,
    allergen_note: '',
    note: '',
    send_confirmation: false,
});

const close = () => {
    form.reset();
    form.clearErrors();
    emit('close');
};

const submit = () => {
    form.post(props.action, { preserveScroll: true, onSuccess: close });
};
</script>

<template>
    <Modal :show="show" @close="close" maxWidth="2xl">
        <!-- novalidate: chyby hlási server po slovensky, nie prehliadač po anglicky. -->
        <form @submit.prevent="submit" novalidate class="p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-1">{{ title }}</h2>
            <p v-if="subtitle" class="text-sm text-gray-500 dark:text-gray-400 mb-6">{{ subtitle }}</p>

            <div v-if="form.errors.capacity" class="mb-4 rounded-md bg-red-50 dark:bg-red-900/30 p-3 text-sm text-red-700 dark:text-red-300">
                {{ form.errors.capacity }}
            </div>

            <div class="space-y-5 max-h-[60vh] overflow-y-auto pr-1">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="add_name">Meno a priezvisko <span class="text-red-500">*</span></InputLabel>
                        <TextInput id="add_name" type="text" class="mt-1 block w-full" v-model="form.name" />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="add_email">E-mail</InputLabel>
                        <TextInput id="add_email" type="email" class="mt-1 block w-full" v-model="form.email" />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>
                </div>

                <fieldset>
                    <legend class="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">Alergie na jedlo</legend>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2">
                        <label v-for="allergen in allergens" :key="allergen.id" class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" :value="allergen.id" v-model="form.allergen_ids"
                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                <span class="font-semibold text-gray-500 dark:text-gray-400 mr-0.5">{{ allergen.id }}.</span>{{ allergen.name }}
                            </span>
                        </label>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">Strava a rola</legend>
                    <div class="flex flex-wrap gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.is_vegan" class="rounded border-gray-300 text-green-600 focus:ring-green-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4" />
                            <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">Vegán</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.is_vegetarian" class="rounded border-gray-300 text-green-600 focus:ring-green-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4" />
                            <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">Vegetarián</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.is_teacher" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4" />
                            <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">Učiteľ / učiteľka</span>
                        </label>
                    </div>
                    <InputError class="mt-1" :message="form.errors.is_vegan" />
                </fieldset>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="add_allergen_note">Poznámka k strave</InputLabel>
                        <TextInput id="add_allergen_note" type="text" class="mt-1 block w-full" v-model="form.allergen_note" />
                        <InputError class="mt-1" :message="form.errors.allergen_note" />
                    </div>
                    <div>
                        <InputLabel for="add_note">Poznámka</InputLabel>
                        <TextInput id="add_note" type="text" class="mt-1 block w-full" v-model="form.note" />
                        <InputError class="mt-1" :message="form.errors.note" />
                    </div>
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="form.send_confirmation" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4" />
                    <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">Poslať hosťovi potvrdzovací e-mail</span>
                </label>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="close" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Zrušiť
                </button>
                <PrimaryButton :disabled="form.processing">
                    {{ form.processing ? 'Pridávam…' : 'Pridať hosťa' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
