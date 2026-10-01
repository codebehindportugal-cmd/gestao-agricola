<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    maquinas: { type: Object, required: true },
    alfaias: { type: Object, required: true },
    revisoes: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, required: true },
    can: { type: Object, required: true },
    maquinaTipoOptions: { type: Array, default: () => [] },
    alfaiaTipoOptions: { type: Array, default: () => [] },
    maquinaEstadoOptions: { type: Array, default: () => [] },
    alfaiaEstadoOptions: { type: Array, default: () => [] },
    revisaoTipoOptions: { type: Array, default: () => [] },
    maquinaOptions: { type: Array, default: () => [] },
    alfaiaOptions: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const filterState = reactive({
    search: props.filters.search ?? '',
    tipo: props.filters.tipo ?? '',
    estado: props.filters.estado ?? '',
    alfaia_estado: props.filters.alfaia_estado ?? '',
    maquina_id: props.filters.maquina_id ?? '',
    revisao_tipo: props.filters.revisao_tipo ?? '',
    revisao_maquina_id: props.filters.revisao_maquina_id ?? '',
    revisao_alfaia_id: props.filters.revisao_alfaia_id ?? '',
});

const maquinaModalOpen = ref(false);
const alfaiaModalOpen = ref(false);
const revisaoModalOpen = ref(false);
const editingMaquina = ref(null);
const editingAlfaia = ref(null);
const editingRevisao = ref(null);

const maquinaBase = {
    nome: '',
    tipo: 'trator',
    marca: '',
    modelo: '',
    matricula: '',
    numero_serie: '',
    ano_aquisicao: '',
    horas_uso: '',
    horas_manutencao: '',
    consumo_agua_ha: '',
    consumo_combustivel: '',
    custo_hora: '',
    custo_km: '',
    estado: 'operacional',
    observacoes: '',
};

const alfaiaBase = {
    nome: '',
    tipo: 'charrua',
    maquina_id: '',
    descricao: '',
    comprimento: '',
    largura: '',
    consumo_agua_ha: '',
    custo_hora: '',
    estado: 'operacional',
    observacoes: '',
};

const revisaoBase = {
    maquina_id: '',
    alfaia_id: '',
    data_manutencao: '',
    tipo: 'revisão',
    descricao: '',
    custo: '',
    duracao_minutos: '',
    proxima_manutencao: '',
    observacoes: '',
};

const maquinaForm = useForm({ ...maquinaBase });
const alfaiaForm = useForm({ ...alfaiaBase });
const revisaoForm = useForm({ ...revisaoBase });
const maquinaErrors = computed(() => Object.values(maquinaForm.errors));
const alfaiaErrors = computed(() => Object.values(alfaiaForm.errors));
const revisaoErrors = computed(() => Object.values(revisaoForm.errors));

const currentQuery = computed(() => ({
    search: filterState.search || undefined,
    tipo: filterState.tipo || undefined,
    estado: filterState.estado || undefined,
    alfaia_estado: filterState.alfaia_estado || undefined,
    maquina_id: filterState.maquina_id || undefined,
    revisao_tipo: filterState.revisao_tipo || undefined,
    revisao_maquina_id: filterState.revisao_maquina_id || undefined,
    revisao_alfaia_id: filterState.revisao_alfaia_id || undefined,
}));

watch(
    () => [
        filterState.search,
        filterState.tipo,
        filterState.estado,
        filterState.alfaia_estado,
        filterState.maquina_id,
        filterState.revisao_tipo,
        filterState.revisao_maquina_id,
        filterState.revisao_alfaia_id,
    ],
    () => {
        router.get(route('app.maquinaria.index'), currentQuery.value, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    },
);

const labels = {
    em_manutencao: 'em manutenção',
    moto_4: 'moto 4',
    camiao: 'camião',
};

const labelize = (value) => labels[value] ?? String(value ?? '').replaceAll('_', ' ');
const isPulverizador = (tipo) => String(tipo ?? '').toLowerCase() === 'pulverizador';
const isVeiculo = (tipo) => ['carro', 'carrinha', 'camião', 'camiao', 'moto_4'].includes(String(tipo ?? '').toLowerCase());
const usesFuelConsumption = (tipo) => ['trator', 'ceifeira', 'carregador', 'carro', 'carrinha', 'camião', 'camiao', 'moto_4'].includes(String(tipo ?? '').toLowerCase());
const fuelConsumptionLabel = computed(() => (isVeiculo(maquinaForm.tipo) ? 'Consumo de combustível (L/100 km)' : 'Consumo de combustível (L/h)'));
const usageLabel = (tipo) => (isVeiculo(tipo) ? 'Quilómetros' : 'Horas');
const usageUnit = (tipo) => (isVeiculo(tipo) ? 'km' : 'h');
const maintenanceLabel = computed(() => (isVeiculo(maquinaForm.tipo) ? 'Próxima manutenção (km)' : 'Próxima manutenção (h)'));

const formatNumber = (value) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return new Intl.NumberFormat('pt-PT', { maximumFractionDigits: 2 }).format(Number(value));
};

const formatCurrency = (value) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(Number(value));
};

const formatDate = (value) => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('pt-PT').format(new Date(`${value}T00:00:00`));
};

const formatDuration = (minutes) => {
    if (!minutes) {
        return '-';
    }

    const hours = Math.floor(Number(minutes) / 60);
    const remainingMinutes = Number(minutes) % 60;

    if (!hours) {
        return `${remainingMinutes} min`;
    }

    return remainingMinutes ? `${hours} h ${remainingMinutes} min` : `${hours} h`;
};

