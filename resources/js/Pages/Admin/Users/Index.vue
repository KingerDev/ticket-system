<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    users: Array,
});

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);

// --- vytvorenie ---
const showCreateModal = ref(false);

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    is_super_admin: false,
});

const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    showCreateModal.value = true;
};

const submitCreate = () => {
    createForm.post(route('admin.users.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreateModal.value = false; createForm.reset(); },
    });
};

// --- úprava ---
const showEditModal = ref(false);
const editingUser = ref(null);

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    is_super_admin: false,
});

const openEdit = (user) => {
    editingUser.value = user;
    editForm.clearErrors();
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.password_confirmation = '';
    editForm.is_super_admin = !!user.is_super_admin;
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.patch(route('admin.users.update', editingUser.value.id), {
        preserveScroll: true,
        onSuccess: () => { showEditModal.value = false; },
    });
};

// --- odstránenie ---
const showDeleteModal = ref(false);
const deletingUser = ref(null);
const deleteForm = useForm({});

const openDelete = (user) => {
    deletingUser.value = user;
    showDeleteModal.value = true;
};

const submitDelete = () => {
    deleteForm.delete(route('admin.users.destroy', deletingUser.value.id), {
        preserveScroll: true,
        onFinish: () => { showDeleteModal.value = false; },
    });
};

const superAdminCount = computed(() => props.users.filter(u => u.is_super_admin).length);
</script>

