<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const menuAberto = ref(false);
const menuUtilizador = ref(false);

// Icones de traco, 24x24. Só caminhos (path) para se desenharem com um v-for.
const icones = {
    hoje: ['M3 11.5 12 4l9 7.5', 'M5 10v10h14V10'],
    caderno: ['M6 3h11a2 2 0 0 1 2 2v16H8a2 2 0 0 1-2-2z', 'M6 17a2 2 0 0 1 2-2h11', 'M10 7h6M10 10h4'],
    calendario: ['M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z', 'M3 10h18M8 3v4M16 3v4'],
    parcelas: ['m3 7 6-3 6 3 6-3v13l-6 3-6-3-6 3z', 'M9 4v13M15 7v13'],
    terrenos: ['M3 20h18', 'm5 20 5-9 4 6 2-3 3 6'],
    culturas: ['M12 21V11', 'M12 11c0-4 3-7 7-7 0 4-3 7-7 7z', 'M12 14c0-3-2.5-5.5-6-5.5 0 3 2.5 5.5 6 5.5z'],
    stock: ['M3 8 12 3l9 5v8l-9 5-9-5z', 'm3 8 9 5 9-5M12 13v8'],
    maquinaria: ['M7 14a3 3 0 1 1 0 6 3 3 0 0 1 0-6z', 'M18 16a2 2 0 1 1 0 4 2 2 0 0 1 0-4z', 'M4 14V8h6l2 6h6v4M10 8V5h3'],
    pessoas: ['M9 4.5a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7z', 'M2.5 20a6.5 6.5 0 0 1 13 0', 'M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6'],
    local: ['M12 21s7-6.2 7-11.5a7 7 0 1 0-14 0C5 14.8 12 21 12 21z', 'M12 7a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5z'],
    custos: ['M4 19V9M10 19V5M16 19v-7M22 19H2'],
    despesas: ['M6 3h12v18l-3-2-3 2-3-2-3 2z', 'M9 8h6M9 12h6'],
    casa: ['M5 4h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z', 'M8 21h8M12 17v4'],
    admin: ['M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6z', 'm9 12 2 2 4-4'],
    mais: ['M4 6h16M4 12h16M4 18h16'],
    fechar: ['M6 6l12 12M18 6 6 18'],
    seta: ['m6 9 6 6 6-6'],
};

const grupos = computed(() => {
    const lista = [
        {
            titulo: null,
            links: [{ label: 'Hoje', routeName: 'dashboard', active: 'dashboard', icone: 'hoje' }],
        },
        {
            titulo: 'Campo',
            links: [
                { label: 'Caderno de campo', routeName: 'app.operacoes.index', active: 'app.operacoes.*', icone: 'caderno' },
                { label: 'Calendário', routeName: 'app.calendario.index', active: 'app.calendario.*', icone: 'calendario' },
                { label: 'Parcelas', routeName: 'app.parcelas.index', active: 'app.parcelas.*', icone: 'parcelas' },
                { label: 'Culturas', routeName: 'app.culturas.index', active: 'app.culturas.*', icone: 'culturas' },
                { label: 'Terrenos', routeName: 'app.terrenos.index', active: 'app.terrenos.*', icone: 'terrenos' },
            ],
        },
        {
            titulo: 'Recursos',
            links: [
                { label: 'Stock', routeName: 'app.stock.index', active: 'app.stock.*', icone: 'stock' },
                { label: 'Maquinaria', routeName: 'app.maquinaria.index', active: ['app.maquinaria.*', 'app.maquinas.*', 'app.alfaias.*'], icone: 'maquinaria' },
                { label: 'Mão de obra', routeName: 'app.mao-obra.index', active: ['app.mao-obra.index', 'app.funcionarios.*', 'app.equipas.*'], icone: 'pessoas' },
                { label: 'Localização', routeName: 'app.mao-obra.localizacoes', active: 'app.mao-obra.localizacoes', icone: 'local' },
            ],
        },
        {
            titulo: 'Dinheiro',
            links: [
                { label: 'Custos da campanha', routeName: 'app.campanhas.index', active: 'app.campanhas.*', icone: 'custos' },
                { label: 'Despesas', routeName: 'app.despesas.index', active: 'app.despesas.*', icone: 'despesas' },
            ],
        },
        {
            titulo: 'Outros',
            links: [{ label: 'Casa', routeName: 'app.casa.index', active: 'app.casa.*', icone: 'casa' }],
        },
    ];

    if (podeGerirUtilizadores.value) {
        lista.push({
            titulo: 'Administração',
            links: [
                { label: 'Utilizadores', routeName: 'users.index', active: 'users.*', icone: 'pessoas' },
                { label: 'Perfis', routeName: 'roles.index', active: 'roles.*', icone: 'admin' },
                { label: 'Permissões', routeName: 'permissions.index', active: 'permissions.*', icone: 'admin' },
            ],
        });
    }

    // Ignora ligações cuja rota não existe neste ambiente
    return lista
        .map((grupo) => ({ ...grupo, links: grupo.links.filter((link) => route().has(link.routeName)) }))
        .filter((grupo) => grupo.links.length);
});

