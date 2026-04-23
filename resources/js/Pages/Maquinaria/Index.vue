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
    estado: 'operacional',
    observacoes: '',
};

const revisaoBase = {
    maquina_id: '',
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
    observacoes: data.observacoes || null,
}));

const normalizeAlfaia = (form) => form.transform((data) => ({
    ...data,
    maquina_id: data.maquina_id || null,
    descricao: data.descricao || null,
    comprimento: data.comprimento || null,
    largura: data.largura || null,
    consumo_agua_ha: data.consumo_agua_ha || null,
    observacoes: data.observacoes || null,
}));

const normalizeRevisao = (form) => form.transform((data) => ({
    ...data,
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
    if (!window.confirm(`Remover a mÃ¡quina "${maquina.nome}"?`)) {
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

const openEditRevisao = (revisao) => {
    editingRevisao.value = revisao;
    revisaoForm.defaults({
        maquina_id: revisao.maquina_id?.toString() ?? '',
        data_manutencao: revisao.data_manutencao ?? '',
        tipo: revisao.tipo ?? 'revisÃ£o',
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
    if (!window.confirm(`Remover a revisÃ£o de "${revisao.maquina_nome}"?`)) {
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
};
</script>

<template>
    <Head title="Maquinaria" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-700">Frota agricola</p>
                    <h1 class="mt-2 text-3xl font-black text-slate-900">Maquinaria</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        Gere tratores, viaturas, equipamentos e alfaias para associar Ã s operaÃ§Ãµes no terreno.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <PrimaryButton
                        v-if="can.create_maquina"
                        class="rounded-full bg-emerald-700 px-5 py-3 text-sm normal-case tracking-normal hover:bg-emerald-600 focus:bg-emerald-600"
                        @click="openCreateMaquina"
                    >
                        Nova mÃ¡quina
                    </PrimaryButton>
                    <PrimaryButton
                        v-if="can.create_alfaia"
                        class="rounded-full bg-slate-900 px-5 py-3 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800"
                        @click="openCreateAlfaia"
                    >
                        Nova alfaia
                    </PrimaryButton>
                    <PrimaryButton
                        v-if="can.create_revisao"
                        class="rounded-full bg-amber-700 px-5 py-3 text-sm normal-case tracking-normal hover:bg-amber-600 focus:bg-amber-600"
                        @click="openCreateRevisao()"
                    >
                        Nova revisÃ£o
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.16),_transparent_28%),linear-gradient(180deg,_#f8fafc_0%,_#eef6f1_100%)] py-10">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <div v-if="flashSuccess" class="rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800">
                    {{ flashSuccess }}
                </div>

                <section class="grid gap-4 md:grid-cols-3 xl:grid-cols-7">
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.20)]">
                        <p class="text-sm font-medium text-slate-500">MÃ¡quinas</p>
                        <p class="mt-3 text-4xl font-black text-slate-900">{{ summary.maquinas }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.20)]">
                        <p class="text-sm font-medium text-slate-500">Operacionais</p>
                        <p class="mt-3 text-4xl font-black text-emerald-700">{{ summary.operacionais }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.20)]">
                        <p class="text-sm font-medium text-slate-500">Em manutenÃ§Ã£o</p>
                        <p class="mt-3 text-4xl font-black text-amber-700">{{ summary.manutencao }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.20)]">
                        <p class="text-sm font-medium text-slate-500">Alfaias</p>
                        <p class="mt-3 text-4xl font-black text-slate-900">{{ summary.alfaias }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.20)]">
                        <p class="text-sm font-medium text-slate-500">Alfaias ativas</p>
                        <p class="mt-3 text-4xl font-black text-emerald-700">{{ summary.alfaias_operacionais }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.20)]">
                        <p class="text-sm font-medium text-slate-500">RevisÃµes</p>
                        <p class="mt-3 text-4xl font-black text-slate-900">{{ summary.revisoes }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.20)]">
                        <p class="text-sm font-medium text-slate-500">PrÃ³ximas revisÃµes</p>
                        <p class="mt-3 text-4xl font-black text-amber-700">{{ summary.proximas_revisoes }}</p>
                    </article>
                </section>

                <section class="rounded-[32px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.20)]">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_0.72fr_0.72fr_0.72fr_0.78fr_0.78fr_0.78fr_auto]">
                        <div>
                            <InputLabel value="Pesquisar" />
                            <TextInput v-model="filterState.search" class="mt-2 block w-full rounded-2xl" placeholder="Nome, marca, matricula ou alfaia" />
                        </div>
                        <div>
                            <InputLabel value="Tipo de mÃ¡quina" />
                            <select v-model="filterState.tipo" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todos</option>
                                <option v-for="tipo in maquinaTipoOptions" :key="tipo" :value="tipo">{{ labelize(tipo) }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Estado da mÃ¡quina" />
                            <select v-model="filterState.estado" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todos</option>
                                <option v-for="estado in maquinaEstadoOptions" :key="estado" :value="estado">{{ labelize(estado) }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Estado alfaia" />
                            <select v-model="filterState.alfaia_estado" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todas</option>
                                <option v-for="estado in alfaiaEstadoOptions" :key="estado" :value="estado">{{ labelize(estado) }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="MÃ¡quina associada" />
                            <select v-model="filterState.maquina_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todas</option>
                                <option v-for="maquina in maquinaOptions" :key="maquina.id" :value="String(maquina.id)">{{ maquina.nome }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Tipo de revisÃ£o" />
                            <select v-model="filterState.revisao_tipo" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todas</option>
                                <option v-for="tipo in revisaoTipoOptions" :key="tipo" :value="tipo">{{ labelize(tipo) }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="MÃ¡quina revista" />
                            <select v-model="filterState.revisao_maquina_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todas</option>
                                <option v-for="maquina in maquinaOptions" :key="maquina.id" :value="String(maquina.id)">{{ maquina.nome }}</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <SecondaryButton class="w-full justify-center rounded-full px-5 py-3 text-sm normal-case tracking-normal" @click="cleanFilters">
                                Limpar
                            </SecondaryButton>
                        </div>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-[1.08fr_0.92fr]">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="text-xl font-black text-slate-900">MÃ¡quinas e viaturas</h2>
                            <p class="text-sm text-slate-500">{{ maquinas.total }} registos</p>
                        </div>

                        <article v-for="maquina in maquinas.data" :key="maquina.id" class="rounded-[32px] border border-white/80 bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.20)]">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h3 class="text-2xl font-black text-slate-900">{{ maquina.nome }}</h3>
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="maquinaStatusClass(maquina.estado)">
                                            {{ labelize(maquina.estado) }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm font-medium capitalize text-emerald-700">{{ labelize(maquina.tipo) }}</p>
                                    <p class="mt-2 text-sm text-slate-500">
                                        {{ maquina.marca || 'Sem marca' }} {{ maquina.modelo || '' }}
                                    </p>
                                </div>
                                <div class="text-left md:text-right">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Matricula</p>
                                    <p class="mt-1 text-sm font-bold text-slate-800">{{ maquina.matricula || '-' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Ano {{ maquina.ano_aquisicao || '-' }}</p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-4">
                                <div class="rounded-3xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Horas</p>
                                    <p class="mt-2 text-xl font-black text-slate-900">{{ formatNumber(maquina.horas_uso) }}</p>
                                </div>
                                <div class="rounded-3xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">PrÃ³x. manut.</p>
                                    <p class="mt-2 text-xl font-black text-amber-700">{{ formatNumber(maquina.horas_manutencao) }}</p>
                                </div>
                                <div class="rounded-3xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Alfaias</p>
                                    <p class="mt-2 text-xl font-black text-slate-900">{{ maquina.alfaias_count }}</p>
                                </div>
                                <div class="rounded-3xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">OperaÃ§Ãµes</p>
                                    <p class="mt-2 text-xl font-black text-slate-900">{{ maquina.operacoes_count }}</p>
                                </div>
                                <div v-if="isPulverizador(maquina.tipo)" class="rounded-3xl bg-sky-50 p-4 sm:col-span-2">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-700">Consumo de ?gua</p>
                                    <p class="mt-2 text-xl font-black text-sky-900">
                                        {{ maquina.consumo_agua_ha ? `${formatNumber(maquina.consumo_agua_ha)} L/ha` : '-' }}
                                    </p>
                                </div>
                                <div v-if="isVeiculo(maquina.tipo)" class="rounded-3xl bg-amber-50 p-4 sm:col-span-2">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Consumo de combustÃ­vel</p>
                                    <p class="mt-2 text-xl font-black text-amber-900">
                                        {{ maquina.consumo_combustivel ? `${formatNumber(maquina.consumo_combustivel)} L/100 km` : '-' }}
                                    </p>
                                </div>
                            </div>

                            <p class="mt-4 rounded-3xl bg-lime-50/60 p-4 text-sm leading-7 text-slate-600">
                                {{ maquina.observacoes || 'Sem observaÃ§Ãµes adicionais.' }}
                            </p>

                            <div class="mt-5 flex flex-wrap gap-3">
                                <PrimaryButton v-if="maquina.can_update" class="rounded-full bg-slate-900 px-4 py-2 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800" @click="openEditMaquina(maquina)">
                                    Editar
                                </PrimaryButton>
                                <SecondaryButton v-if="can.create_revisao" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="openCreateRevisao(maquina)">
                                    Registar revisÃ£o
                                </SecondaryButton>
                                <DangerButton v-if="maquina.can_delete" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="deleteMaquina(maquina)">
                                    Remover
                                </DangerButton>
                            </div>
                        </article>

                        <section v-if="!maquinas.data.length" class="rounded-[32px] border border-dashed border-slate-300 bg-white/70 px-6 py-10 text-center text-sm text-slate-600">
                            Nenhuma mÃ¡quina encontrada com os filtros atuais.
                        </section>

                        <section v-if="maquinas.links?.length > 3" class="flex flex-wrap items-center gap-2">
                            <component
                                :is="link.url ? Link : 'span'"
                                v-for="link in maquinas.links"
                                :key="`maquina-${link.label}-${link.url}`"
                                :href="link.url || undefined"
                                class="rounded-full px-4 py-2 text-sm transition"
                                :class="link.active ? 'bg-emerald-700 text-white' : 'bg-white text-slate-600 shadow hover:bg-slate-50'"
                                v-html="link.label"
                            />
                        </section>
                    </div>

                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="text-xl font-black text-slate-900">Alfaias</h2>
                            <p class="text-sm text-slate-500">{{ alfaias.total }} equipamentos</p>
                        </div>

                        <article v-for="alfaia in alfaias.data" :key="alfaia.id" class="rounded-[32px] border border-white/80 bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.20)]">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h3 class="text-2xl font-black text-slate-900">{{ alfaia.nome }}</h3>
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="alfaiaStatusClass(alfaia.estado)">
                                            {{ labelize(alfaia.estado) }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm font-medium capitalize text-emerald-700">{{ labelize(alfaia.tipo) }}</p>
                                    <p class="mt-2 text-sm text-slate-500">
                                        Associada a: {{ alfaia.maquina_nome || 'Sem mÃ¡quina' }}
                                    </p>
                                </div>
                                <div class="rounded-3xl bg-emerald-50 px-4 py-3 text-center">
                                    <p class="text-2xl font-black text-emerald-700">{{ alfaia.operacoes_count }}</p>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">usos</p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-3xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Comprimento</p>
                                    <p class="mt-2 text-sm font-bold text-slate-800">{{ alfaia.comprimento ? `${formatNumber(alfaia.comprimento)} m` : '-' }}</p>
                                </div>
                                <div class="rounded-3xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Largura</p>
                                    <p class="mt-2 text-sm font-bold text-slate-800">{{ alfaia.largura ? `${formatNumber(alfaia.largura)} m` : '-' }}</p>
                                </div>
                                <div v-if="isPulverizador(alfaia.tipo)" class="rounded-3xl bg-sky-50 p-4 sm:col-span-2">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-700">Consumo de ?gua</p>
                                    <p class="mt-2 text-sm font-bold text-sky-900">
                                        {{ alfaia.consumo_agua_ha ? `${formatNumber(alfaia.consumo_agua_ha)} L/ha` : '-' }}
                                    </p>
                                </div>
                            </div>

                            <p class="mt-4 rounded-3xl bg-slate-50 p-4 text-sm leading-7 text-slate-600">
                                {{ alfaia.descricao || alfaia.observacoes || 'Sem descriÃ§Ã£o para esta alfaia.' }}
                            </p>

                            <div class="mt-5 flex flex-wrap gap-3">
                                <PrimaryButton v-if="alfaia.can_update" class="rounded-full bg-slate-900 px-4 py-2 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800" @click="openEditAlfaia(alfaia)">
                                    Editar
                                </PrimaryButton>
                                <DangerButton v-if="alfaia.can_delete" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="deleteAlfaia(alfaia)">
                                    Remover
                                </DangerButton>
                            </div>
                        </article>

                        <section v-if="!alfaias.data.length" class="rounded-[32px] border border-dashed border-slate-300 bg-white/70 px-6 py-10 text-center text-sm text-slate-600">
                            Nenhuma alfaia encontrada com os filtros atuais.
                        </section>

                        <section v-if="alfaias.links?.length > 3" class="flex flex-wrap items-center gap-2">
                            <component
                                :is="link.url ? Link : 'span'"
                                v-for="link in alfaias.links"
                                :key="`alfaia-${link.label}-${link.url}`"
                                :href="link.url || undefined"
                                class="rounded-full px-4 py-2 text-sm transition"
                                :class="link.active ? 'bg-emerald-700 text-white' : 'bg-white text-slate-600 shadow hover:bg-slate-50'"
                                v-html="link.label"
                            />
                        </section>
                    </div>
                </section>

                <section class="flex flex-col gap-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-xl font-black text-slate-900">RevisÃµes</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ revisoes.total }} registos de manutenÃ§Ã£o e revisÃ£o</p>
                        </div>
                        <PrimaryButton
                            v-if="can.create_revisao"
                            class="rounded-full bg-amber-700 px-5 py-3 text-sm normal-case tracking-normal hover:bg-amber-600 focus:bg-amber-600"
                            @click="openCreateRevisao()"
                        >
                            Nova revisÃ£o
                        </PrimaryButton>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <article v-for="revisao in revisoes.data" :key="revisao.id" class="rounded-[32px] border border-white/80 bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.20)]">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h3 class="text-2xl font-black text-slate-900">{{ revisao.maquina_nome || 'MÃ¡quina removida' }}</h3>
                                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold capitalize text-amber-700">
                                            {{ labelize(revisao.tipo) }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm font-medium text-emerald-700">{{ formatDate(revisao.data_manutencao) }}</p>
                                </div>
                                <div class="text-left sm:text-right">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Custo</p>
                                    <p class="mt-1 text-lg font-black text-slate-900">{{ formatCurrency(revisao.custo) }}</p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-3xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">DuraÃ§Ã£o</p>
                                    <p class="mt-2 text-sm font-bold text-slate-800">{{ formatDuration(revisao.duracao_minutos) }}</p>
                                </div>
                                <div class="rounded-3xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">PrÃ³xima</p>
                                    <p class="mt-2 text-sm font-bold text-slate-800">{{ formatDate(revisao.proxima_manutencao) }}</p>
                                </div>
                                <div class="rounded-3xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">MÃ¡quina</p>
                                    <p class="mt-2 text-sm font-bold capitalize text-slate-800">{{ labelize(revisao.maquina_tipo || '-') }}</p>
                                </div>
                            </div>

                            <p class="mt-4 rounded-3xl bg-amber-50/70 p-4 text-sm leading-7 text-slate-600">
                                {{ revisao.descricao }}
                            </p>
                            <p v-if="revisao.observacoes" class="mt-3 rounded-3xl bg-slate-50 p-4 text-sm leading-7 text-slate-600">
                                {{ revisao.observacoes }}
                            </p>

                            <div class="mt-5 flex flex-wrap gap-3">
                                <PrimaryButton v-if="revisao.can_update" class="rounded-full bg-slate-900 px-4 py-2 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800" @click="openEditRevisao(revisao)">
                                    Editar
                                </PrimaryButton>
                                <DangerButton v-if="revisao.can_delete" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="deleteRevisao(revisao)">
                                    Remover
                                </DangerButton>
                            </div>
                        </article>
                    </div>

                    <section v-if="!revisoes.data.length" class="rounded-[32px] border border-dashed border-slate-300 bg-white/70 px-6 py-10 text-center text-sm text-slate-600">
                        Nenhuma revisÃ£o encontrada com os filtros atuais.
                    </section>

                    <section v-if="revisoes.links?.length > 3" class="flex flex-wrap items-center gap-2">
                        <component
                            :is="link.url ? Link : 'span'"
                            v-for="link in revisoes.links"
                            :key="`revisao-${link.label}-${link.url}`"
                            :href="link.url || undefined"
                            class="rounded-full px-4 py-2 text-sm transition"
                            :class="link.active ? 'bg-emerald-700 text-white' : 'bg-white text-slate-600 shadow hover:bg-slate-50'"
                            v-html="link.label"
                        />
                    </section>
                </section>
            </div>
        </div>

        <Modal :show="maquinaModalOpen" max-width="2xl" @close="closeMaquinaModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-black text-slate-900">{{ editingMaquina ? 'Editar mÃ¡quina' : 'Nova mÃ¡quina' }}</h2>
                <p class="mt-2 text-sm text-slate-500">Regista tratores, automÃ³veis, carrinhas e outros equipamentos motorizados.</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitMaquina">
                    <div v-if="maquinaErrors.length" class="sm:col-span-2 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">NÃ£o foi possÃ­vel guardar a mÃ¡quina. RevÃª estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in maquinaErrors" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Nome" />
                        <TextInput v-model="maquinaForm.nome" class="mt-2 block w-full rounded-2xl" placeholder="Ex: Trator principal" />
                        <InputError class="mt-2" :message="maquinaForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="maquinaForm.tipo" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="tipo in maquinaTipoOptions" :key="tipo" :value="tipo">{{ labelize(tipo) }}</option>
                        </select>
                        <InputError class="mt-2" :message="maquinaForm.errors.tipo" />
                    </div>
                    <div>
                        <InputLabel value="Marca" />
                        <TextInput v-model="maquinaForm.marca" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="maquinaForm.errors.marca" />
                    </div>
                    <div>
                        <InputLabel value="Modelo" />
                        <TextInput v-model="maquinaForm.modelo" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="maquinaForm.errors.modelo" />
                    </div>
                    <div>
                        <InputLabel value="Matricula" />
                        <TextInput v-model="maquinaForm.matricula" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="maquinaForm.errors.matricula" />
                    </div>
                    <div>
                        <InputLabel value="Numero de serie" />
                        <TextInput v-model="maquinaForm.numero_serie" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="maquinaForm.errors.numero_serie" />
                    </div>
                    <div>
                        <InputLabel value="Ano aquisicao" />
                        <TextInput v-model="maquinaForm.ano_aquisicao" type="number" min="1900" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="maquinaForm.errors.ano_aquisicao" />
                    </div>
                    <div>
                        <InputLabel value="Estado" />
                        <select v-model="maquinaForm.estado" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="estado in maquinaEstadoOptions" :key="estado" :value="estado">{{ labelize(estado) }}</option>
                        </select>
                        <InputError class="mt-2" :message="maquinaForm.errors.estado" />
                    </div>
                    <div>
                        <InputLabel value="Horas de uso" />
                        <TextInput v-model="maquinaForm.horas_uso" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="maquinaForm.errors.horas_uso" />
                    </div>
                    <div>
                        <InputLabel value="PrÃ³xima manutenÃ§Ã£o (h)" />
                        <TextInput v-model="maquinaForm.horas_manutencao" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="maquinaForm.errors.horas_manutencao" />
                    </div>
                    <div v-if="isPulverizador(maquinaForm.tipo)">
                        <InputLabel value="Consumo de ï¿½gua (L/ha)" />
                        <TextInput v-model="maquinaForm.consumo_agua_ha" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="maquinaForm.errors.consumo_agua_ha" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="ObservaÃ§Ãµes" />
                        <textarea v-model="maquinaForm.observacoes" rows="4" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="maquinaForm.errors.observacoes" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="closeMaquinaModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="rounded-full bg-emerald-700 px-4 py-2 text-sm normal-case tracking-normal hover:bg-emerald-600 focus:bg-emerald-600" :disabled="maquinaForm.processing">
                            Guardar mÃ¡quina
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="alfaiaModalOpen" max-width="2xl" @close="closeAlfaiaModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-black text-slate-900">{{ editingAlfaia ? 'Editar alfaia' : 'Nova alfaia' }}</h2>
                <p class="mt-2 text-sm text-slate-500">Associa alfaias e implementos Ã s mÃ¡quinas usadas nas operaÃ§Ãµes.</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitAlfaia">
                    <div v-if="alfaiaErrors.length" class="sm:col-span-2 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">NÃ£o foi possÃ­vel guardar a alfaia. RevÃª estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in alfaiaErrors" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Nome" />
                        <TextInput v-model="alfaiaForm.nome" class="mt-2 block w-full rounded-2xl" placeholder="Ex: Grade discos" />
                        <InputError class="mt-2" :message="alfaiaForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="alfaiaForm.tipo" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="tipo in alfaiaTipoOptions" :key="tipo" :value="tipo">{{ labelize(tipo) }}</option>
                        </select>
                        <InputError class="mt-2" :message="alfaiaForm.errors.tipo" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="MÃ¡quina associada" />
                        <select v-model="alfaiaForm.maquina_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">Sem mÃ¡quina associada</option>
                            <option v-for="maquina in maquinaOptions" :key="maquina.id" :value="String(maquina.id)">
                                {{ maquina.nome }} - {{ labelize(maquina.tipo) }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="alfaiaForm.errors.maquina_id" />
                    </div>
                    <div>
                        <InputLabel value="Comprimento (m)" />
                        <TextInput v-model="alfaiaForm.comprimento" type="number" step="0.01" min="0.01" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="alfaiaForm.errors.comprimento" />
                    </div>
                    <div>
                        <InputLabel value="Largura (m)" />
                        <TextInput v-model="alfaiaForm.largura" type="number" step="0.01" min="0.01" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="alfaiaForm.errors.largura" />
                    </div>
                    <div v-if="isPulverizador(alfaiaForm.tipo)" class="sm:col-span-2">
                        <InputLabel value="Consumo de ï¿½gua (L/ha)" />
                        <TextInput v-model="alfaiaForm.consumo_agua_ha" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="alfaiaForm.errors.consumo_agua_ha" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Estado" />
                        <select v-model="alfaiaForm.estado" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="estado in alfaiaEstadoOptions" :key="estado" :value="estado">{{ labelize(estado) }}</option>
                        </select>
                        <InputError class="mt-2" :message="alfaiaForm.errors.estado" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="DescriÃ§Ã£o" />
                        <textarea v-model="alfaiaForm.descricao" rows="3" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="alfaiaForm.errors.descricao" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="ObservaÃ§Ãµes" />
                        <textarea v-model="alfaiaForm.observacoes" rows="3" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="alfaiaForm.errors.observacoes" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="closeAlfaiaModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="rounded-full bg-slate-900 px-4 py-2 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800" :disabled="alfaiaForm.processing">
                            Guardar alfaia
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="revisaoModalOpen" max-width="2xl" @close="closeRevisaoModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-black text-slate-900">{{ editingRevisao ? 'Editar revisÃ£o' : 'Nova revisÃ£o' }}</h2>
                <p class="mt-2 text-sm text-slate-500">Regista revisÃµes, inspeÃ§Ãµes e manutenÃ§Ãµes feitas Ã s mÃ¡quinas e viaturas.</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitRevisao">
                    <div v-if="revisaoErrors.length" class="sm:col-span-2 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">NÃ£o foi possÃ­vel guardar a revisÃ£o. RevÃª estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in revisaoErrors" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="MÃ¡quina" />
                        <select v-model="revisaoForm.maquina_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">Seleciona uma mÃ¡quina</option>
                            <option v-for="maquina in maquinaOptions" :key="maquina.id" :value="String(maquina.id)">
                                {{ maquina.nome }} - {{ labelize(maquina.tipo) }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="revisaoForm.errors.maquina_id" />
                    </div>
                    <div>
                        <InputLabel value="Data da revisÃ£o" />
                        <TextInput v-model="revisaoForm.data_manutencao" type="date" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="revisaoForm.errors.data_manutencao" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="revisaoForm.tipo" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="tipo in revisaoTipoOptions" :key="tipo" :value="tipo">{{ labelize(tipo) }}</option>
                        </select>
                        <InputError class="mt-2" :message="revisaoForm.errors.tipo" />
                    </div>
                    <div>
                        <InputLabel value="Custo (â‚¬)" />
                        <TextInput v-model="revisaoForm.custo" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="revisaoForm.errors.custo" />
                    </div>
                    <div>
                        <InputLabel value="DuraÃ§Ã£o (min)" />
                        <TextInput v-model="revisaoForm.duracao_minutos" type="number" min="1" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="revisaoForm.errors.duracao_minutos" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="PrÃ³xima revisÃ£o" />
                        <TextInput v-model="revisaoForm.proxima_manutencao" type="date" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="revisaoForm.errors.proxima_manutencao" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="DescriÃ§Ã£o" />
                        <textarea v-model="revisaoForm.descricao" rows="4" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="revisaoForm.errors.descricao" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="ObservaÃ§Ãµes" />
                        <textarea v-model="revisaoForm.observacoes" rows="3" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="revisaoForm.errors.observacoes" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="closeRevisaoModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="rounded-full bg-amber-700 px-4 py-2 text-sm normal-case tracking-normal hover:bg-amber-600 focus:bg-amber-600" :disabled="revisaoForm.processing">
                            Guardar revisÃ£o
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
