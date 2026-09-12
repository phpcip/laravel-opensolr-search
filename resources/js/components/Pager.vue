<script setup>
/**
 * Previous / next navigation; the parent decides what a page change means.
 */
defineProps({
    page: { type: Number, default: 1 },
    pages: { type: Number, default: 1 },
});

defineEmits(['change']);
</script>

<template>
    <nav v-if="pages > 1" class="pager" aria-label="Pagination">
        <button type="button" class="btn" :disabled="page <= 1" @click="$emit('change', page - 1)">Previous</button>
        <span>Page {{ page }} of {{ pages }}</span>
        <button type="button" class="btn" :disabled="page >= pages" @click="$emit('change', page + 1)">Next</button>
    </nav>
</template>