const separadores = [
    { label: 'Hoje', routeName: 'dashboard', active: 'dashboard', icone: 'hoje' },
    { label: 'Caderno', routeName: 'app.operacoes.index', active: 'app.operacoes.*', icone: 'caderno' },
    { label: 'Stock', routeName: 'app.stock.index', active: 'app.stock.*', icone: 'stock' },
    { label: 'Custos', routeName: 'app.campanhas.index', active: 'app.campanhas.*', icone: 'custos' },
];

function ativo(link) {
    const padroes = Array.isArray(link.active) ? link.active : [link.active];
    return padroes.some((padrao) => route().current(padrao));
}

const podeGerirUtilizadores = computed(() => {
    return page.props.auth.user &&
        page.props.auth.user.permissions &&
        page.props.auth.user.permissions.some((permission) => permission.name === 'usuarios.manage');
});

const campaignOptions = computed(() => page.props.workingCampaign?.options ?? []);
const activeCampaign = computed(() => page.props.workingCampaign?.active ?? null);

const iniciais = computed(() => {
    const nome = page.props.auth.user?.name ?? '';
    return nome.split(/\s+/).filter(Boolean).slice(0, 2).map((parte) => parte[0]).join('').toUpperCase();
});

const nomeCampanha = (campanha) => (campanha?.nome ?? '').replace(/^Campanha\s+/i, '');

function setActiveCampaign(event) {
    router.post(route('app.campanha-ativa.update'), {
        campanha_ano: event.target.value,
    }, {
        preserveScroll: true,
        preserveState: false,
    });
}

// Fecha o menu do telemóvel quando se muda de página
watch(() => page.url, () => {
    menuAberto.value = false;
    menuUtilizador.value = false;
});
</script>

