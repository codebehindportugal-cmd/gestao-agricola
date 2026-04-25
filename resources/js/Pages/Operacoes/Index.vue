<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import OperacaoForm from '@/Components/OperacaoForm.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    operacoes: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, required: true },
    can: { type: Object, required: true },
    estadoOptions: { type: Array, default: () => [] },
    tipoOptions: { type: Array, default: () => [] },
    parcelas: { type: Array, default: () => [] },
    culturas: { type: Array, default: () => [] },
    maquinas: { type: Array, default: () => [] },
    alfaias: { type: Array, default: () => [] },
    operadores: { type: Array, default: () => [] },
    funcionarios: { type: Array, default: () => [] },
    equipas: { type: Array, default: () => [] },
    campanhas: { type: Array, default: () => [] },
    cadernoCampo: { type: Array, default: () => [] },
    produtos: { type: Array, default: () => [] },
    stockResumo: { type: Array, default: () => [] },
    exploracaoDados: { type: Object, default: () => ({}) },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const createModalOpen = ref(false);
const productModalOpen = ref(false);
const exploracaoModalOpen = ref(false);
const editingOperacao = ref(null);

const filterState = reactive({
    search: props.filters.search ?? '',
    estado: props.filters.estado ?? '',
    parcela_id: props.filters.parcela_id ?? '',
    tipo: props.filters.tipo ?? '',
});

const baseFormData = {
    parcela_id: props.filters.parcela_id ?? '',
    parcela_ids: [],
    cultura_id: '',
    campanha_id: '',
    tipo: '',
    data_hora_inicio: '',
    data_hora_fim: '',
    maquina_id: '',
    alfaia_id: '',
    operador_id: '',
    funcionario_id: '',
    equipa_id: '',
    produtor_nome: '',
    aplicador_nome: '',
    aplicador_numero_autorizacao: '',
    exploracao_concelho: '',
    exploracao_freguesia: '',
    duracao_horas: '',
    distancia_km: '',
    combustivel_gasto_l: '',
    custo_estimado: '',
    custo_real: '',
    colheita_quantidade_total: '',
    colheita_quantidade_perdas: '',
    colheita_qualidade: 'comercial',
    estado: 'planejada',
    observacoes: '',
    produtos: [],
};

const createForm = useForm({ ...baseFormData });
const editForm = useForm({ ...baseFormData });
const productForm = useForm({
    nome: '',
    tipo: 'fitofarmaco',
    unidade_medida: 'L',
    custo_unitario: '',
    codigo_interno: '',
    numero_autorizacao_dgav: '',
    estabelecimento_venda_nome: '',
    estabelecimento_venda_autorizacao: '',
    descricao: '',
});
const exploracaoForm = useForm({
    produtor_nome: props.exploracaoDados.produtor_nome ?? '',
    concelho: props.exploracaoDados.concelho ?? '',
    freguesia: props.exploracaoDados.freguesia ?? '',
});

const createErrorMessages = computed(() => Object.values(createForm.errors));
const editErrorMessages = computed(() => Object.values(editForm.errors));
const productErrorMessages = computed(() => Object.values(productForm.errors));
const exploracaoErrorMessages = computed(() => Object.values(exploracaoForm.errors));

const currentQuery = computed(() => ({
    search: filterState.search || undefined,
    estado: filterState.estado || undefined,
    parcela_id: filterState.parcela_id || undefined,
    tipo: filterState.tipo || undefined,
}));

watch(
    () => [filterState.search, filterState.estado, filterState.parcela_id, filterState.tipo],
    () => {
        router.get(route('app.operacoes.index'), currentQuery.value, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    },
);

const openCreateModal = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.estado = 'planejada';
    createForm.parcela_id = filterState.parcela_id || '';
    createForm.parcela_ids = filterState.parcela_id ? [filterState.parcela_id] : [];
    createForm.produtos = [];
    createModalOpen.value = true;
};

const closeCreateModal = () => {
    createModalOpen.value = false;
    createForm.clearErrors();
};

