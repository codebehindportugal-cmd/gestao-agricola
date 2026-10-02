<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    fornecedores: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
    resumo: { type: Object, required: true },
    faturasSemFornecedor: { type: Number, default: 0 },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const pesquisa = ref(props.filters.search ?? '');
const soComDivida = ref(true);

let temporizador = null;
watch(pesquisa, (valor) => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => {
        router.get(route('app.fornecedores.index'), { search: valor || undefined }, { preserveState: true, replace: true, preserveScroll: true });
    }, 300);
});

const lista = computed(() => (soComDivida.value
    ? props.fornecedores.filter((f) => Math.abs(f.saldo) > 0.005 || f.faturas_em_aberto > 0)
    : props.fornecedores));

const euro = (v) => new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(Number(v ?? 0));
const data = (d) => (d ? new Date(`${d}T12:00:00`).toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—');
const diasDesde = (d) => (d ? Math.floor((Date.now() - new Date(`${d}T12:00:00`).getTime()) / 86400000) : null);

const corSaldo = (f) => {
    if (f.saldo < -0.005) return 'text-sky-700';
    if (f.saldo <= 0.005) return 'text-slate-500';
    const dias = diasDesde(f.em_aberto_desde);
    return dias !== null && dias > 90 ? 'text-red-700' : 'text-slate-900';
};
</script>

<template>
    <Head title="Fornecedores" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-sm font-semibold text-verde-700">Dinheiro</p>
                <h1 class="mt-1 text-[28px] font-bold leading-tight text-slate-900">Fornecedores</h1>
                <p class="mt-1 text-sm text-slate-600">Quanto se deve a cada um: faturas de compra menos os recibos.</p>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto flex max-w-7xl flex-col gap-5 px-4 sm:px-6 lg:px-8">
                <div v-if="flashSuccess" class="rounded-xl border border-verde-200 bg-verde-50 px-5 py-4 text-sm font-medium text-verde-800" role="status">
                    {{ flashSuccess }}
                </div>

                <section aria-label="Resumo" class="cartao grid grid-cols-2 md:grid-cols-4">
                    <div class="flex flex-col gap-1 p-4 sm:p-5">
                        <span class="text-sm text-slate-600">Em dívida</span>
                        <span class="numero text-xl font-bold sm:text-2xl" :class="resumo.em_divida > 0 ? 'text-red-700' : ''">{{ euro(resumo.em_divida) }}</span>
                    </div>
                    <div class="flex flex-col gap-1 border-l border-slate-200 p-4 sm:p-5">
                        <span class="text-sm text-slate-600">Fornecedores a quem se deve</span>
                        <span class="numero text-xl font-bold sm:text-2xl">{{ resumo.com_divida }}</span>
                    </div>
                    <div class="flex flex-col gap-1 border-t border-slate-200 p-4 sm:p-5 md:border-l md:border-t-0">
                        <span class="text-sm text-slate-600">Faturas por pagar</span>
                        <span class="numero text-xl font-bold sm:text-2xl">{{ resumo.faturas_em_aberto }}</span>
                    </div>
                    <div class="flex flex-col gap-1 border-l border-t border-slate-200 p-4 sm:p-5 md:border-t-0">
                        <span class="text-sm text-slate-600">Mais antiga por pagar</span>
                        <span class="numero text-xl font-bold sm:text-2xl">{{ data(resumo.em_aberto_desde) }}</span>
                    </div>
                </section>

                <p v-if="faturasSemFornecedor" class="rounded-xl border border-ocre-200 bg-ocre-50 px-5 py-3 text-sm text-ocre-700">
                    {{ faturasSemFornecedor }} {{ faturasSemFornecedor === 1 ? 'fatura não tem' : 'faturas não têm' }} fornecedor e não entram nestas contas.
                    Preenche o fornecedor em <Link :href="route('app.despesas.index')" class="font-semibold underline">Despesas</Link>.
                </p>

                <section class="cartao overflow-hidden">
                    <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <label class="relative flex w-full items-center sm:max-w-sm">
                            <span class="sr-only">Pesquisar</span>
                            <svg class="pointer-events-none absolute left-3 h-5 w-5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                            <TextInput v-model="pesquisa" type="search" class="block w-full pl-10" placeholder="Nome ou NIF" />
                        </label>
                        <label class="flex min-h-[44px] items-center gap-2 text-sm text-slate-700">
                            <input v-model="soComDivida" type="checkbox" class="rounded border-slate-300 text-verde-700 focus:ring-verde-600" />
                            Só com contas em aberto
                        </label>
                    </div>

                    <!-- Tabela (computador) -->
                    <div class="hidden md:block" role="table" aria-label="Fornecedores">
                        <div role="row" class="grid grid-cols-[minmax(0,2fr)_110px_110px_120px_90px_110px_110px] gap-4 border-b border-slate-200 bg-slate-50 px-5 py-2.5 text-xs font-semibold text-slate-600">
                            <span role="columnheader">Fornecedor</span>
                            <span role="columnheader" class="text-right">Faturado</span>
                            <span role="columnheader" class="text-right">Pago</span>
                            <span role="columnheader" class="text-right">Em dívida</span>
                            <span role="columnheader" class="text-right">Por pagar</span>
                            <span role="columnheader" class="text-right">Desde</span>
                            <span role="columnheader" class="text-right">Último recibo</span>
                        </div>
                        <Link
                            v-for="f in lista"
                            :key="f.id"
                            :href="route('app.fornecedores.show', f.id)"
                            role="row"
                            class="grid min-h-[56px] grid-cols-[minmax(0,2fr)_110px_110px_120px_90px_110px_110px] items-center gap-4 border-b border-slate-100 px-5 py-2 text-sm text-slate-800 no-underline last:border-b-0 hover:bg-slate-50"
                        >
                            <span role="cell" class="flex min-w-0 flex-col">
                                <span class="truncate font-semibold text-slate-900">{{ f.nome }}</span>
                                <span v-if="f.nif" class="text-xs text-slate-500">NIF {{ f.nif }}</span>
                            </span>
                            <span role="cell" class="numero text-right text-slate-600">{{ euro(f.faturado) }}</span>
                            <span role="cell" class="numero text-right text-slate-600">{{ euro(f.pago) }}</span>
                            <span role="cell" class="numero text-right font-bold" :class="corSaldo(f)">
                                {{ f.saldo < -0.005 ? `${euro(-f.saldo)} a favor` : euro(f.saldo) }}
                            </span>
                            <span role="cell" class="numero text-right">{{ f.faturas_em_aberto }}</span>
                            <span role="cell" class="numero text-right text-slate-600">{{ data(f.em_aberto_desde) }}</span>
                            <span role="cell" class="numero text-right text-slate-600">{{ data(f.ultimo_pagamento) }}</span>
                        </Link>
                    </div>

                    <!-- Lista (telemóvel) -->
                    <ul class="divide-y divide-slate-100 md:hidden">
                        <li v-for="f in lista" :key="`m-${f.id}`">
                            <Link :href="route('app.fornecedores.show', f.id)" class="flex items-center justify-between gap-3 px-4 py-3 text-slate-800 no-underline">
                                <span class="flex min-w-0 flex-col gap-0.5">
                                    <span class="truncate font-semibold">{{ f.nome }}</span>
                                    <span class="text-sm text-slate-600">
                                        {{ f.faturas_em_aberto }} por pagar<template v-if="f.em_aberto_desde"> · desde {{ data(f.em_aberto_desde) }}</template>
                                    </span>
                                </span>
                                <span class="numero shrink-0 font-bold" :class="corSaldo(f)">
                                    {{ f.saldo < -0.005 ? `${euro(-f.saldo)} a favor` : euro(f.saldo) }}
                                </span>
                            </Link>
                        </li>
                    </ul>

                    <p v-if="!lista.length" class="px-5 py-10 text-center text-sm text-slate-600">
                        {{ soComDivida ? 'Não há contas em aberto com fornecedores.' : 'Nenhum fornecedor com faturas ou recibos.' }}
                    </p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
