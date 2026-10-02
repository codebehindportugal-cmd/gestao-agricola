<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    fornecedor: { type: Object, required: true },
    saldo: { type: Object, required: true },
    faturas: { type: Array, default: () => [] },
    pagamentos: { type: Array, default: () => [] },
    extrato: { type: Array, default: () => [] },
    pagasNoAto: { type: Array, default: () => [] },
    outrosFornecedores: { type: Array, default: () => [] },
    metodos: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const euro = (v) => new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(Number(v ?? 0));
const data = (d) => (d ? new Date(`${d}T12:00:00`).toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—');
const hoje = () => new Date().toISOString().slice(0, 10);

const nomeMetodo = (m) => ({
    transferencia: 'Transferência',
    numerario: 'Numerário',
    cheque: 'Cheque',
    multibanco: 'Multibanco',
    debito_direto: 'Débito direto',
    outro: 'Outro',
}[m] ?? m ?? '');

const estados = {
    por_pagar: { texto: 'Por pagar', classe: 'bg-red-50 text-red-800' },
    parcial: { texto: 'Pago em parte', classe: 'bg-ocre-100 text-ocre-700' },
    paga: { texto: 'Paga', classe: 'bg-verde-100 text-verde-800' },
};

// ── Separadores ─────────────────────────────────────────────────────────────
const separador = ref('faturas');
const verTodas = ref(false);
const faturasVisiveis = computed(() => (verTodas.value ? [...props.faturas].reverse() : props.faturas.filter((f) => f.estado !== 'paga')));
const emAberto = computed(() => props.faturas.filter((f) => f.estado !== 'paga'));

// ── Registar recibo ─────────────────────────────────────────────────────────
const reciboAberto = ref(false);
const escolhidas = reactive({}); // despesa_id -> valor (string) | undefined

const recibo = useForm({
    data: hoje(),
    valor: '',
    numero_recibo: '',
    metodo: 'transferencia',
    notas: '',
    ficheiro: null,
    imputar_automaticamente: true,
    faturas: [],
});

const abrirRecibo = (fatura = null) => {
    recibo.reset();
    recibo.clearErrors();
    recibo.data = hoje();
    Object.keys(escolhidas).forEach((k) => delete escolhidas[k]);
    valorSegueSoma.value = true;
    if (fatura) {
        escolhidas[fatura.id] = String(fatura.em_falta);
        recibo.valor = String(fatura.em_falta);
    }
    reciboAberto.value = true;
};

// Enquanto o valor nao for escrito a mao, acompanha a soma das faturas marcadas.
const valorSegueSoma = ref(true);

const alternar = (fatura) => {
    if (escolhidas[fatura.id] !== undefined) {
        delete escolhidas[fatura.id];
    } else {
        escolhidas[fatura.id] = String(fatura.em_falta);
    }
    if (valorSegueSoma.value) {
        recibo.valor = somaEscolhidas.value ? somaEscolhidas.value.toFixed(2) : '';
    }
};

const somaEscolhidas = computed(() => Object.values(escolhidas).reduce((s, v) => s + Number(v || 0), 0));
const nEscolhidas = computed(() => Object.keys(escolhidas).length);
const diferenca = computed(() => Math.round((Number(recibo.valor || 0) - somaEscolhidas.value) * 100) / 100);

const usarSoma = () => {
    recibo.valor = somaEscolhidas.value.toFixed(2);
};

const guardarRecibo = () => {
    recibo
        .transform((d) => ({
            ...d,
            imputar_automaticamente: d.imputar_automaticamente ? 1 : 0,
            faturas: Object.entries(escolhidas).map(([id, valor]) => ({ despesa_id: Number(id), valor: valor === '' ? null : valor })),
        }))
        .post(route('app.fornecedores.pagamentos.store', props.fornecedor.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reciboAberto.value = false;
            },
        });
};

const anularRecibo = (p) => {
    if (!window.confirm(`Anular o recibo ${p.numero_recibo || ''} de ${euro(p.valor)}? As faturas que ele pagava voltam a ficar por pagar.`)) return;
    router.delete(route('app.fornecedores.pagamentos.destroy', p.id), { preserveScroll: true });
};

const imputar = () => router.post(route('app.fornecedores.imputar', props.fornecedor.id), {}, { preserveScroll: true });