const maquinaStatusClass = (estado) => ({
    operacional: 'bg-emerald-50 text-emerald-700',
    em_manutencao: 'bg-amber-50 text-amber-700',
    danificada: 'bg-red-50 text-red-700',
    retirada: 'bg-slate-100 text-slate-600',
}[estado] ?? 'bg-slate-100 text-slate-600');

const alfaiaStatusClass = (estado) => ({
    operacional: 'bg-emerald-50 text-emerald-700',
    danificada: 'bg-red-50 text-red-700',
    retirada: 'bg-slate-100 text-slate-600',
}[estado] ?? 'bg-slate-100 text-slate-600');

const normalizeMaquina = (form) => form.transform((data) => ({
    ...data,
    marca: data.marca || null,
    modelo: data.modelo || null,
    matricula: data.matricula || null,
    numero_serie: data.numero_serie || null,
    ano_aquisicao: data.ano_aquisicao || null,
    horas_uso: data.horas_uso || null,
    horas_manutencao: data.horas_manutencao || null,
    consumo_agua_ha: data.consumo_agua_ha || null,
    consumo_combustivel: data.consumo_combustivel || null,
    custo_hora: data.custo_hora || null,
    custo_km: data.custo_km || null,
    observacoes: data.observacoes || null,
}));

const normalizeAlfaia = (form) => form.transform((data) => ({
    ...data,
    maquina_id: data.maquina_id || null,
    descricao: data.descricao || null,
    comprimento: data.comprimento || null,
    largura: data.largura || null,
    consumo_agua_ha: data.consumo_agua_ha || null,
    custo_hora: data.custo_hora || null,
    observacoes: data.observacoes || null,
}));

const normalizeRevisao = (form) => form.transform((data) => ({
    ...data,
    maquina_id: data.maquina_id || null,
    alfaia_id: data.alfaia_id || null,
    custo: data.custo || null,
    duracao_minutos: data.duracao_minutos || null,
    proxima_manutencao: data.proxima_manutencao || null,
    observacoes: data.observacoes || null,
}));

const openCreateMaquina = () => {
    editingMaquina.value = null;
    maquinaForm.defaults({ ...maquinaBase });
    maquinaForm.reset();
    maquinaForm.clearErrors();
    maquinaModalOpen.value = true;
};

const openEditMaquina = (maquina) => {
    editingMaquina.value = maquina;
    maquinaForm.defaults({
        nome: maquina.nome ?? '',
        tipo: maquina.tipo ?? 'trator',
        marca: maquina.marca ?? '',
        modelo: maquina.modelo ?? '',
        matricula: maquina.matricula ?? '',
        numero_serie: maquina.numero_serie ?? '',
        ano_aquisicao: maquina.ano_aquisicao?.toString() ?? '',
        horas_uso: maquina.horas_uso?.toString() ?? '',
        horas_manutencao: maquina.horas_manutencao?.toString() ?? '',
        consumo_agua_ha: maquina.consumo_agua_ha?.toString() ?? '',
        consumo_combustivel: maquina.consumo_combustivel?.toString() ?? '',
        custo_hora: maquina.custo_hora?.toString() ?? '',
        custo_km: maquina.custo_km?.toString() ?? '',
        estado: maquina.estado ?? 'operacional',
        observacoes: maquina.observacoes ?? '',
    });
    maquinaForm.reset();
    maquinaForm.clearErrors();
    maquinaModalOpen.value = true;
};

const closeMaquinaModal = () => {
    maquinaModalOpen.value = false;
    editingMaquina.value = null;
    maquinaForm.clearErrors();
};

const submitMaquina = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeMaquinaModal(),
        onFinish: () => maquinaForm.transform((data) => data),
    };

    if (editingMaquina.value) {
        normalizeMaquina(maquinaForm).patch(route('app.maquinas.update', { maquina: editingMaquina.value.id, ...currentQuery.value }), options);
        return;
    }

    normalizeMaquina(maquinaForm).post(route('app.maquinas.store', currentQuery.value), options);
};

const deleteMaquina = (maquina) => {
    if (!window.confirm(`Remover a máquina "${maquina.nome}"?`)) {
        return;
    }

    router.delete(route('app.maquinas.destroy', { maquina: maquina.id, ...currentQuery.value }), {
        preserveScroll: true,
    });
};

const openCreateAlfaia = () => {
    editingAlfaia.value = null;
    alfaiaForm.defaults({ ...alfaiaBase, maquina_id: filterState.maquina_id || '' });
    alfaiaForm.reset();
    alfaiaForm.clearErrors();
    alfaiaModalOpen.value = true;
};

const openEditAlfaia = (alfaia) => {
    editingAlfaia.value = alfaia;
    alfaiaForm.defaults({
        nome: alfaia.nome ?? '',
        tipo: alfaia.tipo ?? 'charrua',
        maquina_id: alfaia.maquina_id?.toString() ?? '',
        descricao: alfaia.descricao ?? '',
        comprimento: alfaia.comprimento?.toString() ?? '',
        largura: alfaia.largura?.toString() ?? '',
        consumo_agua_ha: alfaia.consumo_agua_ha?.toString() ?? '',
        custo_hora: alfaia.custo_hora?.toString() ?? '',
        estado: alfaia.estado ?? 'operacional',
        observacoes: alfaia.observacoes ?? '',
    });
    alfaiaForm.reset();
    alfaiaForm.clearErrors();
    alfaiaModalOpen.value = true;
};

