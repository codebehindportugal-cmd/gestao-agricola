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
    funcionarios: { type: Object, required: true },
    equipas: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, required: true },
    can: { type: Object, required: true },
    statusOptions: { type: Array, default: () => [] },
    contratoOptions: { type: Array, default: () => [] },
    equipaStatusOptions: { type: Array, default: () => [] },
    funcionarioOptions: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const filterState = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    tipo_contrato: props.filters.tipo_contrato ?? '',
    equipa_status: props.filters.equipa_status ?? '',
});

const funcionarioModalOpen = ref(false);
const equipaModalOpen = ref(false);
const editingFuncionario = ref(null);
const editingEquipa = ref(null);

const funcionarioBase = {
    nome: '',
    email: '',
    telefone: '',
    cargo: '',
    aplicador_numero_autorizacao: '',
    data_admissao: '',
    data_saida: '',
    tipo_contrato: 'permanente',
    valor_hora: '',
    status: 'ativo',
    observacoes: '',
};

const equipaBase = {
    nome: '',
    lider_id: '',
    descricao: '',
    status: 'ativa',
    funcionario_ids: [],
};

const funcionarioForm = useForm({ ...funcionarioBase });
const equipaForm = useForm({ ...equipaBase });
const funcionarioErrors = computed(() => Object.values(funcionarioForm.errors));
const equipaErrors = computed(() => Object.values(equipaForm.errors));

const currentQuery = computed(() => ({
    search: filterState.search || undefined,
    status: filterState.status || undefined,
    tipo_contrato: filterState.tipo_contrato || undefined,
    equipa_status: filterState.equipa_status || undefined,
}));

watch(
    () => [filterState.search, filterState.status, filterState.tipo_contrato, filterState.equipa_status],
    () => {
        router.get(route('app.mao-obra.index'), currentQuery.value, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    },
);

const labels = {
    ativo: 'Ativo',
    inativo: 'Inativo',
    em_licenca: 'Em licença',
    permanente: 'Permanente',
    temporario: 'Temporário',
    estagiario: 'Estagiário',
    ativa: 'Ativa',
    inativa: 'Inativa',
};

const labelize = (value) => labels[value] ?? String(value ?? '').replaceAll('_', ' ');

const funcionarioStatusClass = (status) => ({
    ativo: 'bg-emerald-50 text-emerald-700',
    inativo: 'bg-slate-100 text-slate-600',
    em_licenca: 'bg-amber-50 text-amber-700',
}[status] ?? 'bg-slate-100 text-slate-600');

const equipaStatusClass = (status) => ({
    ativa: 'bg-emerald-50 text-emerald-700',
    inativa: 'bg-slate-100 text-slate-600',
}[status] ?? 'bg-slate-100 text-slate-600');

const openCreateFuncionario = () => {
    editingFuncionario.value = null;
    funcionarioForm.defaults({ ...funcionarioBase });
    funcionarioForm.reset();
    funcionarioForm.clearErrors();
    funcionarioModalOpen.value = true;
};

const openEditFuncionario = (funcionario) => {
    editingFuncionario.value = funcionario;
    funcionarioForm.defaults({
        nome: funcionario.nome ?? '',
        email: funcionario.email ?? '',
        telefone: funcionario.telefone ?? '',
        cargo: funcionario.cargo ?? '',
        aplicador_numero_autorizacao: funcionario.aplicador_numero_autorizacao ?? '',
        data_admissao: funcionario.data_admissao ?? '',
        data_saida: funcionario.data_saida ?? '',
        tipo_contrato: funcionario.tipo_contrato ?? 'permanente',
        valor_hora: funcionario.valor_hora?.toString() ?? '',
        status: funcionario.status ?? 'ativo',
        observacoes: funcionario.observacoes ?? '',
    });
    funcionarioForm.reset();
    funcionarioForm.clearErrors();
    funcionarioModalOpen.value = true;
};

const closeFuncionarioModal = () => {
    funcionarioModalOpen.value = false;
    editingFuncionario.value = null;
    funcionarioForm.clearErrors();
};

const submitFuncionario = () => {
    const options = { preserveScroll: true, onSuccess: () => closeFuncionarioModal() };

    if (editingFuncionario.value) {
        funcionarioForm.patch(route('app.funcionarios.update', { funcionario: editingFuncionario.value.id, ...currentQuery.value }), options);
        return;
    }

    funcionarioForm.post(route('app.funcionarios.store', currentQuery.value), options);
};

const deleteFuncionario = (funcionario) => {
    if (!window.confirm(`Remover o trabalhador "${funcionario.nome}"?`)) {
        return;
    }

    router.delete(route('app.funcionarios.destroy', { funcionario: funcionario.id, ...currentQuery.value }), {
        preserveScroll: true,
    });
};

const openCreateEquipa = () => {
    editingEquipa.value = null;
    equipaForm.defaults({ ...equipaBase, funcionario_ids: [] });
    equipaForm.reset();
    equipaForm.clearErrors();
    equipaModalOpen.value = true;
};

const openEditEquipa = (equipa) => {
    editingEquipa.value = equipa;
    equipaForm.defaults({
        nome: equipa.nome ?? '',
        lider_id: equipa.lider_id?.toString() ?? '',
        descricao: equipa.descricao ?? '',
        status: equipa.status ?? 'ativa',
        funcionario_ids: equipa.funcionario_ids ?? [],
    });
    equipaForm.reset();
    equipaForm.clearErrors();
    equipaModalOpen.value = true;
};

const closeEquipaModal = () => {
    equipaModalOpen.value = false;
    editingEquipa.value = null;
    equipaForm.clearErrors();
};

const submitEquipa = () => {
    const options = { preserveScroll: true, onSuccess: () => closeEquipaModal() };

    if (editingEquipa.value) {
        equipaForm.patch(route('app.equipas.update', { equipa: editingEquipa.value.id, ...currentQuery.value }), options);
        return;
    }

    equipaForm.post(route('app.equipas.store', currentQuery.value), options);
};

const deleteEquipa = (equipa) => {
    if (!window.confirm(`Remover a equipa "${equipa.nome}"?`)) {
        return;
    }

    router.delete(route('app.equipas.destroy', { equipa: equipa.id, ...currentQuery.value }), {
        preserveScroll: true,
    });
};

const cleanFilters = () => {
    filterState.search = '';
    filterState.status = '';
    filterState.tipo_contrato = '';
    filterState.equipa_status = '';
};

const formatCurrency = (value) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value));
};
</script>

