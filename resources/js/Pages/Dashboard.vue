<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DashboardPolygonsMap from '@/Components/DashboardPolygonsMap.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    stats: {
        type: Array,
        default: () => [],
    },
    statusCards: {
        type: Array,
        default: () => [],
    },
    recentOperations: {
        type: Array,
        default: () => [],
    },
    focusAreas: {
        type: Array,
        default: () => [],
    },
    mapPolygons: {
        type: Array,
        default: () => [],
    },
});

const toneClasses = {
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    amber: 'border-amber-200 bg-amber-50 text-amber-700',
    sky: 'border-sky-200 bg-sky-50 text-sky-700',
};
</script>

<template>
    <Head title="Painel Agrícola" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                        <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 18c3-3 5.5-4.5 8-4.5S17 15 20 18" stroke-linecap="round" />
                            <path d="M6 21c2.7-2 5-3 7.5-3S18.3 19 21 21" stroke-linecap="round" opacity="0.65" />
                            <path d="M12 13V5" stroke-linecap="round" />
                            <path d="M12 5c0-1.8 1.8-3.2 4-3.5 0 2.3-1.7 4.1-4 4.5Z" fill="currentColor" stroke="none" />
                            <path d="M12 9C12 7 10.4 5.4 8 5c0 2.3 1.7 4.1 4 4.5Z" fill="currentColor" stroke="none" opacity="0.85" />
                        </svg>
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-700">
                        Gestão Agrícola
                    </p>
                </div>
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 class="text-3xl font-black text-slate-900">
                            Painel operacional da exploração
                        </h1>
                        <p class="mt-2 max-w-2xl text-sm text-slate-600">
                            Uma visão rápida do que já está registado no sistema e do que podemos desenvolver a seguir.
                        </p>
                    </div>
                    <div class="rounded-3xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        Backend do domínio pronto. Frontend do produto agora começa a ganhar forma.
                    </div>
                </div>
            </div>
        </template>

        <div class="bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_32%),linear-gradient(180deg,_#f8fafc_0%,_#eef6f1_100%)] py-10">
            <div class="mx-auto flex max-w-7xl flex-col gap-8 px-4 sm:px-6 lg:px-8">
                <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="stat in stats"
                        :key="stat.label"
                        class="rounded-[28px] border border-white/80 bg-white/90 p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.35)] backdrop-blur"
                    >
                        <p class="text-sm font-medium text-slate-500">{{ stat.label }}</p>
                        <p class="mt-4 text-4xl font-black tracking-tight text-slate-900">{{ stat.value }}</p>
                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ stat.description }}</p>
                    </article>
                </section>

                <section class="rounded-[32px] border border-white/80 bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                    <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.32em] text-emerald-700">
                                Mapa da exploracao
                            </p>
                            <h2 class="mt-3 text-2xl font-black text-slate-900">
                                Terrenos e parcelas desenhados
                            </h2>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                                Visão satélite com todos os polígonos guardados. Os terrenos aparecem a verde e as parcelas a azul.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs font-semibold">
                            <span class="rounded-full bg-emerald-50 px-3 py-2 text-emerald-700">Terrenos</span>
                            <span class="rounded-full bg-sky-50 px-3 py-2 text-sky-700">Parcelas</span>
                            <span class="rounded-full bg-slate-100 px-3 py-2 text-slate-600">{{ mapPolygons.length }} polígonos</span>
                        </div>
                    </div>

                    <DashboardPolygonsMap
                        v-if="mapPolygons.length"
                        :polygons="mapPolygons"
                        height-class="h-[460px] lg:h-[620px]"
                    />

                    <div
                        v-else
                        class="rounded-[28px] border border-dashed border-slate-300 bg-slate-50 px-6 py-14 text-center text-sm leading-7 text-slate-600"
                    >
                        Ainda não existem polígonos guardados. Desenha um terreno ou uma parcela para aparecer aqui no painel.
                    </div>
                </section>

                <section class="grid gap-5 lg:grid-cols-[1.4fr_0.9fr]">
                    <article class="rounded-[32px] bg-slate-950 p-8 text-white shadow-[0_24px_80px_-40px_rgba(15,23,42,0.85)]">
                        <p class="text-xs font-semibold uppercase tracking-[0.32em] text-emerald-300">
                            Centro de controlo
                        </p>
                        <h2 class="mt-4 max-w-xl text-3xl font-black leading-tight">
                            O projeto já tem base sólida para passar de API para produto utilizável.
                        </h2>
                        <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300">
                            Nesta primeira iteração, estamos a sair do template padrão do Laravel e a preparar uma interface coerente com terrenos, culturas, operações e equipamentos.
                        </p>

                        <div class="mt-8 grid gap-4 md:grid-cols-3">
                            <div
                                v-for="card in statusCards"
                                :key="card.label"
                                class="rounded-3xl border px-5 py-4"
                                :class="toneClasses[card.tone]"
                            >
                                <p class="text-sm font-semibold">{{ card.label }}</p>
                                <p class="mt-3 text-3xl font-black">{{ card.value }}</p>
                                <p class="mt-2 text-xs font-medium opacity-80">Total registado: {{ card.total }}</p>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-[32px] border border-emerald-100 bg-white p-8 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-xs font-semibold uppercase tracking-[0.32em] text-emerald-700">
                            Áreas de foco
                        </p>
                        <div class="mt-6 space-y-5">
                            <div
                                v-for="area in focusAreas"
                                :key="area.title"
                                class="rounded-3xl bg-slate-50 p-5"
                            >
                                <h3 class="text-lg font-bold text-slate-900">{{ area.title }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ area.description }}</p>
                            </div>
                        </div>
                    </article>
                </section>

                <section class="grid gap-5 lg:grid-cols-[1.05fr_0.95fr]">
                    <article class="rounded-[32px] border border-slate-200 bg-white p-8 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">
                                    Atividade recente
                                </p>
                                <h2 class="mt-3 text-2xl font-black text-slate-900">
                                    Últimas operações registadas
                                </h2>
                            </div>
                            <div class="rounded-full bg-slate-100 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                API v1
                            </div>
                        </div>

                        <div v-if="recentOperations.length" class="mt-6 overflow-hidden rounded-3xl border border-slate-100">
                            <div
                                v-for="operation in recentOperations"
                                :key="operation.id"
                                class="grid gap-3 border-b border-slate-100 px-5 py-4 last:border-b-0 md:grid-cols-[1.2fr_1fr_1fr_auto]"
                            >
                                <div>
                                    <p class="text-base font-bold capitalize text-slate-900">{{ operation.tipo }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ operation.inicio || 'Sem data definida' }}</p>
                                </div>
                                <p class="text-sm text-slate-600">{{ operation.parcela }}</p>
                                <p class="text-sm text-slate-600">{{ operation.maquina }}</p>
                                <span class="justify-self-start rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold capitalize text-emerald-700">
                                    {{ operation.estado || 'sem estado' }}
                                </span>
                            </div>
                        </div>

                        <div
                            v-else
                            class="mt-6 rounded-3xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-sm leading-7 text-slate-600"
                        >
                            Ainda não existem operações recentes para mostrar. Assim que começarmos a registar trabalhos no terreno, esta área passa a refletir a atividade real.
                        </div>
                    </article>

                    <article class="rounded-[32px] bg-[linear-gradient(160deg,_#14532d_0%,_#0f172a_100%)] p-8 text-white shadow-[0_24px_80px_-40px_rgba(15,23,42,0.85)]">
                        <p class="text-xs font-semibold uppercase tracking-[0.32em] text-emerald-200">
                            Próximo sprint
                        </p>
                        <h2 class="mt-4 text-3xl font-black leading-tight">
                            Três módulos com maior retorno imediato
                        </h2>
                        <div class="mt-6 space-y-4">
                            <div class="rounded-3xl bg-white/10 p-5 backdrop-blur">
                                <h3 class="text-lg font-bold">CRUDs web para terrenos, parcelas e culturas</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-200">
                                    A API já existe; falta transformar isso em páginas de listagem, filtros e formulários.
                                </p>
                            </div>
                            <div class="rounded-3xl bg-white/10 p-5 backdrop-blur">
                                <h3 class="text-lg font-bold">Agenda operacional</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-200">
                                    Planeamento e execução de operações com estado, datas e recursos associados.
                                </p>
                            </div>
                            <div class="rounded-3xl bg-white/10 p-5 backdrop-blur">
                                <h3 class="text-lg font-bold">Colheita, lotes e stock</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-200">
                                    A próxima camada natural para fechar o ciclo produtivo e ganhar rastreabilidade.
                                </p>
                            </div>
                        </div>
                    </article>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
