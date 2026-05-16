<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import WorkerLocationsMap from '@/Components/WorkerLocationsMap.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    funcionarios: { type: Array, default: () => [] },
    summary: { type: Object, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const copiedId = ref(null);
const lastPanelRefresh = ref(new Date());
let refreshInterval = null;

const sortedFuncionarios = computed(() => [...props.funcionarios].sort((a, b) => {
    if (a.has_location !== b.has_location) return a.has_location ? -1 : 1;

    return String(a.nome).localeCompare(String(b.nome));
}));

const formatDate = (value) => {
    if (!value) return 'Ainda sem localização';

    return new Intl.DateTimeFormat('pt-PT', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
};

const freshnessClass = (value) => {
    if (!value) return 'bg-slate-100 text-slate-600';

    const minutes = (Date.now() - new Date(value).getTime()) / 60000;

    if (minutes <= 15) return 'bg-emerald-50 text-emerald-700';
    if (minutes <= 60) return 'bg-amber-50 text-amber-700';

    return 'bg-slate-100 text-slate-600';
};

const freshnessLabel = (value) => {
    if (!value) return 'Sem sinal';

    const minutes = Math.max(0, Math.round((Date.now() - new Date(value).getTime()) / 60000));

    if (minutes < 1) return 'Agora';
    if (minutes < 60) return `${minutes} min`;

    return `${Math.round(minutes / 60)} h`;
};

const copyShareUrl = async (funcionario) => {
    await navigator.clipboard.writeText(funcionario.share_url);
    copiedId.value = funcionario.id;
    setTimeout(() => {
        if (copiedId.value === funcionario.id) copiedId.value = null;
    }, 1800);
};

const refreshToken = (funcionario) => {
    if (!window.confirm(`Renovar o link de localização de ${funcionario.nome}? O link antigo deixa de funcionar.`)) return;

    router.post(route('app.funcionarios.localizacao.refresh', funcionario.id), {}, {
        preserveScroll: true,
    });
};

const refreshPage = () => {
    router.reload({
        only: ['funcionarios', 'summary'],
        preserveScroll: true,
        onSuccess: () => {
            lastPanelRefresh.value = new Date();
        },
    });
};

onMounted(() => {
    refreshInterval = window.setInterval(refreshPage, 30000);
});

onBeforeUnmount(() => {
    if (refreshInterval) {
        window.clearInterval(refreshInterval);
        refreshInterval = null;
    }
});
</script>

<template>
    <Head title="Localização da equipa" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-700">Mão de obra</p>
                    <h1 class="mt-2 text-3xl font-black text-slate-900">Localização dos trabalhadores</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        Acompanhe a última posição partilhada pelos telemóveis. Cada trabalhador precisa abrir o seu link e autorizar a localização.
                    </p>
                </div>
                <PrimaryButton class="rounded-full bg-emerald-700 px-5 py-3 text-sm normal-case tracking-normal hover:bg-emerald-600 focus:bg-emerald-600" @click="refreshPage">
                    Atualizar mapa
                </PrimaryButton>
            </div>
        </template>

        <div class="bg-[linear-gradient(180deg,_#f8fafc_0%,_#eef7f0_100%)] py-10">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <div v-if="flashSuccess" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800">
                    {{ flashSuccess }}
                </div>

                <div class="rounded-2xl border border-emerald-100 bg-white px-5 py-4 text-sm text-slate-600 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                    O mapa atualiza automaticamente a cada 30 segundos. Última atualização do painel:
                    <span class="font-semibold text-slate-900">{{ formatDate(lastPanelRefresh) }}</span>
                </div>

                <section class="grid gap-4 md:grid-cols-3">
                    <article class="rounded-[24px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.22)]">
                        <p class="text-sm font-medium text-slate-500">Trabalhadores ativos</p>
                        <p class="mt-3 text-4xl font-black text-slate-900">{{ summary.ativos }}</p>
                    </article>
                    <article class="rounded-[24px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.22)]">
                        <p class="text-sm font-medium text-slate-500">Com localização</p>
                        <p class="mt-3 text-4xl font-black text-emerald-700">{{ summary.com_localizacao }}</p>
                    </article>
                    <article class="rounded-[24px] bg-white p-6 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.22)]">
                        <p class="text-sm font-medium text-slate-500">Atualizados nos últimos 15 min</p>
                        <p class="mt-3 text-4xl font-black text-emerald-700">{{ summary.recentes }}</p>
                    </article>
                </section>

                <section class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
                    <div class="rounded-[28px] bg-white p-4 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.22)]">
                        <WorkerLocationsMap :funcionarios="funcionarios" />
                    </div>

                    <aside class="flex flex-col gap-3">
                        <article v-for="funcionario in sortedFuncionarios" :key="funcionario.id" class="rounded-[24px] bg-white p-5 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.18)]">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h2 class="text-lg font-black text-slate-900">{{ funcionario.nome }}</h2>
                                    <p class="mt-1 text-sm text-slate-500">{{ funcionario.cargo || 'Sem função' }}</p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="freshnessClass(funcionario.location_shared_at)">
                                    {{ freshnessLabel(funcionario.location_shared_at) }}
                                </span>
                            </div>

                            <dl class="mt-4 grid gap-3 text-sm">
                                <div class="rounded-2xl bg-slate-50 p-3">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Último sinal</dt>
                                    <dd class="mt-1 font-medium text-slate-800">{{ formatDate(funcionario.location_shared_at) }}</dd>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-2xl bg-slate-50 p-3">
                                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Precisão</dt>
                                        <dd class="mt-1 font-medium text-slate-800">{{ funcionario.accuracy ? `${funcionario.accuracy} m` : '-' }}</dd>
                                    </div>
                                    <div class="rounded-2xl bg-slate-50 p-3">
                                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Telefone</dt>
                                        <dd class="mt-1 truncate font-medium text-slate-800">{{ funcionario.telefone || '-' }}</dd>
                                    </div>
                                </div>
                            </dl>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <SecondaryButton type="button" class="rounded-full px-4 py-2 text-xs normal-case tracking-normal" @click="copyShareUrl(funcionario)">
                                    {{ copiedId === funcionario.id ? 'Link copiado' : 'Copiar link' }}
                                </SecondaryButton>
                                <SecondaryButton type="button" class="rounded-full px-4 py-2 text-xs normal-case tracking-normal" @click="refreshToken(funcionario)">
                                    Renovar link
                                </SecondaryButton>
                            </div>
                        </article>
                    </aside>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
