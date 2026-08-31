<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    ano: Number,
    mes: Number,
    inicioGrelha: String,
    fimGrelha: String,
    filtros: { type: Object, default: () => ({}) },
    compromissos: { type: Array, default: () => [] },
    proximos: { type: Array, default: () => [] },
    atrasados: { type: Array, default: () => [] },
    resumo: { type: Object, default: () => ({}) },
    opcoes: { type: Object, default: () => ({}) },
    permissoes: { type: Object, default: () => ({}) },
});

const MESES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
const DIAS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

const ROTULOS_CATEGORIA = {
    pagamento: 'Pagamento',
    tarefa_agricola: 'Tarefa agrícola',
    manutencao: 'Manutenção',
    prazo_legal: 'Prazo legal',
};

const ROTULOS_RECORRENCIA = {
    nenhuma: 'Não repete',
    mensal: 'Mensal',
    trimestral: 'Trimestral',
    semestral: 'Semestral',
    anual: 'Anual',
    personalizada: 'Personalizada',
};

// Cor por categoria — a barra lateral de cada cartão.
const CORES = {
    pagamento: 'border-l-amber-500 bg-amber-50 text-amber-900',
    tarefa_agricola: 'border-l-emerald-600 bg-emerald-50 text-emerald-900',
    manutencao: 'border-l-sky-600 bg-sky-50 text-sky-900',
    prazo_legal: 'border-l-violet-600 bg-violet-50 text-violet-900',
};

// toISOString() converte para UTC: em Portugal (UTC+1 no verao) a meia-noite
// local cai no dia anterior em UTC e a grelha saia toda deslocada um dia.
// Formatamos a partir das componentes locais e ancoramos o cursor ao meio-dia,
// que tambem imuniza contra as mudancas da hora de verao.
const paraIso = (data) => {
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');
    return `${ano}-${mes}-${dia}`;
};

const hojeIso = paraIso(new Date());

const dias = computed(() => {
    const resultado = [];
    const fim = new Date(props.fimGrelha + 'T12:00:00');
    const cursor = new Date(props.inicioGrelha + 'T12:00:00');

    while (cursor <= fim) {
        const iso = paraIso(cursor);
        resultado.push({
            iso,
            numero: cursor.getDate(),
            doMes: cursor.getMonth() + 1 === props.mes,
            hoje: iso === hojeIso,
            itens: props.compromissos.filter((c) => c.data === iso),
        });
        cursor.setDate(cursor.getDate() + 1);
    }

    return resultado;
});

const euros = (valor) => {
    if (valor === null || valor === undefined || valor === '') return '';
    return new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(Number(valor));
};

const dataCurta = (iso) => {
    if (!iso) return '';
    const [a, m, d] = iso.split('-');
    return `${d}/${m}/${a}`;
};

const navegar = (delta) => {
    let mes = props.mes + delta;
    let ano = props.ano;
    if (mes < 1) { mes = 12; ano -= 1; }
    if (mes > 12) { mes = 1; ano += 1; }
    router.get(route('app.calendario.index'), { ...props.filtros, mes, ano }, { preserveState: true, preserveScroll: true });
};

const irParaHoje = () => {
    const agora = new Date();
    router.get(route('app.calendario.index'), { ...props.filtros, mes: agora.getMonth() + 1, ano: agora.getFullYear() }, { preserveState: true });
};

const filtroCategoria = ref(props.filtros.categoria ?? '');
const filtroEstado = ref(props.filtros.estado ?? '');

