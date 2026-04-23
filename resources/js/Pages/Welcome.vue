<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    appName: {
        type: String,
        required: true,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Head title="Gestão Agrícola" />
    <div class="min-h-screen overflow-hidden bg-[linear-gradient(135deg,_#082f49_0%,_#14532d_48%,_#f3f4f6_48%,_#f8fafc_100%)] text-slate-900">
        <div class="mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 lg:px-10">
            <header class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.35em] text-emerald-100">
                        {{ appName }}
                    </p>
                    <h1 class="mt-3 max-w-3xl text-4xl font-black leading-tight text-white md:text-6xl">
                        Operação agrícola, planeamento e execução num único sistema.
                    </h1>
                    <p class="mt-5 max-w-2xl text-base leading-8 text-emerald-50/90">
                        A plataforma já tem modelos, permissões e APIs para terrenos, parcelas, culturas, operações e maquinaria. Agora estamos a transformá-la numa aplicação pronta para uso diário.
                    </p>
                </div>

                <nav v-if="canLogin" class="flex flex-wrap items-center gap-3">
                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="route('dashboard')"
                            class="rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-900 shadow-lg shadow-slate-950/10 transition hover:bg-emerald-50"
                        >
                            Entrar no painel
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="rounded-full border border-white/30 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                        >
                            Entrar
                        </Link>
                    </template>
                </nav>
            </header>

            <main class="mt-12 grid flex-1 gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                <section class="space-y-6">
                    <div class="grid gap-4 md:grid-cols-3">
                        <article class="rounded-[28px] border border-white/15 bg-white/10 p-6 text-white backdrop-blur">
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-100">Módulos base</p>
                            <p class="mt-4 text-4xl font-black">6</p>
                            <p class="mt-3 text-sm leading-6 text-emerald-50/90">Terrenos, parcelas, culturas, operações, máquinas e alfaias.</p>
                        </article>
                        <article class="rounded-[28px] border border-white/15 bg-white/10 p-6 text-white backdrop-blur">
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-100">RBAC</p>
                            <p class="mt-4 text-4xl font-black">5</p>
                            <p class="mt-3 text-sm leading-6 text-emerald-50/90">Perfis preparados para administração, operação e consulta.</p>
                        </article>
                        <article class="rounded-[28px] border border-white/15 bg-white/10 p-6 text-white backdrop-blur">
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-100">API</p>
                            <p class="mt-4 text-4xl font-black">v1</p>
                            <p class="mt-3 text-sm leading-6 text-emerald-50/90">Estrutura pronta para evoluir sem partir integrações futuras.</p>
                        </article>
                    </div>

                    <div class="rounded-[36px] bg-white p-8 shadow-[0_24px_80px_-50px_rgba(15,23,42,0.75)]">
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-emerald-700">O que já temos</p>
                        <div class="mt-6 grid gap-4 md:grid-cols-2">
                            <div class="rounded-3xl bg-slate-50 p-5">
                                <h2 class="text-xl font-black text-slate-900">Base de dados do domínio</h2>
                                <p class="mt-2 text-sm leading-7 text-slate-600">Estrutura montada para acompanhar produção, equipamentos, equipas e custos.</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-5">
                                <h2 class="text-xl font-black text-slate-900">Controllers e validação</h2>
                                <p class="mt-2 text-sm leading-7 text-slate-600">CRUDs REST, requests de validação e políticas já tratam o núcleo operacional.</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-5">
                                <h2 class="text-xl font-black text-slate-900">Autenticação e permissões</h2>
                                <p class="mt-2 text-sm leading-7 text-slate-600">Acesso preparado por função para reduzir risco e organizar responsabilidades.</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-5">
                                <h2 class="text-xl font-black text-slate-900">Próximo salto</h2>
                                <p class="mt-2 text-sm leading-7 text-slate-600">Dar cara ao produto com páginas web, fluxos de registo e dashboards úteis.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <aside class="space-y-6">
                    <div class="rounded-[36px] border border-slate-200 bg-white/90 p-8 shadow-[0_24px_80px_-50px_rgba(15,23,42,0.65)] backdrop-blur">
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-slate-500">Roadmap imediato</p>
                        <ol class="mt-6 space-y-4">
                            <li class="rounded-3xl bg-emerald-50 p-5">
                                <p class="text-sm font-semibold text-emerald-700">1. Interface dos módulos principais</p>
                                <p class="mt-2 text-sm leading-6 text-slate-600">Listagens, formulários e filtros para o núcleo da exploração.</p>
                            </li>
                            <li class="rounded-3xl bg-amber-50 p-5">
                                <p class="text-sm font-semibold text-amber-700">2. Agenda e execução de operações</p>
                                <p class="mt-2 text-sm leading-6 text-slate-600">Planeamento diário com estado, responsáveis e recursos associados.</p>
                            </li>
                            <li class="rounded-3xl bg-sky-50 p-5">
                                <p class="text-sm font-semibold text-sky-700">3. Colheita, lotes e stock</p>
                                <p class="mt-2 text-sm leading-6 text-slate-600">Fechar o ciclo produtivo com rastreabilidade e inventário.</p>
                            </li>
                        </ol>
                    </div>

                    <div class="rounded-[36px] bg-slate-950 p-8 text-white shadow-[0_24px_80px_-50px_rgba(15,23,42,0.85)]">
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-emerald-300">Stack</p>
                        <p class="mt-4 text-3xl font-black">Laravel {{ laravelVersion }} + Vue + Inertia</p>
                        <p class="mt-4 text-sm leading-7 text-slate-300">
                            Estrutura moderna para crescermos com segurança: backend forte no domínio, frontend reativo e evolução incremental por módulos.
                        </p>
                    </div>
                </aside>
            </main>
        </div>
    </div>
</template>
