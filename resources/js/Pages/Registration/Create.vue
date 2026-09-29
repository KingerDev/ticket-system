<script setup>
import { ref, computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    noteMax: { type: Number, default: 1000 },
    allergens: { type: Array, required: true },
    // Kde a kedy sa platí; kým ho organizátori nezverejnia, je prázdne.
    paymentInfo: { type: String, default: null },
});

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

// Meno aj priezvisko je povinné pre každého hosťa – aspoň dve slová.
const FULL_NAME_RE = /^\p{L}[\p{L}\p{M}'\-.]*(\s+\p{L}[\p{L}\p{M}'\-.]*)+$/u;

// Rovnaká kontrola ako na serveri: doména musí mať bodku a koncovku
// (prehliadač sám pustí aj „jana@gmail“).
const EMAIL_RE = /^[^@\s]+@[^@\s]+\.\p{L}{2,}$/u;

const nameErrors = ref({});
const emailErrors = ref({});

const clearNameError = (index) => {
    delete nameErrors.value[index];
};

const clearEmailError = (index) => {
    delete emailErrors.value[index];
};

const validateGuests = () => {
    const names = {};
    const emails = {};

    form.guests.forEach((guest, index) => {
        const name = (guest.name ?? '').trim();
        const email = (guest.email ?? '').trim();

        if (name === '') {
            names[index] = 'Zadajte meno a priezvisko.';
        } else if (!FULL_NAME_RE.test(name)) {
            names[index] = 'Zadajte meno aj priezvisko (napr. Jana Nováková).';
        }

        if (email === '') {
            if (index === 0) emails[index] = 'Zadajte e-mail – pošleme naň potvrdenie rezervácie.';
        } else if (!EMAIL_RE.test(email)) {
            emails[index] = 'Zadajte celú e-mailovú adresu (napr. jana.novakova@gmail.com).';
        }
    });

    nameErrors.value = names;
    emailErrors.value = emails;

    return Object.keys(names).length === 0 && Object.keys(emails).length === 0;
};

const addGuest = () => {
    form.guests.push(newGuest());
    nameErrors.value = {};
    emailErrors.value = {};
};

// Kontaktná osoba (1. hosť) sa odstrániť nedá – inak by sa ňou potichu stal ďalší hosť.
const removeGuest = (index) => {
    if (index > 0 && form.guests.length > 1) {
        form.guests.splice(index, 1);
        nameErrors.value = {};
        emailErrors.value = {};
    }
};

// Vegán a vegetarián sa vylučujú – na serveri ostávajú dve polia, vo formulári jedna voľba.
const dietOf = (guest) => (guest.is_vegan ? 'vegan' : guest.is_vegetarian ? 'vegetarian' : 'none');

const setDiet = (guest, diet) => {
    guest.is_vegan = diet === 'vegan';
    guest.is_vegetarian = diet === 'vegetarian';
};

const DIETS = [
    { value: 'none', label: 'Žiadna' },
    { value: 'vegetarian', label: 'Vegetarián' },
    { value: 'vegan', label: 'Vegán' },
];

/** Slovenské skloňovanie: 1 hosťa, 2 hostí, 5 hostí. */
const guestCountLabel = computed(() =>
    form.guests.length === 1 ? '1 hosťa' : `${form.guests.length} hostí`
);

const hasErrors = computed(() =>
    Object.keys(form.errors).length > 0
        || Object.keys(nameErrors.value).length > 0
        || Object.keys(emailErrors.value).length > 0
);

const submit = () => {
    if (!validateGuests()) return;

    form.post(route('register.store'));
};
</script>