const openEditModal = (operacao) => {
    editingOperacao.value = operacao;
    editForm.reset();
    editForm.clearErrors();
    editForm.parcela_id = operacao.parcela_id?.toString() ?? '';
    editForm.parcela_ids = [];
    editForm.cultura_id = operacao.cultura_id?.toString() ?? '';
    editForm.campanha_id = operacao.campanha_id?.toString() ?? '';
    editForm.tipo = operacao.tipo ?? '';
    editForm.data_hora_inicio = operacao.data_hora_inicio ?? '';
    editForm.data_hora_fim = operacao.data_hora_fim ?? '';
    editForm.maquina_id = operacao.maquina_id?.toString() ?? '';
    editForm.alfaia_id = operacao.alfaia_id?.toString() ?? '';
    editForm.operador_id = operacao.operador_id?.toString() ?? '';
    editForm.funcionario_id = operacao.funcionario_id?.toString() ?? '';
    editForm.equipa_id = operacao.equipa_id?.toString() ?? '';
    editForm.produtor_nome = operacao.produtor_nome ?? '';
    editForm.aplicador_nome = operacao.aplicador_nome ?? '';
    editForm.aplicador_numero_autorizacao = operacao.aplicador_numero_autorizacao ?? '';
    editForm.exploracao_concelho = operacao.exploracao_concelho ?? '';
    editForm.exploracao_freguesia = operacao.exploracao_freguesia ?? '';
    editForm.duracao_horas = operacao.duracao_horas?.toString() ?? '';
    editForm.distancia_km = operacao.distancia_km?.toString() ?? '';
    editForm.combustivel_gasto_l = operacao.combustivel_gasto_l?.toString() ?? '';
    editForm.custo_estimado = operacao.custo_estimado?.toString() ?? '';
    editForm.custo_real = operacao.custo_real?.toString() ?? '';
    editForm.colheita_quantidade_total = operacao.colheita_quantidade_total?.toString() ?? '';
    editForm.colheita_quantidade_perdas = operacao.colheita_quantidade_perdas?.toString() ?? '';
    editForm.colheita_qualidade = operacao.colheita_qualidade ?? 'comercial';
    editForm.estado = operacao.estado ?? 'planejada';
    editForm.observacoes = operacao.observacoes ?? '';
    editForm.produtos = (operacao.produtos ?? []).map((produto) => ({
        produto_id: produto.produto_id?.toString() ?? '',
        quantidade: produto.quantidade?.toString() ?? '',
        unidade_medida: produto.unidade_medida ?? '',
        dose: produto.dose?.toString() ?? '',
        dose_unidade: produto.dose_unidade ?? '',
        area_tratada: produto.area_tratada?.toString() ?? '',
        volume_calda: produto.volume_calda?.toString() ?? '',
        finalidade: produto.finalidade ?? '',
        intervalo_seguranca_dias: produto.intervalo_seguranca_dias?.toString() ?? '',
        estabelecimento_venda_nome: produto.estabelecimento_venda_nome ?? '',
        estabelecimento_venda_autorizacao: produto.estabelecimento_venda_autorizacao ?? '',
        custo_unitario: produto.custo_unitario?.toString() ?? '',
        observacoes: produto.observacoes ?? '',
    }));
};

const closeEditModal = () => {
    editingOperacao.value = null;
    editForm.clearErrors();
};

const openProductModal = (type = 'fitofarmaco') => {
    productForm.defaults({
        nome: '',
        tipo: type,
        unidade_medida: type === 'fitofarmaco' ? 'L' : 'kg',
        custo_unitario: '',
        codigo_interno: '',
        numero_autorizacao_dgav: '',
        estabelecimento_venda_nome: '',
        estabelecimento_venda_autorizacao: '',
        descricao: '',
    });
    productForm.reset();
    productForm.clearErrors();
    productModalOpen.value = true;
};

const closeProductModal = () => {
    productModalOpen.value = false;
    productForm.clearErrors();
};

