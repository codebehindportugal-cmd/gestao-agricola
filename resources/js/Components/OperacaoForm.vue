<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
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
    exploracaoDados: { type: Object, default: () => ({}) },
    tipoOptions: { type: Array, default: () => [] },
    estadoOptions: { type: Array, default: () => [] },
    allowMultipleParcelas: { type: Boolean, default: false },
    submitLabel: { type: String, default: 'Guardar operação' },
    submitButtonClass: {
        type: String,
        default: 'bg-emerald-700 hover:bg-emerald-600 focus:bg-emerald-600',
    },
    operacaoId: { type: [Number, String], default: null },
    imagePath: { type: String, default: null },
});

const extracting = ref(false);
const extractError = ref(null);
const extractSuccess = ref(false);

const uploading = ref(false);
const uploadError = ref(null);
const currentImageUrl = ref(null);
const fileInputRef = ref(null);

const canUpload = computed(
    () => props.operacaoId && isTratamentoFitossanitario(props.form.tipo),
);

const uploadImagem = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    uploading.value = true;
    uploadError.value = null;

    const formData = new FormData();
    formData.append('image', file);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const response = await fetch(`/operacoes/${props.operacaoId}/upload-imagem`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken ?? '',
            },
            body: formData,
        });

        const json = await response.json();

        if (!response.ok) {
            uploadError.value = json.errors?.image?.[0] ?? json.error ?? 'Erro ao carregar imagem.';
            return;
        }

        currentImageUrl.value = json.image_url;
        emit('imageUploaded', json.image_path);
    } catch {
        uploadError.value = 'Erro de ligação ao servidor.';
    } finally {
        uploading.value = false;
        if (fileInputRef.value) fileInputRef.value.value = '';
    }
};

const canExtract = computed(
    () => props.operacaoId && props.imagePath && isTratamentoFitossanitario(props.form.tipo),
);

const extrairDadosImagem = async () => {
    extracting.value = true;
    extractError.value = null;
    extractSuccess.value = false;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const response = await fetch(`/operacoes/${props.operacaoId}/extrair-imagem`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken ?? '',
            },
        });

        const json = await response.json();

        if (!response.ok) {
            extractError.value = json.error ?? 'Erro ao extrair dados.';
            return;
        }

        const dados = json.dados ?? {};

        if (dados.aplicador_nome) props.form.aplicador_nome = dados.aplicador_nome;
        if (dados.aplicador_numero_autorizacao) props.form.aplicador_numero_autorizacao = dados.aplicador_numero_autorizacao;
        if (dados.data_aplicacao && !props.form.data_hora_inicio) {
            props.form.data_hora_inicio = dados.data_aplicacao + 'T08:00';
        }

        if (props.form.produtos?.length > 0) {
            const p = props.form.produtos[0];
            if (dados.dose != null) p.dose = String(dados.dose);
            if (dados.dose_unidade) p.dose_unidade = dados.dose_unidade;
            if (dados.area_tratada != null) p.area_tratada = String(dados.area_tratada);
            if (dados.volume_calda != null) p.volume_calda = String(dados.volume_calda);
            if (dados.finalidade) p.finalidade = dados.finalidade;
            if (dados.intervalo_seguranca_dias != null) p.intervalo_seguranca_dias = String(dados.intervalo_seguranca_dias);
            if (dados.estabelecimento_venda_nome) p.estabelecimento_venda_nome = dados.estabelecimento_venda_nome;
            if (dados.estabelecimento_venda_autorizacao) p.estabelecimento_venda_autorizacao = dados.estabelecimento_venda_autorizacao;
        }

        extractSuccess.value = true;
        setTimeout(() => { extractSuccess.value = false; }, 4000);
    } catch {
        extractError.value = 'Erro de ligação ao servidor.';
    } finally {
        extracting.value = false;
    }
};

const emit = defineEmits(['submit', 'cancel', 'openProductModal', 'imageUploaded']);

const activeTab = ref('geral');

const MAX_HOURS_PER_DAY = 8;
const vehicleTypes = ['carro', 'carrinha', 'camião', 'camiao', 'moto_4'];

