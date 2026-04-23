<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DashboardPolygonsMap from '@/Components/DashboardPolygonsMap.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: { type: Array, default: () => [] },
    statusCards: { type: Array, default: () => [] },
    recentOperations: { type: Array, default: () => [] },
    focusAreas: { type: Array, default: () => [] },
    mapPolygons: { type: Array, default: () => [] },
});

const toneClasses = {
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    amber: 'border-amber-200 bg-amber-50 text-amber-700',
    sky: 'border-sky-200 bg-sky-50 text-sky-700',
};
</script>

<template>
    <Head title="Hoje" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-700">Hoje na exploração</p>
                    <h1 class="mt-2 text-3xl font-black text-slate-900">Painel simples para operar, registar e controlar custos</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-600">
                        O foco deve estar em registar o trabalho no campo, fechar o caderno de campo e perceber os custos por campanha.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Link
                        :href="route('app.operacoes.index')"
                        class="inline-flex items-center rounded-full bg-emerald-700 px-5 py-3 text-sm font-medium text-white transition hover:bg-emerald-600"
                    >
                        Abrir caderno
                    </Link>
                    <Link
                        :href="route('app.campanhas.index')"
                        class="inline-flex items-center rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Ver custos
                    </Link>
                </div>
            </div>
        </template>

        <div class="bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_32%),linear-gradient(180deg,_#f8fafc_0%,_#eef6f1_100%)] py-10">
            <div class="mx-auto flex max-w-7xl flex-col gap-8 px-4 sm:px-6 lg:px-8">
                <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="stat in stats"
                        :key="stat.label"
                        class="rounded-[28px] border border-white/80 bg-white/90 p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]"
                    >
                        <p class="text-sm font-medium text-slate-500">{{ stat.label }}</p>
                        <p class="mt-4 text-4xl font-black tracking-tight text-slate-900">{{ stat.value }}</p>
                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ stat.description }}</p>
                    </article>
                </section>

                <section class="grid gap-5 lg:grid-cols-[1.15fr_0.85fr]">
                    <article class="rounded-[32px] border border-slate-200 bg-white p-8 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Atividade recente</p>
                                <h2 class="mt-3 text-2xl font-black text-slate-900">Últimas operações registadas</h2>
                            </div>
                            <Link :href="route('app.operacoes.index')" class="text-sm font-semibold text-emerald-700">
                                Ver tudo
                            </Link>
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
                            Ainda não existem operações recentes. O próximo passo é começar a registar o trabalho diário no módulo Caderno.
                        </div>
                    </article>

                    <article class="rounded-[32px] bg-slate-950 p-8 text-white shadow-[0_24px_80px_-40px_rgba(15,23,42,0.85)]">
                        <p class="text-xs font-semibold uppercase tracking-[0.32em] text-emerald-300">Prioridades</p>
                        <div class="mt-6 space-y-4">
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
                </section>

                <section class="grid gap-5 lg:grid-cols-[1fr_1fr]">
                    <article class="rounded-[32px] border border-white/80 bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.32em] text-emerald-700">Mapa da exploração</p>
                                <h2 class="mt-3 text-2xl font-black text-slate-900">Terrenos e parcelas desenhados</h2>
                                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                                    Vista rápida da estrutura da exploração para localizar parcelas e confirmar limites.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                                <span class="rounded-full bg-emerald-50 px-3 py-2 text-emerald-700">Terrenos</span>
                                <span class="rounded-full bg-sky-50 px-3 py-2 text-sky-700">Parcelas</span>
                                <span class="rounded-full bg-slate-100 px-3 py-2 text-slate-600">{{ mapPolygons.length }} polígonos</span>
                            </div>
                        </div>

                        <DashboardPolygonsMap v-if="mapPolygons.length" :polygons="mapPolygons" height-class="h-[420px] lg:h-[520px]" />

                        <div
                            v-else
                            class="rounded-[28px] border border-dashed border-slate-300 bg-slate-50 px-6 py-14 text-center text-sm leading-7 text-slate-600"
                        >
                            Ainda não existem polígonos guardados. Desenha um terreno ou uma parcela para aparecer aqui.
                        </div>
                    </article>

                    <article class="rounded-[32px] border border-emerald-100 bg-white p-8 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-xs font-semibold uppercase tracking-[0.32em] text-emerald-700">Fluxo recomendado</p>
                        <div class="mt-6 space-y-5">
                            <div v-for="area in focusAreas" :key="area.title" class="rounded-3xl bg-slate-50 p-5">
                                <h3 class="text-lg font-bold text-slate-900">{{ area.title }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ area.description }}</p>
                            </div>
                        </div>
                    </article>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