const openExploracaoModal = () => {
    exploracaoForm.defaults({
        produtor_nome: props.exploracaoDados.produtor_nome ?? '',
        concelho: props.exploracaoDados.concelho ?? '',
        freguesia: props.exploracaoDados.freguesia ?? '',
    });
    exploracaoForm.reset();
    exploracaoForm.clearErrors();
    exploracaoModalOpen.value = true;
};

const closeExploracaoModal = () => {
    exploracaoModalOpen.value = false;
    exploracaoForm.clearErrors();
};

const submitExploracao = () => {
    exploracaoForm.post(route('app.operacoes.exploracao-dados.update', currentQuery.value), {
        preserveScroll: true,
        onSuccess: () => closeExploracaoModal(),
    });
};

const normalizePayload = (form) => form.transform((data) => {
    const parcelaIds = (data.parcela_ids ?? []).filter(Boolean);

    return {
        ...data,
        parcela_id: data.parcela_id || parcelaIds[0] || null,
        parcela_ids: parcelaIds.length ? parcelaIds : null,
        cultura_id: data.cultura_id || null,
        campanha_id: data.campanha_id || null,
        maquina_id: data.maquina_id || null,
        alfaia_id: data.alfaia_id || null,
        operador_id: data.operador_id || null,
        funcionario_id: data.funcionario_id || null,
        equipa_id: data.equipa_id || null,
        duracao_horas: null,
        distancia_km: data.distancia_km || null,
        combustivel_gasto_l: null,
        custo_estimado: data.custo_estimado || null,
        custo_real: data.custo_real || null,
        colheita_quantidade_total: data.colheita_quantidade_total || null,
        colheita_quantidade_perdas: data.colheita_quantidade_perdas || null,
        colheita_qualidade: data.colheita_qualidade || 'comercial',
        data_hora_inicio: data.data_hora_inicio ? data.data_hora_inicio.replace('T', ' ') : '',
        data_hora_fim: data.data_hora_fim ? data.data_hora_fim.replace('T', ' ') : null,
        produtos: (data.produtos ?? []).filter((produto) => produto.produto_id).map((produto) => ({
            ...produto,
            quantidade: produto.quantidade || null,
            unidade_medida: produto.unidade_medida || null,
            dose: produto.dose || null,
            dose_unidade: produto.dose_unidade || null,
            area_tratada: produto.area_tratada || null,
            volume_calda: produto.volume_calda || null,
            finalidade: produto.finalidade || null,
            intervalo_seguranca_dias: produto.intervalo_seguranca_dias || null,
            estabelecimento_venda_nome: produto.estabelecimento_venda_nome || null,
            estabelecimento_venda_autorizacao: produto.estabelecimento_venda_autorizacao || null,
            custo_unitario: produto.custo_unitario || null,
            observacoes: produto.observacoes || null,
        })),
    };
});

const submitCreate = () => {
    normalizePayload(createForm).post(route('app.operacoes.store', currentQuery.value), {
        preserveScroll: true,
        onSuccess: () => closeCreateModal(),
        onFinish: () => createForm.transform((data) => data),
    });
};

const submitEdit = () => {
    if (!editingOperacao.value) {
        return;
    }

    normalizePayload(editForm).patch(route('app.operacoes.update', {
        operacao: editingOperacao.value.id,
        ...currentQuery.value,
    }), {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
        onFinish: () => editForm.transform((data) => data),
    });
};

const submitProduct = () => {
    productForm
        .transform((data) => ({
            ...data,
            custo_unitario: data.custo_unitario || null,
            codigo_interno: data.codigo_interno || null,
            estabelecimento_venda_nome: data.estabelecimento_venda_nome || null,
            estabelecimento_venda_autorizacao: data.estabelecimento_venda_autorizacao || null,
            descricao: data.descricao || null,
        }))
        .post(route('app.operacoes.produtos.store', currentQuery.value), {
            preserveScroll: true,
            onSuccess: () => closeProductModal(),
            onFinish: () => productForm.transform((data) => data),
        });
};

