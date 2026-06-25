<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    despesas: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    categorias: { type: Array, default: () => [] },
    resumoMes: { type: Object, default: () => ({}) },
    resumoMesAnterior: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

// ─── filtros ────────────────────────────────────────────────────────────────
const mesAtual = ref(props.filters.mes ?? new Date().getMonth() + 1);
const anoAtual = ref(props.filters.ano ?? new Date().getFullYear());
const searchQuery = ref(props.filters.search ?? '');
const categoriaFiltro = ref(props.filters.categoria ?? '');

let searchTimer = null;

function aplicarFiltros() {
    router.get(route('app.despesas.index'), {
        mes: mesAtual.value,
        ano: anoAtual.value,
        search: searchQuery.value || undefined,
        categoria: categoriaFiltro.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

watch([mesAtual, anoAtual, categoriaFiltro], () => aplicarFiltros());
watch(searchQuery, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(aplicarFiltros, 350);
});

function mudarMes(delta) {
    let m = mesAtual.value + delta;
    let a = anoAtual.value;
    if (m < 1) { m = 12; a--; }
    if (m > 12) { m = 1; a++; }
    mesAtual.value = m;
    anoAtual.value = a;
}

const nomeMesAtual = computed(() => {
    const d = new Date(anoAtual.value, mesAtual.value - 1, 1);
    return d.toLocaleDateString('pt-PT', { month: 'long', year: 'numeric' });
});

// ─── modal ──────────────────────────────────────────────────────────────────
const showModal = ref(false);
const editingDespesa = ref(null);
const ficheiroPreview = ref(null);
const ficheiroNome = ref('');
const ficheiroInput = ref(null);

const form = useForm({
    titulo: '',
    numero_fatura: '',
    fornecedor: '',
    valor: '',
    data: '',
    categoria: 'outro',
    notas: '',
    ficheiro: null,
});

function abrirCriar() {
    editingDespesa.value = null;
    form.reset();
    form.data = new Date().toISOString().split('T')[0];
    form.mes = mesAtual.value;
    form.ano = anoAtual.value;
    ficheiroPreview.value = null;
    ficheiroNome.value = '';
    showModal.value = true;
}

function abrirEditar(despesa) {
    editingDespesa.value = despesa;
    form.titulo = despesa.titulo;
    form.numero_fatura = despesa.numero_fatura ?? '';
    form.fornecedor = despesa.fornecedor ?? '';
    form.valor = despesa.valor;
    form.data = despesa.data;
    form.categoria = despesa.categoria;
    form.notas = despesa.notas ?? '';
    form.ficheiro = null;
    ficheiroPreview.value = despesa.ficheiro_url ?? null;
    ficheiroNome.value = despesa.ficheiro_path ? despesa.ficheiro_path.split('/').pop() : '';
    showModal.value = true;
}

function fecharModal() {
    showModal.value = false;
    editingDespesa.value = null;
    ficheiroPreview.value = null;
    ficheiroNome.value = '';
}

function onFicheiroChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    form.ficheiro = file;
    ficheiroNome.value = file.name;

    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (ev) => { ficheiroPreview.value = ev.target.result; };
        reader.readAsDataURL(file);
    } else {
        ficheiroPreview.value = null;
    }
}

function submeter() {
    const params = { mes: mesAtual.value, ano: anoAtual.value };
    if (editingDespesa.value) {
        form.post(route('app.despesas.update', editingDespesa.value.id) + '?' + new URLSearchParams(params), {
            method: 'patch',
            forceFormData: true,
            onSuccess: fecharModal,
        });
    } else {
        form.post(route('app.despesas.store') + '?' + new URLSearchParams(params), {
            forceFormData: true,
            onSuccess: fecharModal,
        });
    }
}

// ─── eliminar ───────────────────────────────────────────────────────────────
const showDeleteConfirm = ref(false);
const deletingDespesa = ref(null);

function confirmarEliminar(despesa) {
    deletingDespesa.value = despesa;
    showDeleteConfirm.value = true;
}

function eliminar() {
    router.delete(route('app.despesas.destroy', deletingDespesa.value.id), {
        onSuccess: () => { showDeleteConfirm.value = false; deletingDespesa.value = null; },
    });
}

