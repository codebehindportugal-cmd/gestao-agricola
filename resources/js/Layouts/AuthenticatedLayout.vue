<script setup>
import { computed, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const showingNavigationDropdown = ref(false);
const showingResourcesDropdown = ref(false);
const showingAdminDropdown = ref(false);

const primaryLinks = [
    { label: 'Hoje', routeName: 'dashboard', active: 'dashboard' },
    { label: 'Caderno', routeName: 'app.operacoes.index', active: 'app.operacoes.*' },
    { label: 'Parcelas', routeName: 'app.parcelas.index', active: 'app.parcelas.*' },
    { label: 'Custos', routeName: 'app.campanhas.index', active: 'app.campanhas.*' },
];

const resourceLinks = [
    { label: 'Stock', routeName: 'app.stock.index', active: 'app.stock.*' },
    { label: 'Maquinaria', routeName: 'app.maquinaria.index', active: 'app.maquinaria.*' },
    { label: 'MÃ£o de obra', routeName: 'app.mao-obra.index', active: 'app.mao-obra.*' },
];

const canManageUsers = computed(() => {
    return page.props.auth.user &&
        page.props.auth.user.permissions &&
        page.props.auth.user.permissions.some((permission) => permission.name === 'usuarios.manage');
});

const resourcesActive = computed(() => {
    return route().current('app.stock.*') ||
        route().current('app.maquinaria.*') ||
        route().current('app.maquinas.*') ||
        route().current('app.alfaias.*') ||
        route().current('app.mao-obra.*') ||
        route().current('app.funcionarios.*') ||
        route().current('app.equipas.*');
});

const resourcesPanelOpen = computed(() => showingResourcesDropdown.value || resourcesActive.value);
</script>

<template>
    <div>
        <div class="min-h-screen bg-slate-100">
            <nav class="border-b border-emerald-100 bg-white/90 backdrop-blur">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex items-center gap-8">
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')" class="flex items-center gap-3">
                                    <ApplicationLogo class="block h-10 w-10" />
                                    <div class="hidden sm:block">
                                        <p class="text-sm font-black uppercase tracking-[0.24em] text-slate-900">Agro</p>
                                        <p class="text-xs font-medium uppercase tracking-[0.28em] text-emerald-700">GestÃ£o AgrÃ­cola</p>
                                    </div>
                                </Link>
                            </div>

                            <div class="hidden items-center gap-3 sm:flex">
                                <NavLink
                                    v-for="link in primaryLinks"
                                    :key="link.routeName"
                                    :href="route(link.routeName)"
                                    :active="route().current(link.active)"
                                >
                                    {{ link.label }}
                                </NavLink>

                                <div>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition"
                                        :class="resourcesActive ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:text-slate-900'"
                                        @click="showingResourcesDropdown = !showingResourcesDropdown"
                                    >
                                        Recursos
                                        <svg class="ml-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>

                                <div v-if="canManageUsers" class="relative">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900"
                                        @click="showingAdminDropdown = !showingAdminDropdown"
                                    >
                                        AdministraÃ§Ã£o
                                        <svg class="ml-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                        </svg>
                                    </button>

                                    <div
                                        v-show="showingAdminDropdown"
                                        class="absolute right-0 z-20 mt-2 w-48 rounded-2xl border border-slate-200 bg-white p-2 shadow-lg"
                                        @click.away="showingAdminDropdown = false"
                                    >
                                        <Link :href="route('users.index')" class="block rounded-xl px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50" @click="showingAdminDropdown = false">
                                            Utilizadores
                                        </Link>
                                        <Link :href="route('roles.index')" class="block rounded-xl px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50" @click="showingAdminDropdown = false">
                                            Perfis
                                        </Link>
                                        <Link :href="route('permissions.index')" class="block rounded-xl px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50" @click="showingAdminDropdown = false">
                                            PermissÃµes
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium leading-4 text-slate-600 transition hover:border-emerald-200 hover:text-slate-900 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}
                                                <svg class="-me-0.5 ms-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">Perfil</DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">Terminar sessÃ£o</DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
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

                <div v-show="resourcesPanelOpen" class="hidden border-t border-slate-200 bg-white sm:block">
                    <div class="mx-auto flex max-w-7xl flex-wrap gap-3 px-4 py-3 sm:px-6 lg:px-8">
                        <Link
                            v-for="link in resourceLinks"
                            :key="`desktop-${link.routeName}`"
                            :href="route(link.routeName)"
                            class="rounded-full px-4 py-2 text-sm font-medium transition"
                            :class="route().current(link.active) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-50 text-slate-700 hover:bg-slate-100'"
                            @click="showingResourcesDropdown = false"
                        >
                            {{ link.label }}
                        </Link>
                    </div>
                </div>

                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            v-for="link in primaryLinks"
                            :key="link.routeName"
                            :href="route(link.routeName)"
                            :active="route().current(link.active)"
                        >
                            {{ link.label }}
                        </ResponsiveNavLink>

                        <div class="border-t border-gray-200 pt-4 pb-2">
                            <div class="px-4 text-xs font-semibold uppercase tracking-wider text-gray-500">Recursos</div>
                            <ResponsiveNavLink
                                v-for="link in resourceLinks"
                                :key="link.routeName"
                                :href="route(link.routeName)"
                                :active="route().current(link.active)"
                            >
                                {{ link.label }}
                            </ResponsiveNavLink>
                        </div>

                        <div v-if="canManageUsers" class="border-t border-gray-200 pt-4 pb-2">
                            <div class="px-4 text-xs font-semibold uppercase tracking-wider text-gray-500">AdministraÃ§Ã£o</div>
                            <ResponsiveNavLink :href="route('users.index')" :active="route().current('users.*')">Utilizadores</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('roles.index')" :active="route().current('roles.*')">Perfis</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('permissions.index')" :active="route().current('permissions.*')">PermissÃµes</ResponsiveNavLink>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800">{{ $page.props.auth.user.name }}</div>
                            <div class="text-sm font-medium text-gray-500">{{ $page.props.auth.user.email }}</div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">Perfil</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">Terminar sessÃ£o</ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <header v-if="$slots.header" class="border-b border-white/60 bg-white/70 shadow-sm backdrop-blur">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <div v-if="$page.props.flash?.error" class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">
                        {{ $page.props.flash.error }}
                    </div>
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
