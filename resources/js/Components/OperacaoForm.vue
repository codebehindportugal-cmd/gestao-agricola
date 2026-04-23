<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    parcelas: { type: Array, default: () => [] },
    culturas: { type: Array, default: () => [] },
    campanhas: { type: Array, default: () => [] },
    maquinas: { type: Array, default: () => [] },
    alfaias: { type: Array, default: () => [] },
    operadores: { type: Array, default: () => [] },
    funcionarios: { type: Array, default: () => [] },
    equipas: { type: Array, default: () => [] },
    produtos: { type: Array, default: () => [] },
    tipoOptions: { type: Array, default: () => [] },
    estadoOptions: { type: Array, default: () => [] },
    submitLabel: { type: String, default: 'Guardar operação' },
    submitButtonClass: {
        type: String,
        default: 'bg-emerald-700 hover:bg-emerald-600 focus:bg-emerald-600',
    },
});

const emit = defineEmits(['submit', 'cancel', 'openProductModal']);

const activeTab = ref('geral');

const productTypeConfig = {
    'tratamento fitossanitario': {
        title: 'Produtos fitofarmacêuticos',
        empty: 'Adiciona pelo menos um produto fitofarmacêutico para este tratamento.',
        tipos: ['fitofarmaco', 'fitofarmaco', 'fitofarmaceutico', 'produto fitofarmaceutico'],
        required: true,
    },
    fertilizacao: {
        title: 'Fertilizantes',
        empty: 'Adiciona os fertilizantes usados nesta operação.',
        tipos: ['fertilizante'],
        required: false,
    },
    sementeira: {
        title: 'Sementes',
        empty: 'Adiciona as sementes usadas nesta operação.',
        tipos: ['semente'],
        required: false,
    },
    plantacao: {
        title: 'Plantas ou sementes',
        empty: 'Adiciona os produtos vegetais usados nesta operação.',
        tipos: ['semente', 'planta'],
        required: false,
    },
};
const normaliseText = (value) => String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

const productConfigFor = (tipo) => productTypeConfig[normaliseText(tipo)] ?? null;
const isTratamentoFitossanitario = (tipo) => normaliseText(tipo) === 'tratamento fitossanitario';
const usesProducts = (form) => !!productConfigFor(form.tipo);
const productTitle = (form) => productConfigFor(form.tipo)?.title ?? 'Produtos';
const productEmptyText = (form) => productConfigFor(form.tipo)?.empty ?? 'Adiciona os produtos usados nesta operação.';
const productRequired = (form) => productConfigFor(form.tipo)?.required ?? false;
const estadoLabel = (estado) => ({
    planejada: 'planeada',
    em_curso: 'em curso',
    concluida: 'concluída',
    cancelada: 'cancelada',
}[estado] ?? estado);

const productOptionsFor = (form) => {
    const config = productConfigFor(form.tipo);

    if (!config) {
        return props.produtos;
    }

    const allowedTypes = config.tipos.map(normaliseText);

    return props.produtos.filter((produto) => allowedTypes.includes(normaliseText(produto.tipo)));
};

const productFormBase = () => ({
    produto_id: '',
    quantidade: '',
    unidade_medida: '',
    dose: '',
    dose_unidade: '',
    area_tratada: '',
    volume_calda: '',
    finalidade: '',
    intervalo_seguranca_dias: '',
    estabelecimento_venda_nome: '',
    estabelecimento_venda_autorizacao: '',
    custo_unitario: '',
    observacoes: '',
});

const ensureProductRows = (form) => {
    if (usesProducts(form) && !form.produtos.length) {
        form.produtos = [productFormBase()];
        return;
    }

    if (!usesProducts(form)) {
        form.produtos = [];
    }
};

const addProductRow = (form) => {
    form.produtos = [...form.produtos, productFormBase()];
};

const removeProductRow = (form, index) => {
    form.produtos = form.produtos.filter((_, rowIndex) => rowIndex !== index);
};

