<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    funcionario: { type: Object, required: true },
    token: { type: String, required: true },
});

const watchId = ref(null);
const status = ref('parado');
const lastSharedAt = ref(props.funcionario.last_shared_at);
const errorMessage = ref('');
const wakeLockActive = ref(false);
let wakeLock = null;
let retryTimer = null;

const isSharing = computed(() => watchId.value !== null);
const statusLabel = computed(() => {
    const labels = {
        parado: 'Partilha parada',
        a_pedir: 'A pedir permissão',
        ativo: 'A partilhar localização',
        erro: 'Não foi possível obter localização',
    };

    return labels[status.value] ?? 'Partilha parada';
});

const formatDate = (value) => {
    if (!value) return 'Ainda não partilhou';

    return new Intl.DateTimeFormat('pt-PT', {
        dateStyle: 'short',
        timeStyle: 'medium',
    }).format(new Date(value));
};

const sendPosition = async (position) => {
    const response = await axios.post(route('funcionarios.localizacao.store', props.token), {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy,
        speed: position.coords.speed,
        heading: position.coords.heading,
    });

    lastSharedAt.value = response.data.funcionario.location_shared_at;
    status.value = 'ativo';
    errorMessage.value = '';
};

const requestWakeLock = async () => {
    if (!('wakeLock' in navigator)) {
        wakeLockActive.value = false;
        return;
    }

    try {
        wakeLock = await navigator.wakeLock.request('screen');
        wakeLockActive.value = true;
        wakeLock.addEventListener('release', () => {
            wakeLockActive.value = false;
        });
    } catch {
        wakeLockActive.value = false;
    }
};

const releaseWakeLock = async () => {
    if (wakeLock) {
        await wakeLock.release().catch(() => {});
        wakeLock = null;
    }

    wakeLockActive.value = false;
};

const stopSharing = () => {
    if (watchId.value !== null) {
        navigator.geolocation.clearWatch(watchId.value);
        watchId.value = null;
    }

    if (retryTimer) {
        window.clearTimeout(retryTimer);
        retryTimer = null;
    }

    releaseWakeLock();

    if (status.value !== 'erro') status.value = 'parado';
};

const startSharing = async () => {
    if (!navigator.geolocation) {
        status.value = 'erro';
        errorMessage.value = 'Este telemóvel ou navegador não suporta localização.';
        return;
    }

    status.value = 'a_pedir';
    errorMessage.value = '';
    await requestWakeLock();

    watchId.value = navigator.geolocation.watchPosition(
        (position) => {
            sendPosition(position).catch(() => {
                status.value = 'erro';
                errorMessage.value = 'Falhou o envio da localização. Verifique a internet e tente novamente.';
            });
        },
        (error) => {
            status.value = 'erro';
            errorMessage.value = error.code === error.PERMISSION_DENIED
                ? 'A permissão de localização foi recusada no telemóvel.'
                : 'Não foi possível obter a localização atual.';
            if (error.code === error.PERMISSION_DENIED) {
                stopSharing();
                return;
            }

            if (retryTimer) {
                return;
            }

            retryTimer = window.setTimeout(() => {
                stopSharing();
                startSharing();
            }, 15000);
        },
        {
            enableHighAccuracy: true,
            maximumAge: 15000,
            timeout: 20000,
        },
    );
};

const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible' && isSharing.value && !wakeLockActive.value) {
        requestWakeLock();
    }
};

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    stopSharing();
});
</script>

<template>
    <Head title="Partilhar localização" />

    <main class="min-h-screen bg-slate-950 text-white">
        <div class="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-5 py-8">
            <section class="rounded-[28px] border border-white/10 bg-white/10 p-6 shadow-2xl backdrop-blur">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-emerald-300">Gestão agrícola</p>
                <h1 class="mt-3 text-3xl font-black">Partilhar localização</h1>
                <p class="mt-3 text-sm leading-6 text-slate-200">
                    {{ funcionario.nome }} · {{ funcionario.cargo || 'Trabalhador' }}
                </p>

                <div class="mt-6 rounded-3xl bg-slate-900/70 p-5">
                    <p class="text-sm font-semibold text-slate-300">Estado</p>
                    <p class="mt-2 text-2xl font-black" :class="isSharing ? 'text-emerald-300' : 'text-white'">{{ statusLabel }}</p>
                    <p class="mt-2 text-sm text-slate-300">Último envio: {{ formatDate(lastSharedAt) }}</p>
                    <p v-if="isSharing" class="mt-2 text-sm" :class="wakeLockActive ? 'text-emerald-200' : 'text-amber-200'">
                        {{ wakeLockActive ? 'Ecrã mantido ligado neste navegador.' : 'Mantenha o ecrã ligado para continuar a enviar.' }}
                    </p>
                    <p v-if="errorMessage" class="mt-3 rounded-2xl border border-red-400/40 bg-red-500/10 p-3 text-sm text-red-100">
                        {{ errorMessage }}
                    </p>
                </div>

                <div class="mt-6 grid gap-3">
                    <PrimaryButton
                        v-if="!isSharing"
                        class="justify-center rounded-full bg-emerald-500 px-5 py-4 text-base normal-case tracking-normal text-white hover:bg-emerald-400 focus:bg-emerald-400"
                        @click="startSharing"
                    >
                        Começar a partilhar
                    </PrimaryButton>
                    <SecondaryButton
                        v-else
                        class="justify-center rounded-full border-white/20 bg-white px-5 py-4 text-base normal-case tracking-normal text-slate-900"
                        @click="stopSharing"
                    >
                        Parar partilha
                    </SecondaryButton>
                </div>

                <p class="mt-5 text-xs leading-5 text-slate-400">
                    Para partilha contínua por browser, deixe esta página aberta durante o trabalho. Se o telemóvel bloquear a página em segundo plano, o envio pode parar.
                </p>
            </section>
        </div>
    </main>
</template>
