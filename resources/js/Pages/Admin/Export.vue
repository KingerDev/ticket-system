<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = ref({
    sort_by: 'name',
    format: 'pdf',
    separate_teachers: false,
    include_allergens: false,
    include_seat: false,
    include_ticket: false,
});

const loading = ref(false);

const submit = () => {
    loading.value = true;

    const params = new URLSearchParams({
        sort_by: form.value.sort_by,
        format: form.value.format,
        separate_teachers: form.value.separate_teachers ? '1' : '0',
        include_allergens: form.value.include_allergens ? '1' : '0',
        include_seat: form.value.include_seat ? '1' : '0',
        include_ticket: form.value.include_ticket ? '1' : '0',
    });

    // Use a form POST to trigger file download
    const formEl = document.createElement('form');
    formEl.method = 'POST';
    formEl.action = route('admin.export.download');

    // CSRF token
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    formEl.appendChild(csrf);

    for (const [key, value] of params.entries()) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        formEl.appendChild(input);
    }

    document.body.appendChild(formEl);
    formEl.submit();
    document.body.removeChild(formEl);

    setTimeout(() => { loading.value = false; }, 2000);
};
</script>

<template>
    <Head title="Export zoznamu" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Export zoznamu hostí</h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700 p-8 space-y-8">

                    <!-- Radenie -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Zoradiť podľa</h3>
                        <div class="flex gap-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="form.sort_by" value="name" class="text-blue-600 focus:ring-blue-500" />
                                <span class="text-gray-800 dark:text-gray-200 text-sm font-medium">Mena (A–Z)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="form.sort_by" value="table" class="text-blue-600 focus:ring-blue-500" />
                                <span class="text-gray-800 dark:text-gray-200 text-sm font-medium">Stola a miesta</span>
                            </label>
                        </div>
                    </div>

                    <!-- Možnosti -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Možnosti</h3>
                        <div class="space-y-3">
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    type="checkbox"
                                    v-model="form.separate_teachers"
                                    class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                                />
                                <div>
                                    <span class="text-gray-800 dark:text-gray-200 text-sm font-medium">Učitelia ako samostatný zoznam</span>
                                    <p class="text-xs text-gray-400">Učitelia budú v samostatnej sekcii / liste</p>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    type="checkbox"
                                    v-model="form.include_seat"
                                    class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <div>
                                    <span class="text-gray-800 dark:text-gray-200 text-sm font-medium">Zahrnúť stôl a miesto</span>
                                    <p class="text-xs text-gray-400">Pridá stĺpce Stôl a Číslo miesta</p>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    type="checkbox"
                                    v-model="form.include_allergens"
                                    class="w-4 h-4 rounded border-gray-300 text-red-500 focus:ring-red-400"
                                />
                                <div>
                                    <span class="text-gray-800 dark:text-gray-200 text-sm font-medium">Zahrnúť alergény</span>
                                    <p class="text-xs text-gray-400">Pridá stĺpec s alergénmi pre každého hosťa</p>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    type="checkbox"
                                    v-model="form.include_ticket"
                                    class="w-4 h-4 rounded border-gray-300 text-yellow-500 focus:ring-yellow-400"
                                />
                                <div>
                                    <span class="text-gray-800 dark:text-gray-200 text-sm font-medium">Zahrnúť číslo lístka</span>
                                    <p class="text-xs text-gray-400">Pridá stĺpec s 3-miestnym kódom lístka</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Formát -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Formát exportu</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <label
                                class="flex items-center gap-3 p-4 rounded-lg border-2 cursor-pointer transition-colors"
                                :class="form.format === 'pdf'
                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                    : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'"
                            >
                                <input type="radio" v-model="form.format" value="pdf" class="sr-only" />
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM8.5 17.5c-.3 0-.5-.1-.7-.3-.4-.4-.4-1 0-1.4l5-5c.4-.4 1-.4 1.4 0 .4.4.4 1 0 1.4l-5 5c-.2.2-.4.3-.7.3z"/>
                                </svg>
                                <div>
                                    <div class="font-semibold text-gray-900 dark:text-white text-sm">PDF</div>
                                    <div class="text-xs text-gray-400">Na tlač, A4</div>
                                </div>
                            </label>

                            <label
                                class="flex items-center gap-3 p-4 rounded-lg border-2 cursor-pointer transition-colors"
                                :class="form.format === 'excel'
                                    ? 'border-green-500 bg-green-50 dark:bg-green-900/20'
                                    : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'"
                            >
                                <input type="radio" v-model="form.format" value="excel" class="sr-only" />
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-600 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM9 13h2l1.5 2.5L14 13h2l-2.5 4 2.5 4h-2l-1.5-2.5L11 21H9l2.5-4L9 13z"/>
                                </svg>
                                <div>
                                    <div class="font-semibold text-gray-900 dark:text-white text-sm">Excel</div>
                                    <div class="text-xs text-gray-400">.xlsx, editovateľný</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="pt-2">
                        <button
                            @click="submit"
                            :disabled="loading"
                            class="w-full py-3 px-6 rounded-lg font-semibold text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            :class="form.format === 'excel' ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700'"
                        >
                            <span v-if="loading">Generujem...</span>
                            <span v-else>Stiahnuť {{ form.format === 'excel' ? 'Excel' : 'PDF' }}</span>
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
