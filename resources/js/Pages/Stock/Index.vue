<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    produtos: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, required: true },
    tipoOptions: { type: Array, default: () => [] },
    estabelecimentos: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const productModalOpen = ref(false);
const estabelecimentoModalOpen = ref(false);
const stockModalOpen = ref(false);
const editingProduto = ref(null);
const productTypeOptions = computed(() => {
    const defaults = ['combustivel', 'fertilizante', 'fitofarmaco', 'planta', 'semente', 'corretivo', 'outro'];
    return [...new Set([...defaults, ...props.tipoOptions])].sort();
});

const unitForType = (tipo) => ({
    combustivel: 'L',
    fertilizante: 'kg',
    fitofarmaco: 'L',
    planta: 'un',
    semente: 'kg',
    corretivo: 'kg',
}[tipo] ?? 'un');

const filterState = reactive({
    search: props.filters.search ?? '',
    tipo: props.filters.tipo ?? '',
});

const currentQuery = computed(() => ({
    search: filterState.search || undefined,
    tipo: filterState.tipo || undefined,
}));

watch(
    () => [filterState.search, filterState.tipo],
    () => {
        router.get(route('app.stock.index'), currentQuery.value, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    },
);

const productForm = useForm({
    nome: '',
    tipo: 'fitofarmaco',
    unidade_medida: 'L',
    custo_unitario: '',
    stock_minimo: '',
    quantidade_inicial: '',
    codigo_interno: '',
    numero_autorizacao_dgav: '',
    estabelecimento_venda_id: '',
    estabelecimento_venda_nome: '',
    estabelecimento_venda_autorizacao: '',
    descricao: '',
});

const estabelecimentoForm = useForm({
    nome: '',
    numero_autorizacao: '',
});

const stockForm = useForm({
    ajuste_tipo: 'adicionar',
    quantidade: '',
    stock_minimo: '',
    custo_unitario: '',
    unidade_medida: 'kg',
    observacoes: '',
});

const productErrors = computed(() => Object.values(productForm.errors));
const stockErrors = computed(() => Object.values(stockForm.errors));
const estabelecimentoErrors = computed(() => Object.values(estabelecimentoForm.errors));

const openProductModal = () => {
    productForm.reset();
    productForm.clearErrors();
    productForm.tipo = 'fitofarmaco';
    productForm.unidade_medida = 'L';
    productForm.estabelecimento_venda_id = '';
    productForm.estabelecimento_venda_nome = '';
    productForm.estabelecimento_venda_autorizacao = '';
    productModalOpen.value = true;
};

const openEstabelecimentoModal = () => {
    estabelecimentoForm.reset();
    estabelecimentoForm.clearErrors();
    estabelecimentoModalOpen.value = true;
};

const closeEstabelecimentoModal = () => {
    estabelecimentoModalOpen.value = false;
    estabelecimentoForm.clearErrors();
};

const submitEstabelecimento = () => {
    estabelecimentoForm.post(route('app.stock.estabelecimentos.store', currentQuery.value), {
        preserveScroll: true,
        onSuccess: () => closeEstabelecimentoModal(),
    });
};

watch(() => productForm.tipo, (tipo) => {
    productForm.unidade_medida = unitForType(tipo);
});

const closeProductModal = () => {
    productModalOpen.value = false;
    productForm.clearErrors();
};

const openStockModal = (produto) => {
    editingProduto.value = produto;
    stockForm.reset();
    stockForm.clearErrors();
    stockForm.ajuste_tipo = 'adicionar';
    stockForm.quantidade = '';
    stockForm.stock_minimo = produto.stock_minimo?.toString() ?? '0';
    stockForm.custo_unitario = produto.custo_unitario?.toString() ?? '';
    stockForm.unidade_medida = produto.unidade_medida ?? 'kg';
    stockForm.observacoes = '';
    stockModalOpen.value = true;
};

const closeStockModal = () => {
    stockModalOpen.value = false;
    editingProduto.value = null;
    stockForm.clearErrors();
};

const submitProduct = () => {
    productForm.post(route('app.stock.produtos.store', currentQuery.value), {
        preserveScroll: true,
        onSuccess: () => closeProductModal(),
    });
};

const submitStock = () => {
    if (!editingProduto.value) {
        return;
    }

    stockForm.patch(route('app.stock.update', {
        produto: editingProduto.value.id,
        ...currentQuery.value,
    }), {
        preserveScroll: true,
        onSuccess: () => closeStockModal(),
    });
};

const formatNumber = (value) => new Intl.NumberFormat('pt-PT', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
}).format(Number(value ?? 0));
const nomeTipoProduto = (tipo) => ({
    fitofarmaco: 'Fitofarmacêutico',
    fitofarmaceutico: 'Fitofarmacêutico',
    fertilizante: 'Adubo',
    combustivel: 'Combustível',
    semente: 'Semente',
    planta: 'Planta',
    corretivo: 'Corretivo',
    outro: 'Outro',
}[tipo] ?? (tipo ? tipo.charAt(0).toUpperCase() + tipo.slice(1) : '—'));