const closeAlfaiaModal = () => {
    alfaiaModalOpen.value = false;
    editingAlfaia.value = null;
    alfaiaForm.clearErrors();
};

const submitAlfaia = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeAlfaiaModal(),
        onFinish: () => alfaiaForm.transform((data) => data),
    };

    if (editingAlfaia.value) {
        normalizeAlfaia(alfaiaForm).patch(route('app.alfaias.update', { alfaia: editingAlfaia.value.id, ...currentQuery.value }), options);
        return;
    }

    normalizeAlfaia(alfaiaForm).post(route('app.alfaias.store', currentQuery.value), options);
};

const deleteAlfaia = (alfaia) => {
    if (!window.confirm(`Remover a alfaia "${alfaia.nome}"?`)) {
        return;
    }

    router.delete(route('app.alfaias.destroy', { alfaia: alfaia.id, ...currentQuery.value }), {
        preserveScroll: true,
    });
};

const openCreateRevisao = (maquina = null) => {
    editingRevisao.value = null;
    revisaoForm.defaults({
        ...revisaoBase,
        maquina_id: maquina?.id?.toString() ?? (filterState.revisao_maquina_id || filterState.maquina_id || ''),
    });
    revisaoForm.reset();
    revisaoForm.clearErrors();
    revisaoModalOpen.value = true;
};

/**
 * Revisão de uma alfaia. A máquina fica em branco de propósito: um radiador do
 * triturador é gasto do triturador, e não do tractor que o puxa.
 */
const openCreateRevisaoAlfaia = (alfaia) => {
    editingRevisao.value = null;
    revisaoForm.defaults({
        ...revisaoBase,
        alfaia_id: alfaia?.id?.toString() ?? '',
    });
    revisaoForm.reset();
    revisaoForm.clearErrors();
    revisaoModalOpen.value = true;
};

const verRevisoesDaAlfaia = (alfaia) => {
    filterState.revisao_maquina_id = '';
    filterState.revisao_alfaia_id = alfaia.id.toString();
};

const openEditRevisao = (revisao) => {
    editingRevisao.value = revisao;
    revisaoForm.defaults({
        maquina_id: revisao.maquina_id?.toString() ?? '',
        alfaia_id: revisao.alfaia_id?.toString() ?? '',
        data_manutencao: revisao.data_manutencao ?? '',
        tipo: revisao.tipo ?? 'revisão',
        descricao: revisao.descricao ?? '',
        custo: revisao.custo?.toString() ?? '',
        duracao_minutos: revisao.duracao_minutos?.toString() ?? '',
        proxima_manutencao: revisao.proxima_manutencao ?? '',
        observacoes: revisao.observacoes ?? '',
    });
    revisaoForm.reset();
    revisaoForm.clearErrors();
    revisaoModalOpen.value = true;
};

const closeRevisaoModal = () => {
    revisaoModalOpen.value = false;
    editingRevisao.value = null;
    revisaoForm.clearErrors();
};

const submitRevisao = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeRevisaoModal(),
        onFinish: () => revisaoForm.transform((data) => data),
    };

    if (editingRevisao.value) {
        normalizeRevisao(revisaoForm).patch(route('app.revisoes.update', { revisao: editingRevisao.value.id, ...currentQuery.value }), options);
        return;
    }

    normalizeRevisao(revisaoForm).post(route('app.revisoes.store', currentQuery.value), options);
};

const deleteRevisao = (revisao) => {
    if (!window.confirm(`Remover a revisão de "${revisao.equipamento_nome}"?`)) {
        return;
    }

    router.delete(route('app.revisoes.destroy', { revisao: revisao.id, ...currentQuery.value }), {
        preserveScroll: true,
    });
};

const cleanFilters = () => {
    filterState.search = '';
    filterState.tipo = '';
    filterState.estado = '';
    filterState.alfaia_estado = '';
    filterState.maquina_id = '';
    filterState.revisao_tipo = '';
    filterState.revisao_maquina_id = '';
    filterState.revisao_alfaia_id = '';
};
</script>

