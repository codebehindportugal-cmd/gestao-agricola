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
    culturas: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    summary: {
        type: Object,
        required: true,
    },
    can: {
        type: Object,
        required: true,
    },
    estadoOptions: {
        type: Array,
        default: () => [],
    },
    cicloOptions: {
        type: Array,
        default: () => [],
    },
    grupoOptions: {
        type: Array,
        default: () => [],
    },
    tipoOptions: {
        type: Array,
        default: () => [],
    },
    variedadeOptions: {
        type: Array,
        default: () => [],
    },
    parcelas: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const createModalOpen = ref(false);
const editingCultura = ref(null);

const filterState = reactive({
    search: props.filters.search ?? '',
    estado: props.filters.estado ?? '',
    parcela_id: props.filters.parcela_id ?? '',
    grupo_cultura: props.filters.grupo_cultura ?? '',
    tipo: props.filters.tipo ?? '',
});

const baseFormData = {
    parcela_id: props.filters.parcela_id ?? '',
    nome: '',
    grupo_cultura: 'outro',
    ciclo_produtivo: 'anual',
    ano_inicio_producao: '',
    data_fim_producao: '',
    tipo: '',
    variedade: '',
    data_plantacao: '',
    previsao_colheita: '',
    ciclo_dias: '',
    quantidade_esperada: '',
    unidade_medida: 'kg',
    estado: 'planejada',
    observacoes: '',
};

const createForm = useForm({ ...baseFormData });
const editForm = useForm({ ...baseFormData });

const createErrorMessages = computed(() => Object.values(createForm.errors));
const editErrorMessages = computed(() => Object.values(editForm.errors));
const gruposPermanentes = ['arvores_fruto', 'olival', 'vinha', 'florestais'];

watch(
    () => [filterState.search, filterState.estado, filterState.parcela_id, filterState.grupo_cultura, filterState.tipo],
    () => {
        router.get(
            route('app.culturas.index'),
            {
                search: filterState.search || undefined,
                estado: filterState.estado || undefined,
                parcela_id: filterState.parcela_id || undefined,
                grupo_cultura: filterState.grupo_cultura || undefined,
                tipo: filterState.tipo || undefined,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            },
        );
    },
);

watch(
    () => createForm.grupo_cultura,
    (grupo) => {
        if (gruposPermanentes.includes(grupo)) {
            createForm.ciclo_produtivo = 'permanente';
        }
    },
);

watch(
    () => editForm.grupo_cultura,
    (grupo) => {
        if (gruposPermanentes.includes(grupo)) {
            editForm.ciclo_produtivo = 'permanente';
        }
    },
);

const currentQuery = computed(() => ({
    search: filterState.search || undefined,
    estado: filterState.estado || undefined,
    parcela_id: filterState.parcela_id || undefined,
    grupo_cultura: filterState.grupo_cultura || undefined,
    tipo: filterState.tipo || undefined,
}));

const openCreateModal = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.estado = 'planejada';
    createForm.grupo_cultura = 'outro';
    createForm.ciclo_produtivo = 'anual';
    createForm.ano_inicio_producao = '';
    createForm.data_fim_producao = '';
    createForm.unidade_medida = 'kg';
    createForm.parcela_id = filterState.parcela_id || '';
    createModalOpen.value = true;
};

const closeCreateModal = () => {
    createModalOpen.value = false;
    createForm.clearErrors();
};

