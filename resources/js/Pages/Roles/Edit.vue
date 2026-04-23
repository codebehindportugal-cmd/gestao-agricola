<template>
    <AuthenticatedLayout title="Edit Role">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Role
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white border-b border-gray-200">
                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    required
                                />
                                <div v-if="form.errors.name" class="text-red-600 text-sm mt-1">{{ form.errors.name }}</div>
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    rows="3"
                                ></textarea>
                                <div v-if="form.errors.description" class="text-red-600 text-sm mt-1">{{ form.errors.description }}</div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Permissions</label>
                                <div class="mt-2 space-y-2">
                                    <label v-for="permission in permissions" :key="permission.id" class="inline-flex items-center mr-4">
                                        <input
                                            v-model="form.permissions"
                                            :value="permission.id"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">{{ permission.name }}</span>
                                    </label>
                                </div>
                                <div v-if="form.errors.permissions" class="text-red-600 text-sm mt-1">{{ form.errors.permissions }}</div>
                            </div>

                            <div class="flex items-center justify-end">
                                <Link
                                    :href="route('roles.index')"
                                    class="mr-4 text-gray-600 hover:text-gray-900"
                                >
                                    Cancel
                                </Link>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50"
                                >
                                    Update Role
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    role: Object,
    permissions: Array,
    rolePermissions: Array,
});

const form = useForm({
    name: props.role.name,
    description: props.role.description,
    permissions: props.rolePermissions,
});

const submit = () => {
    form.patch(route('roles.update', props.role.id), {
        onSuccess: () => {
            // Optional: redirect or show success
        },
    });
};
</script>