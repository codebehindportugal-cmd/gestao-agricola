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
import { computed, onMounted, reactive, ref, watch } from 'vue';

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
    // { campanha: {...}, especies: [...], total: {...} } — null fora de época.
    resumoEspecies: { type: Object, default: null },
    produtos: { type: Array, default: () => [] },
    exploracaoDados: { type: Object, default: () => ({}) },
    contagemTipos: { type: Object, default: () => ({}) },
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
    cultura_id: props.filters.cultura_id ?? '',
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
    recursos: [],
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

const culturaFilterOptions = computed(() => {
    const seen = new Set();

    return props.culturas.filter((cultura) => {
        if (seen.has(cultura.nome)) {
            return false;
        }

        seen.add(cultura.nome);
        return true;
    });
});

const selectedCultureParcelaIds = computed(() => {
    if (!filterState.cultura_id) {
        return null;
    }

    const selectedCulture = props.culturas.find((cultura) => String(cultura.id) === String(filterState.cultura_id));

    return props.culturas
        .filter((cultura) => String(cultura.id) === String(filterState.cultura_id) || cultura.nome === selectedCulture?.nome)
        .map((cultura) => String(cultura.parcela_id));
});

const filteredParcelas = computed(() => {
    if (!selectedCultureParcelaIds.value) {
        return props.parcelas;
    }

    return props.parcelas.filter((parcela) => selectedCultureParcelaIds.value.includes(String(parcela.id)));
});

const filterParcelaId = computed(() => {
    if (
        filterState.parcela_id &&
        selectedCultureParcelaIds.value &&
        !selectedCultureParcelaIds.value.includes(String(filterState.parcela_id))
    ) {
        return '';
    }

    return filterState.parcela_id;
});

const currentQuery = computed(() => ({
    search: filterState.search || undefined,
    estado: filterState.estado || undefined,
    parcela_id: filterParcelaId.value || undefined,
    cultura_id: filterState.cultura_id || undefined,
    tipo: filterState.tipo || undefined,
}));

watch(
    () => [filterState.search, filterState.estado, filterState.parcela_id, filterState.cultura_id, filterState.tipo],
    () => {
        router.get(route('app.operacoes.index'), currentQuery.value, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    },
);

watch(() => filterState.cultura_id, () => {
    if (
        filterState.parcela_id &&
        selectedCultureParcelaIds.value &&
        !selectedCultureParcelaIds.value.includes(String(filterState.parcela_id))
    ) {
        filterState.parcela_id = '';
    }
});

const openCreateModal = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.estado = 'planejada';
    createForm.parcela_id = filterParcelaId.value || '';
    createForm.parcela_ids = filterParcelaId.value ? [filterParcelaId.value] : [];
    createForm.cultura_id = filterState.cultura_id || '';
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
    // A caixa mostra so a parte escrita a mao; as maquinas somam-se na gravacao.
    editForm.custo_real = (operacao.custo_real_extra ?? operacao.custo_real)?.toString() ?? '';
    editForm.recursos = (operacao.recursos ?? []).map((recurso) => ({
        maquina_id: recurso.maquina_id ?? '',
        alfaia_id: recurso.alfaia_id ?? '',
        nome: recurso.nome ?? '',
        papel: recurso.papel ?? '',
        unidades: recurso.unidades ?? 1,
        horas: recurso.horas ?? '',
        km: recurso.km ?? '',
        custo_hora: recurso.custo_hora ?? '',
        custo_km: recurso.custo_km ?? '',
    }));
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
        duracao_horas: data.duracao_horas || null,
        distancia_km: data.distancia_km || null,
        combustivel_gasto_l: null,
        custo_estimado: data.custo_estimado || null,
        custo_real: data.custo_real || null,
        colheita_quantidade_total: data.colheita_quantidade_total || null,
        colheita_quantidade_perdas: data.colheita_quantidade_perdas || null,
        colheita_qualidade: data.colheita_qualidade || 'comercial',
        data_hora_inicio: data.data_hora_inicio ? data.data_hora_inicio.replace('T', ' ') : '',
        data_hora_fim: data.data_hora_fim ? data.data_hora_fim.replace('T', ' ') : null,
        recursos: (data.recursos ?? [])
            .filter((recurso) => recurso.maquina_id || recurso.alfaia_id || recurso.nome)
            .map((recurso) => ({
                maquina_id: recurso.maquina_id || null,
                alfaia_id: recurso.alfaia_id || null,
                nome: recurso.nome || null,
                papel: recurso.papel || null,
                unidades: recurso.unidades || 1,
                horas: recurso.horas || null,
                km: recurso.km || null,
                custo_hora: recurso.custo_hora || null,
                custo_km: recurso.custo_km || null,
            })),
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
    planejada: 'bg-sky-50 text-sky-800',
    em_curso: 'bg-ocre-100 text-ocre-700',
    concluida: 'bg-verde-100 text-verde-800',
    cancelada: 'bg-slate-100 text-slate-600',
}[estado] ?? 'bg-slate-100 text-slate-600');

