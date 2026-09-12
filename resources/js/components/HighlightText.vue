<script setup>
/**
 * Renders highlight segments from the API as text nodes, wrapping only the hits in <mark>.
 * Nothing here is ever inserted as HTML.
 */
defineProps({
    segments: { type: Array, default: () => [] },
});
</script>

<template>
    <template v-for="(segment, index) in segments" :key="index">
        <mark v-if="segment.h">{{ segment.t }}</mark>
        <template v-else>{{ segment.t }}</template>
    </template>
</template>
