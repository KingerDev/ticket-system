<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    contactEmail: { type: String, default: null },
});
</script>

<template>
    <Head title="Rezervácia je ukončená" />

    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8 flex items-center justify-center">
        <div class="max-w-xl w-full p-8 sm:p-12 text-center bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-4 tracking-tight">
                Rezervácia lístkov je ukončená
            </h1>
            <p class="text-lg text-gray-600 dark:text-gray-300">
                Rezervácia lístkov na Beánie EF UMB 2026 je už uzavretá. Ďakujeme za váš záujem.
            </p>
            <p class="mt-4 text-gray-500 dark:text-gray-400">
                Ak máte otázku k svojej rezervácii, kontaktujte nás, prosím<template v-if="contactEmail">
                na <a :href="`mailto:${contactEmail}`" class="font-semibold underline">{{ contactEmail }}</a></template>.
            </p>
        </div>
    </div>
</template>
