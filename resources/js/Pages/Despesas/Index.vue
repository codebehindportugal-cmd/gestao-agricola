<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useQRScanner } from '@/composables/useQRScanner.js';

const { scanAndParseAT } = useQRScanner();
const page = usePage();

const props = defineProps({
    despesas: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    categorias: { type: Array, default: () => [] },
    taxasIva: { type: Array, default: () => [0, 6, 13, 23] },
    vendas: { type: Array, default: () => [] },
    tiposVenda: { type: Array, default: () => [] },
    resumoMes: { type: Object, default: () => ({}) },
    resumoVendas: { type: Object, default: () => ({}) },
    resumoMesAnterior: { type: Object, default: () => ({}) },
    analytics: { type: Object, default: () => ({ tem_items: false }) },
    produtos: { type: Array, default: () => [] },
    lotes: { type: Array, default: () => [] },
    partilhados: { type: Array, default: () => [] },
    resumoPartilhados: { type: Object, default: () => ({}) },
    tiposCustoPartilhado: { type: Array, default: () => [] },
    basesRateio: { type: Array, default: () => ['kg', 'area'] },
    can: { type: Object, default: () => ({}) },
});

const activeCampaign = computed(() => page.props.workingCampaign?.active ?? null);

// ─── filtros / navegação mês ─────────────────────────────────────────────────
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
    return new Date(anoAtual.value, mesAtual.value - 1, 1)
        .toLocaleDateString('pt-PT', { month: 'long', year: 'numeric' });
});

// ─── form ────────────────────────────────────────────────────────────────────
const showModal = ref(false);
const editingDespesa = ref(null);
const ficheiroPreview = ref(null);
const ficheiroNome = ref('');

const form = useForm({
    titulo: '',
    numero_fatura: '',
    fornecedor: '',
    valor: '',
    data_despesa: new Date().toISOString().split('T')[0],
    categoria: 'outro',
    notas: '',
    ficheiro: null,
    items: [],
});

// ─── itens ───────────────────────────────────────────────────────────────────
function novoItem() {
    return { descricao: '', quantidade: 1, preco_unitario: '', desconto_percentagem: 0, iva_percentagem: 23, produto_id: null, notas: '' };
}

function adicionarItem() {
    form.items.push(novoItem());
}

function removerItem(idx) {
    form.items.splice(idx, 1);
}

function itemTotal(item) {
    const base = itemBase(item);
    return base + base * (parseFloat(item.iva_percentagem) || 0) / 100;
}

// Base já líquida: o desconto entra antes do IVA, como na fatura.
function itemBase(item) {
    return itemBruto(item) * (1 - (parseFloat(item.desconto_percentagem) || 0) / 100);
}

function itemBruto(item) {
    return (parseFloat(item.quantidade) || 0) * (parseFloat(item.preco_unitario) || 0);
}

function itemDesconto(item) {
    return itemBruto(item) - itemBase(item);
}

// O que o produto custou mesmo por unidade — é este preço que vai para o stock.
function itemPrecoLiquido(item) {
    return (parseFloat(item.preco_unitario) || 0) * (1 - (parseFloat(item.desconto_percentagem) || 0) / 100);
}

const subtotalForm = computed(() => form.items.reduce((s, i) => s + itemBase(i), 0));
const ivaForm = computed(() => form.items.reduce((s, i) => {
    const base = itemBase(i);
    return s + base * (parseFloat(i.iva_percentagem) || 0) / 100;
}, 0));
const descontoForm = computed(() => form.items.reduce((s, i) => s + itemDesconto(i), 0));
const totalComIvaForm = computed(() => subtotalForm.value + ivaForm.value);
const temItems = computed(() => form.items.length > 0);

// Quando produto é seleccionado, preenche o preço unitário
function onProdutoChange(item) {
    if (!item.produto_id) return;
    const prod = props.produtos.find(p => p.id === parseInt(item.produto_id));
    if (prod?.custo_unitario) {
        item.preco_unitario = parseFloat(prod.custo_unitario);
    }
    if (!item.descricao && prod?.nome) {
        item.descricao = prod.nome;
    }
}

// ─── modal open/close ────────────────────────────────────────────────────────
function abrirCriar() {
    editingDespesa.value = null;
    form.reset();
    form.data_despesa = new Date().toISOString().split('T')[0];
    form.categoria = 'outro';
    form.items = [];
    ficheiroPreview.value = null;
    ficheiroNome.value = '';
    showModal.value = true;
}

function abrirEditar(despesa) {
    editingDespesa.value = despesa;
    form.titulo = despesa.titulo;
    form.numero_fatura = despesa.numero_fatura ?? '';
    form.fornecedor = despesa.fornecedor ?? '';
    form.valor = despesa.total_fatura;
    form.data_despesa = despesa.data;
    form.categoria = despesa.categoria;
    form.notas = despesa.notas ?? '';
    form.ficheiro = null;
    form.items = despesa.items.map(i => ({
        descricao: i.descricao,
        quantidade: i.quantidade,
        preco_unitario: i.preco_unitario,
        desconto_percentagem: i.desconto_percentagem ?? 0,
        iva_percentagem: i.iva_percentagem,
        produto_id: i.produto_id,
        notas: i.notas ?? '',
    }));
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
    leitura.value = null;
    erroLeitura.value = '';

    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (ev) => { ficheiroPreview.value = ev.target.result; };
        reader.readAsDataURL(file);
    } else {
        ficheiroPreview.value = null;
    }

    analisarFatura(file);
}

function submeter() {
    // Se tem itens, usar o total calculado como valor
    if (temItems.value) {
        form.valor = totalComIvaForm.value.toFixed(2);
    }

    const baseUrl = editingDespesa.value
        ? route('app.despesas.update', editingDespesa.value.id)
        : route('app.despesas.store');
    const url = `${baseUrl}?mes=${mesAtual.value}&ano=${anoAtual.value}`;

    const opts = {
        forceFormData: true,
        onSuccess: () => {
            form.transform((data) => data);
            fecharModal();
        },
        onFinish: () => form.transform((data) => data),
    };

    form.transform((data) => {
        const payload = { ...data, data: data.data_despesa };
        delete payload.data_despesa;

        return payload;
    });

    if (editingDespesa.value) {
        form.patch(url, opts);
    } else {
        form.post(url, opts);
    }
}

