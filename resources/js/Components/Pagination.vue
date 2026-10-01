<script setup>
defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});

const rotulo = (texto) => String(texto)
    .replace('&laquo; Previous', '‹ Anterior')
    .replace('Next &raquo;', 'Seguinte ›')
    .replace('pagination.previous', '‹ Anterior')
    .replace('pagination.next', 'Seguinte ›');
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap items-center gap-2">
        <component
            :is="link.url ? 'a' : 'span'"
            v-for="link in links"
            :key="`${link.label}-${link.url}`"
            :href="link.url || undefined"
            class="numero inline-flex min-h-[40px] min-w-[40px] items-center justify-center rounded-lg border px-3 text-sm font-medium transition"
            :class="link.active
                ? 'border-verde-700 bg-verde-700 text-white'
                : link.url
                    ? 'border-slate-300 bg-white text-slate-800 hover:bg-slate-50'
                    : 'cursor-not-allowed border-transparent text-slate-400'"
            v-html="rotulo(link.label)"
        />
    </nav>
</template>