const aplicarFiltros = () => {
    router.get(route('app.calendario.index'), {
        mes: props.mes,
        ano: props.ano,
        categoria: filtroCategoria.value || undefined,
        estado: filtroEstado.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};

// ── Formulário ───────────────────────────────────────────────────────────────

const mostrarForm = ref(false);
const emEdicao = ref(null);
const aGuardar = ref(false);

// Este formulario tem um campo chamado "data", que colide com o metodo
// form.data() do useForm() do Inertia. Por isso usa-se estado simples + router,
// e os erros vem das props da pagina.
const page = usePage();
const erros = computed(() => page.props.errors ?? {});

const formVazio = () => ({
    titulo: '',
    descricao: '',
    categoria: 'pagamento',
    tipo: '',
    entidade: '',
    data: hojeIso,
    hora: '',
    valor: '',
    recorrencia: 'nenhuma',
    recorrencia_intervalo: '',
    recorrencia_unidade: 'mes',
    recorrencia_fim: '',
    antecedencia_aviso_dias: '7',
    campanha_id: '',
    parcela_id: '',
    cultura_id: '',
    maquina_id: '',
    funcionario_id: '',
    notas: '',
});

const form = ref(formVazio());

const abrirNovo = (dataIso = null) => {
    emEdicao.value = null;
    form.value = formVazio();
    if (dataIso) form.value.data = dataIso;
    mostrarForm.value = true;
};

const abrirEdicao = (item) => {
    emEdicao.value = item;
    const base = formVazio();

    Object.keys(base).forEach((chave) => {
        if (item[chave] !== null && item[chave] !== undefined) {
            // Os TextInput esperam strings; os numeros vindos do servidor
            // disparavam avisos de tipo.
            base[chave] = typeof item[chave] === 'number' ? String(item[chave]) : item[chave];
        }
    });

    form.value = base;
    mostrarForm.value = true;
};

const submeter = () => {
    aGuardar.value = true;

    const opcoes = {
        preserveScroll: true,
        onSuccess: () => { mostrarForm.value = false; },
        onFinish: () => { aGuardar.value = false; },
    };

    if (emEdicao.value) {
        router.patch(route('app.calendario.update', emEdicao.value.id), form.value, opcoes);
    } else {
        router.post(route('app.calendario.store'), form.value, opcoes);
    }
};

// ── Concluir ────────────────────────────────────────────────────────────────

const mostrarConcluir = ref(false);
const aConcluir = ref(null);

const formConcluir = useForm({
    valor_pago: '',
    data_conclusao: hojeIso,
    criar_custo: true,
});

const abrirConcluir = (item) => {
    aConcluir.value = item;
    formConcluir.clearErrors();
    formConcluir.valor_pago = item.valor ?? '';
    formConcluir.data_conclusao = hojeIso;
    formConcluir.criar_custo = true;
    mostrarConcluir.value = true;
};

const confirmarConcluir = () => {
    formConcluir.post(route('app.calendario.concluir', aConcluir.value.id), {
        preserveScroll: true,
        onSuccess: () => { mostrarConcluir.value = false; },
    });
};

const reabrir = (item) => {
    router.post(route('app.calendario.reabrir', item.id), {}, { preserveScroll: true });
};

const eliminar = (item, serie = false) => {
    const aviso = serie
        ? 'Eliminar esta série e todas as ocorrências pendentes?'
        : 'Eliminar este compromisso?';

    if (!window.confirm(aviso)) return;

    router.delete(route('app.calendario.destroy', item.id), {
        data: { serie },
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Calendário" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Calendário</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Resumo -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-lg shadow p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">A pagar este mês</p>
                        <p class="text-2xl font-semibold text-gray-900 mt-1">{{ euros(resumo.por_pagar_mes) || '—' }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Total do mês</p>
                        <p class="text-2xl font-semibold text-gray-900 mt-1">{{ euros(resumo.total_mes) || '—' }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Pendentes</p>
                        <p class="text-2xl font-semibold text-gray-900 mt-1">{{ resumo.pendentes ?? 0 }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4" :class="resumo.atrasados > 0 ? 'ring-2 ring-red-400' : ''">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Em atraso</p>
                        <p class="text-2xl font-semibold mt-1" :class="resumo.atrasados > 0 ? 'text-red-600' : 'text-gray-900'">
                            {{ resumo.atrasados ?? 0 }}
                        </p>
                    </div>
                </div>

                <!-- Barra de controlo -->
                <div class="bg-white rounded-lg shadow p-4 flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <SecondaryButton @click="navegar(-1)">&larr;</SecondaryButton>
                        <span class="text-lg font-semibold text-gray-800 min-w-[10rem] text-center">
                            {{ MESES[mes - 1] }} {{ ano }}
                        </span>
                        <SecondaryButton @click="navegar(1)">&rarr;</SecondaryButton>
                        <SecondaryButton @click="irParaHoje">Hoje</SecondaryButton>
                    </div>

                    <div class="flex items-center gap-2 ml-auto">
                        <select v-model="filtroCategoria" @change="aplicarFiltros"
                            class="border-gray-300 rounded-md text-sm">
                            <option value="">Todas as categorias</option>
                            <option v-for="c in opcoes.categorias" :key="c" :value="c">{{ ROTULOS_CATEGORIA[c] }}</option>
                        </select>
                        <select v-model="filtroEstado" @change="aplicarFiltros"
                            class="border-gray-300 rounded-md text-sm">
                            <option value="">Todos os estados</option>
                            <option value="pendente">Pendente</option>
                            <option value="concluido">Concluído</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                        <PrimaryButton v-if="permissoes.criar" @click="abrirNovo()">Novo</PrimaryButton>
                    </div>
                </div>

                <!-- Atrasados -->
                <div v-if="atrasados.length" class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <h3 class="font-semibold text-red-800 mb-2">Em atraso ({{ atrasados.length }})</h3>
                    <ul class="space-y-1">
                        <li v-for="item in atrasados" :key="item.id"
                            class="flex items-center gap-3 text-sm text-red-900">
                            <span class="font-medium">{{ dataCurta(item.data) }}</span>
                            <span>{{ item.titulo }}</span>
                            <span v-if="item.valor" class="font-semibold">{{ euros(item.valor) }}</span>
                            <button v-if="permissoes.editar" class="ml-auto text-xs underline"
                                @click="abrirConcluir(item)">marcar feito</button>
                        </li>
                    </ul>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <!-- Grelha do mês -->
                    <div class="xl:col-span-2 bg-white rounded-lg shadow overflow-hidden">
                        <div class="grid grid-cols-7 border-b bg-gray-50">
                            <div v-for="d in DIAS" :key="d"
                                class="px-2 py-2 text-xs font-semibold text-gray-500 text-center uppercase">
                                {{ d }}
                            </div>
                        </div>
                        <div class="grid grid-cols-7">
                            <div v-for="dia in dias" :key="dia.iso"
                                class="min-h-[7rem] border-b border-r p-1.5 align-top"
                                :class="[dia.doMes ? 'bg-white' : 'bg-gray-50 text-gray-400', dia.hoje ? 'ring-2 ring-inset ring-emerald-500' : '']">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-semibold" :class="dia.hoje ? 'text-emerald-700' : ''">
                                        {{ dia.numero }}
                                    </span>
                                    <button v-if="permissoes.criar && dia.doMes"
                                        class="text-xs text-gray-300 hover:text-emerald-600"
                                        title="Adicionar neste dia"
                                        @click="abrirNovo(dia.iso)">+</button>
                                </div>
                                <button v-for="item in dia.itens" :key="item.id"
                                    class="w-full text-left mb-1 px-1.5 py-1 rounded border-l-4 text-[11px] leading-tight hover:brightness-95"
                                    :class="[CORES[item.categoria], item.estado === 'concluido' ? 'opacity-50 line-through' : '', item.atrasado ? 'ring-1 ring-red-400' : '']"
                                    @click="abrirEdicao(item)">
                                    <span class="block font-medium truncate">{{ item.titulo }}</span>
                                    <span v-if="item.valor" class="block">{{ euros(item.valor) }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Próximos 60 dias -->
                    <div class="bg-white rounded-lg shadow p-4">
                        <h3 class="font-semibold text-gray-800 mb-3">Próximos 60 dias</h3>
                        <p v-if="!proximos.length" class="text-sm text-gray-500">Nada agendado.</p>
                        <ul class="space-y-2">
                            <li v-for="item in proximos" :key="item.id"
                                class="border-l-4 rounded px-2 py-2 text-sm"
                                :class="CORES[item.categoria]">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-medium">{{ item.titulo }}</p>
                                        <p class="text-xs opacity-75">
                                            {{ dataCurta(item.data) }}
                                            <span v-if="item.dias_para_prazo === 0"> · hoje</span>
                                            <span v-else-if="item.dias_para_prazo > 0"> · daqui a {{ item.dias_para_prazo }} dias</span>
                                            <span v-if="item.entidade"> · {{ item.entidade }}</span>
                                        </p>
                                        <p v-if="item.contexto.length" class="text-xs opacity-60">{{ item.contexto.join(' · ') }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p v-if="item.valor" class="font-semibold">{{ euros(item.valor) }}</p>
                                        <button v-if="permissoes.editar" class="text-xs underline opacity-75"
                                            @click="abrirConcluir(item)">feito</button>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: criar / editar -->
        <Modal :show="mostrarForm" max-width="2xl" @close="mostrarForm = false">
            <form class="p-6 space-y-4" @submit.prevent="submeter">
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ emEdicao ? 'Editar compromisso' : 'Novo compromisso' }}
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <InputLabel for="titulo" value="Título" />
                        <TextInput id="titulo" v-model="form.titulo" type="text" class="mt-1 block w-full" required />
                        <InputError :message="erros.titulo" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="categoria" value="Categoria" />
                        <select id="categoria" v-model="form.categoria" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option v-for="c in opcoes.categorias" :key="c" :value="c">{{ ROTULOS_CATEGORIA[c] }}</option>
                        </select>
                        <InputError :message="erros.categoria" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="tipo" value="Tipo (IMI, Seguro, Poda…)" />
                        <TextInput id="tipo" v-model="form.tipo" type="text" class="mt-1 block w-full" />
                    </div>

                    <div>
                        <InputLabel for="data" value="Data" />
                        <TextInput id="data" v-model="form.data" type="date" class="mt-1 block w-full" required />
                        <InputError :message="erros.data" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="valor" value="Valor (€)" />
                        <TextInput id="valor" v-model="form.valor" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                        <InputError :message="erros.valor" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="entidade" value="Entidade" />
                        <TextInput id="entidade" v-model="form.entidade" type="text" class="mt-1 block w-full"
                            placeholder="Finanças, Segurança Social, seguradora…" />
                    </div>

                    <div>
                        <InputLabel for="aviso" value="Avisar com (dias)" />
                        <TextInput id="aviso" v-model="form.antecedencia_aviso_dias" type="number" min="0" class="mt-1 block w-full" />
                    </div>

                    <div>
                        <InputLabel for="recorrencia" value="Repetição" />
                        <select id="recorrencia" v-model="form.recorrencia" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option v-for="r in opcoes.recorrencias" :key="r" :value="r">{{ ROTULOS_RECORRENCIA[r] }}</option>
                        </select>
                    </div>

                    <div v-if="form.recorrencia !== 'nenhuma'">
                        <InputLabel for="recorrencia_fim" value="Repetir até (opcional)" />
                        <TextInput id="recorrencia_fim" v-model="form.recorrencia_fim" type="date" class="mt-1 block w-full" />
                        <InputError :message="erros.recorrencia_fim" class="mt-1" />
                    </div>

                    <template v-if="form.recorrencia === 'personalizada'">
                        <div>
                            <InputLabel for="intervalo" value="A cada" />
                            <TextInput id="intervalo" v-model="form.recorrencia_intervalo" type="number" min="1" class="mt-1 block w-full" />
                            <InputError :message="erros.recorrencia_intervalo" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="unidade" value="Unidade" />
                            <select id="unidade" v-model="form.recorrencia_unidade" class="mt-1 block w-full border-gray-300 rounded-md">
                                <option value="dia">dias</option>
                                <option value="semana">semanas</option>
                                <option value="mes">meses</option>
                                <option value="ano">anos</option>
                            </select>
                        </div>
                    </template>

                    <div>
                        <InputLabel for="campanha" value="Campanha" />
                        <select id="campanha" v-model="form.campanha_id" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option value="">—</option>
                            <option v-for="c in opcoes.campanhas" :key="c.id" :value="c.id">{{ c.nome }}</option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="parcela" value="Parcela" />
                        <select id="parcela" v-model="form.parcela_id" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option value="">—</option>
                            <option v-for="p in opcoes.parcelas" :key="p.id" :value="p.id">{{ p.nome }}</option>
                        </select>
                    </div>

                    <div v-if="form.categoria === 'manutencao'">
                        <InputLabel for="maquina" value="Máquina" />
                        <select id="maquina" v-model="form.maquina_id" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option value="">—</option>
                            <option v-for="m in opcoes.maquinas" :key="m.id" :value="m.id">{{ m.nome }}</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel for="notas" value="Notas" />
                        <textarea id="notas" v-model="form.notas" rows="2"
                            class="mt-1 block w-full border-gray-300 rounded-md"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <div>
                        <DangerButton v-if="emEdicao && permissoes.eliminar" type="button"
                            @click="eliminar(emEdicao, false); mostrarForm = false">
                            Eliminar
                        </DangerButton>
                        <button v-if="emEdicao && permissoes.eliminar && emEdicao.recorrencia !== 'nenhuma'"
                            type="button" class="ml-3 text-sm text-red-600 underline"
                            @click="eliminar(emEdicao, true); mostrarForm = false">
                            Eliminar série
                        </button>
                    </div>
                    <div class="flex gap-3">
                        <SecondaryButton type="button" @click="mostrarForm = false">Cancelar</SecondaryButton>
                        <PrimaryButton :disabled="aGuardar">Guardar</PrimaryButton>
                    </div>
                </div>
            </form>
        </Modal>

        <!-- Modal: concluir -->
        <Modal :show="mostrarConcluir" max-width="md" @close="mostrarConcluir = false">
            <form class="p-6 space-y-4" @submit.prevent="confirmarConcluir">
                <h3 class="text-lg font-semibold text-gray-900">Marcar como feito</h3>
                <p class="text-sm text-gray-600">{{ aConcluir?.titulo }}</p>

                <div>
                    <InputLabel for="valor_pago" value="Valor pago (€)" />
                    <TextInput id="valor_pago" v-model="formConcluir.valor_pago" type="number" step="0.01" min="0"
                        class="mt-1 block w-full" />
                    <InputError :message="formConcluir.errors.valor_pago" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="data_conclusao" value="Data" />
                    <TextInput id="data_conclusao" v-model="formConcluir.data_conclusao" type="date" class="mt-1 block w-full" />
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" v-model="formConcluir.criar_custo" class="rounded border-gray-300" />
                    Registar como custo (entra na tesouraria)
                </label>

                <div class="flex justify-end gap-3 pt-2">
                    <SecondaryButton type="button" @click="mostrarConcluir = false">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="formConcluir.processing">Confirmar</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
