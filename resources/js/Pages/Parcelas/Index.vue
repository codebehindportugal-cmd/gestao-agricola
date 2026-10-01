<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TerrenoPolygonMap from '@/Components/TerrenoPolygonMap.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    parcelas: {
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
    terrenos: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const createModalOpen = ref(false);
const editingParcela = ref(null);
const importInput = ref(null);

const filterState = reactive({
    search: props.filters.search ?? '',
    estado: props.filters.estado ?? '',
    terreno_id: props.filters.terreno_id ?? '',
});

const baseFormData = {
    terreno_id: props.filters.terreno_id ?? '',
    nome: '',
    numero_parcela: '',
    area_total: '',
    area_util: '',
    estado: 'livre',
    tipo_ocupacao: 'culturas_anuais',
    numero_arvores: '',
    compasso_linha_m: '',
    compasso_planta_m: '',
    descricao: '',
    latitude: '',
    longitude: '',
    poligono: [],
    cultura_nome: '',
    cultura_variedade: '',
    cultura_tipo: '',
    cultura_data_plantacao: '',
    cultura_estado: 'em_crescimento',
};

const createForm = useForm({ ...baseFormData });
const editForm = useForm({ ...baseFormData });
const importForm = useForm({
    ficheiro: null,
});
const createErrorMessages = computed(() => Object.values(createForm.errors));
const editErrorMessages = computed(() => Object.values(editForm.errors));

watch(
    () => [filterState.search, filterState.estado, filterState.terreno_id],
    () => {
        router.get(
            '/parcelas',
            {
                search: filterState.search || undefined,
                estado: filterState.estado || undefined,
                terreno_id: filterState.terreno_id || undefined,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            },
        );
    },
);

const openCreateModal = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.estado = 'livre';
    createForm.tipo_ocupacao = 'culturas_anuais';
    createForm.cultura_estado = 'em_crescimento';
    createForm.terreno_id = filterState.terreno_id || '';
    createModalOpen.value = true;
};

const closeCreateModal = () => {
    createModalOpen.value = false;
    createForm.clearErrors();
};

const openEditModal = (parcela) => {
    editingParcela.value = parcela;
    editForm.reset();
    editForm.clearErrors();
    editForm.terreno_id = parcela.terreno_id?.toString() ?? '';
    editForm.nome = parcela.nome ?? '';
    editForm.numero_parcela = parcela.numero_parcela ?? '';
    editForm.area_total = parcela.area_total?.toString() ?? '';
    editForm.area_util = parcela.area_util?.toString() ?? '';
    editForm.estado = parcela.estado ?? 'livre';
    editForm.tipo_ocupacao = parcela.tipo_ocupacao ?? 'culturas_anuais';
    editForm.numero_arvores = parcela.numero_arvores?.toString() ?? '';
    editForm.compasso_linha_m = parcela.compasso_linha_m?.toString() ?? '';
    editForm.compasso_planta_m = parcela.compasso_planta_m?.toString() ?? '';
    editForm.descricao = parcela.descricao ?? '';
    editForm.latitude = parcela.latitude?.toString() ?? '';
    editForm.longitude = parcela.longitude?.toString() ?? '';
    editForm.poligono = parcela.poligono ?? [];
    const cultura = parcela.culturas?.[0] ?? null;
    editForm.cultura_nome = cultura?.nome ?? '';
    editForm.cultura_variedade = cultura?.variedade ?? '';
    editForm.cultura_tipo = cultura?.tipo ?? parcela.tipo_ocupacao ?? 'culturas_anuais';
    editForm.cultura_data_plantacao = cultura?.data_plantacao ?? '';
    editForm.cultura_estado = cultura?.estado ?? 'em_crescimento';
};

const closeEditModal = () => {
    editingParcela.value = null;
    editForm.clearErrors();
};

const currentQuery = computed(() => ({
    search: filterState.search || undefined,
    estado: filterState.estado || undefined,
    terreno_id: filterState.terreno_id || undefined,
}));

const pathWithQuery = (path, query = currentQuery.value) => {
    const params = new URLSearchParams();

    Object.entries(query).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            params.set(key, value);
        }
    });

    const queryString = params.toString();

    return queryString ? `${path}?${queryString}` : path;
};

const submitCreate = () => {
    createForm.post(pathWithQuery('/parcelas'), {
        preserveScroll: true,
        onSuccess: () => closeCreateModal(),
    });
};