const deleteOperacao = (operacao) => {
    if (!window.confirm(`Remover a operação "${operacao.tipo}"?`)) {
        return;
    }

    router.delete(route('app.operacoes.destroy', {
        operacao: operacao.id,
        ...currentQuery.value,
    }), {
        preserveScroll: true,
    });
};

const estadoBadgeClass = (estado) => ({
    planejada: 'bg-sky-50 text-sky-700',
    em_curso: 'bg-amber-50 text-amber-700',
    concluida: 'bg-emerald-50 text-emerald-700',
    cancelada: 'bg-slate-100 text-slate-600',
}[estado] ?? 'bg-slate-100 text-slate-600');

const estadoLabel = (estado) => ({
    planejada: 'planeada',
    em_curso: 'em curso',
    concluida: 'concluída',
    cancelada: 'cancelada',
}[estado] ?? estado);

const productFieldSummary = (operacao) => {
    const product = operacao.produtos?.find((item) => item.finalidade);

    if (!product) {
        return null;
    }

    return `${product.finalidade} · IS ${product.intervalo_seguranca_dias ?? '-'} dias`;
};

const formatNumber = (value) => {
    return new Intl.NumberFormat('pt-PT', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));
};

const stockStats = computed(() => ({
    totalProdutos: props.stockResumo.length,
    abaixoMinimo: props.stockResumo.filter((produto) => produto.abaixo_minimo).length,
    valorTotal: props.stockResumo.reduce((total, produto) => total + Number(produto.valor_stock ?? 0), 0),
}));
</script>

