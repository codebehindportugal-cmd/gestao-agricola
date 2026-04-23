<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const showingAdminDropdown = ref(false);
</script>

<template>
    <div>
        <div class="min-h-screen bg-slate-100">
            <nav
                class="border-b border-emerald-100 bg-white/90 backdrop-blur"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex items-center gap-8">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')" class="flex items-center gap-3">
                                    <ApplicationLogo
                                        class="block h-10 w-10"
                                    />
                                    <div class="hidden sm:block">
                                        <p class="text-sm font-black uppercase tracking-[0.24em] text-slate-900">
                                            Agro
                                        </p>
                                        <p class="text-xs font-medium uppercase tracking-[0.28em] text-emerald-700">
                                            Gestão Agrícola
                                        </p>
                                    </div>
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden items-center gap-3 sm:flex"
                            >
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Painel
                                </NavLink>
                                <NavLink
                                    :href="route('app.terrenos.index')"
                                    :active="route().current('app.terrenos.*')"
                                >
                                    Terrenos
                                </NavLink>
                                <NavLink
                                    :href="route('app.parcelas.index')"
                                    :active="route().current('app.parcelas.*')"
                                >
                                    Parcelas
                                </NavLink>
                                <NavLink
                                    :href="route('app.culturas.index')"
                                    :active="route().current('app.culturas.*')"
                                >
                                    Culturas
                                </NavLink>
                                <NavLink
                                    :href="route('app.operacoes.index')"
                                    :active="route().current('app.operacoes.*')"
                                >
                                    Operações
                                </NavLink>
                                <NavLink
                                    :href="route('app.maquinaria.index')"
                                    :active="route().current('app.maquinaria.*') || route().current('app.maquinas.*') || route().current('app.alfaias.*')"
                                >
                                    Maquinaria
                                </NavLink>
                                <NavLink
                                    :href="route('app.mao-obra.index')"
                                    :active="route().current('app.mao-obra.*') || route().current('app.funcionarios.*') || route().current('app.equipas.*')"
                                >
                                    Mão de obra
                                </NavLink>
                                <!-- Admin Dropdown -->
                                <div class="relative" v-if="$page.props.auth.user && $page.props.auth.user.permissions && $page.props.auth.user.permissions.some(p => p.name === 'usuarios.manage')">
                                    <button
                                        @click="showingAdminDropdown = !showingAdminDropdown"
                                        class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                    >
                                        Administração
                                        <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    <div
                                        v-show="showingAdminDropdown"
                                        class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5"
                                        @click.away="showingAdminDropdown = false"
                                    >
                                        <div class="py-1">
                                            <Link
                                                :href="route('users.index')"
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                                @click="showingAdminDropdown = false"
                                            >
                                                Utilizadores
                                            </Link>
                                            <Link
                                                :href="route('roles.index')"
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                                @click="showingAdminDropdown = false"
                                            >
                                                Roles
                                            </Link>
                                            <Link
                                                :href="route('permissions.index')"
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                                @click="showingAdminDropdown = false"
                                            >
                                                Permissões
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">
                                    MVP Fase 1
                                </span>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium leading-4 text-slate-600 transition duration-150 ease-in-out hover:border-emerald-200 hover:text-slate-900 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Perfil
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Terminar sessão
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Painel
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('app.terrenos.index')"
                            :active="route().current('app.terrenos.*')"
                        >
                            Terrenos
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('app.parcelas.index')"
                            :active="route().current('app.parcelas.*')"
                        >
                            Parcelas
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('app.culturas.index')"
                            :active="route().current('app.culturas.*')"
                        >
                            Culturas
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('app.operacoes.index')"
                            :active="route().current('app.operacoes.*')"
                        >
                            Operações
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('app.maquinaria.index')"
                            :active="route().current('app.maquinaria.*') || route().current('app.maquinas.*') || route().current('app.alfaias.*')"
                        >
                            Maquinaria
                        </ResponsiveNavLink>
                        <!-- Admin Links Mobile -->
                        <div v-if="$page.props.auth.user && $page.props.auth.user.permissions && $page.props.auth.user.permissions.some(p => p.name === 'usuarios.manage')" class="border-t border-gray-200 pt-4 pb-2">
                            <div class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Administração
                            </div>
                            <ResponsiveNavLink
                                :href="route('users.index')"
                                :active="route().current('users.*')"
                            >
                                Utilizadores
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('roles.index')"
                                :active="route().current('roles.*')"
                            >
                                Roles
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('permissions.index')"
                                :active="route().current('permissions.*')"
                            >
                                Permissões
                            </ResponsiveNavLink>
                        </div>
                        <ResponsiveNavLink
                            :href="route('app.mao-obra.index')"
                            :active="route().current('app.mao-obra.*') || route().current('app.funcionarios.*') || route().current('app.equipas.*')"
                        >
                            Mão de obra
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Perfil
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Terminar sessão
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="border-b border-white/60 bg-white/70 shadow-sm backdrop-blur"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <div
                    v-if="$page.props.flash?.error"
                    class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8"
                >
                    <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">
                        {{ $page.props.flash.error }}
                    </div>
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
