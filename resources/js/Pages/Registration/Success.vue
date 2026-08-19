<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    reservation_number: {
        type: String,
        required: true,
    }
});
</script>

<template>
    <Head title="Úspešná Registrácia" />

    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8 flex items-center justify-center">
        <div class="max-w-2xl w-full p-8 sm:p-12 text-center relative overflow-hidden bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700">
            
            <!-- Confetti / Success Graphics (Pure CSS / SVG) -->
            <div class="absolute top-0 left-0 w-full h-full pointer-events-none overflow-hidden">
                <div class="absolute top-[-10%] left-[-10%] w-40 h-40 bg-green-400 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
                <div class="absolute top-[-10%] right-[-10%] w-40 h-40 bg-blue-400 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>
                <div class="absolute bottom-[-20%] left-[20%] w-40 h-40 bg-purple-400 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000"></div>
            </div>

            <div class="relative z-10 w-24 h-24 mx-auto mb-8 bg-green-100 text-green-500 rounded-full flex items-center justify-center shadow-inner">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            <h2 class="text-4xl font-extrabold text-gray-900 dark:text-white mb-4 tracking-tight">
                Ďakujeme za prihlásenie!
            </h2>
            
            <p class="text-lg text-gray-600 dark:text-gray-400 mb-8 max-w-lg mx-auto leading-relaxed">
                Vaša registrácia na <strong class="text-gray-900 dark:text-gray-200">Beánie EF UMB 2026</strong> bola úspešne prijatá.
            </p>

            <div class="bg-gray-50 dark:bg-gray-900/50 rounded-2xl p-6 sm:p-8 border border-gray-100 dark:border-gray-700 mb-8 max-w-md mx-auto text-left shadow-sm">
                <h3 class="font-semibold text-gray-900 dark:text-white text-lg mb-4 flex items-center">
                    <svg class="w-5 h-5 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Dôležité informácie
                </h3>
                
                <ul class="space-y-4">
                    <li class="flex items-start">
                        <div class="flex-shrink-0 mt-1">
                            <span class="w-2 h-2 rounded-full bg-blue-500 inline-block"></span>
                        </div>
                        <p class="ml-3 text-sm text-gray-600 dark:text-gray-400">
                            Lístky Vám budú doručené až po uhradení platby a výbere stola.
                        </p>
                    </li>
                </ul>
            </div>

            <div class="flex justify-center">
                <Link
                    :href="route('register')"
                    class="inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-xl text-blue-700 bg-blue-100 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                    Späť na registráciu
                </Link>
            </div>

        </div>
    </div>
</template>

<style scoped>
@keyframes blob {
    0% { transform: translate(0px, 0px) scale(1); }
    33% { transform: translate(30px, -50px) scale(1.1); }
    66% { transform: translate(-20px, 20px) scale(0.9); }
    100% { transform: translate(0px, 0px) scale(1); }
}
.animate-blob {
    animation: blob 7s infinite;
}
.animation-delay-2000 {
    animation-delay: 2s;
}
.animation-delay-4000 {
    animation-delay: 4s;
}
</style>
