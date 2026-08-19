<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    actions: Object,
    users: Array,
});

const search = ref(props.filters.search ?? '');
const userId = ref(props.filters.user_id ?? '');
const action = ref(props.filters.action ?? '');

let debounce = null;

const applyFilters = () => {
    router.get(route('admin.activity_log'), {
        search: search.value || undefined,
        user_id: userId.value || undefined,
        action: action.value || undefined,
    }, { preserveState: true, replace: true });
};

watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 300);
});

watch([userId, action], applyFilters);

const resetFilters = () => {
    search.value = '';
    userId.value = '';
    action.value = '';
};

// Rozbalenie detailu zmien
const expanded = ref(new Set());

const toggle = (id) => {
    const next = new Set(expanded.value);
    next.has(id) ? next.delete(id) : next.add(id);
    expanded.value = next;
};

const formatValue = (value) => {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'áno' : 'nie';
    if (Array.isArray(value)) return value.length ? value.join(', ') : '—';
    return String(value);
};
</script>

<template>
    <Head title="Záznam činnosti" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Záznam činnosti
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Prehľad zmien v administrácii – kto, kedy a čo urobil. Záznamy sa nedajú upravovať ani mazať.
                </p>

                <!-- Filtre -->
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Hľadať v popise alebo mene…"
                        class="sm:col-span-2 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    />
                    <select
                        v-model="userId"
                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    >
                        <option value="">Všetci používatelia</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <select
                        v-model="action"
                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    >
                        <option value="">Všetky akcie</option>
                        <option v-for="(label, key) in actions" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>

                <!-- Zoznam -->
                <div class="bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 rounded-lg overflow-hidden">
                    <div v-if="!logs.data.length" class="p-10 text-center text-gray-500 dark:text-gray-400">
                        Žiadne záznamy nezodpovedajú filtru.
                        <button @click="resetFilters" class="ml-1 text-blue-600 hover:underline">Zrušiť filter</button>
                    </div>

                    <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-for="log in logs.data" :key="log.id" class="px-5 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                            {{ log.action_label }}
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ log.user_name }}</span>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ log.description }}</p>
                                    <button
                                        v-if="log.properties"
                                        @click="toggle(log.id)"
                                        class="mt-1 text-xs text-blue-600 dark:text-blue-400 hover:underline"
                                    >
                                        {{ expanded.has(log.id) ? 'Skryť zmeny' : 'Zobraziť zmeny' }}
                                    </button>
                                </div>
                                <div class="text-right text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    <div>{{ log.created_at }}</div>
                                    <div v-if="log.ip_address" class="mt-0.5">{{ log.ip_address }}</div>
                                </div>
                            </div>

                            <div v-if="log.properties && expanded.has(log.id)" class="mt-3 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 p-3">
                                <table class="w-full text-xs">
                                    <tbody>
                                        <tr v-for="(change, field) in log.properties" :key="field" class="align-top">
                                            <td class="py-1 pr-4 font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ field }}</td>
                                            <template v-if="change && typeof change === 'object' && 'pred' in change">
                                                <td class="py-1 pr-3 text-red-600 dark:text-red-400 line-through">{{ formatValue(change.pred) }}</td>
                                                <td class="py-1 text-green-700 dark:text-green-400">{{ formatValue(change.po) }}</td>
                                            </template>
                                            <td v-else colspan="2" class="py-1 text-gray-600 dark:text-gray-400">{{ formatValue(change) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Stránkovanie -->
                <div v-if="logs.links.length > 3" class="flex flex-wrap gap-1 justify-center">
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="(link, i) in logs.links"
                        :key="i"
                        :href="link.url"
                        v-html="link.label"
                        class="px-3 py-1.5 text-sm rounded-md border"
                        :class="link.active
                            ? 'bg-blue-600 text-white border-blue-600'
                            : link.url
                                ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
                                : 'text-gray-400 border-gray-200 dark:border-gray-700 cursor-default'"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
