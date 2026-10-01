<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    relatorio: { type: Object, required: true },
    campanhas: { type: Array, default: () => [] },
});

const campanha = computed(() => props.relatorio.campanha);
const total = computed(() => props.relatorio.total);
const rubricas = computed(() => props.relatorio.rubricas);
const porRubrica = computed(() => props.relatorio.por_rubrica);
const meses = computed(() => props.relatorio.por_mes);
const especies = computed(() => props.relatorio.por_especie?.especies ?? []);
const totalEspecies = computed(() => props.relatorio.por_especie?.total ?? null);

const eur = (v) => new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(v || 0);
const eur0 = (v) => new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(v || 0);
const num = (v, d = 0) => new Intl.NumberFormat('pt-PT', { minimumFractionDigits: d, maximumFractionDigits: d }).format(v || 0);
const dataPt = (iso) => (iso ? iso.split('-').reverse().join('/') : '—');

// So as rubricas com valor aparecem como colunas: a tabela nao precisa de
// oito colunas a zero numa campanha sem energia nem manutencao.
const rubricasComValor = computed(() =>
    Object.keys(rubricas.value).filter((r) => Math.abs(porRubrica.value[r] || 0) > 0.004),
);

const rubricasOrdenadas = computed(() =>
    rubricasComValor.value
        .map((r) => ({ chave: r, rotulo: rubricas.value[r], valor: porRubrica.value[r] }))
        .sort((a, b) => b.valor - a.valor),
);

const maxMes = computed(() => Math.max(1, ...meses.value.map((m) => Math.max(m.custos, m.vendas))));

const coresRubrica = {
    mao_obra: 'bg-emerald-500',
    maquinaria: 'bg-amber-500',
    material: 'bg-sky-500',
    produtos: 'bg-violet-500',
    energia: 'bg-yellow-400',
    manutencao: 'bg-orange-500',
    partilhados: 'bg-slate-400',
    outro: 'bg-rose-400',
};

const mudarCampanha = (event) => {
    router.get(route('app.campanhas.relatorio', event.target.value));
};

const cardClass = 'rounded-xl bg-white p-4 sm:p-5 border border-slate-200';
</script>

