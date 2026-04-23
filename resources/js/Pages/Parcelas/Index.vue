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
const createModalOpen = ref(false);
const editingParcela = ref(null);

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
};

const createForm = useForm({ ...baseFormData });
const editForm = useForm({ ...baseFormData });
const createErrorMessages = computed(() => Object.values(createForm.errors));
const editErrorMessages = computed(() => Object.values(editForm.errors));

watch(
    () => [filterState.search, filterState.estado, filterState.terreno_id],
    () => {
        router.get(
            route('app.parcelas.index'),
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

const submitCreate = () => {
    createForm.post(route('app.parcelas.store', currentQuery.value), {
        preserveScroll: true,
        onSuccess: () => closeCreateModal(),
    });
};

const submitEdit = () => {
    if (!editingParcela.value) {
        return;
    }

    editForm.patch(route('app.parcelas.update', { parcela: editingParcela.value.id, ...currentQuery.value }), {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};

const deleteParcela = (parcela) => {
    if (!window.confirm(`Remover a parcela "${parcela.nome}"?`)) {
        return;
    }

    router.delete(route('app.parcelas.destroy', { parcela: parcela.id, ...currentQuery.value }), {
        preserveScroll: true,
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
    em_preparacao: 'em preparaÃ§Ã£o',
    pousio: 'pousio',
}[estado] ?? estado);

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
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-100 text-sky-700">
                            <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M4 18h16" stroke-linecap="round" />
                                <path d="M4 12h16" stroke-linecap="round" opacity="0.75" />
                                <path d="M4 6h16" stroke-linecap="round" opacity="0.5" />
                                <path d="M8 4v16" stroke-linecap="round" opacity="0.6" />
                                <path d="M16 4v16" stroke-linecap="round" opacity="0.6" />
                            </svg>
                        </div>
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-700">
                            Estrutura Produtiva
                        </p>
                    </div>
                    <h1 class="mt-2 text-3xl font-black text-slate-900">
                        Parcelas
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600">
                        OrganizaÃ§Ã£o das subdivisÃµes dos terrenos com controlo de Ã¡rea, estado e atividade associada.
                    </p>
                </div>

                <Link
                    v-if="can.create"
                    :href="route('app.parcelas.create', currentQuery)"
                    class="justify-center rounded-full bg-emerald-700 px-5 py-3 text-sm normal-case tracking-normal hover:bg-emerald-600 focus:bg-emerald-600"
                >
                    Nova parcela
                </Link>
            </div>
        </template>

        <div class="bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.14),_transparent_30%),linear-gradient(180deg,_#f8fafc_0%,_#eef6f1_100%)] py-10">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="flashSuccess"
                    class="rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800"
                >
                    {{ flashSuccess }}
                </div>

                <section class="grid gap-4 md:grid-cols-4">
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-sky-100 text-sky-700">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M4 18h16" stroke-linecap="round" />
                                    <path d="M4 12h16" stroke-linecap="round" opacity="0.75" />
                                    <path d="M8 4v16" stroke-linecap="round" opacity="0.6" />
                                    <path d="M16 4v16" stroke-linecap="round" opacity="0.6" />
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-slate-500">Parcelas registadas</p>
                        </div>
                        <p class="mt-3 text-4xl font-black text-slate-900">{{ summary.total }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Cultivadas</p>
                        <p class="mt-3 text-4xl font-black text-emerald-700">{{ summary.cultivadas }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Ãrea total</p>
                        <p class="mt-3 text-4xl font-black text-slate-900">{{ formatArea(summary.area_total) }}</p>
                        <p class="mt-1 text-sm text-slate-500">ha</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Ãrea Ãºtil</p>
                        <p class="mt-3 text-4xl font-black text-slate-900">{{ formatArea(summary.area_util) }}</p>
                        <p class="mt-1 text-sm text-slate-500">ha</p>
                    </article>
                </section>

                <section class="rounded-[32px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                    <div class="grid gap-4 md:grid-cols-[1.2fr_0.8fr_0.9fr_auto]">
                        <div>
                            <InputLabel value="Pesquisar" />
                            <TextInput
                                v-model="filterState.search"
                                class="mt-2 block w-full rounded-2xl border-slate-200"
                                placeholder="Nome, cÃ³digo ou descriÃ§Ã£o"
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
                            <InputLabel value="Terreno" />
                            <select
                                v-model="filterState.terreno_id"
                                class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            >
                                <option value="">Todos</option>
                                <option v-for="terreno in terrenos" :key="terreno.id" :value="String(terreno.id)">
                                    {{ terreno.nome }}
                                </option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <SecondaryButton
                                class="w-full justify-center rounded-full px-5 py-3 text-sm normal-case tracking-normal"
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
                        class="rounded-[32px] border border-white/80 bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="text-2xl font-black text-slate-900">{{ parcela.nome }}</h2>
                                    <span
                                        class="rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="estadoBadgeClass(parcela.estado)"
                                    >
                                        {{ estadoLabel(parcela.estado) }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">
                                    {{ parcela.terreno_nome || 'Sem terreno associado' }}
                                    <span v-if="parcela.numero_parcela">Â· {{ parcela.numero_parcela }}</span>
                                </p>
                            </div>
                            <p class="text-right text-3xl font-black text-slate-900">
                                {{ formatArea(parcela.area_total) }}
                                <span class="block text-sm font-medium text-slate-500">ha</span>
                            </p>
                        </div>

                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Ãrea Ãºtil</p>
                                <p class="mt-2 text-sm text-slate-700">{{ formatArea(parcela.area_util) }} ha</p>
                            </div>
                            <div class="rounded-3xl bg-emerald-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-emerald-500">Ocupacao</p>
                                <p class="mt-2 text-sm text-slate-700">{{ parcela.tipo_ocupacao || 'culturas_anuais' }}</p>
                            </div>
                            <div class="rounded-3xl bg-emerald-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-emerald-500">Arvores</p>
                                <p class="mt-2 text-sm text-slate-700">
                                    {{ parcela.numero_arvores ?? '-' }}
                                    <span v-if="parcela.compasso_linha_m || parcela.compasso_planta_m">
                                        - {{ parcela.compasso_linha_m || '-' }} x {{ parcela.compasso_planta_m || '-' }} m
                                    </span>
                                </p>
                            </div>

                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">OperaÃ§Ãµes</p>
                                <p class="mt-2 text-sm text-slate-700">{{ parcela.operacoes_count }}</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">AtualizaÃ§Ã£o</p>
                                <p class="mt-2 text-sm text-slate-700">{{ parcela.updated_at || 'Sem registo' }}</p>
                            </div>
                        </div>

                        <div class="mt-5 rounded-3xl bg-sky-50/50 p-4">
                            <p class="text-sm leading-7 text-slate-600">
                                {{ parcela.descricao || 'Sem descriÃ§Ã£o adicional para esta parcela.' }}
                            </p>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-3">

                            <Link
                                :href="route('app.terrenos.index', { search: parcela.terreno_nome || undefined })"
                                class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                Ver terreno
                            </Link>
                            <Link
                                v-if="can.create"
                                :href="route('app.parcelas.edit', { parcela: parcela.id, ...currentQuery })"
                                class="rounded-full bg-slate-900 px-4 py-2 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800"
                            >
                                Editar
                            </Link>
                            <DangerButton
                                v-if="can.delete"
                                class="rounded-full px-4 py-2 text-sm normal-case tracking-normal"
                                @click="deleteParcela(parcela)"
                            >
                                Remover
                            </DangerButton>
                        </div>
                    </article>
                </section>

                <section
                    v-if="!parcelas.data.length"
                    class="rounded-[32px] border border-dashed border-slate-300 bg-white/70 px-6 py-12 text-center text-sm leading-7 text-slate-600"
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
                <h2 class="text-2xl font-black text-slate-900">Nova parcela</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Cria uma subdivisÃ£o produtiva associada a um terreno e desenha o perÃ­metro no mapa.
                </p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitCreate">
                    <div v-if="createErrorMessages.length" class="sm:col-span-2 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">NÃ£o foi possÃ­vel guardar a parcela. RevÃª estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in createErrorMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Terreno" />
                        <select
                            v-model="createForm.terreno_id"
                            class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
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
                        <TextInput v-model="createForm.nome" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="createForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Numero da parcela" />
                        <TextInput v-model="createForm.numero_parcela" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="createForm.errors.numero_parcela" />
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
                        <InputLabel value="Ãrea total (ha)" />
                        <TextInput v-model="createForm.area_total" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="createForm.errors.area_total" />
                    </div>
                    <div>
                        <InputLabel value="Area util (ha)" />
                        <TextInput v-model="createForm.area_util" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="createForm.errors.area_util" />
                    </div>
                    <div>
                        <InputLabel value="Latitude do centro" />
                        <TextInput v-model="createForm.latitude" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="createForm.errors.latitude" />
                    </div>
                    <div>
                        <InputLabel value="Longitude do centro" />
                        <TextInput v-model="createForm.longitude" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="createForm.errors.longitude" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="PolÃ­gono da parcela" />
                        <p v-if="selectedCreateTerreno?.poligono?.length" class="mt-2 text-xs leading-6 text-slate-500">
                            O contorno tracejado mostra o terreno selecionado como referÃªncia visual.
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
                        <InputLabel value="DescriÃ§Ã£o" />
                        <textarea
                            v-model="createForm.descricao"
                            class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            rows="4"
                        />
                        <InputError class="mt-2" :message="createForm.errors.descricao" />
                    </div>

                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="closeCreateModal">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton class="rounded-full bg-emerald-700 px-4 py-2 text-sm normal-case tracking-normal hover:bg-emerald-600 focus:bg-emerald-600" :disabled="createForm.processing">
                            Guardar parcela
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="!!editingParcela" max-width="2xl" @close="closeEditModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-black text-slate-900">Editar parcela</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Atualiza os dados de {{ editingParcela?.nome }} e ajusta o perÃ­metro no mapa.
                </p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitEdit">
                    <div v-if="editErrorMessages.length" class="sm:col-span-2 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">NÃ£o foi possÃ­vel atualizar a parcela. RevÃª estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in editErrorMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Terreno" />
                        <select
                            v-model="editForm.terreno_id"
                            class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
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
                        <TextInput v-model="editForm.nome" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="editForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Numero da parcela" />
                        <TextInput v-model="editForm.numero_parcela" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="editForm.errors.numero_parcela" />
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
                        <InputLabel value="Ãrea total (ha)" />
                        <TextInput v-model="editForm.area_total" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="editForm.errors.area_total" />
                    </div>
                    <div>
                        <InputLabel value="Area util (ha)" />
                        <TextInput v-model="editForm.area_util" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="editForm.errors.area_util" />
                    </div>
                    <div>
                        <InputLabel value="Latitude do centro" />
                        <TextInput v-model="editForm.latitude" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="editForm.errors.latitude" />
                    </div>
                    <div>
                        <InputLabel value="Longitude do centro" />
                        <TextInput v-model="editForm.longitude" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="editForm.errors.longitude" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="PolÃ­gono da parcela" />
                        <p v-if="selectedEditTerreno?.poligono?.length" class="mt-2 text-xs leading-6 text-slate-500">
                            O contorno tracejado mostra o terreno selecionado como referÃªncia visual.
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
                        <InputLabel value="DescriÃ§Ã£o" />
                        <textarea
                            v-model="editForm.descricao"
                            class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            rows="4"
                        />
                        <InputError class="mt-2" :message="editForm.errors.descricao" />
                    </div>

                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="closeEditModal">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton class="rounded-full bg-slate-900 px-4 py-2 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800" :disabled="editForm.processing">
                            Atualizar parcela
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>


