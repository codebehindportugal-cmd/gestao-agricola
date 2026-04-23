<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <Head v-if="title" :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <slot name="header" />
        </template>

        <slot />
    </AuthenticatedLayout>
</template>