const submitEdit = () => {
    if (!editingParcela.value) {
        return;
    }

    editForm.patch(pathWithQuery(`/parcelas/${editingParcela.value.id}`), {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};

const deleteParcela = (parcela) => {
    if (!window.confirm(`Remover a parcela "${parcela.nome}"?`)) {
        return;
    }

    router.delete(pathWithQuery(`/parcelas/${parcela.id}`), {
        preserveScroll: true,
    });
};

const openImportPicker = () => {
    importInput.value?.click();
};

const submitImport = (event) => {
    const file = event.target.files?.[0] ?? null;

    if (!file) {
        return;
    }

    importForm.ficheiro = file;
    importForm.post('/terrenos/importar', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            importForm.reset();
            event.target.value = '';
        },
        onError: () => {
            event.target.value = '';
        },
    });
};

const formatArea = (value) => {
    const number = Number(value ?? 0);

    return new Intl.NumberFormat('pt-PT', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(number);
};

const estadoBadgeClass = (estado) => ({
    livre: 'bg-sky-50 text-sky-700',
    cultivada: 'bg-emerald-50 text-emerald-700',
    em_preparacao: 'bg-amber-50 text-amber-700',
    pousio: 'bg-slate-100 text-slate-600',
}[estado] ?? 'bg-slate-100 text-slate-600');

const estadoLabel = (estado) => ({
    livre: 'livre',
    cultivada: 'cultivada',
    em_preparacao: 'em preparação',
    pousio: 'pousio',
}[estado] ?? estado);

const culturaEstadoLabel = (estado) => ({
    planejada: 'planeada',
    em_crescimento: 'em crescimento',
    madura: 'madura',
    colhida: 'colhida',
    cancelada: 'cancelada',
}[estado] ?? estado);

const tipoOcupacaoLabel = (tipo) => ({
    culturas_anuais: 'culturas anuais',
    pomar: 'pomar',
    misto: 'misto',
    estufa: 'estufa',
    outro: 'outro',
}[tipo] ?? tipo);

const selectedCreateTerreno = computed(() =>
    props.terrenos.find((terreno) => String(terreno.id) === String(createForm.terreno_id)) ?? null,
);

const selectedEditTerreno = computed(() =>
    props.terrenos.find((terreno) => String(terreno.id) === String(editForm.terreno_id)) ?? null,
);

const updateCreatePolygonCenter = ({ latitude, longitude }) => {
    createForm.latitude = latitude?.toString() ?? '';
    createForm.longitude = longitude?.toString() ?? '';
};

const updateEditPolygonCenter = ({ latitude, longitude }) => {
    editForm.latitude = latitude?.toString() ?? '';
    editForm.longitude = longitude?.toString() ?? '';
};

const updateCreatePolygonArea = (area) => {
    createForm.area_total = area?.toString() ?? '';
};

const updateEditPolygonArea = (area) => {
    editForm.area_total = area?.toString() ?? '';
};
</script>

<template>
    <Head title="Parcelas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                            <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M4 18h16" stroke-linecap="round" />
                                <path d="M4 12h16" stroke-linecap="round" opacity="0.75" />
                                <path d="M4 6h16" stroke-linecap="round" opacity="0.5" />
                                <path d="M8 4v16" stroke-linecap="round" opacity="0.6" />
                                <path d="M16 4v16" stroke-linecap="round" opacity="0.6" />
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-verde-700">
                            Estrutura Produtiva
                        </p>
                    </div>
                    <h1 class="mt-1 text-[28px] font-bold leading-tight text-slate-900">
                        Parcelas
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600">
                        Organização das subdivisões dos terrenos com controlo de área, estado e atividade associada.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a
                        href="/terrenos/exportar"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Exportar
                    </a>
                    <SecondaryButton
                        v-if="can.create"
                        type="button"
                        class="justify-center rounded-lg px-5 py-3 text-sm "
                        :disabled="importForm.processing"
                        @click="openImportPicker"
                    >
                        Importar
                    </SecondaryButton>
                    <input
                        ref="importInput"
                        type="file"
                        accept="application/json,.json"
                        class="hidden"
                        @change="submitImport"
                    >
                    <Link
                        v-if="can.create"
                        :href="pathWithQuery('/parcelas/criar')"
                        class="justify-center rounded-lg bg-emerald-700 px-5 py-3 text-sm hover:bg-verde-800 focus:bg-verde-800 text-white font-semibold inline-flex items-center"
                    >
                        Nova parcela
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="flashSuccess"
                    class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800"
                >
                    {{ flashSuccess }}
                </div>
                <div
                    v-if="flashError || importForm.errors.ficheiro"
                    class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700"
                >
                    {{ flashError || importForm.errors.ficheiro }}
                </div>

                <section class="grid gap-4 md:grid-cols-4">
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M4 18h16" stroke-linecap="round" />
                                    <path d="M4 12h16" stroke-linecap="round" opacity="0.75" />
                                    <path d="M8 4v16" stroke-linecap="round" opacity="0.6" />
                                    <path d="M16 4v16" stroke-linecap="round" opacity="0.6" />
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-slate-500">Parcelas registadas</p>
                        </div>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ summary.total }}</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-sm font-medium text-slate-500">Cultivadas</p>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ summary.cultivadas }}</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-sm font-medium text-slate-500">Área total</p>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ formatArea(summary.area_total) }}</p>
                        <p class="mt-1 text-sm text-slate-500">ha</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-sm font-medium text-slate-500">Área útil</p>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ formatArea(summary.area_util) }}</p>
                        <p class="mt-1 text-sm text-slate-500">ha</p>
                    </article>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                    <div class="grid gap-4 md:grid-cols-[1.2fr_0.8fr_0.9fr_auto]">
                        <div>
                            <InputLabel value="Pesquisar" />
                            <TextInput
                                v-model="filterState.search"
                                class="mt-2 block w-full rounded-lg border-slate-200"
                                placeholder="Nome, código ou descrição"
                            />
                        </div>
                        <div>
                            <InputLabel value="Estado" />
                            <select
                                v-model="filterState.estado"
                                class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500"
                            >
                                <option value="">Todos</option>
                                <option v-for="estado in estadoOptions" :key="estado" :value="estado">
                                    {{ estadoLabel(estado) }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Terreno" />
                            <select
                                v-model="filterState.terreno_id"
                                class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500"
                            >
                                <option value="">Todos</option>
                                <option v-for="terreno in terrenos" :key="terreno.id" :value="String(terreno.id)">
                                    {{ terreno.nome }}
                                </option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <SecondaryButton
                                class="w-full justify-center rounded-lg px-5 py-3 text-sm "
                                @click="
                                    filterState.search = '';
                                    filterState.estado = '';
                                    filterState.terreno_id = '';
                                "
                            >
                                Limpar
                            </SecondaryButton>
                        </div>
                    </div>
                </section>

                <section class="grid gap-5 lg:grid-cols-2">
                    <article
                        v-for="parcela in parcelas.data"
                        :key="parcela.id"
                        class="rounded-xl border border-slate-200 bg-white p-6"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="break-words text-xl font-bold sm:text-2xl text-slate-900">{{ parcela.nome }}</h2>
                                    <span
                                        class="rounded-md px-3 py-1 text-xs font-semibold"
                                        :class="estadoBadgeClass(parcela.estado)"
                                    >
                                        {{ estadoLabel(parcela.estado) }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">
                                    {{ parcela.terreno_nome || 'Sem terreno associado' }}
                                    <span v-if="parcela.numero_parcela">· {{ parcela.numero_parcela }}</span>
                                </p>
                            </div>
                            <p class="shrink-0 text-3xl font-bold text-slate-900 sm:text-right">
                                {{ formatArea(parcela.area_total) }}
                                <span class="block text-sm font-medium text-slate-500">ha</span>
                            </p>
                        </div>

                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold text-slate-400">Área útil</p>
                                <p class="mt-2 text-sm text-slate-700">{{ formatArea(parcela.area_util) }} ha</p>
                            </div>
                            <div class="rounded-xl bg-emerald-50 p-4">
                                <p class="text-xs font-semibold text-emerald-500">Ocupação</p>
                                <p class="mt-2 text-sm text-slate-700">{{ parcela.tipo_ocupacao || 'culturas_anuais' }}</p>
                            </div>
                            <div class="rounded-xl p-4" :class="parcela.culturas_count ? 'bg-emerald-50' : 'bg-amber-50'">
                                <p class="text-xs font-semibold" :class="parcela.culturas_count ? 'text-emerald-500' : 'text-amber-600'">Cultura</p>
                                <p class="mt-2 text-sm text-slate-700">
                                    {{ parcela.culturas?.[0]?.label || 'Sem cultura registada' }}
                                </p>
                                <p v-if="parcela.culturas_count > 1" class="mt-1 text-xs text-slate-500">
                                    +{{ parcela.culturas_count - 1 }} outra(s)
                                </p>
                            </div>
                            <div class="rounded-xl bg-emerald-50 p-4">
                                <p class="text-xs font-semibold text-emerald-500">Árvores</p>
                                <p class="mt-2 text-sm text-slate-700">
                                    {{ parcela.numero_arvores ?? '-' }}
                                    <span v-if="parcela.compasso_linha_m || parcela.compasso_planta_m">
                                        - {{ parcela.compasso_linha_m || '-' }} x {{ parcela.compasso_planta_m || '-' }} m
                                    </span>
                                </p>
                            </div>

                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold text-slate-400">Operações</p>
                                <p class="mt-2 text-sm text-slate-700">{{ parcela.operacoes_count }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold text-slate-400">Atualização</p>
                                <p class="mt-2 text-sm text-slate-700">{{ parcela.updated_at || 'Sem registo' }}</p>
                            </div>
                        </div>

                        <div class="mt-5 rounded-xl bg-sky-50/50 p-4">
                            <p class="text-sm leading-7 text-slate-600">
                                {{ parcela.descricao || 'Sem descrição adicional para esta parcela.' }}
                            </p>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-3">

                            <Link
                                :href="pathWithQuery('/terrenos', { search: parcela.terreno_nome || undefined })"
                                class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                Ver terreno
                            </Link>
                            <Link
                                v-if="can.create"
                                :href="pathWithQuery(`/parcelas/${parcela.id}/editar`)"
                                class="rounded-lg bg-slate-900 px-4 py-2 text-sm hover:bg-slate-800 focus:bg-slate-800 text-white font-semibold inline-flex items-center"
                            >
                                Editar
                            </Link>
                            <DangerButton
                                v-if="can.delete"
                                class="rounded-lg px-4 py-2 text-sm "
                                @click="deleteParcela(parcela)"
                            >
                                Remover
                            </DangerButton>
                        </div>
                    </article>
                </section>

                <section
                    v-if="!parcelas.data.length"
                    class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm leading-7 text-slate-600"
                >
                    Nenhuma parcela encontrada com os filtros atuais.
                </section>

                <section
                    v-if="parcelas.links?.length > 3"
                    class="flex flex-wrap items-center gap-2"
                >
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="link in parcelas.links"
                        :key="`${link.label}-${link.url}`"
                        :href="link.url || undefined"
                        class="rounded-lg px-4 py-2 text-sm transition"
                        :class="link.active
 ? 'bg-emerald-700 text-white'
 : 'bg-white text-slate-600 hover:bg-slate-50'"
                        v-html="link.label"
                    />
                </section>
            </div>
        </div>

        <Modal :show="createModalOpen" max-width="2xl" @close="closeCreateModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-900">Nova parcela</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Cria uma subdivisão produtiva associada a um terreno e desenha o perímetro no mapa.
                </p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitCreate">
                    <div v-if="createErrorMessages.length" class="sm:col-span-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível guardar a parcela. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in createErrorMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Terreno" />
                        <select
                            v-model="createForm.terreno_id"
                            class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option value="">Selecionar terreno</option>
                            <option v-for="terreno in terrenos" :key="terreno.id" :value="String(terreno.id)">
                                {{ terreno.nome }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.terreno_id" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Nome" />
                        <TextInput v-model="createForm.nome" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="createForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Número da parcela" />
                        <TextInput v-model="createForm.numero_parcela" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="createForm.errors.numero_parcela" />
                    </div>
                    <div>
                        <InputLabel value="Estado" />
                        <select
                            v-model="createForm.estado"
                            class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option v-for="estado in estadoOptions" :key="estado" :value="estado">
                                {{ estadoLabel(estado) }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.estado" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Tipo de ocupação" />
                        <select
                            v-model="createForm.tipo_ocupacao"
                            class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option value="culturas_anuais">Culturas anuais</option>
                            <option value="pomar">Pomar</option>
                            <option value="misto">Misto</option>
                            <option value="estufa">Estufa</option>
                            <option value="outro">Outro</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.tipo_ocupacao" />
                    </div>
                    <div class="sm:col-span-2 rounded-xl border border-emerald-100 bg-emerald-50/70 p-4">
                        <p class="text-sm font-semibold text-slate-800">Cultura / variedade principal</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Preenche para a parcela ficar pronta para operações e colheitas.
                        </p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Cultura" />
                                <TextInput v-model="createForm.cultura_nome" class="mt-2 block w-full rounded-lg bg-white" placeholder="Ex: Pereira, Tomate, Batata" />
                                <InputError class="mt-2" :message="createForm.errors.cultura_nome" />
                            </div>
                            <div>
                                <InputLabel value="Variedade" />
                                <TextInput v-model="createForm.cultura_variedade" class="mt-2 block w-full rounded-lg bg-white" placeholder="Ex: Rocha, Cherry, Agria" />
                                <InputError class="mt-2" :message="createForm.errors.cultura_variedade" />
                            </div>
                            <div>
                                <InputLabel value="Tipo" />
                                <TextInput v-model="createForm.cultura_tipo" class="mt-2 block w-full rounded-lg bg-white" :placeholder="tipoOcupacaoLabel(createForm.tipo_ocupacao)" />
                                <InputError class="mt-2" :message="createForm.errors.cultura_tipo" />
                            </div>
                            <div>
                                <InputLabel value="Data de plantação" />
                                <TextInput v-model="createForm.cultura_data_plantacao" type="date" class="mt-2 block w-full rounded-lg bg-white" />
                                <InputError class="mt-2" :message="createForm.errors.cultura_data_plantacao" />
                            </div>
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Área total (ha)" />
                        <TextInput v-model="createForm.area_total" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="createForm.errors.area_total" />
                    </div>
                    <div>
                        <InputLabel value="Área útil (ha)" />
                        <TextInput v-model="createForm.area_util" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="createForm.errors.area_util" />
                    </div>
                    <div>
                        <InputLabel value="Latitude do centro" />
                        <TextInput v-model="createForm.latitude" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="createForm.errors.latitude" />
                    </div>
                    <div>
                        <InputLabel value="Longitude do centro" />
                        <TextInput v-model="createForm.longitude" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="createForm.errors.longitude" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Polígono da parcela" />
                        <p v-if="selectedCreateTerreno?.poligono?.length" class="mt-2 text-xs leading-6 text-slate-500">
                            O contorno tracejado mostra o terreno selecionado como referência visual.
                        </p>
                        <div class="mt-2">
                            <TerrenoPolygonMap
                                :polygon="createForm.poligono"
                                :context-polygon="selectedCreateTerreno?.poligono ?? []"
                                :latitude="createForm.latitude"
                                :longitude="createForm.longitude"
                                :context-latitude="selectedCreateTerreno?.latitude"
                                :context-longitude="selectedCreateTerreno?.longitude"
                                @update:polygon="(polygon) => createForm.poligono = polygon"
                                @update:center="updateCreatePolygonCenter"
                                @update:area="updateCreatePolygonArea"
                            />
                        </div>
                        <InputError class="mt-2" :message="createForm.errors.poligono" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Descrição" />
                        <textarea
                            v-model="createForm.descricao"
                            class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500"
                            rows="4"
                        />
                        <InputError class="mt-2" :message="createForm.errors.descricao" />
                    </div>

                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-lg px-4 py-2 text-sm " @click="closeCreateModal">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton class="rounded-lg bg-emerald-700 px-4 py-2 text-sm hover:bg-verde-800 focus:bg-verde-800 text-white font-semibold inline-flex items-center" :disabled="createForm.processing">
                            Guardar parcela
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="!!editingParcela" max-width="2xl" @close="closeEditModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-900">Editar parcela</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Atualiza os dados de {{ editingParcela?.nome }} e ajusta o perímetro no mapa.
                </p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitEdit">
                    <div v-if="editErrorMessages.length" class="sm:col-span-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível atualizar a parcela. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in editErrorMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Terreno" />
                        <select
                            v-model="editForm.terreno_id"
                            class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option value="">Selecionar terreno</option>
                            <option v-for="terreno in terrenos" :key="terreno.id" :value="String(terreno.id)">
                                {{ terreno.nome }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.terreno_id" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Nome" />
                        <TextInput v-model="editForm.nome" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="editForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Número da parcela" />
                        <TextInput v-model="editForm.numero_parcela" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="editForm.errors.numero_parcela" />
                    </div>
                    <div>
                        <InputLabel value="Estado" />
                        <select
                            v-model="editForm.estado"
                            class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option v-for="estado in estadoOptions" :key="estado" :value="estado">
                                {{ estadoLabel(estado) }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.estado" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Tipo de ocupação" />
                        <select
                            v-model="editForm.tipo_ocupacao"
                            class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option value="culturas_anuais">Culturas anuais</option>
                            <option value="pomar">Pomar</option>
                            <option value="misto">Misto</option>
                            <option value="estufa">Estufa</option>
                            <option value="outro">Outro</option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.tipo_ocupacao" />
                    </div>
                    <div class="sm:col-span-2 rounded-xl border border-emerald-100 bg-emerald-50/70 p-4">
                        <p class="text-sm font-semibold text-slate-800">Cultura / variedade principal</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Atualiza a cultura usada por defeito nas operações e colheitas desta parcela.
                        </p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Cultura" />
                                <TextInput v-model="editForm.cultura_nome" class="mt-2 block w-full rounded-lg bg-white" placeholder="Ex: Pereira, Tomate, Batata" />
                                <InputError class="mt-2" :message="editForm.errors.cultura_nome" />
                            </div>
                            <div>
                                <InputLabel value="Variedade" />
                                <TextInput v-model="editForm.cultura_variedade" class="mt-2 block w-full rounded-lg bg-white" placeholder="Ex: Rocha, Cherry, Agria" />
                                <InputError class="mt-2" :message="editForm.errors.cultura_variedade" />
                            </div>
                            <div>
                                <InputLabel value="Tipo" />
                                <TextInput v-model="editForm.cultura_tipo" class="mt-2 block w-full rounded-lg bg-white" :placeholder="tipoOcupacaoLabel(editForm.tipo_ocupacao)" />
                                <InputError class="mt-2" :message="editForm.errors.cultura_tipo" />
                            </div>
                            <div>
                                <InputLabel value="Estado da cultura" />
                                <select v-model="editForm.cultura_estado" class="mt-2 block w-full rounded-lg border-slate-200 bg-white focus:border-emerald-500 focus:ring-emerald-500">
                                    <option value="planejada">{{ culturaEstadoLabel('planejada') }}</option>
                                    <option value="em_crescimento">{{ culturaEstadoLabel('em_crescimento') }}</option>
                                    <option value="madura">{{ culturaEstadoLabel('madura') }}</option>
                                    <option value="colhida">{{ culturaEstadoLabel('colhida') }}</option>
                                    <option value="cancelada">{{ culturaEstadoLabel('cancelada') }}</option>
                                </select>
                                <InputError class="mt-2" :message="editForm.errors.cultura_estado" />
                            </div>
                            <div>
                                <InputLabel value="Data de plantação" />
                                <TextInput v-model="editForm.cultura_data_plantacao" type="date" class="mt-2 block w-full rounded-lg bg-white" />
                                <InputError class="mt-2" :message="editForm.errors.cultura_data_plantacao" />
                            </div>
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Área total (ha)" />
                        <TextInput v-model="editForm.area_total" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="editForm.errors.area_total" />
                    </div>
                    <div>
                        <InputLabel value="Área útil (ha)" />
                        <TextInput v-model="editForm.area_util" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="editForm.errors.area_util" />
                    </div>
                    <div>
                        <InputLabel value="Latitude do centro" />
                        <TextInput v-model="editForm.latitude" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="editForm.errors.latitude" />
                    </div>
                    <div>
                        <InputLabel value="Longitude do centro" />
                        <TextInput v-model="editForm.longitude" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="editForm.errors.longitude" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Polígono da parcela" />
                        <p v-if="selectedEditTerreno?.poligono?.length" class="mt-2 text-xs leading-6 text-slate-500">
                            O contorno tracejado mostra o terreno selecionado como referência visual.
                        </p>
                        <div class="mt-2">
                            <TerrenoPolygonMap
                                :polygon="editForm.poligono"
                                :context-polygon="selectedEditTerreno?.poligono ?? []"
                                :latitude="editForm.latitude"
                                :longitude="editForm.longitude"
                                :context-latitude="selectedEditTerreno?.latitude"
                                :context-longitude="selectedEditTerreno?.longitude"
                                @update:polygon="(polygon) => editForm.poligono = polygon"
                                @update:center="updateEditPolygonCenter"
                                @update:area="updateEditPolygonArea"
                            />
                        </div>
                        <InputError class="mt-2" :message="editForm.errors.poligono" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Descrição" />
                        <textarea
                            v-model="editForm.descricao"
                            class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500"
                            rows="4"
                        />
                        <InputError class="mt-2" :message="editForm.errors.descricao" />
                    </div>

                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-lg px-4 py-2 text-sm " @click="closeEditModal">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton class="rounded-lg bg-slate-900 px-4 py-2 text-sm hover:bg-slate-800 focus:bg-slate-800 text-white font-semibold inline-flex items-center" :disabled="editForm.processing">
                            Atualizar parcela
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>




