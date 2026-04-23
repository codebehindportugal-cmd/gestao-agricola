<script setup>
defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap items-center gap-2">
        <component
            :is="link.url ? 'a' : 'span'"
            v-for="link in links"
            :key="`${link.label}-${link.url}`"
            :href="link.url || undefined"
            class="rounded px-3 py-2 text-sm transition"
            :class="link.active
                ? 'bg-blue-600 text-white'
                : link.url
                    ? 'bg-white text-slate-700 shadow hover:bg-slate-50'
                    : 'cursor-not-allowed bg-slate-100 text-slate-400'"
            v-html="link.label"
        />
    </nav>
</template>
