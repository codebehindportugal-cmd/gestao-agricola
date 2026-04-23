<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TerrenoPolygonMap from '@/Components/TerrenoPolygonMap.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    mode: {
        type: String,
        required: true,
    },
    parcela: {
        type: Object,
        default: null,
    },
    filters: {
        type: Object,
        default: () => ({}),
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

const isEditing = computed(() => props.mode === 'edit');
const title = computed(() => isEditing.value ? `Editar ${props.parcela?.nome ?? 'parcela'}` : 'Nova parcela');

const form = useForm({
    terreno_id: props.parcela?.terreno_id?.toString() ?? props.filters.terreno_id ?? '',
    nome: props.parcela?.nome ?? '',
    numero_parcela: props.parcela?.numero_parcela ?? '',
    area_total: props.parcela?.area_total?.toString() ?? '',
    area_util: props.parcela?.area_util?.toString() ?? '',
    estado: props.parcela?.estado ?? 'livre',
    tipo_ocupacao: props.parcela?.tipo_ocupacao ?? 'culturas_anuais',
    numero_arvores: props.parcela?.numero_arvores?.toString() ?? '',
    compasso_linha_m: props.parcela?.compasso_linha_m?.toString() ?? '',
    compasso_planta_m: props.parcela?.compasso_planta_m?.toString() ?? '',
    descricao: props.parcela?.descricao ?? '',
    latitude: props.parcela?.latitude?.toString() ?? '',
    longitude: props.parcela?.longitude?.toString() ?? '',
    poligono: props.parcela?.poligono ?? [],
});
const errorMessages = computed(() => Object.values(form.errors));

const selectedTerreno = computed(() =>
    props.terrenos.find((terreno) => String(terreno.id) === String(form.terreno_id)) ?? null,
);

const estadoLabel = (estado) => ({
    livre: 'livre',
    cultivada: 'cultivada',
    em_preparacao: 'em preparação',
    pousio: 'pousio',
}[estado] ?? estado);

const currentQuery = computed(() => ({
    search: props.filters.search || undefined,
    estado: props.filters.estado || undefined,
    terreno_id: props.filters.terreno_id || undefined,
}));

const updatePolygonCenter = ({ latitude, longitude }) => {
    form.latitude = latitude?.toString() ?? '';
    form.longitude = longitude?.toString() ?? '';
};

const updatePolygonArea = (area) => {
    form.area_total = area?.toString() ?? '';
};

const normalizePolygon = (polygon) =>
    (polygon ?? [])
        .filter((point) => Array.isArray(point) && point.length === 2)
        .map(([lat, lng]) => [Number(lat), Number(lng)]);

const calculatePolygonArea = (polygon) => {
    if (polygon.length < 3) {
        return null;
    }

    const earthRadius = 6378137;
    const toRadians = (degrees) => degrees * Math.PI / 180;
    const area = polygon.reduce((total, point, index) => {
        const nextPoint = polygon[(index + 1) % polygon.length];

        return total + toRadians(nextPoint[1] - point[1]) * (2 + Math.sin(toRadians(point[0])) + Math.sin(toRadians(nextPoint[0])));
    }, 0);

    return Number((Math.abs(area * earthRadius * earthRadius / 2) / 10000).toFixed(4));
};

const calculatePolygonCenter = (polygon) => {
    if (!polygon.length) {
        return { latitude: null, longitude: null };
    }

    const totals = polygon.reduce(
        (acc, point) => ({
            latitude: acc.latitude + point[0],
            longitude: acc.longitude + point[1],
        }),
        { latitude: 0, longitude: 0 },
    );

    return {
        latitude: Number((totals.latitude / polygon.length).toFixed(8)),
        longitude: Number((totals.longitude / polygon.length).toFixed(8)),
    };
};

const useSelectedTerrenoPolygon = () => {
    const polygon = normalizePolygon(selectedTerreno.value?.poligono);

    if (!polygon.length) {
        return;
    }

    const center = calculatePolygonCenter(polygon);
    const area = selectedTerreno.value?.area_total ?? calculatePolygonArea(polygon);

    form.poligono = polygon;
    form.latitude = selectedTerreno.value?.latitude?.toString() || center.latitude?.toString() || '';
    form.longitude = selectedTerreno.value?.longitude?.toString() || center.longitude?.toString() || '';
    form.area_total = area?.toString() ?? '';

    if (!form.area_util) {
        form.area_util = form.area_total;
    }
};

const submit = () => {
    if (isEditing.value) {
        form.patch(route('app.parcelas.update', { parcela: props.parcela.id, ...currentQuery.value }));
        return;
    }

    form.post(route('app.parcelas.store', currentQuery.value));
};
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-sky-700">Parcelas</p>
                    <h1 class="mt-2 text-3xl font-black text-slate-900">{{ title }}</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600">
                        Desenha a parcela sobre a imagem satélite. Quando selecionas um terreno, o seu contorno aparece como referência.
                    </p>
                </div>
                <Link :href="route('app.parcelas.index', currentQuery)">
                    <SecondaryButton class="rounded-full px-5 py-3 text-sm normal-case tracking-normal">Voltar</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.14),_transparent_30%),linear-gradient(180deg,_#f8fafc_0%,_#eef6f1_100%)] py-10">
            <form class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[0.86fr_1.4fr] lg:px-8" @submit.prevent="submit">
                <section class="rounded-[32px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-if="errorMessages.length" class="sm:col-span-2 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            <p class="font-semibold">Não foi possível guardar a parcela. Revê estes pontos:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li v-for="message in errorMessages" :key="message">{{ message }}</li>
                            </ul>
                        </div>

                        <div class="sm:col-span-2">
                            <InputLabel value="Terreno" />
                            <select v-model="form.terreno_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Selecionar terreno</option>
                                <option v-for="terreno in terrenos" :key="terreno.id" :value="String(terreno.id)">{{ terreno.nome }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.terreno_id" />
                        </div>
                        <div class="sm:col-span-2">
                            <InputLabel value="Nome" />
                            <TextInput v-model="form.nome" class="mt-2 block w-full rounded-2xl" />
                            <InputError class="mt-2" :message="form.errors.nome" />
                        </div>
                        <div>
                            <InputLabel value="Numero da parcela" />
                            <TextInput v-model="form.numero_parcela" class="mt-2 block w-full rounded-2xl" />
                            <InputError class="mt-2" :message="form.errors.numero_parcela" />
                        </div>
                        <div>
                            <InputLabel value="Estado" />
                            <select v-model="form.estado" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option v-for="estado in estadoOptions" :key="estado" :value="estado">{{ estadoLabel(estado) }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.estado" />
                        </div>
                        <div class="sm:col-span-2 rounded-3xl border border-emerald-100 bg-emerald-50/50 p-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel value="Tipo de ocupacao" />
                                    <select v-model="form.tipo_ocupacao" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                        <option value="culturas_anuais">Culturas anuais</option>
                                        <option value="pomar">Pomar / arvores</option>
                                        <option value="misto">Misto</option>
                                        <option value="estufa">Estufa</option>
                                        <option value="outro">Outro</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.tipo_ocupacao" />
                                </div>
                                <div>
                                    <InputLabel value="Numero de arvores" />
                                    <TextInput v-model="form.numero_arvores" class="mt-2 block w-full rounded-2xl" />
                                    <InputError class="mt-2" :message="form.errors.numero_arvores" />
                                </div>
                                <div>
                                    <InputLabel value="Compasso entre linhas (m)" />
                                    <TextInput v-model="form.compasso_linha_m" class="mt-2 block w-full rounded-2xl" />
                                    <InputError class="mt-2" :message="form.errors.compasso_linha_m" />
                                </div>
                                <div>
                                    <InputLabel value="Compasso entre plantas (m)" />
                                    <TextInput v-model="form.compasso_planta_m" class="mt-2 block w-full rounded-2xl" />
                                    <InputError class="mt-2" :message="form.errors.compasso_planta_m" />
                                </div>
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Área total (ha)" />
                            <TextInput v-model="form.area_total" class="mt-2 block w-full rounded-2xl" />
                            <InputError class="mt-2" :message="form.errors.area_total" />
                        </div>
                        <div>
                            <InputLabel value="Area util (ha)" />
                            <TextInput v-model="form.area_util" class="mt-2 block w-full rounded-2xl" />
                            <InputError class="mt-2" :message="form.errors.area_util" />
                        </div>
                        <div>
                            <InputLabel value="Latitude do centro" />
                            <TextInput v-model="form.latitude" class="mt-2 block w-full rounded-2xl" />
                            <InputError class="mt-2" :message="form.errors.latitude" />
                        </div>
                        <div>
                            <InputLabel value="Longitude do centro" />
                            <TextInput v-model="form.longitude" class="mt-2 block w-full rounded-2xl" />
                            <InputError class="mt-2" :message="form.errors.longitude" />
                        </div>
                        <div class="sm:col-span-2">
                            <InputLabel value="Descrição" />
                            <textarea v-model="form.descricao" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" rows="5" />
                            <InputError class="mt-2" :message="form.errors.descricao" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <Link :href="route('app.parcelas.index', currentQuery)">
                            <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal">Cancelar</SecondaryButton>
                        </Link>
                        <PrimaryButton class="rounded-full bg-emerald-700 px-5 py-2 text-sm normal-case tracking-normal hover:bg-emerald-600 focus:bg-emerald-600" :disabled="form.processing">
                            {{ isEditing ? 'Atualizar parcela' : 'Guardar parcela' }}
                        </PrimaryButton>
                    </div>
                </section>

                <section class="rounded-[32px] bg-white p-4 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)] lg:sticky lg:top-6 lg:self-start">
                    <InputLabel value="Polígono da parcela" />
                    <p v-if="selectedTerreno?.poligono?.length" class="mt-2 text-xs leading-6 text-slate-500">
                        O contorno tracejado mostra o terreno selecionado como referência visual.
                    </p>
                    <SecondaryButton
                        v-if="selectedTerreno?.poligono?.length"
                        type="button"
                        class="mt-3 rounded-full px-4 py-2 text-sm normal-case tracking-normal"
                        @click="useSelectedTerrenoPolygon"
                    >
                        Usar polígono do terreno
                    </SecondaryButton>
                    <div class="mt-3">
                        <TerrenoPolygonMap
                            height-class="h-[520px] lg:h-[680px]"
                            :polygon="form.poligono"
                            :context-polygon="selectedTerreno?.poligono ?? []"
                            :latitude="form.latitude"
                            :longitude="form.longitude"
                            :context-latitude="selectedTerreno?.latitude"
                            :context-longitude="selectedTerreno?.longitude"
                            selectable-context-polygon
                            @update:polygon="(polygon) => form.poligono = polygon"
                            @update:center="updatePolygonCenter"
                            @update:area="updatePolygonArea"
                            @select:context-polygon="useSelectedTerrenoPolygon"
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.poligono" />
                </section>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