// ─── eliminar ────────────────────────────────────────────────────────────────
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

const vendaForm = useForm({
    descricao: '',
    tipo: 'venda_colheita',
    quantidade: '',
    unidade: 'kg',
    preco_unitario: '',
    valor: '',
    lote_id: '',
    data_venda: new Date().toISOString().split('T')[0],
    comprador_nome: '',
    documento: '',
    observacoes: '',
});

// Quilos x preço dá o valor. Quem vende fruta sabe o preço a que a vendeu;
// o total é conta que a aplicação faz, mas continua editável à mão.
const valorCalculadoVenda = computed(() => {
    const kg = Number(vendaForm.quantidade);
    const preco = Number(vendaForm.preco_unitario);

    if (!kg || !preco) return null;

    return Math.round(kg * preco * 100) / 100;
});

watch(valorCalculadoVenda, (valor) => {
    if (valor !== null) vendaForm.valor = valor;
});

const vendaTipoLabel = (tipo) => ({
    venda_colheita: 'Venda de colheita',
    subsidio: 'Subsidio',
    servico: 'Servico',
    outro: 'Outro',
}[tipo] ?? tipo);

const totalDespesas = computed(() => Number(props.resumoMes.total ?? 0));
const totalVendas = computed(() => Number(props.resumoVendas.total ?? 0));
const saldoMes = computed(() => totalVendas.value - totalDespesas.value);

function submeterVenda() {
    const url = `${route('app.despesas.vendas.store')}?mes=${mesAtual.value}&ano=${anoAtual.value}`;

    vendaForm.transform((data) => {
        const payload = { ...data, data: data.data_venda };
        delete payload.data_venda;

        return payload;
    });

    vendaForm.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            vendaForm.transform((data) => data);
            vendaForm.reset();
            vendaForm.tipo = 'venda_colheita';
            vendaForm.unidade = 'kg';
            vendaForm.data_venda = new Date().toISOString().split('T')[0];
        },
        onFinish: () => vendaForm.transform((data) => data),
    });
}

function eliminarVenda(venda) {
    if (!window.confirm(`Eliminar a venda "${venda.descricao}"?`)) return;

    router.delete(`${route('app.despesas.vendas.destroy', venda.id)}?mes=${mesAtual.value}&ano=${anoAtual.value}`, {
        preserveScroll: true,
    });
}

// ─── custos partilhados ──────────────────────────────────────────────────────
const partilhadoForm = useForm({
    descricao: '',
    tipo: 'energia',
    valor: '',
    data_custo: new Date().toISOString().split('T')[0],
    base_rateio: 'kg',
    observacoes: '',
});

const tipoCustoLabel = (tipo) => ({
    energia: 'Energia / eletricidade',
    material: 'Material',
    mao_obra: 'Mão de obra',
    maquinaria: 'Maquinaria',
    manutencao: 'Manutenção',
    outro: 'Outro',
}[tipo] ?? tipo);

const baseRateioLabel = (base) => ({
    kg: 'por quilo colhido',
    area: 'por hectare',
    igual: 'em partes iguais',
}[base] ?? base);

const totalPartilhados = computed(() => Number(props.resumoPartilhados.total ?? 0));
const precoMedioVenda = computed(() => Number(props.resumoVendas.preco_medio ?? 0));
const quilosVendidos = computed(() => Number(props.resumoVendas.quantidade ?? 0));

