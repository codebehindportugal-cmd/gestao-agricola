<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="min-h-[44px] rounded-lg border-slate-300 bg-white text-sm text-slate-900 placeholder:text-slate-400 focus:border-verde-600 focus:ring-verde-600"
        v-model="model"
        ref="input"
    />
</template>
