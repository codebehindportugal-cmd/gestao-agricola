<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref } from 'vue';

// Chave da API no perfil (29/09/2026). A chave em claro so existe na resposta
// ao pedido de gerar: fica neste componente ate a pagina sair, e nao volta.
const props = defineProps({
    apiToken: { type: Object, required: true },
});

const estado = ref({ ...props.apiToken });
const password = ref('');
const erro = ref('');
const aGerar = ref(false);
const chaveNova = ref('');
const copiada = ref(false);

const gerar = async () => {
    erro.value = '';
    aGerar.value = true;
    try {
        const { data } = await window.axios.post(route('profile.api-token.store'), { password: password.value });
        chaveNova.value = data.token;
        estado.value = { ...estado.value, ...data.estado };
        password.value = '';
    } catch (e) {
        erro.value = e.response?.data?.errors?.password?.[0]
            ?? e.response?.data?.message
            ?? 'Não foi possível gerar a chave.';
    } finally {
        aGerar.value = false;
    }
};

const revogar = async () => {
    if (!window.confirm('Revogar a chave? O chat deixa de conseguir escrever na API até gerares outra.')) return;
    const { data } = await window.axios.delete(route('profile.api-token.destroy'));
    estado.value = { ...estado.value, ...data.estado };
    chaveNova.value = '';
};

const copiar = async () => {
    await navigator.clipboard.writeText(chaveNova.value);
    copiada.value = true;
    setTimeout(() => (copiada.value = false), 2000);
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">Chave da API</h2>
            <p class="mt-1 text-sm text-gray-600">
                Serve para o chat registar operações, colheitas, faturas e custos na API (/api/v1).
                Gerar uma nova desliga a anterior.
            </p>
        </header>

        <p v-if="!apiToken.pode" class="mt-4 text-sm text-gray-600">
            A tua conta não tem permissão para escrever na API. Fala com um administrador.
        </p>

        <template v-else>
            <p class="mt-4 text-sm text-gray-700">
                <template v-if="estado.existe">
                    Chave activa desde {{ estado.criada_em }} · último uso: {{ estado.ultimo_uso ?? 'nunca usada' }}
                </template>
                <template v-else>Ainda não tens chave.</template>
            </p>

            <div v-if="chaveNova" class="mt-4 rounded-md border border-amber-300 bg-amber-50 p-4">
                <p class="text-sm font-medium text-amber-900">Copia-a agora — não volta a ser mostrada.</p>
                <code class="mt-2 block break-all rounded bg-white p-2 text-sm select-all">{{ chaveNova }}</code>
                <button type="button" class="mt-2 text-sm font-medium text-amber-900 underline" @click="copiar">
                    {{ copiada ? 'Copiada' : 'Copiar' }}
                </button>
            </div>

            <form class="mt-6 space-y-4" @submit.prevent="gerar">
                <div>
                    <InputLabel for="api_token_password" value="Password actual" />
                    <TextInput
                        id="api_token_password"
                        v-model="password"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="current-password"
                    />
                    <InputError :message="erro" class="mt-2" />
                </div>

                <div class="flex items-center gap-4">
                    <PrimaryButton :disabled="aGerar || !password">
                        {{ estado.existe ? 'Gerar nova chave da API' : 'Gerar chave da API' }}
                    </PrimaryButton>
                    <DangerButton v-if="estado.existe" type="button" @click="revogar">Revogar</DangerButton>
                </div>
            </form>
        </template>
    </section>
</template>
