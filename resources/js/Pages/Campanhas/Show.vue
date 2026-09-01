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
import { computed, ref } from 'vue';

const props = defineProps({
    campanha: { type: Object, required: true },
    operacoes: { type: Array, default: () => [] },
    colheitas: { type: Array, default: () => [] },
    custos: { type: Array, default: () => [] },
    resumo: { type: Object, required: true },
    can: { type: Object, required: true },
    tiposCusto: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

// ── Formatação ──────────────────────────────────────────────────────────────

const formatCurrency = (v) =>
    new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(v || 0);

const formatNumber = (v, decimals = 2) =>
    new Intl.NumberFormat('pt-PT', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(v || 0);

const statusLabel = (s) => ({ planejada: 'planeada', em_curso: 'em curso', concluida: 'concluída', cancelada: 'cancelada' }[s] ?? s);

const statusBadgeClass = (s) => ({
    planejada: 'bg-sky-50 text-sky-700',
    em_curso: 'bg-amber-50 text-amber-700',
    concluida: 'bg-emerald-50 text-emerald-700',
    cancelada: 'bg-slate-100 text-slate-600',
}[s] ?? 'bg-slate-100 text-slate-600');

const tipoCustoLabel = (t) => ({
    material: 'Material',
    mao_obra: 'Mão de obra',
    maquinaria: 'Maquinaria',
    energia: 'Energia',
    manutencao: 'Manutenção',
    outro: 'Outro',
}[t] ?? t);

const tipoCustoClass = (t) => ({
    material: 'bg-amber-50 text-amber-700',
    mao_obra: 'bg-blue-50 text-blue-700',
    maquinaria: 'bg-orange-50 text-orange-700',
    energia: 'bg-yellow-50 text-yellow-700',
    manutencao: 'bg-purple-50 text-purple-700',
    outro: 'bg-slate-100 text-slate-600',
}[t] ?? 'bg-slate-100 text-slate-600');

const estadoOpClass = (e) => ({
    planejada: 'bg-sky-50 text-sky-600',
    em_curso: 'bg-amber-50 text-amber-600',
    concluida: 'bg-emerald-50 text-emerald-600',
    cancelada: 'bg-slate-100 text-slate-500',
}[e] ?? 'bg-slate-100 text-slate-500');

// ── Decomposição de custo ────────────────────────────────────────────────────

const custoBreakdown = computed(() => {
    const total = props.resumo.custo_total;
    const pct = (v) => total > 0 ? (v / total) * 100 : 0;
    return [
        { label: 'Operações', valor: props.resumo.custo_operacoes, pct: pct(props.resumo.custo_operacoes), color: 'bg-emerald-500' },
        { label: 'Produtos aplicados', valor: props.resumo.custo_produtos, pct: pct(props.resumo.custo_produtos), color: 'bg-amber-400' },
        { label: 'Custos diretos', valor: props.resumo.custo_diretos, pct: pct(props.resumo.custo_diretos), color: 'bg-blue-500' },
    ];
});

// ── Modal de custos ──────────────────────────────────────────────────────────

const custoModalOpen = ref(false);
const editingCusto = ref(null);

const custoForm = useForm({
    tipo: 'material',
    descricao: '',
    valor: '',
    data_custo: '',
    observacoes: '',
});

function openCustoModal(custo = null) {
    editingCusto.value = custo;
    custoForm.clearErrors();
    custoForm.tipo = custo?.tipo ?? 'material';
    custoForm.descricao = custo?.descricao ?? '';
    custoForm.valor = custo?.valor ?? '';
    custoForm.data_custo = custo?.data_custo ?? '';
    custoForm.observacoes = custo?.observacoes ?? '';
    custoModalOpen.value = true;
}

function closeCustoModal() {
    custoModalOpen.value = false;
    editingCusto.value = null;
    custoForm.reset();
    custoForm.clearErrors();
}

function submitCusto() {
    if (editingCusto.value) {
        custoForm.patch(route('app.campanhas.custos.update', [props.campanha.id, editingCusto.value.id]), {
            onSuccess: closeCustoModal,
        });
    } else {
        custoForm.post(route('app.campanhas.custos.store', props.campanha.id), {
            onSuccess: closeCustoModal,
        });
    }
}

function deleteCusto(custo) {
    if (!confirm(`Remover o custo "${custo.descricao}"?`)) return;
    router.delete(route('app.campanhas.custos.destroy', [props.campanha.id, custo.id]));
}
</script>

<template>
    <Head :title="campanha.cultura_nome" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <Link :href="route('app.campanhas.index')" class="mb-2 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-widest text-emerald-700 hover:text-emerald-900">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        Campanhas
                    </Link>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-3xl font-black text-slate-900">{{ campanha.cultura_nome }}</h1>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusBadgeClass(campanha.status)">
                            {{ statusLabel(campanha.status) }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">
                        <span v-if="campanha.terreno_nome || campanha.parcela_nome">
                            {{ [campanha.terreno_nome, campanha.parcela_nome].filter(Boolean).join(' — ') }}
                            <span v-if="campanha.area_ha"> · {{ formatNumber(campanha.area_ha) }} ha</span>
                            <span class="mx-2 text-slate-300">|</span>
                        </span>
                        {{ campanha.data_inicio || '—' }} até {{ campanha.data_fim || 'em curso' }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('app.campanhas.caderno-campo', campanha.id)"
                        class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100"
                    >
                        Caderno de campo
                    </Link>
                    <Link
                        :href="route('app.campanhas.custos-pdf', campanha.id)"
                        class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-100"
                    >
                        Custos PDF
                    </Link>
                </div>
            </div>
        </template>

        <div class="bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_32%),linear-gradient(180deg,_#f8fafc_0%,_#eef6f1_100%)] py-10">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">

                <!-- Flash success -->
                <div v-if="flashSuccess" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700">
                    {{ flashSuccess }}
                </div>

                <!-- Resumo numérico (4 cards) -->
                <section class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <article class="rounded-[28px] bg-white p-5 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Produção real</p>
                        <p class="mt-2 text-2xl font-black text-slate-900">{{ formatNumber(resumo.producao_real, 0) }} kg</p>
                        <p v-if="campanha.producao_esperada" class="mt-1 text-xs text-slate-400">
                            previsto: {{ formatNumber(campanha.producao_esperada, 0) }} kg
                        </p>
                    </article>
                    <article class="rounded-[28px] bg-white p-5 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Custo total</p>
                        <p class="mt-2 text-2xl font-black text-amber-700">{{ formatCurrency(resumo.custo_total) }}</p>
                        <p v-if="campanha.custo_estimado" class="mt-1 text-xs text-slate-400">
                            estimado: {{ formatCurrency(campanha.custo_estimado) }}
                        </p>
                    </article>
                    <article class="rounded-[28px] bg-white p-5 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Custo / kg</p>
                        <p class="mt-2 text-2xl font-black text-slate-900">
                            {{ resumo.custo_por_kg > 0 ? formatCurrency(resumo.custo_por_kg) : '—' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">por quilograma colhido</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-5 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Custo / ha</p>
                        <p class="mt-2 text-2xl font-black text-slate-900">
                            {{ resumo.custo_por_ha > 0 ? formatCurrency(resumo.custo_por_ha) : '—' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">{{ campanha.area_ha > 0 ? `${formatNumber(campanha.area_ha)} ha` : 'área não definida' }}</p>
                    </article>
                </section>

                <!-- Decomposição do custo -->
                <section class="rounded-[32px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                    <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-400">Decomposição do custo</h2>

                    <div v-if="resumo.custo_total > 0" class="mt-5 space-y-4">
                        <div v-for="item in custoBreakdown" :key="item.label" class="flex items-center gap-4">
                            <div class="w-36 shrink-0">
                                <p class="text-sm font-medium text-slate-700">{{ item.label }}</p>
                                <p class="text-xs text-slate-400">{{ formatCurrency(item.valor) }}</p>
                            </div>
                            <div class="flex-1">
                                <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="item.color"
                                        :style="{ width: `${item.pct}%` }"
                                    />
                                </div>
                            </div>
                            <div class="w-12 text-right text-sm font-semibold text-slate-600">
                                {{ item.pct.toFixed(0) }}%
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-400">Nenhum custo registado ainda.</p>
                </section>

                <!-- Operações -->
                <section class="rounded-[32px] bg-white shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                    <div class="flex items-center justify-between p-6 pb-4">
                        <h2 class="text-lg font-black text-slate-900">
                            Operações
                            <span class="ml-2 text-base font-semibold text-slate-400">({{ operacoes.length }})</span>
                        </h2>
                        <Link
                            :href="route('app.operacoes.index', { campanha_id: campanha.id })"
                            class="text-sm font-medium text-emerald-700 hover:text-emerald-900"
                        >
                            Ver todas →
                        </Link>
                    </div>

                    <div v-if="operacoes.length" class="divide-y divide-slate-100 px-2 pb-4">
                        <div
                            v-for="op in operacoes"
                            :key="op.id"
                            class="flex flex-col gap-1 rounded-2xl px-4 py-3 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:gap-4"
                        >
                            <div class="w-24 shrink-0 text-sm text-slate-400">{{ op.data }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-slate-800 capitalize">{{ op.tipo }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="estadoOpClass(op.estado)">
                                        {{ statusLabel(op.estado) }}
                                    </span>
                                </div>
                                <p class="mt-0.5 truncate text-xs text-slate-400">
                                    <span v-if="op.parcela_nome">{{ op.parcela_nome }}</span>
                                    <span v-if="op.maquina_nome"> · {{ op.maquina_nome }}</span>
                                    <span v-if="op.responsavel"> · {{ op.responsavel }}</span>
                                    <span v-if="op.produtos_nomes" class="italic"> · {{ op.produtos_nomes }}</span>
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p v-if="op.custo_real > 0 || op.custo_produtos > 0" class="text-sm font-semibold text-slate-800">
                                    {{ formatCurrency(op.custo_real + op.custo_produtos) }}
                                </p>
                                <p v-if="op.custo_real > 0 && op.custo_produtos > 0" class="text-xs text-slate-400">
                                    {{ formatCurrency(op.custo_real) }} op + {{ formatCurrency(op.custo_produtos) }} prod.
                                </p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="px-6 pb-6 text-sm text-slate-400">Nenhuma operação associada a esta campanha.</p>
                </section>

                <!-- Custos diretos -->
                <section class="rounded-[32px] bg-white shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                    <div class="flex items-center justify-between p-6 pb-4">
                        <h2 class="text-lg font-black text-slate-900">
                            Custos diretos
                            <span class="ml-2 text-base font-semibold text-slate-400">({{ custos.length }})</span>
                        </h2>
                        <button
                            v-if="can.manage_custos"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                            @click="openCustoModal()"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Adicionar custo
                        </button>
                    </div>

                    <div v-if="custos.length" class="divide-y divide-slate-100 px-2 pb-4">
                        <div
                            v-for="custo in custos"
                            :key="custo.id"
                            class="flex flex-col gap-2 rounded-2xl px-4 py-3 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:gap-4"
                        >
                            <span class="w-28 shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold text-center" :class="tipoCustoClass(custo.tipo)">
                                {{ tipoCustoLabel(custo.tipo) }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-slate-800">{{ custo.descricao }}</p>
                                <p v-if="custo.data_custo" class="text-xs text-slate-400">{{ custo.data_custo }}</p>
                            </div>
                            <p class="shrink-0 text-right text-base font-bold text-slate-900">{{ formatCurrency(custo.valor) }}</p>
                            <div v-if="can.manage_custos" class="flex shrink-0 gap-2">
                                <button
                                    type="button"
                                    class="rounded-full px-3 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-100"
                                    @click="openCustoModal(custo)"
                                >
                                    Editar
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full px-3 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50"
                                    @click="deleteCusto(custo)"
                                >
                                    Remover
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="px-6 pb-6">
                        <p class="text-sm text-slate-400">
                            Nenhum custo direto registado. Use o botão "Adicionar custo" para registar mão de obra, materiais ou outros gastos não ligados a operações.
                        </p>
                    </div>
                </section>

                <!-- Colheitas -->
                <section v-if="colheitas.length" class="rounded-[32px] bg-white shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                    <div class="p-6 pb-4">
                        <h2 class="text-lg font-black text-slate-900">
                            Colheitas
                            <span class="ml-2 text-base font-semibold text-slate-400">({{ colheitas.length }})</span>
                        </h2>
                    </div>
                    <div class="divide-y divide-slate-100 px-2 pb-4">
                        <div
                            v-for="c in colheitas"
                            :key="c.id"
                            class="flex flex-col gap-1 rounded-2xl px-4 py-3 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:gap-4"
                        >
                            <div class="w-24 shrink-0 text-sm text-slate-400">{{ c.data_colheita }}</div>
                            <div class="flex-1">
                                <p class="font-semibold text-slate-800">{{ formatNumber(c.quantidade_total, 0) }} kg</p>
                                <p class="text-xs text-slate-400">
                                    <span v-if="c.qualidade">{{ c.qualidade }}</span>
                                    <span v-if="c.quantidade_perdas > 0"> · {{ formatNumber(c.quantidade_perdas, 0) }} kg perdas</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Observações -->
                <section v-if="campanha.observacoes" class="rounded-[32px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Observações</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-700 whitespace-pre-line">{{ campanha.observacoes }}</p>
                </section>

            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Modal de custo -->
    <Modal :show="custoModalOpen" max-width="lg" @close="closeCustoModal">
        <form class="p-6" @submit.prevent="submitCusto">
            <h2 class="text-lg font-black text-slate-900">
                {{ editingCusto ? 'Editar custo' : 'Novo custo direto' }}
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Custos não ligados a operações — materiais, mão de obra externa, energia, etc.
            </p>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel value="Tipo *" />
                    <select
                        v-model="custoForm.tipo"
                        class="mt-1 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                    >
                        <option v-for="t in tiposCusto" :key="t" :value="t">{{ tipoCustoLabel(t) }}</option>
                    </select>
                    <InputError :message="custoForm.errors.tipo" class="mt-1" />
                </div>

                <div>
                    <InputLabel value="Data" />
                    <TextInput
                        v-model="custoForm.data_custo"
                        type="date"
                        class="mt-1 block w-full rounded-2xl border-slate-200"
                    />
                    <InputError :message="custoForm.errors.data_custo" class="mt-1" />
                </div>

                <div class="sm:col-span-2">
                    <InputLabel value="Descrição *" />
                    <TextInput
                        v-model="custoForm.descricao"
                        type="text"
                        placeholder="Ex: Semente de trigo certificada, Mão de obra apanha, …"
                        class="mt-1 block w-full rounded-2xl border-slate-200"
                    />
                    <InputError :message="custoForm.errors.descricao" class="mt-1" />
                </div>

                <div>
                    <InputLabel value="Valor (€) *" />
                    <TextInput
                        v-model="custoForm.valor"
                        type="number"
                        step="0.01"
                        min="0.01"
                        placeholder="0.00"
                        class="mt-1 block w-full rounded-2xl border-slate-200"
                    />
                    <InputError :message="custoForm.errors.valor" class="mt-1" />
                </div>

                <div>
                    <InputLabel value="Observações" />
                    <TextInput
                        v-model="custoForm.observacoes"
                        type="text"
                        class="mt-1 block w-full rounded-2xl border-slate-200"
                    />
                    <InputError :message="custoForm.errors.observacoes" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton type="button" @click="closeCustoModal">Cancelar</SecondaryButton>
                <PrimaryButton type="submit" :disabled="custoForm.processing">
                    {{ editingCusto ? 'Guardar alterações' : 'Adicionar custo' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