<template>
    <div class="min-h-screen bg-fundo text-slate-900 lg:grid lg:grid-cols-[248px_minmax(0,1fr)]">
        <!-- Menu lateral (computador) e gaveta (telemóvel) -->
        <div
            v-if="menuAberto"
            class="fixed inset-0 z-[1400] bg-slate-900/50 lg:hidden"
            aria-hidden="true"
            @click="menuAberto = false"
        />
        <aside
            class="fixed inset-y-0 left-0 z-[1500] flex w-[280px] flex-col border-r border-slate-200 bg-white px-3 py-5 transition-transform duration-200 lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:w-auto lg:translate-x-0"
            :class="menuAberto ? 'translate-x-0' : '-translate-x-full'"
            aria-label="Menu principal"
        >
            <div class="flex items-center justify-between gap-2 px-3 pb-4">
                <Link :href="route('dashboard')" class="flex items-center gap-3 text-slate-900 no-underline">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-verde-700 text-sm font-bold text-white">GA</span>
                    <span class="flex flex-col leading-tight">
                        <span class="text-[15px] font-bold">Gestão Agrícola</span>
                        <span class="text-xs text-slate-500">Caderno da exploração</span>
                    </span>
                </Link>
                <button
                    type="button"
                    class="flex h-11 w-11 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 lg:hidden"
                    aria-label="Fechar menu"
                    @click="menuAberto = false"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path v-for="d in icones.fechar" :key="d" :d="d" />
                    </svg>
                </button>
            </div>

            <label v-if="campaignOptions.length" class="flex flex-col gap-1.5 px-3 pb-3 text-xs font-semibold text-slate-600">
                Campanha
                <select
                    class="min-h-[40px] rounded-lg border-slate-300 bg-slate-50 py-2 text-sm font-medium text-slate-900 focus:border-verde-600 focus:ring-verde-600"
                    :value="activeCampaign?.id ?? ''"
                    @change="setActiveCampaign"
                >
                    <option v-for="campanha in campaignOptions" :key="campanha.id" :value="campanha.id">
                        {{ nomeCampanha(campanha) }}
                    </option>
                </select>
            </label>

            <nav class="flex-1 overflow-y-auto" aria-label="Secções">
                <div v-for="grupo in grupos" :key="grupo.titulo ?? 'inicio'" class="flex flex-col gap-0.5">
                    <span v-if="grupo.titulo" class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ grupo.titulo }}
                    </span>
                    <Link
                        v-for="link in grupo.links"
                        :key="link.routeName"
                        :href="route(link.routeName)"
                        :aria-current="ativo(link) ? 'page' : undefined"
                        class="flex min-h-[38px] items-center gap-3 rounded-lg px-3 text-sm no-underline transition"
                        :class="ativo(link) ? 'bg-verde-100 font-semibold text-verde-800' : 'font-medium text-slate-800 hover:bg-slate-100'"
                    >
                        <svg
                            class="h-5 w-5 shrink-0"
                            :class="ativo(link) ? 'text-verde-700' : 'text-slate-500'"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path v-for="d in icones[link.icone]" :key="d" :d="d" />
                        </svg>
                        {{ link.label }}
                    </Link>
                </div>
            </nav>

            <div class="relative mt-3 border-t border-slate-100 pt-3">
                <button
                    type="button"
                    class="flex min-h-[48px] w-full items-center gap-3 rounded-lg px-3 text-left hover:bg-slate-100"
                    :aria-expanded="menuUtilizador"
                    @click="menuUtilizador = !menuUtilizador"
                >
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700">{{ iniciais }}</span>
                    <span class="flex min-w-0 flex-1 flex-col leading-tight">
                        <span class="truncate text-sm font-semibold">{{ $page.props.auth.user.name }}</span>
                        <span class="truncate text-xs text-slate-500">{{ $page.props.auth.user.email }}</span>
                    </span>
                    <svg class="h-4 w-4 shrink-0 text-slate-500 transition" :class="menuUtilizador ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path v-for="d in icones.seta" :key="d" :d="d" />
                    </svg>
                </button>
                <div v-show="menuUtilizador" class="absolute bottom-full left-0 right-0 mb-2 rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                    <Link :href="route('profile.edit')" class="flex min-h-[44px] items-center rounded-md px-3 text-sm text-slate-800 no-underline hover:bg-slate-100">
                        Perfil
                    </Link>
                    <Link :href="route('logout')" method="post" as="button" class="flex min-h-[44px] w-full items-center rounded-md px-3 text-left text-sm text-slate-800 hover:bg-slate-100">
                        Terminar sessão
                    </Link>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-col pb-[76px] lg:pb-0">
            <!-- Barra de topo (só telemóvel) -->
            <div class="sticky top-0 z-[1200] flex h-14 items-center justify-between border-b border-slate-200 bg-white px-4 lg:hidden">
                <Link :href="route('dashboard')" class="flex items-center gap-2 text-slate-900 no-underline">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-verde-700 text-xs font-bold text-white">GA</span>
                    <span class="text-[15px] font-bold">Gestão Agrícola</span>
                </Link>
                <span v-if="activeCampaign" class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">{{ nomeCampanha(activeCampaign) }}</span>
            </div>

            <header v-if="$slots.header" class="border-b border-slate-200 bg-white">
                <div class="mx-auto w-full max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main class="flex-1">
                <div v-if="$page.props.flash?.error" class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-800" role="alert">
                        {{ $page.props.flash.error }}
                    </div>
                </div>
                <slot />
            </main>
        </div>

        <!-- Separadores em baixo (só telemóvel) -->
        <nav class="fixed inset-x-0 bottom-0 z-[1200] flex border-t border-slate-200 bg-white px-1 pb-[max(8px,env(safe-area-inset-bottom))] pt-1 lg:hidden" aria-label="Atalhos">
            <Link
                v-for="link in separadores"
                :key="link.routeName"
                :href="route(link.routeName)"
                :aria-current="ativo(link) ? 'page' : undefined"
                class="flex min-h-[56px] flex-1 flex-col items-center justify-center gap-1 text-[11px] no-underline"
                :class="ativo(link) ? 'font-semibold text-verde-700' : 'font-medium text-slate-600'"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path v-for="d in icones[link.icone]" :key="d" :d="d" />
                </svg>
                {{ link.label }}
            </Link>
            <button
                type="button"
                class="flex min-h-[56px] flex-1 flex-col items-center justify-center gap-1 text-[11px] font-medium text-slate-600"
                :aria-expanded="menuAberto"
                @click="menuAberto = true"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path v-for="d in icones.mais" :key="d" :d="d" />
                </svg>
                Mais
            </button>
        </nav>
    </div>
</template>
