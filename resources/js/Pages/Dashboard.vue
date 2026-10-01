<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DashboardPolygonsMap from '@/Components/DashboardPolygonsMap.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: { type: Array, default: () => [] },
    statusCards: { type: Array, default: () => [] },
    recentOperations: { type: Array, default: () => [] },
    focusAreas: { type: Array, default: () => [] },
    mapPolygons: { type: Array, default: () => [] },
    alertas: { type: Object, default: () => ({ intervalo_seguranca: [], manutencoes: [] }) },
    despesasMes: { type: Object, default: () => ({ total: 0, count: 0, variacao: null, por_categoria: {} }) },
    resumoCampanha: { type: Object, default: null },
    atencao: { type: Array, default: () => [] },
    proximosPagamentos: { type: Array, default: () => [] },
});

const page = usePage();
const primeiroNome = computed(() => (page.props.auth.user?.name ?? '').split(' ')[0]);

const hoje = new Date();
const dataTexto = hoje.toLocaleDateString('pt-PT', { weekday: 'long', day: 'numeric', month: 'long' });
const dataHoje = dataTexto.charAt(0).toUpperCase() + dataTexto.slice(1);
const saudacao = hoje.getHours() < 13 ? 'Bom dia' : hoje.getHours() < 20 ? 'Boa tarde' : 'Boa noite';

const euros = (valor) => new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(Number(valor ?? 0));
const numero = (valor, casas = 0) => new Intl.NumberFormat('pt-PT', { minimumFractionDigits: casas, maximumFractionDigits: casas }).format(Number(valor ?? 0));

const despesasVariacao = computed(() => {
    const v = props.despesasMes.variacao;
    if (v === null || v === undefined) return null;
    if (v > 0) return { cls: 'text-red-700', label: `+${v}% que no mês anterior` };
    if (v < 0) return { cls: 'text-verde-700', label: `${v}% que no mês anterior` };
    return { cls: 'text-slate-600', label: 'igual ao mês anterior' };
});

const totalPagamentos = computed(() =>
    props.proximosPagamentos.reduce((soma, pagamento) => soma + Number(pagamento.valor ?? 0), 0),
);

const especies = computed(() => (props.resumoCampanha?.especies ?? []).filter((linha) => linha.kg > 0 || linha.custo_total > 0));
const maiorCusto = computed(() => Math.max(1, ...especies.value.map((linha) => linha.custo_total)));

// Últimos registos agrupados por dia e tipo: uma pulverização é uma linha, não 24
const ultimosRegistos = computed(() => {
    const grupos = new Map();
    for (const operacao of props.recentOperations) {
        const chave = `${operacao.dia}|${operacao.tipo}`;
        if (!grupos.has(chave)) {
            grupos.set(chave, { chave, dia: operacao.dia, tipo: operacao.tipo, parcelas: [], estado: operacao.estado });
        }
        grupos.get(chave).parcelas.push(operacao.parcela);
    }
    return [...grupos.values()].slice(0, 5).map((grupo) => ({
        ...grupo,
        data: grupo.dia ? new Date(`${grupo.dia}T12:00:00`).toLocaleDateString('pt-PT', { day: 'numeric', month: 'short' }).replace('.', '') : '—',
        onde: grupo.parcelas.length === 1 ? grupo.parcelas[0] : `${grupo.parcelas.length} parcelas`,
    }));
});

const nomeTipo = (tipo) => (tipo ? tipo.charAt(0).toUpperCase() + tipo.slice(1) : 'Operação');

const urgenciaIS = (dias) => (dias <= 3 ? 'bg-red-50 text-red-800' : dias <= 7 ? 'bg-ocre-100 text-ocre-700' : 'bg-slate-100 text-slate-700');
const urgenciaManutencao = (dias) => (dias <= 0 ? 'bg-red-50 text-red-800' : dias <= 7 ? 'bg-ocre-100 text-ocre-700' : 'bg-slate-100 text-slate-700');

const linkSeExiste = (nome, params = {}) => (route().has(nome) ? route(nome, params) : null);
</script>