const marcarPagoNoAto = (fatura, valor) => {
    router.patch(route('app.fornecedores.pago-no-ato', fatura.id), { pago_no_ato: valor }, { preserveScroll: true });
};

// ── Juntar fornecedores ─────────────────────────────────────────────────────
const juntarAberto = ref(false);
const juntar = useForm({ outro_id: '' });
const guardarJuntar = () => {
    juntar.post(route('app.fornecedores.juntar', props.fornecedor.id), {
        preserveScroll: true,
        onSuccess: () => { juntarAberto.value = false; juntar.reset(); },
    });
};
</script>

<template>
    <Head :title="fornecedor.nome" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <Link :href="route('app.fornecedores.index')" class="text-sm font-semibold text-verde-700 no-underline hover:underline">← Fornecedores</Link>
                    <h1 class="mt-1 text-[28px] font-bold leading-tight text-slate-900">{{ fornecedor.nome }}</h1>
                    <p v-if="fornecedor.nif || fornecedor.telefone" class="mt-1 text-sm text-slate-600">
                        {{ [fornecedor.nif ? `NIF ${fornecedor.nif}` : null, fornecedor.telefone].filter(Boolean).join(' · ') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <SecondaryButton v-if="can.delete && outrosFornecedores.length" @click="juntarAberto = true">Juntar outro fornecedor</SecondaryButton>
                    <PrimaryButton v-if="can.create" @click="abrirRecibo()">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                        Registar recibo
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto flex max-w-7xl flex-col gap-5 px-4 sm:px-6 lg:px-8">
                <div v-if="flashSuccess" class="rounded-xl border border-verde-200 bg-verde-50 px-5 py-4 text-sm font-medium text-verde-800" role="status">
                    {{ flashSuccess }}
                </div>

                <section aria-label="Resumo" class="cartao grid grid-cols-2 md:grid-cols-4">
                    <div class="flex flex-col gap-1 p-4 sm:p-5">
                        <span class="text-sm text-slate-600">{{ saldo.saldo < -0.005 ? 'A nosso favor' : 'Em dívida' }}</span>
                        <span class="numero text-xl font-bold sm:text-2xl" :class="saldo.saldo > 0.005 ? 'text-red-700' : (saldo.saldo < -0.005 ? 'text-sky-700' : '')">
                            {{ euro(Math.abs(saldo.saldo)) }}
                        </span>
                        <span class="text-xs text-slate-500">{{ saldo.faturas_em_aberto }} {{ saldo.faturas_em_aberto === 1 ? 'fatura' : 'faturas' }} por pagar</span>
                    </div>
                    <div class="flex flex-col gap-1 border-l border-slate-200 p-4 sm:p-5">
                        <span class="text-sm text-slate-600">Faturado</span>
                        <span class="numero text-xl font-bold sm:text-2xl">{{ euro(saldo.faturado) }}</span>
                    </div>
                    <div class="flex flex-col gap-1 border-t border-slate-200 p-4 sm:p-5 md:border-l md:border-t-0">
                        <span class="text-sm text-slate-600">Pago</span>
                        <span class="numero text-xl font-bold sm:text-2xl">{{ euro(saldo.pago) }}</span>
                    </div>
                    <div class="flex flex-col gap-1 border-l border-t border-slate-200 p-4 sm:p-5 md:border-t-0">
                        <span class="text-sm text-slate-600">Pago sem fatura ligada</span>
                        <span class="numero text-xl font-bold sm:text-2xl" :class="saldo.por_imputar > 0.005 ? 'text-ocre-700' : 'text-slate-500'">{{ euro(saldo.por_imputar) }}</span>
                        <button
                            v-if="saldo.por_imputar > 0.005 && saldo.faturas_em_aberto > 0 && can.create"
                            type="button"
                            class="mt-1 self-start text-sm font-semibold text-verde-700 underline"
                            @click="imputar"
                        >Abater nas faturas mais antigas</button>
                    </div>
                </section>

                <section class="cartao overflow-hidden">
                    <div class="flex flex-wrap gap-1 border-b border-slate-200 px-3 pt-2" role="tablist">
                        <button
                            v-for="t in [
                                { id: 'faturas', label: `Faturas (${emAberto.length} por pagar)` },
                                { id: 'recibos', label: `Recibos (${pagamentos.length})` },
                                { id: 'extrato', label: 'Extrato' },
                                ...(pagasNoAto.length ? [{ id: 'no-ato', label: `Pagas no ato (${pagasNoAto.length})` }] : []),
                            ]"
                            :key="t.id"
                            type="button"
                            role="tab"
                            :aria-selected="separador === t.id"
                            class="-mb-px min-h-[44px] border-b-2 px-3 text-sm font-semibold"
                            :class="separador === t.id ? 'border-verde-700 text-verde-800' : 'border-transparent text-slate-600 hover:text-slate-900'"
                            @click="separador = t.id"
                        >{{ t.label }}</button>
                    </div>

                    <!-- Faturas -->
                    <div v-if="separador === 'faturas'">
                        <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                            <label class="flex min-h-[40px] items-center gap-2 text-sm text-slate-700">
                                <input v-model="verTodas" type="checkbox" class="rounded border-slate-300 text-verde-700 focus:ring-verde-600" />
                                Mostrar também as pagas
                            </label>
                        </div>
                        <ul class="divide-y divide-slate-100 border-t border-slate-100">
                            <li v-for="f in faturasVisiveis" :key="f.id" class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                <div class="flex min-w-0 flex-col">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-slate-900">{{ f.numero_fatura || 'Sem número' }}</span>
                                        <span class="etiqueta" :class="estados[f.estado].classe">{{ estados[f.estado].texto }}</span>
                                    </span>
                                    <span class="truncate text-sm text-slate-600">
                                        {{ data(f.data) }}<template v-if="f.estado !== 'paga' && f.dias !== null"> · há {{ f.dias }} dias</template> · {{ f.titulo }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="flex flex-col text-right">
                                        <span class="numero font-bold">{{ euro(f.estado === 'paga' ? f.valor : f.em_falta) }}</span>
                                        <span v-if="f.estado === 'parcial'" class="numero text-xs text-slate-500">de {{ euro(f.valor) }}</span>
                                    </span>
                                    <template v-if="f.estado !== 'paga'">
                                        <button v-if="can.create" type="button" class="min-h-[40px] rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-900 hover:bg-slate-50" @click="abrirRecibo(f)">Pagar</button>
                                        <button v-if="can.delete" type="button" class="min-h-[40px] px-1 text-xs text-slate-500 underline" title="Fatura-recibo ou paga ao balcão" @click="marcarPagoNoAto(f, true)">Paga no ato</button>
                                    </template>
                                </div>
                            </li>
                        </ul>
                        <p v-if="!faturasVisiveis.length" class="px-5 py-10 text-center text-sm text-slate-600">
                            {{ verTodas ? 'Sem faturas deste fornecedor.' : 'Nada por pagar a este fornecedor.' }}
                        </p>
                    </div>

                    <!-- Recibos -->
                    <div v-else-if="separador === 'recibos'">
                        <ul class="divide-y divide-slate-100">
                            <li v-for="p in pagamentos" :key="p.id" class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:px-5">
                                <div class="flex min-w-0 flex-col gap-0.5">
                                    <span class="font-semibold text-slate-900">
                                        {{ p.numero_recibo || 'Pagamento sem recibo' }}
                                        <span class="font-normal text-slate-600">· {{ data(p.data) }}<template v-if="p.metodo"> · {{ nomeMetodo(p.metodo) }}</template></span>
                                    </span>
                                    <span v-if="p.faturas.length" class="text-sm text-slate-700">
                                        Pagou: {{ p.faturas.map((f) => `${f.numero_fatura} (${euro(f.valor)})`).join(', ') }}
                                    </span>
                                    <span v-if="p.faturas_pendentes.length" class="text-sm text-ocre-700">
                                        À espera de: {{ p.faturas_pendentes.map((f) => f.numero_fatura).join(', ') }} (ainda não registadas)
                                    </span>
                                    <span v-if="p.por_imputar > 0.005" class="text-sm text-ocre-700">{{ euro(p.por_imputar) }} sem fatura ligada</span>
                                    <span v-if="p.notas" class="text-sm text-slate-500">{{ p.notas }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="numero font-bold">{{ euro(p.valor) }}</span>
                                    <a v-if="p.ficheiro_url" :href="p.ficheiro_url" target="_blank" rel="noopener" class="text-sm font-semibold text-verde-700 underline">Ver recibo</a>
                                    <button v-if="can.delete" type="button" class="min-h-[40px] px-1 text-sm text-red-700 underline" @click="anularRecibo(p)">Anular</button>
                                </div>
                            </li>
                        </ul>
                        <p v-if="!pagamentos.length" class="px-5 py-10 text-center text-sm text-slate-600">Ainda não há recibos deste fornecedor.</p>
                    </div>

                    <!-- Extrato -->
                    <div v-else-if="separador === 'extrato'" class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead class="bg-slate-50 text-xs font-semibold text-slate-600">
                                <tr>
                                    <th class="px-5 py-2.5 text-left">Data</th>
                                    <th class="px-3 py-2.5 text-left">Documento</th>
                                    <th class="px-3 py-2.5 text-left">Descrição</th>
                                    <th class="px-3 py-2.5 text-right">Fatura</th>
                                    <th class="px-3 py-2.5 text-right">Pago</th>
                                    <th class="px-5 py-2.5 text-right">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in extrato" :key="`${m.tipo}-${m.id}`" class="border-t border-slate-100">
                                    <td class="numero px-5 py-2 text-slate-600">{{ data(m.data) }}</td>
                                    <td class="px-3 py-2 font-semibold">{{ m.documento }}</td>
                                    <td class="max-w-[280px] truncate px-3 py-2 text-slate-600">{{ m.descricao }}</td>
                                    <td class="numero px-3 py-2 text-right">{{ m.debito ? euro(m.debito) : '' }}</td>
                                    <td class="numero px-3 py-2 text-right text-verde-800">{{ m.credito ? euro(m.credito) : '' }}</td>
                                    <td class="numero px-5 py-2 text-right font-semibold">{{ euro(m.saldo) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="!extrato.length" class="px-5 py-10 text-center text-sm text-slate-600">Sem movimentos.</p>
                    </div>

                    <!-- Pagas no ato -->
                    <div v-else-if="separador === 'no-ato'">
                        <p class="px-4 py-3 text-sm text-slate-600 sm:px-5">Faturas-recibo e faturas simplificadas: já vêm pagas e não contam na dívida.</p>
                        <ul class="divide-y divide-slate-100 border-t border-slate-100">
                            <li v-for="f in pagasNoAto" :key="f.id" class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                                <span class="flex min-w-0 flex-col">
                                    <span class="font-semibold">{{ f.numero_fatura || 'Sem número' }}</span>
                                    <span class="truncate text-sm text-slate-600">{{ data(f.data) }} · {{ f.titulo }}</span>
                                </span>
                                <span class="flex items-center gap-3">
                                    <span class="numero font-semibold">{{ euro(f.valor) }}</span>
                                    <button v-if="can.delete" type="button" class="min-h-[40px] px-1 text-xs text-slate-500 underline" @click="marcarPagoNoAto(f, false)">Afinal está por pagar</button>
                                </span>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>
        </div>

        <!-- Modal: registar recibo -->
        <Modal :show="reciboAberto" max-width="2xl" @close="reciboAberto = false">
            <form class="p-6 sm:p-8" @submit.prevent="guardarRecibo">
                <h2 class="text-2xl font-bold text-slate-900">Registar recibo</h2>
                <p class="mt-1 text-sm text-slate-600">{{ fornecedor.nome }} — marca as faturas que o recibo diz pagar.</p>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Data do recibo" />
                        <TextInput v-model="recibo.data" type="date" class="mt-2 block w-full" required />
                        <InputError class="mt-2" :message="recibo.errors.data" />
                    </div>
                    <div>
                        <InputLabel value="Valor pago (€)" />
                        <TextInput v-model="recibo.valor" type="number" step="0.01" min="0.01" class="mt-2 block w-full" required @input="valorSegueSoma = false" />
                        <InputError class="mt-2" :message="recibo.errors.valor" />
                    </div>
                    <div>
                        <InputLabel value="N.º do recibo" />
                        <TextInput v-model="recibo.numero_recibo" class="mt-2 block w-full" placeholder="RC 2026/123" />
                        <InputError class="mt-2" :message="recibo.errors.numero_recibo" />
                    </div>
                    <div>
                        <InputLabel value="Como foi pago" />
                        <select v-model="recibo.metodo" class="mt-2 block min-h-[44px] w-full rounded-lg border-slate-300 focus:border-verde-600 focus:ring-verde-600">
                            <option v-for="m in metodos" :key="m" :value="m">{{ nomeMetodo(m) }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Foto do recibo (opcional)" />
                        <input type="file" accept="image/*,application/pdf" class="mt-2 block w-full text-sm" @change="(e) => (recibo.ficheiro = e.target.files[0] ?? null)" />
                        <InputError class="mt-2" :message="recibo.errors.ficheiro" />
                    </div>
                </div>

                <fieldset class="mt-6">
                    <legend class="text-sm font-semibold text-slate-800">Faturas pagas por este recibo</legend>
                    <ul v-if="emAberto.length" class="mt-2 max-h-72 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-200">
                        <li v-for="f in emAberto" :key="f.id" class="flex items-center gap-3 px-3 py-2">
                            <input
                                :id="`f-${f.id}`"
                                type="checkbox"
                                class="h-5 w-5 rounded border-slate-300 text-verde-700 focus:ring-verde-600"
                                :checked="escolhidas[f.id] !== undefined"
                                @change="alternar(f)"
                            />
                            <label :for="`f-${f.id}`" class="flex min-w-0 flex-1 flex-col text-sm">
                                <span class="font-semibold">{{ f.numero_fatura || 'Sem número' }}</span>
                                <span class="text-slate-600">{{ data(f.data) }} · falta {{ euro(f.em_falta) }}</span>
                            </label>
                            <TextInput
                                v-if="escolhidas[f.id] !== undefined"
                                v-model="escolhidas[f.id]"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="w-28 text-right"
                                :aria-label="`Valor pago da fatura ${f.numero_fatura}`"
                            />
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-slate-600">Não há faturas por pagar a este fornecedor; o valor fica a seu favor.</p>

                    <div v-if="nEscolhidas" class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                        <span>Soma das faturas marcadas: <strong class="numero">{{ euro(somaEscolhidas) }}</strong></span>
                        <span v-if="recibo.valor !== '' && Math.abs(diferenca) > 0.005" :class="diferenca > 0 ? 'text-ocre-700' : 'text-red-700'">
                            {{ diferenca > 0 ? `Sobram ${euro(diferenca)} sem fatura` : `Faltam ${euro(-diferenca)} no recibo` }}
                        </span>
                        <button v-if="Math.abs(diferenca) > 0.005" type="button" class="font-semibold text-verde-700 underline" @click="usarSoma">Usar a soma como valor</button>
                    </div>
                    <label v-else-if="emAberto.length" class="mt-3 flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="recibo.imputar_automaticamente" type="checkbox" class="rounded border-slate-300 text-verde-700 focus:ring-verde-600" />
                        Sem faturas marcadas, abater nas mais antigas
                    </label>
                </fieldset>

                <div class="mt-4">
                    <InputLabel value="Notas" />
                    <textarea v-model="recibo.notas" rows="2" class="mt-2 block w-full rounded-lg border-slate-300 focus:border-verde-600 focus:ring-verde-600" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="reciboAberto = false">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="recibo.processing">Guardar recibo</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal: juntar fornecedores -->
        <Modal :show="juntarAberto" max-width="lg" @close="juntarAberto = false">
            <form class="p-6 sm:p-8" @submit.prevent="guardarJuntar">
                <h2 class="text-2xl font-bold text-slate-900">Juntar fornecedor</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Para quando o mesmo fornecedor aparece com dois nomes. As faturas e recibos do escolhido passam para <strong>{{ fornecedor.nome }}</strong> e a ficha dele é arquivada.
                </p>
                <select v-model="juntar.outro_id" class="mt-4 block min-h-[44px] w-full rounded-lg border-slate-300 focus:border-verde-600 focus:ring-verde-600" required>
                    <option value="" disabled>Escolher fornecedor</option>
                    <option v-for="o in outrosFornecedores" :key="o.id" :value="o.id">{{ o.nome }}</option>
                </select>
                <InputError class="mt-2" :message="juntar.errors.outro_id" />
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="juntarAberto = false">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="juntar.processing || !juntar.outro_id">Juntar</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
