<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    waitlist: Array,
    // { total, blocked, taken, free } – free je null, kým nie je nastavená sála
    capacity: Object,
});

const promotingId = ref(null);

const promote = (registracia) => {
    promotingId.value = registracia.id;
    router.post(route('admin.waitlist.promote', registracia.id), {}, {
        preserveScroll: true,
        onFinish: () => { promotingId.value = null; },
    });
};

const fits = (registracia) => props.capacity.free === null || registracia.guest_count <= props.capacity.free;

const hostiLabel = (n) => (n === 1 ? '1 hosť' : n < 5 ? `${n} hostia` : `${n} hostí`);
</script>

<template>
    <Head title="Náhradníci" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Náhradníci</h2>
        </template>

        <div class="py-12">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <p class="text-sm text-gray-600 dark:text-gray-400 px-4 sm:px-0">
                    Registrácie, pre ktoré pri odoslaní nebolo dosť voľných miest. Hostia zatiaľ neplatia.
                    Keď sa miesto uvoľní (storno, zrušená rezervácia stoličky), presuňte skupinu medzi riadne —
                    kontaktnej osobe príde e-mail, že môže prísť zaplatiť. Nikto sa nepresúva automaticky.
                </p>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 px-4 sm:px-0">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4 shadow-sm">
                        <div class="text-3xl font-black text-green-600 dark:text-green-400">{{ capacity.free ?? '—' }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Voľné miesta pre verejnosť</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4 shadow-sm">
                        <div class="text-3xl font-black text-amber-600 dark:text-amber-400">{{ waitlist.reduce((s, r) => s + r.guest_count, 0) }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Hostí medzi náhradníkmi</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4 shadow-sm">
                        <div class="text-3xl font-black text-purple-600 dark:text-purple-400">{{ capacity.blocked }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Rezervované organizátormi</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4 shadow-sm">
                        <div class="text-3xl font-black text-gray-700 dark:text-gray-200">{{ capacity.total }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Stoličiek v sále</div>
                    </div>
                </div>

                <div v-if="waitlist.length" class="bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Poradie podľa času registrácie</h3>
                    </div>

                    <ol class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-for="(r, index) in waitlist" :key="r.id" class="p-5 flex flex-wrap items-start justify-between gap-4">
                            <div class="flex gap-4 min-w-0">
                                <span class="flex-shrink-0 w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300 text-sm font-bold flex items-center justify-center">
                                    {{ index + 1 }}
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Link :href="route('admin.registrations.show', r.id)" class="font-semibold text-gray-900 dark:text-white hover:underline">
                                            {{ r.reservation_number }}
                                        </Link>
                                        <span class="text-gray-500 dark:text-gray-400">{{ r.registrant_name }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                            {{ hostiLabel(r.guest_count) }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-1 break-words">{{ r.guests.join(', ') }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 break-all">
                                        Registrácia {{ r.waitlisted_at }} · {{ r.registrant_email }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-col items-end gap-1">
                                <button
                                    @click="promote(r)"
                                    :disabled="!fits(r) || promotingId === r.id"
                                    class="px-4 py-2 rounded-md bg-green-600 hover:bg-green-700 text-white text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    {{ promotingId === r.id ? 'Presúvam…' : 'Presunúť medzi riadne' }}
                                </button>
                                <span v-if="!fits(r)" class="text-xs text-gray-500 dark:text-gray-400">
                                    Nie je dosť voľných miest ({{ capacity.free }}).
                                </span>
                            </div>
                        </li>
                    </ol>
                </div>

                <div v-else class="bg-white dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700 p-10 text-center text-gray-500 dark:text-gray-400">
                    Medzi náhradníkmi nie je nikto.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