const openEditModal = (cultura) => {
    editingCultura.value = cultura;
    editForm.reset();
    editForm.clearErrors();
    editForm.parcela_id = cultura.parcela_id?.toString() ?? '';
    editForm.nome = cultura.nome ?? '';
    editForm.grupo_cultura = cultura.grupo_cultura ?? 'outro';
    editForm.ciclo_produtivo = cultura.ciclo_produtivo ?? 'anual';
    editForm.ano_inicio_producao = cultura.ano_inicio_producao?.toString() ?? '';
    editForm.data_fim_producao = cultura.data_fim_producao ?? '';
    editForm.tipo = cultura.tipo ?? '';
    editForm.variedade = cultura.variedade ?? '';
    editForm.data_plantacao = cultura.data_plantacao ?? '';
    editForm.previsao_colheita = cultura.previsao_colheita ?? '';
    editForm.ciclo_dias = cultura.ciclo_dias?.toString() ?? '';
    editForm.quantidade_esperada = cultura.quantidade_esperada?.toString() ?? '';
    editForm.unidade_medida = cultura.unidade_medida ?? 'kg';
    editForm.estado = cultura.estado ?? 'planejada';
    editForm.observacoes = cultura.observacoes ?? '';
};

const closeEditModal = () => {
    editingCultura.value = null;
    editForm.clearErrors();
};

const submitCreate = () => {
    createForm.post(route('app.culturas.store', currentQuery.value), {
        preserveScroll: true,
        onSuccess: () => closeCreateModal(),
    });
};