<template>
    <Head title="Mão de obra" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-700">Recursos humanos</p>
                    <h1 class="mt-2 text-3xl font-black text-slate-900">Mão de obra</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        Gere trabalhadores e equipas para depois associar jornadas, operações e colheitas.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <PrimaryButton
                        v-if="can.create_funcionario"
                        class="rounded-full bg-emerald-700 px-5 py-3 text-sm normal-case tracking-normal hover:bg-emerald-600 focus:bg-emerald-600"
                        @click="openCreateFuncionario"
                    >
                        Novo trabalhador
                    </PrimaryButton>
                    <PrimaryButton
                        v-if="can.create_equipa"
                        class="rounded-full bg-slate-900 px-5 py-3 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800"
                        @click="openCreateEquipa"
                    >
                        Nova equipa
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_28%),linear-gradient(180deg,_#f8fafc_0%,_#eff6f0_100%)] py-10">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <div v-if="flashSuccess" class="rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800">
                    {{ flashSuccess }}
                </div>

                <section class="grid gap-4 md:grid-cols-5">
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.22)]">
                        <p class="text-sm font-medium text-slate-500">Operários</p>
                        <p class="mt-3 text-4xl font-black text-slate-900">{{ summary.funcionarios }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.22)]">
                        <p class="text-sm font-medium text-slate-500">Ativos</p>
                        <p class="mt-3 text-4xl font-black text-emerald-700">{{ summary.ativos }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.22)]">
                        <p class="text-sm font-medium text-slate-500">Em licença</p>
                        <p class="mt-3 text-4xl font-black text-amber-700">{{ summary.em_licenca }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.22)]">
                        <p class="text-sm font-medium text-slate-500">Equipas</p>
                        <p class="mt-3 text-4xl font-black text-slate-900">{{ summary.equipas }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.22)]">
                        <p class="text-sm font-medium text-slate-500">Equipas ativas</p>
                        <p class="mt-3 text-4xl font-black text-emerald-700">{{ summary.equipas_ativas }}</p>
                    </article>
                </section>

                <section class="rounded-[32px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.22)]">
                    <div class="grid gap-4 md:grid-cols-[1fr_0.8fr_0.85fr_0.8fr_auto]">
                        <div>
                            <InputLabel value="Pesquisar" />
                            <TextInput v-model="filterState.search" class="mt-2 block w-full rounded-2xl" placeholder="Nome, cargo, email ou equipa" />
                        </div>
                        <div>
                            <InputLabel value="Estado do trabalhador" />
                            <select v-model="filterState.status" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todos</option>
                                <option v-for="status in statusOptions" :key="status" :value="status">{{ labelize(status) }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Contrato" />
                            <select v-model="filterState.tipo_contrato" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todos</option>
                                <option v-for="contrato in contratoOptions" :key="contrato" :value="contrato">{{ labelize(contrato) }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Estado da equipa" />
                            <select v-model="filterState.equipa_status" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todas</option>
                                <option v-for="status in equipaStatusOptions" :key="status" :value="status">{{ labelize(status) }}</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <SecondaryButton class="w-full justify-center rounded-full px-5 py-3 text-sm normal-case tracking-normal" @click="cleanFilters">
                                Limpar
                            </SecondaryButton>
                        </div>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="text-xl font-black text-slate-900">Operários</h2>
                            <p class="text-sm text-slate-500">{{ funcionarios.total }} registos</p>
                        </div>

                        <article v-for="funcionario in funcionarios.data" :key="funcionario.id" class="rounded-[32px] border border-white/80 bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.22)]">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h3 class="text-2xl font-black text-slate-900">{{ funcionario.nome }}</h3>
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="funcionarioStatusClass(funcionario.status)">
                                            {{ labelize(funcionario.status) }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm font-medium text-emerald-700">{{ funcionario.cargo }}</p>
                                    <p class="mt-2 text-sm text-slate-500">{{ funcionario.email || 'Sem email' }} · {{ funcionario.telefone || 'Sem telefone' }}</p>
                                </div>
                                <div class="text-left md:text-right">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Contrato</p>
                                    <p class="mt-1 text-sm font-bold text-slate-800">{{ labelize(funcionario.tipo_contrato) }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Desde {{ funcionario.data_admissao || '-' }}</p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-4">
                                <div class="rounded-3xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Equipas</p>
                                    <p class="mt-2 text-xl font-black text-slate-900">{{ funcionario.equipas_count }}</p>
                                </div>
                                <div class="rounded-3xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Jornadas</p>
                                    <p class="mt-2 text-xl font-black text-slate-900">{{ funcionario.jornadas_count }}</p>
                                </div>
                                <div class="rounded-3xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Saída</p>
                                    <p class="mt-2 text-sm text-slate-700">{{ funcionario.data_saida || 'Sem data' }}</p>
                                </div>
                                <div class="rounded-3xl bg-emerald-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Valor/hora</p>
                                    <p class="mt-2 text-sm font-bold text-emerald-900">{{ formatCurrency(funcionario.valor_hora) }}</p>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <span v-for="equipa in funcionario.equipas" :key="equipa.id" class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                    {{ equipa.nome }}
                                </span>
                                <span v-if="!funcionario.equipas.length" class="text-sm text-slate-500">Sem equipa atribuída</span>
                            </div>

                            <p class="mt-4 rounded-3xl bg-lime-50/60 p-4 text-sm leading-7 text-slate-600">
                                {{ funcionario.observacoes || 'Sem observações adicionais.' }}
                            </p>

                            <div class="mt-5 flex flex-wrap gap-3">
                                <PrimaryButton v-if="funcionario.can_update" class="rounded-full bg-slate-900 px-4 py-2 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800" @click="openEditFuncionario(funcionario)">
                                    Editar
                                </PrimaryButton>
                                <DangerButton v-if="funcionario.can_delete" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="deleteFuncionario(funcionario)">
                                    Remover
                                </DangerButton>
                            </div>
                        </article>

                        <section v-if="!funcionarios.data.length" class="rounded-[32px] border border-dashed border-slate-300 bg-white/70 px-6 py-10 text-center text-sm text-slate-600">
                            Nenhum trabalhador encontrado com os filtros atuais.
                        </section>

                        <section v-if="funcionarios.links?.length > 3" class="flex flex-wrap items-center gap-2">
                            <component
                                :is="link.url ? Link : 'span'"
                                v-for="link in funcionarios.links"
                                :key="`funcionario-${link.label}-${link.url}`"
                                :href="link.url || undefined"
                                class="rounded-full px-4 py-2 text-sm transition"
                                :class="link.active ? 'bg-emerald-700 text-white' : 'bg-white text-slate-600 shadow hover:bg-slate-50'"
                                v-html="link.label"
                            />
                        </section>
                    </div>

                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="text-xl font-black text-slate-900">Equipas</h2>
                            <p class="text-sm text-slate-500">{{ equipas.total }} grupos</p>
                        </div>

                        <article v-for="equipa in equipas.data" :key="equipa.id" class="rounded-[32px] border border-white/80 bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.22)]">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h3 class="text-2xl font-black text-slate-900">{{ equipa.nome }}</h3>
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="equipaStatusClass(equipa.status)">
                                            {{ labelize(equipa.status) }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm text-slate-500">Líder: {{ equipa.lider_nome || 'Sem líder' }}</p>
                                </div>
                                <div class="rounded-3xl bg-emerald-50 px-4 py-3 text-center">
                                    <p class="text-2xl font-black text-emerald-700">{{ equipa.funcionarios_count }}</p>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">membros</p>
                                </div>
                            </div>

                            <p class="mt-4 rounded-3xl bg-slate-50 p-4 text-sm leading-7 text-slate-600">
                                {{ equipa.descricao || 'Sem descrição para esta equipa.' }}
                            </p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <span v-for="funcionario in equipa.funcionarios" :key="funcionario.id" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                    {{ funcionario.nome }} · {{ funcionario.cargo }}
                                </span>
                                <span v-if="!equipa.funcionarios.length" class="text-sm text-slate-500">Sem membros atribuídos</span>
                            </div>

                            <div class="mt-5 flex flex-wrap gap-3">
                                <PrimaryButton v-if="equipa.can_update" class="rounded-full bg-slate-900 px-4 py-2 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800" @click="openEditEquipa(equipa)">
                                    Editar
                                </PrimaryButton>
                                <DangerButton v-if="equipa.can_delete" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="deleteEquipa(equipa)">
                                    Remover
                                </DangerButton>
                            </div>
                        </article>

                        <section v-if="!equipas.data.length" class="rounded-[32px] border border-dashed border-slate-300 bg-white/70 px-6 py-10 text-center text-sm text-slate-600">
                            Nenhuma equipa encontrada com os filtros atuais.
                        </section>

                        <section v-if="equipas.links?.length > 3" class="flex flex-wrap items-center gap-2">
                            <component
                                :is="link.url ? Link : 'span'"
                                v-for="link in equipas.links"
                                :key="`equipa-${link.label}-${link.url}`"
                                :href="link.url || undefined"
                                class="rounded-full px-4 py-2 text-sm transition"
                                :class="link.active ? 'bg-emerald-700 text-white' : 'bg-white text-slate-600 shadow hover:bg-slate-50'"
                                v-html="link.label"
                            />
                        </section>
                    </div>
                </section>
            </div>
        </div>

        <Modal :show="funcionarioModalOpen" max-width="2xl" @close="closeFuncionarioModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-black text-slate-900">{{ editingFuncionario ? 'Editar trabalhador' : 'Novo trabalhador' }}</h2>
                <p class="mt-2 text-sm text-slate-500">Regista os dados base do trabalhador para equipas e jornadas.</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitFuncionario">
                    <div v-if="funcionarioErrors.length" class="sm:col-span-2 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível guardar o trabalhador. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in funcionarioErrors" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Nome" />
                        <TextInput v-model="funcionarioForm.nome" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="funcionarioForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Cargo / função" />
                        <TextInput v-model="funcionarioForm.cargo" class="mt-2 block w-full rounded-2xl" placeholder="Ex: Podador, Tratorista" />
                        <InputError class="mt-2" :message="funcionarioForm.errors.cargo" />
                    </div>
                    <div>
                        <InputLabel value="N.o autorização do aplicador" />
                        <TextInput v-model="funcionarioForm.aplicador_numero_autorizacao" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="funcionarioForm.errors.aplicador_numero_autorizacao" />
                    </div>
                    <div>
                        <InputLabel value="Email" />
                        <TextInput v-model="funcionarioForm.email" type="email" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="funcionarioForm.errors.email" />
                    </div>
                    <div>
                        <InputLabel value="Telefone" />
                        <TextInput v-model="funcionarioForm.telefone" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="funcionarioForm.errors.telefone" />
                    </div>
                    <div>
                        <InputLabel value="Data de admissão" />
                        <TextInput v-model="funcionarioForm.data_admissao" type="date" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="funcionarioForm.errors.data_admissao" />
                    </div>
                    <div>
                        <InputLabel value="Data de saída" />
                        <TextInput v-model="funcionarioForm.data_saida" type="date" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="funcionarioForm.errors.data_saida" />
                    </div>
                    <div>
                        <InputLabel value="Tipo de contrato" />
                        <select v-model="funcionarioForm.tipo_contrato" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="contrato in contratoOptions" :key="contrato" :value="contrato">{{ labelize(contrato) }}</option>
                        </select>
                        <InputError class="mt-2" :message="funcionarioForm.errors.tipo_contrato" />
                    </div>
                    <div>
                        <InputLabel value="Valor por hora (€)" />
                        <TextInput v-model="funcionarioForm.valor_hora" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="funcionarioForm.errors.valor_hora" />
                    </div>
                    <div>
                        <InputLabel value="Estado" />
                        <select v-model="funcionarioForm.status" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="status in statusOptions" :key="status" :value="status">{{ labelize(status) }}</option>
                        </select>
                        <InputError class="mt-2" :message="funcionarioForm.errors.status" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Observações" />
                        <textarea v-model="funcionarioForm.observacoes" rows="4" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="funcionarioForm.errors.observacoes" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="closeFuncionarioModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="rounded-full bg-emerald-700 px-4 py-2 text-sm normal-case tracking-normal hover:bg-emerald-600 focus:bg-emerald-600" :disabled="funcionarioForm.processing">
                            Guardar trabalhador
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="equipaModalOpen" max-width="2xl" @close="closeEquipaModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-black text-slate-900">{{ editingEquipa ? 'Editar equipa' : 'Nova equipa' }}</h2>
                <p class="mt-2 text-sm text-slate-500">Cria grupos de trabalho para poda, colheita, rega ou manutenção.</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitEquipa">
                    <div v-if="equipaErrors.length" class="sm:col-span-2 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível guardar a equipa. Revê estes pontos:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in equipaErrors" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Nome da equipa" />
                        <TextInput v-model="equipaForm.nome" class="mt-2 block w-full rounded-2xl" placeholder="Ex: Equipa colheita A" />
                        <InputError class="mt-2" :message="equipaForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Estado" />
                        <select v-model="equipaForm.status" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="status in equipaStatusOptions" :key="status" :value="status">{{ labelize(status) }}</option>
                        </select>
                        <InputError class="mt-2" :message="equipaForm.errors.status" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Líder" />
                        <select v-model="equipaForm.lider_id" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">Sem líder</option>
                            <option v-for="funcionario in funcionarioOptions" :key="funcionario.id" :value="String(funcionario.id)">
                                {{ funcionario.nome }} - {{ funcionario.cargo }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="equipaForm.errors.lider_id" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Membros" />
                        <div class="mt-2 grid max-h-56 gap-2 overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2">
                            <label v-for="funcionario in funcionarioOptions" :key="funcionario.id" class="flex items-start gap-3 rounded-2xl bg-white p-3 text-sm text-slate-700 shadow-sm">
                                <input v-model="equipaForm.funcionario_ids" type="checkbox" :value="String(funcionario.id)" class="mt-1 rounded border-slate-300 text-emerald-700 focus:ring-emerald-500" />
                                <span>
                                    <span class="block font-semibold text-slate-900">{{ funcionario.nome }}</span>
                                    <span class="block text-xs text-slate-500">{{ funcionario.cargo }} · {{ labelize(funcionario.status) }}</span>
                                </span>
                            </label>
                            <p v-if="!funcionarioOptions.length" class="p-3 text-sm text-slate-500">Cria primeiro trabalhadores para adicionar membros.</p>
                        </div>
                        <InputError class="mt-2" :message="equipaForm.errors.funcionario_ids" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Descrição" />
                        <textarea v-model="equipaForm.descricao" rows="4" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="equipaForm.errors.descricao" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="closeEquipaModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="rounded-full bg-slate-900 px-4 py-2 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800" :disabled="equipaForm.processing">
                            Guardar equipa
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