// ─── formatação ─────────────────────────────────────────────────────────────
const formatCurrency = (v) => new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(v || 0);

const categoriaLabel = (cat) => ({
    combustivel: 'Combustível',
    sementes: 'Sementes',
    fertilizantes: 'Fertilizantes',
    fitofarmaceuticos: 'Fitofarmacêuticos',
    equipamento: 'Equipamento',
    mao_obra: 'Mão de obra',
    outro: 'Outro',
}[cat] ?? cat);

const categoriaBadge = (cat) => ({
    combustivel: 'bg-orange-100 text-orange-700',
    sementes: 'bg-green-100 text-green-700',
    fertilizantes: 'bg-lime-100 text-lime-700',
    fitofarmaceuticos: 'bg-violet-100 text-violet-700',
    equipamento: 'bg-sky-100 text-sky-700',
    mao_obra: 'bg-amber-100 text-amber-700',
    outro: 'bg-slate-100 text-slate-600',
}[cat] ?? 'bg-slate-100 text-slate-600');

const categoriaIcone = (cat) => ({
    combustivel: '⛽',
    sementes: '🌱',
    fertilizantes: '🧪',
    fitofarmaceuticos: '💊',
    equipamento: '🔧',
    mao_obra: '👷',
    outro: '📦',
}[cat] ?? '📦');

const variacaoSinal = computed(() => {
    const v = props.resumoMes.variacao ?? 0;
    if (v > 0) return { cls: 'text-red-600', label: `+${v}%` };
    if (v < 0) return { cls: 'text-emerald-600', label: `${v}%` };
    return { cls: 'text-slate-500', label: '—' };
});

const exportarPdfUrl = computed(() =>
    route('app.despesas.resumo-pdf') + `?mes=${mesAtual.value}&ano=${anoAtual.value}`
);
const exportarCsvUrl = computed(() =>
    route('app.despesas.exportar-csv') + `?mes=${mesAtual.value}&ano=${anoAtual.value}`
);

const isPdf = (path) => path && path.endsWith('.pdf');
</script>

