<script setup>
defineProps({
    value: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <label class="block text-xs font-semibold text-slate-700 mb-1">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
