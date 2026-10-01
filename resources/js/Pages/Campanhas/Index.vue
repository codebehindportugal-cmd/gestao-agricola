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
                    <p class="text-sm font-semibold text-verde-700">Custos e campanhas</p>
                    <h1 class="mt-1 text-[28px] font-bold leading-tight text-slate-900">Fechar campanhas com custos, produção e caderno de campo</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-600">
                        Esta área deve responder a três perguntas: quanto custou, quanto produziu e que operações ficaram registadas.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <section class="grid gap-4 md:grid-cols-3">
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-sm font-medium text-slate-500">Campanhas</p>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ summary.total }}</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-sm font-medium text-slate-500">Concluídas</p>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ summary.concluidas }}</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-sm font-medium text-slate-500">Custo total registado</p>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ formatCurrency(summary.custo_total) }}</p>
                    </article>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                    <div class="grid gap-4 md:grid-cols-[1.2fr_0.8fr_0.8fr_1fr]">
                        <div>
                            <InputLabel value="Pesquisar" />
                            <TextInput v-model="filterState.search" class="mt-2 block w-full rounded-lg border-slate-200" placeholder="Ano ou cultura" />
                        </div>
                        <div>
                            <InputLabel value="Estado" />
                            <select v-model="filterState.status" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todos</option>
                                <option v-for="status in statusOptions" :key="status" :value="status">{{ statusLabel(status) }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Ano" />
                            <select v-model="filterState.ano" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todos</option>
                                <option v-for="ano in anos" :key="ano" :value="ano">{{ ano }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Cultura" />
                            <select v-model="filterState.cultura_id" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
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
                        class="rounded-xl border border-slate-200 bg-white p-6"
                    >
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="text-2xl font-bold text-slate-900">{{ campanha.cultura_nome }}</h2>
                                    <span class="rounded-md px-3 py-1 text-xs font-semibold" :class="statusBadgeClass(campanha.status)">
                                        {{ statusLabel(campanha.status) }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">
                                    {{ campanha.data_inicio || 'Sem início' }} até {{ campanha.data_fim || 'Sem fim' }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Link
                                    :href="route('app.campanhas.show', campanha.id)"
                                    class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                >
                                    Ver detalhe
                                </Link>
                                <Link
                                    :href="route('app.campanhas.relatorio', campanha.id)"
                                    class="inline-flex items-center rounded-lg border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700"
                                >
                                    Relatório
                                </Link>
                                <Link
                                    :href="route('app.campanhas.caderno-campo', campanha.id)"
                                    class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100"
                                >
                                    Caderno de campo
                                </Link>
                                <Link
                                    :href="route('app.campanhas.custos-pdf', campanha.id)"
                                    class="inline-flex items-center rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-100"
                                >
                                    Custos PDF
                                </Link>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-4">
                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold text-slate-400">Produção real</p>
                                <p class="mt-2 text-lg font-bold text-slate-900">{{ formatNumber(campanha.producao_real) }} kg</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold text-slate-400">Custo total</p>
                                <p class="mt-2 text-lg font-bold text-slate-900">{{ formatCurrency(campanha.custo_total) }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold text-slate-400">Custo por kg</p>
                                <p class="mt-2 text-lg font-bold text-slate-900">{{ formatCurrency(campanha.custo_por_kg) }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold text-slate-400">Registos</p>
                                <p class="mt-2 text-lg font-bold text-slate-900">{{ campanha.operacoes_count }} operações · {{ campanha.colheitas_count }} colheitas</p>
                            </div>
                        </div>
                    </article>
                </section>

                <section v-if="!campanhas.data.length" class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm leading-7 text-slate-600">
                    Nenhuma campanha encontrada com os filtros atuais.
                </section>

                <Pagination v-if="campanhas.links?.length > 3" :links="campanhas.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
