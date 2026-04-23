<template>
    <AuthenticatedLayout title="Roles">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Roles
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white border-b border-gray-200">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-medium">Manage Roles</h3>
                            <Link
                                :href="route('roles.create')"
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                            >
                                Add Role
                            </Link>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full table-auto">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-2 text-left">Name</th>
                                        <th class="px-4 py-2 text-left">Description</th>
                                        <th class="px-4 py-2 text-left">Permissions</th>
                                        <th class="px-4 py-2 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="role in roles.data" :key="role.id" class="border-t">
                                        <td class="px-4 py-2">{{ role.name }}</td>
                                        <td class="px-4 py-2">{{ role.description }}</td>
                                        <td class="px-4 py-2">
                                            <span
                                                v-for="permission in role.permissions"
                                                :key="permission.id"
                                                class="inline-block bg-gray-200 rounded-full px-2 py-1 text-xs font-semibold text-gray-700 mr-1"
                                            >
                                                {{ permission.name }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2">
                                            <Link
                                                :href="route('roles.edit', role.id)"
                                                class="text-blue-600 hover:text-blue-900 mr-2"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                @click="deleteRole(role.id)"
                                                class="text-red-600 hover:text-red-900"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-4">
                            <div class="flex justify-center">
                                <Pagination :links="roles.links" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Link, router } from '@inertiajs/vue3';

defineProps({
    roles: Object,
});

const deleteRole = (roleId) => {
    if (confirm('Are you sure you want to delete this role?')) {
        router.delete(route('roles.destroy', roleId));
    }
};
</script>