const updateProductDefaults = (form, index) => {
    const row = form.produtos[index];
    const produto = props.produtos.find((item) => String(item.id) === String(row.produto_id));

    if (!produto) {
        return;
    }

    row.unidade_medida = row.unidade_medida || produto.unidade_medida || 'kg';
    row.custo_unitario = row.custo_unitario || produto.custo_unitario?.toString() || '';
};

const filteredCulturas = computed(() => {
    const parcelaId = String(props.form.parcela_id || '');

    if (!parcelaId) {
        return props.culturas;
    }

    return props.culturas.filter((cultura) => String(cultura.parcela_id) === parcelaId);
});

const filteredCampanhas = computed(() => {
    const culturaId = String(props.form.cultura_id || '');

    if (!culturaId) {
        return props.campanhas;
    }

    return props.campanhas.filter((campanha) => String(campanha.cultura_id) === culturaId);
});

const totalCustoProdutos = computed(() => {
    return props.form.produtos?.reduce((total, produto) => {
        const quantidade = parseFloat(produto.quantidade) || 0;
        const custoUnitario = parseFloat(produto.custo_unitario) || 0;
        return total + (quantidade * custoUnitario);
    }, 0) || 0;
});

const formatCurrency = (value) => new Intl.NumberFormat('pt-PT', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(value);

const tabs = computed(() => [
    { id: 'geral', label: 'Geral', shortLabel: 'Geral', icon: 'calendar', visible: true },
    { id: 'recursos', label: 'Recursos', shortLabel: 'Rec.', icon: 'user', visible: true },
    { id: 'produtos', label: 'Produtos', shortLabel: 'Prod.', icon: 'flask', visible: usesProducts(props.form) },
    { id: 'custos', label: 'Custos', shortLabel: 'Custos', icon: 'euro', visible: true },
]);

const visibleTabs = computed(() => tabs.value.filter((tab) => tab.visible));

watch(() => props.form.tipo, () => {
    ensureProductRows(props.form);
    if (!usesProducts(props.form) && activeTab.value === 'produtos') {
        activeTab.value = 'geral';
    }
});

onMounted(() => {
    activeTab.value = 'geral';
});

const setActiveTab = (tabId) => {
    activeTab.value = tabId;
};
</script>

<template>
    <div>
        <!-- Tabs -->
        <div class="border-b border-slate-200 bg-white">
            <nav class="flex gap-1 overflow-x-auto px-2 sm:px-6" aria-label="Tabs">
                <button
                    v-for="tab in visibleTabs"
                    :key="tab.id"
                    type="button"
                    :class="[
                        'flex min-w-0 items-center gap-2 border-b-2 px-3 py-4 text-sm font-medium transition',
                        activeTab === tab.id
                            ? 'border-emerald-600 bg-emerald-50/50 text-emerald-700'
                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'
                    ]"
                    @click="setActiveTab(tab.id)"
                >
                    <svg v-if="tab.icon === 'calendar'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <svg v-else-if="tab.icon === 'user'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <svg v-else-if="tab.icon === 'flask'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                    <svg v-else-if="tab.icon === 'euro'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 15.536c-1.171 1.952-3.07 1.952-4.242 0-1.172-1.953-1.172-5.119 0-7.072 1.171-1.952 3.07-1.952 4.242 0M8 10.5h4m-4 3h4m9-1.5a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="sm:hidden">{{ tab.shortLabel }}</span>
                    <span class="hidden sm:inline">{{ tab.label }}</span>
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <form class="p-6" @submit.prevent="emit('submit')">
            <!-- Geral Tab -->
            <div v-show="activeTab === 'geral'" class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <InputLabel value="Parcela" />
                    <select v-model="form.parcela_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Selecionar parcela</option>
                        <option v-for="parcela in parcelas" :key="parcela.id" :value="String(parcela.id)">
                            {{ parcela.nome }} - {{ parcela.terreno_nome }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.parcela_id" />
                </div>
                <div>
                    <InputLabel value="Tipo" />
                    <select v-model="form.tipo" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Selecionar tipo</option>
                        <option v-for="tipo in tipoOptions" :key="tipo" :value="tipo">{{ tipo }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.tipo" />
                </div>
                <div>
                    <InputLabel value="Estado" />
                    <select v-model="form.estado" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option v-for="estado in estadoOptions" :key="estado" :value="estado">{{ estadoLabel(estado) }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.estado" />
                </div>
                <div>
                    <InputLabel value="Cultura" />
                    <select v-model="form.cultura_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Sem cultura</option>
                        <option v-for="cultura in filteredCulturas" :key="cultura.id" :value="String(cultura.id)">{{ cultura.nome }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.cultura_id" />
                </div>
                <div>
                    <InputLabel value="Campanha" />
                    <select v-model="form.campanha_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Sem campanha</option>
                        <option v-for="campanha in filteredCampanhas" :key="campanha.id" :value="String(campanha.id)">{{ campanha.nome }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.campanha_id" />
                </div>
                <div>
                    <InputLabel value="Data e hora de início" />
                    <TextInput v-model="form.data_hora_inicio" type="datetime-local" class="mt-2 block w-full rounded-2xl" />
                    <InputError class="mt-2" :message="form.errors.data_hora_inicio" />
                </div>
                <div>
                    <InputLabel value="Data e hora de fim" />
                    <TextInput v-model="form.data_hora_fim" type="datetime-local" class="mt-2 block w-full rounded-2xl" />
                    <InputError class="mt-2" :message="form.errors.data_hora_fim" />
                </div>
                <div class="sm:col-span-2">
                    <InputLabel value="Observações" />
                    <textarea v-model="form.observacoes" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" rows="4" />
                    <InputError class="mt-2" :message="form.errors.observacoes" />
                </div>
            </div>

            <!-- Recursos Tab -->
            <div v-show="activeTab === 'recursos'" class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel value="Trabalhador" />
                    <select v-model="form.funcionario_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Sem trabalhador</option>
                        <option v-for="funcionario in funcionarios" :key="funcionario.id" :value="String(funcionario.id)">
                            {{ funcionario.nome }}{{ funcionario.cargo ? ` - ${funcionario.cargo}` : '' }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.funcionario_id" />
                </div>
                <div>
                    <InputLabel value="Equipa" />
                    <select v-model="form.equipa_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Sem equipa</option>
                        <option v-for="equipa in equipas" :key="equipa.id" :value="String(equipa.id)">{{ equipa.nome }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.equipa_id" />
                </div>
                <div>
                    <InputLabel value="Máquina" />
                    <select v-model="form.maquina_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Sem máquina</option>
                        <option v-for="maquina in maquinas" :key="maquina.id" :value="String(maquina.id)">{{ maquina.nome }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.maquina_id" />
                </div>
                <div>
                    <InputLabel value="Alfaia" />
                    <select v-model="form.alfaia_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Sem alfaia</option>
                        <option v-for="alfaia in alfaias" :key="alfaia.id" :value="String(alfaia.id)">{{ alfaia.nome }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.alfaia_id" />
                </div>
                <div>
                    <InputLabel value="Duração (h)" />
                    <TextInput v-model="form.duracao_horas" class="mt-2 block w-full rounded-2xl" />
                    <InputError class="mt-2" :message="form.errors.duracao_horas" />
                </div>
                <div>
                    <InputLabel value="Operador sistema" />
                    <select v-model="form.operador_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Sem operador</option>
                        <option v-for="operador in operadores" :key="operador.id" :value="String(operador.id)">{{ operador.nome }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.operador_id" />
                </div>
            </div>

            <!-- Produtos Tab -->
            <div v-show="activeTab === 'produtos'" class="space-y-4">
                <!-- DGAV Fields -->
                <div v-if="isTratamentoFitossanitario(form.tipo)" class="rounded-3xl border border-sky-100 bg-sky-50/60 p-4">
                    <h3 class="text-lg font-semibold text-sky-900 mb-4">Dados da aplicação (Caderno de Campo DGAV)</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Produtor" />
                            <TextInput v-model="form.produtor_nome" class="mt-2 block w-full rounded-2xl" />
                            <InputError class="mt-2" :message="form.errors.produtor_nome" />
                        </div>
                        <div>
                            <InputLabel value="Aplicador / entidade" />
                            <TextInput v-model="form.aplicador_nome" class="mt-2 block w-full rounded-2xl" />
                            <InputError class="mt-2" :message="form.errors.aplicador_nome" />
                        </div>
                        <div>
                            <InputLabel value="N.º aplicador / entidade" />
                            <TextInput v-model="form.aplicador_numero_autorizacao" class="mt-2 block w-full rounded-2xl" />
                            <InputError class="mt-2" :message="form.errors.aplicador_numero_autorizacao" />
                        </div>
                        <div>
                            <InputLabel value="Concelho" />
                            <TextInput v-model="form.exploracao_concelho" class="mt-2 block w-full rounded-2xl" />
                            <InputError class="mt-2" :message="form.errors.exploracao_concelho" />
                        </div>
                        <div>
                            <InputLabel value="Freguesia" />
                            <TextInput v-model="form.exploracao_freguesia" class="mt-2 block w-full rounded-2xl" />
                            <InputError class="mt-2" :message="form.errors.exploracao_freguesia" />
                        </div>
                    </div>
                </div>

                <!-- Products Section -->
                <div class="rounded-3xl border border-emerald-100 bg-emerald-50/60 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <InputLabel :value="productTitle(form)" />
                            <p class="mt-1 text-xs leading-5 text-slate-500">
                                {{ productEmptyText(form) }}
                                <span v-if="productRequired(form)" class="font-semibold text-red-700">Obrigatório.</span>
                            </p>
                        </div>
                        <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="addProductRow(form)">
                            Adicionar produto
                        </SecondaryButton>
                    </div>
                    <InputError class="mt-2" :message="form.errors.produtos" />
                    <div v-if="!productOptionsFor(form).length" class="mt-3 rounded-2xl border border-dashed border-emerald-200 bg-white/70 p-4 text-sm text-slate-600">
                        Não existem produtos deste tipo na lista.
                        <button type="button" class="font-semibold text-emerald-700 underline" @click="emit('openProductModal', isTratamentoFitossanitario(form.tipo) ? 'fitofarmaco' : normaliseText(form.tipo))">
                            Criar produto
                        </button>
                    </div>
                    <div class="mt-4 space-y-4">
                        <div v-for="(produto, index) in form.produtos" :key="`produto-${index}`" class="grid gap-3 rounded-2xl bg-white p-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <InputLabel value="Produto" />
                                <select v-model="produto.produto_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" @change="updateProductDefaults(form, index)">
                                    <option value="">Selecionar produto</option>
                                    <option v-for="item in productOptionsFor(form)" :key="item.id" :value="String(item.id)">{{ item.nome }} - {{ item.tipo }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.produto_id`]" />
                            </div>
                            <div>
                                <InputLabel value="Quantidade" />
                                <TextInput v-model="produto.quantidade" type="number" step="0.01" min="0.01" class="mt-2 block w-full rounded-2xl" />
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.quantidade`]" />
                            </div>
                            <div>
                                <InputLabel value="Unidade" />
                                <TextInput v-model="produto.unidade_medida" class="mt-2 block w-full rounded-2xl" />
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.unidade_medida`]" />
                            </div>
                            <div v-if="isTratamentoFitossanitario(form.tipo)">
                                <InputLabel value="Dose" />
                                <TextInput v-model="produto.dose" type="number" step="0.001" min="0" class="mt-2 block w-full rounded-2xl" />
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.dose`]" />
                            </div>
                            <div v-if="isTratamentoFitossanitario(form.tipo)">
                                <InputLabel value="Unidade da dose" />
                                <TextInput v-model="produto.dose_unidade" class="mt-2 block w-full rounded-2xl" placeholder="L/ha, kg/ha" />
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.dose_unidade`]" />
                            </div>
                            <div v-if="isTratamentoFitossanitario(form.tipo)">
                                <InputLabel value="Área tratada (ha)" />
                                <TextInput v-model="produto.area_tratada" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.area_tratada`]" />
                            </div>
                            <div v-if="isTratamentoFitossanitario(form.tipo)">
                                <InputLabel value="Volume de calda (L)" />
                                <TextInput v-model="produto.volume_calda" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.volume_calda`]" />
                            </div>
                            <div v-if="isTratamentoFitossanitario(form.tipo)">
                                <InputLabel value="Finalidade / inimigo" />
                                <TextInput v-model="produto.finalidade" class="mt-2 block w-full rounded-2xl" />
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.finalidade`]" />
                            </div>
                            <div v-if="isTratamentoFitossanitario(form.tipo)">
                                <InputLabel value="Intervalo de segurança (dias)" />
                                <TextInput v-model="produto.intervalo_seguranca_dias" type="number" min="0" class="mt-2 block w-full rounded-2xl" />
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.intervalo_seguranca_dias`]" />
                            </div>
                            <div v-if="isTratamentoFitossanitario(form.tipo)">
                                <InputLabel value="Estabelecimento de venda" />
                                <TextInput v-model="produto.estabelecimento_venda_nome" class="mt-2 block w-full rounded-2xl" />
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.estabelecimento_venda_nome`]" />
                            </div>
                            <div v-if="isTratamentoFitossanitario(form.tipo)">
                                <InputLabel value="N.º autorização do estabelecimento" />
                                <TextInput v-model="produto.estabelecimento_venda_autorizacao" class="mt-2 block w-full rounded-2xl" />
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.estabelecimento_venda_autorizacao`]" />
                            </div>
                            <div>
                                <InputLabel value="Custo unitário" />
                                <TextInput v-model="produto.custo_unitario" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.custo_unitario`]" />
                            </div>
                            <div class="flex items-end">
                                <DangerButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="removeProductRow(form, index)">Remover</DangerButton>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custos Tab -->
            <div v-show="activeTab === 'custos'" class="space-y-4">
                <div class="rounded-2xl bg-amber-50 p-4 text-sm text-slate-600">
                    Os custos de produtos são calculados automaticamente a partir das quantidades e preços unitários.
                    Aqui podes registar o custo total estimado e o custo real da operação (mão de obra, máquinas, etc.).
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Custo estimado (€)" />
                        <div class="relative mt-2">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">€</span>
                            <TextInput v-model="form.custo_estimado" type="number" step="0.01" min="0" class="pl-8 block w-full rounded-2xl" />
                        </div>
                        <InputError class="mt-2" :message="form.errors.custo_estimado" />
                    </div>
                    <div>
                        <InputLabel value="Custo real (€)" />
                        <div class="relative mt-2">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">€</span>
                            <TextInput v-model="form.custo_real" type="number" step="0.01" min="0" class="pl-8 block w-full rounded-2xl" />
                        </div>
                        <InputError class="mt-2" :message="form.errors.custo_real" />
                    </div>
                    <div v-if="totalCustoProdutos > 0" class="sm:col-span-2 rounded-2xl bg-emerald-50 p-4">
                        <p class="text-sm font-medium text-emerald-800">
                            Custo total de produtos: {{ formatCurrency(totalCustoProdutos) }} €
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="emit('cancel')">Cancelar</SecondaryButton>
                <PrimaryButton class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" :class="submitButtonClass" :disabled="form.processing">
                    {{ submitLabel }}
                </PrimaryButton>
            </div>
        </form>
    </div>
</template>
