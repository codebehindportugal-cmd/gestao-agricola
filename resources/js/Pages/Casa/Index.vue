<script setup>
import { computed, h, onMounted, onUnmounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    baseUrl: { type: String, required: true },
    cameras: { type: Array, default: () => [] },
    modoVideo: { type: String, default: 'webrtc' },
    intervaloFeed: { type: Number, default: 5 },
    minutosSemContacto: { type: Number, default: 12 },
    kiosk: { type: Boolean, default: false },
    feed: { type: Object, required: true },
});

// No ecrã de parede não queremos barra de navegação nem margens.
const Kiosk = (_, { slots }) =>
    h('div', { class: 'min-h-screen bg-neutral-950' }, slots.default?.());
const Moldura = computed(() => (props.kiosk ? Kiosk : AppLayout));

const feed = ref(props.feed);
const offline = ref(false);
const relogio = ref(new Date());
let temporizadorFeed = null;
let temporizadorRelogio = null;

async function actualizar() {
    // Poupar pedidos quando ninguém está a olhar.
    if (document.hidden) return;

    try {
        const resposta = await fetch(route('app.casa.feed'), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        });

        // Sessão expirada: recarregar leva ao login em vez de mostrar dados velhos.
        if (resposta.status === 401 || resposta.status === 419) {
            window.location.reload();
            return;
        }
        if (!resposta.ok) throw new Error(resposta.status);

        feed.value = await resposta.json();
        offline.value = false;
    } catch {
        offline.value = true;
    }
}

const semContacto = computed(() => {
    if (!feed.value.ultimoContacto) return true;
    const decorrido = Date.now() - new Date(feed.value.ultimoContacto).getTime();
    return decorrido > props.minutosSemContacto * 60 * 1000;
});

const activos = computed(() =>
    (feed.value.dispositivos ?? []).filter((d) => d.estado === 'on')
);

const calendario = computed(() => feed.value.calendario);

const totalCalendario = computed(() => {
    const c = calendario.value;
    if (!c) return 0;
    return c.hoje.length + c.proximos.length + c.atrasados.length;
});

const CATEGORIAS = {
    pagamento: { rotulo: 'Pagamento', cor: 'bg-amber-500/15 text-amber-300' },
    tarefa_agricola: { rotulo: 'Campo', cor: 'bg-emerald-500/15 text-emerald-300' },
    manutencao: { rotulo: 'Manutenção', cor: 'bg-sky-500/15 text-sky-300' },
    prazo_legal: { rotulo: 'Prazo legal', cor: 'bg-violet-500/15 text-violet-300' },
};

const categoria = (c) => CATEGORIAS[c] ?? { rotulo: c, cor: 'bg-neutral-700 text-neutral-300' };

const fHora = new Intl.DateTimeFormat('pt-PT', { hour: '2-digit', minute: '2-digit' });
const fData = new Intl.DateTimeFormat('pt-PT', { day: '2-digit', month: 'short' });
const fEuro = new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' });

const horas = (iso) => (iso ? fHora.format(new Date(iso)) : '—');
const dia = (iso) => (iso ? fData.format(new Date(iso)) : '—');
const euros = (v) => (v === null || v === undefined ? null : fEuro.format(v));
const horaCurta = (h) => (h ? String(h).slice(0, 5) : null);

function haQuanto(iso) {
    const segundos = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
    if (segundos < 60) return 'agora';
    if (segundos < 3600) return `há ${Math.floor(segundos / 60)} min`;
    if (segundos < 86400) return `há ${Math.floor(segundos / 3600)} h`;
    return `há ${Math.floor(segundos / 86400)} d`;
}

const streamUrl = (cam) =>
    `${props.baseUrl}/go2rtc/stream.html?src=${encodeURIComponent(cam)}&mode=${props.modoVideo}`;

onMounted(() => {
    temporizadorFeed = setInterval(actualizar, props.intervaloFeed * 1000);
    temporizadorRelogio = setInterval(() => (relogio.value = new Date()), 1000);
    document.addEventListener('visibilitychange', actualizar);
});

onUnmounted(() => {
    clearInterval(temporizadorFeed);
    clearInterval(temporizadorRelogio);
    document.removeEventListener('visibilitychange', actualizar);
});
</script>