<template>
    <Head title="Despesas e Faturas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-700">Despesas e faturas</p>
                    <h1 class="mt-2 text-3xl font-black text-slate-900">Controlo de despesas e documentos</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600">
                        Registe faturas e despesas com foto ou scan. O resumo mensal calcula automaticamente os totais por categoria.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a :href="exportarPdfUrl" target="_blank"
                       class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-emerald-200 hover:text-emerald-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        PDF
                    </a>
                    <a :href="exportarCsvUrl"
                       class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-emerald-200 hover:text-emerald-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        CSV
                    </a>
                    <button v-if="can.create" @click="abrirCriar"
                        class="inline-flex items-center gap-2 rounded-full bg-emerald-700 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Nova despesa
                    </button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

            <!-- navegação mês -->
            <div class="mb-6 flex items-center justify-between">
                <button @click="mudarMes(-1)"
                    class="rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <h2 class="text-lg font-bold capitalize text-slate-900">{{ nomeMesAtual }}</h2>
                <button @click="mudarMes(1)"
                    class="rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

            <!-- resumo -->
            <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="col-span-2 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 sm:col-span-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Total do mês</p>
                    <p class="mt-1 text-2xl font-black text-emerald-900">{{ formatCurrency(resumoMes.total) }}</p>
                    <p class="mt-1 text-xs text-emerald-700">{{ resumoMes.count ?? 0 }} despesa(s)</p>
                    <p v-if="resumoMes.variacao !== null" class="mt-1 text-xs font-medium" :class="variacaoSinal.cls">
                        {{ variacaoSinal.label }} vs mês anterior
                    </p>
                </div>
                <div v-for="(val, cat) in resumoMes.por_categoria" :key="cat"
                     v-show="val > 0"
                     class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-medium text-slate-500">{{ categoriaIcone(cat) }} {{ categoriaLabel(cat) }}</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ formatCurrency(val) }}</p>
                    <p class="text-xs text-slate-400">
                        {{ resumoMes.total > 0 ? Math.round(val / resumoMes.total * 100) : 0 }}%
                    </p>
                </div>
            </div>

            <!-- filtros -->
            <div class="mb-4 flex flex-wrap gap-3">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Pesquisar título, fornecedor..."
                    class="h-10 rounded-full border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                />
                <select
                    v-model="categoriaFiltro"
                    class="h-10 rounded-full border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none focus:border-emerald-400"
                >
                    <option value="">Todas as categorias</option>
                    <option v-for="cat in categorias" :key="cat" :value="cat">{{ categoriaLabel(cat) }}</option>
                </select>
            </div>

            <!-- lista -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div v-if="despesas.data.length === 0" class="py-16 text-center">
                    <p class="text-3xl">🧾</p>
                    <p class="mt-3 text-sm font-medium text-slate-600">Sem despesas neste mês</p>
                    <p class="mt-1 text-xs text-slate-400">Clique em "Nova despesa" para registar a primeira.</p>
                </div>

                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="d in despesas.data" :key="d.id"
                        class="flex items-start gap-4 px-5 py-4 transition hover:bg-slate-50">

                        <!-- miniatura / ícone -->
                        <div class="flex-shrink-0">
                            <template v-if="d.ficheiro_url && !isPdf(d.ficheiro_path)">
                                <img :src="d.ficheiro_url" :alt="d.titulo"
                                     class="h-12 w-12 rounded-xl object-cover shadow-sm ring-1 ring-slate-200 cursor-pointer"
                                     @click="ficheiroPreview = d.ficheiro_url; showModal = false;" />
                            </template>
                            <template v-else-if="d.ficheiro_url && isPdf(d.ficheiro_path)">
                                <a :href="d.ficheiro_url" target="_blank"
                                   class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 text-xl shadow-sm ring-1 ring-slate-200">
                                    📄
                                </a>
                            </template>
                            <template v-else>
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-xl">
                                    {{ categoriaIcone(d.categoria) }}
                                </div>
                            </template>
                        </div>

                        <!-- conteúdo -->
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline gap-2">
                                <p class="font-semibold text-slate-900">{{ d.titulo }}</p>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="categoriaBadge(d.categoria)">
                                    {{ categoriaLabel(d.categoria) }}
                                </span>
                            </div>
                            <div class="mt-0.5 flex flex-wrap gap-3 text-xs text-slate-500">
                                <span v-if="d.fornecedor">{{ d.fornecedor }}</span>
                                <span v-if="d.numero_fatura"># {{ d.numero_fatura }}</span>
                                <span>{{ new Date(d.data).toLocaleDateString('pt-PT') }}</span>
                            </div>
                            <p v-if="d.notas" class="mt-1 text-xs text-slate-400 line-clamp-1">{{ d.notas }}</p>
                        </div>

                        <!-- valor e acções -->
                        <div class="flex flex-shrink-0 flex-col items-end gap-2">
                            <p class="text-lg font-black text-slate-900">{{ formatCurrency(d.valor) }}</p>
                            <div class="flex gap-1">
                                <button @click="abrirEditar(d)"
                                    class="rounded-full px-3 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-100">
                                    Editar
                                </button>
                                <button v-if="can.delete" @click="confirmarEliminar(d)"
                                    class="rounded-full px-3 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50">
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="mt-6">
                <Pagination :links="despesas.links" />
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- ── Modal criar / editar ──────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-[2000] flex items-end justify-center sm:items-center">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="fecharModal" />

            <div class="relative w-full max-h-[95dvh] overflow-y-auto rounded-t-3xl bg-white p-6 shadow-2xl sm:max-w-lg sm:rounded-3xl">
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-900">
                        {{ editingDespesa ? 'Editar despesa' : 'Nova despesa' }}
                    </h3>
                    <button @click="fecharModal" class="rounded-full p-1.5 text-slate-400 hover:bg-slate-100">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="submeter" class="space-y-4">

                    <!-- título -->
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Título *</label>
                        <input v-model="form.titulo" type="text" required
                               class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                               placeholder="Ex: Gasóleo tractor" />
                        <p v-if="form.errors.titulo" class="mt-1 text-xs text-red-600">{{ form.errors.titulo }}</p>
                    </div>

                    <!-- valor + data -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Valor (€) *</label>
                            <input v-model="form.valor" type="number" step="0.01" min="0.01" required
                                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                                   placeholder="0,00" />
                            <p v-if="form.errors.valor" class="mt-1 text-xs text-red-600">{{ form.errors.valor }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Data *</label>
                            <input v-model="form.data" type="date" required
                                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" />
                            <p v-if="form.errors.data" class="mt-1 text-xs text-red-600">{{ form.errors.data }}</p>
                        </div>
                    </div>

                    <!-- categoria -->
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Categoria *</label>
                        <select v-model="form.categoria" required
                                class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                            <option v-for="cat in categorias" :key="cat" :value="cat">{{ categoriaLabel(cat) }}</option>
                        </select>
                    </div>

                    <!-- fornecedor + nº fatura -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Fornecedor</label>
                            <input v-model="form.fornecedor" type="text"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                                   placeholder="Nome" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Nº Fatura</label>
                            <input v-model="form.numero_fatura" type="text"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                                   placeholder="FT2024/001" />
                        </div>
                    </div>

                    <!-- upload ficheiro -->
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Foto / Scan (imagem ou PDF)
                        </label>

                        <!-- pré-visualização imagem -->
                        <div v-if="ficheiroPreview && !ficheiroPreview.endsWith('.pdf')" class="mb-3">
                            <img :src="ficheiroPreview" alt="Pré-visualização"
                                 class="h-40 w-full rounded-xl object-contain ring-1 ring-slate-200 bg-slate-50" />
                        </div>
                        <div v-else-if="ficheiroNome" class="mb-3 flex items-center gap-2 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <span class="text-xl">📄</span> {{ ficheiroNome }}
                        </div>

                        <!-- botões de captura -->
                        <div class="flex gap-2">
                            <!-- câmara (Android) -->
                            <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 px-4 py-4 text-sm font-medium text-slate-600 transition hover:border-emerald-400 hover:text-emerald-700">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Câmara
                                <input ref="ficheiroInput" type="file" accept="image/*" capture="environment" class="sr-only" @change="onFicheiroChange" />
                            </label>
                            <!-- galeria / ficheiro -->
                            <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 px-4 py-4 text-sm font-medium text-slate-600 transition hover:border-emerald-400 hover:text-emerald-700">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Galeria / PDF
                                <input type="file" accept="image/*,application/pdf" class="sr-only" @change="onFicheiroChange" />
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">JPEG, PNG, WebP ou PDF · máx 20 MB</p>
                    </div>

                    <!-- notas -->
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Notas</label>
                        <textarea v-model="form.notas" rows="2"
                                  class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                                  placeholder="Observações adicionais..." />
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="fecharModal"
                                class="flex-1 rounded-full border border-slate-200 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="form.processing"
                                class="flex-1 rounded-full bg-emerald-700 py-3 text-sm font-medium text-white transition hover:bg-emerald-600 disabled:opacity-60">
                            {{ form.processing ? 'A guardar...' : (editingDespesa ? 'Guardar alterações' : 'Registar despesa') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- ── Confirmação eliminar ──────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="showDeleteConfirm" class="fixed inset-0 z-[2100] flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showDeleteConfirm = false" />
            <div class="relative w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-900">Eliminar despesa?</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Vai eliminar <strong>{{ deletingDespesa?.titulo }}</strong>.
                    Esta acção não pode ser revertida.
                </p>
                <div class="mt-5 flex gap-3">
                    <button @click="showDeleteConfirm = false"
                            class="flex-1 rounded-full border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        Cancelar
                    </button>
                    <button @click="eliminar"
                            class="flex-1 rounded-full bg-red-600 py-2.5 text-sm font-medium text-white hover:bg-red-500">
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ── Lightbox imagem ───────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="ficheiroPreview && !showModal"
             class="fixed inset-0 z-[3000] flex items-center justify-center bg-slate-900/80 p-4"
             @click="ficheiroPreview = null">
            <img :src="ficheiroPreview" alt="Documento"
                 class="max-h-full max-w-full rounded-2xl shadow-2xl" @click.stop />
        </div>
    </Teleport>
</template>