<template>
    <Head title="Registrácia na Beánie EF UMB 2026" />

    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8 flex items-center justify-center">
        <div class="max-w-3xl w-full space-y-8 bg-white dark:bg-gray-800 px-4 py-8 sm:p-8 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700">
            <div>
                <h2 class="text-center text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-purple-600 dark:from-blue-400 dark:to-purple-400">
                    Registrácia na Beánie EF UMB 2026
                </h2>
                <p class="mt-3 text-center text-sm text-gray-600 dark:text-gray-400">
                    Jednou registráciou prihlásite aj viac ľudí naraz – stačí pridať ďalšieho hosťa.
                </p>

                <!-- Čo bude nasledovať: bez tohto ľudia po odoslaní čakajú lístky, ktoré ešte neprídu. -->
                <ol class="mt-5 space-y-2 text-sm text-gray-600 dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900/40 rounded-xl p-4">
                    <li class="flex gap-3">
                        <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center">1</span>
                        <span>Vyplníte údaje za každého hosťa a odošlete registráciu.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center">2</span>
                        <span>Na e-mail vám pošleme potvrdenie s číslom rezervácie.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center">3</span>
                        <span>
                            Rezerváciu uhradíte <strong>v hotovosti, osobne u organizátorov</strong>
                            <template v-if="paymentInfo"> ({{ paymentInfo }})</template><template v-else> – termíny a miesto včas zverejníme</template>.
                            Po úhrade vám pridelíme miesta pri stole a vydáme lístky.
                        </span>
                    </li>
                </ol>
            </div>

            <form @submit.prevent="submit" class="mt-8 space-y-6">
                <!-- Súhrn chýb: pri dlhšom formulári nie je vidieť, kde presne je problém. -->
                <div
                    v-if="hasErrors"
                    class="flex gap-3 rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/20 p-4"
                    role="alert"
                >
                    <svg class="h-5 w-5 flex-shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    </svg>
                    <p class="text-sm text-red-700 dark:text-red-300">
                        Registráciu sa nepodarilo odoslať. Skontrolujte polia označené červenou a skúste to znova.
                    </p>
                </div>

                <div class="space-y-6">
                    <div
                        v-for="(guest, index) in form.guests"
                        :key="index"
                        class="relative bg-gray-50 dark:bg-gray-700/50 p-6 rounded-xl border border-gray-200 dark:border-gray-600"
                    >
                        <button
                            v-if="index > 0"
                            @click.prevent="removeGuest(index)"
                            type="button"
                            title="Odstrániť tohto hosťa"
                            :aria-label="`Odstrániť ${index + 1}. hosťa`"
                            class="absolute top-4 right-4 text-red-500 hover:text-red-700 dark:text-red-400"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>

                        <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-4 uppercase tracking-wider flex items-center gap-2 flex-wrap">
                            {{ index + 1 }}. hosť
                            <span
                                v-if="index === 0"
                                class="normal-case tracking-normal text-xs font-medium px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300"
                            >
                                kontaktná osoba
                            </span>
                        </h4>

                        <div class="space-y-5">
                            <!-- Meno + e-mail -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel :for="'name_' + index">
                                        Meno a priezvisko
                                        <span class="text-red-500">*</span>
                                    </InputLabel>
                                    <TextInput
                                        :id="'name_' + index"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="guest.name"
                                        required
                                        autocomplete="name"
                                        placeholder="Napr. Jana Nováková"
                                        :autofocus="index === 0"
                                        @input="clearNameError(index)"
                                    />
                                    <InputError class="mt-1" :message="nameErrors[index] || form.errors[`guests.${index}.name`]" />
                                </div>
                                <div>
                                    <InputLabel :for="'email_' + index">
                                        E-mail
                                        <span v-if="index === 0" class="text-red-500">*</span>
                                        <span v-else class="text-gray-400 text-xs ml-1 font-normal">(nepovinné)</span>
                                    </InputLabel>
                                    <TextInput
                                        :id="'email_' + index"
                                        type="email"
                                        class="mt-1 block w-full"
                                        v-model="guest.email"
                                        autocomplete="email"
                                        :placeholder="index === 0 ? 'jana.novakova@email.sk' : ''"
                                        :required="index === 0"
                                        @input="clearEmailError(index)"
                                    />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        <template v-if="index === 0">Sem pošleme potvrdenie s číslom rezervácie.</template>
                                        <template v-else>Vyplňte, ak má hosť dostať potvrdenie aj sám.</template>
                                    </p>
                                    <InputError class="mt-1" :message="emailErrors[index] || form.errors[`guests.${index}.email`]" />
                                </div>
                            </div>

                            <!-- Alergény 1–14 -->
                            <fieldset>
                                <legend class="block font-medium text-sm text-gray-700 dark:text-gray-300">
                                    Alergie na jedlo
                                </legend>
                                <p class="mt-1 mb-3 text-xs text-gray-500 dark:text-gray-400">
                                    Označte, čo hosť nesmie jesť – kuchyňa podľa toho pripraví jedlo.
                                    Čísla zodpovedajú oficiálnemu zoznamu 14 alergénov, ktorý používajú reštaurácie.
                                    Ak hosť alergiu nemá, nechajte prázdne.
                                </p>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2">
                                    <label
                                        v-for="allergen in allergens"
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
                            </fieldset>

                            <!-- Vegán / vegetarián -->
                            <fieldset>
                                <legend class="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">
                                    Špeciálna strava
                                </legend>
                                <div class="flex flex-wrap gap-6">
                                    <label
                                        v-for="diet in DIETS"
                                        :key="diet.value"
                                        class="flex items-center gap-2 cursor-pointer"
                                    >
                                        <input
                                            type="radio"
                                            :name="'diet_' + index"
                                            :value="diet.value"
                                            :checked="dietOf(guest) === diet.value"
                                            @change="setDiet(guest, diet.value)"
                                            class="border-gray-300 text-green-600 focus:ring-green-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4"
                                        />
                                        <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">{{ diet.label }}</span>
                                    </label>
                                </div>
                                <InputError class="mt-1" :message="form.errors[`guests.${index}.is_vegan`]" />
                            </fieldset>

                            <!-- Poznámky -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel :for="'allergen_note_' + index" value="Doplnenie k alergiám" />
                                    <textarea
                                        :id="'allergen_note_' + index"
                                        v-model="guest.allergen_note"
                                        rows="2"
                                        :maxlength="noteMax"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                        placeholder="Napr. celiakia, silná alergia na orechy"
                                    ></textarea>
                                    <p class="mt-1 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                        <span>Nepovinné</span>
                                        <span :class="{ 'text-amber-600 dark:text-amber-400': guest.allergen_note.length >= noteMax * 0.9 }">
                                            {{ guest.allergen_note.length }} / {{ noteMax }}
                                        </span>
                                    </p>
                                    <InputError class="mt-1" :message="form.errors[`guests.${index}.allergen_note`]" />
                                </div>
                                <div>
                                    <InputLabel :for="'note_' + index" value="Odkaz pre organizátorov" />
                                    <textarea
                                        :id="'note_' + index"
                                        v-model="guest.note"
                                        rows="2"
                                        :maxlength="noteMax"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                        placeholder="Napr. chceme sedieť spolu, potrebujem bezbariérový prístup"
                                    ></textarea>
                                    <p class="mt-1 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                        <span>Nepovinné</span>
                                        <span :class="{ 'text-amber-600 dark:text-amber-400': guest.note.length >= noteMax * 0.9 }">
                                            {{ guest.note.length }} / {{ noteMax }}
                                        </span>
                                    </p>
                                    <InputError class="mt-1" :message="form.errors[`guests.${index}.note`]" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pridanie hosťa -->
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

                <InputError class="text-center" :message="form.errors.guests" />

                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <PrimaryButton
                        class="w-full justify-center py-4 text-lg bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 shadow-md hover:shadow-lg"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        <span v-if="form.processing">Odosielam…</span>
                        <span v-else>Odoslať registráciu pre {{ guestCountLabel }}</span>
                    </PrimaryButton>
                    <p class="mt-3 text-center text-xs text-gray-500 dark:text-gray-400">
                        Polia označené <span class="text-red-500">*</span> sú povinné. Odoslaním vás ešte k ničomu nezaväzujeme – platí sa až v hotovosti osobne u organizátorov.
                    </p>
                </div>
            </form>
        </div>
    </div>
</template>