<template>
    <Head title="Hoje" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-verde-700">{{ dataHoje }}</p>
                    <h1 class="mt-1 text-[28px] font-bold leading-tight text-slate-900">{{ saudacao }}<template v-if="primeiroNome">, {{ primeiroNome }}</template></h1>
                </div>
                <Link
                    v-if="linkSeExiste('app.calendario.index')"
                    :href="route('app.calendario.index')"
                    class="inline-flex min-h-[44px] items-center gap-2 self-start rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-900 no-underline hover:bg-slate-50 md:self-auto"
                >
                    <svg class="h-5 w-5 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>
                    Calendário
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto flex max-w-7xl flex-col gap-5 px-4 sm:px-6 lg:px-8">
                <!-- Atalhos para registar -->
                <section aria-label="Registar" class="grid gap-3 sm:grid-cols-3">
                    <Link
                        :href="route('app.operacoes.index', { nova: 1 })"
                        class="flex items-center gap-4 rounded-xl bg-verde-700 p-4 text-white no-underline transition hover:bg-verde-800"
                    >
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-white/15">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                        </span>
                        <span class="flex flex-col">
                            <span class="text-base font-semibold">Registar operação</span>
                            <span class="text-sm text-verde-100">Tratamento, poda, rega, colheita…</span>
                        </span>
                    </Link>
                    <Link
                        v-if="linkSeExiste('app.despesas.index')"
                        :href="route('app.despesas.index', { nova: 1 })"
                        class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 text-slate-900 no-underline transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-verde-700">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h3l2-3h6l2 3h3v13H4z" /><circle cx="12" cy="13" r="3.5" /></svg>
                        </span>
                        <span class="flex flex-col">
                            <span class="text-base font-semibold">Registar fatura</span>
                            <span class="text-sm text-slate-600">Fotografa o papel e confirma as linhas</span>
                        </span>
                    </Link>
                    <a
                        v-if="linkSeExiste('app.despesas.index')"
                        :href="`${route('app.despesas.index')}#registar-venda`"
                        class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 text-slate-900 no-underline transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-verde-700">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10h18l-2 10H5z" /><path d="m8 10 4-6 4 6" /></svg>
                        </span>
                        <span class="flex flex-col">
                            <span class="text-base font-semibold">Registar venda</span>
                            <span class="text-sm text-slate-600">Quilos, preço e comprador</span>
                        </span>
                    </a>
                </section>

                <!-- Campanha -->
                <section v-if="resumoCampanha" class="cartao" :aria-label="resumoCampanha.nome">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 sm:px-5">
                        <h2 class="text-[17px] font-semibold">
                            {{ resumoCampanha.nome }}
                            <span v-if="resumoCampanha.terminou" class="ml-2 align-middle text-sm font-normal text-slate-600">terminou a {{ resumoCampanha.fim }}</span>
                        </h2>
                        <Link :href="route('app.campanhas.index')" class="inline-flex min-h-[44px] items-center gap-1 text-sm font-semibold no-underline">
                            Custos da campanha
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg>
                        </Link>
                    </div>
                    <div class="grid grid-cols-2 lg:grid-cols-4">
                        <div class="flex flex-col gap-1 p-4 sm:p-5">
                            <span class="text-sm text-slate-600">Colhido</span>
                            <span class="numero text-xl font-bold sm:text-2xl">{{ numero(resumoCampanha.kg) }} kg</span>
                        </div>
                        <div class="flex flex-col gap-1 border-l border-slate-200 p-4 sm:p-5">
                            <span class="text-sm text-slate-600">Vendido</span>
                            <span class="numero text-xl font-bold sm:text-2xl">{{ numero(resumoCampanha.kg_vendidos) }} kg</span>
                            <span class="numero text-sm text-slate-600">
                                {{ euros(resumoCampanha.vendas) }}<template v-if="resumoCampanha.preco_medio_kg"> · {{ numero(resumoCampanha.preco_medio_kg, 2) }} €/kg</template>
                            </span>
                        </div>
                        <div class="flex flex-col gap-1 border-t border-slate-200 p-4 sm:p-5 lg:border-l lg:border-t-0">
                            <span class="text-sm text-slate-600">Custo total</span>
                            <span class="numero text-xl font-bold sm:text-2xl">{{ euros(resumoCampanha.custo_total) }}</span>
                            <span v-if="resumoCampanha.custo_kg" class="numero text-sm text-slate-600">{{ numero(resumoCampanha.custo_kg, 2) }} € por kg colhido</span>
                        </div>
                        <div class="flex flex-col gap-1 border-l border-t border-slate-200 p-4 sm:p-5 lg:border-t-0">
                            <span class="text-sm text-slate-600">Resultado até agora</span>
                            <span class="numero text-xl font-bold sm:text-2xl" :class="resumoCampanha.margem < 0 ? 'text-red-800' : 'text-verde-700'">{{ euros(resumoCampanha.margem) }}</span>
                            <span class="text-sm text-slate-600">vendas menos custos</span>
                        </div>
                    </div>
                    <div v-if="especies.length" class="flex flex-col gap-3 border-t border-slate-100 px-4 py-4 sm:px-5">
                        <span class="rotulo">Custo por espécie</span>
                        <div v-for="linha in especies" :key="linha.especie" class="grid grid-cols-[110px_minmax(0,1fr)_auto] items-center gap-3 text-sm sm:grid-cols-[140px_minmax(0,1fr)_200px]">
                            <span class="truncate text-slate-800">{{ linha.especie }}</span>
                            <span class="block h-2.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                <span class="block h-full rounded-full bg-verde-600" :style="{ width: `${Math.max(2, (linha.custo_total / maiorCusto) * 100)}%` }" />
                            </span>
                            <span class="numero text-right">
                                <b class="font-semibold">{{ euros(linha.custo_total) }}</b>
                                <span v-if="linha.custo_kg" class="hidden text-slate-600 sm:inline"> · {{ numero(linha.custo_kg, 2) }} €/kg</span>
                            </span>
                        </div>
                    </div>
                </section>

                <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)]">
                    <div class="flex flex-col gap-5">
                        <!-- Precisa de atenção -->
                        <section v-if="atencao.length || alertas.intervalo_seguranca?.length || alertas.manutencoes?.length" class="cartao">
                            <h2 class="px-4 py-3 text-[17px] font-semibold sm:px-5">Precisa de atenção</h2>
                            <ul>
                                <li v-for="item in atencao" :key="item.titulo" class="grid grid-cols-[36px_minmax(0,1fr)] gap-x-3 gap-y-1 border-t border-slate-100 px-4 py-3 sm:grid-cols-[36px_minmax(0,1fr)_auto] sm:items-center sm:px-5">
                                    <span class="row-span-2 flex h-9 w-9 items-center justify-center rounded-lg sm:row-span-1" :class="item.tom === 'aviso' ? 'bg-ocre-100 text-ocre-700' : 'bg-verde-100 text-verde-700'">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 2 20h20z" /><path d="M12 10v4M12 17h.01" /></svg>
                                    </span>
                                    <span class="flex min-w-0 flex-col gap-0.5">
                                        <span class="font-semibold text-slate-900">{{ item.titulo }}</span>
                                        <span class="text-sm text-slate-600">{{ item.texto }}</span>
                                    </span>
                                    <Link :href="item.href" class="inline-flex min-h-[40px] items-center gap-1 whitespace-nowrap text-sm font-semibold no-underline">
                                        {{ item.acao }}
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg>
                                    </Link>
                                </li>
                                <li v-for="alerta in alertas.intervalo_seguranca" :key="`is-${alerta.operacao_id}-${alerta.produto_nome}`" class="flex items-start justify-between gap-3 border-t border-slate-100 px-4 py-3 sm:px-5">
                                    <span class="flex min-w-0 flex-col gap-0.5">
                                        <span class="font-semibold">Não colher em {{ alerta.parcela_nome }} antes de {{ alerta.fim_intervalo }}</span>
                                        <span class="text-sm text-slate-600">{{ alerta.produto_nome }} aplicado a {{ alerta.data_aplicacao }}<template v-if="alerta.cultura_nome"> · {{ alerta.cultura_nome }}</template></span>
                                    </span>
                                    <span class="etiqueta shrink-0" :class="urgenciaIS(alerta.dias_restantes)">{{ alerta.dias_restantes === 0 ? 'hoje' : `${alerta.dias_restantes} dias` }}</span>
                                </li>
                                <li v-for="(manutencao, index) in alertas.manutencoes" :key="`mant-${index}`" class="flex items-start justify-between gap-3 border-t border-slate-100 px-4 py-3 sm:px-5">
                                    <span class="flex min-w-0 flex-col gap-0.5">
                                        <span class="font-semibold">{{ manutencao.maquina_nome }}: {{ manutencao.tipo }}</span>
                                        <span class="text-sm text-slate-600">{{ manutencao.dias_ate_manutencao < 0 ? 'Devia ter sido feita a' : 'Prevista para' }} {{ manutencao.proxima_manutencao }}</span>
                                    </span>
                                    <span class="etiqueta shrink-0" :class="urgenciaManutencao(manutencao.dias_ate_manutencao)">
                                        {{ manutencao.dias_ate_manutencao < 0 ? `${Math.abs(manutencao.dias_ate_manutencao)} dias atrasada` : manutencao.dias_ate_manutencao === 0 ? 'hoje' : `${manutencao.dias_ate_manutencao} dias` }}
                                    </span>
                                </li>
                            </ul>
                        </section>
                        <section v-else class="cartao px-5 py-6 text-sm text-slate-600">
                            Nada pendente. Os avisos de intervalos de segurança, revisões e dados em falta aparecem aqui.
                        </section>

                        <!-- Mapa -->
                        <section v-if="mapPolygons.length" class="cartao overflow-hidden">
                            <div class="flex items-center justify-between px-4 py-3 sm:px-5">
                                <h2 class="text-[17px] font-semibold">Mapa da exploração</h2>
                                <Link :href="route('app.parcelas.index')" class="inline-flex min-h-[44px] items-center text-sm font-semibold no-underline">Parcelas</Link>
                            </div>
                            <DashboardPolygonsMap :polygons="mapPolygons" height-class="h-[320px] lg:h-[380px]" />
                        </section>
                    </div>

                    <div class="flex flex-col gap-5">
                        <!-- Pagamentos -->
                        <section class="cartao">
                            <div class="flex items-center justify-between gap-2 px-4 py-3 sm:px-5">
                                <h2 class="text-[17px] font-semibold">Próximos pagamentos</h2>
                                <span v-if="totalPagamentos" class="numero text-sm text-slate-600">{{ euros(totalPagamentos) }}</span>
                            </div>
                            <ul v-if="proximosPagamentos.length">
                                <li v-for="pagamento in proximosPagamentos" :key="pagamento.id" class="grid grid-cols-[44px_minmax(0,1fr)_auto] items-center gap-3 border-t border-slate-100 px-4 py-3 sm:px-5">
                                    <span class="flex flex-col items-center leading-none">
                                        <b class="text-lg font-bold">{{ pagamento.dia }}</b>
                                        <span class="mt-1 text-[11px] font-semibold uppercase text-slate-500">{{ pagamento.mes }}</span>
                                    </span>
                                    <span class="flex min-w-0 flex-col gap-0.5">
                                        <span class="flex flex-wrap items-center gap-2">
                                            <span class="truncate font-semibold">{{ pagamento.titulo }}</span>
                                            <span v-if="pagamento.dias < 0" class="etiqueta bg-red-50 text-red-800">em atraso</span>
                                            <span v-else-if="pagamento.dias <= 14" class="etiqueta bg-ocre-100 text-ocre-700">{{ pagamento.dias === 0 ? 'hoje' : `em ${pagamento.dias} dias` }}</span>
                                        </span>
                                        <span class="truncate text-sm text-slate-600">{{ pagamento.detalhe }}</span>
                                    </span>
                                    <span class="numero font-semibold">{{ pagamento.valor !== null ? euros(pagamento.valor) : '—' }}</span>
                                </li>
                            </ul>
                            <p v-else class="border-t border-slate-100 px-4 py-4 text-sm text-slate-600 sm:px-5">Nada a pagar nas próximas semanas.</p>
                        </section>

                        <!-- Despesas do mês -->
                        <section v-if="despesasMes.count > 0 || despesasMes.total > 0" class="cartao px-4 py-4 sm:px-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex flex-col gap-1">
                                    <span class="text-sm text-slate-600">Despesas deste mês</span>
                                    <span class="numero text-xl font-bold sm:text-2xl">{{ euros(despesasMes.total) }}</span>
                                    <span class="text-sm text-slate-600">
                                        {{ despesasMes.count }} {{ despesasMes.count === 1 ? 'fatura' : 'faturas' }}<template v-if="despesasVariacao"> · <span :class="despesasVariacao.cls">{{ despesasVariacao.label }}</span></template>
                                    </span>
                                </div>
                                <Link :href="route('app.despesas.index')" class="inline-flex min-h-[44px] items-center text-sm font-semibold no-underline">Despesas</Link>
                            </div>
                        </section>

                        <!-- Últimos registos -->
                        <section class="cartao">
                            <div class="flex items-center justify-between px-4 py-3 sm:px-5">
                                <h2 class="text-[17px] font-semibold">Últimos registos</h2>
                                <Link :href="route('app.operacoes.index')" class="inline-flex min-h-[44px] items-center text-sm font-semibold no-underline">Caderno de campo</Link>
                            </div>
                            <ul v-if="ultimosRegistos.length">
                                <li v-for="registo in ultimosRegistos" :key="registo.chave" class="grid grid-cols-[64px_minmax(0,1fr)] gap-3 border-t border-slate-100 px-4 py-3 text-sm sm:px-5">
                                    <span class="numero text-slate-600">{{ registo.data }}</span>
                                    <span class="flex min-w-0 flex-col gap-0.5">
                                        <span class="font-semibold">{{ nomeTipo(registo.tipo) }}</span>
                                        <span class="truncate text-slate-600">{{ registo.onde }}</span>
                                    </span>
                                </li>
                            </ul>
                            <p v-else class="border-t border-slate-100 px-4 py-4 text-sm text-slate-600 sm:px-5">Ainda não há operações registadas.</p>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
