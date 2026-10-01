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
    terrenos: {
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
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const createModalOpen = ref(false);
const editingTerreno = ref(null);
const importInput = ref(null);

const filterState = reactive({
    search: props.filters.search ?? '',
    estado: props.filters.estado ?? '',
});

const baseFormData = {
    nome: '',
    area_total: '',
    estado: 'ativo',
    localizacao: '',
    tipo_solo: '',
    descricao: '',
    latitude: '',
    longitude: '',
    poligono: [],
};

const createForm = useForm({ ...baseFormData });
const editForm = useForm({ ...baseFormData });
const importForm = useForm({
    ficheiro: null,
});
const createErrorMessages = computed(() => Object.values(createForm.errors));
const editErrorMessages = computed(() => Object.values(editForm.errors));

watch(
    () => [filterState.search, filterState.estado],
    () => {
        router.get(
            '/terrenos',
            {
                search: filterState.search || undefined,
                estado: filterState.estado || undefined,
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
    createForm.estado = 'ativo';
    createForm.poligono = [];
    createModalOpen.value = true;
};

const closeCreateModal = () => {
    createModalOpen.value = false;
    createForm.clearErrors();
};

const openEditModal = (terreno) => {
    editingTerreno.value = terreno;
    editForm.reset();
    editForm.clearErrors();
    editForm.nome = terreno.nome ?? '';
    editForm.area_total = terreno.area_total?.toString() ?? '';
    editForm.estado = terreno.estado ?? 'ativo';
    editForm.localizacao = terreno.localizacao ?? '';
    editForm.tipo_solo = terreno.tipo_solo ?? '';
    editForm.descricao = terreno.descricao ?? '';
    editForm.latitude = terreno.latitude?.toString() ?? '';
    editForm.longitude = terreno.longitude?.toString() ?? '';
    editForm.poligono = terreno.poligono ?? [];
};

const closeEditModal = () => {
    editingTerreno.value = null;
    editForm.clearErrors();
};

const submitCreate = () => {
    createForm.post('/terrenos', {
        preserveScroll: true,
        onSuccess: () => closeCreateModal(),
    });
};

const submitEdit = () => {
    if (!editingTerreno.value) {
        return;
    }

    editForm.patch(`/terrenos/${editingTerreno.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};

const deleteTerreno = (terreno) => {
    if (!window.confirm(`Remover o terreno "${terreno.nome}"?`)) {
        return;
    }

    router.delete(`/terrenos/${terreno.id}`, {
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
    ativo: 'bg-emerald-50 text-emerald-700',
    inativo: 'bg-slate-100 text-slate-600',
    em_manutencao: 'bg-amber-50 text-amber-700',
}[estado] ?? 'bg-slate-100 text-slate-600');

const estadoLabel = (estado) => ({
    ativo: 'ativo',
    inativo: 'inativo',
    em_manutencao: 'em manutenção',
}[estado] ?? estado);

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
    <Head title="Terrenos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                            <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 18c3-3 6-4.5 9-4.5s6 1.5 9 4.5" stroke-linecap="round" />
                                <path d="M5 21c2.8-2 5.2-3 7-3s4.2 1 7 3" stroke-linecap="round" opacity="0.65" />
                                <path d="M12 13V4" stroke-linecap="round" />
                                <path d="M12 4c0-1.8 1.8-3.2 4-3.5 0 2.3-1.7 4.1-4 4.5Z" fill="currentColor" stroke="none" />
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-verde-700">
                            Módulo Web
                        </p>
                    </div>
                    <h1 class="mt-1 text-[28px] font-bold leading-tight text-slate-900">
                        Terrenos
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600">
                        Gestão central da exploração com listagem, filtros, manutenção e desenho do perímetro no mapa.
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
                        href="/terrenos/criar"
                        class="justify-center rounded-lg bg-emerald-700 px-5 py-3 text-sm hover:bg-verde-800 focus:bg-verde-800 text-white font-semibold inline-flex items-center"
                    >
                        Novo terreno
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

                <section class="grid gap-4 md:grid-cols-3">
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M4 18c2.7-2.7 5.3-4 8-4s5.3 1.3 8 4" stroke-linecap="round" />
                                    <path d="M12 14V5" stroke-linecap="round" />
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-slate-500">Terrenos registados</p>
                        </div>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ summary.total }}</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-sm font-medium text-slate-500">Terrenos ativos</p>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ summary.ativos }}</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-sm font-medium text-slate-500">Área total</p>
                        <p class="numero mt-1 text-2xl font-bold text-slate-900">{{ formatArea(summary.area_total) }}</p>
                        <p class="mt-1 text-sm text-slate-500">ha</p>
                    </article>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                    <div class="grid gap-4 md:grid-cols-[1.4fr_0.8fr_auto]">
                        <div>
                            <InputLabel value="Pesquisar" />
                            <TextInput
                                v-model="filterState.search"
                                class="mt-2 block w-full rounded-lg border-slate-200"
                                placeholder="Nome, localização ou tipo de solo"
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
                        <div class="flex items-end">
                            <SecondaryButton
                                class="w-full justify-center rounded-lg px-5 py-3 text-sm "
                                @click="filterState.search = ''; filterState.estado = ''"
                            >
                                Limpar filtros
                            </SecondaryButton>
                        </div>
                    </div>
                </section>

                <section class="grid gap-5 lg:grid-cols-2">
                    <article
                        v-for="terreno in terrenos.data"
                        :key="terreno.id"
                        class="rounded-xl border border-slate-200 bg-white p-6"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="break-words text-xl font-bold sm:text-2xl text-slate-900">{{ terreno.nome }}</h2>
                                    <span
                                        class="rounded-md px-3 py-1 text-xs font-semibold capitalize"
                                        :class="estadoBadgeClass(terreno.estado)"
                                    >
                                        {{ estadoLabel(terreno.estado) }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">
                                    Atualizado em {{ terreno.updated_at || 'sem registo' }}
                                </p>
                            </div>
                            <p class="shrink-0 text-3xl font-bold text-slate-900 sm:text-right">
                                {{ formatArea(terreno.area_total) }}
                                <span class="block text-sm font-medium text-slate-500">ha</span>
                            </p>
                        </div>

                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold text-slate-400">Localização</p>
                                <p class="mt-2 text-sm text-slate-700">{{ terreno.localizacao || 'Não definida' }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold text-slate-400">Tipo de solo</p>
                                <p class="mt-2 text-sm text-slate-700">{{ terreno.tipo_solo || 'Não definido' }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold text-slate-400">Parcelas</p>
                                <p class="mt-2 text-sm text-slate-700">{{ terreno.parcelas_count }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold text-slate-400">Centro</p>
                                <p class="mt-2 text-sm text-slate-700">
                                    {{ terreno.latitude || '-' }} / {{ terreno.longitude || '-' }}
                                </p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4 sm:col-span-2">
                                <p class="text-xs font-semibold text-slate-400">Polígono</p>
                                <p class="mt-2 text-sm text-slate-700">
                                    {{ terreno.poligono?.length ? `${terreno.poligono.length} pontos guardados` : 'Sem polígono definido' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 rounded-xl bg-emerald-50/50 p-4">
                            <p class="text-sm leading-7 text-slate-600">
                                {{ terreno.descricao || 'Sem descrição adicional para este terreno.' }}
                            </p>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link
                                :href="`/parcelas?terreno_id=${terreno.id}`"
                                class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                Ver parcelas
                            </Link>
                            <Link
                                v-if="can.create"
                                :href="`/terrenos/${terreno.id}/editar`"
                                class="rounded-lg bg-slate-900 px-4 py-2 text-sm hover:bg-slate-800 focus:bg-slate-800 text-white font-semibold inline-flex items-center"
                            >
                                Editar
                            </Link>
                            <DangerButton
                                v-if="can.delete"
                                class="rounded-lg px-4 py-2 text-sm "
                                @click="deleteTerreno(terreno)"
                            >
                                Remover
                            </DangerButton>
                        </div>
                    </article>
                </section>

                <section
                    v-if="!terrenos.data.length"
                    class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm leading-7 text-slate-600"
                >
                    Nenhum terreno encontrado com os filtros atuais.
                </section>

                <section
                    v-if="terrenos.links?.length > 3"
                    class="flex flex-wrap items-center gap-2"
                >
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="link in terrenos.links"
                        :key="`${link.label}-${link.url}`"
                        :href="link.url || undefined"
                        class="rounded-lg px-4 py-2 text-sm transition"
                        :class="link.active ? 'bg-emerald-700 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
                        v-html="link.label"
                    />
                </section>
            </div>
        </div>

        <Modal :show="createModalOpen" max-width="2xl" @close="closeCreateModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-900">Novo terreno</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Regista uma nova unidade produtiva da exploração e desenha o seu contorno no mapa.
                </p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitCreate">
                    <div v-if="createErrorMessages.length" class="sm:col-span-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível guardar o terreno. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in createErrorMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Nome" />
                        <TextInput v-model="createForm.nome" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="createForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Área total (ha)" />
                        <TextInput v-model="createForm.area_total" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="createForm.errors.area_total" />
                    </div>
                    <div>
                        <InputLabel value="Estado" />
                        <select v-model="createForm.estado" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="estado in estadoOptions" :key="estado" :value="estado">
                                {{ estadoLabel(estado) }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.estado" />
                    </div>
                    <div>
                        <InputLabel value="Localização" />
                        <TextInput v-model="createForm.localizacao" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="createForm.errors.localizacao" />
                    </div>
                    <div>
                        <InputLabel value="Tipo de solo" />
                        <TextInput v-model="createForm.tipo_solo" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="createForm.errors.tipo_solo" />
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
                        <InputLabel value="Polígono do terreno" />
                        <div class="mt-2">
                            <TerrenoPolygonMap
                                :polygon="createForm.poligono"
                                :latitude="createForm.latitude"
                                :longitude="createForm.longitude"
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
                            Guardar terreno
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="!!editingTerreno" max-width="2xl" @close="closeEditModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-900">Editar terreno</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Atualiza os dados operacionais de {{ editingTerreno?.nome }} e ajusta o perímetro no mapa.
                </p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitEdit">
                    <div v-if="editErrorMessages.length" class="sm:col-span-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível atualizar o terreno. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in editErrorMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Nome" />
                        <TextInput v-model="editForm.nome" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="editForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Área total (ha)" />
                        <TextInput v-model="editForm.area_total" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="editForm.errors.area_total" />
                    </div>
                    <div>
                        <InputLabel value="Estado" />
                        <select v-model="editForm.estado" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="estado in estadoOptions" :key="estado" :value="estado">
                                {{ estadoLabel(estado) }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.estado" />
                    </div>
                    <div>
                        <InputLabel value="Localização" />
                        <TextInput v-model="editForm.localizacao" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="editForm.errors.localizacao" />
                    </div>
                    <div>
                        <InputLabel value="Tipo de solo" />
                        <TextInput v-model="editForm.tipo_solo" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="editForm.errors.tipo_solo" />
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
                        <InputLabel value="Polígono do terreno" />
                        <div class="mt-2">
                            <TerrenoPolygonMap
                                :polygon="editForm.poligono"
                                :latitude="editForm.latitude"
                                :longitude="editForm.longitude"
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
                            Atualizar terreno
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