const estadoLabel = (estado) => ({
    planejada: 'Planeada',
    em_curso: 'Em curso',
    concluida: 'Concluída',
    cancelada: 'Cancelada',
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

// ─── Caderno agrupado por dia ────────────────────────────────────────────────
// Uma pulverização são 20+ operações (uma por parcela) com a mesma data:
// mostram-se juntas numa "passagem", com os totais por produto em cima.
const tiposCor = {
    'tratamento fitossanitário': 'bg-verde-600',
    'fertilização': 'bg-ocre-500',
    colheita: 'bg-red-700',
    'mobilização do solo': 'bg-slate-500',
    poda: 'bg-sky-700',
    rega: 'bg-sky-500',
    manutenção: 'bg-slate-400',
};
const corTipo = (tipo) => tiposCor[tipo] ?? 'bg-slate-400';
const nomeTipo = (tipo) => (tipo ? tipo.charAt(0).toUpperCase() + tipo.slice(1) : 'Sem tipo');

const especieClasse = (especie) => ({
    Pereira: 'bg-verde-100 text-verde-800',
    Macieira: 'bg-red-50 text-red-800',
}[especie] ?? 'bg-slate-100 text-slate-700');

// Só o dia mais recente da página abre; os outros mostram o resumo e abrem com um toque.
const diasAbertos = ref(new Set());
const alternarDia = (dia) => {
    const novo = new Set(diasAbertos.value);
    novo.has(dia) ? novo.delete(dia) : novo.add(dia);
    diasAbertos.value = novo;
};
const diaAberto = (dia) => diasAbertos.value.has(dia);
watch(() => props.operacoes.data, () => {
    const primeiro = passagens.value[0]?.chave;
    diasAbertos.value = new Set(primeiro ? [primeiro] : []);
});

const dataLonga = (dia) => {
    if (!dia) return { semana: '', numero: '–', mes: '' };
    const data = new Date(`${dia}T12:00:00`);
    return {
        semana: data.toLocaleDateString('pt-PT', { weekday: 'short' }).replace('.', '').slice(0, 3),
        numero: data.getDate(),
        mes: data.toLocaleDateString('pt-PT', { month: 'short' }).replace('.', ''),
        ano: data.getFullYear(),
    };
};

const custoOperacao = (operacao) => {
    const produtos = (operacao.produtos ?? []).reduce((soma, produto) => soma + Number(produto.custo_total ?? 0), 0);
    return produtos + Number(operacao.custo_real ?? 0);
};

const passagens = computed(() => {
    const porDia = new Map();

    for (const operacao of props.operacoes.data ?? []) {
        const dia = operacao.dia ?? 'sem-data';
        // Tratamento e adubo foliar vão na mesma passagem do pulverizador; o resto fica à parte
        const familia = ['tratamento fitossanitário', 'fertilização'].includes(operacao.tipo) ? 'pulverizacao' : operacao.tipo;
        const chave = `${dia}|${familia}`;
        if (!porDia.has(chave)) {
            porDia.set(chave, { chave, dia, operacoes: [] });
        }
        porDia.get(chave).operacoes.push(operacao);
    }

    return [...porDia.values()].map((grupo) => {
        const parcelas = new Map();
        const produtos = new Map();
        const tipos = new Map();
        let horas = 0;
        let custo = 0;
        let maiorIS = null;

        for (const operacao of grupo.operacoes) {
            tipos.set(operacao.tipo, (tipos.get(operacao.tipo) ?? 0) + 1);
            horas += Number(operacao.duracao_horas ?? 0);
            custo += custoOperacao(operacao);

            const chave = operacao.parcela_id ?? `op-${operacao.id}`;
            if (!parcelas.has(chave)) {
                parcelas.set(chave, {
                    chave,
                    nome: operacao.parcela_nome ?? 'Sem parcela',
                    terreno: operacao.terreno_nome,
                    especie: operacao.especie,
                    area: operacao.parcela_area,
                    horas: 0,
                    custo: 0,
                    produtos: [],
                    operacoes: [],
                });
            }
            const linha = parcelas.get(chave);
            linha.horas += Number(operacao.duracao_horas ?? 0);
            linha.custo += custoOperacao(operacao);
            linha.operacoes.push(operacao);
            linha.produtos.push(...(operacao.produtos ?? []));

            for (const produto of operacao.produtos ?? []) {
                const total = produtos.get(produto.nome) ?? { nome: produto.nome, quantidade: 0, unidade: produto.unidade_medida };
                total.quantidade += Number(produto.quantidade ?? 0);
                produtos.set(produto.nome, total);

                const is = Number(produto.intervalo_seguranca_dias ?? 0);
                if (is > 1 && (!maiorIS || is > maiorIS.dias)) {
                    maiorIS = { dias: is, produto: produto.nome };
                }
            }
        }

        const linhas = [...parcelas.values()].sort((a, b) => Number(b.area ?? 0) - Number(a.area ?? 0));
        const area = linhas.reduce((soma, linha) => soma + Number(linha.area ?? 0), 0);
        const primeira = grupo.operacoes[0];
        const titulo = tipos.size === 1 && linhas.length === 1
            ? `${nomeTipo(primeira.tipo)} · ${primeira.parcela_nome ?? 'sem parcela'}`
            : tipos.has('tratamento fitossanitário') || tipos.has('fertilização')
                ? `Pulverização · ${linhas.length} ${linhas.length === 1 ? 'parcela' : 'parcelas'}`
                : `${nomeTipo(primeira.tipo)} · ${linhas.length} ${linhas.length === 1 ? 'parcela' : 'parcelas'}`;

        return {
            chave: grupo.chave,
            dia: grupo.dia,
            data: dataLonga(grupo.dia === 'sem-data' ? null : grupo.dia),
            titulo,
            tipos: [...tipos.entries()].map(([tipo, total]) => ({ tipo, total })),
            linhas,
            area,
            horas,
            custo,
            maiorIS,
            meios: [primeira.maquina_nome, primeira.alfaia_nome].filter(Boolean).join(' + '),
            aplicador: primeira.aplicador_nome || primeira.operador_nome,
            produtos: [...produtos.values()].sort((a, b) => b.quantidade - a.quantidade),
            estados: [...new Set(grupo.operacoes.map((operacao) => operacao.estado))],
        };
    });
});

diasAbertos.value = new Set(passagens.value[0]?.chave ? [passagens.value[0].chave] : []);

const contagemTiposLista = computed(() => {
    const contagem = props.contagemTipos ?? {};
    return Object.entries(contagem)
        .map(([tipo, total]) => ({ tipo, total: Number(total) }))
        .sort((a, b) => b.total - a.total);
});

const euros = (valor) => new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(Number(valor ?? 0));

onMounted(() => {
    if (new URLSearchParams(window.location.search).get('nova') === '1' && props.can.create) {
        openCreateModal();
    }
});

</script>

<template>
    <Head title="Operações" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-verde-700">Campo</p>
                    <h1 class="mt-1 text-[28px] font-bold leading-tight text-slate-900">Caderno de campo</h1>
                </div>

                <div class="flex flex-wrap gap-2">
                    <SecondaryButton v-if="can.create" @click="openExploracaoModal">Dados da exploração</SecondaryButton>
                    <SecondaryButton v-if="can.create" @click="openProductModal()">Novo produto</SecondaryButton>
                    <PrimaryButton v-if="can.create" @click="openCreateModal">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                        Nova operação
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto flex max-w-7xl flex-col gap-5 px-4 sm:px-6 lg:px-8">
                <div v-if="flashSuccess" class="rounded-xl border border-verde-200 bg-verde-50 px-5 py-4 text-sm font-medium text-verde-800" role="status">
                    {{ flashSuccess }}
                </div>

                <section aria-label="Resumo" class="cartao grid grid-cols-2 md:grid-cols-4">
                    <div class="flex flex-col gap-1 p-4 sm:p-5">
                        <span class="text-sm text-slate-600">Operações</span>
                        <span class="numero text-xl font-bold sm:text-2xl">{{ formatNumber(summary.total) }}</span>
                    </div>
                    <div class="flex flex-col gap-1 border-l border-slate-200 p-4 sm:p-5">
                        <span class="text-sm text-slate-600">Concluídas</span>
                        <span class="numero text-xl font-bold sm:text-2xl">{{ formatNumber(summary.concluidas) }}</span>
                    </div>
                    <div class="flex flex-col gap-1 border-t border-slate-200 p-4 sm:p-5 md:border-l md:border-t-0">
                        <span class="text-sm text-slate-600">Em curso</span>
                        <span class="numero text-xl font-bold sm:text-2xl">{{ formatNumber(summary.em_curso) }}</span>
                    </div>
                    <div class="flex flex-col gap-1 border-l border-t border-slate-200 p-4 sm:p-5 md:border-t-0">
                        <span class="text-sm text-slate-600">Planeadas</span>
                        <span class="numero text-xl font-bold sm:text-2xl">{{ formatNumber(summary.planeadas) }}</span>
                    </div>
                </section>

                <section aria-label="Filtros" class="cartao flex flex-col gap-4 p-4 sm:p-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <label class="relative flex w-full items-center lg:max-w-md">
                            <span class="sr-only">Pesquisar</span>
                            <svg class="pointer-events-none absolute left-3 h-5 w-5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                            <TextInput v-model="filterState.search" type="search" class="block w-full pl-10" placeholder="Produto, tipo ou observação" />
                        </label>
                        <button
                            v-if="filterState.search || filterState.estado || filterState.parcela_id || filterState.cultura_id || filterState.tipo"
                            type="button"
                            class="min-h-[44px] self-start rounded-lg px-3 text-sm font-semibold text-verde-700 hover:bg-verde-50 lg:self-auto"
                            @click="filterState.search = ''; filterState.estado = ''; filterState.parcela_id = ''; filterState.cultura_id = ''; filterState.tipo = ''"
                        >
                            Limpar filtros
                        </button>
                    </div>

                    <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1" role="group" aria-label="Tipo de operação">
                        <button
                            type="button"
                            :aria-pressed="!filterState.tipo"
                            class="inline-flex min-h-[40px] shrink-0 items-center gap-2 rounded-lg border px-3 text-sm transition"
                            :class="!filterState.tipo ? 'border-verde-700 bg-verde-700 font-semibold text-white' : 'border-slate-300 bg-white font-medium text-slate-800 hover:bg-slate-50'"
                            @click="filterState.tipo = ''"
                        >
                            Todas
                            <span class="numero" :class="!filterState.tipo ? 'text-verde-100' : 'text-slate-500'">{{ formatNumber(summary.total) }}</span>
                        </button>
                        <button
                            v-for="item in contagemTiposLista"
                            :key="item.tipo"
                            type="button"
                            :aria-pressed="filterState.tipo === item.tipo"
                            class="inline-flex min-h-[40px] shrink-0 items-center gap-2 rounded-lg border px-3 text-sm transition"
                            :class="filterState.tipo === item.tipo ? 'border-verde-700 bg-verde-700 font-semibold text-white' : 'border-slate-300 bg-white font-medium text-slate-800 hover:bg-slate-50'"
                            @click="filterState.tipo = filterState.tipo === item.tipo ? '' : item.tipo"
                        >
                            <span class="h-2 w-2 rounded-full" :class="corTipo(item.tipo)" aria-hidden="true" />
                            {{ nomeTipo(item.tipo) }}
                            <span class="numero" :class="filterState.tipo === item.tipo ? 'text-verde-100' : 'text-slate-500'">{{ formatNumber(item.total) }}</span>
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="flex flex-col gap-1.5 text-xs font-semibold text-slate-600">
                            Parcela
                            <select v-model="filterState.parcela_id" class="min-h-[44px] rounded-lg border-slate-300 text-sm font-normal text-slate-900 focus:border-verde-600 focus:ring-verde-600">
                                <option value="">Todas as parcelas</option>
                                <option v-for="parcela in filteredParcelas" :key="parcela.id" :value="String(parcela.id)">{{ parcela.nome }}</option>
                            </select>
                        </label>
                        <label class="flex flex-col gap-1.5 text-xs font-semibold text-slate-600">
                            Cultura
                            <select v-model="filterState.cultura_id" class="min-h-[44px] rounded-lg border-slate-300 text-sm font-normal text-slate-900 focus:border-verde-600 focus:ring-verde-600">
                                <option value="">Todas as culturas</option>
                                <option v-for="cultura in culturaFilterOptions" :key="cultura.id" :value="String(cultura.id)">{{ cultura.nome }}</option>
                            </select>
                        </label>
                        <label class="flex flex-col gap-1.5 text-xs font-semibold text-slate-600">
                            Estado
                            <select v-model="filterState.estado" class="min-h-[44px] rounded-lg border-slate-300 text-sm font-normal text-slate-900 focus:border-verde-600 focus:ring-verde-600">
                                <option value="">Todos</option>
                                <option v-for="estado in estadoOptions" :key="estado" :value="estado">{{ estadoLabel(estado) }}</option>
                            </select>
                        </label>
                    </div>
                </section>

                <section
                    v-for="passagem in passagens"
                    :key="passagem.chave"
                    class="cartao overflow-hidden"
                    :aria-label="`${passagem.titulo}, ${passagem.data.numero} ${passagem.data.mes}`"
                >
                    <button
                        type="button"
                        class="grid w-full grid-cols-[40px_minmax(0,1fr)_20px] items-start gap-3 p-4 text-left hover:bg-slate-50 sm:grid-cols-[60px_minmax(0,1fr)_auto] sm:gap-4 sm:p-5"
                        :aria-expanded="diaAberto(passagem.chave)"
                        @click="alternarDia(passagem.chave)"
                    >
                        <span class="flex flex-col items-center leading-none">
                            <span class="text-xs font-semibold uppercase text-slate-500">{{ passagem.data.semana }}</span>
                            <span class="mt-1 text-[26px] font-bold text-slate-900">{{ passagem.data.numero }}</span>
                            <span class="mt-1 text-xs font-semibold uppercase text-slate-500">{{ passagem.data.mes }}</span>
                        </span>
                        <span class="flex min-w-0 flex-col gap-2">
                            <span class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                <span class="text-base font-semibold text-slate-900 sm:text-lg">{{ passagem.titulo }}</span>
                                <span v-for="item in passagem.tipos" :key="item.tipo" class="inline-flex items-center gap-1.5 text-sm text-slate-700">
                                    <span class="h-2 w-2 rounded-full" :class="corTipo(item.tipo)" aria-hidden="true" />{{ nomeTipo(item.tipo) }}
                                </span>
                                <span v-for="estado in passagem.estados" :key="estado" class="etiqueta" :class="estadoBadgeClass(estado)">{{ estadoLabel(estado) }}</span>
                            </span>
                            <span class="numero text-base font-bold text-slate-900 sm:hidden">{{ euros(passagem.custo) }}</span>
                            <span class="text-sm text-slate-600">
                                <template v-if="passagem.area">{{ formatNumber(passagem.area) }} ha · </template>
                                <template v-if="passagem.horas">{{ formatNumber(passagem.horas) }} h · </template>
                                <template v-if="passagem.meios">{{ passagem.meios }}</template>
                                <template v-if="passagem.aplicador"> · {{ passagem.aplicador }}</template>
                            </span>
                            <span v-if="passagem.produtos.length" class="flex flex-wrap gap-1.5">
                                <span v-for="produto in passagem.produtos.slice(0, 8)" :key="produto.nome" class="inline-flex items-baseline gap-1.5 whitespace-nowrap rounded-md bg-slate-100 px-2 py-1 text-[13px]">
                                    <b class="font-semibold text-slate-900">{{ produto.nome }}</b>
                                    <span class="numero text-slate-700">{{ formatNumber(produto.quantidade) }} {{ produto.unidade }}</span>
                                </span>
                                <span v-if="passagem.produtos.length > 8" class="px-1 py-1 text-[13px] text-slate-600">+{{ passagem.produtos.length - 8 }}</span>
                            </span>
                        </span>
                        <span class="flex flex-col items-end gap-2">
                            <span class="numero hidden whitespace-nowrap text-lg font-bold text-slate-900 sm:inline">{{ euros(passagem.custo) }}</span>
                            <svg class="h-5 w-5 text-slate-500 transition" :class="diaAberto(passagem.chave) ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                        </span>
                    </button>

                    <div v-show="diaAberto(passagem.chave)" class="border-t border-slate-200">
                        <!-- Tabela (computador) -->
                        <div class="hidden md:block" role="table" :aria-label="`Parcelas de ${passagem.data.numero} ${passagem.data.mes}`">
                            <div role="row" class="grid grid-cols-[minmax(170px,1.2fr)_100px_70px_minmax(0,3fr)_60px_96px_88px] gap-4 border-b border-slate-200 bg-slate-50 px-5 py-2.5 text-xs font-semibold text-slate-600">
                                <span role="columnheader">Parcela</span>
                                <span role="columnheader">Cultura</span>
                                <span role="columnheader" class="text-right">Área</span>
                                <span role="columnheader">Produtos · quantidade · dose</span>
                                <span role="columnheader" class="text-right">Horas</span>
                                <span role="columnheader" class="text-right">Custo</span>
                                <span role="columnheader"><span class="sr-only">Ações</span></span>
                            </div>
                            <div
                                v-for="linha in passagem.linhas"
                                :key="linha.chave"
                                role="row"
                                class="grid grid-cols-[minmax(170px,1.2fr)_100px_70px_minmax(0,3fr)_60px_96px_88px] items-center gap-4 border-b border-slate-100 px-5 py-2.5 text-sm last:border-b-0"
                            >
                                <span role="cell" class="flex min-w-0 flex-col">
                                    <span class="truncate font-semibold text-slate-900">{{ linha.nome }}</span>
                                    <span v-if="linha.terreno && linha.terreno !== linha.nome" class="truncate text-xs text-slate-500">{{ linha.terreno }}</span>
                                </span>
                                <span role="cell"><span v-if="linha.especie" class="etiqueta" :class="especieClasse(linha.especie)">{{ linha.especie }}</span></span>
                                <span role="cell" class="numero text-right">{{ linha.area ? `${formatNumber(linha.area)} ha` : '—' }}</span>
                                <span role="cell" class="flex min-w-0 flex-wrap gap-x-4 gap-y-1">
                                    <span v-for="produto in linha.produtos" :key="`${linha.chave}-${produto.produto_id}`" class="inline-flex items-baseline gap-1.5 whitespace-nowrap text-[13px]">
                                        <b class="font-semibold text-slate-900">{{ produto.nome }}</b>
                                        <span class="numero">{{ formatNumber(produto.quantidade) }} {{ produto.unidade_medida }}</span>
                                        <span v-if="produto.dose" class="numero text-slate-500">{{ formatNumber(produto.dose) }} {{ produto.dose_unidade }}</span>
                                    </span>
                                    <span v-if="!linha.produtos.length" class="text-[13px] text-slate-500">
                                        {{ linha.operacoes.map((operacao) => operacao.observacoes).filter(Boolean)[0] || 'Sem produtos' }}
                                    </span>
                                </span>
                                <span role="cell" class="numero text-right text-slate-700">{{ linha.horas ? formatNumber(linha.horas) : '—' }}</span>
                                <span role="cell" class="numero text-right font-semibold">{{ euros(linha.custo) }}</span>
                                <span role="cell" class="flex justify-end gap-1">
                                    <template v-for="operacao in linha.operacoes" :key="operacao.id">
                                        <button
                                            v-if="operacao.can_update"
                                            type="button"
                                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 hover:text-slate-900"
                                            :aria-label="`Editar ${operacao.tipo} em ${linha.nome}`"
                                            :title="`Editar ${operacao.tipo}`"
                                            @click="openEditModal(operacao)"
                                        >
                                            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16z" /><path d="m13.5 6.5 4 4" /></svg>
                                        </button>
                                        <button
                                            v-if="operacao.can_delete"
                                            type="button"
                                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-600 hover:bg-red-50 hover:text-red-700"
                                            :aria-label="`Remover ${operacao.tipo} em ${linha.nome}`"
                                            :title="`Remover ${operacao.tipo}`"
                                            @click="deleteOperacao(operacao)"
                                        >
                                            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" /></svg>
                                        </button>
                                    </template>
                                </span>
                            </div>
                        </div>

                        <!-- Lista (telemóvel) -->
                        <ul class="divide-y divide-slate-100 md:hidden">
                            <li v-for="linha in passagem.linhas" :key="`m-${linha.chave}`" class="flex flex-col gap-2 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="flex min-w-0 flex-col">
                                        <span class="font-semibold text-slate-900">{{ linha.nome }}</span>
                                        <span class="text-xs text-slate-600">
                                            <template v-if="linha.especie">{{ linha.especie }} · </template>
                                            <template v-if="linha.area">{{ formatNumber(linha.area) }} ha</template>
                                        </span>
                                    </span>
                                    <span class="numero whitespace-nowrap text-sm font-semibold">{{ euros(linha.custo) }}</span>
                                </div>
                                <div v-if="linha.produtos.length" class="flex flex-wrap gap-x-3 gap-y-1">
                                    <span v-for="produto in linha.produtos" :key="`mp-${linha.chave}-${produto.produto_id}`" class="text-[13px]">
                                        <b class="font-semibold">{{ produto.nome }}</b>
                                        <span class="numero text-slate-700"> {{ formatNumber(produto.quantidade) }} {{ produto.unidade_medida }}</span>
                                    </span>
                                </div>
                                <div class="flex gap-2">
                                    <template v-for="operacao in linha.operacoes" :key="`mo-${operacao.id}`">
                                        <button v-if="operacao.can_update" type="button" class="min-h-[40px] rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-800" @click="openEditModal(operacao)">
                                            Editar<span v-if="linha.operacoes.length > 1"> {{ operacao.tipo }}</span>
                                        </button>
                                    </template>
                                </div>
                            </li>
                        </ul>

                        <div class="flex flex-col gap-1 border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <span class="font-semibold">Total do dia: <span class="numero">{{ euros(passagem.custo) }}</span></span>
                            <span v-if="passagem.maiorIS" class="text-slate-600">
                                Intervalo de segurança mais longo: {{ passagem.maiorIS.produto }}, {{ passagem.maiorIS.dias }} dias
                            </span>
                        </div>
                    </div>
                </section>

                <section v-if="!passagens.length" class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-600">
                    Nenhuma operação encontrada com estes filtros.
                </section>

                <div v-if="operacoes.links?.length > 3" class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-sm text-slate-600">
                        Dias {{ operacoes.from }}–{{ operacoes.to }} de {{ operacoes.total }}
                    </span>
                    <Pagination :links="operacoes.links" />
                </div>
            </div>
        </div>

        <Modal :show="createModalOpen" max-width="2xl" @close="closeCreateModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-900">Nova operação</h2>
                <p class="mt-2 text-sm text-slate-500">Regista uma atividade agrícola com recursos, produtos e custos associados.</p>

                <div class="mt-6 space-y-5">
                    <div v-if="createErrorMessages.length" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível guardar a operação. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in createErrorMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <OperacaoForm
                        :key="createModalOpen ? 'create-open' : 'create-closed'"
                        :form="createForm"
                        :parcelas="filteredParcelas"
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
                <h2 class="text-2xl font-bold text-slate-900">Editar operação</h2>
                <p class="mt-2 text-sm text-slate-500">Atualiza os dados operacionais desta atividade.</p>

                <div class="mt-6 space-y-5">
                    <div v-if="editErrorMessages.length" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
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
                        :operacao-id="editingOperacao?.id"
                        :colheitas-count="editingOperacao?.colheitas_count ?? 0"
                        :image-path="editingOperacao?.image_path"
                        submit-label="Atualizar operação"
                        submit-button-class="bg-slate-900 hover:bg-slate-800 focus:bg-slate-800 text-white font-semibold inline-flex items-center"
                        @submit="submitEdit"
                        @cancel="closeEditModal"
                        @open-product-modal="openProductModal"
                        @image-uploaded="(path) => { if (editingOperacao) editingOperacao.image_path = path; }"
                    />
                </div>
            </div>
        </Modal>

        <Modal :show="exploracaoModalOpen" max-width="lg" @close="closeExploracaoModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-900">Dados da exploração</h2>

                <form class="mt-6 grid gap-4" @submit.prevent="submitExploracao">
                    <div v-if="exploracaoErrorMessages.length" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <ul class="list-disc space-y-1 pl-5">
                            <li v-for="message in exploracaoErrorMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Produtor / dono da exploração" />
                        <TextInput v-model="exploracaoForm.produtor_nome" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="exploracaoForm.errors.produtor_nome" />
                    </div>
                    <div>
                        <InputLabel value="Concelho" />
                        <TextInput v-model="exploracaoForm.concelho" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="exploracaoForm.errors.concelho" />
                    </div>
                    <div>
                        <InputLabel value="Freguesia" />
                        <TextInput v-model="exploracaoForm.freguesia" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="exploracaoForm.errors.freguesia" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-lg px-4 py-2 text-sm " @click="closeExploracaoModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="rounded-lg bg-emerald-700 px-4 py-2 text-sm hover:bg-verde-800 focus:bg-verde-800 text-white font-semibold inline-flex items-center" :disabled="exploracaoForm.processing">
                            Guardar
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="productModalOpen" max-width="2xl" @close="closeProductModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-900">Novo produto</h2>
                <p class="mt-2 text-sm text-slate-500">Cria produtos para usar nas operações, como fitofármacos, fertilizantes e sementes.</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitProduct">
                    <div v-if="productErrorMessages.length" class="sm:col-span-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível guardar o produto. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in productErrorMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Nome" />
                        <TextInput v-model="productForm.nome" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="productForm.errors.nome" />
                    </div>

                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="productForm.tipo" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
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
                        <TextInput v-model="productForm.unidade_medida" class="mt-2 block w-full rounded-lg" placeholder="kg, L, un" />
                        <InputError class="mt-2" :message="productForm.errors.unidade_medida" />
                    </div>

                    <div>
                        <InputLabel value="Custo unitário" />
                        <TextInput v-model="productForm.custo_unitario" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="productForm.errors.custo_unitario" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Código interno" />
                        <TextInput v-model="productForm.codigo_interno" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="productForm.errors.codigo_interno" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="N.º AV/APV/ACP/AE" />
                        <TextInput v-model="productForm.numero_autorizacao_dgav" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="productForm.errors.numero_autorizacao_dgav" />
                    </div>

                    <div v-if="productForm.tipo === 'fitofarmaco'" class="sm:col-span-2">
                        <InputLabel value="Estabelecimento de venda" />
                        <TextInput v-model="productForm.estabelecimento_venda_nome" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="productForm.errors.estabelecimento_venda_nome" />
                    </div>

                    <div v-if="productForm.tipo === 'fitofarmaco'" class="sm:col-span-2">
                        <InputLabel value="N.º autorização do estabelecimento" />
                        <TextInput v-model="productForm.estabelecimento_venda_autorizacao" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="productForm.errors.estabelecimento_venda_autorizacao" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Descrição" />
                        <textarea v-model="productForm.descricao" rows="3" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="productForm.errors.descricao" />
                    </div>

                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-lg px-4 py-2 text-sm " @click="closeProductModal">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton class="rounded-lg bg-emerald-700 px-4 py-2 text-sm hover:bg-verde-800 focus:bg-verde-800 text-white font-semibold inline-flex items-center" :disabled="productForm.processing">
                            Guardar produto
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