const submitEdit = () => {
    if (!editingCultura.value) {
        return;
    }

    editForm.patch(route('app.culturas.update', { cultura: editingCultura.value.id, ...currentQuery.value }), {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};

const deleteCultura = (cultura) => {
    if (!window.confirm(`Remover a cultura "${cultura.nome}"?`)) {
        return;
    }

    router.delete(route('app.culturas.destroy', { cultura: cultura.id, ...currentQuery.value }), {
        preserveScroll: true,
    });
};

const formatNumber = (value) => {
    const number = Number(value ?? 0);

    return new Intl.NumberFormat('pt-PT', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(number);
};

const estadoBadgeClass = (estado) => ({
    planejada: 'bg-sky-50 text-sky-700',
    em_crescimento: 'bg-emerald-50 text-emerald-700',
    madura: 'bg-amber-50 text-amber-700',
    colhida: 'bg-lime-50 text-lime-700',
    cancelada: 'bg-slate-100 text-slate-600',
}[estado] ?? 'bg-slate-100 text-slate-600');

const estadoLabel = (estado) => ({
    planejada: 'planeada',
    em_crescimento: 'em crescimento',
    madura: 'madura',
    colhida: 'colhida',
    cancelada: 'cancelada',
}[estado] ?? estado);
</script>

<template>
    <Head title="Culturas" />

    <datalist id="cultura-tipos">
        <option v-for="tipo in tipoOptions" :key="tipo" :value="tipo" />
    </datalist>

    <datalist id="cultura-variedades">
        <option v-for="variedade in variedadeOptions" :key="variedade" :value="variedade" />
    </datalist>

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-700">
                        Produção
                    </p>
                    <h1 class="mt-2 text-3xl font-black text-slate-900">
                        Culturas
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600">
                        Registo e manutenção das culturas por parcela, com ciclo, previsão e estado produtivo.
                    </p>
                </div>

                <PrimaryButton
                    v-if="can.create"
                    class="justify-center rounded-full bg-emerald-700 px-5 py-3 text-sm normal-case tracking-normal hover:bg-emerald-600 focus:bg-emerald-600"
                    @click="openCreateModal"
                >
                    Nova cultura
                </PrimaryButton>
            </div>
        </template>

        <div class="bg-[radial-gradient(circle_at_top_right,_rgba(132,204,22,0.16),_transparent_28%),linear-gradient(180deg,_#f8fafc_0%,_#eef6f1_100%)] py-10">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="flashSuccess"
                    class="rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800"
                >
                    {{ flashSuccess }}
                </div>

                <section class="grid gap-4 md:grid-cols-5">
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Culturas registadas</p>
                        <p class="mt-3 text-4xl font-black text-slate-900">{{ summary.total }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Ativas</p>
                        <p class="mt-3 text-4xl font-black text-emerald-700">{{ summary.ativas }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Permanentes</p>
                        <p class="mt-3 text-4xl font-black text-amber-700">{{ summary.permanentes }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Colhidas</p>
                        <p class="mt-3 text-4xl font-black text-lime-700">{{ summary.colhidas }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Quantidade esperada</p>
                        <p class="mt-3 text-4xl font-black text-slate-900">{{ formatNumber(summary.quantidade_esperada) }}</p>
                    </article>
                </section>

                <section class="rounded-[32px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                    <div class="grid gap-4 md:grid-cols-[1fr_0.8fr_0.85fr_0.85fr_0.85fr_auto]">
                        <div>
                            <InputLabel value="Pesquisar" />
                            <TextInput
                                v-model="filterState.search"
                                class="mt-2 block w-full rounded-2xl border-slate-200"
                                placeholder="Nome, tipo ou variedade"
                            />
                        </div>
                        <div>
                            <InputLabel value="Estado" />
                            <select
                                v-model="filterState.estado"
                                class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            >
                                <option value="">Todos</option>
                                <option v-for="estado in estadoOptions" :key="estado" :value="estado">
                                        {{ estadoLabel(estado) }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Parcela" />
                            <select
                                v-model="filterState.parcela_id"
                                class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            >
                                <option value="">Todas</option>
                                <option v-for="parcela in parcelas" :key="parcela.id" :value="String(parcela.id)">
                                    {{ parcela.nome }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Grupo" />
                            <select
                                v-model="filterState.grupo_cultura"
                                class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            >
                                <option value="">Todos</option>
                                <option v-for="grupo in grupoOptions" :key="grupo.value" :value="grupo.value">
                                    {{ grupo.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Tipo" />
                            <TextInput
                                v-model="filterState.tipo"
                                class="mt-2 block w-full rounded-2xl border-slate-200"
                                placeholder="Ex: cereal"
                            />
                        </div>
                        <div class="flex items-end">
                            <SecondaryButton
                                class="w-full justify-center rounded-full px-5 py-3 text-sm normal-case tracking-normal"
                                @click="
                                    filterState.search = '';
                                    filterState.estado = '';
                                    filterState.parcela_id = '';
                                    filterState.grupo_cultura = '';
                                    filterState.tipo = '';
                                "
                            >
                                Limpar
                            </SecondaryButton>
                        </div>
                    </div>
                </section>

                <section class="grid gap-5 lg:grid-cols-2">
                    <article
                        v-for="cultura in culturas.data"
                        :key="cultura.id"
                        class="rounded-[32px] border border-white/80 bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="text-2xl font-black text-slate-900">{{ cultura.nome }}</h2>
                                    <span
                                        class="rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="estadoBadgeClass(cultura.estado)"
                                    >
                                        {{ estadoLabel(cultura.estado) }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">
                                    {{ cultura.terreno_nome || 'Sem terreno' }} · {{ cultura.parcela_nome || 'Sem parcela' }}
                                </p>
                                <p class="mt-2 inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                                    {{ cultura.ciclo_produtivo === 'permanente' ? 'Cultura permanente' : 'Cultura anual' }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600">{{ cultura.grupo_cultura || 'outro' }}</p>
                                <p class="text-lg font-black text-slate-900">{{ cultura.tipo }}</p>
                                <p class="text-sm text-slate-500">{{ cultura.variedade || 'Sem variedade' }}</p>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Plantação</p>
                                <p class="mt-2 text-sm text-slate-700">{{ cultura.data_plantacao || 'Sem data' }}</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Previsão</p>
                                <p class="mt-2 text-sm text-slate-700">{{ cultura.previsao_colheita || 'Sem previsão' }}</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Ciclo</p>
                                <p class="mt-2 text-sm text-slate-700">
                                    {{ cultura.ciclo_produtivo === 'permanente' ? 'Permanente' : `${cultura.ciclo_dias || '-'} dias` }}
                                </p>
                                <p v-if="cultura.ano_inicio_producao" class="mt-1 text-xs text-slate-500">
                                    Início de produção: {{ cultura.ano_inicio_producao }}
                                </p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Quantidade esperada</p>
                                <p class="mt-2 text-sm text-slate-700">
                                    {{ formatNumber(cultura.quantidade_esperada) }} {{ cultura.unidade_medida || '' }}
                                </p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Operações</p>
                                <p class="mt-2 text-sm text-slate-700">{{ cultura.operacoes_count }}</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Colheitas</p>
                                <p class="mt-2 text-sm text-slate-700">{{ cultura.colheitas_count }}</p>
                            </div>
                        </div>

                        <div class="mt-5 rounded-3xl bg-lime-50/50 p-4">
                            <p class="text-sm leading-7 text-slate-600">
                                {{ cultura.observacoes || 'Sem observações adicionais para esta cultura.' }}
                            </p>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link
                                :href="route('app.operacoes.index', { parcela_id: cultura.parcela_id, tipo: undefined, search: cultura.nome })"
                                class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                Ver operações
                            </Link>
                            <Link
                                :href="route('app.parcelas.index', { parcela_id: cultura.parcela_id })"
                                class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                Ver parcela
                            </Link>
                            <PrimaryButton
                                v-if="can.create"
                                class="rounded-full bg-slate-900 px-4 py-2 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800"
                                @click="openEditModal(cultura)"
                            >
                                Editar
                            </PrimaryButton>
                            <DangerButton
                                v-if="can.delete"
                                class="rounded-full px-4 py-2 text-sm normal-case tracking-normal"
                                @click="deleteCultura(cultura)"
                            >
                                Remover
                            </DangerButton>
                        </div>
                    </article>
                </section>

                <section
                    v-if="!culturas.data.length"
                    class="rounded-[32px] border border-dashed border-slate-300 bg-white/70 px-6 py-12 text-center text-sm leading-7 text-slate-600"
                >
                    Nenhuma cultura encontrada com os filtros atuais.
                </section>

                <section
                    v-if="culturas.links?.length > 3"
                    class="flex flex-wrap items-center gap-2"
                >
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="link in culturas.links"
                        :key="`${link.label}-${link.url}`"
                        :href="link.url || undefined"
                        class="rounded-full px-4 py-2 text-sm transition"
                        :class="link.active
                            ? 'bg-emerald-700 text-white'
                            : 'bg-white text-slate-600 shadow hover:bg-slate-50'"
                        v-html="link.label"
                    />
                </section>
            </div>
        </div>

        <Modal :show="createModalOpen" max-width="2xl" @close="closeCreateModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-black text-slate-900">Nova cultura</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Regista uma cultura numa parcela existente.
                </p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitCreate">
                    <div
                        v-if="createErrorMessages.length"
                        class="sm:col-span-2 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
                    >
                        <p class="font-semibold">Não foi possível guardar a cultura. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in createErrorMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Parcela" />
                        <select
                            v-model="createForm.parcela_id"
                            class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option value="">Selecionar parcela</option>
                            <option v-for="parcela in parcelas" :key="parcela.id" :value="String(parcela.id)">
                                {{ parcela.nome }} - {{ parcela.terreno_nome }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.parcela_id" />
                    </div>
                    <div>
                        <InputLabel value="Nome" />
                        <TextInput v-model="createForm.nome" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="createForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Grupo de cultura" />
                        <select
                            v-model="createForm.grupo_cultura"
                            class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option v-for="grupo in grupoOptions" :key="grupo.value" :value="grupo.value">
                                {{ grupo.label }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.grupo_cultura" />
                    </div>
                    <div>
                        <InputLabel value="Ciclo produtivo" />
                        <select
                            v-model="createForm.ciclo_produtivo"
                            class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option v-for="ciclo in cicloOptions" :key="ciclo.value" :value="ciclo.value">
                                {{ ciclo.label }}
                            </option>
                        </select>
                        <p class="mt-2 text-xs leading-5 text-slate-500">
                            Nas culturas permanentes, cria-se a cultura uma vez e registam-se campanhas/colheitas todos os anos.
                        </p>
                        <InputError class="mt-2" :message="createForm.errors.ciclo_produtivo" />
                    </div>
                    <div>
                        <InputLabel value="Tipo / especie" />
                        <TextInput v-model="createForm.tipo" list="cultura-tipos" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="createForm.errors.tipo" />
                    </div>
                    <div>
                        <InputLabel value="Variedade" />
                        <TextInput v-model="createForm.variedade" list="cultura-variedades" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="createForm.errors.variedade" />
                    </div>
                    <div>
                        <InputLabel value="Estado" />
                        <select
                            v-model="createForm.estado"
                            class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option v-for="estado in estadoOptions" :key="estado" :value="estado">
                                {{ estadoLabel(estado) }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.estado" />
                    </div>
                    <div>
                        <InputLabel value="Data de plantação" />
                        <TextInput v-model="createForm.data_plantacao" type="date" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="createForm.errors.data_plantacao" />
                    </div>
                    <div v-if="createForm.ciclo_produtivo === 'permanente'">
                        <InputLabel value="Ano inicio producao" />
                        <TextInput v-model="createForm.ano_inicio_producao" class="mt-2 block w-full rounded-2xl" placeholder="Ex: 2026" />
                        <InputError class="mt-2" :message="createForm.errors.ano_inicio_producao" />
                    </div>
                    <div v-if="createForm.ciclo_produtivo === 'permanente'">
                        <InputLabel value="Fim de producao" />
                        <TextInput v-model="createForm.data_fim_producao" type="date" class="mt-2 block w-full rounded-2xl" />
                        <p class="mt-2 text-xs text-slate-500">Opcional. Usa apenas quando o pomar/vinha/olival deixar de produzir.</p>
                        <InputError class="mt-2" :message="createForm.errors.data_fim_producao" />
                    </div>
                    <div>
                        <InputLabel value="Previsão de colheita" />
                        <TextInput v-model="createForm.previsao_colheita" type="date" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="createForm.errors.previsao_colheita" />
                    </div>
                    <div>
                        <InputLabel value="Ciclo (dias)" />
                        <TextInput v-model="createForm.ciclo_dias" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="createForm.errors.ciclo_dias" />
                    </div>
                    <div>
                        <InputLabel value="Quantidade esperada" />
                        <TextInput v-model="createForm.quantidade_esperada" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="createForm.errors.quantidade_esperada" />
                    </div>
                    <div>
                        <InputLabel value="Unidade" />
                        <TextInput v-model="createForm.unidade_medida" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="createForm.errors.unidade_medida" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Observações" />
                        <textarea
                            v-model="createForm.observacoes"
                            class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            rows="4"
                        />
                        <InputError class="mt-2" :message="createForm.errors.observacoes" />
                    </div>

                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="closeCreateModal">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton class="rounded-full bg-emerald-700 px-4 py-2 text-sm normal-case tracking-normal hover:bg-emerald-600 focus:bg-emerald-600" :disabled="createForm.processing">
                            Guardar cultura
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="!!editingCultura" max-width="2xl" @close="closeEditModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-black text-slate-900">Editar cultura</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Atualiza os dados de {{ editingCultura?.nome }}.
                </p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitEdit">
                    <div
                        v-if="editErrorMessages.length"
                        class="sm:col-span-2 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
                    >
                        <p class="font-semibold">Não foi possível atualizar a cultura. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in editErrorMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Parcela" />
                        <select
                            v-model="editForm.parcela_id"
                            class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option value="">Selecionar parcela</option>
                            <option v-for="parcela in parcelas" :key="parcela.id" :value="String(parcela.id)">
                                {{ parcela.nome }} - {{ parcela.terreno_nome }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.parcela_id" />
                    </div>
                    <div>
                        <InputLabel value="Nome" />
                        <TextInput v-model="editForm.nome" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="editForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Grupo de cultura" />
                        <select
                            v-model="editForm.grupo_cultura"
                            class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option v-for="grupo in grupoOptions" :key="grupo.value" :value="grupo.value">
                                {{ grupo.label }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.grupo_cultura" />
                    </div>
                    <div>
                        <InputLabel value="Ciclo produtivo" />
                        <select
                            v-model="editForm.ciclo_produtivo"
                            class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option v-for="ciclo in cicloOptions" :key="ciclo.value" :value="ciclo.value">
                                {{ ciclo.label }}
                            </option>
                        </select>
                        <p class="mt-2 text-xs leading-5 text-slate-500">
                            Para arvores de fruto, mantem a cultura como permanente e regista uma campanha/colheita por ano.
                        </p>
                        <InputError class="mt-2" :message="editForm.errors.ciclo_produtivo" />
                    </div>
                    <div>
                        <InputLabel value="Tipo / especie" />
                        <TextInput v-model="editForm.tipo" list="cultura-tipos" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="editForm.errors.tipo" />
                    </div>
                    <div>
                        <InputLabel value="Variedade" />
                        <TextInput v-model="editForm.variedade" list="cultura-variedades" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="editForm.errors.variedade" />
                    </div>
                    <div>
                        <InputLabel value="Estado" />
                        <select
                            v-model="editForm.estado"
                            class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option v-for="estado in estadoOptions" :key="estado" :value="estado">
                                {{ estadoLabel(estado) }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.estado" />
                    </div>
                    <div>
                        <InputLabel value="Data de plantação" />
                        <TextInput v-model="editForm.data_plantacao" type="date" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="editForm.errors.data_plantacao" />
                    </div>
                    <div v-if="editForm.ciclo_produtivo === 'permanente'">
                        <InputLabel value="Ano inicio producao" />
                        <TextInput v-model="editForm.ano_inicio_producao" class="mt-2 block w-full rounded-2xl" placeholder="Ex: 2026" />
                        <InputError class="mt-2" :message="editForm.errors.ano_inicio_producao" />
                    </div>
                    <div v-if="editForm.ciclo_produtivo === 'permanente'">
                        <InputLabel value="Fim de producao" />
                        <TextInput v-model="editForm.data_fim_producao" type="date" class="mt-2 block w-full rounded-2xl" />
                        <p class="mt-2 text-xs text-slate-500">Opcional. Usa apenas quando o pomar/vinha/olival deixar de produzir.</p>
                        <InputError class="mt-2" :message="editForm.errors.data_fim_producao" />
                    </div>
                    <div>
                        <InputLabel value="Previsão de colheita" />
                        <TextInput v-model="editForm.previsao_colheita" type="date" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="editForm.errors.previsao_colheita" />
                    </div>
                    <div>
                        <InputLabel value="Ciclo (dias)" />
                        <TextInput v-model="editForm.ciclo_dias" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="editForm.errors.ciclo_dias" />
                    </div>
                    <div>
                        <InputLabel value="Quantidade esperada" />
                        <TextInput v-model="editForm.quantidade_esperada" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="editForm.errors.quantidade_esperada" />
                    </div>
                    <div>
                        <InputLabel value="Unidade" />
                        <TextInput v-model="editForm.unidade_medida" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="editForm.errors.unidade_medida" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Observações" />
                        <textarea
                            v-model="editForm.observacoes"
                            class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            rows="4"
                        />
                        <InputError class="mt-2" :message="editForm.errors.observacoes" />
                    </div>

                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="closeEditModal">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton class="rounded-full bg-slate-900 px-4 py-2 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800" :disabled="editForm.processing">
                            Atualizar cultura
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
