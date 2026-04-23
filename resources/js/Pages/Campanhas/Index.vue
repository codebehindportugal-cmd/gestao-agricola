<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Pagination from '@/Components/Pagination.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    campanhas: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, required: true },
    can: { type: Object, required: true },
    statusOptions: { type: Array, default: () => [] },
    anos: { type: Array, default: () => [] },
    culturas: { type: Array, default: () => [] },
});

const filterState = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    ano: props.filters.ano ?? '',
    cultura_id: props.filters.cultura_id ?? '',
});

const currentQuery = computed(() => ({
    search: filterState.search || undefined,
    status: filterState.status || undefined,
    ano: filterState.ano || undefined,
    cultura_id: filterState.cultura_id || undefined,
}));

watch(
    () => [filterState.search, filterState.status, filterState.ano, filterState.cultura_id],
    () => {
        router.get(route('app.campanhas.index'), currentQuery.value, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    },
);

const formatCurrency = (value) => new Intl.NumberFormat('pt-PT', {
    style: 'currency',
    currency: 'EUR',
}).format(value || 0);

const formatNumber = (value) => new Intl.NumberFormat('pt-PT', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(value || 0);

const statusLabel = (status) => ({
    planejada: 'planeada',
    em_curso: 'em curso',
    concluida: 'concluída',
    cancelada: 'cancelada',
}[status] ?? status);

const statusBadgeClass = (status) => ({
    planejada: 'bg-sky-50 text-sky-700',
    em_curso: 'bg-amber-50 text-amber-700',
    concluida: 'bg-emerald-50 text-emerald-700',
    cancelada: 'bg-slate-100 text-slate-600',
}[status] ?? 'bg-slate-100 text-slate-600');
</script>

<template>
    <Head title="Custos e Campanhas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-700">Custos e campanhas</p>
                    <h1 class="mt-2 text-3xl font-black text-slate-900">Fechar campanhas com custos, produção e caderno de campo</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-600">
                        Esta área deve responder a três perguntas: quanto custou, quanto produziu e que operações ficaram registadas.
                    </p>
                </div>
            </div>
        </template>

        <div class="bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_32%),linear-gradient(180deg,_#f8fafc_0%,_#eef6f1_100%)] py-10">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <section class="grid gap-4 md:grid-cols-3">
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Campanhas</p>
                        <p class="mt-3 text-4xl font-black text-slate-900">{{ summary.total }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Concluídas</p>
                        <p class="mt-3 text-4xl font-black text-emerald-700">{{ summary.concluidas }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Custo total registado</p>
                        <p class="mt-3 text-4xl font-black text-amber-700">{{ formatCurrency(summary.custo_total) }}</p>
                    </article>
                </section>

                <section class="rounded-[32px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                    <div class="grid gap-4 md:grid-cols-[1.2fr_0.8fr_0.8fr_1fr]">
                        <div>
                            <InputLabel value="Pesquisar" />
                            <TextInput v-model="filterState.search" class="mt-2 block w-full rounded-2xl border-slate-200" placeholder="Ano ou cultura" />
                        </div>
                        <div>
                            <InputLabel value="Estado" />
                            <select v-model="filterState.status" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todos</option>
                                <option v-for="status in statusOptions" :key="status" :value="status">{{ statusLabel(status) }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Ano" />
                            <select v-model="filterState.ano" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todos</option>
                                <option v-for="ano in anos" :key="ano" :value="ano">{{ ano }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Cultura" />
                            <select v-model="filterState.cultura_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todas</option>
                                <option v-for="cultura in culturas" :key="cultura.id" :value="cultura.id">{{ cultura.nome }}</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="grid gap-5">
                    <article
                        v-for="campanha in campanhas.data"
                        :key="campanha.id"
                        class="rounded-[32px] border border-white/80 bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]"
                    >
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="text-2xl font-black text-slate-900">{{ campanha.cultura_nome }} · {{ campanha.ano }}</h2>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusBadgeClass(campanha.status)">
                                        {{ statusLabel(campanha.status) }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">
                                    {{ campanha.data_inicio || 'Sem início' }} até {{ campanha.data_fim || 'Sem fim' }}
                                </p>
                            </div>

                            <Link
                                :href="route('app.campanhas.exportar', campanha.id)"
                                class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100"
                            >
                                Exportar relatório
                            </Link>
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-4">
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Produção real</p>
                                <p class="mt-2 text-lg font-bold text-slate-900">{{ formatNumber(campanha.producao_real) }} kg</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Custo real</p>
                                <p class="mt-2 text-lg font-bold text-slate-900">{{ formatCurrency(campanha.custo_real) }}</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Custo por kg</p>
                                <p class="mt-2 text-lg font-bold text-slate-900">{{ formatCurrency(campanha.custo_por_kg) }}</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Registos</p>
                                <p class="mt-2 text-lg font-bold text-slate-900">{{ campanha.operacoes_count }} operações · {{ campanha.colheitas_count }} colheitas</p>
                            </div>
                        </div>
                    </article>
                </section>

                <section v-if="!campanhas.data.length" class="rounded-[32px] border border-dashed border-slate-300 bg-white/70 px-6 py-12 text-center text-sm leading-7 text-slate-600">
                    Nenhuma campanha encontrada com os filtros atuais.
                </section>

                <Pagination v-if="campanhas.links?.length > 3" :links="campanhas.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