const estadoStock = (produto) => {
    const atual = Number(produto.stock_atual ?? 0);
    if (atual < -0.05) return { texto: 'Negativo', classe: 'bg-red-50 text-red-800' };
    if (atual <= 0.05) return { texto: 'Esgotado', classe: 'bg-slate-100 text-slate-700' };
    if (produto.abaixo_minimo && Number(produto.stock_minimo ?? 0) > 0) return { texto: 'Abaixo do mínimo', classe: 'bg-ocre-100 text-ocre-700' };
    return { texto: 'Em stock', classe: 'bg-verde-100 text-verde-800' };
};

const dataCurta = (data) => (data ? new Date(`${data}T12:00:00`).toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit' }) : '—');

</script>

<template>
    <Head title="Stock" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-verde-700">Recursos</p>
                    <h1 class="mt-1 text-[28px] font-bold leading-tight text-slate-900">Stock</h1>
                </div>
                <div class="flex flex-wrap gap-2">
                    <SecondaryButton @click="openEstabelecimentoModal">Novo estabelecimento</SecondaryButton>
                    <PrimaryButton @click="openProductModal">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                        Novo produto
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto flex max-w-7xl flex-col gap-5 px-4 sm:px-6 lg:px-8">
                <div v-if="flashSuccess" class="rounded-xl border border-verde-200 bg-verde-50 px-5 py-4 text-sm font-medium text-verde-800" role="status">
                    {{ flashSuccess }}
                </div>

                <section aria-label="Resumo" class="cartao grid grid-cols-3">
                    <div class="flex flex-col gap-1 p-4 sm:p-5">
                        <span class="text-sm text-slate-600">Produtos</span>
                        <span class="numero text-xl font-bold sm:text-2xl">{{ summary.total_produtos }}</span>
                    </div>
                    <div class="flex flex-col gap-1 border-l border-slate-200 p-4 sm:p-5">
                        <span class="text-sm text-slate-600">No mínimo ou abaixo</span>
                        <span class="numero text-xl font-bold sm:text-2xl" :class="summary.abaixo_minimo ? 'text-ocre-700' : ''">{{ summary.abaixo_minimo }}</span>
                    </div>
                    <div class="flex flex-col gap-1 border-l border-slate-200 p-4 sm:p-5">
                        <span class="text-sm text-slate-600">Valor em armazém</span>
                        <span class="numero text-xl font-bold sm:text-2xl">{{ formatNumber(summary.valor_total) }} €</span>
                    </div>
                </section>

                <section class="cartao overflow-hidden">
                    <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <label class="relative flex w-full items-center sm:max-w-sm">
                            <span class="sr-only">Pesquisar</span>
                            <svg class="pointer-events-none absolute left-3 h-5 w-5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                            <TextInput v-model="filterState.search" type="search" class="block w-full pl-10" placeholder="Nome, código ou DGAV" />
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            Tipo
                            <select v-model="filterState.tipo" class="min-h-[44px] rounded-lg border-slate-300 text-sm focus:border-verde-600 focus:ring-verde-600">
                                <option value="">Todos</option>
                                <option v-for="tipo in tipoOptions" :key="tipo" :value="tipo">{{ nomeTipoProduto(tipo) }}</option>
                            </select>
                        </label>
                    </div>

                    <!-- Tabela (computador) -->
                    <div class="hidden md:block" role="table" aria-label="Produtos">
                        <div role="row" class="grid grid-cols-[minmax(0,2.4fr)_120px_100px_64px_88px_96px_60px_128px_84px] gap-4 border-b border-slate-200 bg-slate-50 px-5 py-2.5 text-xs font-semibold text-slate-600">
                            <span role="columnheader">Produto</span>
                            <span role="columnheader">Tipo</span>
                            <span role="columnheader" class="text-right">Em stock</span>
                            <span role="columnheader" class="text-right">Mínimo</span>
                            <span role="columnheader" class="text-right">Custo</span>
                            <span role="columnheader" class="text-right">Valor</span>
                            <span role="columnheader" class="text-right">Mexido</span>
                            <span role="columnheader">Estado</span>
                            <span role="columnheader"><span class="sr-only">Ações</span></span>
                        </div>
                        <div
                            v-for="produto in produtos.data"
                            :key="produto.id"
                            role="row"
                            class="grid min-h-[56px] grid-cols-[minmax(0,2.4fr)_120px_100px_64px_88px_96px_60px_128px_84px] items-center gap-4 border-b border-slate-100 px-5 py-2 text-sm last:border-b-0"
                        >
                            <span role="cell" class="flex min-w-0 flex-col">
                                <span class="truncate font-semibold text-slate-900" :title="produto.nome">{{ produto.nome }}</span>
                                <span v-if="produto.numero_autorizacao_dgav || produto.codigo_interno" class="truncate text-xs text-slate-500">
                                    {{ [produto.numero_autorizacao_dgav, produto.codigo_interno].filter(Boolean).join(' · ') }}
                                </span>
                            </span>
                            <span role="cell" class="text-slate-700">{{ nomeTipoProduto(produto.tipo) }}</span>
                            <span role="cell" class="numero text-right font-semibold">{{ formatNumber(produto.stock_atual) }} {{ produto.unidade_medida }}</span>
                            <span role="cell" class="numero text-right text-slate-600">{{ formatNumber(produto.stock_minimo) }}</span>
                            <span role="cell" class="numero text-right text-slate-700">{{ produto.custo_unitario !== null ? `${formatNumber(produto.custo_unitario)} €` : '—' }}</span>
                            <span role="cell" class="numero text-right">{{ produto.valor_stock !== null ? `${formatNumber(produto.valor_stock)} €` : '—' }}</span>
                            <span role="cell" class="numero text-right text-slate-600">{{ dataCurta(produto.ultimo_movimento_em) }}</span>
                            <span role="cell"><span class="etiqueta" :class="estadoStock(produto).classe">{{ estadoStock(produto).texto }}</span></span>
                            <span role="cell" class="flex justify-end">
                                <button type="button" class="min-h-[40px] rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-900 hover:bg-slate-50" @click="openStockModal(produto)">
                                    Ajustar
                                </button>
                            </span>
                        </div>
                    </div>

                    <!-- Lista (telemóvel) -->
                    <ul class="divide-y divide-slate-100 md:hidden">
                        <li v-for="produto in produtos.data" :key="`m-${produto.id}`" class="flex items-center justify-between gap-3 px-4 py-3">
                            <span class="flex min-w-0 flex-col gap-1">
                                <span class="truncate font-semibold">{{ produto.nome }}</span>
                                <span class="flex items-center gap-2 text-sm text-slate-600">
                                    <span class="numero font-semibold text-slate-900">{{ formatNumber(produto.stock_atual) }} {{ produto.unidade_medida }}</span>
                                    <span class="etiqueta" :class="estadoStock(produto).classe">{{ estadoStock(produto).texto }}</span>
                                </span>
                            </span>
                            <button type="button" class="min-h-[44px] shrink-0 rounded-lg border border-slate-300 px-3 text-sm font-semibold" @click="openStockModal(produto)">Ajustar</button>
                        </li>
                    </ul>

                    <p v-if="!produtos.data.length" class="px-5 py-10 text-center text-sm text-slate-600">Nenhum produto encontrado com estes filtros.</p>
                </section>

                <div v-if="produtos.links?.length > 3" class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-sm text-slate-600">{{ produtos.from }}–{{ produtos.to }} de {{ produtos.total }} produtos</span>
                    <Pagination :links="produtos.links" />
                </div>
            </div>
        </div>

        <Modal :show="productModalOpen" max-width="2xl" @close="closeProductModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-900">Novo produto</h2>
                <p class="mt-2 text-sm text-slate-500">Cria o produto e define já o stock inicial.</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitProduct">
                    <div v-if="productErrors.length" class="sm:col-span-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível guardar o produto.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in productErrors" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Nome" />
                        <TextInput v-model="productForm.nome" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="productForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="productForm.tipo" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="tipo in productTypeOptions" :key="tipo" :value="tipo">{{ tipo }}</option>
                        </select>
                        <InputError class="mt-2" :message="productForm.errors.tipo" />
                    </div>
                    <div>
                        <InputLabel value="Unidade" />
                        <TextInput v-model="productForm.unidade_medida" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="productForm.errors.unidade_medida" />
                    </div>
                    <div>
                        <InputLabel value="Preço unitário (€)" />
                        <TextInput v-model="productForm.custo_unitario" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="productForm.errors.custo_unitario" />
                    </div>
                    <div>
                        <InputLabel value="Stock mínimo" />
                        <TextInput v-model="productForm.stock_minimo" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="productForm.errors.stock_minimo" />
                    </div>
                    <div>
                        <InputLabel value="Stock inicial" />
                        <TextInput v-model="productForm.quantidade_inicial" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="productForm.errors.quantidade_inicial" />
                    </div>
                    <div>
                        <InputLabel value="Código interno" />
                        <TextInput v-model="productForm.codigo_interno" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="productForm.errors.codigo_interno" />
                    </div>
                    <div>
                        <InputLabel value="N.º DGAV" />
                        <TextInput v-model="productForm.numero_autorizacao_dgav" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="productForm.errors.numero_autorizacao_dgav" />
                    </div>
                    <div v-if="productForm.tipo === 'fitofarmaco'" class="sm:col-span-2">
                        <InputLabel value="Estabelecimento de venda" />
                        <select v-model="productForm.estabelecimento_venda_id" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">Selecionar estabelecimento</option>
                            <option v-for="estabelecimento in estabelecimentos" :key="estabelecimento.id" :value="String(estabelecimento.id)">
                                {{ estabelecimento.nome }}{{ estabelecimento.numero_autorizacao ? ` - ${estabelecimento.numero_autorizacao}` : '' }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="productForm.errors.estabelecimento_venda_id" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Descrição" />
                        <textarea v-model="productForm.descricao" rows="3" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="productForm.errors.descricao" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-lg px-4 py-2 text-sm " @click="closeProductModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="rounded-lg bg-emerald-700 px-4 py-2 text-sm hover:bg-verde-800 focus:bg-verde-800 text-white font-semibold inline-flex items-center" :disabled="productForm.processing">
                            Guardar produto
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="estabelecimentoModalOpen" max-width="lg" @close="closeEstabelecimentoModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-900">Novo estabelecimento</h2>

                <form class="mt-6 grid gap-4" @submit.prevent="submitEstabelecimento">
                    <div v-if="estabelecimentoErrors.length" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <ul class="list-disc space-y-1 pl-5">
                            <li v-for="message in estabelecimentoErrors" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Nome" />
                        <TextInput v-model="estabelecimentoForm.nome" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="estabelecimentoForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="N.º autorização" />
                        <TextInput v-model="estabelecimentoForm.numero_autorizacao" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="estabelecimentoForm.errors.numero_autorizacao" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-lg px-4 py-2 text-sm " @click="closeEstabelecimentoModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="rounded-lg bg-emerald-700 px-4 py-2 text-sm hover:bg-verde-800 focus:bg-verde-800 text-white font-semibold inline-flex items-center" :disabled="estabelecimentoForm.processing">
                            Guardar
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="stockModalOpen" max-width="2xl" @close="closeStockModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-900">Ajustar stock</h2>
                <p class="mt-2 text-sm text-slate-500">{{ editingProduto?.nome || 'Produto' }}</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitStock">
                    <div v-if="stockErrors.length" class="sm:col-span-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível atualizar o stock.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in stockErrors" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Modo" />
                        <select v-model="stockForm.ajuste_tipo" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="adicionar">Adicionar stock</option>
                            <option value="definir_total">Definir total</option>
                        </select>
                        <InputError class="mt-2" :message="stockForm.errors.ajuste_tipo" />
                    </div>
                    <div>
                        <InputLabel :value="stockForm.ajuste_tipo === 'adicionar' ? 'Quantidade a adicionar' : 'Quantidade total'" />
                        <TextInput v-model="stockForm.quantidade" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="stockForm.errors.quantidade" />
                    </div>
                    <div>
                        <InputLabel value="Unidade" />
                        <TextInput v-model="stockForm.unidade_medida" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="stockForm.errors.unidade_medida" />
                    </div>
                    <div>
                        <InputLabel value="Stock mínimo" />
                        <TextInput v-model="stockForm.stock_minimo" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="stockForm.errors.stock_minimo" />
                    </div>
                    <div>
                        <InputLabel value="Preço unitário (€)" />
                        <TextInput v-model="stockForm.custo_unitario" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-lg" />
                        <InputError class="mt-2" :message="stockForm.errors.custo_unitario" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Observações" />
                        <textarea v-model="stockForm.observacoes" rows="3" class="mt-2 block w-full rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="stockForm.errors.observacoes" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-lg px-4 py-2 text-sm " @click="closeStockModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="rounded-lg bg-slate-900 px-4 py-2 text-sm hover:bg-slate-800 focus:bg-slate-800 text-white font-semibold inline-flex items-center" :disabled="stockForm.processing">
                            Guardar ajuste
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