<template>
    <Head title="Operações" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-700">Caderno de campo</p>
                    <h1 class="mt-2 text-3xl font-black text-slate-900">Operações</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-600">
                        Regista o trabalho no campo, os produtos aplicados, os responsáveis e os custos da operação.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <PrimaryButton
                        v-if="can.create"
                        class="justify-center rounded-full bg-emerald-700 px-5 py-3 text-sm normal-case tracking-normal hover:bg-emerald-600 focus:bg-emerald-600"
                        @click="openCreateModal"
                    >
                        Nova operação
                    </PrimaryButton>
                    <SecondaryButton
                        v-if="can.create"
                        class="justify-center rounded-full px-5 py-3 text-sm normal-case tracking-normal"
                        @click="openProductModal()"
                    >
                        Novo produto
                    </SecondaryButton>
                    <SecondaryButton
                        v-if="can.create"
                        class="justify-center rounded-full px-5 py-3 text-sm normal-case tracking-normal"
                        @click="openExploracaoModal"
                    >
                        Dados exploração
                    </SecondaryButton>
                </div>
            </div>
        </template>

        <div class="bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.16),_transparent_28%),linear-gradient(180deg,_#f8fafc_0%,_#eef6f1_100%)] py-10">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <div v-if="flashSuccess" class="rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800">
                    {{ flashSuccess }}
                </div>

                <section class="grid gap-4 md:grid-cols-4">
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Operações registadas</p>
                        <p class="mt-3 text-4xl font-black text-slate-900">{{ summary.total }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Planeadas</p>
                        <p class="mt-3 text-4xl font-black text-sky-700">{{ summary.planeadas }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Em curso</p>
                        <p class="mt-3 text-4xl font-black text-amber-700">{{ summary.em_curso }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Concluídas</p>
                        <p class="mt-3 text-4xl font-black text-emerald-700">{{ summary.concluidas }}</p>
                    </article>
                </section>

                <section class="rounded-[32px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                    <div class="grid gap-4 md:grid-cols-[1fr_0.85fr_0.85fr_0.85fr_auto]">
                        <div>
                            <InputLabel value="Pesquisar" />
                            <TextInput v-model="filterState.search" class="mt-2 block w-full rounded-2xl border-slate-200" placeholder="Tipo, estado ou observações" />
                        </div>
                        <div>
                            <InputLabel value="Estado" />
                            <select v-model="filterState.estado" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todos</option>
                                <option v-for="estado in estadoOptions" :key="estado" :value="estado">{{ estadoLabel(estado) }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Parcela" />
                            <select v-model="filterState.parcela_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todas</option>
                                <option v-for="parcela in parcelas" :key="parcela.id" :value="String(parcela.id)">{{ parcela.nome }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Tipo" />
                            <select v-model="filterState.tipo" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todos</option>
                                <option v-for="tipo in tipoOptions" :key="tipo" :value="tipo">{{ tipo }}</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <SecondaryButton class="w-full justify-center rounded-full px-5 py-3 text-sm normal-case tracking-normal" @click="filterState.search = ''; filterState.estado = ''; filterState.parcela_id = ''; filterState.tipo = ''">
                                Limpar
                            </SecondaryButton>
                        </div>
                    </div>
                </section>

                <section v-if="cadernoCampo.length" class="rounded-[32px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-xl font-black text-slate-900">Resumo por campanha</h2>
                            <p class="mt-1 text-sm text-slate-500">Visão rápida dos tratamentos fitofarmacêuticos e do custo de produtos.</p>
                        </div>
                    </div>
                    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <article v-for="campanha in cadernoCampo" :key="campanha.id" class="rounded-3xl bg-emerald-50/70 p-4">
                            <p class="text-sm font-semibold text-slate-900">{{ campanha.nome }}</p>
                            <p class="mt-3 text-3xl font-black text-emerald-700">{{ campanha.tratamentos }}</p>
                            <p class="text-xs uppercase tracking-[0.2em] text-emerald-700">tratamentos</p>
                            <p class="mt-3 text-sm font-semibold text-slate-700">Custo total: {{ formatNumber(campanha.custo_total) }} €</p>
                            <p class="mt-1 text-xs text-slate-500">Produtos: {{ formatNumber(campanha.custo_produtos) }} €</p>
                            <p v-if="campanha.custo_por_unidade" class="mt-1 text-xs font-semibold text-emerald-700">
                                {{ formatNumber(campanha.custo_por_unidade) }} €/unidade produzida
                            </p>
                            <Link
                                :href="route('app.campanhas.exportar', campanha.id)"
                                class="mt-4 inline-flex items-center rounded-full border border-emerald-200 bg-white px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100"
                            >
                                Gerar caderno
                            </Link>
                        </article>
                    </div>
                </section>

                <section v-if="stockResumo.length" class="rounded-[32px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-xl font-black text-slate-900">Stock de produtos</h2>
                            <p class="mt-1 text-sm text-slate-500">Visão rápida do que tens disponível, do mínimo definido e do valor em stock.</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl bg-slate-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Produtos</p>
                                <p class="mt-2 text-2xl font-black text-slate-900">{{ stockStats.totalProdutos }}</p>
                            </div>
                            <div class="rounded-2xl bg-amber-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">A rever</p>
                                <p class="mt-2 text-2xl font-black text-amber-700">{{ stockStats.abaixoMinimo }}</p>
                            </div>
                            <div class="rounded-2xl bg-emerald-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Valor stock</p>
                                <p class="mt-2 text-2xl font-black text-emerald-700">{{ formatNumber(stockStats.valorTotal) }} €</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="produto in stockResumo"
                            :key="produto.id"
                            class="rounded-3xl border p-4"
                            :class="produto.abaixo_minimo ? 'border-amber-200 bg-amber-50/70' : 'border-emerald-100 bg-emerald-50/50'"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ produto.nome }}</p>
                                    <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">{{ produto.tipo }}</p>
                                </div>
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="produto.abaixo_minimo ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                                >
                                    {{ produto.abaixo_minimo ? 'baixo' : 'ok' }}
                                </span>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-2xl bg-white/80 p-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Atual</p>
                                    <p class="mt-2 text-sm font-bold text-slate-900">
                                        {{ formatNumber(produto.stock_atual) }} {{ produto.unidade_medida }}
                                    </p>
                                </div>
                                <div class="rounded-2xl bg-white/80 p-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Mínimo</p>
                                    <p class="mt-2 text-sm font-bold text-slate-900">
                                        {{ formatNumber(produto.stock_minimo) }} {{ produto.unidade_medida }}
                                    </p>
                                </div>
                                <div class="rounded-2xl bg-white/80 p-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Preço</p>
                                    <p class="mt-2 text-sm font-bold text-slate-900">
                                        {{ produto.custo_unitario !== null ? `${formatNumber(produto.custo_unitario)} €` : '-' }}
                                    </p>
                                </div>
                            </div>

                            <p class="mt-4 text-sm text-slate-600">
                                Valor em stock:
                                <span class="font-semibold text-slate-900">
                                    {{ produto.valor_stock !== null ? `${formatNumber(produto.valor_stock)} €` : '-' }}
                                </span>
                            </p>
                        </article>
                    </div>
                </section>

                <section class="grid gap-5 lg:grid-cols-2">
                    <article
                        v-for="operacao in operacoes.data"
                        :key="operacao.id"
                        class="rounded-[32px] border border-white/80 bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="text-2xl font-black capitalize text-slate-900">{{ operacao.tipo }}</h2>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="estadoBadgeClass(operacao.estado)">
                                        {{ estadoLabel(operacao.estado) }}
                                    </span>
                                    <span v-if="productFieldSummary(operacao)" class="text-xs text-slate-500">
                                        {{ productFieldSummary(operacao) }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">
                                    {{ operacao.terreno_nome || 'Sem terreno' }} · {{ operacao.parcela_nome || 'Sem parcela' }}
                                </p>
                            </div>
                            <p class="text-right text-sm font-medium text-slate-500">
                                {{ operacao.updated_at || 'Sem atualização' }}
                            </p>
                        </div>

                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Início</p>
                                <p class="mt-2 text-sm text-slate-700">{{ operacao.data_hora_inicio?.replace('T', ' ') || 'Sem data' }}</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Fim</p>
                                <p class="mt-2 text-sm text-slate-700">{{ operacao.data_hora_fim?.replace('T', ' ') || 'Por definir' }}</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Responsável</p>
                                <p class="mt-2 text-sm text-slate-700">{{ operacao.operador_nome || 'Sem operador' }}</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Equipa</p>
                                <p class="mt-2 text-sm text-slate-700">{{ operacao.equipa_nome || 'Sem equipa' }}</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Máquina</p>
                                <p class="mt-2 text-sm text-slate-700">{{ operacao.maquina_nome || 'Sem máquina' }}</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Duração</p>
                                <p class="mt-2 text-sm text-slate-700">{{ operacao.duracao_horas ? `${formatNumber(operacao.duracao_horas)} h` : 'Sem duração' }}</p>
                            </div>
                            <div v-if="operacao.tipo === 'colheita'" class="rounded-3xl bg-emerald-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-emerald-700">Kg apanhados</p>
                                <p class="mt-2 text-sm font-semibold text-slate-800">{{ formatNumber(operacao.colheita_quantidade_total) }} kg</p>
                                <p v-if="operacao.colheita_quantidade_perdas" class="mt-1 text-xs text-slate-500">Perdas: {{ formatNumber(operacao.colheita_quantidade_perdas) }} kg</p>
                            </div>
                            <div class="rounded-3xl bg-amber-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-amber-600">Combustível</p>
                                <p class="mt-2 text-sm text-slate-700">{{ operacao.combustivel_gasto_l ? `${formatNumber(operacao.combustivel_gasto_l)} L` : 'Sem cálculo' }}</p>
                                <p v-if="operacao.distancia_km" class="mt-1 text-xs text-slate-500">{{ formatNumber(operacao.distancia_km) }} km</p>
                            </div>
                        </div>

                        <div v-if="operacao.produtos?.length" class="mt-5 rounded-3xl border border-emerald-100 bg-emerald-50/70 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-emerald-700">Produtos aplicados</p>
                            <div class="mt-3 space-y-2">
                                <div v-for="produto in operacao.produtos" :key="produto.produto_id" class="flex flex-col gap-1 rounded-2xl bg-white/80 p-3 text-sm text-slate-700 sm:flex-row sm:items-center sm:justify-between">
                                    <span class="font-semibold">{{ produto.nome }}</span>
                                    <span>
                                        {{ formatNumber(produto.quantidade) }} {{ produto.unidade_medida }}
                                        <span v-if="produto.custo_total"> · {{ formatNumber(produto.custo_total) }} €</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 rounded-3xl bg-amber-50/50 p-4">
                            <p class="text-sm leading-7 text-slate-600">
                                {{ operacao.observacoes || 'Sem observações para esta operação.' }}
                            </p>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link
                                :href="route('app.parcelas.index', { search: operacao.parcela_nome || undefined })"
                                class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                Ver parcela
                            </Link>
                            <PrimaryButton
                                v-if="operacao.can_update"
                                class="rounded-full bg-slate-900 px-4 py-2 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800"
                                @click="openEditModal(operacao)"
                            >
                                Editar
                            </PrimaryButton>
                            <DangerButton
                                v-if="operacao.can_delete"
                                class="rounded-full px-4 py-2 text-sm normal-case tracking-normal"
                                @click="deleteOperacao(operacao)"
                            >
                                Remover
                            </DangerButton>
                        </div>
                    </article>
                </section>

                <section v-if="!operacoes.data.length" class="rounded-[32px] border border-dashed border-slate-300 bg-white/70 px-6 py-12 text-center text-sm leading-7 text-slate-600">
                    Nenhuma operação encontrada com os filtros atuais.
                </section>

                <Pagination v-if="operacoes.links?.length > 3" :links="operacoes.links" />
            </div>
        </div>

        <Modal :show="createModalOpen" max-width="2xl" @close="closeCreateModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-black text-slate-900">Nova operação</h2>
                <p class="mt-2 text-sm text-slate-500">Regista uma atividade agrícola com recursos, produtos e custos associados.</p>

                <div class="mt-6 space-y-5">
                    <div v-if="createErrorMessages.length" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível guardar a operação. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in createErrorMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <OperacaoForm
                        :key="createModalOpen ? 'create-open' : 'create-closed'"
                        :form="createForm"
                        :parcelas="parcelas"
                        :culturas="culturas"
                        :campanhas="campanhas"
                        :maquinas="maquinas"
                        :alfaias="alfaias"
                        :operadores="operadores"
                        :funcionarios="funcionarios"
                        :equipas="equipas"
                        :produtos="produtos"
                        :exploracao-dados="exploracaoDados"
                        :tipo-options="tipoOptions"
                        :estado-options="estadoOptions"
                        allow-multiple-parcelas
                        @submit="submitCreate"
                        @cancel="closeCreateModal"
                        @open-product-modal="openProductModal"
                    />
                </div>
            </div>
        </Modal>

        <Modal :show="!!editingOperacao" max-width="2xl" @close="closeEditModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-black text-slate-900">Editar operação</h2>
                <p class="mt-2 text-sm text-slate-500">Atualiza os dados operacionais desta atividade.</p>

                <div class="mt-6 space-y-5">
                    <div v-if="editErrorMessages.length" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível atualizar a operação. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in editErrorMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <OperacaoForm
                        :key="editingOperacao ? `edit-${editingOperacao.id}` : 'edit-empty'"
                        :form="editForm"
                        :parcelas="parcelas"
                        :culturas="culturas"
                        :campanhas="campanhas"
                        :maquinas="maquinas"
                        :alfaias="alfaias"
                        :operadores="operadores"
                        :funcionarios="funcionarios"
                        :equipas="equipas"
                        :produtos="produtos"
                        :exploracao-dados="exploracaoDados"
                        :tipo-options="tipoOptions"
                        :estado-options="estadoOptions"
                        submit-label="Atualizar operação"
                        submit-button-class="bg-slate-900 hover:bg-slate-800 focus:bg-slate-800"
                        @submit="submitEdit"
                        @cancel="closeEditModal"
                        @open-product-modal="openProductModal"
                    />
                </div>
            </div>
        </Modal>

        <Modal :show="exploracaoModalOpen" max-width="lg" @close="closeExploracaoModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-black text-slate-900">Dados da exploração</h2>

                <form class="mt-6 grid gap-4" @submit.prevent="submitExploracao">
                    <div v-if="exploracaoErrorMessages.length" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <ul class="list-disc space-y-1 pl-5">
                            <li v-for="message in exploracaoErrorMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Produtor / dono da exploração" />
                        <TextInput v-model="exploracaoForm.produtor_nome" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="exploracaoForm.errors.produtor_nome" />
                    </div>
                    <div>
                        <InputLabel value="Concelho" />
                        <TextInput v-model="exploracaoForm.concelho" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="exploracaoForm.errors.concelho" />
                    </div>
                    <div>
                        <InputLabel value="Freguesia" />
                        <TextInput v-model="exploracaoForm.freguesia" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="exploracaoForm.errors.freguesia" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="closeExploracaoModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="rounded-full bg-emerald-700 px-4 py-2 text-sm normal-case tracking-normal hover:bg-emerald-600 focus:bg-emerald-600" :disabled="exploracaoForm.processing">
                            Guardar
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="productModalOpen" max-width="2xl" @close="closeProductModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-black text-slate-900">Novo produto</h2>
                <p class="mt-2 text-sm text-slate-500">Cria produtos para usar nas operações, como fitofármacos, fertilizantes e sementes.</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitProduct">
                    <div v-if="productErrorMessages.length" class="sm:col-span-2 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível guardar o produto. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in productErrorMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Nome" />
                        <TextInput v-model="productForm.nome" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.nome" />
                    </div>

                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="productForm.tipo" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="fitofarmaco">Fitofármaco</option>
                            <option value="fertilizante">Fertilizante</option>
                            <option value="semente">Semente</option>
                            <option value="planta">Planta</option>
                            <option value="combustivel">Combustível</option>
                            <option value="outro">Outro</option>
                        </select>
                        <InputError class="mt-2" :message="productForm.errors.tipo" />
                    </div>

                    <div>
                        <InputLabel value="Unidade" />
                        <TextInput v-model="productForm.unidade_medida" class="mt-2 block w-full rounded-2xl" placeholder="kg, L, un" />
                        <InputError class="mt-2" :message="productForm.errors.unidade_medida" />
                    </div>

                    <div>
                        <InputLabel value="Custo unitário" />
                        <TextInput v-model="productForm.custo_unitario" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.custo_unitario" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Código interno" />
                        <TextInput v-model="productForm.codigo_interno" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.codigo_interno" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="N.º AV/APV/ACP/AE" />
                        <TextInput v-model="productForm.numero_autorizacao_dgav" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.numero_autorizacao_dgav" />
                    </div>

                    <div v-if="productForm.tipo === 'fitofarmaco'" class="sm:col-span-2">
                        <InputLabel value="Estabelecimento de venda" />
                        <TextInput v-model="productForm.estabelecimento_venda_nome" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.estabelecimento_venda_nome" />
                    </div>

                    <div v-if="productForm.tipo === 'fitofarmaco'" class="sm:col-span-2">
                        <InputLabel value="N.º autorização do estabelecimento" />
                        <TextInput v-model="productForm.estabelecimento_venda_autorizacao" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.estabelecimento_venda_autorizacao" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Descrição" />
                        <textarea v-model="productForm.descricao" rows="3" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="productForm.errors.descricao" />
                    </div>

                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="closeProductModal">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton class="rounded-full bg-emerald-700 px-4 py-2 text-sm normal-case tracking-normal hover:bg-emerald-600 focus:bg-emerald-600" :disabled="productForm.processing">
                            Guardar produto
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