<template>
    <Head title="Maquinaria" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold text-verde-700">Frota agrícola</p>
                    <h1 class="mt-1 text-[28px] font-bold leading-tight text-slate-900">Maquinaria</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        Gere tratores, viaturas, equipamentos e alfaias para associar às operações no terreno.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <PrimaryButton
                        v-if="can.create_maquina"
                        class="rounded-lg bg-emerald-700 px-5 py-3 text-sm hover:bg-verde-800 focus:bg-verde-800 text-white font-semibold inline-flex items-center"
                        @click="openCreateMaquina"
                    >
                        Nova máquina
                    </PrimaryButton>
                    <PrimaryButton
                        v-if="can.create_alfaia"
                        class="rounded-lg bg-slate-900 px-5 py-3 text-sm hover:bg-slate-800 focus:bg-slate-800 text-white font-semibold inline-flex items-center"
                        @click="openCreateAlfaia"
                    >
                        Nova alfaia
                    </PrimaryButton>
                    <PrimaryButton
                        v-if="can.create_revisao"
                        class="rounded-lg bg-amber-700 px-5 py-3 text-sm hover:bg-amber-600 focus:bg-amber-600"
                        @click="openCreateRevisao()"
                    >
                        Nova revisão
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <div v-if="flashSuccess" class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800">
                    {{ flashSuccess }}
                </div>

                <section class="grid gap-4 md:grid-cols-3 xl:grid-cols-7">
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-sm font-medium text-slate-500">Máquinas</p>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ summary.maquinas }}</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-sm font-medium text-slate-500">Operacionais</p>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ summary.operacionais }}</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-sm font-medium text-slate-500">Em manutenção</p>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ summary.manutencao }}</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-sm font-medium text-slate-500">Alfaias</p>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ summary.alfaias }}</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-sm font-medium text-slate-500">Alfaias ativas</p>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ summary.alfaias_operacionais }}</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-sm font-medium text-slate-500">Revisões</p>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ summary.revisoes }}</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-sm font-medium text-slate-500">Próximas revisões</p>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ summary.proximas_revisoes }}</p>
                    </article>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_0.72fr_0.72fr_0.72fr_0.78fr_0.78fr_0.78fr_auto]">
                        <div>
                            <InputLabel value="Pesquisar" />
                            <TextInput v-model="filterState.search" class="mt-2 block w-full rounded-lg" placeholder="Nome, marca, matrícula ou alfaia" />
                        </div>
                        <div>
                            <InputLabel value="Tipo de máquina" />
                            <select v-model="filterState.tipo" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todos</option>
                                <option v-for="tipo in maquinaTipoOptions" :key="tipo" :value="tipo">{{ labelize(tipo) }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Estado da máquina" />
                            <select v-model="filterState.estado" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todos</option>
                                <option v-for="estado in maquinaEstadoOptions" :key="estado" :value="estado">{{ labelize(estado) }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Estado alfaia" />
                            <select v-model="filterState.alfaia_estado" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todas</option>
                                <option v-for="estado in alfaiaEstadoOptions" :key="estado" :value="estado">{{ labelize(estado) }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Máquina associada" />
                            <select v-model="filterState.maquina_id" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todas</option>
                                <option v-for="maquina in maquinaOptions" :key="maquina.id" :value="String(maquina.id)">{{ maquina.nome }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Tipo de revisão" />
                            <select v-model="filterState.revisao_tipo" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todas</option>
                                <option v-for="tipo in revisaoTipoOptions" :key="tipo" :value="tipo">{{ labelize(tipo) }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Alfaia revista" />
                            <select v-model="filterState.revisao_alfaia_id" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todas</option>
                                <option v-for="alfaia in alfaiaOptions" :key="alfaia.id" :value="String(alfaia.id)">{{ alfaia.nome }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Máquina revista" />
                            <select v-model="filterState.revisao_maquina_id" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todas</option>
                                <option v-for="maquina in maquinaOptions" :key="maquina.id" :value="String(maquina.id)">{{ maquina.nome }}</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <SecondaryButton class="w-full justify-center rounded-lg px-5 py-3 text-sm " @click="cleanFilters">
                                Limpar
                            </SecondaryButton>
                        </div>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-[1.08fr_0.92fr]">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="text-xl font-bold text-slate-900">Máquinas e viaturas</h2>
                            <p class="text-sm text-slate-500">{{ maquinas.total }} registos</p>
                        </div>

                        <article v-for="maquina in maquinas.data" :key="maquina.id" class="rounded-xl border border-slate-200 bg-white p-6">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h3 class="text-2xl font-bold text-slate-900">{{ maquina.nome }}</h3>
                                        <span class="rounded-md px-3 py-1 text-xs font-semibold" :class="maquinaStatusClass(maquina.estado)">
                                            {{ labelize(maquina.estado) }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm font-medium capitalize text-emerald-700">{{ labelize(maquina.tipo) }}</p>
                                    <p class="mt-2 text-sm text-slate-500">
                                        {{ maquina.marca || 'Sem marca' }} {{ maquina.modelo || '' }}
                                    </p>
                                </div>
                                <div class="text-left md:text-right">
                                    <p class="text-xs font-semibold text-slate-400">Matrícula</p>
                                    <p class="mt-1 text-sm font-bold text-slate-800">{{ maquina.matricula || '-' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Ano {{ maquina.ano_aquisicao || '-' }}</p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-4">
                                <div class="rounded-xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold text-slate-400">{{ usageLabel(maquina.tipo) }}</p>
                                    <p class="mt-2 text-xl font-bold text-slate-900">{{ formatNumber(maquina.horas_uso) }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ usageUnit(maquina.tipo) }}</p>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold text-slate-400">Próx. manut.</p>
                                    <p class="mt-2 text-xl font-bold text-amber-700">{{ formatNumber(maquina.horas_manutencao) }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ usageUnit(maquina.tipo) }}</p>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold text-slate-400">Alfaias</p>
                                    <p class="mt-2 text-xl font-bold text-slate-900">{{ maquina.alfaias_count }}</p>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold text-slate-400">Operações</p>
                                    <p class="mt-2 text-xl font-bold text-slate-900">{{ maquina.operacoes_count }}</p>
                                </div>
                                <div class="rounded-xl bg-amber-50 p-4 sm:col-span-2">
                                    <p class="text-xs font-semibold text-amber-700">Peças e manutenção</p>
                                    <p class="mt-2 text-xl font-bold text-amber-900">{{ formatCurrency(maquina.custo_acumulado) }}</p>
                                    <p class="mt-1 text-xs text-amber-700">
                                        {{ formatCurrency(maquina.custo_pecas) }} em peças · {{ maquina.manutencoes_count }} revisões
                                    </p>
                                </div>
                                <div v-if="isPulverizador(maquina.tipo)" class="rounded-xl bg-sky-50 p-4 sm:col-span-2">
                                    <p class="text-xs font-semibold text-sky-700">Consumo de água</p>
                                    <p class="mt-2 text-xl font-bold text-sky-900">
                                        {{ maquina.consumo_agua_ha ? `${formatNumber(maquina.consumo_agua_ha)} L/ha` : '-' }}
                                    </p>
                                </div>
                                <div v-if="maquina.custo_hora || maquina.custo_km" class="rounded-xl bg-emerald-50 p-4 sm:col-span-2">
                                    <p class="text-sm font-semibold text-verde-700">Custo de utilização</p>
                                    <p class="mt-2 text-xl font-bold text-emerald-900">
                                        <span v-if="maquina.custo_hora">{{ formatNumber(maquina.custo_hora) }} €/h</span>
                                        <span v-if="maquina.custo_hora && maquina.custo_km"> · </span>
                                        <span v-if="maquina.custo_km">{{ formatNumber(maquina.custo_km) }} €/km</span>
                                    </p>
                                </div>
                                <div v-if="usesFuelConsumption(maquina.tipo)" class="rounded-xl bg-amber-50 p-4 sm:col-span-2">
                                    <p class="text-xs font-semibold text-amber-700">Consumo de combustível</p>
                                    <p class="mt-2 text-xl font-bold text-amber-900">
                                        {{ maquina.consumo_combustivel ? `${formatNumber(maquina.consumo_combustivel)} ${isVeiculo(maquina.tipo) ? 'L/100 km' : 'L/h'}` : '-' }}
                                    </p>
                                </div>
                            </div>

                            <p class="mt-4 rounded-xl bg-lime-50/60 p-4 text-sm leading-7 text-slate-600">
                                {{ maquina.observacoes || 'Sem observações adicionais.' }}
                            </p>

                            <div class="mt-5 flex flex-wrap gap-3">
                                <PrimaryButton v-if="maquina.can_update" class="rounded-lg bg-slate-900 px-4 py-2 text-sm hover:bg-slate-800 focus:bg-slate-800 text-white font-semibold inline-flex items-center" @click="openEditMaquina(maquina)">
                                    Editar
                                </PrimaryButton>
                                <SecondaryButton v-if="can.create_revisao" class="rounded-lg px-4 py-2 text-sm " @click="openCreateRevisao(maquina)">
                                    Registar revisão
                                </SecondaryButton>
                                <DangerButton v-if="maquina.can_delete" class="rounded-lg px-4 py-2 text-sm " @click="deleteMaquina(maquina)">
                                    Remover
                                </DangerButton>
                            </div>
                        </article>

                        <section v-if="!maquinas.data.length" class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-600">
                            Nenhuma máquina encontrada com os filtros atuais.
                        </section>

                        <section v-if="maquinas.links?.length > 3" class="flex flex-wrap items-center gap-2">
                            <component
                                :is="link.url ? Link : 'span'"
                                v-for="link in maquinas.links"
                                :key="`maquina-${link.label}-${link.url}`"
                                :href="link.url || undefined"
                                class="rounded-lg px-4 py-2 text-sm transition"
                                :class="link.active ? 'bg-emerald-700 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
                                v-html="link.label"
                            />
                        </section>
                    </div>

                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="text-xl font-bold text-slate-900">Alfaias</h2>
                            <p class="text-sm text-slate-500">{{ alfaias.total }} equipamentos</p>
                        </div>

                        <article v-for="alfaia in alfaias.data" :key="alfaia.id" class="rounded-xl border border-slate-200 bg-white p-6">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                <div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h3 class="break-words text-xl font-bold sm:text-2xl text-slate-900">{{ alfaia.nome }}</h3>
                                        <span class="rounded-md px-3 py-1 text-xs font-semibold" :class="alfaiaStatusClass(alfaia.estado)">
                                            {{ labelize(alfaia.estado) }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm font-medium capitalize text-emerald-700">{{ labelize(alfaia.tipo) }}</p>
                                    <p class="mt-2 text-sm text-slate-500">
                                        Associada a: {{ alfaia.maquina_nome || 'Sem máquina' }}
                                    </p>
                                </div>
                                <div class="shrink-0 self-start rounded-xl bg-emerald-50 px-4 py-3 text-center">
                                    <p class="text-2xl font-bold text-emerald-700">{{ alfaia.operacoes_count }}</p>
                                    <p class="text-sm font-semibold text-verde-700">usos</p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold text-slate-400">Comprimento</p>
                                    <p class="mt-2 text-sm font-bold text-slate-800">{{ alfaia.comprimento ? `${formatNumber(alfaia.comprimento)} m` : '-' }}</p>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold text-slate-400">Largura</p>
                                    <p class="mt-2 text-sm font-bold text-slate-800">{{ alfaia.largura ? `${formatNumber(alfaia.largura)} m` : '-' }}</p>
                                </div>
                                <div v-if="isPulverizador(alfaia.tipo)" class="rounded-xl bg-sky-50 p-4 sm:col-span-2">
                                    <p class="text-xs font-semibold text-sky-700">Consumo de água</p>
                                    <p class="mt-2 text-sm font-bold text-sky-900">
                                        {{ alfaia.consumo_agua_ha ? `${formatNumber(alfaia.consumo_agua_ha)} L/ha` : '-' }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-xl bg-amber-50 p-4">
                                    <p class="text-xs font-semibold text-amber-700">Peças</p>
                                    <p class="mt-2 text-sm font-bold text-amber-900">{{ formatCurrency(alfaia.custo_pecas) }}</p>
                                </div>
                                <div class="rounded-xl bg-amber-50 p-4">
                                    <p class="text-xs font-semibold text-amber-700">Manutenção</p>
                                    <p class="mt-2 text-sm font-bold text-amber-900">
                                        {{ formatCurrency(alfaia.custo_manutencoes) }}
                                        <span class="font-medium text-amber-700">({{ alfaia.manutencoes_count }})</span>
                                    </p>
                                </div>
                                <div class="rounded-xl bg-slate-900 p-4 text-white font-semibold inline-flex items-center">
                                    <p class="text-xs font-semibold text-slate-300">Já custou</p>
                                    <p class="mt-2 text-sm font-bold text-white">{{ formatCurrency(alfaia.custo_acumulado) }}</p>
                                </div>
                            </div>

                            <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm leading-7 text-slate-600">
                                {{ alfaia.descricao || alfaia.observacoes || 'Sem descrição para esta alfaia.' }}
                            </p>

                            <div class="mt-5 flex flex-wrap gap-3">
                                <PrimaryButton v-if="alfaia.can_update" class="rounded-lg bg-amber-700 px-4 py-2 text-sm hover:bg-amber-600 focus:bg-amber-600" @click="openCreateRevisaoAlfaia(alfaia)">
                                    Nova revisão
                                </PrimaryButton>
                                <SecondaryButton v-if="alfaia.manutencoes_count" class="rounded-lg px-4 py-2 text-sm " @click="verRevisoesDaAlfaia(alfaia)">
                                    Ver revisões
                                </SecondaryButton>
                                <PrimaryButton v-if="alfaia.can_update" class="rounded-lg bg-slate-900 px-4 py-2 text-sm hover:bg-slate-800 focus:bg-slate-800 text-white font-semibold inline-flex items-center" @click="openEditAlfaia(alfaia)">
                                    Editar
                                </PrimaryButton>
                                <DangerButton v-if="alfaia.can_delete" class="rounded-lg px-4 py-2 text-sm " @click="deleteAlfaia(alfaia)">
                                    Remover
                                </DangerButton>
                            </div>
                        </article>

                        <section v-if="!alfaias.data.length" class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-600">
                            Nenhuma alfaia encontrada com os filtros atuais.
                        </section>

                        <section v-if="alfaias.links?.length > 3" class="flex flex-wrap items-center gap-2">
                            <component
                                :is="link.url ? Link : 'span'"
                                v-for="link in alfaias.links"
                                :key="`alfaia-${link.label}-${link.url}`"
                                :href="link.url || undefined"
                                class="rounded-lg px-4 py-2 text-sm transition"
                                :class="link.active ? 'bg-emerald-700 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
                                v-html="link.label"
                            />
                        </section>
                    </div>
                </section>

                <section class="flex flex-col gap-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">Revisões</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ revisoes.total }} registos de manutenção e revisão</p>
                        </div>
                        <PrimaryButton
                            v-if="can.create_revisao"
                            class="rounded-lg bg-amber-700 px-5 py-3 text-sm hover:bg-amber-600 focus:bg-amber-600"
                            @click="openCreateRevisao()"
                        >
                            Nova revisão
                        </PrimaryButton>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <article v-for="revisao in revisoes.data" :key="revisao.id" class="rounded-xl border border-slate-200 bg-white p-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h3 class="text-2xl font-bold text-slate-900">{{ revisao.equipamento_nome || 'Equipamento removido' }}</h3>
                                        <span v-if="revisao.alfaia_id" class="rounded-md bg-emerald-50 px-3 py-1 text-sm font-semibold text-verde-700">
                                            alfaia
                                        </span>
                                        <span class="rounded-md bg-amber-50 px-3 py-1 text-xs font-semibold capitalize text-amber-700">
                                            {{ labelize(revisao.tipo) }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm font-medium text-emerald-700">{{ formatDate(revisao.data_manutencao) }}</p>
                                </div>
                                <div class="text-left sm:text-right">
                                    <p class="text-xs font-semibold text-slate-400">Custo</p>
                                    <p class="mt-1 text-lg font-bold text-slate-900">{{ formatCurrency(revisao.custo) }}</p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold text-slate-400">Duração</p>
                                    <p class="mt-2 text-sm font-bold text-slate-800">{{ formatDuration(revisao.duracao_minutos) }}</p>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold text-slate-400">Próxima</p>
                                    <p class="mt-2 text-sm font-bold text-slate-800">{{ formatDate(revisao.proxima_manutencao) }}</p>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold text-slate-400">Equipamento</p>
                                    <p class="mt-2 text-sm font-bold capitalize text-slate-800">
                                        {{ labelize(revisao.alfaia_tipo || revisao.maquina_tipo || '-') }}
                                    </p>
                                </div>
                            </div>

                            <p class="mt-4 rounded-xl bg-amber-50/70 p-4 text-sm leading-7 text-slate-600">
                                {{ revisao.descricao }}
                            </p>
                            <p v-if="revisao.observacoes" class="mt-3 rounded-xl bg-slate-50 p-4 text-sm leading-7 text-slate-600">
                                {{ revisao.observacoes }}
                            </p>

                            <div class="mt-5 flex flex-wrap gap-3">
                                <PrimaryButton v-if="revisao.can_update" class="rounded-lg bg-slate-900 px-4 py-2 text-sm hover:bg-slate-800 focus:bg-slate-800 text-white font-semibold inline-flex items-center" @click="openEditRevisao(revisao)">
                                    Editar
                                </PrimaryButton>
                                <DangerButton v-if="revisao.can_delete" class="rounded-lg px-4 py-2 text-sm " @click="deleteRevisao(revisao)">
                                    Remover
                                </DangerButton>
                            </div>
                        </article>
                    </div>

                    <section v-if="!revisoes.data.length" class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-600">
                        Nenhuma revisão encontrada com os filtros atuais.
                    </section>

                    <section v-if="revisoes.links?.length > 3" class="flex flex-wrap items-center gap-2">
                        <component
                            :is="link.url ? Link : 'span'"
                            v-for="link in revisoes.links"
                            :key="`revisao-${link.label}-${link.url}`"
                            :href="link.url || undefined"
                            class="rounded-lg px-4 py-2 text-sm transition"
                            :class="link.active ? 'bg-emerald-700 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
                            v-html="link.label"
                        />
                    </section>
                </section>
            </div>
        </div>

        <Modal :show="maquinaModalOpen" max-width="2xl" @close="closeMaquinaModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-900">{{ editingMaquina ? 'Editar máquina' : 'Nova máquina' }}</h2>
                <p class="mt-2 text-sm text-slate-500">Regista tratores, automóveis, carrinhas e outros equipamentos motorizados.</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitMaquina">
                    <div v-if="maquinaErrors.length" class="sm:col-span-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível guardar a máquina. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in maquinaErrors" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Nome" />
                        <TextInput v-model="maquinaForm.nome" class="mt-2 block w-full rounded-lg" placeholder="Ex: Trator principal" />
                        <InputError class="mt-2" :message="maquinaForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="maquinaForm.tipo" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="tipo in maquinaTipoOptions" :key="tipo" :value="tipo">{{ labelize(tipo) }}</option>
                        </select>
                        <InputError class="mt-2" :message="maquinaForm.errors.tipo" />
                    </div>
                    <div>
                        <InputLabel value="Marca" />
                        <TextInput v-model="maquinaForm.marca" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="maquinaForm.errors.marca" />
                    </div>
                    <div>
                        <InputLabel value="Modelo" />
                        <TextInput v-model="maquinaForm.modelo" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="maquinaForm.errors.modelo" />
                    </div>
                    <div>
                        <InputLabel value="Matrícula" />
                        <TextInput v-model="maquinaForm.matricula" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="maquinaForm.errors.matricula" />
                    </div>
                    <div>
                        <InputLabel value="Número de série" />
                        <TextInput v-model="maquinaForm.numero_serie" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="maquinaForm.errors.numero_serie" />
                    </div>
                    <div>
                        <InputLabel value="Ano de aquisição" />
                        <TextInput v-model="maquinaForm.ano_aquisicao" type="number" min="1900" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="maquinaForm.errors.ano_aquisicao" />
                    </div>
                    <div>
                        <InputLabel value="Estado" />
                        <select v-model="maquinaForm.estado" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="estado in maquinaEstadoOptions" :key="estado" :value="estado">{{ labelize(estado) }}</option>
                        </select>
                        <InputError class="mt-2" :message="maquinaForm.errors.estado" />
                    </div>
                    <div>
                        <InputLabel :value="isVeiculo(maquinaForm.tipo) ? 'Quilómetros atuais' : 'Horas de uso'" />
                        <TextInput v-model="maquinaForm.horas_uso" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="maquinaForm.errors.horas_uso" />
                    </div>
                    <div>
                        <InputLabel :value="maintenanceLabel" />
                        <TextInput v-model="maquinaForm.horas_manutencao" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="maquinaForm.errors.horas_manutencao" />
                    </div>
                    <div v-if="isPulverizador(maquinaForm.tipo)">
                        <InputLabel value="Consumo de Água (L/ha)" />
                        <TextInput v-model="maquinaForm.consumo_agua_ha" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="maquinaForm.errors.consumo_agua_ha" />
                    </div>
                    <div v-if="usesFuelConsumption(maquinaForm.tipo)">
                        <InputLabel :value="fuelConsumptionLabel" />
                        <TextInput v-model="maquinaForm.consumo_combustivel" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="maquinaForm.errors.consumo_combustivel" />
                    </div>
                    <!-- Custo de utilizacao: e o que faz as maquinas entrarem no custo das operacoes -->
                    <div>
                        <InputLabel value="Custo por hora (€/h)" />
                        <TextInput v-model="maquinaForm.custo_hora" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <p class="mt-1 text-xs text-slate-400">Combustível, desgaste e amortização por hora de trabalho.</p>
                        <InputError class="mt-2" :message="maquinaForm.errors.custo_hora" />
                    </div>
                    <div>
                        <InputLabel value="Custo por km (€/km)" />
                        <TextInput v-model="maquinaForm.custo_km" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <p class="mt-1 text-xs text-slate-400">Para viaturas de transporte, em alternativa ao custo por hora.</p>
                        <InputError class="mt-2" :message="maquinaForm.errors.custo_km" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Observações" />
                        <textarea v-model="maquinaForm.observacoes" rows="4" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="maquinaForm.errors.observacoes" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-lg px-4 py-2 text-sm " @click="closeMaquinaModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="rounded-lg bg-emerald-700 px-4 py-2 text-sm hover:bg-verde-800 focus:bg-verde-800 text-white font-semibold inline-flex items-center" :disabled="maquinaForm.processing">
                            Guardar máquina
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="alfaiaModalOpen" max-width="2xl" @close="closeAlfaiaModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-900">{{ editingAlfaia ? 'Editar alfaia' : 'Nova alfaia' }}</h2>
                <p class="mt-2 text-sm text-slate-500">Associa alfaias e implementos às máquinas usadas nas operações.</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitAlfaia">
                    <div v-if="alfaiaErrors.length" class="sm:col-span-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível guardar a alfaia. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in alfaiaErrors" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Nome" />
                        <TextInput v-model="alfaiaForm.nome" class="mt-2 block w-full rounded-lg" placeholder="Ex: Grade discos" />
                        <InputError class="mt-2" :message="alfaiaForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="alfaiaForm.tipo" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="tipo in alfaiaTipoOptions" :key="tipo" :value="tipo">{{ labelize(tipo) }}</option>
                        </select>
                        <InputError class="mt-2" :message="alfaiaForm.errors.tipo" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Máquina associada" />
                        <select v-model="alfaiaForm.maquina_id" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">Sem máquina associada</option>
                            <option v-for="maquina in maquinaOptions" :key="maquina.id" :value="String(maquina.id)">
                                {{ maquina.nome }} - {{ labelize(maquina.tipo) }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="alfaiaForm.errors.maquina_id" />
                    </div>
                    <div>
                        <InputLabel value="Comprimento (m)" />
                        <TextInput v-model="alfaiaForm.comprimento" type="number" step="0.01" min="0.01" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="alfaiaForm.errors.comprimento" />
                    </div>
                    <div>
                        <InputLabel value="Largura (m)" />
                        <TextInput v-model="alfaiaForm.largura" type="number" step="0.01" min="0.01" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="alfaiaForm.errors.largura" />
                    </div>
                    <div v-if="isPulverizador(alfaiaForm.tipo)" class="sm:col-span-2">
                        <InputLabel value="Consumo de Água (L/ha)" />
                        <TextInput v-model="alfaiaForm.consumo_agua_ha" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="alfaiaForm.errors.consumo_agua_ha" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Custo por hora (€/h)" />
                        <TextInput v-model="alfaiaForm.custo_hora" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <p class="mt-1 text-xs text-slate-400">Usado quando a alfaia entra numa operação sem trator associado.</p>
                        <InputError class="mt-2" :message="alfaiaForm.errors.custo_hora" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Estado" />
                        <select v-model="alfaiaForm.estado" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="estado in alfaiaEstadoOptions" :key="estado" :value="estado">{{ labelize(estado) }}</option>
                        </select>
                        <InputError class="mt-2" :message="alfaiaForm.errors.estado" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Descrição" />
                        <textarea v-model="alfaiaForm.descricao" rows="3" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="alfaiaForm.errors.descricao" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Observações" />
                        <textarea v-model="alfaiaForm.observacoes" rows="3" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="alfaiaForm.errors.observacoes" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-lg px-4 py-2 text-sm " @click="closeAlfaiaModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="rounded-lg bg-slate-900 px-4 py-2 text-sm hover:bg-slate-800 focus:bg-slate-800 text-white font-semibold inline-flex items-center" :disabled="alfaiaForm.processing">
                            Guardar alfaia
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="revisaoModalOpen" max-width="2xl" @close="closeRevisaoModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-900">{{ editingRevisao ? 'Editar revisão' : 'Nova revisão' }}</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Regista revisões, inspeções e manutenções. Indica a máquina, a alfaia, ou as duas quando a revisão é do conjunto.
                </p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitRevisao">
                    <div v-if="revisaoErrors.length" class="sm:col-span-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível guardar a revisão. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in revisaoErrors" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Máquina" />
                        <select v-model="revisaoForm.maquina_id" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">Nenhuma</option>
                            <option v-for="maquina in maquinaOptions" :key="maquina.id" :value="String(maquina.id)">
                                {{ maquina.nome }} - {{ labelize(maquina.tipo) }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="revisaoForm.errors.maquina_id" />
                    </div>
                    <div>
                        <InputLabel value="Alfaia" />
                        <select v-model="revisaoForm.alfaia_id" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">Nenhuma</option>
                            <option v-for="alfaia in alfaiaOptions" :key="alfaia.id" :value="String(alfaia.id)">
                                {{ alfaia.nome }} - {{ labelize(alfaia.tipo) }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="revisaoForm.errors.alfaia_id" />
                    </div>
                    <div>
                        <InputLabel value="Data da revisão" />
                        <TextInput v-model="revisaoForm.data_manutencao" type="date" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="revisaoForm.errors.data_manutencao" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="revisaoForm.tipo" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="tipo in revisaoTipoOptions" :key="tipo" :value="tipo">{{ labelize(tipo) }}</option>
                        </select>
                        <InputError class="mt-2" :message="revisaoForm.errors.tipo" />
                    </div>
                    <div>
                        <InputLabel value="Custo (€)" />
                        <TextInput v-model="revisaoForm.custo" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="revisaoForm.errors.custo" />
                    </div>
                    <div>
                        <InputLabel value="Duração (min)" />
                        <TextInput v-model="revisaoForm.duracao_minutos" type="number" min="1" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="revisaoForm.errors.duracao_minutos" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Próxima revisão" />
                        <TextInput v-model="revisaoForm.proxima_manutencao" type="date" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="revisaoForm.errors.proxima_manutencao" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Descrição" />
                        <textarea v-model="revisaoForm.descricao" rows="4" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="revisaoForm.errors.descricao" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Observações" />
                        <textarea v-model="revisaoForm.observacoes" rows="3" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="revisaoForm.errors.observacoes" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-lg px-4 py-2 text-sm " @click="closeRevisaoModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="rounded-lg bg-amber-700 px-4 py-2 text-sm hover:bg-amber-600 focus:bg-amber-600" :disabled="revisaoForm.processing">
                            Guardar revisão
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>