function submeterPartilhado() {
    partilhadoForm.post(`${route('app.despesas.partilhados.store')}?mes=${mesAtual.value}&ano=${anoAtual.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            partilhadoForm.reset();
            partilhadoForm.tipo = 'energia';
            partilhadoForm.base_rateio = 'kg';
            partilhadoForm.data_custo = new Date().toISOString().split('T')[0];
        },
    });
}

function eliminarPartilhado(custo) {
    if (!window.confirm(`Eliminar o custo partilhado "${custo.descricao}"?`)) return;

    router.delete(`${route('app.despesas.partilhados.destroy', custo.id)}?mes=${mesAtual.value}&ano=${anoAtual.value}`, {
        preserveScroll: true,
    });
}

// ─── leitura da fatura ───────────────────────────────────────────────────────
//
// O QR da AT traz, por lei, apenas o cabeçalho: NIF, data, número, IVA e total.
// As linhas dos produtos nunca lá estão — daí a fatura entrar sempre sem
// artigos. Por isso lêem-se as duas coisas: o QR no browser, que é imediato e
// exacto, e o OCR no servidor, que é quem consegue apanhar as linhas.
const leitura = ref(null);       // { fornecedor, numero_fatura, data, total, linhas: [...] }
const qrScanning = ref(false);
const erroLeitura = ref('');

async function analisarFatura(file) {
    if (!file) return;

    qrScanning.value = true;

    let qr = null;
    if (file.type.startsWith('image/')) {
        try {
            const resultado = await scanAndParseAT(file);
            if (resultado.is_at_qr) qr = resultado;
        } catch {
            // Sem QR legível — o OCR do servidor ainda pode salvar a leitura.
        }
    }

    let servidor = null;
    try {
        const dados = new FormData();
        dados.append('ficheiro', file);
        const { data } = await window.axios.post(route('app.despesas.extrair-fatura'), dados);
        servidor = data;
    } catch (e) {
        erroLeitura.value = e?.response?.data?.message
            ?? 'Não foi possível ler a fatura no servidor.';
    }

    if (qr || servidor) {
        // O QR ganha nos campos que traz: é o documento a dizê-lo, não um palpite.
        leitura.value = {
            fornecedor: servidor?.fornecedor ?? null,
            nif: qr?.nif_fornecedor ?? servidor?.nif ?? null,
            numero_fatura: qr?.numero_fatura ?? servidor?.numero_fatura ?? null,
            data: qr?.data ?? servidor?.data ?? null,
            total: qr?.total ?? servidor?.total ?? null,
            linhas: servidor?.linhas ?? [],
            fonte: servidor?.fonte ?? 'ocr',
            avisos: servidor?.avisos ?? [],
            rever: servidor?.rever ?? true,
            tem_qr: Boolean(qr),
        };
    }

    qrScanning.value = false;
}

const somaLinhasLidas = computed(() =>
    (leitura.value?.linhas ?? []).reduce((s, l) => s + (Number(l.total_linha) || 0), 0));

function preencherDaLeitura() {
    const l = leitura.value;
    if (!l) return;

    // Carregar no botão é dizer "usa o que leste": os campos são substituídos.
    // Antes só se preenchia o que estivesse vazio, e como a data já vem com a de
    // hoje por omissão, a data da fatura nunca entrava.
    if (l.data) form.data_despesa = l.data;
    if (l.numero_fatura) form.numero_fatura = l.numero_fatura;
    if (l.fornecedor) form.fornecedor = l.fornecedor;
    else if (l.nif && !form.fornecedor) form.fornecedor = `NIF: ${l.nif}`;
    if (l.fornecedor && !form.titulo) form.titulo = l.fornecedor;
    if (l.total) form.valor = Number(l.total).toFixed(2);

    if (l.linhas.length > 0) {
        form.items = l.linhas.map((linha) => ({
            descricao: linha.descricao,
            quantidade: linha.quantidade,
            preco_unitario: linha.preco_unitario,
            desconto_percentagem: linha.desconto_percentagem ?? 0,
            iva_percentagem: linha.iva_percentagem,
            produto_id: linha.produto_id,
            notas: '',
        }));
    }

    leitura.value = null;
}

// ─── lightbox ────────────────────────────────────────────────────────────────
const lightboxUrl = ref(null);

// ─── formatação ──────────────────────────────────────────────────────────────
const fmt = (v) => new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(v || 0);
const fmtN = (v, d = 2) => new Intl.NumberFormat('pt-PT', { minimumFractionDigits: d, maximumFractionDigits: d }).format(v || 0);

const categoriaLabel = (cat) => ({
    combustivel: 'Combustível', sementes: 'Sementes', fertilizantes: 'Fertilizantes',
    fitofarmaceuticos: 'Fitofarmacêuticos', equipamento: 'Equipamento', pecas: 'Peças',
    mao_obra: 'Mão de obra', outro: 'Outro',
}[cat] ?? cat);

const categoriaBadge = (cat) => ({
    combustivel: 'bg-orange-100 text-orange-700', sementes: 'bg-green-100 text-green-700',
    fertilizantes: 'bg-lime-100 text-lime-700', fitofarmaceuticos: 'bg-violet-100 text-violet-700',
    equipamento: 'bg-sky-100 text-sky-700', pecas: 'bg-zinc-100 text-zinc-700',
    mao_obra: 'bg-amber-100 text-amber-700',
    outro: 'bg-slate-100 text-slate-600',
}[cat] ?? 'bg-slate-100 text-slate-600');

const categoriaIcone = (cat) => ({
    combustivel: '⛽', sementes: '🌱', fertilizantes: '🧪', fitofarmaceuticos: '💊',
    equipamento: '🔧', pecas: '⚙️', mao_obra: '👷', outro: '📦',
}[cat] ?? '📦');

const variacaoLabel = computed(() => {
    const v = props.resumoMes.variacao;
    if (v === null || v === undefined) return null;
    return { cls: v > 0 ? 'text-red-600' : v < 0 ? 'text-emerald-600' : 'text-slate-500', label: v > 0 ? `+${v}%` : `${v}%` };
});

const exportPdfUrl = computed(() => route('app.despesas.resumo-pdf') + `?mes=${mesAtual.value}&ano=${anoAtual.value}`);
const exportCsvUrl = computed(() => route('app.despesas.exportar-csv') + `?mes=${mesAtual.value}&ano=${anoAtual.value}`);

const isPdf = (path) => path && path.toLowerCase().endsWith('.pdf');
const isPdfPreview = (url) => url && !url.match(/\.(jpe?g|png|webp|gif)$/i);
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
                        {{ activeCampaign ? `Campanha: ${activeCampaign.nome}.` : 'Sem campanha ativa.' }}
                        Registe faturas, vendas e acompanhe o saldo do mês.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a :href="exportPdfUrl" target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-emerald-200 hover:text-emerald-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        PDF
                    </a>
                    <a :href="exportCsvUrl"
                       class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-emerald-200 hover:text-emerald-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        CSV
                    </a>
                    <button v-if="can.create" @click="abrirCriar"
                            class="inline-flex items-center gap-2 rounded-full bg-emerald-700 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Nova fatura
                    </button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

            <!-- navegação mês -->
            <div class="mb-6 flex items-center justify-between">
                <button @click="mudarMes(-1)" class="rounded-full p-2 text-slate-500 transition hover:bg-slate-100">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <h2 class="text-lg font-bold capitalize text-slate-900">{{ nomeMesAtual }}</h2>
                <button @click="mudarMes(1)" class="rounded-full p-2 text-slate-500 transition hover:bg-slate-100">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
            <!-- resumo do mês -->
            <div class="mb-6 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Vendas do mês</p>
                    <p class="mt-1 text-2xl font-black text-emerald-900">{{ fmt(totalVendas) }}</p>
                    <p class="mt-1 text-xs text-emerald-700">
                        {{ resumoVendas.count ?? 0 }} venda(s)
                        <span v-if="quilosVendidos > 0"> · {{ quilosVendidos }} kg · {{ fmt(precoMedioVenda) }}/kg</span>
                    </p>
                </div>
                <div class="rounded-2xl border border-red-100 bg-red-50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-red-700">Despesas do mês</p>
                    <p class="mt-1 text-2xl font-black text-red-900">{{ fmt(totalDespesas) }}</p>
                    <p class="mt-1 text-xs text-red-700">{{ resumoMes.count ?? 0 }} fatura(s)</p>
                    <p v-if="variacaoLabel" class="mt-1 text-xs font-medium" :class="variacaoLabel.cls">
                        {{ variacaoLabel.label }} vs mês anterior
                    </p>
                </div>
                <div class="rounded-2xl border p-5" :class="saldoMes >= 0 ? 'border-sky-200 bg-sky-50' : 'border-amber-200 bg-amber-50'">
                    <p class="text-xs font-semibold uppercase tracking-wider" :class="saldoMes >= 0 ? 'text-sky-700' : 'text-amber-700'">Saldo da campanha</p>
                    <p class="mt-1 text-2xl font-black" :class="saldoMes >= 0 ? 'text-sky-900' : 'text-amber-900'">{{ fmt(saldoMes) }}</p>
                    <p class="mt-1 text-xs" :class="saldoMes >= 0 ? 'text-sky-700' : 'text-amber-700'">{{ activeCampaign?.nome ?? 'Campanha não definida' }}</p>
                </div>
            </div>

            <div v-if="Object.keys(resumoMes.por_categoria ?? {}).length" class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <template v-for="(val, cat) in resumoMes.por_categoria" :key="cat">
                    <div v-if="val > 0" class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-xs text-slate-500">{{ categoriaIcone(cat) }} {{ categoriaLabel(cat) }}</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">{{ fmt(val) }}</p>
                        <p class="text-xs text-slate-400">
                            {{ resumoMes.total > 0 ? Math.round(val / resumoMes.total * 100) : 0 }}%
                        </p>
                    </div>
                </template>
            </div>

            <!-- filtros -->
            <div class="mb-4 flex flex-wrap gap-3">
                <input v-model="searchQuery" type="text" placeholder="Pesquisar título, fornecedor, produto..."
                       class="h-10 rounded-full border border-slate-200 bg-white px-4 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" />
                <select v-model="categoriaFiltro"
                        class="h-10 rounded-full border border-slate-200 bg-white px-4 text-sm outline-none focus:border-emerald-400">
                    <option value="">Todas as categorias</option>
                    <option v-for="cat in categorias" :key="cat" :value="cat">{{ categoriaLabel(cat) }}</option>
                </select>
            </div>

            <div class="mb-6 grid gap-4 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                <form v-if="can.create" @submit.prevent="submeterVenda" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Vendas</p>
                            <h3 class="mt-1 text-base font-bold text-slate-900">Registar entrada</h3>
                        </div>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                            {{ activeCampaign?.nome ?? 'Sem campanha' }}
                        </span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Descrição *</label>
                            <input v-model="vendaForm.descricao" type="text" required
                                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                                   placeholder="Ex: Venda tomate mercado" />
                            <p v-if="vendaForm.errors.descricao" class="mt-1 text-xs text-red-600">{{ vendaForm.errors.descricao }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Tipo *</label>
                            <select v-model="vendaForm.tipo" required
                                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                                <option v-for="tipo in tiposVenda" :key="tipo" :value="tipo">{{ vendaTipoLabel(tipo) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Quantidade (kg)</label>
                            <input v-model="vendaForm.quantidade" type="number" min="0" step="0.001" placeholder="Ex: 1250"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" />
                            <p v-if="vendaForm.errors.quantidade" class="mt-1 text-xs text-red-600">{{ vendaForm.errors.quantidade }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Preço €/kg</label>
                            <input v-model="vendaForm.preco_unitario" type="number" min="0" step="0.0001" placeholder="Ex: 0,42"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" />
                            <p v-if="vendaForm.errors.preco_unitario" class="mt-1 text-xs text-red-600">{{ vendaForm.errors.preco_unitario }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Valor *</label>
                            <input v-model="vendaForm.valor" type="number" min="0.01" step="0.01" required
                                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" />
                            <p v-if="valorCalculadoVenda !== null" class="mt-1 text-xs text-emerald-700">
                                Calculado: {{ fmt(valorCalculadoVenda) }}
                            </p>
                            <p v-if="vendaForm.errors.valor" class="mt-1 text-xs text-red-600">{{ vendaForm.errors.valor }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Lote</label>
                            <select v-model="vendaForm.lote_id"
                                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                                <option value="">Sem lote</option>
                                <option v-for="lote in lotes" :key="lote.id" :value="lote.id">
                                    {{ lote.numero_lote }} — {{ lote.quantidade }} {{ lote.unidade ?? 'kg' }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-slate-400">A venda de um lote entra na campanha da colheita.</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Data *</label>
                            <input v-model="vendaForm.data_venda" type="date" required
                                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" />
                            <p v-if="vendaForm.errors.data" class="mt-1 text-xs text-red-600">{{ vendaForm.errors.data }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Comprador</label>
                            <input v-model="vendaForm.comprador_nome" type="text"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Documento</label>
                            <input v-model="vendaForm.documento" type="text"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                                   placeholder="Fatura, recibo ou referência" />
                        </div>
                    </div>

                    <button type="submit" :disabled="vendaForm.processing"
                            class="mt-4 w-full rounded-full bg-emerald-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-600 disabled:opacity-60">
                        {{ vendaForm.processing ? 'A guardar...' : 'Registar venda' }}
                    </button>
                </form>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Vendas do período</p>
                            <p class="mt-1 text-sm text-slate-500">{{ fmt(totalVendas) }} no mês selecionado</p>
                        </div>
                    </div>
                    <div v-if="vendas.length === 0" class="py-12 text-center">
                        <p class="text-sm font-medium text-slate-600">Sem vendas neste mês</p>
                        <p class="mt-1 text-xs text-slate-400">As vendas registadas entram no saldo da campanha.</p>
                    </div>
                    <ul v-else class="divide-y divide-slate-100">
                        <li v-for="venda in vendas" :key="venda.id" class="flex items-start justify-between gap-4 px-5 py-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-semibold text-slate-900">{{ venda.descricao }}</p>
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                        {{ vendaTipoLabel(venda.tipo) }}
                                    </span>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-3 text-xs text-slate-500">
                                    <span>{{ new Date(venda.data).toLocaleDateString('pt-PT') }}</span>
                                    <span v-if="venda.quantidade" class="font-semibold text-slate-600">
                                        {{ venda.quantidade }} {{ venda.unidade ?? 'kg' }}
                                    </span>
                                    <span v-if="venda.preco_unitario">{{ fmt(venda.preco_unitario) }}/{{ venda.unidade ?? 'kg' }}</span>
                                    <span v-if="venda.lote">Lote {{ venda.lote }}</span>
                                    <span v-if="venda.comprador_nome">{{ venda.comprador_nome }}</span>
                                    <span v-if="venda.documento">{{ venda.documento }}</span>
                                </div>
                            </div>
                            <div class="flex flex-shrink-0 flex-col items-end gap-2">
                                <p class="text-base font-black text-emerald-700">{{ fmt(venda.valor) }}</p>
                                <button v-if="can.delete" type="button" @click="eliminarVenda(venda)"
                                        class="rounded-full px-3 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50">
                                    Eliminar
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- custos partilhados -->
            <div class="mb-6 grid gap-4 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                <form v-if="can.create" @submit.prevent="submeterPartilhado" class="rounded-2xl border border-amber-200 bg-amber-50/40 p-5 shadow-sm">
                    <div class="mb-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Custos partilhados</p>
                        <h3 class="mt-1 text-base font-bold text-slate-900">Luz das regas, frio, IMI, seguros</h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Gastos que servem várias campanhas ao mesmo tempo. Entram repartidos por todas as campanhas do período.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Descrição *</label>
                            <input v-model="partilhadoForm.descricao" type="text" required
                                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
                                   placeholder="Ex: Eletricidade da rega — Julho" />
                            <p v-if="partilhadoForm.errors.descricao" class="mt-1 text-xs text-red-600">{{ partilhadoForm.errors.descricao }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Tipo *</label>
                            <select v-model="partilhadoForm.tipo" required
                                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-amber-400">
                                <option v-for="tipo in tiposCustoPartilhado" :key="tipo" :value="tipo">{{ tipoCustoLabel(tipo) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Valor *</label>
                            <input v-model="partilhadoForm.valor" type="number" min="0.01" step="0.01" required
                                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100" />
                            <p v-if="partilhadoForm.errors.valor" class="mt-1 text-xs text-red-600">{{ partilhadoForm.errors.valor }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Data *</label>
                            <input v-model="partilhadoForm.data_custo" type="date" required
                                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100" />
                            <p v-if="partilhadoForm.errors.data_custo" class="mt-1 text-xs text-red-600">{{ partilhadoForm.errors.data_custo }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Repartir *</label>
                            <select v-model="partilhadoForm.base_rateio" required
                                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-amber-400">
                                <option v-for="base in basesRateio" :key="base" :value="base">{{ baseRateioLabel(base) }}</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Notas</label>
                            <input v-model="partilhadoForm.observacoes" type="text"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
                                   placeholder="Contador, período faturado, contrato" />
                        </div>
                    </div>

                    <button type="submit" :disabled="partilhadoForm.processing"
                            class="mt-4 w-full rounded-full bg-amber-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-amber-500 disabled:opacity-60">
                        {{ partilhadoForm.processing ? 'A guardar...' : 'Registar custo partilhado' }}
                    </button>
                </form>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Partilhados do período</p>
                            <p class="mt-1 text-sm text-slate-500">{{ fmt(totalPartilhados) }} no mês selecionado</p>
                        </div>
                    </div>
                    <div v-if="partilhados.length === 0" class="py-12 text-center">
                        <p class="text-sm font-medium text-slate-600">Sem custos partilhados neste mês</p>
                        <p class="mt-1 text-xs text-slate-400">Registe aqui a luz das regas e das câmaras frigoríficas.</p>
                    </div>
                    <ul v-else class="divide-y divide-slate-100">
                        <li v-for="custo in partilhados" :key="custo.id" class="px-5 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate font-semibold text-slate-900">{{ custo.descricao }}</p>
                                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                            {{ tipoCustoLabel(custo.tipo) }}
                                        </span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-3 text-xs text-slate-500">
                                        <span>{{ new Date(custo.data).toLocaleDateString('pt-PT') }}</span>
                                        <span>repartido {{ baseRateioLabel(custo.base_rateio) }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-shrink-0 flex-col items-end gap-2">
                                    <p class="text-base font-black text-amber-700">{{ fmt(custo.valor) }}</p>
                                    <button v-if="can.delete" type="button" @click="eliminarPartilhado(custo)"
                                            class="rounded-full px-3 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50">
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                            <ul v-if="custo.campanhas.length" class="mt-3 space-y-1 rounded-xl bg-slate-50 px-3 py-2">
                                <li v-for="quota in custo.campanhas" :key="quota.campanha_id"
                                    class="flex items-center justify-between text-xs text-slate-600">
                                    <span class="truncate">{{ quota.campanha }}</span>
                                    <span class="font-semibold text-slate-800">{{ fmt(quota.valor) }}</span>
                                </li>
                            </ul>
                            <p v-else class="mt-3 text-xs text-slate-400">
                                Ainda não há campanha no período desta data — o custo fica por imputar.
                            </p>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- lista -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div v-if="despesas.data.length === 0" class="py-16 text-center">
                    <p class="text-3xl">🧾</p>
                    <p class="mt-3 text-sm font-medium text-slate-600">Sem despesas neste mês</p>
                    <p class="mt-1 text-xs text-slate-400">Clique em "Nova fatura" para começar.</p>
                </div>

                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="d in despesas.data" :key="d.id" class="px-5 py-4 transition hover:bg-slate-50/60">
                        <div class="flex items-start gap-4">
                            <!-- thumbnail -->
                            <div class="flex-shrink-0">
                                <template v-if="d.ficheiro_url && !isPdf(d.ficheiro_path)">
                                    <img :src="d.ficheiro_url" :alt="d.titulo"
                                         class="h-12 w-12 cursor-pointer rounded-xl object-cover shadow-sm ring-1 ring-slate-200"
                                         @click="lightboxUrl = d.ficheiro_url" />
                                </template>
                                <template v-else-if="d.ficheiro_url && isPdf(d.ficheiro_path)">
                                    <a :href="d.ficheiro_url" target="_blank"
                                       class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 text-xl shadow-sm ring-1 ring-slate-200">📄</a>
                                </template>
                                <template v-else>
                                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-xl">
                                        {{ categoriaIcone(d.categoria) }}
                                    </div>
                                </template>
                            </div>

                            <!-- info -->
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline gap-2">
                                    <p class="font-semibold text-slate-900">{{ d.titulo }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="categoriaBadge(d.categoria)">
                                        {{ categoriaLabel(d.categoria) }}
                                    </span>
                                    <span v-if="d.tem_items"
                                          class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                        {{ d.items.length }} linha{{ d.items.length !== 1 ? 's' : '' }}
                                    </span>
                                </div>
                                <div class="mt-0.5 flex flex-wrap gap-3 text-xs text-slate-500">
                                    <span v-if="d.fornecedor">{{ d.fornecedor }}</span>
                                    <span v-if="d.numero_fatura"># {{ d.numero_fatura }}</span>
                                    <span>{{ new Date(d.data).toLocaleDateString('pt-PT') }}</span>
                                </div>

                                <!-- linhas resumo -->
                                <div v-if="d.tem_items" class="mt-2 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-slate-400">
                                    <span>Subtotal: {{ fmt(d.subtotal_calculado) }}</span>
                                    <span>IVA: {{ fmt(d.iva_calculado) }}</span>
                                </div>
                            </div>

                            <!-- total + acções -->
                            <div class="flex flex-shrink-0 flex-col items-end gap-2">
                                <p class="text-lg font-black text-slate-900">{{ fmt(d.total_fatura) }}</p>
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
                        </div>

                        <!-- detalhe linhas (expandível por hover/click – opcional) -->
                        <div v-if="d.tem_items"
                             class="mt-3 overflow-hidden rounded-xl border border-slate-100 bg-slate-50">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="border-b border-slate-200 text-left">
                                        <th class="px-3 py-2 font-semibold text-slate-500">Descrição</th>
                                        <th class="hidden px-3 py-2 text-right font-semibold text-slate-500 sm:table-cell">Qtd</th>
                                        <th class="hidden px-3 py-2 text-right font-semibold text-slate-500 sm:table-cell">Preço unit.</th>
                                        <th class="px-3 py-2 text-right font-semibold text-slate-500">IVA%</th>
                                        <th class="px-3 py-2 text-right font-semibold text-slate-500">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, idx) in d.items" :key="idx"
                                        class="border-b border-slate-100 last:border-0">
                                        <td class="px-3 py-2 text-slate-700">{{ item.descricao }}</td>
                                        <td class="hidden px-3 py-2 text-right text-slate-600 sm:table-cell">
                                            {{ fmtN(item.quantidade, 3) }}
                                        </td>
                                        <td class="hidden px-3 py-2 text-right text-slate-600 sm:table-cell">
                                            {{ fmt(item.preco_unitario) }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-slate-500">{{ item.iva_percentagem }}%</td>
                                        <td class="px-3 py-2 text-right font-semibold text-slate-800">
                                            {{ fmt(item.total_com_iva) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="mt-6">
                <Pagination :links="despesas.links" />
            </div>

            <!-- análise mensal -->
            <div v-if="analytics.tem_items" class="mt-10">
                <h3 class="mb-4 text-sm font-bold uppercase tracking-wider text-slate-500">Análise do mês</h3>
                <div class="grid gap-4 sm:grid-cols-3">
                    <!-- IVA -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">IVA total do mês</p>
                        <p class="mt-2 text-2xl font-black text-slate-900">{{ fmt(analytics.iva_total) }}</p>
                        <p class="mt-1 text-xs text-slate-400">Subtotal s/ IVA: {{ fmt(analytics.subtotal) }}</p>
                        <p class="mt-0.5 text-xs text-slate-400">Total c/ IVA: {{ fmt(analytics.subtotal + analytics.iva_total) }}</p>
                    </div>

                    <!-- por fornecedor -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Por fornecedor</p>
                        <ul class="space-y-2">
                            <li v-for="f in analytics.por_fornecedor.slice(0, 5)" :key="f.fornecedor"
                                class="flex items-center justify-between gap-2">
                                <span class="truncate text-xs text-slate-600">{{ f.fornecedor }}</span>
                                <span class="flex-shrink-0 text-xs font-semibold text-slate-900">{{ fmt(f.total) }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- produtos mais comprados -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Produtos mais comprados</p>
                        <ul class="space-y-2">
                            <li v-for="p in analytics.top_descricoes.slice(0, 5)" :key="p.descricao"
                                class="flex items-center justify-between gap-2">
                                <span class="truncate text-xs text-slate-600">{{ p.descricao }}</span>
                                <div class="flex flex-shrink-0 items-center gap-2">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">×{{ p.count }}</span>
                                    <span class="text-xs font-semibold text-slate-900">{{ fmt(p.total) }}</span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- ── Modal criar / editar ───────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-[2000] flex items-end justify-center sm:items-center">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="fecharModal" />

            <div class="relative flex max-h-[96dvh] w-full flex-col rounded-t-3xl bg-white shadow-2xl sm:max-w-2xl sm:rounded-3xl">
                <!-- header modal -->
                <div class="flex flex-shrink-0 items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="text-lg font-bold text-slate-900">
                        {{ editingDespesa ? 'Editar fatura' : 'Nova fatura' }}
                    </h3>
                    <button @click="fecharModal" class="rounded-full p-1.5 text-slate-400 hover:bg-slate-100">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- scroll area -->
                <div class="flex-1 overflow-y-auto">
                    <form @submit.prevent="submeter">
                        <!-- ── SECÇÃO: Cabeçalho ──────────────────────────── -->
                        <div class="space-y-4 px-6 py-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Cabeçalho da fatura</p>

                            <!-- foto -->
                            <div>
                                <div v-if="ficheiroPreview && !isPdfPreview(ficheiroPreview)" class="mb-3">
                                    <img :src="ficheiroPreview" alt="Fatura"
                                         class="h-36 w-full rounded-xl object-contain ring-1 ring-slate-200 bg-slate-50 cursor-pointer"
                                         @click="lightboxUrl = ficheiroPreview" />
                                </div>
                                <div v-else-if="ficheiroNome" class="mb-3 flex items-center gap-2 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                    <span class="text-xl">📄</span> {{ ficheiroNome }}
                                </div>
                                <div class="flex gap-2">
                                    <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 px-3 py-3 text-sm font-medium text-slate-600 transition hover:border-emerald-400 hover:text-emerald-700">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        Câmara
                                        <input type="file" accept="image/*" capture="environment" class="sr-only" @change="onFicheiroChange" />
                                    </label>
                                    <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 px-3 py-3 text-sm font-medium text-slate-600 transition hover:border-emerald-400 hover:text-emerald-700">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        Galeria / PDF
                                        <input type="file" accept="image/*,application/pdf" class="sr-only" @change="onFicheiroChange" />
                                    </label>
                                </div>
                            </div>

                            <!-- leitura da fatura -->
                            <div v-if="qrScanning" class="flex items-center gap-2 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-700">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                                A ler a fatura — QR e linhas dos produtos…
                            </div>

                            <div v-if="erroLeitura && !qrScanning"
                                 class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                                {{ erroLeitura }} Pode preencher à mão.
                            </div>

                            <div v-if="leitura && !qrScanning"
                                 class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm">
                                <p class="font-semibold text-blue-800">
                                    Fatura lida{{ leitura.tem_qr ? ' (QR AT)' : '' }}{{ leitura.fonte === 'visao' ? ' · linhas com leitura assistida' : '' }}
                                </p>
                                <p class="mt-0.5 text-xs text-blue-600">
                                    {{ [leitura.fornecedor, leitura.numero_fatura, leitura.data, leitura.total ? fmt(leitura.total) : null].filter(Boolean).join(' · ') }}
                                </p>

                                <div v-if="leitura.linhas.length" class="mt-2 max-h-40 overflow-y-auto rounded-lg bg-white/70 p-2">
                                    <p class="mb-1 text-xs font-semibold text-blue-800">
                                        {{ leitura.linhas.length }} linha(s) — soma {{ fmt(somaLinhasLidas) }}
                                    </p>
                                    <ul class="space-y-0.5">
                                        <li v-for="(linha, idx) in leitura.linhas" :key="idx"
                                            class="flex items-center justify-between gap-3 text-xs text-slate-600">
                                            <span class="truncate">{{ linha.descricao }}</span>
                                            <span class="shrink-0 tabular-nums">
                                                {{ fmtN(linha.quantidade) }} × {{ fmt(linha.preco_unitario) }}<template v-if="linha.desconto_percentagem > 0"> −{{ fmtN(linha.desconto_percentagem) }}%</template>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                <p v-else class="mt-2 text-xs text-blue-600">
                                    Não foram reconhecidas linhas de produtos — o cabeçalho é preenchido na mesma.
                                </p>

                                <ul v-if="leitura.avisos.length" class="mt-2 space-y-0.5">
                                    <li v-for="(aviso, idx) in leitura.avisos" :key="idx" class="text-xs text-amber-700">
                                        {{ aviso }}
                                    </li>
                                </ul>

                                <div class="mt-2 flex gap-2">
                                    <button type="button" @click="preencherDaLeitura"
                                            class="rounded-full bg-blue-600 px-3 py-1 text-xs font-semibold text-white hover:bg-blue-500">
                                        Preencher fatura
                                    </button>
                                    <button type="button" @click="leitura = null"
                                            class="rounded-full border border-blue-200 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-100">
                                        Ignorar
                                    </button>
                                </div>
                                <p v-if="leitura.rever" class="mt-2 text-xs text-slate-500">
                                    Confira os valores antes de guardar — a leitura é automática.
                                </p>
                            </div>

                            <!-- título -->
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Título *</label>
                                <input v-model="form.titulo" type="text" required
                                       class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                                       placeholder="Ex: Gasóleo Julho" />
                                <p v-if="form.errors.titulo" class="mt-1 text-xs text-red-600">{{ form.errors.titulo }}</p>
                            </div>

                            <!-- nº fatura + fornecedor -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Nº Fatura</label>
                                    <input v-model="form.numero_fatura" type="text"
                                           class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                                           placeholder="FT2024/001" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Fornecedor</label>
                                    <input v-model="form.fornecedor" type="text"
                                           class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                                           placeholder="Nome" />
                                </div>
                            </div>

                            <!-- data + categoria -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Data *</label>
                                    <input v-model="form.data_despesa" type="date" required
                                           class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" />
                                    <p v-if="form.errors.data" class="mt-1 text-xs text-red-600">{{ form.errors.data }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Categoria *</label>
                                    <select v-model="form.categoria" required
                                            class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                                        <option v-for="cat in categorias" :key="cat" :value="cat">{{ categoriaLabel(cat) }}</option>
                                    </select>
                                </div>
                            </div>
                            <!-- notas -->
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Notas</label>
                                <textarea v-model="form.notas" rows="2"
                                          class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                                          placeholder="Observações adicionais..." />
                            </div>
                        </div>

                        <!-- ── SECÇÃO: Linhas de produto ──────────────────── -->
                        <div class="border-t border-slate-100 px-6 py-5">
                            <div class="mb-3 flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Linhas da fatura</p>
                                <button type="button" @click="adicionarItem"
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                    Adicionar linha
                                </button>
                            </div>

                            <p v-if="form.items.length === 0"
                               class="rounded-xl border border-dashed border-slate-200 bg-slate-50 py-6 text-center text-xs text-slate-400">
                                Sem linhas — preencha o valor total abaixo, ou adicione produtos individuais.
                            </p>

                            <div v-else class="space-y-3">
                                <div v-for="(item, idx) in form.items" :key="idx"
                                     class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <!-- linha: produto (opcional) + descrição -->
                                    <div class="mb-2 flex gap-2">
                                        <div class="flex-1">
                                            <input v-model="item.descricao" type="text" required
                                                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-400"
                                                   :placeholder="`Linha ${idx + 1} — descrição`" />
                                        </div>
                                        <button type="button" @click="removerItem(idx)"
                                                class="rounded-lg px-2 text-slate-400 hover:text-red-500">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>

                                    <!-- produto da horta (link opcional) -->
                                    <div v-if="produtos.length > 0" class="mb-2">
                                        <select v-model="item.produto_id" @change="onProdutoChange(item)"
                                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 outline-none focus:border-emerald-400">
                                            <option :value="null">— sem ligação a produto da horta —</option>
                                            <optgroup v-for="tipo in [...new Set(produtos.map(p => p.tipo))]" :key="tipo" :label="tipo">
                                                <option v-for="p in produtos.filter(p => p.tipo === tipo)" :key="p.id" :value="p.id">
                                                    {{ p.nome }}
                                                </option>
                                            </optgroup>
                                        </select>
                                    </div>

                                    <!-- qtd + preço + desconto + IVA -->
                                    <div class="grid grid-cols-4 gap-2">
                                        <div>
                                            <label class="mb-1 block text-[10px] font-semibold uppercase text-slate-400">Qtd</label>
                                            <input v-model="item.quantidade" type="number" step="0.001" min="0.001" required
                                                   class="w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-sm text-right outline-none focus:border-emerald-400" />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-[10px] font-semibold uppercase text-slate-400">Preço unit. €</label>
                                            <input v-model="item.preco_unitario" type="number" step="0.01" min="0" required
                                                   class="w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-sm text-right outline-none focus:border-emerald-400" />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-[10px] font-semibold uppercase text-slate-400">Desc. %</label>
                                            <input v-model="item.desconto_percentagem" type="number" step="0.01" min="0" max="100"
                                                   class="w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-sm text-right outline-none focus:border-emerald-400"
                                                   placeholder="0" />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-[10px] font-semibold uppercase text-slate-400">IVA %</label>
                                            <select v-model="item.iva_percentagem"
                                                    class="w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-sm outline-none focus:border-emerald-400">
                                                <option v-for="taxa in taxasIva" :key="taxa" :value="taxa">{{ taxa }}%</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- total da linha -->
                                    <div class="mt-2 flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="text-slate-400">
                                                base {{ fmt(itemBase(item)) }} + IVA {{ fmt(itemBase(item) * (parseFloat(item.iva_percentagem) || 0) / 100) }}
                                            </span>
                                            <span v-if="parseFloat(item.desconto_percentagem) > 0"
                                                  class="rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-700">
                                                −{{ fmt(itemDesconto(item)) }} · {{ fmt(itemPrecoLiquido(item)) }}/un
                                            </span>
                                            <span v-if="item.produto_id"
                                                  class="rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-700">
                                                📦 +{{ (parseFloat(item.quantidade) || 0).toFixed(2) }} ao stock
                                            </span>
                                        </div>
                                        <span class="font-bold text-slate-800">{{ fmt(itemTotal(item)) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- totais calculados -->
                            <div v-if="temItems" class="mt-4 rounded-xl bg-emerald-50 p-4 text-sm">
                                <div v-if="descontoForm > 0" class="flex justify-between text-slate-600">
                                    <span>Descontos</span>
                                    <span>−{{ fmt(descontoForm) }}</span>
                                </div>
                                <div class="flex justify-between text-slate-600">
                                    <span>Subtotal s/ IVA</span>
                                    <span>{{ fmt(subtotalForm) }}</span>
                                </div>
                                <div class="flex justify-between mt-1 text-slate-600">
                                    <span>IVA total</span>
                                    <span>{{ fmt(ivaForm) }}</span>
                                </div>
                                <div class="flex justify-between mt-2 border-t border-emerald-200 pt-2">
                                    <span class="font-bold text-slate-900">Total c/ IVA</span>
                                    <span class="text-lg font-black text-emerald-700">{{ fmt(totalComIvaForm) }}</span>
                                </div>
                            </div>

                            <!-- valor manual (sem linhas) -->
                            <div v-else class="mt-3">
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Valor total *</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-semibold text-slate-400">€</span>
                                    <input v-model="form.valor" type="number" step="0.01" min="0.01"
                                           :required="!temItems"
                                           class="w-full rounded-xl border border-slate-200 py-3 pl-8 pr-4 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                                           placeholder="0,00" />
                                </div>
                                <p v-if="form.errors.valor" class="mt-1 text-xs text-red-600">{{ form.errors.valor }}</p>
                            </div>
                        </div>

                        <!-- ── botões ─────────────────────────────────────── -->
                        <div class="flex flex-shrink-0 gap-3 border-t border-slate-100 px-6 py-4">
                            <button type="button" @click="fecharModal"
                                    class="flex-1 rounded-full border border-slate-200 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing"
                                    class="flex-1 rounded-full bg-emerald-700 py-3 text-sm font-medium text-white transition hover:bg-emerald-600 disabled:opacity-60">
                                {{ form.processing ? 'A guardar...' : (editingDespesa ? 'Guardar alterações' : 'Registar fatura') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ── Confirmação eliminar ──────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="showDeleteConfirm" class="fixed inset-0 z-[2100] flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showDeleteConfirm = false" />
            <div class="relative w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-900">Eliminar fatura?</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Vai eliminar <strong>{{ deletingDespesa?.titulo }}</strong> e todas as suas linhas.
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

    <!-- ── Lightbox imagem ───────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="lightboxUrl"
             class="fixed inset-0 z-[3000] flex items-center justify-center bg-slate-900/85 p-4"
             @click="lightboxUrl = null">
            <img :src="lightboxUrl" alt="Fatura"
                 class="max-h-full max-w-full rounded-2xl shadow-2xl" @click.stop />
            <button class="absolute right-4 top-4 rounded-full bg-white/20 p-2 text-white hover:bg-white/30"
                    @click="lightboxUrl = null">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </Teleport>
</template>
