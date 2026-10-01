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
    terreno: {
        type: Object,
        default: null,
    },
    estadoOptions: {
        type: Array,
        default: () => [],
    },
});

const isEditing = computed(() => props.mode === 'edit');
const title = computed(() => isEditing.value ? `Editar ${props.terreno?.nome ?? 'terreno'}` : 'Novo terreno');

const form = useForm({
    nome: props.terreno?.nome ?? '',
    area_total: props.terreno?.area_total?.toString() ?? '',
    estado: props.terreno?.estado ?? 'ativo',
    localizacao: props.terreno?.localizacao ?? '',
    tipo_solo: props.terreno?.tipo_solo ?? '',
    descricao: props.terreno?.descricao ?? '',
    latitude: props.terreno?.latitude?.toString() ?? '',
    longitude: props.terreno?.longitude?.toString() ?? '',
    poligono: props.terreno?.poligono ?? [],
});
const errorMessages = computed(() => Object.values(form.errors));

const estadoLabel = (estado) => ({
    ativo: 'ativo',
    inativo: 'inativo',
    em_manutencao: 'em manutenção',
}[estado] ?? estado);

const updatePolygonCenter = ({ latitude, longitude }) => {
    form.latitude = latitude?.toString() ?? '';
    form.longitude = longitude?.toString() ?? '';
};

const updatePolygonArea = (area) => {
    form.area_total = area?.toString() ?? '';
};

const submit = () => {
    if (isEditing.value) {
        form.patch(`/terrenos/${props.terreno.id}`);
        return;
    }

    form.post('/terrenos');
};
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-verde-700">Terrenos</p>
                    <h1 class="mt-1 text-[28px] font-bold leading-tight text-slate-900">{{ title }}</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600">
                        Usa a vista satélite para desenhar o perímetro com mais detalhe. A área e o centro são atualizados automaticamente.
                    </p>
                </div>
                <Link href="/terrenos">
                    <SecondaryButton class="rounded-lg px-5 py-3 text-sm ">Voltar</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <form class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[0.86fr_1.4fr] lg:px-8" @submit.prevent="submit">
                <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-if="errorMessages.length" class="sm:col-span-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            <p class="font-semibold">Não foi possível guardar o terreno. Revê estes pontos:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li v-for="message in errorMessages" :key="message">{{ message }}</li>
                            </ul>
                        </div>

                        <div class="sm:col-span-2">
                            <InputLabel value="Nome" />
                            <TextInput v-model="form.nome" class="mt-2 block w-full rounded-lg" />
                            <InputError class="mt-2" :message="form.errors.nome" />
                        </div>
                        <div>
                            <InputLabel value="Área total (ha)" />
                            <TextInput v-model="form.area_total" class="mt-2 block w-full rounded-lg" />
                            <InputError class="mt-2" :message="form.errors.area_total" />
                        </div>
                        <div>
                            <InputLabel value="Estado" />
                            <select v-model="form.estado" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                                <option v-for="estado in estadoOptions" :key="estado" :value="estado">{{ estadoLabel(estado) }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.estado" />
                        </div>
                        <div>
                            <InputLabel value="Localizacao" />
                            <TextInput v-model="form.localizacao" class="mt-2 block w-full rounded-lg" />
                            <InputError class="mt-2" :message="form.errors.localizacao" />
                        </div>
                        <div>
                            <InputLabel value="Tipo de solo" />
                            <TextInput v-model="form.tipo_solo" class="mt-2 block w-full rounded-lg" />
                            <InputError class="mt-2" :message="form.errors.tipo_solo" />
                        </div>
                        <div>
                            <InputLabel value="Latitude do centro" />
                            <TextInput v-model="form.latitude" class="mt-2 block w-full rounded-lg" />
                            <InputError class="mt-2" :message="form.errors.latitude" />
                        </div>
                        <div>
                            <InputLabel value="Longitude do centro" />
                            <TextInput v-model="form.longitude" class="mt-2 block w-full rounded-lg" />
                            <InputError class="mt-2" :message="form.errors.longitude" />
                        </div>
                        <div class="sm:col-span-2">
                            <InputLabel value="Descrição" />
                            <textarea v-model="form.descricao" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" rows="5" />
                            <InputError class="mt-2" :message="form.errors.descricao" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <Link href="/terrenos">
                            <SecondaryButton type="button" class="rounded-lg px-4 py-2 text-sm ">Cancelar</SecondaryButton>
                        </Link>
                        <PrimaryButton class="rounded-lg bg-emerald-700 px-5 py-2 text-sm hover:bg-verde-800 focus:bg-verde-800 text-white font-semibold inline-flex items-center" :disabled="form.processing">
                            {{ isEditing ? 'Atualizar terreno' : 'Guardar terreno' }}
                        </PrimaryButton>
                    </div>
                </section>

                <section class="rounded-xl bg-white p-4 lg:sticky lg:top-6 lg:self-start border border-slate-200">
                    <InputLabel value="Polígono do terreno" />
                    <div class="mt-3">
                        <TerrenoPolygonMap
                            height-class="h-[520px] lg:h-[680px]"
                            :polygon="form.poligono"
                            :latitude="form.latitude"
                            :longitude="form.longitude"
                            @update:polygon="(polygon) => form.poligono = polygon"
                            @update:center="updatePolygonCenter"
                            @update:area="updatePolygonArea"
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.poligono" />
                </section>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