<template>
    <Head :title="`Relatório — ${campanha.nome}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <Link :href="route('app.campanhas.show', campanha.id)" class="mb-2 inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 hover:text-emerald-900">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        Campanha
                    </Link>
                    <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">Relatório da campanha {{ campanha.nome }}</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ dataPt(campanha.inicio) }} a {{ dataPt(campanha.fim) }}
                        <span v-if="campanha.area_ha"> · {{ num(campanha.area_ha, 2) }} ha</span>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select
                        v-if="campanhas.length > 1"
                        :value="campanha.id"
                        class="rounded-full border-slate-200 py-2 pl-4 pr-10 text-sm text-slate-700 focus:border-emerald-500 focus:ring-emerald-500"
                        @change="mudarCampanha"
                    >
                        <option v-for="c in campanhas" :key="c.id" :value="c.id">{{ c.nome }}</option>
                    </select>
                    <a
                        :href="route('app.campanhas.custos-pdf', campanha.id)"
                        class="inline-flex items-center rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-100"
                    >
                        Custos PDF
                    </a>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">

                <!-- Totais -->
                <section class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    <article :class="cardClass">
                        <p class="text-xs font-semibold text-slate-400">Custos</p>
                        <p class="mt-2 text-lg font-bold text-slate-900 sm:text-2xl">{{ eur0(total.custos) }}</p>
                    </article>
                    <article :class="cardClass">
                        <p class="text-xs font-semibold text-slate-400">Vendas</p>
                        <p class="mt-2 text-lg font-bold text-slate-900 sm:text-2xl">{{ eur0(total.vendas) }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ num(total.kg_vendidos) }} kg vendidos</p>
                    </article>
                    <article :class="cardClass">
                        <p class="text-xs font-semibold text-slate-400">Margem</p>
                        <p class="mt-2 text-lg font-bold sm:text-2xl" :class="total.margem >= 0 ? 'text-emerald-700' : 'text-rose-600'">
                            {{ eur0(total.margem) }}
                        </p>
                    </article>
                    <article :class="cardClass">
                        <p class="text-xs font-semibold text-slate-400">Colhido</p>
                        <p class="mt-2 text-lg font-bold text-slate-900 sm:text-2xl">{{ num(total.kg_colhidos) }} kg</p>
                    </article>
                    <article :class="cardClass" class="col-span-2 lg:col-span-1">
                        <p class="text-xs font-semibold text-slate-400">Custo / kg</p>
                        <p class="mt-2 text-lg font-bold text-slate-900 sm:text-2xl">{{ eur(total.custo_por_kg) }}</p>
                        <p class="mt-1 text-xs text-slate-400">venda média {{ eur(total.preco_medio) }}/kg</p>
                    </article>
                </section>

                <!-- Rubricas -->
                <section :class="cardClass">
                    <h2 class="text-sm font-bold text-slate-500">Para onde foi o dinheiro</h2>
                    <div v-if="total.custos > 0" class="mt-4 flex h-3 w-full overflow-hidden rounded-full bg-slate-100">
                        <div
                            v-for="r in rubricasOrdenadas"
                            :key="r.chave"
                            :class="coresRubrica[r.chave]"
                            :style="{ width: `${(r.valor / total.custos) * 100}%` }"
                            :title="`${r.rotulo}: ${eur(r.valor)}`"
                        />
                    </div>
                    <ul class="mt-4 grid gap-x-8 gap-y-2 sm:grid-cols-2 lg:grid-cols-4">
                        <li v-for="r in rubricasOrdenadas" :key="r.chave" class="flex items-center justify-between gap-3 text-sm">
                            <span class="flex items-center gap-2 text-slate-600">
                                <span class="h-2.5 w-2.5 rounded-full" :class="coresRubrica[r.chave]" />
                                {{ r.rotulo }}
                            </span>
                            <span class="font-semibold tabular-nums text-slate-900">
                                {{ eur0(r.valor) }}
                                <span class="ml-1 text-xs font-normal text-slate-400">{{ num((r.valor / (total.custos || 1)) * 100) }}%</span>
                            </span>
                        </li>
                    </ul>
                    <p v-if="!rubricasOrdenadas.length" class="mt-3 text-sm text-slate-500">Ainda não há custos nesta campanha.</p>
                </section>

                <!-- Mês a mês -->
                <section :class="cardClass">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-sm font-bold text-slate-500">Mês a mês</h2>
                        <p class="flex items-center gap-4 text-xs text-slate-500">
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-rose-400" /> custos</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500" /> vendas</span>
                        </p>
                    </div>

                    <div class="mt-5 flex h-40 items-end gap-1.5 sm:gap-3">
                        <div v-for="m in meses" :key="m.mes" class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1">
                            <div class="flex h-full w-full items-end justify-center gap-0.5">
                                <div class="w-1/2 max-w-[18px] rounded-t bg-rose-400" :style="{ height: `${(m.custos / maxMes) * 100}%` }" :title="`Custos ${m.rotulo}: ${eur(m.custos)}`" />
                                <div class="w-1/2 max-w-[18px] rounded-t bg-emerald-500" :style="{ height: `${(m.vendas / maxMes) * 100}%` }" :title="`Vendas ${m.rotulo}: ${eur(m.vendas)}`" />
                            </div>
                            <span class="truncate text-xs text-slate-400">{{ m.rotulo.split(' ')[0] }}</span>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-xs text-slate-400">
                                    <th class="py-2 pr-4 font-semibold">Mês</th>
                                    <th v-for="r in rubricasComValor" :key="r" class="whitespace-nowrap px-3 py-2 text-right font-semibold">{{ rubricas[r] }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">Custos</th>
                                    <th class="px-3 py-2 text-right font-semibold">Vendas</th>
                                    <th class="px-3 py-2 text-right font-semibold">Margem</th>
                                    <th class="px-3 py-2 text-right font-semibold">Colhido</th>
                                    <th class="whitespace-nowrap py-2 pl-3 text-right font-semibold">Custo acum.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in meses" :key="m.mes" class="border-b border-slate-100 tabular-nums">
                                    <td class="whitespace-nowrap py-2 pr-4 font-medium text-slate-700">{{ m.rotulo }}</td>
                                    <td v-for="r in rubricasComValor" :key="r" class="px-3 py-2 text-right text-slate-600">
                                        {{ m.rubricas[r] ? eur0(m.rubricas[r]) : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ m.custos ? eur0(m.custos) : '—' }}</td>
                                    <td class="px-3 py-2 text-right text-slate-700">{{ m.vendas ? eur0(m.vendas) : '—' }}</td>
                                    <td class="px-3 py-2 text-right" :class="m.margem >= 0 ? 'text-emerald-700' : 'text-rose-600'">
                                        {{ m.custos || m.vendas ? eur0(m.margem) : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-right text-slate-600">{{ m.kg_colhidos ? `${num(m.kg_colhidos)} kg` : '—' }}</td>
                                    <td class="py-2 pl-3 text-right text-slate-500">{{ eur0(m.custos_acumulado) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="font-bold tabular-nums text-slate-900">
                                    <td class="py-3 pr-4">Campanha</td>
                                    <td v-for="r in rubricasComValor" :key="r" class="px-3 py-3 text-right">{{ eur0(porRubrica[r]) }}</td>
                                    <td class="px-3 py-3 text-right">{{ eur0(total.custos) }}</td>
                                    <td class="px-3 py-3 text-right">{{ eur0(total.vendas) }}</td>
                                    <td class="px-3 py-3 text-right" :class="total.margem >= 0 ? 'text-emerald-700' : 'text-rose-600'">{{ eur0(total.margem) }}</td>
                                    <td class="px-3 py-3 text-right">{{ num(total.kg_colhidos) }} kg</td>
                                    <td class="py-3 pl-3" />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <!-- Por espécie -->
                <section v-if="especies.length" :class="cardClass">
                    <h2 class="text-sm font-bold text-slate-500">Por espécie</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-xs text-slate-400">
                                    <th class="py-2 pr-4 font-semibold">Espécie</th>
                                    <th class="px-3 py-2 text-right font-semibold">Área</th>
                                    <th class="px-3 py-2 text-right font-semibold">Colhido</th>
                                    <th class="px-3 py-2 text-right font-semibold">Custos</th>
                                    <th class="px-3 py-2 text-right font-semibold">Custo/kg</th>
                                    <th class="px-3 py-2 text-right font-semibold">Vendas</th>
                                    <th class="px-3 py-2 text-right font-semibold">Venda/kg</th>
                                    <th class="py-2 pl-3 text-right font-semibold">Margem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="e in especies" :key="e.especie" class="border-b border-slate-100 tabular-nums">
                                    <td class="py-2 pr-4 font-medium text-slate-700">{{ e.especie }}</td>
                                    <td class="px-3 py-2 text-right text-slate-600">{{ e.area_ha ? `${num(e.area_ha, 2)} ha` : '—' }}</td>
                                    <td class="px-3 py-2 text-right text-slate-600">{{ e.kg ? `${num(e.kg)} kg` : '—' }}</td>
                                    <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ eur0(e.custo_total) }}</td>
                                    <td class="px-3 py-2 text-right text-slate-600">{{ e.custo_kg ? eur(e.custo_kg) : '—' }}</td>
                                    <td class="px-3 py-2 text-right text-slate-700">{{ e.vendas ? eur0(e.vendas) : '—' }}</td>
                                    <td class="px-3 py-2 text-right text-slate-600">{{ e.preco_medio_kg ? eur(e.preco_medio_kg) : '—' }}</td>
                                    <td class="py-2 pl-3 text-right" :class="e.margem >= 0 ? 'text-emerald-700' : 'text-rose-600'">{{ eur0(e.margem) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p
                        v-if="totalEspecies && Math.abs(totalEspecies.custo_total - total.custos) > 1"
                        class="mt-3 text-xs text-slate-500"
                    >
                        A soma por espécie ({{ eur0(totalEspecies.custo_total) }}) não inclui a parte dos custos partilhados de outras campanhas
                        ({{ eur0(total.custos - totalEspecies.custo_total) }}), que só entra no total da campanha.
                    </p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
