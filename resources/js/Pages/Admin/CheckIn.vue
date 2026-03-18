<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, onMounted, nextTick } from 'vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const form = useForm({
    ticket_code: '',
});

const inputRef = ref(null);
const page = usePage();

const submit = () => {
    if (!form.ticket_code) return;
    
    // Auto pad to 3 digits if they entered fewer (e.g. '5' => '005')
    form.ticket_code = form.ticket_code.padStart(3, '0');

    form.post(route('admin.checkin.store'), {
        preserveScroll: true,
        onFinish: () => {
            form.reset('ticket_code');
            focusInput();
        }
    });
};

const focusInput = () => {
    nextTick(() => {
        if (inputRef.value) {
            inputRef.value.focus();
        }
    });
};

onMounted(() => {
    focusInput();
});
</script>

<template>
    <Head title="Check-in Skener" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Vyhľadanie Lístka</h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Search Input -->
                <div class="bg-white dark:bg-gray-800 p-6 sm:p-10 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 text-center relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl -mr-32 -mt-32 pointer-events-none"></div>
                    
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Kontrola Lístka</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-8">Zadajte 3-miestne ID napísané na lístku.</p>

                    <form @submit.prevent="submit" class="max-w-md mx-auto space-y-4">
                        <div class="flex items-center space-x-2">
                            <TextInput
                                ref="inputRef"
                                v-model="form.ticket_code"
                                type="text"
                                maxlength="3"
                                class="w-full text-center text-3xl font-mono tracking-widest py-4 bg-gray-50 dark:bg-gray-900 focus:ring-4 focus:ring-blue-500/20"
                                placeholder="001"
                                :disabled="form.processing"
                                autocomplete="off"
                            />
                            <PrimaryButton type="submit" class="py-6 px-8 text-lg" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Hľadať
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- Last Scan Status Messages -->
                <div v-if="page.props.flash.success || page.props.flash.error" class="transition-all duration-300">
                    
                    <!-- SUCCESS -->
                    <div v-if="page.props.flash.success" class="bg-green-50 dark:bg-green-900/30 border-2 border-green-500 rounded-2xl p-8 shadow-xl text-center relative overflow-hidden">
                        <div class="absolute -right-10 -bottom-10 opacity-20 transform rotate-12">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-48 w-48 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>

                        <div class="relative z-10">
                            <h3 class="text-3xl font-black text-green-700 dark:text-green-400 mb-2">Povolte Výstup!</h3>
                            <p class="text-green-600 dark:text-green-300 font-medium text-lg mb-6">{{ page.props.flash.success }}</p>
                            
                            <div v-if="page.props.flash.success_guest" class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow border border-green-100 dark:border-green-800 inline-block text-left min-w-[300px]">
                                <div class="flex items-center gap-3 mb-4 border-b pb-2">
                                    <h4 class="text-xl font-bold text-gray-900 dark:text-white">{{ page.props.flash.success_guest.name }}</h4>
                                    <span v-if="page.props.flash.success_guest.is_teacher" class="px-2.5 py-0.5 rounded-full text-sm font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400">Učiteľ</span>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg">
                                        <span class="text-gray-500 dark:text-gray-400 text-sm font-semibold uppercase">Stôl</span>
                                        <span class="text-2xl font-black text-gray-900 dark:text-white">{{ page.props.flash.success_guest.table }}</span>
                                    </div>
                                    <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg">
                                        <span class="text-gray-500 dark:text-gray-400 text-sm font-semibold uppercase">Miesto</span>
                                        <span class="text-2xl font-black text-gray-900 dark:text-white">{{ page.props.flash.success_guest.seat }}</span>
                                    </div>
                                    <div v-if="page.props.flash.success_guest.allergens" class="mt-4 p-3 bg-red-50 dark:bg-red-900/40 border border-red-200 dark:border-red-800 rounded-lg">
                                        <span class="block text-red-500 dark:text-red-400 text-xs font-bold uppercase mb-1">Pozor Alergény!</span>
                                        <span class="text-red-700 dark:text-red-300 font-medium">{{ page.props.flash.success_guest.allergens }}</span>
                                    </div>
                                    <div v-if="page.props.flash.success_guest.note" class="mt-2 p-3 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg">
                                        <span class="block text-gray-500 dark:text-gray-400 text-xs font-bold uppercase mb-1">Poznámka</span>
                                        <span class="text-gray-700 dark:text-gray-300">{{ page.props.flash.success_guest.note }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ERROR -->
                    <div v-if="page.props.flash.error" class="bg-red-50 dark:bg-red-900/30 border-2 border-red-500 rounded-2xl p-8 shadow-xl text-center relative overflow-hidden">
                         <div class="absolute -right-10 -bottom-10 opacity-20 transform -rotate-12">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-48 w-48 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="relative z-10">
                            <h3 class="text-3xl font-black text-red-700 dark:text-red-400 mb-2">Chyba Skenovania</h3>
                            <p class="text-red-600 dark:text-red-300 font-bold text-xl">{{ page.props.flash.error }}</p>

                            <!-- Already checked-in guest details -->
                            <div v-if="page.props.flash.already_checked_in_guest" class="mt-6 bg-white dark:bg-gray-800 rounded-xl p-6 shadow border border-red-100 dark:border-red-800 inline-block text-left min-w-[300px]">
                                <div class="flex items-center gap-3 mb-4 border-b pb-2">
                                    <h4 class="text-xl font-bold text-gray-900 dark:text-white">{{ page.props.flash.already_checked_in_guest.name }}</h4>
                                    <span v-if="page.props.flash.already_checked_in_guest.is_teacher" class="px-2.5 py-0.5 rounded-full text-sm font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400">Učiteľ</span>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg">
                                        <span class="text-gray-500 dark:text-gray-400 text-sm font-semibold uppercase">Stôl</span>
                                        <span class="text-2xl font-black text-gray-900 dark:text-white">{{ page.props.flash.already_checked_in_guest.table }}</span>
                                    </div>
                                    <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg">
                                        <span class="text-gray-500 dark:text-gray-400 text-sm font-semibold uppercase">Miesto</span>
                                        <span class="text-2xl font-black text-gray-900 dark:text-white">{{ page.props.flash.already_checked_in_guest.seat }}</span>
                                    </div>
                                    <div class="flex justify-between items-center bg-red-50 dark:bg-red-900/20 p-3 rounded-lg">
                                        <span class="text-gray-500 dark:text-gray-400 text-sm font-semibold uppercase">Check-in</span>
                                        <span class="text-lg font-bold text-red-600 dark:text-red-400">{{ page.props.flash.already_checked_in_guest.checked_in_at }}</span>
                                    </div>
                                    <div v-if="page.props.flash.already_checked_in_guest.allergens" class="mt-2 p-3 bg-red-50 dark:bg-red-900/40 border border-red-200 dark:border-red-800 rounded-lg">
                                        <span class="block text-red-500 dark:text-red-400 text-xs font-bold uppercase mb-1">Pozor Alergény!</span>
                                        <span class="text-red-700 dark:text-red-300 font-medium">{{ page.props.flash.already_checked_in_guest.allergens }}</span>
                                    </div>
                                    <div v-if="page.props.flash.already_checked_in_guest.note" class="mt-2 p-3 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg">
                                        <span class="block text-gray-500 dark:text-gray-400 text-xs font-bold uppercase mb-1">Poznámka</span>
                                        <span class="text-gray-700 dark:text-gray-300">{{ page.props.flash.already_checked_in_guest.note }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
