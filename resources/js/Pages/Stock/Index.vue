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
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const productModalOpen = ref(false);
const stockModalOpen = ref(false);
const editingProduto = ref(null);

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
    estabelecimento_venda_nome: '',
    estabelecimento_venda_autorizacao: '',
    descricao: '',
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

const openProductModal = () => {
    productForm.reset();
    productForm.clearErrors();
    productForm.tipo = 'fitofarmaco';
    productForm.unidade_medida = 'L';
    productForm.estabelecimento_venda_nome = '';
    productForm.estabelecimento_venda_autorizacao = '';
    productModalOpen.value = true;
};

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
</script>

<template>
    <Head title="Stock" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-700">Recursos</p>
                    <h1 class="mt-2 text-3xl font-black text-slate-900">Stock</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-600">
                        Ajusta quantidades, cria produtos novos e controla o mínimo disponível.
                    </p>
                </div>

                <PrimaryButton
                    class="justify-center rounded-full bg-emerald-700 px-5 py-3 text-sm normal-case tracking-normal hover:bg-emerald-600 focus:bg-emerald-600"
                    @click="openProductModal"
                >
                    Novo produto
                </PrimaryButton>
            </div>
        </template>

        <div class="bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.16),_transparent_28%),linear-gradient(180deg,_#f8fafc_0%,_#eef6f1_100%)] py-10">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <div v-if="flashSuccess" class="rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800">
                    {{ flashSuccess }}
                </div>

                <section class="grid gap-4 md:grid-cols-3">
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Produtos</p>
                        <p class="mt-3 text-4xl font-black text-slate-900">{{ summary.total_produtos }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Abaixo do mínimo</p>
                        <p class="mt-3 text-4xl font-black text-amber-700">{{ summary.abaixo_minimo }}</p>
                    </article>
                    <article class="rounded-[28px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                        <p class="text-sm font-medium text-slate-500">Valor total</p>
                        <p class="mt-3 text-4xl font-black text-emerald-700">{{ formatNumber(summary.valor_total) }} €</p>
                    </article>
                </section>

                <section class="rounded-[32px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                    <div class="grid gap-4 md:grid-cols-[1fr_0.8fr_auto]">
                        <div>
                            <InputLabel value="Pesquisar" />
                            <TextInput v-model="filterState.search" class="mt-2 block w-full rounded-2xl border-slate-200" placeholder="Nome, tipo ou código" />
                        </div>
                        <div>
                            <InputLabel value="Tipo" />
                            <select v-model="filterState.tipo" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Todos</option>
                                <option v-for="tipo in tipoOptions" :key="tipo" :value="tipo">{{ tipo }}</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <SecondaryButton class="w-full justify-center rounded-full px-5 py-3 text-sm normal-case tracking-normal" @click="filterState.search = ''; filterState.tipo = ''">
                                Limpar
                            </SecondaryButton>
                        </div>
                    </div>
                </section>

                <section class="grid gap-5 lg:grid-cols-2">
                    <article
                        v-for="produto in produtos.data"
                        :key="produto.id"
                        class="rounded-[32px] border border-white/80 bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="text-2xl font-black text-slate-900">{{ produto.nome }}</h2>
                                    <span
                                        class="rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="produto.abaixo_minimo ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'"
                                    >
                                        {{ produto.abaixo_minimo ? 'baixo' : 'ok' }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">
                                    {{ produto.tipo }}
                                    <span v-if="produto.codigo_interno">· {{ produto.codigo_interno }}</span>
                                </p>
                            </div>
                            <p class="text-right text-sm font-medium text-slate-500">
                                {{ produto.ultimo_movimento_em || 'Sem movimento' }}
                            </p>
                        </div>

                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Stock atual</p>
                                <p class="mt-2 text-sm text-slate-700">{{ formatNumber(produto.stock_atual) }} {{ produto.unidade_medida }}</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Stock mínimo</p>
                                <p class="mt-2 text-sm text-slate-700">{{ formatNumber(produto.stock_minimo) }} {{ produto.unidade_medida }}</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Preço unitário</p>
                                <p class="mt-2 text-sm text-slate-700">{{ produto.custo_unitario !== null ? `${formatNumber(produto.custo_unitario)} €` : '-' }}</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Valor em stock</p>
                                <p class="mt-2 text-sm text-slate-700">{{ produto.valor_stock !== null ? `${formatNumber(produto.valor_stock)} €` : '-' }}</p>
                            </div>
                        </div>

                        <p class="mt-5 rounded-3xl bg-emerald-50/60 p-4 text-sm leading-7 text-slate-600">
                            {{ produto.ultimo_movimento_obs || 'Sem observações no último movimento.' }}
                        </p>

                        <div class="mt-5 flex flex-wrap gap-3">
                            <PrimaryButton class="rounded-full bg-slate-900 px-4 py-2 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800" @click="openStockModal(produto)">
                                Ajustar stock
                            </PrimaryButton>
                        </div>
                    </article>
                </section>

                <section v-if="!produtos.data.length" class="rounded-[32px] border border-dashed border-slate-300 bg-white/70 px-6 py-12 text-center text-sm leading-7 text-slate-600">
                    Nenhum produto encontrado com os filtros atuais.
                </section>

                <Pagination v-if="produtos.links?.length > 3" :links="produtos.links" />
            </div>
        </div>

        <Modal :show="productModalOpen" max-width="2xl" @close="closeProductModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-black text-slate-900">Novo produto</h2>
                <p class="mt-2 text-sm text-slate-500">Cria o produto e define já o stock inicial.</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitProduct">
                    <div v-if="productErrors.length" class="sm:col-span-2 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível guardar o produto.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in productErrors" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Nome" />
                        <TextInput v-model="productForm.nome" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <TextInput v-model="productForm.tipo" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.tipo" />
                    </div>
                    <div>
                        <InputLabel value="Unidade" />
                        <TextInput v-model="productForm.unidade_medida" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.unidade_medida" />
                    </div>
                    <div>
                        <InputLabel value="Preço unitário (€)" />
                        <TextInput v-model="productForm.custo_unitario" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.custo_unitario" />
                    </div>
                    <div>
                        <InputLabel value="Stock mínimo" />
                        <TextInput v-model="productForm.stock_minimo" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.stock_minimo" />
                    </div>
                    <div>
                        <InputLabel value="Stock inicial" />
                        <TextInput v-model="productForm.quantidade_inicial" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.quantidade_inicial" />
                    </div>
                    <div>
                        <InputLabel value="Código interno" />
                        <TextInput v-model="productForm.codigo_interno" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.codigo_interno" />
                    </div>
                    <div>
                        <InputLabel value="N.º DGAV" />
                        <TextInput v-model="productForm.numero_autorizacao_dgav" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.numero_autorizacao_dgav" />
                    </div>
                    <div v-if="productForm.tipo === 'fitofarmaco'" class="sm:col-span-2">
                        <InputLabel value="Estabelecimento de venda" />
                        <TextInput v-model="productForm.estabelecimento_venda_nome" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.estabelecimento_venda_nome" />
                    </div>
                    <div v-if="productForm.tipo === 'fitofarmaco'" class="sm:col-span-2">
                        <InputLabel value="N.º autorização do estabelecimento" />
                        <TextInput v-model="productForm.estabelecimento_venda_autorizacao" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="productForm.errors.estabelecimento_venda_autorizacao" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Descrição" />
                        <textarea v-model="productForm.descricao" rows="3" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="productForm.errors.descricao" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="closeProductModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="rounded-full bg-emerald-700 px-4 py-2 text-sm normal-case tracking-normal hover:bg-emerald-600 focus:bg-emerald-600" :disabled="productForm.processing">
                            Guardar produto
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="stockModalOpen" max-width="2xl" @close="closeStockModal">
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-black text-slate-900">Ajustar stock</h2>
                <p class="mt-2 text-sm text-slate-500">{{ editingProduto?.nome || 'Produto' }}</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitStock">
                    <div v-if="stockErrors.length" class="sm:col-span-2 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Não foi possível atualizar o stock.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="message in stockErrors" :key="message">{{ message }}</li>
                        </ul>
                    </div>

                    <div>
                        <InputLabel value="Modo" />
                        <select v-model="stockForm.ajuste_tipo" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="adicionar">Adicionar stock</option>
                            <option value="definir_total">Definir total</option>
                        </select>
                        <InputError class="mt-2" :message="stockForm.errors.ajuste_tipo" />
                    </div>
                    <div>
                        <InputLabel :value="stockForm.ajuste_tipo === 'adicionar' ? 'Quantidade a adicionar' : 'Quantidade total'" />
                        <TextInput v-model="stockForm.quantidade" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="stockForm.errors.quantidade" />
                    </div>
                    <div>
                        <InputLabel value="Unidade" />
                        <TextInput v-model="stockForm.unidade_medida" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="stockForm.errors.unidade_medida" />
                    </div>
                    <div>
                        <InputLabel value="Stock mínimo" />
                        <TextInput v-model="stockForm.stock_minimo" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="stockForm.errors.stock_minimo" />
                    </div>
                    <div>
                        <InputLabel value="Preço unitário (€)" />
                        <TextInput v-model="stockForm.custo_unitario" type="number" step="0.01" min="0" class="mt-2 block w-full rounded-2xl" />
                        <InputError class="mt-2" :message="stockForm.errors.custo_unitario" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Observações" />
                        <textarea v-model="stockForm.observacoes" rows="3" class="mt-2 block w-full rounded-2xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                        <InputError class="mt-2" :message="stockForm.errors.observacoes" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-3">
                        <SecondaryButton type="button" class="rounded-full px-4 py-2 text-sm normal-case tracking-normal" @click="closeStockModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="rounded-full bg-slate-900 px-4 py-2 text-sm normal-case tracking-normal hover:bg-slate-800 focus:bg-slate-800" :disabled="stockForm.processing">
                            Guardar ajuste
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