<template>
    <Head title="Casa" />

    <component :is="Moldura" title="Casa">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Casa
            </h2>
        </template>

        <div class="bg-neutral-950 p-4 text-neutral-100" :class="kiosk ? '' : 'rounded-xl'">
            <!-- cabeçalho -->
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
                <div class="flex items-baseline gap-3">
                    <span class="text-3xl font-semibold tabular-nums">
                        {{ fHora.format(relogio) }}
                    </span>
                    <span class="text-sm text-neutral-500">
                        {{ new Intl.DateTimeFormat('pt-PT', { weekday: 'long', day: 'numeric', month: 'long' }).format(relogio) }}
                    </span>
                </div>

                <div
                    v-if="offline || semContacto"
                    class="rounded bg-amber-600/20 px-3 py-1 text-sm text-amber-300"
                >
                    {{ offline ? 'Sem ligação ao site' : 'Sem dados recentes da casa' }} —
                    o painel pode estar desactualizado
                </div>
            </div>

            <div class="grid gap-4" :class="calendario ? 'xl:grid-cols-[3fr,1fr]' : ''">
                <div>
                    <!-- câmaras -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div
                            v-for="cam in cameras"
                            :key="cam"
                            class="relative aspect-video overflow-hidden rounded-lg bg-black"
                        >
                            <iframe
                                :src="streamUrl(cam)"
                                class="h-full w-full border-0"
                                allow="autoplay"
                                referrerpolicy="no-referrer"
                                loading="lazy"
                            />
                            <span
                                class="pointer-events-none absolute bottom-2 left-2 rounded bg-black/70 px-2 py-0.5 text-xs uppercase tracking-wide"
                            >
                                {{ cam }}
                            </span>
                        </div>

                        <p v-if="!cameras.length" class="text-sm text-neutral-500">
                            Nenhuma câmara configurada em <code>CASA_CAMERAS</code>.
                        </p>
                    </div>

                    <!-- sensores accionados agora -->
                    <div class="mt-5">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                            Estado
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="d in activos"
                                :key="d.entity_id"
                                class="rounded-full bg-red-500/20 px-3 py-1 text-sm text-red-300"
                            >
                                {{ d.zona ? d.zona + ' · ' : '' }}{{ d.nome }}
                            </span>
                            <span v-if="!activos.length" class="text-sm text-neutral-500">
                                Nada accionado.
                            </span>
                        </div>
                    </div>

                    <!-- histórico -->
                    <div class="mt-5">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                            Movimento
                        </h3>
                        <ul class="divide-y divide-neutral-800 text-sm">
                            <li
                                v-for="e in feed.eventos"
                                :key="e.id"
                                class="flex items-baseline gap-3 py-2"
                            >
                                <span class="w-12 shrink-0 tabular-nums text-neutral-500">
                                    {{ horas(e.ocorreu_em) }}
                                </span>
                                <span class="w-28 shrink-0 truncate text-neutral-400">
                                    {{ e.zona || '—' }}
                                </span>
                                <span class="truncate">{{ e.nome }}</span>
                                <span class="ml-auto shrink-0 text-xs text-neutral-600">
                                    {{ haQuanto(e.ocorreu_em) }}
                                </span>
                            </li>
                            <li v-if="!feed.eventos?.length" class="py-2 text-neutral-500">
                                Sem registos.
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- calendário -->
                <aside v-if="calendario" class="space-y-5">
                    <section v-if="calendario.atrasados.length">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-red-400">
                            Atrasados
                        </h3>
                        <ul class="space-y-2">
                            <li
                                v-for="c in calendario.atrasados"
                                :key="c.id"
                                class="rounded-lg border border-red-500/30 bg-red-500/5 p-2"
                            >
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="truncate text-sm">{{ c.titulo }}</span>
                                    <span class="shrink-0 text-xs tabular-nums text-red-400">
                                        {{ dia(c.data) }}
                                    </span>
                                </div>
                                <div class="mt-1 flex items-center gap-2">
                                    <span
                                        class="rounded px-1.5 py-0.5 text-[11px]"
                                        :class="categoria(c.categoria).cor"
                                    >
                                        {{ categoria(c.categoria).rotulo }}
                                    </span>
                                    <span v-if="c.valor" class="text-xs tabular-nums text-neutral-400">
                                        {{ euros(c.valor) }}
                                    </span>
                                </div>
                            </li>
                        </ul>
                    </section>

                    <section>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                            Hoje
                        </h3>
                        <ul class="space-y-2">
                            <li
                                v-for="c in calendario.hoje"
                                :key="c.id"
                                class="rounded-lg bg-neutral-900 p-2"
                            >
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="truncate text-sm">{{ c.titulo }}</span>
                                    <span
                                        v-if="horaCurta(c.hora)"
                                        class="shrink-0 text-xs tabular-nums text-neutral-400"
                                    >
                                        {{ horaCurta(c.hora) }}
                                    </span>
                                </div>
                                <div class="mt-1 flex items-center gap-2">
                                    <span
                                        class="rounded px-1.5 py-0.5 text-[11px]"
                                        :class="categoria(c.categoria).cor"
                                    >
                                        {{ categoria(c.categoria).rotulo }}
                                    </span>
                                    <span v-if="c.entidade" class="truncate text-xs text-neutral-500">
                                        {{ c.entidade }}
                                    </span>
                                    <span v-if="c.valor" class="ml-auto shrink-0 text-xs tabular-nums text-neutral-400">
                                        {{ euros(c.valor) }}
                                    </span>
                                </div>
                            </li>
                            <li v-if="!calendario.hoje.length" class="text-sm text-neutral-500">
                                Nada marcado para hoje.
                            </li>
                        </ul>
                    </section>

                    <section v-if="calendario.proximos.length">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                            A seguir
                        </h3>
                        <ul class="divide-y divide-neutral-800 text-sm">
                            <li
                                v-for="c in calendario.proximos"
                                :key="c.id"
                                class="flex items-baseline gap-3 py-1.5"
                            >
                                <span class="w-14 shrink-0 tabular-nums text-neutral-500">
                                    {{ dia(c.data) }}
                                </span>
                                <span class="truncate">{{ c.titulo }}</span>
                                <span
                                    v-if="c.valor"
                                    class="ml-auto shrink-0 text-xs tabular-nums text-neutral-500"
                                >
                                    {{ euros(c.valor) }}
                                </span>
                            </li>
                        </ul>
                    </section>

                    <p v-if="!totalCalendario" class="text-sm text-neutral-600">
                        Calendário sem compromissos pendentes.
                    </p>
                </aside>
            </div>
        </div>
    </component>
</template>