const productTypeConfig = {
    'tratamento fitossanitario': {
        title: 'Produtos fitofarmacêuticos',
        empty: 'Adiciona pelo menos um produto fitofarmacêutico para este tratamento.',
        tipos: ['fitofarmaco', 'fitofarmacêutico', 'fitofarmaceutico', 'produto fitofarmaceutico'],
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

const normalizeText = (value) => String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

const productConfigFor = (tipo) => productTypeConfig[normalizeText(tipo)] ?? null;
const usesProducts = (form) => !!productConfigFor(form.tipo);
const isTratamentoFitossanitario = (tipo) => normalizeText(tipo) === 'tratamento fitossanitario';
const isFertilizacao = (tipo) => normalizeText(tipo) === 'fertilizacao';
const usesDoseAreaCalculation = (tipo) => isTratamentoFitossanitario(tipo) || isFertilizacao(tipo);
const isColheita = (tipo) => normalizeText(tipo) === 'colheita';
const productTitle = (form) => productConfigFor(form.tipo)?.title ?? 'Produtos';
const productEmptyText = (form) => productConfigFor(form.tipo)?.empty ?? 'Adiciona os produtos usados nesta operação.';
const productRequired = (form) => productConfigFor(form.tipo)?.required ?? false;

const parseDecimal = (value) => {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    return Number(String(value).replace(',', '.')) || 0;
};

const doseIsPerHectare = (unit) => normalizeText(unit).includes('/ha');

const calculatedProductQuantity = (produto) => {
    const dose = parseDecimal(produto.dose);
    const area = parseDecimal(produto.area_tratada);

    if (!dose || !area || !doseIsPerHectare(produto.dose_unidade)) {
        return null;
    }

    return Number((dose * area).toFixed(3)).toString();
};

const syncCalculatedProductQuantities = (form) => {
    if (!usesDoseAreaCalculation(form.tipo)) {
        return;
    }

    (form.produtos ?? []).forEach((produto) => {
        const quantidade = calculatedProductQuantity(produto);

        if (quantidade !== null && produto.quantidade !== quantidade) {
            produto.quantidade = quantidade;
        }
    });
};

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

    const allowedTypes = config.tipos.map(normalizeText);
    return props.produtos.filter((produto) => allowedTypes.includes(normalizeText(produto.tipo)));
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

    row.unidade_medida = produto.unidade_medida || 'kg';
    row.custo_unitario = produto.custo_unitario?.toString() || '';
    row.estabelecimento_venda_nome = produto.estabelecimento_venda_nome || '';
    row.estabelecimento_venda_autorizacao = produto.estabelecimento_venda_autorizacao || '';

    if (usesDoseAreaCalculation(form.tipo) && !row.area_tratada && selectedParcelaArea.value) {
        row.area_tratada = selectedParcelaArea.value.toString();
    }

    syncCalculatedProductQuantities(form);
};

const selectedProduct = (produtoId) => props.produtos.find((item) => String(item.id) === String(produtoId)) ?? null;
const selectedMachine = computed(() => props.maquinas.find((item) => String(item.id) === String(props.form.maquina_id)) ?? null);
const selectedMachineIsVehicle = computed(() => vehicleTypes.includes(normalizeText(selectedMachine.value?.tipo)));
const selectedFuncionario = computed(() => props.funcionarios.find((item) => String(item.id) === String(props.form.funcionario_id)) ?? null);
const selectedParcela = computed(() => {
    const parcelaId = props.allowMultipleParcelas
        ? (props.form.parcela_ids ?? []).filter(Boolean)[0]
        : props.form.parcela_id;

    return props.parcelas.find((item) => String(item.id) === String(parcelaId)) ?? null;
});
const selectedParcelaCulturas = computed(() => {
    const parcelaId = String(selectedParcela.value?.id ?? '');

    if (!parcelaId) {
        return [];
    }

    return props.culturas.filter((cultura) => String(cultura.parcela_id) === parcelaId);
});
const selectedParcelaArea = computed(() => {
    if (props.allowMultipleParcelas) {
        const selectedIds = (props.form.parcela_ids ?? []).filter(Boolean).map(String);
        const totalArea = props.parcelas
            .filter((parcela) => selectedIds.includes(String(parcela.id)))
            .reduce((total, parcela) => total + (parseDecimal(parcela.area_util) || parseDecimal(parcela.area_total) || 0), 0);

        return totalArea || '';
    }

    return selectedParcela.value?.area_util ?? selectedParcela.value?.area_total ?? '';
});

const syncCampanhaFromCultura = (form) => {
    const culturaId = String(form.cultura_id || '');
    const campanhas = props.campanhas.filter((campanha) => String(campanha.cultura_id) === culturaId);

    if (!campanhas.length) {
        form.campanha_id = '';
        return;
    }

    const currentCampanha = campanhas.find((campanha) => String(campanha.id) === String(form.campanha_id));
    form.campanha_id = String((currentCampanha ?? campanhas[0]).id);
};

const culturaLabel = (cultura) => cultura?.label
    ?? [cultura?.nome, cultura?.variedade].filter(Boolean).join(' - ')
    ?? '';

const syncContextFromParcela = (form) => {
    const selectedParcelas = props.allowMultipleParcelas
        ? (form.parcela_ids ?? []).filter(Boolean)
        : [form.parcela_id].filter(Boolean);

    if (selectedParcelas.length !== 1) {
        form.parcela_id = selectedParcelas[0] ?? '';
        if (!props.allowMultipleParcelas) {
            form.cultura_id = '';
        }
        form.campanha_id = '';
        return;
    }

    const parcelaId = String(selectedParcelas[0] || '');
    form.parcela_id = parcelaId;
    const culturas = props.culturas.filter((cultura) => String(cultura.parcela_id) === parcelaId);

    if (!culturas.length) {
        form.cultura_id = '';
        form.campanha_id = '';
        return;
    }

    const currentCultura = culturas.find((cultura) => String(cultura.id) === String(form.cultura_id));
    form.cultura_id = String((currentCultura ?? (culturas.length === 1 ? culturas[0] : null))?.id ?? '');
    syncCampanhaFromCultura(form);
};

const totalCustoProdutos = computed(() => props.form.produtos?.reduce((total, produto) => {
    const quantidade = parseFloat(produto.quantidade) || 0;
    const custoUnitario = parseFloat(produto.custo_unitario) || 0;
    return total + (quantidade * custoUnitario);
}, 0) || 0);

const calculatedDuration = computed(() => {
    if (!props.form.data_hora_inicio || !props.form.data_hora_fim) {
        return '';
    }

    const start = new Date(props.form.data_hora_inicio);
    const end = new Date(props.form.data_hora_fim);

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end <= start) {
        return '';
    }

    let cursor = new Date(start);
    let total = 0;

    while (cursor < end) {
        const dayEnd = new Date(cursor);
        dayEnd.setHours(23, 59, 59, 999);
        const segmentEnd = end < dayEnd ? end : dayEnd;
        const hours = (segmentEnd.getTime() - cursor.getTime()) / 36e5;

        total += Math.min(hours, MAX_HOURS_PER_DAY);
        cursor = new Date(segmentEnd.getTime() + 1);
    }

    return Number(total.toFixed(2)).toString();
});