<template>
    <Head title="Používatelia" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Používatelia
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div class="flex flex-wrap items-center justify-between gap-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Účty s prístupom do administrácie. Super administrátor navyše spravuje používateľov
                        a vidí záznam činnosti.
                    </p>
                    <PrimaryButton @click="openCreate">Pridať používateľa</PrimaryButton>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 rounded-lg overflow-hidden">
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-for="user in users" :key="user.id" class="p-5 flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ user.name }}</span>
                                    <span
                                        v-if="user.is_super_admin"
                                        class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400"
                                    >
                                        Super administrátor
                                    </span>
                                    <span
                                        v-else
                                        class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                                    >
                                        Administrátor
                                    </span>
                                    <span v-if="user.id === currentUserId" class="text-xs text-gray-400">(vy)</span>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ user.email }}</div>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    @click="openEdit(user)"
                                    class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 bg-white dark:bg-gray-800"
                                >
                                    Upraviť
                                </button>
                                <button
                                    v-if="user.id !== currentUserId"
                                    @click="openDelete(user)"
                                    class="px-3 py-1.5 border border-red-200 dark:border-red-900/50 rounded-md text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 bg-white dark:bg-gray-800"
                                >
                                    Odstrániť
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Vytvorenie -->
        <Modal :show="showCreateModal" @close="showCreateModal = false" maxWidth="lg">
            <form @submit.prevent="submitCreate" class="p-6 space-y-4">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Nový používateľ</h2>

                <div>
                    <InputLabel for="c_name">Meno <span class="text-red-500">*</span></InputLabel>
                    <TextInput id="c_name" type="text" class="mt-1 block w-full" v-model="createForm.name" required />
                    <InputError class="mt-1" :message="createForm.errors.name" />
                </div>
                <div>
                    <InputLabel for="c_email">E-mail <span class="text-red-500">*</span></InputLabel>
                    <TextInput id="c_email" type="email" class="mt-1 block w-full" v-model="createForm.email" required />
                    <InputError class="mt-1" :message="createForm.errors.email" />
                </div>
                <div>
                    <InputLabel for="c_password">Heslo <span class="text-red-500">*</span></InputLabel>
                    <TextInput id="c_password" type="password" class="mt-1 block w-full" v-model="createForm.password" required />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Aspoň 8 znakov.</p>
                    <InputError class="mt-1" :message="createForm.errors.password" />
                </div>
                <div>
                    <InputLabel for="c_password2">Heslo znova <span class="text-red-500">*</span></InputLabel>
                    <TextInput id="c_password2" type="password" class="mt-1 block w-full" v-model="createForm.password_confirmation" required />
                </div>

                <label class="flex items-start gap-3 cursor-pointer pt-2">
                    <input type="checkbox" v-model="createForm.is_super_admin" class="mt-0.5 rounded border-gray-300 text-purple-600 focus:ring-purple-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        <span class="font-medium">Super administrátor</span><br />
                        <span class="text-xs text-gray-500 dark:text-gray-400">Bude môcť zakladať ďalších používateľov a čítať záznam činnosti.</span>
                    </span>
                </label>

                <div class="flex justify-end space-x-3 pt-2">
                    <button type="button" @click="showCreateModal = false" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Zrušiť
                    </button>
                    <PrimaryButton :disabled="createForm.processing" :class="{ 'opacity-25': createForm.processing }">
                        {{ createForm.processing ? 'Vytváram…' : 'Vytvoriť' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Úprava -->
        <Modal :show="showEditModal" @close="showEditModal = false" maxWidth="lg">
            <form v-if="editingUser" @submit.prevent="submitEdit" class="p-6 space-y-4">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Upraviť používateľa</h2>

                <div>
                    <InputLabel for="e_name">Meno <span class="text-red-500">*</span></InputLabel>
                    <TextInput id="e_name" type="text" class="mt-1 block w-full" v-model="editForm.name" required />
                    <InputError class="mt-1" :message="editForm.errors.name" />
                </div>
                <div>
                    <InputLabel for="e_email">E-mail <span class="text-red-500">*</span></InputLabel>
                    <TextInput id="e_email" type="email" class="mt-1 block w-full" v-model="editForm.email" required />
                    <InputError class="mt-1" :message="editForm.errors.email" />
                </div>
                <div>
                    <InputLabel for="e_password">Nové heslo</InputLabel>
                    <TextInput id="e_password" type="password" class="mt-1 block w-full" v-model="editForm.password" autocomplete="new-password" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nechajte prázdne, ak sa heslo nemá meniť.</p>
                    <InputError class="mt-1" :message="editForm.errors.password" />
                </div>
                <div v-if="editForm.password">
                    <InputLabel for="e_password2">Nové heslo znova</InputLabel>
                    <TextInput id="e_password2" type="password" class="mt-1 block w-full" v-model="editForm.password_confirmation" autocomplete="new-password" />
                </div>

                <label class="flex items-start gap-3 cursor-pointer pt-2">
                    <input type="checkbox" v-model="editForm.is_super_admin" class="mt-0.5 rounded border-gray-300 text-purple-600 focus:ring-purple-500 dark:bg-gray-900 dark:border-gray-700 w-4 h-4" />
                    <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">Super administrátor</span>
                </label>
                <p v-if="editingUser.is_super_admin && superAdminCount <= 1" class="text-xs text-amber-700 dark:text-amber-400">
                    Toto je jediný super administrátor – rolu mu nie je možné odobrať.
                </p>

                <div class="flex justify-end space-x-3 pt-2">
                    <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Zrušiť
                    </button>
                    <PrimaryButton :disabled="editForm.processing" :class="{ 'opacity-25': editForm.processing }">
                        {{ editForm.processing ? 'Ukladám…' : 'Uložiť' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Odstránenie -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false" maxWidth="md">
            <div v-if="deletingUser" class="p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Odstrániť používateľa</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    Naozaj chcete odstrániť účet <strong class="text-gray-900 dark:text-gray-100">{{ deletingUser.name }}</strong>
                    ({{ deletingUser.email }})? Stratí prístup do administrácie.
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    Jeho záznamy v zázname činnosti zostanú zachované.
                </p>

                <div class="flex justify-end space-x-3">
                    <button @click="showDeleteModal = false" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Zrušiť
                    </button>
                    <button
                        @click="submitDelete"
                        :disabled="deleteForm.processing"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm font-semibold disabled:opacity-50"
                    >
                        {{ deleteForm.processing ? 'Odstraňujem…' : 'Odstrániť' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