const calculatedFuelUsage = computed(() => {
    const consumo = parseFloat(selectedMachine.value?.consumo_combustivel) || 0;

    if (!consumo) {
        return '';
    }

    if (selectedMachineIsVehicle.value) {
        const distancia = parseFloat(props.form.distancia_km) || 0;

        return distancia > 0 ? Number(((distancia * consumo) / 100).toFixed(2)).toString() : '';
    }

    const horas = parseFloat(props.form.duracao_horas) || 0;

    return horas > 0 ? Number((horas * consumo).toFixed(2)).toString() : '';
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
const lastAutomaticDuration = ref('');

const applyCalculatedDuration = () => {
    props.form.duracao_horas = calculatedDuration.value;
    lastAutomaticDuration.value = calculatedDuration.value;
};

watch(() => props.form.tipo, () => {
    ensureProductRows(props.form);

    if (isColheita(props.form.tipo) && !props.form.colheita_qualidade) {
        props.form.colheita_qualidade = 'comercial';
    }

    if (!usesProducts(props.form) && activeTab.value === 'produtos') {
        activeTab.value = 'geral';
    }

    syncCalculatedProductQuantities(props.form);
});

watch(() => props.form.parcela_id, () => {
    syncContextFromParcela(props.form);
});

watch(() => props.form.parcela_ids, () => {
    syncContextFromParcela(props.form);
}, { deep: true });

watch(calculatedDuration, (duration, previousDuration) => {
    if (
        !props.form.duracao_horas
        || props.form.duracao_horas === previousDuration
        || props.form.duracao_horas === lastAutomaticDuration.value
    ) {
        props.form.duracao_horas = duration;
        lastAutomaticDuration.value = duration;
    }
}, { immediate: true });

watch(calculatedFuelUsage, (fuelUsage) => {
    props.form.combustivel_gasto_l = fuelUsage;
}, { immediate: true });

watch(() => props.form.cultura_id, () => {
    syncCampanhaFromCultura(props.form);
});

watch(selectedFuncionario, (funcionario) => {
    if (!funcionario) {
        return;
    }

    props.form.aplicador_nome = funcionario.nome ?? '';
    props.form.aplicador_numero_autorizacao = funcionario.aplicador_numero_autorizacao ?? '';
}, { immediate: true });

watch([selectedParcelaArea, () => props.form.tipo], ([area]) => {
    if (!area || !usesDoseAreaCalculation(props.form.tipo)) {
        return;
    }

    props.form.produtos = (props.form.produtos ?? []).map((produto) => ({
        ...produto,
        area_tratada: produto.area_tratada || area.toString(),
    }));

    syncCalculatedProductQuantities(props.form);
}, { immediate: true });

watch(() => props.form.produtos, () => {
    syncCalculatedProductQuantities(props.form);
}, { deep: true });

watch(() => props.imagePath, (path) => {
    if (path && !currentImageUrl.value) {
        currentImageUrl.value = `/storage/${path}`;
    }
}, { immediate: true });

onMounted(() => {
    activeTab.value = 'geral';
    syncContextFromParcela(props.form);
    ensureProductRows(props.form);
    props.form.produtor_nome = props.form.produtor_nome || props.exploracaoDados.produtor_nome || '';
    props.form.exploracao_concelho = props.form.exploracao_concelho || props.exploracaoDados.concelho || '';
    props.form.exploracao_freguesia = props.form.exploracao_freguesia || props.exploracaoDados.freguesia || '';
});

const setActiveTab = (tabId) => {
    activeTab.value = tabId;
};
</script>

<template>
    <div>
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
                            : 'border-transparent text-slate-500 hover:text-slate-700'
                    ]"
                    @click="setActiveTab(tab.id)"
                >
                    <svg v-if="tab.icon === 'calendar'" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <svg v-else-if="tab.icon === 'user'" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <svg v-else-if="tab.icon === 'flask'" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 3h6m-5 0v6.172a2 2 0 01-.586 1.414l-4.95 4.95A2 2 0 005.879 19h12.242a2 2 0 001.415-3.414l-4.95-4.95A2 2 0 0114 9.172V3" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 14c1.333.667 2.667 1 4 1s2.667-.333 4-1" />
                    </svg>
                    <svg v-else-if="tab.icon === 'euro'" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.5 7.5a4.5 4.5 0 10.001 9M7 10h7M7 14h7" />
                    </svg>
                    <span class="sm:hidden">{{ tab.shortLabel }}</span>
                    <span class="hidden sm:inline">{{ tab.label }}</span>
                </button>
            </nav>
        </div>

        <form class="p-6" @submit.prevent="emit('submit')">
            <div v-show="activeTab === 'geral'" class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <InputLabel :value="allowMultipleParcelas ? 'Parcelas' : 'Parcela'" />
                    <select
                        v-if="allowMultipleParcelas"
                        v-model="form.parcela_ids"
                        multiple
                        size="6"
                        class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                    >
                        <option v-for="parcela in parcelas" :key="parcela.id" :value="String(parcela.id)">
                            {{ parcela.nome }} - {{ parcela.terreno_nome }}
                        </option>
                    </select>
                    <select v-else v-model="form.parcela_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Selecionar parcela</option>
                        <option v-for="parcela in parcelas" :key="parcela.id" :value="String(parcela.id)">
                            {{ parcela.nome }} - {{ parcela.terreno_nome }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.parcela_id" />
                    <InputError class="mt-2" :message="form.errors.parcela_ids" />
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

                <div v-if="selectedParcelaCulturas.length" class="sm:col-span-2">
                    <InputLabel :value="selectedParcelaCulturas.length > 1 ? 'Cultura / variedade' : 'Cultura'" />
                    <select v-model="form.cultura_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Selecionar cultura</option>
                        <option v-for="cultura in selectedParcelaCulturas" :key="cultura.id" :value="String(cultura.id)">
                            {{ culturaLabel(cultura) }}
                        </option>
                    </select>
                    <p v-if="selectedParcelaCulturas.length > 1" class="mt-2 text-xs leading-5 text-slate-500">
                        Esta parcela tem várias culturas/variedades. Escolhe exatamente a que estás a trabalhar.
                    </p>
                    <InputError class="mt-2" :message="form.errors.cultura_id" />
                </div>
                <div v-else-if="selectedParcela && isColheita(form.tipo)" class="sm:col-span-2 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Esta parcela ainda não tem uma cultura/variedade registada. Cria primeiro a cultura para conseguires guardar a colheita.
                    <InputError class="mt-2" :message="form.errors.cultura_id" />
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

                <div v-if="isColheita(form.tipo)" class="sm:col-span-2 rounded-3xl border border-emerald-100 bg-emerald-50/70 p-4">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel value="Kg apanhados" />
                            <TextInput v-model="form.colheita_quantidade_total" type="number" step="0.01" min="0.01" class="mt-2 block w-full rounded-2xl bg-white" />
                            <InputError class="mt-2" :message="form.errors.colheita_quantidade_total" />
                        </div>

                        <div>
                            <InputLabel value="Perdas (kg)" />
                            <TextInput v-model="form.colheita_quantidade_perdas" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl bg-white" />
                            <InputError class="mt-2" :message="form.errors.colheita_quantidade_perdas" />
                        </div>

                        <div>
                            <InputLabel value="Qualidade" />
                            <select v-model="form.colheita_qualidade" class="mt-2 block w-full rounded-2xl border-slate-200 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="premium">Premium</option>
                                <option value="superior">Superior</option>
                                <option value="comercial">Comercial</option>
                                <option value="segunda">Segunda</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.colheita_qualidade" />
                        </div>
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <InputLabel value="Observações" />
                    <textarea v-model="form.observacoes" rows="4" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                    <InputError class="mt-2" :message="form.errors.observacoes" />
                </div>
            </div>

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
                    <div class="mt-2 flex gap-2">
                        <TextInput v-model="form.duracao_horas" type="number" step="0.01" min="0" class="block w-full rounded-2xl" placeholder="Horas reais da parcela" />
                        <SecondaryButton
                            v-if="calculatedDuration"
                            type="button"
                            class="shrink-0 rounded-full px-4 py-2 text-xs normal-case tracking-normal"
                            @click="applyCalculatedDuration"
                        >
                            Usar cálculo
                        </SecondaryButton>
                    </div>
                    <p v-if="calculatedDuration" class="mt-2 text-xs text-slate-500">
                        Calculado pelas datas: {{ calculatedDuration }} h. Podes ajustar para o tempo real desta parcela.
                    </p>
                    <InputError class="mt-2" :message="form.errors.duracao_horas" />
                </div>

                <div v-if="selectedMachineIsVehicle">
                    <InputLabel value="Distância (km)" />
                    <TextInput v-model="form.distancia_km" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                    <InputError class="mt-2" :message="form.errors.distancia_km" />
                </div>

                <div v-if="selectedMachine?.consumo_combustivel">
                    <InputLabel value="Combustível gasto (L)" />
                    <TextInput v-model="form.combustivel_gasto_l" readonly class="mt-2 block w-full rounded-2xl bg-amber-50 text-slate-700" />
                    <p class="mt-2 text-xs text-slate-500">
                        {{ selectedMachineIsVehicle ? `${selectedMachine.consumo_combustivel} L/100 km` : `${selectedMachine.consumo_combustivel} L/h` }}
                    </p>
                    <InputError class="mt-2" :message="form.errors.combustivel_gasto_l" />
                </div>

                <div>
                    <InputLabel value="Operador sistema" />
                    <select v-model="form.operador_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Sem operador</option>
                        <option v-for="operador in operadores" :key="operador.id" :value="String(operador.id)">
                            {{ operador.name ?? operador.nome }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.operador_id" />
                </div>
            </div>

            <div v-show="activeTab === 'produtos'" class="space-y-4">
                <div v-if="isTratamentoFitossanitario(form.tipo)" class="rounded-3xl border border-sky-100 bg-sky-50 p-4">
                    <h3 class="mb-4 text-lg font-semibold text-sky-900">Dados da aplicação (Caderno de Campo DGAV)</h3>
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
                            <InputLabel value="N.º autorização do aplicador" />
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

                <div v-if="canUpload" class="rounded-3xl border border-slate-200 bg-slate-50/60 p-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-slate-800">Imagem da ficha de aplicação</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">
                                Fotografia da ficha DGAV para extração automática de dados com IA.
                                Formatos aceites: JPEG, PNG, WebP, HEIC · Máx. 15 MB.
                            </p>
                            <label class="mt-3 flex cursor-pointer items-center gap-2 text-sm font-medium text-emerald-700 hover:text-emerald-600">
                                <input
                                    ref="fileInputRef"
                                    type="file"
                                    accept="image/jpeg,image/jpg,image/png,image/webp,image/heic"
                                    class="sr-only"
                                    :disabled="uploading"
                                    @change="uploadImagem"
                                >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ uploading ? 'A carregar…' : (currentImageUrl ? 'Substituir imagem' : 'Carregar imagem') }}
                            </label>
                            <p v-if="uploadError" class="mt-2 text-xs font-medium text-red-700">{{ uploadError }}</p>
                        </div>

                        <div v-if="currentImageUrl" class="shrink-0">
                            <a :href="currentImageUrl" target="_blank" rel="noopener">
                                <img
                                    :src="currentImageUrl"
                                    alt="Ficha de aplicação"
                                    class="h-24 w-24 rounded-2xl object-cover shadow-sm ring-1 ring-slate-200 transition hover:opacity-90"
                                >
                            </a>
                        </div>
                    </div>
                </div>

                <div v-if="canExtract" class="rounded-3xl border border-violet-100 bg-violet-50/60 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-violet-900">Extração automática de imagem</p>
                            <p class="mt-1 text-xs leading-5 text-violet-700">
                                Analisa a imagem da ficha de aplicação e preenche automaticamente os campos com IA.
                            </p>
                        </div>
                        <button
                            type="button"
                            :disabled="extracting"
                            class="flex shrink-0 items-center gap-2 rounded-full bg-violet-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-violet-600 disabled:opacity-60"
                            @click="extrairDadosImagem"
                        >
                            <svg v-if="!extracting" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                            <svg v-else class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ extracting ? 'A analisar imagem…' : 'Extrair dados da imagem' }}
                        </button>
                    </div>
                    <p v-if="extractSuccess" class="mt-3 rounded-2xl bg-emerald-100 px-4 py-2 text-sm font-medium text-emerald-800">
                        Dados extraídos com sucesso. Verifica os campos preenchidos antes de guardar.
                    </p>
                    <p v-if="extractError" class="mt-3 rounded-2xl bg-red-100 px-4 py-2 text-sm font-medium text-red-800">
                        {{ extractError }}
                    </p>
                </div>

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

                    <div v-if="allowMultipleParcelas" class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                        Ao selecionar várias parcelas, indica a quantidade total usada na tarefa. O sistema reparte essa quantidade pelas parcelas pela área de cada uma, evitando duplicar litros ou kg no stock e nos custos.
                    </div>

                    <div v-if="!productOptionsFor(form).length" class="mt-3 rounded-2xl border border-dashed border-emerald-200 bg-white/70 p-4 text-sm text-slate-600">
                        Não existem produtos deste tipo na lista.
                        <button
                            type="button"
                            class="font-semibold text-emerald-700 underline"
                            @click="emit('openProductModal', isTratamentoFitossanitario(form.tipo) ? 'fitofarmaco' : normalizeText(form.tipo))"
                        >
                            Criar produto
                        </button>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div v-for="(produto, index) in form.produtos" :key="`produto-${index}`" class="grid gap-3 rounded-2xl bg-white p-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <InputLabel value="Produto" />
                                <select
                                    v-model="produto.produto_id"
                                    class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                    @change="updateProductDefaults(form, index)"
                                >
                                    <option value="">Selecionar produto</option>
                                    <option v-for="item in productOptionsFor(form)" :key="item.id" :value="String(item.id)">
                                        {{ item.nome }} - {{ item.tipo }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.produto_id`]" />
                            </div>

                            <div v-if="isTratamentoFitossanitario(form.tipo) && selectedProduct(produto.produto_id)" class="sm:col-span-2 rounded-2xl bg-slate-50 p-4">
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">N.º DGAV</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-800">
                                            {{ selectedProduct(produto.produto_id)?.numero_autorizacao_dgav || 'Sem registo' }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Estabelecimento</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-800">
                                            {{ selectedProduct(produto.produto_id)?.estabelecimento_venda_nome || 'Sem registo' }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Autorização</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-800">
                                            {{ selectedProduct(produto.produto_id)?.estabelecimento_venda_autorizacao || 'Sem registo' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <InputLabel value="Quantidade" />
                                <TextInput
                                    v-model="produto.quantidade"
                                    type="number"
                                    step="0.001"
                                    min="0.001"
                                    :readonly="usesDoseAreaCalculation(form.tipo) && calculatedProductQuantity(produto) !== null"
                                    class="mt-2 block w-full rounded-2xl"
                                    :class="{ 'bg-slate-50 text-slate-600': usesDoseAreaCalculation(form.tipo) && calculatedProductQuantity(produto) !== null }"
                                />
                                <p v-if="usesDoseAreaCalculation(form.tipo) && calculatedProductQuantity(produto) !== null" class="mt-1 text-xs text-slate-500">
                                    Calculado: dose × área tratada.
                                </p>
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.quantidade`]" />
                            </div>

                            <div>
                                <InputLabel value="Unidade" />
                                <TextInput v-model="produto.unidade_medida" readonly class="mt-2 block w-full rounded-2xl bg-slate-50 text-slate-600" />
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.unidade_medida`]" />
                            </div>

                            <div v-if="usesDoseAreaCalculation(form.tipo)">
                                <InputLabel value="Dose" />
                                <TextInput v-model="produto.dose" type="number" step="0.001" min="0" class="mt-2 block w-full rounded-2xl" />
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.dose`]" />
                            </div>

                            <div v-if="usesDoseAreaCalculation(form.tipo)">
                                <InputLabel value="Unidade da dose" />
                                <TextInput v-model="produto.dose_unidade" class="mt-2 block w-full rounded-2xl" placeholder="L/ha, kg/ha" />
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.dose_unidade`]" />
                            </div>

                            <div v-if="usesDoseAreaCalculation(form.tipo)">
                                <InputLabel value="Área tratada (ha)" />
                                <TextInput v-model="produto.area_tratada" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                                <InputError class="mt-2" :message="form.errors[`produtos.${index}.area_tratada`]" />
                            </div>

                            <div v-if="isTratamentoFitossanitario(form.tipo)">
                                <InputLabel value="Volume de calda (L/ha)" />
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

                            <div class="rounded-2xl bg-emerald-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Preço automático</p>
                                <p class="mt-2 text-sm font-semibold text-emerald-900">
                                    {{ produto.custo_unitario ? `€ ${formatCurrency(parseFloat(produto.custo_unitario) || 0)}` : 'Sem preço definido no produto' }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    O custo vem do produto e o total é calculado automaticamente.
                                </p>
                            </div>

                            <div class="flex items-end">
                                <DangerButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="removeProductRow(form, index)">
                                    Remover
                                </DangerButton>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                            <TextInput v-model="form.custo_estimado" type="number" step="0.01" min="0" class="block w-full rounded-2xl pl-8" />
                        </div>
                        <InputError class="mt-2" :message="form.errors.custo_estimado" />
                    </div>

                    <div>
                        <InputLabel value="Custo real (€)" />
                        <div class="relative mt-2">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">€</span>
                            <TextInput v-model="form.custo_real" type="number" step="0.01" min="0" class="block w-full rounded-2xl pl-8" />
                        </div>
                        <InputError class="mt-2" :message="form.errors.custo_real" />
                    </div>

                    <div v-if="form.produtos?.length" class="sm:col-span-2 rounded-2xl bg-emerald-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Calculado automaticamente</p>
                        <p class="mt-2 text-sm font-medium text-emerald-900">
                            Custo total de produtos: € {{ formatCurrency(totalCustoProdutos) }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="emit('cancel')">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" :class="submitButtonClass" :disabled="form.processing">
                    {{ submitLabel }}
                </PrimaryButton>
            </div>
        </form>
    </div>
</template>
